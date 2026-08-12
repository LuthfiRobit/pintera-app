<?php
// app/Notifications/Finance/TagihanDiterbitkanNotification.php

namespace App\Notifications\Finance;

use App\Models\Tagihan;
use App\Models\WhatsAppTemplate;
use Illuminate\Notifications\Messages\MailMessage;

class TagihanDiterbitkanNotification extends FinanceNotification
{
    public function __construct(public Tagihan $tagihan)
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
            'message' => "Tagihan {$this->tagihan->jenisTagihan?->nama} periode {$this->tagihan->billing_period} sebesar Rp".number_format((float) $this->tagihan->net_amount, 0, ',', '.')." telah diterbitkan.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Tagihan Baru Diterbitkan')
            ->line("Tagihan {$this->tagihan->jenisTagihan?->nama} periode {$this->tagihan->billing_period} sebesar Rp".number_format((float) $this->tagihan->net_amount, 0, ',', '.')." telah diterbitkan.")
            ->line('Jatuh tempo: '.($this->tagihan->jatuh_tempo?->format('d M Y') ?? '-'));
    }

    public function toWhatsApp(object $notifiable): ?string
    {
        return WhatsAppTemplate::renderKode('tagihan_baru', [
            'jenis_tagihan' => $this->tagihan->jenisTagihan?->nama ?? '',
            'billing_period' => $this->tagihan->billing_period ?? '',
            'net_amount' => number_format((float) $this->tagihan->net_amount, 0, ',', '.'),
            'jatuh_tempo' => $this->tagihan->jatuh_tempo?->format('d M Y') ?? '-',
        ]);
    }
}
