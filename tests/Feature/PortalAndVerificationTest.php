<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\RecipientInviteMail;
use App\Models\Certificate;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
}
