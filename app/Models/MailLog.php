<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Throwable;

class MailLog extends Model
{
    use HasUuid;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'mailable_type',
        'certificate_id',
        'recipient_email',
        'subject',
        'status',
        'error',
        'payload',
        'attempts',
        'last_attempted_at',
        'sent_at',
    ];

    protected $hidden = ['payload'];

    // Mirror the column defaults so a freshly created model reads the same as a reloaded one.
    protected $attributes = [
        'status' => self::STATUS_QUEUED,
        'attempts' => 0,
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'last_attempted_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    // --- payload ------------------------------------------------------------

    public static function encodePayload(Mailable $mailable): string
    {
        return base64_encode(serialize($mailable));
    }

    /**
     * Rebuild the mailable stored on this row (recipients included).
     */
    public function mailable(): Mailable
    {
        $mailable = unserialize(base64_decode($this->payload));

        if (! $mailable instanceof Mailable) {
            throw new \RuntimeException('Mail log '.$this->id.' does not hold a mailable payload.');
        }

        return $mailable;
    }

    // --- status transitions -------------------------------------------------

    /**
     * Atomically take the row for a send attempt. Returns false when another
     * worker already holds it or it was sent meanwhile, so a duplicate
     * dispatch (e.g. the retry schedule racing a backlogged worker) never
     * results in a second email.
     */
    public function claimForSending(): bool
    {
        $claimed = static::whereKey($this->id)
            ->where(function (Builder $q) {
                $q->whereIn('status', [self::STATUS_QUEUED, self::STATUS_FAILED])
                    ->orWhere(fn (Builder $q) => $q->where('status', self::STATUS_SENDING)
                        ->where('last_attempted_at', '<', now()->subMinutes(config('mail.retry.stale_after_minutes'))));
            })
            ->update([
                'status' => self::STATUS_SENDING,
                'attempts' => DB::raw('attempts + 1'),
                'last_attempted_at' => now(),
            ]);

        if ($claimed) {
            $this->refresh();
        }

        return $claimed === 1;
    }

    /**
     * Non-atomic variant for senders that already guard against double runs
     * (SendCertificateJob checks the certificate status instead).
     */
    public function markSending(): void
    {
        $this->forceFill([
            'status' => self::STATUS_SENDING,
            'attempts' => $this->attempts + 1,
            'last_attempted_at' => now(),
        ])->save();
    }

    public function markSent(): void
    {
        $this->forceFill([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
            'error' => null,
        ])->save();
    }

    public function markFailed(?Throwable $exception): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'error' => mb_strimwidth($exception?->getMessage() ?? 'Unknown error', 0, 2000, '…'),
        ])->save();
    }

    /**
     * The open (not yet sent) row for a certificate mail, or a fresh one.
     * SendCertificateJob keeps one row per send cycle instead of a new row
     * per attempt, so the attempts counter and the retry cap mean something.
     */
    public static function openFor(Certificate $certificate, string $mailableType, string $subject): self
    {
        return static::where('certificate_id', $certificate->id)
            ->where('mailable_type', $mailableType)
            ->whereIn('status', [self::STATUS_QUEUED, self::STATUS_SENDING, self::STATUS_FAILED])
            ->latest()
            ->first()
            ?? static::create([
                'mailable_type' => $mailableType,
                'certificate_id' => $certificate->id,
                'recipient_email' => $certificate->recipient->email,
                'subject' => $subject,
                'status' => self::STATUS_QUEUED,
            ]);
    }

    // --- scopes -------------------------------------------------------------

    /**
     * Rows the 5-minute retry schedule should pick up: not sent, under the
     * attempt cap, and not touched within the cooldown (or stuck in `sending`
     * long enough that the worker must have died mid-send).
     */
    public function scopeRetryable(Builder $query): Builder
    {
        $cooldown = now()->subMinutes(config('mail.retry.cooldown_minutes'));
        $stale = now()->subMinutes(config('mail.retry.stale_after_minutes'));

        return $query
            ->where('attempts', '<', config('mail.retry.max_attempts'))
            ->where(function (Builder $q) use ($cooldown, $stale) {
                $q->where(fn (Builder $q) => $q->whereIn('status', [self::STATUS_QUEUED, self::STATUS_FAILED])
                    ->whereRaw('COALESCE(last_attempted_at, created_at) <= ?', [$cooldown]))
                    ->orWhere(fn (Builder $q) => $q->where('status', self::STATUS_SENDING)
                        ->where('last_attempted_at', '<=', $stale));
            });
    }
}
