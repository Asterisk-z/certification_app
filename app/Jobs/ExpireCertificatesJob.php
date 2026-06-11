<?php

namespace App\Jobs;

use App\Enums\CertificateStatus;
use App\Models\Certificate;
use App\Notifications\CertificateExpiringNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class ExpireCertificatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(): void
    {
        $expired = Certificate::where('status', CertificateStatus::Sent)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', today())
            ->get();

        foreach ($expired as $certificate) {
            $certificate->status = CertificateStatus::Expired;
            $certificate->save();

            activity()->performedOn($certificate)->log('certificate_expired');
        }

        // Heads-up exactly 30 days before expiry (runs daily, so fires once).
        $expiring = Certificate::where('status', CertificateStatus::Sent)
            ->whereDate('expiry_date', today()->addDays(30))
            ->with('recipient.user', 'template')
            ->get();

        foreach ($expiring as $certificate) {
            $certificate->recipient->user?->notify(new CertificateExpiringNotification($certificate));
        }
    }
}
