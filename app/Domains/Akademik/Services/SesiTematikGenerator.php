<?php

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Enums\Hari;
use App\Models\Kelas;
use Carbon\CarbonInterface;

class SesiTematikGenerator
{
    public function generateUntukTanggal(Kelas $kelas, CarbonInterface $tanggal, int $semesterId): ?SesiPembelajaran
    {
        if ($kelas->wali_kelas_guru_id === null || $kelas->pola_jam_id === null) {
            return null;
        }

        $resolusi = (new KalenderAkademikResolver)->resolve($kelas->lembaga, $tanggal);

        if ($resolusi['libur']) {
            return null;
        }

        $hari = Hari::fromCarbonDayOfWeek($tanggal->dayOfWeek);

        $slotHariIni = $kelas->polaJam->jamPelajaran()
            ->where('hari', $hari->value)
            ->isPelajaran()
            ->orderBy('urutan')
            ->get();

        if ($slotHariIni->isEmpty()) {
            return null;
        }

        $sesi = SesiPembelajaran::firstOrCreate(
            [
                'kelas_id' => $kelas->id,
                'tanggal' => $tanggal->toDateString(),
                'jadwal_pelajaran_id' => null,
            ],
            [
                'guru_id' => $kelas->wali_kelas_guru_id,
                'mata_pelajaran_id' => null,
                'jam_mulai' => $slotHariIni->first()->jam_mulai,
                'jam_selesai' => $slotHariIni->last()->jam_selesai,
                'status' => 'terlaksana',
            ]
        );

        if ($sesi->wasRecentlyCreated) {
            foreach ($kelas->siswa()->where('status', 'aktif')->get() as $siswa) {
                Presensi::firstOrCreate(
                    ['sesi_pembelajaran_id' => $sesi->id, 'siswa_id' => $siswa->id],
                    ['status' => 'hadir']
                );
            }
        }

        return $sesi;
    }
}
