<?php

namespace Tests\Feature;

use App\Enums\CertificateStatus;
use App\Enums\UserRole;
use App\Jobs\SendCertificateJob;
use App\Mail\CertificateIssuedMail;
use App\Mail\CertificateRevokedMail;
use App\Models\Certificate;
use App\Models\CertificateCcEmail;
use App\Models\CertificateTemplate;
use App\Models\Organization;
use App\Models\Recipient;
use App\Models\User;
use App\Services\CertificateRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CertificateCcEmailTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private User $orgUserA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orgA = Organization::factory()->create(['email' => 'admin@org-a.test']);
        $this->orgB = Organization::factory()->create(['email' => 'admin@org-b.test']);
        $this->orgUserA = User::factory()->create([
            'role' => UserRole::Organization,
            'organization_id' => $this->orgA->id,
        ]);
    }

    private function queuedCertificate(Organization $org, string $recipientEmail = 'holder@example.test'): Certificate
    {
        $template = CertificateTemplate::factory()->ready()->create(['organization_id' => $org->id]);
        $recipient = Recipient::factory()->create(['organization_id' => $org->id, 'email' => $recipientEmail]);

        return Certificate::factory()->create([
            'organization_id' => $org->id,
            'certificate_template_id' => $template->id,
            'recipient_id' => $recipient->id,
            'status' => CertificateStatus::Queued,
        ]);
    }

    private function fakeRenderer(): void
    {
        $this->mock(CertificateRenderService::class, function ($mock) {
            $mock->shouldReceive('pdf')->andReturn('certificates/fake.pdf');
            $mock->shouldReceive('png')->andReturn('certificates/fake.png');
        });
    }

    // --- CRUD -------------------------------------------------------------

    public function test_org_user_can_add_list_and_remove_cc_recipients(): void
    {
        $created = $this->actingAs($this->orgUserA)->postJson('/api/org/certificate-cc-emails', [
            'email' => 'safety@org-a.test',
            'name' => 'Safety Officer',
        ])->assertCreated()->json();

        $this->assertSame($this->orgA->id, CertificateCcEmail::firstWhere('uuid', $created['uuid'])->organization_id);

        $this->actingAs($this->orgUserA)->getJson('/api/org/certificate-cc-emails')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.email', 'safety@org-a.test');

        $this->actingAs($this->orgUserA)->deleteJson("/api/org/certificate-cc-emails/{$created['uuid']}")->assertOk();
        $this->assertDatabaseMissing('certificate_cc_emails', ['uuid' => $created['uuid']]);
    }

    public function test_cc_listing_is_scoped_to_the_org(): void
    {
        CertificateCcEmail::factory()->create(['organization_id' => $this->orgA->id, 'email' => 'a@org-a.test']);
        CertificateCcEmail::factory()->create(['organization_id' => $this->orgB->id, 'email' => 'b@org-b.test']);

        $this->actingAs($this->orgUserA)->getJson('/api/org/certificate-cc-emails')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.email', 'a@org-a.test');
    }

    public function test_org_cannot_mutate_another_orgs_cc_recipient(): void
    {
        $foreign = CertificateCcEmail::factory()->create(['organization_id' => $this->orgB->id]);

        $this->actingAs($this->orgUserA)->putJson("/api/org/certificate-cc-emails/{$foreign->uuid}", [
            'email' => 'hijack@org-a.test',
        ])->assertNotFound();
        $this->actingAs($this->orgUserA)->deleteJson("/api/org/certificate-cc-emails/{$foreign->uuid}")->assertNotFound();
    }

    public function test_duplicate_email_within_an_org_is_rejected(): void
    {
        CertificateCcEmail::factory()->create(['organization_id' => $this->orgA->id, 'email' => 'dup@org-a.test']);

        $this->actingAs($this->orgUserA)->postJson('/api/org/certificate-cc-emails', [
            'email' => 'dup@org-a.test',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    // --- CC applied to mail ----------------------------------------------

    public function test_issued_mail_copies_the_configured_cc_recipients(): void
    {
        Mail::fake();
        $this->fakeRenderer();

        CertificateCcEmail::factory()->create(['organization_id' => $this->orgA->id, 'email' => 'safety@org-a.test']);
        $certificate = $this->queuedCertificate($this->orgA);

        (new SendCertificateJob($certificate->id))->handle(app(CertificateRenderService::class));

        Mail::assertSent(CertificateIssuedMail::class, fn ($mail) => $mail->hasCc('safety@org-a.test'));
    }

    public function test_issued_mail_falls_back_to_the_org_contact_when_no_cc_is_configured(): void
    {
        Mail::fake();
        $this->fakeRenderer();

        $certificate = $this->queuedCertificate($this->orgA);

        (new SendCertificateJob($certificate->id))->handle(app(CertificateRenderService::class));

        Mail::assertSent(CertificateIssuedMail::class, fn ($mail) => $mail->hasCc('admin@org-a.test'));
    }

    public function test_recipient_is_never_cc_on_their_own_certificate(): void
    {
        Mail::fake();
        $this->fakeRenderer();

        CertificateCcEmail::factory()->create(['organization_id' => $this->orgA->id, 'email' => 'holder@example.test']);
        $certificate = $this->queuedCertificate($this->orgA, 'holder@example.test');

        (new SendCertificateJob($certificate->id))->handle(app(CertificateRenderService::class));

        Mail::assertSent(CertificateIssuedMail::class, fn ($mail) => ! $mail->hasCc('holder@example.test'));
    }

    public function test_revoked_mail_copies_the_configured_cc_recipients(): void
    {
        Mail::fake();
        CertificateCcEmail::factory()->create(['organization_id' => $this->orgA->id, 'email' => 'safety@org-a.test']);

        $certificate = $this->queuedCertificate($this->orgA);
        $certificate->forceFill(['status' => CertificateStatus::Sent, 'sent_at' => now()])->save();

        $this->actingAs($this->orgUserA)
            ->postJson("/api/org/certificates/{$certificate->uuid}/revoke", ['reason' => 'Issued in error'])
            ->assertOk();

        Mail::assertQueued(CertificateRevokedMail::class, fn ($mail) => $mail->hasCc('safety@org-a.test'));
    }
}
