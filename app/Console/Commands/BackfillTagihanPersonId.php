<?php

namespace App\Console\Commands;

use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Pendaftaran;
use App\Models\Siswa;
use Illuminate\Console\Command;

/**
 * Kept as deployment tooling even though tagihan.person_id is now NOT NULL
 * in this branch's own schema history (migration 2026_09_01_000002): a
 * fresh environment (staging/production) restoring an older dump must run
 * this BEFORE `php artisan migrate` applies that NOT NULL migration, or the
 * migration itself will fail on any row with an unresolvable person_id.
 * Do not delete until a future, separately-scoped cleanup task confirms
 * every target environment has already passed through this migration.
 */
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
