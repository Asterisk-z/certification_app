<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Organization;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tenancy must not leak into the public verifier or the recipient portal —
 * both run as non-org actors and must see org-owned certificates normally.
 */
class OrgRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_verify_an_org_owned_certificate(): void
    {
        $org = Organization::factory()->create();
        $template = CertificateTemplate::factory()->ready()->create(['organization_id' => $org->id]);
        $recipient = Recipient::factory()->create(['organization_id' => $org->id]);
        $certificate = Certificate::factory()->sent()->create([
            'organization_id' => $org->id,
            'certificate_template_id' => $template->id,
            'recipient_id' => $recipient->id,
        ]);

        $this->getJson('/api/verify?number='.$certificate->certificate_number)
            ->assertOk()
            ->assertJsonPath('result', 'valid')
            ->assertJsonPath('certificate.holder', $recipient->full_name);
    }

    public function test_recipient_portal_shows_org_issued_certificate(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['role' => UserRole::Recipient]);
        $recipient = Recipient::factory()->create(['organization_id' => $org->id, 'user_id' => $user->id]);
        Certificate::factory()->sent()->create([
            'organization_id' => $org->id,
            'recipient_id' => $recipient->id,
        ]);

        $this->actingAs($user)->getJson('/api/me/certificates')
            ->assertOk()
            ->assertJsonPath('total', 1);
    }
}
