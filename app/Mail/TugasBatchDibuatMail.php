<?php
// app/Mail/TugasBatchDibuatMail.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class TugasBatchDibuatMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, \App\Domains\Kasus\Models\KasusTugas>  $barisTugas
     */
    public function __construct(public Collection $barisTugas)
    {
    }

    public function build(): self
    {
        $pertama = $this->barisTugas->first();
        $mulaiPada = $this->barisTugas->sortBy('mulai_pada')->first()->mulai_pada;
        $batasSelesaiPada = $this->barisTugas->sortByDesc('batas_selesai_pada')->first()->batas_selesai_pada;

        return $this->subject('Tugas Pendampingan Baru: '.$pertama->judul)
            ->view('mail.tugas-batch-dibuat')
            ->with([
                'judul' => $pertama->judul,
                'frekuensi' => $pertama->frekuensi,
                'jumlahBaris' => $this->barisTugas->count(),
                'mulaiPada' => $mulaiPada,
                'batasSelesaiPada' => $batasSelesaiPada,
            ]);
    }
}
