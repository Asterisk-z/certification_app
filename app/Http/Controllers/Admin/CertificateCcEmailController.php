<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\FiltersByOrganization;
use App\Http\Controllers\Controller;
use App\Models\CertificateCcEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD for the per-organization certificate CC list. Mounted under both /admin
 * (platform admin, sees/manages every org) and /org (org admin, scoped to its
 * own org by the BelongsToOrganization global scope).
 */
class CertificateCcEmailController extends Controller
{
    use FiltersByOrganization;

    public function index(Request $request): JsonResponse
    {
        $query = CertificateCcEmail::query()->with('organization:id,uuid,name')->latest();

        if ($search = trim((string) $request->query('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(fn ($q) => $q->where('email', 'like', $like)->orWhere('name', 'like', $like));
        }

        $this->applyOrganizationFilter($query, $request);

        return response()->json($query->paginate((int) $request->query('per_page', 50)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules($request));

        return response()->json(CertificateCcEmail::create($data), 201);
    }

    public function update(Request $request, CertificateCcEmail $ccEmail): JsonResponse
    {
        $data = $request->validate($this->rules($request, $ccEmail));

        $ccEmail->update($data);

        return response()->json($ccEmail);
    }

    public function destroy(CertificateCcEmail $ccEmail): JsonResponse
    {
        $ccEmail->delete();

        return response()->json(['message' => 'CC recipient removed.']);
    }

    /**
     * Validation rules with an org-scoped uniqueness check so the same address
     * can't be added twice for one organization. The org is the actor's own
     * org (an admin acts at the platform level, organization_id = NULL).
     *
     * @return array<string, array<int, mixed>>
     */
    private function rules(Request $request, ?CertificateCcEmail $ccEmail = null): array
    {
        $organizationId = $request->user()->isAdmin() ? null : $request->user()->organization_id;

        $unique = Rule::unique('certificate_cc_emails', 'email')
            ->where(fn ($q) => $organizationId === null
                ? $q->whereNull('organization_id')
                : $q->where('organization_id', $organizationId));

        if ($ccEmail) {
            $unique->ignore($ccEmail->id);
        }

        return [
            'email' => ['required', 'email', 'max:255', $unique],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
