<?php
// app/Console/Commands/BackfillAkunLama.php

namespace App\Console\Commands;

use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Services\AkunSiswaGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillAkunLama extends Command
{
    protected $signature = 'akun:backfill-lama {--dry-run : Show what would change without writing anything}';

    protected $description = 'Flag pre-existing guru accounts for a forced password change and create login accounts for pre-existing siswa that do not have one yet';

    public function handle(AkunSiswaGenerator $generator): int
    {
        $dryRun = $this->option('dry-run');

        $guruUserIds = Guru::withoutGlobalScopes()
            ->whereHas('user', fn ($q) => $q->where('must_change_password', false))
            ->with('user')
            ->get()
            ->pluck('user.id');

        $this->info("Guru accounts to flag for forced password change: {$guruUserIds->count()}");

        if (! $dryRun && $guruUserIds->isNotEmpty()) {
            DB::table('users')->whereIn('id', $guruUserIds)->update(['must_change_password' => true]);
        }

        $siswaTanpaAkun = Siswa::withoutGlobalScopes()->whereNull('user_id')->get();

        $this->info("Siswa accounts to create: {$siswaTanpaAkun->count()}");

        if (! $dryRun) {
            foreach ($siswaTanpaAkun as $siswa) {
                $lembaga = Lembaga::withoutGlobalScopes()->find($siswa->lembaga_id);

                if ($lembaga === null) {
                    $this->warn("Skipping siswa #{$siswa->id} — lembaga #{$siswa->lembaga_id} not found.");

                    continue;
                }

                $user = $generator->buat($siswa->nama_lengkap, $siswa->nis, $lembaga);
                $siswa->update(['user_id' => $user->id]);
            }
        }

        if ($dryRun) {
            $this->comment('--dry-run: no changes were written.');
        }

        return self::SUCCESS;
    }
}
