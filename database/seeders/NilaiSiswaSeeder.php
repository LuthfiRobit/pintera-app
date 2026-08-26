<?php
// database/seeders/NilaiSiswaSeeder.php

namespace Database\Seeders;

use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Domains\Akademik\Models\MataPelajaran;
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

    private function seedSdNilai(Lembaga $sd, TahunAjaran $aktif): void
    {
        $kelasA = Kelas::where('lembaga_id', $sd->id)->where('tahun_ajaran_id', $aktif->id)->where('nama', 'Kelas 1-A')->first();
        if (! $kelasA) {
            return;
        }

        $siswaA = Siswa::where('kelas_id', $kelasA->id)->orderBy('nis')->get();
        $mtk = MataPelajaran::where('lembaga_id', $sd->id)->where('nama', 'Matematika')->first();
        $ipas = MataPelajaran::where('lembaga_id', $sd->id)->where('nama', 'Ilmu Pengetahuan Alam dan Sosial (IPAS)')->first();

        $catatanVariasi = [
            'Menunjukkan pemahaman yang sangat baik, mampu mengerjakan latihan tanpa bantuan.',
            'Cukup baik, masih perlu bimbingan pada beberapa soal cerita.',
            'Sangat unggul, selalu aktif bertanya dan mengerjakan tugas tambahan.',
            'Perlu penguatan pada pemahaman konsep dasar, disarankan les tambahan.',
            'Baik, konsisten dalam menyelesaikan latihan harian.',
        ];

        if ($mtk && $siswaA->isNotEmpty()) {
            $asesmenMtk = Asesmen::where('kelas_id', $kelasA->id)->where('subjek_type', 'mata_pelajaran')->where('subjek_id', $mtk->id)->first();
            $tpMtk1 = KomponenPenilaian::where('subjek_type', 'mata_pelajaran')->where('subjek_id', $mtk->id)->first();

            if ($asesmenMtk && $tpMtk1) {
                foreach ($siswaA as $i => $siswa) {
                    $skor = 70 + (($i * 7 + 13) % 30); // rentang 70-99, bervariasi per siswa
                    NilaiSiswa::updateOrCreate(
                        ['asesmen_id' => $asesmenMtk->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $tpMtk1->id],
                        ['nilai_angka' => $skor, 'catatan' => $catatanVariasi[$i % count($catatanVariasi)]]
                    );
                }
            }
        }

        if ($ipas && $siswaA->isNotEmpty()) {
            $asesmenIpas = Asesmen::where('kelas_id', $kelasA->id)->where('subjek_type', 'mata_pelajaran')->where('subjek_id', $ipas->id)->first();
            $tpIpas1 = KomponenPenilaian::where('subjek_type', 'mata_pelajaran')->where('subjek_id', $ipas->id)->first();

            if ($asesmenIpas && $tpIpas1) {
                foreach ($siswaA as $i => $siswa) {
                    $skor = 72 + (($i * 5 + 9) % 28); // rentang 72-99, bervariasi per siswa
                    NilaiSiswa::updateOrCreate(
                        ['asesmen_id' => $asesmenIpas->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $tpIpas1->id],
                        ['nilai_angka' => $skor, 'catatan' => $catatanVariasi[($i + 2) % count($catatanVariasi)]]
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
