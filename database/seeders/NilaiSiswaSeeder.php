<?php

// database/seeders/NilaiSiswaSeeder.php

namespace Database\Seeders;

use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class NilaiSiswaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::all() as $lembaga) {
            $aktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();
            if (! $aktif) {
                continue;
            }

            if ($lembaga->npsn === '20223333') {
                $this->seedSdNilai($lembaga, $aktif);
            } else {
                $this->seedGenericNilai($lembaga, $aktif);
            }
        }
    }

    /**
     * Sebelumnya cuma mengisi nilai untuk "Kelas 1-A" (2 mapel), jadi 11 dari 12 kelas
     * selalu 0 NilaiSiswa meski sudah ada Asesmen (setelah AsesmenSeeder diperbaiki
     * mencakup semua kelas). Sekarang generic: ambil SEMUA Asesmen tiap kelas (apa pun
     * mapelnya) dan isi nilai utk semua siswa di kelas itu, dengan skor & catatan
     * bervariasi per siswa (bukan nilai flat sama rata) supaya rekap rapor terasa nyata.
     */
    private function seedSdNilai(Lembaga $sd, TahunAjaran $aktif): void
    {
        $kelasList = Kelas::where('lembaga_id', $sd->id)->where('tahun_ajaran_id', $aktif->id)->get();

        $catatanVariasi = [
            'Menunjukkan pemahaman yang sangat baik, mampu mengerjakan latihan tanpa bantuan.',
            'Cukup baik, masih perlu bimbingan pada beberapa soal cerita.',
            'Sangat unggul, selalu aktif bertanya dan mengerjakan tugas tambahan.',
            'Perlu penguatan pada pemahaman konsep dasar, disarankan les tambahan.',
            'Baik, konsisten dalam menyelesaikan latihan harian.',
        ];

        foreach ($kelasList as $kelas) {
            $siswaList = Siswa::where('kelas_id', $kelas->id)->orderBy('nis')->get();
            if ($siswaList->isEmpty()) {
                continue;
            }

            $asesmenList = Asesmen::where('kelas_id', $kelas->id)->where('subjek_type', 'mata_pelajaran')->get();

            foreach ($asesmenList as $asesmen) {
                $tp = KomponenPenilaian::where('subjek_type', 'mata_pelajaran')->where('subjek_id', $asesmen->subjek_id)->where('semester_id', $asesmen->semester_id)->first();
                if (! $tp) {
                    continue;
                }

                foreach ($siswaList as $i => $siswa) {
                    $skor = 70 + (($i * 7 + $asesmen->id * 3 + 13) % 30); // rentang 70-99, bervariasi per siswa & asesmen
                    NilaiSiswa::updateOrCreate(
                        ['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $tp->id],
                        ['nilai_angka' => $skor, 'catatan' => $catatanVariasi[$i % count($catatanVariasi)]]
                    );
                }
            }
        }
    }

    private function seedGenericNilai(Lembaga $lembaga, TahunAjaran $aktif): void
    {
        $kelasList = Kelas::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $aktif->id)->get();

        foreach ($kelasList as $kelas) {
            $asesmenList = Asesmen::where('kelas_id', $kelas->id)->get();
            $siswaList = Siswa::where('kelas_id', $kelas->id)->get();

            foreach ($asesmenList as $asesmen) {
                $tp = $asesmen->komponenPenilaian()->first();
                if (! $tp) {
                    continue;
                }

                foreach ($siswaList as $siswa) {
                    NilaiSiswa::updateOrCreate(
                        ['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $tp->id],
                        ['nilai_angka' => 85, 'catatan' => 'Baik, memenuhi kriteria ketercapaian tujuan pembelajaran.']
                    );
                }
            }
        }
    }
}
