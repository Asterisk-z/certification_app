<?php

namespace App\Jobs;

use App\Enums\UserRole;
use App\Mail\NewsletterMail;
use App\Models\Newsletter;
use App\Models\Recipient;
use App\Models\User;
use App\Services\LoggedMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class SendNewsletterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public readonly int $newsletterId)
    {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        $newsletter = Newsletter::find($this->newsletterId);

        if (! $newsletter) {
            return;
        }

        $emails = $this->audienceEmails($newsletter->audience);
        $mailer = app(LoggedMailer::class);
        $count = 0;

        foreach ($emails->chunk(100) as $chunk) {
            foreach ($chunk as $email) {
                $mailer->queue(new NewsletterMail($newsletter), $email);
                $count++;
            }
        }

        $newsletter->forceFill(['sent_at' => now(), 'recipients_count' => $count])->save();

        activity()->performedOn($newsletter)->withProperties(['recipients' => $count])->log('newsletter_sent');
    }

    private function audienceEmails(string $audience): Collection
    {
        $recipients = Recipient::pluck('email');
        $admins = User::where('role', UserRole::Admin)->pluck('email');

        $emails = match ($audience) {
            'recipients' => $recipients,
            'admins' => $admins,
            default => $recipients->merge($admins),
        };

        return $emails->map(fn ($e) => strtolower($e))->unique()->values();
    }
}
