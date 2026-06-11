<?php

namespace App\Notifications;

use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CertificateRevokedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Certificate $certificate) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'certificate_revoked',
            'title' => 'Credential revoked',
            'message' => "Your credential {$this->certificate->certificate_number} has been revoked.",
            'certificate_uuid' => $this->certificate->uuid,
        ];
    }
}
