<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Recipient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecipientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Recipient::query()->withCount('certificates')->with('groups:id,uuid,name')->latest();

        if ($search = trim((string) $request->query('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(fn ($q) => $q->where('full_name', 'like', $like)->orWhere('email', 'like', $like));
        }

        if ($groupUuid = $request->query('group')) {
            $query->whereHas('groups', fn ($q) => $q->where('uuid', $groupUuid));
        }

        return response()->json($query->paginate((int) $request->query('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('recipients', 'email')->withoutTrashed()],
            'phone' => ['nullable', 'string', 'max:30'],
            'group_uuids' => ['nullable', 'array'],
            'group_uuids.*' => ['uuid', 'exists:groups,uuid'],
        ]);

        $recipient = Recipient::create($data);

        if (! empty($data['group_uuids'])) {
            $groupIds = Group::whereIn('uuid', $data['group_uuids'])->pluck('id');
            $recipient->groups()->sync($groupIds);
        }

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
            'email' => ['required', 'email', 'max:255', Rule::unique('recipients', 'email')->ignore($recipient->id)->withoutTrashed()],
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
