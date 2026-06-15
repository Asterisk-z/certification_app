<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CertificateStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Group;
use App\Models\Organization;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    private const COUNTS = ['users', 'templates', 'groups', 'recipients', 'certificates'];

    public function index(Request $request): JsonResponse
    {
        $query = Organization::query()->withCount(self::COUNTS)->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->query('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('code', 'like', $like));
        }

        return response()->json($query->paginate((int) $request->query('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateOrganization($request);

        $org = Organization::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'email' => $data['email'],
            'code' => $data['code'] ?? $this->uniqueCode($data['name']),
            'status' => $data['status'] ?? 'active',
            'features' => $this->normalizeFeatures($data['features'] ?? null),
            'logo_path' => $this->storeLogo($request),
        ]);

        // The org's login account. Set a password now, or leave a random one and
        // email a setup link (the standard password-reset flow).
        $user = User::create([
            'name' => $org->name,
            'email' => $org->email,
            'role' => UserRole::Organization,
            'organization_id' => $org->id,
            'password' => $data['provision'] === 'password' ? $data['password'] : Str::random(40),
        ]);

        if ($data['provision'] === 'link') {
            Password::sendResetLink(['email' => $user->email]);
        }

        activity()->performedOn($org)->causedBy($request->user())->log('organization_created');

        return response()->json($org->loadCount(self::COUNTS), 201);
    }

    public function show(Organization $organization): JsonResponse
    {
        return response()->json($organization->loadCount(self::COUNTS));
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        $data = $this->validateOrganization($request, $organization);

        $organization->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'email' => $data['email'],
            'code' => $data['code'] ?? $organization->code,
            'status' => $data['status'] ?? $organization->status,
            'features' => $this->normalizeFeatures($data['features'] ?? null),
        ]);

        if ($logo = $this->storeLogo($request)) {
            if ($organization->logo_path) {
                Storage::disk('public')->delete($organization->logo_path);
            }
            $organization->logo_path = $logo;
        }

        $organization->save();

        // Keep the login account in sync; optionally reset its password.
        if ($user = $organization->users()->where('role', UserRole::Organization)->first()) {
            $user->email = $organization->email;
            $user->name = $organization->name;
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();
        }

        activity()->performedOn($organization)->causedBy($request->user())->log('organization_updated');

        return response()->json($organization->loadCount(self::COUNTS));
    }

    public function destroy(Request $request, Organization $organization): JsonResponse
    {
        $organization->delete();

        activity()->performedOn($organization)->causedBy($request->user())->log('organization_deleted');

        return response()->json(['message' => 'Organization deactivated and removed.']);
    }

    /**
     * Toggle status and/or feature access without re-sending the whole profile —
     * used by the quick toggles on the organization detail page.
     */
    public function updateSettings(Request $request, Organization $organization): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'in:active,inactive'],
            'features' => ['nullable', 'array'],
        ]);

        if (! empty($data['status'])) {
            $organization->status = $data['status'];
        }

        if (array_key_exists('features', $data)) {
            $organization->features = $this->normalizeFeatures($data['features']);
        }

        $organization->save();

        activity()->performedOn($organization)->causedBy($request->user())->log('organization_settings_updated');

        return response()->json($organization->loadCount(self::COUNTS));
    }

    /**
     * Dashboard-style statistics for a single organization (admin view).
     */
    public function stats(Organization $organization): JsonResponse
    {
        $orgId = $organization->id;

        $byStatus = Certificate::where('organization_id', $orgId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'totals' => [
                'certificates' => Certificate::where('organization_id', $orgId)->count(),
                'recipients' => Recipient::where('organization_id', $orgId)->count(),
                'templates' => CertificateTemplate::where('organization_id', $orgId)->count(),
                'groups' => Group::where('organization_id', $orgId)->count(),
                'deleted' => Certificate::onlyTrashed()->where('organization_id', $orgId)->count(),
            ],
            'by_status' => $byStatus,
            'expiring_soon' => Certificate::where('organization_id', $orgId)
                ->where('status', CertificateStatus::Sent)
                ->whereBetween('expiry_date', [today(), today()->addDays(30)])
                ->with('recipient:id,full_name', 'template:id,name')
                ->orderBy('expiry_date')
                ->limit(8)
                ->get(['id', 'uuid', 'certificate_number', 'recipient_id', 'certificate_template_id', 'expiry_date']),
        ]);
    }

    /**
     * Send (or re-send) the password setup link to the org's login account.
     */
    public function resendSetup(Request $request, Organization $organization): JsonResponse
    {
        Password::sendResetLink(['email' => $organization->email]);

        activity()->performedOn($organization)->causedBy($request->user())->log('organization_setup_link_sent');

        return response()->json(['message' => 'Setup link sent to '.$organization->email]);
    }

    private function validateOrganization(Request $request, ?Organization $organization = null): array
    {
        $orgId = $organization?->id;
        $userId = $organization?->users()->where('role', UserRole::Organization)->value('id');

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('organizations', 'email')->ignore($orgId),
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('organizations', 'code')->ignore($orgId)],
            'status' => ['nullable', 'in:active,inactive'],
            'features' => ['nullable', 'array'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,webp,svg', 'max:5120'],
            // Provisioning only applies on create; ignored on update unless a
            // password is supplied to reset the login.
            'provision' => [$organization ? 'nullable' : 'required', 'in:password,link'],
            'password' => ['nullable', 'required_if:provision,password', 'string', 'min:8'],
        ]);
    }

    private function normalizeFeatures(?array $features): ?array
    {
        if ($features === null) {
            return null;
        }

        return collect(Organization::FEATURES)
            ->mapWithKeys(fn ($f) => [$f => $features[$f] ?? true])
            ->all();
    }

    private function storeLogo(Request $request): ?string
    {
        return $request->hasFile('logo')
            ? $request->file('logo')->store('organizations', 'public')
            : null;
    }

    private function uniqueCode(string $name): string
    {
        $base = strtoupper(Str::slug(Str::substr($name, 0, 4), '')) ?: 'ORG';
        $code = $base;
        $i = 2;

        while (Organization::where('code', $code)->exists()) {
            $code = $base.$i++;
        }

        return $code;
    }
}
