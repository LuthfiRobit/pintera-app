<?php

namespace App\Notifications\Finance;

use App\Models\Tagihan;
use App\Models\WhatsAppTemplate;
use Illuminate\Notifications\Messages\MailMessage;

class DueReminderNotification extends FinanceNotification
{
    public function __construct(public Tagihan $tagihan, private readonly bool $urgent)
    {
    }

    public function isUrgent(): bool
    {
        return $this->urgent;
    }

    public function via(object $notifiable): array
    {
        return $this->baseChannels();
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'tagihan_id' => $this->tagihan->id,
            'message' => "Tagihan {$this->tagihan->jenisTagihan?->nama} akan jatuh tempo pada ".$this->tagihan->jatuh_tempo?->format('d M Y').'.',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Pengingat Jatuh Tempo Tagihan')
            ->line("Tagihan {$this->tagihan->jenisTagihan?->nama} akan jatuh tempo pada ".$this->tagihan->jatuh_tempo?->format('d M Y').'.')
            ->line('Segera lakukan pembayaran.');
    }

    public function toWhatsApp(object $notifiable): ?string
    {
        return WhatsAppTemplate::renderKode('tagihan_jatuh_tempo', [
            'jenis_tagihan' => $this->tagihan->jenisTagihan?->nama ?? '',
            'jatuh_tempo' => $this->tagihan->jatuh_tempo?->format('d M Y') ?? '-',
        ]);
    }
}
