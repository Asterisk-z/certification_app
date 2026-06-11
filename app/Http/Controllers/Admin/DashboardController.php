<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CertificateStatus;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Group;
use App\Models\Recipient;
use Illuminate\Http\JsonResponse;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $byStatus = Certificate::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'totals' => [
                'certificates' => Certificate::count(),
                'recipients' => Recipient::count(),
                'templates' => CertificateTemplate::count(),
                'groups' => Group::count(),
                'deleted' => Certificate::onlyTrashed()->count(),
            ],
            'by_status' => $byStatus,
            'expiring_soon' => Certificate::where('status', CertificateStatus::Sent)
                ->whereBetween('expiry_date', [today(), today()->addDays(30)])
                ->with('recipient:id,full_name', 'template:id,name')
                ->orderBy('expiry_date')
                ->limit(8)
                ->get(['id', 'uuid', 'certificate_number', 'recipient_id', 'certificate_template_id', 'expiry_date']),
            'recent_activity' => Activity::with('causer:id,name')->latest()->limit(10)->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'description' => $a->description,
                    'causer' => $a->causer?->name,
                    'created_at' => $a->created_at,
                ]),
        ]);
    }
}
