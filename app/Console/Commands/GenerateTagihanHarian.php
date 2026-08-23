<?php
// app/Console/Commands/GenerateTagihanHarian.php

namespace App\Console\Commands;

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Scopes\TenantScope;
use App\Services\TagihanBillingGenerator;
use Illuminate\Console\Command;

class GenerateTagihanHarian extends Command
{
    protected $signature = 'billing:generate-harian';

    protected $description = 'Cron harian: generate tagihan untuk semua jenis_tagihan mode otomatis yang jatuh tanggal_generate hari ini';

    public function handle(TagihanBillingGenerator $generator): int
    {
        $today = now();

        // withoutGlobalScope: cron runs with no authenticated user so TenantScope would be a
        // no-op here anyway, but stays explicit per this plan's tenant-scope constraint rather
        // than relying on "no session exists right now" to make it safe.
        $kandidat = JenisTagihan::withoutGlobalScope(TenantScope::class)
            ->where('mode', 'otomatis')
            ->where('is_active', true)
            ->where('tanggal_generate', $today->day)
            ->where('tanggal_mulai', '<=', $today->toDateString())
            ->where(function ($q) use ($today) {
                $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $today->toDateString());
            })
            ->get();

        foreach ($kandidat as $jenisTagihan) {
            try {
                $generator->generate($jenisTagihan, 'cron');
            } catch (\Throwable $e) {
                $this->error("Gagal memproses jenis_tagihan #{$jenisTagihan->id} ({$jenisTagihan->nama}): {$e->getMessage()}");
            }
        }

        $this->info("{$kandidat->count()} jenis tagihan diproses.");

        return self::SUCCESS;
    }
}
