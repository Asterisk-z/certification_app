<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Server-sent events stream that fires whenever any certificate changes,
 * so the admin list refreshes live (queue worker marking sends, imports,
 * other admins revoking, …).
 *
 * The stream intentionally closes after a short cycle — EventSource
 * reconnects automatically — so a browser tab never pins down one of the
 * few PHP workers for good.
 */
class CertificateStreamController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        return response()->stream(function () {
            echo "retry: 3000\n\n";

            $last = $this->signature();
            $cycles = app()->environment('testing') ? 1 : 12;

            for ($i = 0; $i < $cycles; $i++) {
                $current = $this->signature();

                if ($current !== $last) {
                    echo "event: certificates\n";
                    echo 'data: '.json_encode(['changed_at' => now()->toIso8601String()])."\n\n";
                    $last = $current;
                } else {
                    echo ": heartbeat\n\n";
                }

                if (! app()->runningUnitTests()) {
                    while (ob_get_level() > 0) {
                        ob_end_flush();
                    }
                    flush();

                    if (connection_aborted()) {
                        return;
                    }
                }

                if ($i < $cycles - 1) {
                    sleep(2);
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Cheap change detector over the whole certificates table (including
     * soft-deleted rows, so restores and deletes are picked up too).
     */
    private function signature(): string
    {
        $row = Certificate::withTrashed()
            ->selectRaw('count(*) as total, max(updated_at) as latest')
            ->first();

        return $row->total.'|'.$row->latest;
    }
}
