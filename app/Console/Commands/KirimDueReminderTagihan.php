<?php

namespace App\Console\Commands;

use App\Models\NotificationLog;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Notifications\Finance\DueReminderNotification;
use App\Services\Finance\NotificationDispatcher;
use Illuminate\Console\Command;

class KirimDueReminderTagihan extends Command
{
    protected $signature = 'billing:kirim-due-reminder';

    protected $description = 'Kirim pengingat H-3 dan H-1 sebelum tanggal jatuh_tempo tagihan yang belum lunas';

    public function handle(NotificationDispatcher $dispatcher): int
    {
        $h3 = now()->addDays(3)->toDateString();
        $h1 = now()->addDay()->toDateString();

        $tagihans = Tagihan::whereIn('status', ['belum_bayar', 'sebagian'])
            ->whereIn('jatuh_tempo', [$h3, $h1])
            ->with('jenisTagihan')
            ->get();

        $terkirim = 0;

        foreach ($tagihans as $tagihan) {
            if ($tagihan->tagihable_type !== Siswa::class) {
                continue;
            }

            $siswa = $tagihan->tagihable;
            if ($siswa === null) {
                continue;
            }

            $isUrgent = $tagihan->jatuh_tempo->toDateString() === $h1;
            $eventKey = DueReminderNotification::class;

            $sudahDikirimHariIni = NotificationLog::where('event_key', $eventKey)
                ->where('payload->tagihan_id', $tagihan->id)
                ->whereDate('created_at', now()->toDateString())
                ->exists();

            if ($sudahDikirimHariIni) {
                continue;
            }

            $kontakUtama = $siswa->orangTua()->wherePivot('is_kontak_utama', true)->first();
            if ($kontakUtama === null) {
                continue;
            }

            $dispatcher->send($kontakUtama, new DueReminderNotification($tagihan, $isUrgent), 'finance', ['tagihan_id' => $tagihan->id]);
            $terkirim++;
        }

        $this->info("Reminder terkirim untuk {$terkirim} tagihan.");

        return self::SUCCESS;
    }
}
