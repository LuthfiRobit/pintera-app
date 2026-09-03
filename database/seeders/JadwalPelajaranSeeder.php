<?php

// database/seeders/JadwalPelajaranSeeder.php

namespace Database\Seeders;

use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
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

            if ($lembaga->npsn === '20223333') {
                $this->seedSdJadwal($lembaga, $aktif, $semester, $polaJam, $daftarHari);
            } else {
                $this->seedGenericJadwal($lembaga, $aktif, $semester, $polaJam, $daftarHari);
            }
        }
    }

    /**
     * Jadwal SD realistis untuk SEMUA kelas (bukan cuma 2 kelas seperti sebelumnya):
     * setiap kelas diajar oleh wali kelasnya sendiri (`guru_kelas`) untuk mayoritas
     * mata pelajaran -- pola guru kelas SD yang mengajar hampir semua mapel ke
     * rombelnya sendiri -- KECUALI 2 mapel spesialis (PJOK, Bahasa Inggris) yang
     * diajar guru_mapel lintas kelas. Ini memastikan SETIAP akun guru demo (bukan
     * cuma 2 dari 21) benar-benar punya JadwalPelajaran, supaya fitur Tambah TP /
     * Buat Asesmen / RPP bisa dipraktikkan dari akun manapun.
     */
    private function seedSdJadwal(Lembaga $sd, TahunAjaran $aktif, Semester $semester, PolaJam $polaJam, array $daftarHari): void
    {
        $kelasList = Kelas::where('lembaga_id', $sd->id)->where('tahun_ajaran_id', $aktif->id)->whereNotNull('wali_kelas_guru_id')->get();
        $mapelList = MataPelajaran::where('lembaga_id', $sd->id)->orderBy('id')->get();

        if ($kelasList->isEmpty() || $mapelList->isEmpty()) {
            return;
        }

        $mapelSpesialis = [
            'Pendidikan Jasmani dan Olahraga' => User::where('email', 'maya.anggraini@demo.test')->first()?->guru,
            'Bahasa Inggris' => User::where('email', 'taufik.hidayat@demo.test')->first()?->guru,
        ];

        $hariKerja = array_values(array_intersect($daftarHari, ['senin', 'selasa', 'rabu', 'kamis', 'jumat']));

        foreach ($kelasList as $kelas) {
            $waliKelasGuru = $kelas->waliKelas;
            if (! $waliKelasGuru) {
                continue;
            }

            $slotKe = 0;
            foreach ($hariKerja as $hari) {
                $jamList = JamPelajaran::where('pola_jam_id', $polaJam->id)
                    ->where('hari', $hari)
                    ->where('is_pelajaran', true)
                    ->orderBy('urutan')
                    ->get();

                foreach ($jamList as $jam) {
                    $mapel = $mapelList[$slotKe % $mapelList->count()];
                    $guruPengajar = $mapelSpesialis[$mapel->nama] ?? $waliKelasGuru;
                    $slotKe++;

                    if (! $guruPengajar) {
                        continue;
                    }

                    JadwalPelajaran::firstOrCreate(
                        ['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'semester_id' => $semester->id],
                        ['mata_pelajaran_id' => $mapel->id, 'guru_id' => $guruPengajar->id]
                    );
                }
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
