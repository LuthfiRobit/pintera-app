<?php

namespace App\Console\Commands;

use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Pendaftaran;
use App\Models\Siswa;
use Illuminate\Console\Command;

class BackfillTagihanPersonId extends Command
{
    protected $signature = 'keuangan:backfill-tagihan-person-id';

    protected $description = 'Backfill tagihan.person_id from each row\'s tagihable (Pendaftaran->calonMurid or Siswa directly).';

    public function handle(): int
    {
        $failed = [];
        $processed = 0;
        $succeeded = 0;

        Tagihan::whereNull('person_id')->chunkById(200, function ($tagihanRows) use (&$failed, &$processed, &$succeeded) {
            foreach ($tagihanRows as $tagihan) {
                $processed++;

                $personId = match ($tagihan->tagihable_type) {
                    Pendaftaran::class => Pendaftaran::find($tagihan->tagihable_id)?->calonMurid?->person_id,
                    Siswa::class => Siswa::withoutGlobalScopes()->find($tagihan->tagihable_id)?->person_id,
                    default => null,
                };

                if ($personId === null) {
                    $failed[] = ['id' => $tagihan->id, 'reason' => "tagihable_type={$tagihan->tagihable_type} tagihable_id={$tagihan->tagihable_id} tidak bisa di-resolve ke person_id"];

                    continue;
                }

                $tagihan->update(['person_id' => $personId]);
                $succeeded++;
            }
        });

        $this->info("Diproses: {$processed}, berhasil: {$succeeded}, gagal: ".count($failed));

        foreach ($failed as $item) {
            $this->warn("Tagihan #{$item['id']}: {$item['reason']}");
        }

        return self::SUCCESS;
    }
}
