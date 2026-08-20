<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Actions\Sesi;

use App\Domains\Kasus\DataTransferObjects\JadwalkanSesiData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusSesi;
use App\Models\Scopes\TenantScope;
use App\Notifications\SesiDijadwalkanNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class JadwalkanSesiAction
{
    public function execute(Kasus $kasus, JadwalkanSesiData $data): Collection
    {
        $created = DB::transaction(function () use ($data, $kasus) {
            $rows = collect($data->sesi)->map(fn ($row) => KasusSesi::create([
                'kasus_id' => $kasus->id,
                'dijadwalkan_pada' => $row['dijadwalkan_pada'],
                'peserta' => $row['peserta'],
                'lokasi_mode' => $row['lokasi_mode'],
            ]));

            if ($kasus->status->value === 'ditugaskan') {
                $kasus->update(['status' => 'berjalan']);
            }

            return $rows;
        });

        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();

        foreach ($created as $sesi) {
            if (in_array($sesi->peserta, ['siswa', 'keduanya'], true)) {
                $siswa?->user()->withoutGlobalScope(TenantScope::class)->first()?->notify(new SesiDijadwalkanNotification($sesi));
            }
            if (in_array($sesi->peserta, ['orang_tua', 'keduanya'], true)) {
                $kontakUtama = $siswa?->orangTua()->wherePivot('is_kontak_utama', true)->first();
                $kontakUtama?->notify(new SesiDijadwalkanNotification($sesi));
            }
        }

        return $created;
    }
}
