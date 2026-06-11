<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\SendNewsletterJob;
use App\Mail\NewsletterMail;
use App\Models\Certificate;
use App\Models\MailLog;
use App\Models\Newsletter;
use App\Models\Recipient;
use App\Models\User;
use App\Notifications\AdminAlertNotification;
use App\Notifications\CertificateRevokedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationsAndNewsletterTest extends TestCase
{
    use RefreshDatabase;

    public function test_newsletter_store_queues_job(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->postJson('/api/admin/newsletters', [
            'subject' => 'June update',
            'body' => 'Hello everyone!',
            'audience' => 'all',
        ])->assertCreated();

        Queue::assertPushed(SendNewsletterJob::class, 1);
        $this->assertDatabaseHas('newsletters', ['subject' => 'June update', 'audience' => 'all']);
    }

    public function test_newsletter_job_mails_audience_and_logs(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Recipient::factory()->count(3)->create();
        $newsletter = Newsletter::create([
            'subject' => 'Hi', 'body' => 'Body', 'audience' => 'recipients', 'sent_by' => $admin->id,
        ]);

        (new SendNewsletterJob($newsletter->id))->handle();

        Mail::assertQueued(NewsletterMail::class, 3);
        $this->assertEquals(3, $newsletter->fresh()->recipients_count);
        $this->assertNotNull($newsletter->fresh()->sent_at);
        $this->assertEquals(3, MailLog::where('mailable_type', NewsletterMail::class)->count());
    }

    public function test_revoke_creates_database_notification_for_portal_user(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $portalUser = User::factory()->create(['role' => UserRole::Recipient]);
        $recipient = Recipient::factory()->create(['user_id' => $portalUser->id]);
        $certificate = Certificate::factory()->sent()->create(['recipient_id' => $recipient->id]);

        Notification::fake();

        $this->actingAs($admin)
            ->postJson("/api/admin/certificates/{$certificate->uuid}/revoke")
            ->assertOk();

        Notification::assertSentTo($portalUser, CertificateRevokedNotification::class);
    }

    public function test_notification_endpoints(): void
    {
        $user = User::factory()->create(['role' => UserRole::Recipient]);
        $user->notify(new AdminAlertNotification('test', 'Test', 'Hello'));

        $response = $this->actingAs($user)->getJson('/api/notifications')->assertOk();
        $this->assertEquals(1, $response->json('unread_count'));

        $id = $response->json('notifications.0.id');
        $this->actingAs($user)->postJson("/api/notifications/{$id}/read")->assertOk();

        $this->assertEquals(0, $this->actingAs($user)->getJson('/api/notifications')->json('unread_count'));
    }

    public function test_dashboard_stats(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Certificate::factory()->count(2)->sent()->create();
        Certificate::factory()->create();

        $response = $this->actingAs($admin)->getJson('/api/admin/dashboard/stats')->assertOk();
        $this->assertEquals(3, $response->json('totals.certificates'));
        $this->assertEquals(2, $response->json('by_status.sent'));
    }

    public function test_mail_and_activity_log_endpoints(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        MailLog::create([
            'recipient_email' => 'a@b.com', 'subject' => 'Hi', 'status' => 'sent',
        ]);
        activity()->log('something_happened');

        $this->actingAs($admin)->getJson('/api/admin/logs/mail?q=a@b.com')
            ->assertOk()->assertJsonPath('total', 1);

        $this->actingAs($admin)->getJson('/api/admin/logs/activity')
            ->assertOk();
    }
}
