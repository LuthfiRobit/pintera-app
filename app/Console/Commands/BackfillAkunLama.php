<?php
// app/Console/Commands/BackfillAkunLama.php

namespace App\Console\Commands;

use App\Enums\StatusSiswa;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Services\AkunSiswaGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * One-off backfill for pre-existing (pre-account-system) guru and siswa records.
 *
 * The guru-flagging half (must_change_password) is intended to run ONCE, right after
 * this feature is deployed. Re-running it re-flags every guru who has since changed
 * their password — it selects on the CURRENT must_change_password=false state, which
 * after a first successful run includes voluntary changers, not just untouched legacy
 * accounts. Use --siswa-only on any subsequent run to avoid re-flagging guru.
 */
class BackfillAkunLama extends Command
{
    protected $signature = 'akun:backfill-lama
        {--dry-run : Show what would change without writing anything}
        {--guru-only : Only flag guru accounts for forced password change, skip siswa account creation}
        {--siswa-only : Only create missing siswa accounts, skip flagging guru (safe to re-run)}';

    protected $description = 'One-time backfill: flag pre-existing guru accounts for a forced password change, and create login accounts for pre-existing active siswa that do not have one yet. Re-running the guru half re-flags guru who already changed their password — use --siswa-only for safe re-runs.';

    public function handle(AkunSiswaGenerator $generator): int
    {
        $dryRun = $this->option('dry-run');
        $siswaOnly = $this->option('siswa-only');
        $guruOnly = $this->option('guru-only');

        if (! $siswaOnly) {
            $this->flagGuru($dryRun);
        }

        if (! $guruOnly) {
            $this->backfillSiswa($generator, $dryRun);
        }

        if ($dryRun) {
            $this->comment('--dry-run: no changes were written.');
        }

        return self::SUCCESS;
    }

    private function flagGuru(bool $dryRun): void
    {
        $guruUserIds = Guru::withoutGlobalScopes()
            ->whereHas('user', fn ($q) => $q->where('must_change_password', false))
            ->with('user')
            ->get()
            ->pluck('user.id');

        $this->info("Guru accounts to flag for forced password change: {$guruUserIds->count()}");

        if (! $dryRun && $guruUserIds->isNotEmpty()) {
            DB::table('users')->whereIn('id', $guruUserIds)->update(['must_change_password' => true]);
        }
    }

    private function backfillSiswa(AkunSiswaGenerator $generator, bool $dryRun): void
    {
        // Only currently-active siswa get a backfilled account — alumni, transferred-out,
        // and expelled students don't need a live login with a guessable (NIS) password.
        $siswaTanpaAkun = Siswa::withoutGlobalScopes()
            ->whereNull('user_id')
            ->where('status', StatusSiswa::Aktif->value)
            ->get();

        $this->info("Siswa accounts to create: {$siswaTanpaAkun->count()}");

        if ($dryRun) {
            return;
        }

        foreach ($siswaTanpaAkun as $siswa) {
            $lembaga = Lembaga::withoutGlobalScopes()->find($siswa->lembaga_id);

            if ($lembaga === null) {
                $this->warn("Skipping siswa #{$siswa->id} — lembaga #{$siswa->lembaga_id} not found.");

                continue;
            }

            try {
                DB::transaction(function () use ($generator, $siswa, $lembaga) {
                    $user = $generator->buat($siswa->nama_lengkap, $siswa->nis, $lembaga);
                    $siswa->update(['user_id' => $user->id]);
                });
            } catch (Throwable $e) {
                $this->warn("Skipping siswa #{$siswa->id} — failed to create account: {$e->getMessage()}");

                continue;
            }
        }
    }
}
