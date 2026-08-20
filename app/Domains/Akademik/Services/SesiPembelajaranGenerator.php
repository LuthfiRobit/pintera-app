<?php

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Enums\Hari;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class SesiPembelajaranGenerator
{
    /**
     * @return Collection<int, SesiPembelajaran>
     */
    public function generateUntukTanggal(Kelas $kelas, CarbonInterface $tanggal, int $semesterId): Collection
    {
        $resolusi = (new KalenderAkademikResolver)->resolve($kelas->lembaga, $tanggal);

        if ($resolusi['libur'] || $kelas->pola_jam_id === null) {
            return collect();
        }

        $hari = Hari::fromCarbonDayOfWeek($tanggal->dayOfWeek);

        $jadwalHariIni = JadwalPelajaran::where('kelas_id', $kelas->id)
            ->where('semester_id', $semesterId)
            ->whereHas('jamPelajaran', fn ($q) => $q->where('pola_jam_id', $kelas->pola_jam_id)->where('hari', $hari->value))
            ->with('jamPelajaran')
            ->get()
            ->sortBy(fn (JadwalPelajaran $jadwal) => $jadwal->jamPelajaran->urutan)
            ->values();

        return $this->kelompokkanJadiBlok($jadwalHariIni)
            ->map(fn (Collection $blok) => $this->buatSesi($kelas, $blok, $tanggal));
    }

    /**
     * Groups same-day JadwalPelajaran rows (already sorted by jam_pelajaran.urutan) into
     * blocks of consecutive slots sharing the same mata_pelajaran_id and guru_id — a
     * "double period" taught by the same guru is one teaching session, not two.
     *
     * @param  Collection<int, JadwalPelajaran>  $jadwalHariIni
     * @return Collection<int, Collection<int, JadwalPelajaran>>
     */
    private function kelompokkanJadiBlok(Collection $jadwalHariIni): Collection
    {
        $semuaBlok = collect();
        $blokSaatIni = collect();

        foreach ($jadwalHariIni as $jadwal) {
            if ($blokSaatIni->isNotEmpty()) {
                $terakhir = $blokSaatIni->last();
                $berurutan = $jadwal->jamPelajaran->urutan === $terakhir->jamPelajaran->urutan + 1;
                $samaMapelDanGuru = $jadwal->mata_pelajaran_id === $terakhir->mata_pelajaran_id
                    && $jadwal->guru_id === $terakhir->guru_id;

                if (! ($berurutan && $samaMapelDanGuru)) {
                    $semuaBlok->push($blokSaatIni);
                    $blokSaatIni = collect();
                }
            }

            $blokSaatIni->push($jadwal);
        }

        if ($blokSaatIni->isNotEmpty()) {
            $semuaBlok->push($blokSaatIni);
        }

        return $semuaBlok;
    }

    /**
     * @param  Collection<int, JadwalPelajaran>  $blok  one or more consecutive same-mapel/guru slots
     */
    private function buatSesi(Kelas $kelas, Collection $blok, CarbonInterface $tanggal): SesiPembelajaran
    {
        $jadwalPertama = $blok->first();
        $jadwalTerakhir = $blok->last();

        $sesi = SesiPembelajaran::firstOrCreate(
            [
                'jadwal_pelajaran_id' => $jadwalPertama->id,
                'tanggal' => $tanggal->toDateString(),
            ],
            [
                'kelas_id' => $kelas->id,
                'guru_id' => $jadwalPertama->guru_id,
                'mata_pelajaran_id' => $jadwalPertama->mata_pelajaran_id,
                'jam_mulai' => $jadwalPertama->jamPelajaran->jam_mulai,
                'jam_selesai' => $jadwalTerakhir->jamPelajaran->jam_selesai,
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
