<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReleaseNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReleaseNoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            ReleaseNote::latestFirst()->with('author:id,name')->paginate((int) $request->query('per_page', 20))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $this->assertOrdering($data);

        $note = ReleaseNote::create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($note, 201);
    }

    public function update(Request $request, ReleaseNote $releaseNote): JsonResponse
    {
        $data = $this->validated($request);
        $this->assertOrdering($data, $releaseNote);

        $releaseNote->update($data);

        return response()->json($releaseNote);
    }

    public function destroy(ReleaseNote $releaseNote): JsonResponse
    {
        $releaseNote->delete();

        return response()->json(['message' => 'Release note deleted.']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'version' => ['required', 'string', 'max:40'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'released_on' => ['required', 'date'],
        ]);
    }

    /**
     * Versions must strictly increase and dates must not move backwards.
     * Compared with version_compare so 1.10.0 correctly beats 1.2.0.
     *
     * @throws ValidationException
     */
    private function assertOrdering(array $data, ?ReleaseNote $current = null): void
    {
        $version = trim($data['version']);
        $date = $data['released_on'];

        $others = ReleaseNote::query()
            ->when($current, fn ($q) => $q->whereKeyNot($current->id))
            ->get(['version', 'released_on']);

        if ($others->isEmpty()) {
            return;
        }

        if ($others->contains(fn ($n) => version_compare($n->version, $version, '=='))) {
            $this->reject('version', "Version {$version} already exists.");
        }

        $sorted = $others->sort(fn ($a, $b) => version_compare($a->version, $b->version))->values();
        $lower = $sorted->last(fn ($n) => version_compare($n->version, $version, '<'));
        $higher = $sorted->first(fn ($n) => version_compare($n->version, $version, '>'));

        // A brand-new version must be the highest — you can't add one behind
        // an existing release.
        if (! $current && $higher) {
            $this->reject('version', "Version {$version} must be higher than the latest version (v{$higher->version}).");
        }

        if ($lower && $date < $lower->released_on->format('Y-m-d')) {
            $this->reject('released_on', "The release date must be on or after v{$lower->version} ({$lower->released_on->format('Y-m-d')}).");
        }

        if ($higher && $date > $higher->released_on->format('Y-m-d')) {
            $this->reject('released_on', "The release date must be on or before v{$higher->version} ({$higher->released_on->format('Y-m-d')}).");
        }
    }

    private function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
