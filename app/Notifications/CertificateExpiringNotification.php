<?php

namespace App\Notifications;

use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CertificateExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Certificate $certificate) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your credential expires soon')
            ->greeting('Hello '.$this->certificate->recipient->full_name.',')
            ->line("Your credential **{$this->certificate->certificate_number}** ({$this->certificate->template->name}) expires on {$this->certificate->expiry_date->format('d M Y')}.")
            ->line('Contact the issuer if it needs to be renewed.')
            ->action('View credential', url('/c/'.$this->certificate->uuid));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'certificate_expiring',
            'title' => 'Credential expiring soon',
            'message' => "{$this->certificate->certificate_number} expires on {$this->certificate->expiry_date->format('d M Y')}.",
            'certificate_uuid' => $this->certificate->uuid,
        ];
    }
}
