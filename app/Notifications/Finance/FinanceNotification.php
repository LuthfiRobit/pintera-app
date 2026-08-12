<?php

namespace App\Notifications\Finance;

use Illuminate\Notifications\Notification;

abstract class FinanceNotification extends Notification
{
    protected bool $allowWa = true;

    protected bool $allowEmail = true;

    public function withAllowedChannels(bool $wa, bool $email): static
    {
        $this->allowWa = $wa;
        $this->allowEmail = $email;

        return $this;
    }

    abstract public function isUrgent(): bool;

    protected function baseChannels(): array
    {
        $channels = ['database'];

        if ($this->allowEmail) {
            $channels[] = 'mail';
        }

        if ($this->allowWa) {
            $channels[] = 'whatsapp';
        }

        return $channels;
    }
}
