<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrgFeatureLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_org_without_a_feature_is_blocked_but_keeps_others(): void
    {
        $org = Organization::factory()->create([
            'features' => ['templates' => false, 'recipients' => true, 'groups' => true, 'certificates' => true],
        ]);
        $user = User::factory()->create(['role' => UserRole::Organization, 'organization_id' => $org->id]);

        // Templates are disabled…
        $this->actingAs($user)->getJson('/api/org/templates')->assertForbidden();
        // …but other granted features still work.
        $this->actingAs($user)->getJson('/api/org/certificates')->assertOk();
        $this->actingAs($user)->getJson('/api/org/recipients')->assertOk();
    }

    public function test_admin_bypasses_feature_gating(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        // Admin has no organization, but the feature middleware never restricts admins.
        $this->actingAs($admin)->getJson('/api/admin/templates')->assertOk();
    }

    public function test_org_with_all_features_null_is_unrestricted(): void
    {
        $org = Organization::factory()->create(['features' => null]);
        $user = User::factory()->create(['role' => UserRole::Organization, 'organization_id' => $org->id]);

        $this->actingAs($user)->getJson('/api/org/templates')->assertOk();
        $this->actingAs($user)->getJson('/api/org/groups')->assertOk();
    }
}
