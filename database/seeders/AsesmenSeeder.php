<?php

// database/seeders/AsesmenSeeder.php

namespace Database\Seeders;

use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
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

    /**
     * Buat 1 Asesmen per kombinasi (kelas, mata pelajaran) yang BENAR-BENAR terjadwal
     * di kelas itu (`JadwalPelajaran`), untuk SEMUA kelas -- sebelumnya cuma "Kelas 1-A"
     * + 2 mapel yang di-hardcode, jadi 11 dari 12 kelas selalu 0 Asesmen (Rekap Rapor
     * kosong total untuk kelas manapun selain 1-A). Guru pengajar & subjek diambil dari
     * jadwal yang sama supaya konsisten dengan siapa yang benar-benar mengajar apa.
     */
    private function seedSdAsesmen(Lembaga $sd, TahunAjaran $aktif, Semester $semester): void
    {
        $kelasList = Kelas::where('lembaga_id', $sd->id)->where('tahun_ajaran_id', $aktif->id)->get();

        foreach ($kelasList as $kelas) {
            $kombinasi = JadwalPelajaran::where('kelas_id', $kelas->id)
                ->where('semester_id', $semester->id)
                ->whereNotNull('mata_pelajaran_id')
                ->get(['mata_pelajaran_id', 'guru_id'])
                ->unique(fn ($j) => $j->mata_pelajaran_id.'-'.$j->guru_id);

            foreach ($kombinasi as $i => $jadwal) {
                $mapel = MataPelajaran::find($jadwal->mata_pelajaran_id);
                if (! $mapel) {
                    continue;
                }

                $tp = KomponenPenilaian::where('subjek_type', 'mata_pelajaran')->where('subjek_id', $mapel->id)->where('semester_id', $semester->id)->first();

                $asesmen = Asesmen::firstOrCreate(
                    ['guru_id' => $jadwal->guru_id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'judul' => "Sumatif Lingkup Materi 1: {$mapel->nama}"],
                    ['jenis' => JenisAsesmen::SumatifLingkupMateri, 'tanggal' => now()->subDays(3 + ($i % 5))->toDateString()]
                );

                if ($tp) {
                    $asesmen->komponenPenilaian()->syncWithoutDetaching([$tp->id]);
                }
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
