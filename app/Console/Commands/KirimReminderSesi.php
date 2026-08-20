<?php
// app/Console/Commands/KirimReminderSesi.php

namespace App\Console\Commands;

use App\Domains\Kasus\Models\KasusSesi;
use App\Models\Scopes\TenantScope;
use App\Notifications\SesiReminderNotification;
use Illuminate\Console\Command;

class KirimReminderSesi extends Command
{
    protected $signature = 'kasus:kirim-reminder-sesi';

    protected $description = 'Send H-1 reminder notifications for kasus_sesi scheduled tomorrow with status terjadwal';

    public function handle(): int
    {
        $besok = now()->addDay();

        $sesiBesok = KasusSesi::whereDate('dijadwalkan_pada', $besok->toDateString())
            ->where('status', 'terjadwal')
            ->with('kasus')
            ->get();

        foreach ($sesiBesok as $sesi) {
            $siswa = $sesi->kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();

            if ($siswa === null) {
                continue;
            }

            $sesi->kasus->setRelation('siswa', $siswa);

            if (in_array($sesi->peserta, ['siswa', 'keduanya'], true)) {
                $siswa->user?->notify(new SesiReminderNotification($sesi));
            }
            if (in_array($sesi->peserta, ['orang_tua', 'keduanya'], true)) {
                $kontakUtama = $siswa->orangTua()->wherePivot('is_kontak_utama', true)->first();
                $kontakUtama?->notify(new SesiReminderNotification($sesi));
            }
        }

        $this->info("Reminder terkirim untuk {$sesiBesok->count()} sesi.");

        return self::SUCCESS;
    }
}
