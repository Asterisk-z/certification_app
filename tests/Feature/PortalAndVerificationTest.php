<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\RecipientInviteMail;
use App\Models\Certificate;
use App\Models\Organization;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PortalAndVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_returns_valid_for_sent_certificate(): void
    {
        $certificate = Certificate::factory()->sent()->create();

        $this->getJson('/api/verify?number='.$certificate->certificate_number)
            ->assertOk()
            ->assertJsonPath('result', 'valid')
            ->assertJsonPath('certificate.holder', $certificate->recipient->full_name)
            ->assertJsonPath('certificate.view_url', url('/c/'.$certificate->uuid));
    }

    public function test_verification_hides_view_url_for_revoked_certificates(): void
    {
        $revoked = Certificate::factory()->sent()->create(['status' => 'revoked', 'revoked_at' => now()]);

        $this->getJson('/api/verify?number='.$revoked->certificate_number)
            ->assertOk()
            ->assertJsonPath('certificate.view_url', null);
    }

    public function test_verification_reports_revoked_and_expired_and_not_found(): void
    {
        $revoked = Certificate::factory()->sent()->create(['status' => 'revoked', 'revoked_at' => now()]);
        $expired = Certificate::factory()->expired()->create();

        $this->getJson('/api/verify?number='.$revoked->certificate_number)
            ->assertOk()->assertJsonPath('result', 'revoked');

        $this->getJson('/api/verify?number='.$expired->certificate_number)
            ->assertOk()->assertJsonPath('result', 'expired');

        $this->getJson('/api/verify?number=DOES-NOT-EXIST')
            ->assertNotFound()->assertJsonPath('result', 'not_found');
    }

    public function test_pending_certificates_are_not_publicly_visible(): void
    {
        $pending = Certificate::factory()->create(); // pending by default

        $this->getJson('/api/verify?number='.$pending->certificate_number)
            ->assertNotFound();
    }

    public function test_invite_flow_creates_portal_account(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $recipient = Recipient::factory()->create();

        $this->actingAs($admin)
            ->postJson("/api/admin/recipients/{$recipient->uuid}/invite")
            ->assertOk();

        Mail::assertQueued(RecipientInviteMail::class);

        // Recipient follows the signed link and sets a password.
        $signed = URL::temporarySignedRoute('invite.show', now()->addDays(7), ['recipient' => $recipient->uuid]);
        $query = parse_url($signed, PHP_URL_QUERY);

        $this->getJson("/api/auth/invite/{$recipient->uuid}?{$query}")
            ->assertOk()
            ->assertJsonPath('recipient.email', $recipient->email);

        $this->postJson("/api/auth/invite/{$recipient->uuid}?{$query}", [
            'password' => 'super-secret-1',
            'password_confirmation' => 'super-secret-1',
        ])->assertOk();

        $recipient->refresh();
        $this->assertNotNull($recipient->user_id);
        $this->assertEquals(UserRole::Recipient, $recipient->user->role);
    }

    public function test_invite_link_with_bad_signature_is_rejected(): void
    {
        $recipient = Recipient::factory()->create();

        $this->getJson("/api/auth/invite/{$recipient->uuid}?expires=9999999999&signature=tampered")
            ->assertForbidden();
    }

    public function test_recipient_sees_only_own_visible_certificates(): void
    {
        $user = User::factory()->create(['role' => UserRole::Recipient]);
        $recipient = Recipient::factory()->create(['user_id' => $user->id]);

        Certificate::factory()->sent()->create(['recipient_id' => $recipient->id]);
        Certificate::factory()->create(['recipient_id' => $recipient->id]); // pending — hidden
        Certificate::factory()->sent()->create(); // someone else's

        $this->actingAs($user)
            ->getJson('/api/me/certificates')
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_recipient_sees_certificates_from_every_organization_that_issued_to_their_email(): void
    {
        $user = User::factory()->create(['role' => UserRole::Recipient, 'email' => 'holder@example.com']);

        // The account came from the first organization's invite. The second
        // organization keeps its own record for the same person, which no
        // invite has linked to the account.
        $linked = Recipient::factory()->create([
            'organization_id' => Organization::factory(),
            'user_id' => $user->id,
            'email' => 'holder@example.com',
        ]);
        $unlinked = Recipient::factory()->create([
            'organization_id' => Organization::factory(),
            'email' => 'Holder@Example.com',
        ]);

        Certificate::factory()->sent()->create(['recipient_id' => $linked->id]);
        $second = Certificate::factory()->sent()->create(['recipient_id' => $unlinked->id]);
        Certificate::factory()->sent()->create(); // someone else's

        $this->actingAs($user)
            ->getJson('/api/me/certificates')
            ->assertOk()
            ->assertJsonPath('total', 2);

        $this->actingAs($user)
            ->getJson("/api/me/certificates/{$second->uuid}")
            ->assertOk()
            ->assertJsonPath('uuid', $second->uuid);
    }

    public function test_public_view_shows_the_uploaded_file_instead_of_the_template_render(): void
    {
        Storage::fake('local');
        $certificate = Certificate::factory()->sent()->create(); // issued from a template

        $path = "certificates/uploads/{$certificate->uuid}-manual.pdf";
        Storage::disk('local')->put($path, '%PDF-1.4 uploaded');
        $certificate->forceFill(['uploaded_file_path' => $path])->save();

        $response = $this->get('/c/'.$certificate->uuid)->assertOk();

        $this->assertSame('%PDF-1.4 uploaded', $response->streamedContent());
    }

    public function test_public_view_renders_the_template_when_nothing_was_uploaded(): void
    {
        $certificate = Certificate::factory()->sent()->create();

        $this->get('/c/'.$certificate->uuid)
            ->assertOk()
            ->assertSee('<title>'.$certificate->certificate_number.'</title>', false);
    }

    public function test_recipient_cannot_access_admin_api(): void
    {
        $user = User::factory()->create(['role' => UserRole::Recipient]);

        $this->actingAs($user)->getJson('/api/admin/certificates')->assertForbidden();
    }

    public function test_recipient_cannot_download_others_certificate(): void
    {
        $user = User::factory()->create(['role' => UserRole::Recipient]);
        Recipient::factory()->create(['user_id' => $user->id]);
        $foreign = Certificate::factory()->sent()->create();

        $this->actingAs($user)
            ->getJson("/api/me/certificates/{$foreign->uuid}")
            ->assertNotFound();
    }

    public function test_profile_update_changes_name_and_password(): void
    {
        $user = User::factory()->create(['role' => UserRole::Recipient]);
        Recipient::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->putJson('/api/me/profile', [
            'name' => 'New Name',
            'current_password' => 'password',
            'password' => 'brand-new-pass-1',
            'password_confirmation' => 'brand-new-pass-1',
        ])->assertOk()->assertJsonPath('user.name', 'New Name');

        $this->assertEquals('New Name', $user->fresh()->recipient->full_name);
    }

    public function test_verification_names_the_issuing_organization(): void
    {
        $organization = Organization::factory()->create(['name' => 'Acme Safety Institute Ltd.']);
        $certificate = Certificate::factory()->sent()->create(['organization_id' => $organization->id]);

        $this->getJson('/api/verify?number='.$certificate->certificate_number)
            ->assertOk()
            ->assertJsonPath('certificate.issuer', 'Acme Safety Institute Ltd.');
    }

    public function test_verification_falls_back_to_the_platform_for_admin_issued_credentials(): void
    {
        $certificate = Certificate::factory()->sent()->create(['organization_id' => null]);

        $this->getJson('/api/verify?number='.$certificate->certificate_number)
            ->assertOk()
            ->assertJsonPath('certificate.issuer', config('app.name'));
    }

    public function test_verification_still_names_a_soft_deleted_issuer(): void
    {
        $organization = Organization::factory()->create(['name' => 'Gone Group']);
        $certificate = Certificate::factory()->sent()->create(['organization_id' => $organization->id]);

        $organization->delete();

        $this->getJson('/api/verify?number='.$certificate->certificate_number)
            ->assertOk()
            ->assertJsonPath('certificate.issuer', 'Gone Group');
    }
}
