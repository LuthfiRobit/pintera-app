<?php

namespace App\Notifications\Finance;

use App\Models\WhatsAppTemplate;
use Illuminate\Notifications\Messages\MailMessage;

class TransferManualDisetujuiNotification extends FinanceNotification
{
    public function isUrgent(): bool
    {
        return false;
    }

    public function via(object $notifiable): array
    {
        return $this->baseChannels();
    }

    public function toDatabase(object $notifiable): array
    {
        return ['message' => 'Bukti transfer pembayaran Anda telah diverifikasi dan disetujui.'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Transfer Manual Disetujui')
            ->line('Bukti transfer pembayaran Anda telah diverifikasi dan disetujui.');
    }

    public function toWhatsApp(object $notifiable): ?string
    {
        return WhatsAppTemplate::renderKode('transfer_manual_disetujui', []);
    }
}
