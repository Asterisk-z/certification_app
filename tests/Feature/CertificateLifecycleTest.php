<?php

namespace Tests\Feature;

use App\Enums\CertificateStatus;
use App\Enums\UserRole;
use App\Jobs\ExpireCertificatesJob;
use App\Jobs\SendCertificateJob;
use App\Mail\CertificateIssuedMail;
use App\Mail\CertificateRevokedMail;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Group;
use App\Models\Recipient;
use App\Models\User;
use App\Services\CertificateRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CertificateLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private CertificateTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->template = CertificateTemplate::factory()->ready()->create([
            'user_id' => $this->admin->id,
            'code' => 'HSE',
        ]);
    }

    public function test_send_to_group_queues_certificates(): void
    {
        Queue::fake();
        $group = Group::factory()->create();
        $group->recipients()->attach(Recipient::factory()->count(3)->create());

        $response = $this->actingAs($this->admin)->postJson(
            "/api/admin/templates/{$this->template->uuid}/send",
            [
                'group_uuid' => $group->uuid,
                'completion_date' => '2026-05-01',
                'issue_date' => '2026-06-01',
            ]
        );

        $response->assertCreated()->assertJsonPath('count', 3);
        $this->assertEquals(3, Certificate::where('status', CertificateStatus::Queued)->count());
        Queue::assertPushed(SendCertificateJob::class, 3);

        $numbers = Certificate::pluck('certificate_number')->sort()->values();
        $this->assertEquals(['HSE-000001', 'HSE-000002', 'HSE-000003'], $numbers->all());
    }

    public function test_send_to_individual_recipients(): void
    {
        Queue::fake();
        $recipients = Recipient::factory()->count(2)->create();

        $this->actingAs($this->admin)->postJson(
            "/api/admin/templates/{$this->template->uuid}/send",
            [
                'recipient_uuids' => $recipients->pluck('uuid')->all(),
                'completion_date' => '2026-05-01',
                'issue_date' => '2026-06-01',
            ]
        )->assertCreated()->assertJsonPath('count', 2);
    }

    public function test_send_job_marks_certificate_sent_and_logs_mail(): void
    {
        Mail::fake();
        $this->mock(CertificateRenderService::class, function ($mock) {
            $mock->shouldReceive('pdf')->andReturn('certificates/fake.pdf');
        });

        $certificate = Certificate::factory()->create([
            'certificate_template_id' => $this->template->id,
            'status' => CertificateStatus::Queued,
        ]);

        (new SendCertificateJob($certificate->id))->handle(app(CertificateRenderService::class));

        $certificate->refresh();
        $this->assertEquals(CertificateStatus::Sent, $certificate->status);
        $this->assertNotNull($certificate->sent_at);
        Mail::assertSent(CertificateIssuedMail::class);
        $this->assertDatabaseHas('mail_logs', ['certificate_id' => $certificate->id, 'status' => 'sent']);
    }

    public function test_failed_job_marks_certificate_failed(): void
    {
        $certificate = Certificate::factory()->create([
            'certificate_template_id' => $this->template->id,
            'status' => CertificateStatus::Queued,
        ]);

        (new SendCertificateJob($certificate->id))->failed(new \RuntimeException('SMTP down'));

        $certificate->refresh();
        $this->assertEquals(CertificateStatus::Failed, $certificate->status);
        $this->assertStringContainsString('SMTP down', $certificate->send_error);
        $this->assertDatabaseHas('mail_logs', ['certificate_id' => $certificate->id, 'status' => 'failed']);
    }

    public function test_resend_failed_certificate(): void
    {
        Queue::fake();
        $certificate = Certificate::factory()->create([
            'certificate_template_id' => $this->template->id,
            'status' => CertificateStatus::Failed,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/certificates/{$certificate->uuid}/resend")
            ->assertOk()
            ->assertJsonPath('status', 'queued');

        Queue::assertPushed(SendCertificateJob::class, 1);
    }

    public function test_revoke_and_unrevoke(): void
    {
        Mail::fake();
        $certificate = Certificate::factory()->sent()->create(['certificate_template_id' => $this->template->id]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/certificates/{$certificate->uuid}/revoke", ['reason' => 'Issued in error'])
            ->assertOk()
            ->assertJsonPath('status', 'revoked');

        Mail::assertQueued(CertificateRevokedMail::class);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/certificates/{$certificate->uuid}/unrevoke")
            ->assertOk()
            ->assertJsonPath('status', 'sent');
    }

    public function test_renew_creates_new_certificate_with_same_data(): void
    {
        Queue::fake();
        $certificate = Certificate::factory()->expired()->create([
            'certificate_template_id' => $this->template->id,
            'data' => ['course_title' => 'Working at Heights'],
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/certificates/{$certificate->uuid}/renew", ['issue_date' => '2026-06-10'])
            ->assertCreated();

        $this->assertEquals('renewed', $certificate->fresh()->status->value);
        $this->assertEquals(['course_title' => 'Working at Heights'], $response->json('data'));
        $this->assertEquals('2026-06-10', substr($response->json('issue_date'), 0, 10));
        $this->assertEquals('2027-06-10', substr($response->json('expiry_date'), 0, 10));
        $this->assertEquals($certificate->id, Certificate::where('uuid', $response->json('uuid'))->first()->renewed_from_id);
    }

    public function test_soft_delete_restore_and_deleted_listing(): void
    {
        $certificate = Certificate::factory()->sent()->create(['certificate_template_id' => $this->template->id]);

        $this->actingAs($this->admin)->deleteJson("/api/admin/certificates/{$certificate->uuid}")->assertOk();
        $this->assertSoftDeleted('certificates', ['id' => $certificate->id]);

        $this->actingAs($this->admin)
            ->getJson('/api/admin/certificates?status=deleted')
            ->assertOk()
            ->assertJsonPath('total', 1);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/certificates/{$certificate->uuid}/restore")
            ->assertOk();
        $this->assertNull($certificate->fresh()->deleted_at);
    }

    public function test_bulk_revoke_and_bulk_delete(): void
    {
        Mail::fake();
        $certificates = Certificate::factory()->count(3)->sent()->create(['certificate_template_id' => $this->template->id]);

        $this->actingAs($this->admin)->postJson('/api/admin/certificates/bulk', [
            'action' => 'revoke',
            'uuids' => $certificates->pluck('uuid')->all(),
        ])->assertOk()->assertJsonPath('affected', 3);

        $this->assertEquals(3, Certificate::where('status', CertificateStatus::Revoked)->count());

        $this->actingAs($this->admin)->postJson('/api/admin/certificates/bulk', [
            'action' => 'delete',
            'uuids' => $certificates->pluck('uuid')->all(),
        ])->assertOk()->assertJsonPath('affected', 3);

        $this->assertEquals(0, Certificate::count());
    }

    public function test_manual_certificate_with_custom_number(): void
    {
        $recipient = Recipient::factory()->create();

        $this->actingAs($this->admin)->postJson('/api/admin/certificates/manual', [
            'template_uuid' => $this->template->uuid,
            'recipient_uuid' => $recipient->uuid,
            'certificate_number' => 'CUSTOM-2026-001',
            'completion_date' => '2026-05-01',
            'issue_date' => '2026-06-01',
        ])->assertCreated()->assertJsonPath('certificate_number', 'CUSTOM-2026-001')
            ->assertJsonPath('is_manual', true);

        // Duplicate numbers are rejected.
        $this->actingAs($this->admin)->postJson('/api/admin/certificates/manual', [
            'template_uuid' => $this->template->uuid,
            'recipient_uuid' => $recipient->uuid,
            'certificate_number' => 'CUSTOM-2026-001',
            'completion_date' => '2026-05-01',
            'issue_date' => '2026-06-01',
        ])->assertUnprocessable();
    }

    public function test_search_finds_certificates_by_number_recipient_and_template(): void
    {
        $match = Certificate::factory()->sent()->create([
            'certificate_template_id' => $this->template->id,
            'recipient_id' => Recipient::factory()->create(['full_name' => 'Alice Wonder', 'email' => 'alice@example.com'])->id,
        ]);
        Certificate::factory()->sent()->create(['certificate_template_id' => $this->template->id]);

        $this->actingAs($this->admin)
            ->getJson('/api/admin/certificates?q=alice')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.uuid', $match->uuid);

        $this->actingAs($this->admin)
            ->getJson('/api/admin/certificates?q='.$match->certificate_number)
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_expiry_job_flips_overdue_sent_certificates(): void
    {
        $overdue = Certificate::factory()->sent()->create([
            'certificate_template_id' => $this->template->id,
            'expiry_date' => now()->subDay(),
        ]);
        $valid = Certificate::factory()->sent()->create([
            'certificate_template_id' => $this->template->id,
            'expiry_date' => now()->addMonth(),
        ]);

        (new ExpireCertificatesJob)->handle();

        $this->assertEquals(CertificateStatus::Expired, $overdue->fresh()->status);
        $this->assertEquals(CertificateStatus::Sent, $valid->fresh()->status);
    }
}
