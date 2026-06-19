<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Manage an organization's certificate-admin login users ("team"). Mounted
 * under both portals:
 *   - /admin/organizations/{organization:uuid}/team — platform admin, any org,
 *     bypasses the certificate_admins cap.
 *   - /org/team — an org's own users, scoped to their org, capped.
 *
 * The target organization is the route-bound one (admin) or the actor's own
 * organization (org portal).
 */
class OrganizationTeamController extends Controller
{
    public function __construct(private readonly OrganizationLimitService $limits) {}

    public function index(Request $request): JsonResponse
    {
        $org = $this->org($request);

        // The original login (lowest id) is the primary contact kept in sync
        // with the organization profile; it cannot be removed here.
        $primaryId = $this->primaryId($org);

        $users = $org->users()->where('role', UserRole::Organization)->orderBy('id')
            ->get(['id', 'uuid', 'name', 'email', 'email_verified_at', 'created_at'])
            ->map(fn (User $u) => [
                'uuid' => $u->uuid,
                'name' => $u->name,
                'email' => $u->email,
                'active' => $u->email_verified_at !== null,
                'is_primary' => $u->id === $primaryId,
                'created_at' => $u->created_at,
            ]);

        return response()->json([
            'data' => $users,
            'limit' => $org->limitFor('certificate_admins'),
            'used' => $users->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $org = $this->org($request);

        // No-op for the platform admin; caps an org inviting its own teammates.
        $this->limits->assertCanInviteAdmin($request->user());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->withoutTrashed()],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => UserRole::Organization,
            'organization_id' => $org->id,
            'password' => Str::random(40),
            'invited_at' => now(),
        ]);

        // Setup link doubles as the password-reset flow (same as org creation).
        Password::sendResetLink(['email' => $user->email]);

        activity()->performedOn($org)->causedBy($request->user())
            ->withProperties(['email' => $user->email])->log('organization_admin_invited');

        return response()->json([
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
        ], 201);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $org = $this->org($request);

        // Only the org's own certificate-admin users can be removed here.
        if ($user->organization_id !== $org->id || $user->role !== UserRole::Organization) {
            abort(404);
        }

        if ($user->id === $this->primaryId($org)) {
            return response()->json(['message' => 'The primary organization login cannot be removed.'], 422);
        }

        $user->delete();

        activity()->performedOn($org)->causedBy($request->user())
            ->withProperties(['email' => $user->email])->log('organization_admin_removed');

        return response()->json(['message' => 'Certificate admin removed.']);
    }

    private function org(Request $request): Organization
    {
        $bound = $request->route('organization');

        if ($bound instanceof Organization) {
            return $bound;
        }

        // Admin route may surface the {organization:uuid} segment as a raw uuid.
        if (is_string($bound) && $bound !== '') {
            return Organization::where('uuid', $bound)->firstOrFail();
        }

        // Org portal: no route segment — scope to the acting user's org.
        return $request->user()->organization;
    }

    private function primaryId(Organization $org): ?int
    {
        return $org->users()->where('role', UserRole::Organization)->min('id');
    }
}
