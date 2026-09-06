<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\Enums\StatusPresensi;
use App\Domains\Akademik\Models\Presensi;
use App\Notifications\Akademik\PresensiPengecualianNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

final class PresensiNotificationService
{
    private const STATUS_PENGECUALIAN = ['izin', 'sakit', 'alpa', 'terlambat'];

    public function kirimJikaPerluAtasPerubahan(Presensi $presensi, ?string $statusLama): void
    {
        $statusBaru = $presensi->status instanceof StatusPresensi ? $presensi->status->value : (string) $presensi->status;

        if ($statusBaru === $statusLama) {
            return;
        }

        if (! in_array($statusBaru, self::STATUS_PENGECUALIAN, true)) {
            return;
        }

        $kontakUtama = $presensi->siswa->orangTua()->wherePivot('is_kontak_utama', true)->first();

        if ($kontakUtama === null) {
            return;
        }

        try {
            Notification::send($kontakUtama, new PresensiPengecualianNotification($presensi));
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim PresensiPengecualianNotification: '.$e->getMessage());
        }
    }
}
