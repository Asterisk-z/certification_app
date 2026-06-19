<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\FiltersByOrganization;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Recipient;
use App\Services\OrganizationLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    use FiltersByOrganization;

    public function index(Request $request): JsonResponse
    {
        $query = Group::query()->with('organization:id,uuid,name')->withCount('recipients')->latest();

        if ($search = trim((string) $request->query('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where('name', 'like', $like);
        }

        $this->applyOrganizationFilter($query, $request);

        return response()->json($query->paginate((int) $request->query('per_page', 15)));
    }

    public function store(Request $request, OrganizationLimitService $limits): JsonResponse
    {
        $limits->assertCanCreate($request->user(), 'groups');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        return response()->json(Group::create($data), 201);
    }

    public function show(Group $group): JsonResponse
    {
        return response()->json($group->loadCount('recipients'));
    }

    public function update(Request $request, Group $group): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $group->update($data);

        return response()->json($group);
    }

    public function destroy(Group $group): JsonResponse
    {
        $group->delete();

        return response()->json(['message' => 'Group deleted.']);
    }

    public function addRecipients(Request $request, Group $group, OrganizationLimitService $limits): JsonResponse
    {
        $data = $request->validate([
            'recipient_uuids' => ['required', 'array', 'min:1'],
            'recipient_uuids.*' => ['uuid'],
        ]);

        $recipients = Recipient::whereIn('uuid', $data['recipient_uuids'])->get();
        $alreadyIn = $group->recipients()->pluck('recipients.id')->all();

        // A new membership for a recipient must not push them past their
        // groups-per-recipient cap (recipients already in this group are no-ops).
        foreach ($recipients as $recipient) {
            if (! in_array($recipient->id, $alreadyIn, true)) {
                $limits->assertGroupsPerRecipient($request->user(), $recipient, $recipient->groups()->count() + 1);
            }
        }

        $ids = $recipients->pluck('id');
        $group->recipients()->syncWithoutDetaching($ids);

        activity()->performedOn($group)->causedBy($request->user())
            ->withProperties(['count' => $ids->count()])->log('recipients_added');

        return response()->json($group->loadCount('recipients'));
    }

    public function removeRecipient(Request $request, Group $group, Recipient $recipient): JsonResponse
    {
        $group->recipients()->detach($recipient->id);

        activity()->performedOn($group)->causedBy($request->user())
            ->withProperties(['recipient' => $recipient->email])->log('recipient_removed');

        return response()->json($group->loadCount('recipients'));
    }
}
