<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\FiltersByOrganization;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Organization;
use App\Models\Recipient;
use App\Services\OrganizationLimitService;
use App\Services\RecipientInviteService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RecipientController extends Controller
{
    use FiltersByOrganization;

    public function __construct(private readonly RecipientInviteService $invites) {}

    /** Column sorts the listing accepts (?sort=…&dir=asc|desc). */
    private const SORTS = ['created', 'name', 'email', 'organization', 'groups', 'certificates', 'portal'];

    public function index(Request $request): JsonResponse
    {
        $query = Recipient::query()
            ->withCount(['certificates', 'groups'])
            ->with(['groups:id,uuid,name', 'organization:id,uuid,name']);

        if ($search = trim((string) $request->query('q'))) {
            $like = $this->like($search);
            $query->where(fn ($q) => $q->where('full_name', 'like', $like)->orWhere('email', 'like', $like));
        }

        // Per-column filters.
        if ($name = trim((string) $request->query('name'))) {
            $query->where('full_name', 'like', $this->like($name));
        }

        if ($email = trim((string) $request->query('email'))) {
            $query->where('email', 'like', $this->like($email));
        }

        if ($request->user()->isAdmin() && ($orgName = trim((string) $request->query('organization_name')))) {
            $query->whereHas('organization', fn ($q) => $q->where('name', 'like', $this->like($orgName)));
        }

        if ($groupUuid = $request->query('group')) {
            $query->whereHas('groups', fn ($q) => $q->where('uuid', $groupUuid));
        }

        match ($request->query('certificates')) {
            'none' => $query->whereDoesntHave('certificates'),
            'some' => $query->whereHas('certificates'),
            default => null,
        };

        match ($request->query('portal')) {
            'active' => $query->whereNotNull('user_id'),
            'not_invited' => $query->whereNull('user_id'),
            default => null,
        };

        $this->applyOrganizationFilter($query, $request);
        $this->applySort($query, $request);

        return response()->json($query->paginate((int) $request->query('per_page', 15)));
    }

    private function applySort(Builder $query, Request $request): void
    {
        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'created';
        $dir = $request->query('dir', $sort === 'created' ? 'desc' : 'asc') === 'desc' ? 'desc' : 'asc';

        match ($sort) {
            'name' => $query->orderBy('full_name', $dir),
            'email' => $query->orderBy('email', $dir),
            'organization' => $query->orderBy(
                Organization::select('name')->whereColumn('organizations.id', 'recipients.organization_id'),
                $dir,
            ),
            'groups' => $query->orderBy('groups_count', $dir),
            'certificates' => $query->orderBy('certificates_count', $dir),
            // Portal access is "has a linked user": sort the booleans.
            'portal' => $query->orderByRaw('(recipients.user_id IS NOT NULL) '.$dir),
            default => $query->orderBy('recipients.created_at', $dir),
        };

        $query->orderBy('recipients.id', 'desc');
    }

    private function like(string $value): string
    {
        return '%'.str_replace(['%', '_'], ['\\%', '\\_'], $value).'%';
    }

    public function store(Request $request, OrganizationLimitService $limits): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $limits->assertCanCreate($request->user(), 'recipients');

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255',
                Rule::unique('recipients', 'email')->where('organization_id', $orgId)->withoutTrashed()],
            'phone' => ['nullable', 'string', 'max:30'],
            'group_uuids' => ['nullable', 'array'],
            'group_uuids.*' => ['uuid', 'exists:groups,uuid'],
        ]);

        $groupIds = empty($data['group_uuids'])
            ? collect()
            : Group::whereIn('uuid', $data['group_uuids'])->pluck('id');

        $recipient = DB::transaction(function () use ($data, $groupIds, $limits, $request) {
            $recipient = Recipient::create($data);

            if ($groupIds->isNotEmpty()) {
                $limits->assertGroupsPerRecipient($request->user(), $recipient, $groupIds->count());
                $recipient->groups()->sync($groupIds);
            }

            return $recipient;
        });

        // Every new recipient is emailed a portal invite.
        $this->invites->send($recipient, $request->user());

        return response()->json($recipient->load('groups:id,uuid,name'), 201);
    }

    public function show(Recipient $recipient): JsonResponse
    {
        return response()->json(
            $recipient->load('groups:id,uuid,name')->loadCount('certificates')
        );
    }

    public function update(Request $request, Recipient $recipient): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255',
                Rule::unique('recipients', 'email')->ignore($recipient->id)
                    ->where('organization_id', $recipient->organization_id)->withoutTrashed()],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $recipient->update($data);

        return response()->json($recipient->load('groups:id,uuid,name'));
    }

    public function destroy(Recipient $recipient): JsonResponse
    {
        $recipient->delete();

        return response()->json(['message' => 'Recipient removed.']);
    }
}
