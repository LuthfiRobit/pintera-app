<?php
// database/seeders/AsesmenSeeder.php

namespace Database\Seeders;

use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Domains\Akademik\Models\MataPelajaran;
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

            if ($lembaga->npsn === '20223333') {
                $this->seedSdAsesmen($lembaga, $aktif, $semester);
            } else {
                $this->seedGenericAsesmen($lembaga, $aktif, $semester);
            }
        }
    }

    private function seedSdAsesmen(Lembaga $sd, TahunAjaran $aktif, Semester $semester): void
    {
        $kelasA = Kelas::where('lembaga_id', $sd->id)->where('tahun_ajaran_id', $aktif->id)->where('nama', 'Kelas 1-A')->first();
        if (! $kelasA) {
            return;
        }

        $mtk = MataPelajaran::where('lembaga_id', $sd->id)->where('nama', 'Matematika')->first();
        $ipas = MataPelajaran::where('lembaga_id', $sd->id)->where('nama', 'Ilmu Pengetahuan Alam dan Sosial (IPAS)')->first();
        $guruHendra = Guru::where('email', 'hendra.gunawan@demo.test')->first();
        $guruMaya = Guru::where('email', 'maya.anggraini@demo.test')->first();

        if ($mtk && $guruHendra) {
            $tpMtk1 = KomponenPenilaian::where('subjek_type', 'mata_pelajaran')->where('subjek_id', $mtk->id)->where('semester_id', $semester->id)->first();

            $asesmenMtk = Asesmen::firstOrCreate(
                ['guru_id' => $guruHendra->id, 'kelas_id' => $kelasA->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mtk->id, 'semester_id' => $semester->id, 'judul' => 'Sumatif Lingkup Materi 1: Bilangan Cacah'],
                ['jenis' => JenisAsesmen::SumatifLingkupMateri, 'tanggal' => now()->subDays(5)->toDateString()]
            );

            if ($tpMtk1) {
                $asesmenMtk->komponenPenilaian()->syncWithoutDetaching([$tpMtk1->id]);
            }
        }

        if ($ipas && $guruMaya) {
            $tpIpas1 = KomponenPenilaian::where('subjek_type', 'mata_pelajaran')->where('subjek_id', $ipas->id)->where('semester_id', $semester->id)->first();

            $asesmenIpas = Asesmen::firstOrCreate(
                ['guru_id' => $guruMaya->id, 'kelas_id' => $kelasA->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $ipas->id, 'semester_id' => $semester->id, 'judul' => 'Sumatif Lingkup Materi 1: Pengenalan Ekosistem'],
                ['jenis' => JenisAsesmen::SumatifLingkupMateri, 'tanggal' => now()->subDays(3)->toDateString()]
            );

            if ($tpIpas1) {
                $asesmenIpas->komponenPenilaian()->syncWithoutDetaching([$tpIpas1->id]);
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

        $tp = KomponenPenilaian::where('subjek_type', 'mata_pelajaran')->where('subjek_id', $mapel->id)->first();

        foreach ($kelasList as $kelas) {
            $asesmen = Asesmen::firstOrCreate(
                ['guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'judul' => "Sumatif Lingkup Materi 1: Evaluasi Dasar {$kelas->nama}"],
                ['jenis' => JenisAsesmen::SumatifLingkupMateri, 'tanggal' => now()->subDays(4)->toDateString()]
            );

            if ($tp) {
                $asesmen->komponenPenilaian()->syncWithoutDetaching([$tp->id]);
            }
        }
    }
}
