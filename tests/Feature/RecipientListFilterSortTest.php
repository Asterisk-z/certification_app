<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\Group;
use App\Models\Organization;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipientListFilterSortTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Recipient $alice;

    private Recipient $bob;

    private Recipient $carol;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);

        $acme = Organization::factory()->create(['name' => 'Acme Safety']);
        $zeta = Organization::factory()->create(['name' => 'Zeta Works']);

        // alice: Zeta, portal active, 2 credentials, in a group
        $this->alice = Recipient::factory()->create([
            'full_name' => 'Alice Adams', 'email' => 'alice@zeta.test',
            'organization_id' => $zeta->id, 'user_id' => User::factory()->create(['role' => UserRole::Recipient])->id,
        ]);
        Certificate::factory()->count(2)->create(['recipient_id' => $this->alice->id]);
        $group = Group::factory()->create(['name' => 'Site A']);
        $this->alice->groups()->attach($group);

        // bob: Acme, not invited, 1 credential
        $this->bob = Recipient::factory()->create([
            'full_name' => 'Bob Brown', 'email' => 'bob@acme.test', 'organization_id' => $acme->id, 'user_id' => null,
        ]);
        Certificate::factory()->create(['recipient_id' => $this->bob->id]);

        // carol: Acme, not invited, no credentials
        $this->carol = Recipient::factory()->create([
            'full_name' => 'Carol Clark', 'email' => 'carol@acme.test', 'organization_id' => $acme->id, 'user_id' => null,
        ]);
    }

    private function names(array $query): array
    {
        return collect($this->actingAs($this->admin)->getJson('/api/admin/recipients?'.http_build_query($query))
            ->assertOk()->json('data'))->pluck('full_name')->all();
    }

    public function test_filters_by_each_column(): void
    {
        $this->assertSame(['Alice Adams'], $this->names(['name' => 'alice']));
        $this->assertSame(['Carol Clark', 'Bob Brown'], $this->names(['email' => '@acme']));
        $this->assertSame(['Alice Adams'], $this->names(['organization_name' => 'zeta']));
        $this->assertSame(['Alice Adams'], $this->names(['group' => $this->alice->groups->first()->uuid]));
        $this->assertSame(['Carol Clark'], $this->names(['certificates' => 'none']));
        $this->assertSame(['Bob Brown', 'Alice Adams'], $this->names(['certificates' => 'some']));
        $this->assertSame(['Alice Adams'], $this->names(['portal' => 'active']));
        $this->assertSame(['Carol Clark', 'Bob Brown'], $this->names(['portal' => 'not_invited']));
        // Filters combine.
        $this->assertSame(['Bob Brown'], $this->names(['email' => '@acme', 'certificates' => 'some']));
    }

    public function test_sorts_by_each_column_in_both_directions(): void
    {
        $this->assertSame(['Carol Clark', 'Bob Brown', 'Alice Adams'], $this->names([]), 'default is newest first');
        $this->assertSame(['Alice Adams', 'Bob Brown', 'Carol Clark'], $this->names(['sort' => 'name']));
        $this->assertSame(['Carol Clark', 'Bob Brown', 'Alice Adams'], $this->names(['sort' => 'name', 'dir' => 'desc']));
        $this->assertSame(['Alice Adams', 'Bob Brown', 'Carol Clark'], $this->names(['sort' => 'email', 'dir' => 'asc']));
        $this->assertSame(['Carol Clark', 'Bob Brown', 'Alice Adams'], $this->names(['sort' => 'organization', 'dir' => 'asc']));
        $this->assertSame(['Alice Adams', 'Carol Clark', 'Bob Brown'], $this->names(['sort' => 'groups', 'dir' => 'desc']));
        $this->assertSame(['Alice Adams', 'Bob Brown', 'Carol Clark'], $this->names(['sort' => 'certificates', 'dir' => 'desc']));
        $this->assertSame(['Carol Clark', 'Bob Brown', 'Alice Adams'], $this->names(['sort' => 'certificates', 'dir' => 'asc']));
        $this->assertSame(['Alice Adams', 'Carol Clark', 'Bob Brown'], $this->names(['sort' => 'portal', 'dir' => 'desc']));
    }

    public function test_unknown_sort_and_direction_fall_back_safely(): void
    {
        $this->assertSame(['Carol Clark', 'Bob Brown', 'Alice Adams'], $this->names(['sort' => 'password', 'dir' => 'sideways']));
    }

    public function test_org_user_cannot_filter_by_organization_name(): void
    {
        $acme = Organization::where('name', 'Acme Safety')->first();
        $orgUser = User::factory()->create(['role' => UserRole::Organization, 'organization_id' => $acme->id]);

        $names = collect($this->actingAs($orgUser)->getJson('/api/org/recipients?organization_name=zeta&sort=name')
            ->assertOk()->json('data'))->pluck('full_name')->all();

        $this->assertSame(['Bob Brown', 'Carol Clark'], $names);
    }
}
