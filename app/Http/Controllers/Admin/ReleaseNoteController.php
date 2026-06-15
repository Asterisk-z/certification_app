<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReleaseNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $note = ReleaseNote::create([
            ...$this->validated($request),
            'created_by' => $request->user()->id,
        ]);

        return response()->json($note, 201);
    }

    public function update(Request $request, ReleaseNote $releaseNote): JsonResponse
    {
        $releaseNote->update($this->validated($request));

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
}
