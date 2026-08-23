<?php
// database/seeders/JadwalPelajaranSeeder.php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\Semester;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class JadwalPelajaranSeeder extends Seeder
{
    public function run(): void
    {
        $daftarHari = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'];

        foreach (Lembaga::all() as $lembaga) {
            $aktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();
            $polaJam = PolaJam::where('lembaga_id', $lembaga->id)->first();

            if (! $aktif || ! $polaJam) {
                continue;
            }

            $semester = Semester::where('tahun_ajaran_id', $aktif->id)->where('status_aktif', true)->first()
                ?? Semester::where('tahun_ajaran_id', $aktif->id)->first();

            if (! $semester) {
                continue;
            }

            if ($lembaga->npsn === '20223344') {
                $this->seedSmpJadwal($lembaga, $aktif, $semester, $polaJam, $daftarHari);
            } else {
                $this->seedGenericJadwal($lembaga, $aktif, $semester, $polaJam, $daftarHari);
            }
        }
    }

    private function seedSmpJadwal(Lembaga $smp, TahunAjaran $aktif, Semester $semester, PolaJam $polaJam, array $daftarHari): void
    {
        $kelasA = Kelas::where('lembaga_id', $smp->id)->where('tahun_ajaran_id', $aktif->id)->where('nama', 'VII-A')->first();
        $kelasB = Kelas::where('lembaga_id', $smp->id)->where('tahun_ajaran_id', $aktif->id)->where('nama', 'VII-B')->first();

        $mapelMatematika = MataPelajaran::where('lembaga_id', $smp->id)->where('nama', 'Matematika')->first();
        $mapelIPA = MataPelajaran::where('lembaga_id', $smp->id)->where('nama', 'Ilmu Pengetahuan Alam (IPA)')->first();

        $guruBudi = Guru::where('email', 'budi.santoso@demo.test')->first();
        $guruSiti = Guru::where('email', 'siti.rahmawati@demo.test')->first();

        if (! $kelasA || ! $kelasB || ! $mapelMatematika || ! $mapelIPA || ! $guruBudi || ! $guruSiti) {
            return;
        }

        foreach ($daftarHari as $hari) {
            $jam1 = JamPelajaran::where('pola_jam_id', $polaJam->id)->where('hari', $hari)->where('urutan', 1)->first();
            $jam2 = JamPelajaran::where('pola_jam_id', $polaJam->id)->where('hari', $hari)->where('urutan', 2)->first();
            $jam4 = JamPelajaran::where('pola_jam_id', $polaJam->id)->where('hari', $hari)->where('urutan', 4)->first();
            $jam5 = JamPelajaran::where('pola_jam_id', $polaJam->id)->where('hari', $hari)->where('urutan', 5)->first();

            if ($jam1 && $jam2 && $jam4 && $jam5) {
                // VII-A: Jam 1 & 2 Matematika (Budi)
                JadwalPelajaran::firstOrCreate(
                    ['kelas_id' => $kelasA->id, 'jam_pelajaran_id' => $jam1->id, 'semester_id' => $semester->id],
                    ['mata_pelajaran_id' => $mapelMatematika->id, 'guru_id' => $guruBudi->id]
                );
                JadwalPelajaran::firstOrCreate(
                    ['kelas_id' => $kelasA->id, 'jam_pelajaran_id' => $jam2->id, 'semester_id' => $semester->id],
                    ['mata_pelajaran_id' => $mapelMatematika->id, 'guru_id' => $guruBudi->id]
                );

                // VII-A: Jam 4 & 5 IPA (Siti)
                JadwalPelajaran::firstOrCreate(
                    ['kelas_id' => $kelasA->id, 'jam_pelajaran_id' => $jam4->id, 'semester_id' => $semester->id],
                    ['mata_pelajaran_id' => $mapelIPA->id, 'guru_id' => $guruSiti->id]
                );
                JadwalPelajaran::firstOrCreate(
                    ['kelas_id' => $kelasA->id, 'jam_pelajaran_id' => $jam5->id, 'semester_id' => $semester->id],
                    ['mata_pelajaran_id' => $mapelIPA->id, 'guru_id' => $guruSiti->id]
                );

                // VII-B: Jam 1 & 2 IPA (Siti)
                JadwalPelajaran::firstOrCreate(
                    ['kelas_id' => $kelasB->id, 'jam_pelajaran_id' => $jam1->id, 'semester_id' => $semester->id],
                    ['mata_pelajaran_id' => $mapelIPA->id, 'guru_id' => $guruSiti->id]
                );
                JadwalPelajaran::firstOrCreate(
                    ['kelas_id' => $kelasB->id, 'jam_pelajaran_id' => $jam2->id, 'semester_id' => $semester->id],
                    ['mata_pelajaran_id' => $mapelIPA->id, 'guru_id' => $guruSiti->id]
                );

                // VII-B: Jam 4 & 5 Matematika (Budi)
                JadwalPelajaran::firstOrCreate(
                    ['kelas_id' => $kelasB->id, 'jam_pelajaran_id' => $jam4->id, 'semester_id' => $semester->id],
                    ['mata_pelajaran_id' => $mapelMatematika->id, 'guru_id' => $guruBudi->id]
                );
                JadwalPelajaran::firstOrCreate(
                    ['kelas_id' => $kelasB->id, 'jam_pelajaran_id' => $jam5->id, 'semester_id' => $semester->id],
                    ['mata_pelajaran_id' => $mapelMatematika->id, 'guru_id' => $guruBudi->id]
                );
            }
        }
    }

    private function seedGenericJadwal(Lembaga $lembaga, TahunAjaran $aktif, Semester $semester, PolaJam $polaJam, array $daftarHari): void
    {
        $kelasList = Kelas::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $aktif->id)->get();
        $mapel = MataPelajaran::where('lembaga_id', $lembaga->id)->first();
        $guru = Guru::where('lembaga_id', $lembaga->id)->first();

        if (! $mapel || ! $guru || $kelasList->isEmpty()) {
            return;
        }

        foreach ($kelasList as $kelas) {
            foreach ($daftarHari as $hari) {
                $jamSlots = JamPelajaran::where('pola_jam_id', $polaJam->id)
                    ->where('hari', $hari)
                    ->where('is_pelajaran', true)
                    ->take(2)
                    ->get();

                foreach ($jamSlots as $jam) {
                    JadwalPelajaran::firstOrCreate(
                        ['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'semester_id' => $semester->id],
                        ['mata_pelajaran_id' => $mapel->id, 'guru_id' => $guru->id]
                    );
                }
            }
        }
    }
}
