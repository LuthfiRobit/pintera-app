<?php
// database/seeders/AsesmenSeeder.php

namespace Database\Seeders;

use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class AsesmenSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::all() as $lembaga) {
            $aktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();
            if (! $aktif) {
                continue;
            }

            $semester = Semester::where('tahun_ajaran_id', $aktif->id)->where('status_aktif', true)->first()
                ?? Semester::where('tahun_ajaran_id', $aktif->id)->first();

            if (! $semester) {
                continue;
            }

            if ($lembaga->npsn === '20223344') {
                $this->seedSmpAsesmen($lembaga, $aktif, $semester);
            } else {
                $this->seedGenericAsesmen($lembaga, $aktif, $semester);
            }
        }
    }

    private function seedSmpAsesmen(Lembaga $smp, TahunAjaran $aktif, Semester $semester): void
    {
        $kelasA = Kelas::where('lembaga_id', $smp->id)->where('tahun_ajaran_id', $aktif->id)->where('nama', 'VII-A')->first();
        if (! $kelasA) {
            return;
        }

        $mtk = MataPelajaran::where('lembaga_id', $smp->id)->where('nama', 'Matematika')->first();
        $ipa = MataPelajaran::where('lembaga_id', $smp->id)->where('nama', 'Ilmu Pengetahuan Alam (IPA)')->first();
        $guruBudi = Guru::where('email', 'budi.santoso@demo.test')->first();
        $guruSiti = Guru::where('email', 'siti.rahmawati@demo.test')->first();

        if ($mtk && $guruBudi) {
            $tpMtk1 = KomponenPenilaian::where('mata_pelajaran_id', $mtk->id)->where('kode', 'TP.1.1')->first();
            $tpMtk2 = KomponenPenilaian::where('mata_pelajaran_id', $mtk->id)->where('kode', 'TP.1.2')->first();

            $asesmenMtk = Asesmen::firstOrCreate(
                ['guru_id' => $guruBudi->id, 'kelas_id' => $kelasA->id, 'mata_pelajaran_id' => $mtk->id, 'semester_id' => $semester->id, 'judul' => 'Sumatif Lingkup Materi 1: Bilangan & Aljabar'],
                ['jenis' => JenisAsesmen::SumatifLingkupMateri, 'tanggal' => now()->subDays(5)->toDateString()]
            );

            if ($tpMtk1 && $tpMtk2) {
                $asesmenMtk->komponenPenilaian()->syncWithoutDetaching([$tpMtk1->id, $tpMtk2->id]);
            }
        }

        if ($ipa && $guruSiti) {
            $tpIpa1 = KomponenPenilaian::where('mata_pelajaran_id', $ipa->id)->where('kode', 'TP.IPA.1')->first();

            $asesmenIpa = Asesmen::firstOrCreate(
                ['guru_id' => $guruSiti->id, 'kelas_id' => $kelasA->id, 'mata_pelajaran_id' => $ipa->id, 'semester_id' => $semester->id, 'judul' => 'Sumatif Lingkup Materi 1: Besaran & Pengukuran'],
                ['jenis' => JenisAsesmen::SumatifLingkupMateri, 'tanggal' => now()->subDays(3)->toDateString()]
            );

            if ($tpIpa1) {
                $asesmenIpa->komponenPenilaian()->syncWithoutDetaching([$tpIpa1->id]);
            }
        }
    }

    private function seedGenericAsesmen(Lembaga $lembaga, TahunAjaran $aktif, Semester $semester): void
    {
        $kelasList = Kelas::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $aktif->id)->get();
        $guru = Guru::where('lembaga_id', $lembaga->id)->first();
        $mapel = MataPelajaran::where('lembaga_id', $lembaga->id)->first();

        if (! $guru || ! $mapel || $kelasList->isEmpty()) {
            return;
        }

        $tp = KomponenPenilaian::where('mata_pelajaran_id', $mapel->id)->first();

        foreach ($kelasList as $kelas) {
            $asesmen = Asesmen::firstOrCreate(
                ['guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'judul' => "Sumatif Lingkup Materi 1: Evaluasi Dasar {$kelas->nama}"],
                ['jenis' => JenisAsesmen::SumatifLingkupMateri, 'tanggal' => now()->subDays(4)->toDateString()]
            );

            if ($tp) {
                $asesmen->komponenPenilaian()->syncWithoutDetaching([$tp->id]);
            }
        }
    }
}
