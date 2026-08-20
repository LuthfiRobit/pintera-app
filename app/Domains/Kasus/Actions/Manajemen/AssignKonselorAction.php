<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Actions\Manajemen;

use App\Domains\Kasus\DataTransferObjects\AssignKonselorData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusConsent;
use App\Domains\Kasus\Enums\StatusKasus;
use App\Notifications\KonselorDipilihNotification;
use App\Models\Siswa;
use Illuminate\Support\Facades\DB;

final class AssignKonselorAction
{
    public function execute(Kasus $kasus, Siswa $siswa, AssignKonselorData $data): Kasus
    {
        DB::transaction(function () use ($data, $kasus) {
            $kasus->update([
                'tingkat_urgensi' => $data->tingkatUrgensi,
                'status' => StatusKasus::MenungguConsent,
                'konselor_guru_id' => $data->konselorTipe === 'guru' ? $data->konselorId : null,
                'konselor_karyawan_id' => $data->konselorTipe === 'karyawan' ? $data->konselorId : null,
            ]);

            KasusConsent::create(['kasus_id' => $kasus->id, 'jenis' => 'sesi_pendampingan']);
            KasusConsent::create(['kasus_id' => $kasus->id, 'jenis' => 'pengumpulan_media']);
        });

        $kontakUtama = $siswa->orangTua()->wherePivot('is_kontak_utama', true)->first();
        $kontakUtama?->notify(new KonselorDipilihNotification($kasus));

        return $kasus->fresh();
    }
}
