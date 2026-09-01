<?php

namespace App\Notifications\Finance;

use App\Domains\Keuangan\Models\Tagihan;
use App\Models\WhatsAppTemplate;
use Illuminate\Notifications\Messages\MailMessage;

class TagihanDirevisiNotification extends FinanceNotification
{
    public function __construct(public Tagihan $tagihan, public float $netAmountLama) {}

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
            'message' => "Tagihan {$this->tagihan->jenisTagihan?->nama} direvisi: Rp".number_format($this->netAmountLama, 0, ',', '.').' -> Rp'.number_format((float) $this->tagihan->net_amount, 0, ',', '.').'.',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tagihan Direvisi')
            ->line("Tagihan {$this->tagihan->jenisTagihan?->nama} telah direvisi.")
            ->line('Nominal lama: Rp'.number_format($this->netAmountLama, 0, ',', '.'))
            ->line('Nominal baru: Rp'.number_format((float) $this->tagihan->net_amount, 0, ',', '.'));
    }

    public function toWhatsApp(object $notifiable): ?string
    {
        return WhatsAppTemplate::renderKode('tagihan_direvisi', [
            'jenis_tagihan' => $this->tagihan->jenisTagihan?->nama ?? '',
            'net_amount_lama' => number_format($this->netAmountLama, 0, ',', '.'),
            'net_amount_baru' => number_format((float) $this->tagihan->net_amount, 0, ',', '.'),
        ]);
    }
}
