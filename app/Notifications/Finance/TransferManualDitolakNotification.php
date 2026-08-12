<?php

namespace App\Notifications\Finance;

use App\Models\WhatsAppTemplate;
use Illuminate\Notifications\Messages\MailMessage;

class TransferManualDitolakNotification extends FinanceNotification
{
    public function __construct(public string $rejectionReason)
    {
    }

    public function isUrgent(): bool
    {
        return true;
    }

    public function via(object $notifiable): array
    {
        return $this->baseChannels();
    }

    public function toDatabase(object $notifiable): array
    {
        return ['message' => "Bukti transfer pembayaran Anda ditolak: {$this->rejectionReason}. Silakan ajukan ulang."];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Transfer Manual Ditolak')
            ->line("Bukti transfer pembayaran Anda ditolak: {$this->rejectionReason}.")
            ->line('Silakan ajukan ulang.');
    }

    public function toWhatsApp(object $notifiable): ?string
    {
        return WhatsAppTemplate::renderKode('transfer_manual_ditolak', [
            'rejection_reason' => $this->rejectionReason,
        ]);
    }
}
