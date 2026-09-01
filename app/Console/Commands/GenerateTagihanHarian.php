<?php

namespace App\Console\Commands;

use App\Domains\Keuangan\Enums\TipeTagihan;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Services\TagihanBillingGenerator;
use App\Models\Scopes\TenantScope;
use Illuminate\Console\Command;

class GenerateTagihanHarian extends Command
{
    protected $signature = 'billing:generate-harian';

    protected $description = 'Cron harian: generate tagihan untuk semua jenis_tagihan mode otomatis yang jadwalnya jatuh hari ini';

    public function handle(TagihanBillingGenerator $generator): int
    {
        $today = now();

        // withoutGlobalScope: cron runs with no authenticated user so TenantScope would be a
        // no-op here anyway, but stays explicit per this plan's tenant-scope constraint rather
        // than relying on "no session exists right now" to make it safe.
        $kandidat = JenisTagihan::withoutGlobalScope(TenantScope::class)
            ->where('mode', 'otomatis')
            ->where('is_active', true)
            ->where('tanggal_mulai', '<=', $today->toDateString())
            ->where(function ($q) use ($today) {
                $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $today->toDateString());
            })
            ->where(function ($q) use ($today) {
                $q->where('tipe', TipeTagihan::Harian->value)
                    ->orWhere(function ($m) use ($today) {
                        $m->where('tipe', TipeTagihan::Mingguan->value)
                            ->where('hari_generate', $today->dayOfWeekIso);
                    })
                    ->orWhere(function ($b) use ($today) {
                        $b->where('tipe', TipeTagihan::Bulanan->value)
                            ->where('tanggal_generate', $today->day);
                    })
                    ->orWhere(function ($t) use ($today) {
                        $t->where('tipe', TipeTagihan::Tahunan->value)
                            ->where('bulan_generate', $today->month)
                            ->where('tanggal_generate', $today->day);
                    });
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
