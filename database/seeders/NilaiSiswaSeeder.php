<?php
// database/seeders/NilaiSiswaSeeder.php

namespace Database\Seeders;

use App\Models\Asesmen;
use App\Models\Kelas;
use App\Models\KomponenPenilaian;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
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

            if ($lembaga->npsn === '20223344') {
                $this->seedSmpNilai($lembaga, $aktif);
            } else {
                $this->seedGenericNilai($lembaga, $aktif);
            }
        }
    }

    private function seedSmpNilai(Lembaga $smp, TahunAjaran $aktif): void
    {
        $kelasA = Kelas::where('lembaga_id', $smp->id)->where('tahun_ajaran_id', $aktif->id)->where('nama', 'VII-A')->first();
        if (! $kelasA) {
            return;
        }

        $siswaA = Siswa::where('kelas_id', $kelasA->id)->orderBy('nis')->get();
        $mtk = MataPelajaran::where('lembaga_id', $smp->id)->where('nama', 'Matematika')->first();
        $ipa = MataPelajaran::where('lembaga_id', $smp->id)->where('nama', 'Ilmu Pengetahuan Alam (IPA)')->first();

        if ($mtk && $siswaA->isNotEmpty()) {
            $asesmenMtk = Asesmen::where('kelas_id', $kelasA->id)->where('mata_pelajaran_id', $mtk->id)->first();
            $tpMtk1 = KomponenPenilaian::where('mata_pelajaran_id', $mtk->id)->where('kode', 'TP.1.1')->first();

            if ($asesmenMtk && $tpMtk1) {
                $skorMtk = [89, 92, 78, 85, 95];
                $catatanMtk = [
                    'Menunjukkan pemahaman yang sangat baik dalam operasi aritmetika dan aljabar.',
                    'Sangat unggul dalam analisis soal cerita dan pemecahan masalah bilangan.',
                    'Perlu penguatan pada pemahaman representasi grafik dan fungsi.',
                    'Cukup baik, konsisten dalam menyelesaikan latihan operasi bilangan bulat.',
                    'Sempurna dalam menguasai seluruh indikator Tujuan Pembelajaran 1 dan 2.',
                ];

                foreach ($siswaA as $i => $siswa) {
                    NilaiSiswa::updateOrCreate(
                        ['asesmen_id' => $asesmenMtk->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $tpMtk1->id],
                        ['nilai_angka' => $skorMtk[$i] ?? 80, 'catatan' => $catatanMtk[$i] ?? 'Baik']
                    );
                }
            }
        }

        if ($ipa && $siswaA->isNotEmpty()) {
            $asesmenIpa = Asesmen::where('kelas_id', $kelasA->id)->where('mata_pelajaran_id', $ipa->id)->first();
            $tpIpa1 = KomponenPenilaian::where('mata_pelajaran_id', $ipa->id)->where('kode', 'TP.IPA.1')->first();

            if ($asesmenIpa && $tpIpa1) {
                $skorIpa = [84, 89, 92, 82, 88];
                foreach ($siswaA as $i => $siswa) {
                    NilaiSiswa::updateOrCreate(
                        ['asesmen_id' => $asesmenIpa->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $tpIpa1->id],
                        ['nilai_angka' => $skorIpa[$i] ?? 85, 'catatan' => 'Mampu menggunakan jangka sorong dan mikrometer sekrup dengan ketelitian baik.']
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
