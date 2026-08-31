<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyPersonsBackfill extends Command
{
    protected $signature = 'identity:verify-backfill';

    protected $description = 'Verify every role-table row has a non-null person_id before code cutover proceeds.';

    public function handle(): int
    {
        $ok = true;

        foreach (['guru', 'karyawan', 'orang_tua', 'siswa', 'calon_murid'] as $table) {
            $missing = DB::table($table)->whereNull('person_id')->pluck('id');

            if ($missing->isNotEmpty()) {
                $ok = false;
                $this->error("{$table}: {$missing->count()} row(s) missing person_id -- ids: {$missing->implode(', ')}");
            }
        }

        if ($ok) {
            $this->info('All role-table rows have person_id populated. Safe to proceed with code cutover.');
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
