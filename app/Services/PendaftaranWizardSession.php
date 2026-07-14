<?php

namespace App\Services;

use App\Models\JalurPpdb;
use App\Models\Lembaga;

class PendaftaranWizardSession
{
    public function key(Lembaga $lembaga, JalurPpdb $jalur): string
    {
        return "spmb_wizard.{$lembaga->id}.{$jalur->id}";
    }

    public function get(Lembaga $lembaga, JalurPpdb $jalur): array
    {
        return session($this->key($lembaga, $jalur), []);
    }

    public function put(Lembaga $lembaga, JalurPpdb $jalur, array $data): void
    {
        $existing = $this->get($lembaga, $jalur);
        session([$this->key($lembaga, $jalur) => array_merge($existing, $data)]);
    }

    public function clear(Lembaga $lembaga, JalurPpdb $jalur): void
    {
        session()->forget($this->key($lembaga, $jalur));
    }
}
