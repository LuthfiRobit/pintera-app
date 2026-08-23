<?php

namespace App\Notifications\Finance;

use App\Domains\Keuangan\Models\Tagihan;
use App\Models\WhatsAppTemplate;
use Illuminate\Notifications\Messages\MailMessage;

class SaldoTidakCukupNotification extends FinanceNotification
{
    public function __construct(public Tagihan $tagihan, public float $selisih)
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
            'message' => "Saldo wallet tidak mencukupi untuk {$this->tagihan->jenisTagihan?->nama}. Kekurangan: Rp".number_format($this->selisih, 0, ',', '.'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Saldo Tidak Cukup')
            ->line("Saldo wallet Anda tidak mencukupi untuk pembayaran {$this->tagihan->jenisTagihan?->nama}.")
            ->line('Kekurangan: Rp'.number_format($this->selisih, 0, ',', '.'));
    }

    public function toWhatsApp(object $notifiable): ?string
    {
        return WhatsAppTemplate::renderKode('saldo_tidak_cukup', [
            'tagihan' => $this->tagihan->jenisTagihan?->nama ?? '',
            'selisih' => number_format($this->selisih, 0, ',', '.'),
        ]);
    }
}
