<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ReleaseNote;
use Illuminate\Http\JsonResponse;

/**
 * Public changelog: the current platform version and the full release
 * history, shown via the version badge across the app.
 */
class ChangelogController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $notes = ReleaseNote::latestFirst()->get([
            'uuid', 'version', 'title', 'body', 'released_on',
        ]);

        return response()->json([
            'current_version' => $notes->first()?->version,
            'notes' => $notes,
        ]);
    }
}
