<?php

namespace App\Notifications;

use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CertificateIssuedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Certificate $certificate) {}

    public function via(object $notifiable): array
    {
        // The certificate email itself is sent separately — this is the
        // in-app notification for portal users.
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'certificate_issued',
            'title' => 'New certificate issued',
            'message' => "You received “{$this->certificate->displayName()}” ({$this->certificate->certificate_number}).",
            'certificate_uuid' => $this->certificate->uuid,
        ];
    }
}
