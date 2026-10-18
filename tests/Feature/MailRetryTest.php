<?php

namespace Tests\Feature;

use App\Enums\CertificateStatus;
use App\Jobs\SendCertificateJob;
use App\Jobs\SendLoggedMailJob;
use App\Mail\CertificateIssuedMail;
use App\Mail\OrganizationWelcomeMail;
use App\Models\Certificate;
use App\Models\MailLog;
use App\Models\Organization;
use App\Services\LoggedMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MailRetryTest extends TestCase
{
    use RefreshDatabase;

    private function queuedLog(): MailLog
    {
        Queue::fake();
        $org = Organization::factory()->create(['email' => 'ops@acme.test']);

        return app(LoggedMailer::class)->queue(new OrganizationWelcomeMail($org, 'https://x.test/login', false), $org->email);
    }

    public function test_logged_mailer_records_payload_before_dispatching(): void
    {
        $log = $this->queuedLog();

        $this->assertSame(MailLog::STATUS_QUEUED, $log->status);
        $this->assertSame('ops@acme.test', $log->recipient_email);
        $this->assertSame('Welcome to '.config('mail.brand_name'), $log->subject);
        $this->assertSame(0, $log->attempts);
        $this->assertInstanceOf(OrganizationWelcomeMail::class, $log->mailable());
        $this->assertTrue($log->mailable()->hasTo('ops@acme.test'));
        Queue::assertPushed(SendLoggedMailJob::class, fn ($job) => $job->mailLogId === $log->id);
    }

    public function test_job_sends_payload_and_marks_row_sent(): void
    {
        Mail::fake();
        $log = $this->queuedLog();

        (new SendLoggedMailJob($log->id))->handle();

        Mail::assertSent(OrganizationWelcomeMail::class, fn ($mail) => $mail->hasTo('ops@acme.test'));
        $log->refresh();
        $this->assertSame(MailLog::STATUS_SENT, $log->status);
        $this->assertSame(1, $log->attempts);
        $this->assertNotNull($log->sent_at);
    }

    public function test_job_records_failure_on_row_instead_of_failing(): void
    {
        $log = $this->queuedLog();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP down'));

        (new SendLoggedMailJob($log->id))->handle();

        $log->refresh();
        $this->assertSame(MailLog::STATUS_FAILED, $log->status);
        $this->assertSame('SMTP down', $log->error);
        $this->assertSame(1, $log->attempts);
    }

    public function test_sent_row_is_never_sent_twice(): void
    {
        Mail::fake();
        $log = $this->queuedLog();
        $log->markSent();

        (new SendLoggedMailJob($log->id))->handle();

        Mail::assertNothingSent();
        $this->assertSame(0, $log->fresh()->attempts);
    }

    public function test_retry_command_redispatches_stale_queued_and_failed_rows_only(): void
    {
        Queue::fake();
        $base = ['recipient_email' => 'a@b.test', 'payload' => MailLog::encodePayload(new OrganizationWelcomeMail(Organization::factory()->create(), 'u', false))];

        $failed = MailLog::forceCreate($base + ['status' => 'failed', 'attempts' => 1, 'last_attempted_at' => now()->subMinutes(6)]);
        $stuckQueued = MailLog::forceCreate($base + ['status' => 'queued', 'created_at' => now()->subMinutes(6)]);
        $stuckSending = MailLog::forceCreate($base + ['status' => 'sending', 'attempts' => 1, 'last_attempted_at' => now()->subMinutes(20)]);
        $justFailed = MailLog::forceCreate($base + ['status' => 'failed', 'attempts' => 1, 'last_attempted_at' => now()->subMinute()]);
        $freshQueued = MailLog::forceCreate($base + ['status' => 'queued']);
        $inFlight = MailLog::forceCreate($base + ['status' => 'sending', 'attempts' => 1, 'last_attempted_at' => now()->subMinutes(2)]);
        $sent = MailLog::forceCreate($base + ['status' => 'sent', 'attempts' => 1, 'last_attempted_at' => now()->subHour()]);
        $capped = MailLog::forceCreate($base + ['status' => 'failed', 'attempts' => config('mail.retry.max_attempts'), 'last_attempted_at' => now()->subHour()]);

        $this->artisan('mail:retry-pending')->assertSuccessful();

        $pushed = collect(Queue::pushed(SendLoggedMailJob::class))->pluck('mailLogId')->sort()->values()->all();
        $this->assertSame([$failed->id, $stuckQueued->id, $stuckSending->id], $pushed);
        $this->assertNotContains($justFailed->id, $pushed);
        $this->assertNotContains($freshQueued->id, $pushed);
        $this->assertNotContains($inFlight->id, $pushed);
        $this->assertNotContains($sent->id, $pushed);
        $this->assertNotContains($capped->id, $pushed);
    }

    public function test_retry_command_requeues_failed_certificate_mail(): void
    {
        Queue::fake();
        $certificate = Certificate::factory()->create(['status' => CertificateStatus::Failed, 'send_error' => 'SMTP down']);
        MailLog::create([
            'mailable_type' => CertificateIssuedMail::class,
            'certificate_id' => $certificate->id,
            'recipient_email' => $certificate->recipient->email,
            'status' => 'failed',
            'attempts' => 3,
            'last_attempted_at' => now()->subMinutes(10),
        ]);

        // A Failed certificate whose row is still within the cooldown must wait.
        $recent = Certificate::factory()->create(['status' => CertificateStatus::Failed]);
        MailLog::create([
            'mailable_type' => CertificateIssuedMail::class,
            'certificate_id' => $recent->id,
            'recipient_email' => $recent->recipient->email,
            'status' => 'failed',
            'attempts' => 3,
            'last_attempted_at' => now()->subMinute(),
        ]);

        $this->artisan('mail:retry-pending')->assertSuccessful();

        Queue::assertPushed(SendCertificateJob::class, 1);
        Queue::assertPushed(SendCertificateJob::class, fn ($job) => $job->certificateId === $certificate->id);
        $this->assertSame(CertificateStatus::Queued, $certificate->fresh()->status);
        $this->assertSame(CertificateStatus::Failed, $recent->fresh()->status);
    }

    public function test_certificate_job_keeps_one_row_per_send_cycle(): void
    {
        $certificate = Certificate::factory()->create(['status' => CertificateStatus::Queued, 'uploaded_file_path' => 'manual.pdf']);
        $job = new SendCertificateJob($certificate->id);

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP down'));

        try {
            $job->handle(app(\App\Services\CertificateRenderService::class));
        } catch (\RuntimeException) {
        }
        $job->failed(new \RuntimeException('SMTP down'));

        $rows = MailLog::where('certificate_id', $certificate->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame(MailLog::STATUS_FAILED, $rows->first()->status);
        $this->assertSame(1, $rows->first()->attempts);
        $this->assertSame('SMTP down', $rows->first()->error);
    }
}
