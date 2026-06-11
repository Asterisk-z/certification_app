<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MailLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class LogController extends Controller
{
    public function mail(Request $request): JsonResponse
    {
        $query = MailLog::query()->with('certificate:id,uuid,certificate_number')->latest();

        if ($search = trim((string) $request->query('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(fn ($q) => $q->where('recipient_email', 'like', $like)->orWhere('subject', 'like', $like));
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->paginate((int) $request->query('per_page', 20)));
    }

    public function activity(Request $request): JsonResponse
    {
        $query = Activity::query()->with('causer:id,name')->latest();

        if ($search = trim((string) $request->query('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where('description', 'like', $like);
        }

        return response()->json($query->paginate((int) $request->query('per_page', 25)));
    }
}
