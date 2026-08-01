<?php
// database/seeders/KomponenPenilaianSeeder.php

namespace Database\Seeders;

use App\Models\KomponenPenilaian;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class KomponenPenilaianSeeder extends Seeder
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
                $this->seedSmpKomponen($lembaga, $semester);
            } else {
                $this->seedGenericKomponen($lembaga, $semester);
            }
        }
    }

    private function seedSmpKomponen(Lembaga $smp, Semester $semester): void
    {
        $mtk = MataPelajaran::where('lembaga_id', $smp->id)->where('nama', 'Matematika')->first();
        $ipa = MataPelajaran::where('lembaga_id', $smp->id)->where('nama', 'Ilmu Pengetahuan Alam (IPA)')->first();

        if ($mtk) {
            KomponenPenilaian::firstOrCreate(
                ['mata_pelajaran_id' => $mtk->id, 'semester_id' => $semester->id, 'kode' => 'TP.1.1'],
                ['deskripsi' => 'Peserta didik dapat menyelesaikan operasi aritmetika pada bilangan bulat dan pecahan.', 'kktp' => 'Minimal 75% benar']
            );
            KomponenPenilaian::firstOrCreate(
                ['mata_pelajaran_id' => $mtk->id, 'semester_id' => $semester->id, 'kode' => 'TP.1.2'],
                ['deskripsi' => 'Peserta didik mendeskripsikan dan mengekspresikan relasi serta fungsi dengan representasi grafik.', 'kktp' => 'Mampu menggambar grafik linier']
            );
        }

        if ($ipa) {
            KomponenPenilaian::firstOrCreate(
                ['mata_pelajaran_id' => $ipa->id, 'semester_id' => $semester->id, 'kode' => 'TP.IPA.1'],
                ['deskripsi' => 'Peserta didik memahami besaran pokok dan besaran turunan dalam satuan internasional.', 'kktp' => 'Tepat menggunakan alat ukur']
            );
        }
    }

    private function seedGenericKomponen(Lembaga $lembaga, Semester $semester): void
    {
        $mapelList = MataPelajaran::where('lembaga_id', $lembaga->id)->get();

        foreach ($mapelList as $mapel) {
            KomponenPenilaian::firstOrCreate(
                ['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'kode' => 'TP.1'],
                ['deskripsi' => "Tujuan Pembelajaran Dasar untuk mata pelajaran {$mapel->nama}.", 'kktp' => 'Tercapai Sesuai Kriteria']
            );
        }
    }
}
