<?php

namespace App\Notifications\Finance;

use App\Models\Tagihan;
use App\Models\WhatsAppTemplate;
use Illuminate\Notifications\Messages\MailMessage;

class PembayaranBerhasilNotification extends FinanceNotification
{
    public function __construct(public Tagihan $tagihan, public string $metode)
    {
    }

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
        return [
            'tagihan_id' => $this->tagihan->id,
            'message' => "Pembayaran {$this->tagihan->jenisTagihan?->nama} sebesar Rp".number_format((float) $this->tagihan->net_amount, 0, ',', '.')." berhasil melalui {$this->metode}.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Pembayaran Berhasil')
            ->line("Pembayaran {$this->tagihan->jenisTagihan?->nama} sebesar Rp".number_format((float) $this->tagihan->net_amount, 0, ',', '.')." berhasil melalui {$this->metode}.");
    }

    public function toWhatsApp(object $notifiable): ?string
    {
        return WhatsAppTemplate::renderKode('pembayaran_berhasil', [
            'tagihan' => $this->tagihan->jenisTagihan?->nama ?? '',
            'amount' => number_format((float) $this->tagihan->net_amount, 0, ',', '.'),
            'metode' => $this->metode,
        ]);
    }
}
