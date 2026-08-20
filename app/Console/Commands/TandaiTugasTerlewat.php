<?php

namespace App\Console\Commands;

use App\Domains\Kasus\Models\KasusTugas;
use Illuminate\Console\Command;

class TandaiTugasTerlewat extends Command
{
    protected $signature = 'kasus:tandai-tugas-terlewat';

    protected $description = 'Mark kasus_tugas as terlewat when batas_selesai_pada has passed and it has zero submissions';

    public function handle(): int
    {
        $tugasList = KasusTugas::whereDate('batas_selesai_pada', '<', now()->toDateString())
            ->whereDoesntHave('submissions')
            ->whereNotIn('status', ['selesai', 'terlewat'])
            ->get();

        foreach ($tugasList as $tugas) {
            $tugas->update(['status' => 'terlewat']);
        }

        $this->info("{$tugasList->count()} tugas ditandai terlewat.");

        return self::SUCCESS;
    }
}
