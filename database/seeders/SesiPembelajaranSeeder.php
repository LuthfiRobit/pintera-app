<?php

// database/seeders/SesiPembelajaranSeeder.php

namespace Database\Seeders;

use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\PolaJam;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SesiPembelajaranSeeder extends Seeder
{
    public function run(): void
    {
        // Memakai "kemarin" (bukan tanggal tetap) supaya sesi selalu terlihat baru saat
        // di-demo. Efek samping: reseed di HARI KALENDER YANG BERBEDA akan menambah baris
        // baru (bukan menimpa baris lama), karena kombinasi key firstOrCreate di bawah
        // menyertakan tanggal — ini bukan bug, murni karakteristik data yang bertambah
        // seiring waktu kalau seeder dijalankan ulang di hari berbeda-beda.
        $kemarin = Carbon::yesterday();
        $hariKemarinName = strtolower($kemarin->locale('id')->dayName);

        foreach (Lembaga::all() as $lembaga) {
            $aktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();
            $polaJam = PolaJam::where('lembaga_id', $lembaga->id)->first();

            if (! $aktif || ! $polaJam) {
                continue;
            }

            if ($lembaga->npsn === '20223333') {
                $this->seedSdSesi($lembaga, $aktif, $polaJam, $kemarin, $hariKemarinName);
            } else {
                $this->seedGenericSesi($lembaga, $aktif, $polaJam, $kemarin, $hariKemarinName);
            }
        }
    }

    private function seedSdSesi(Lembaga $sd, TahunAjaran $aktif, PolaJam $polaJam, Carbon $kemarin, string $hariKemarinName): void
    {
        $kelasA = Kelas::where('lembaga_id', $sd->id)->where('tahun_ajaran_id', $aktif->id)->where('nama', 'Kelas 1-A')->first();
        if (! $kelasA) {
            return;
        }

        $jam1Kemarin = JamPelajaran::where('pola_jam_id', $polaJam->id)->where('hari', $hariKemarinName)->where('urutan', 1)->first();
        $jam4Kemarin = JamPelajaran::where('pola_jam_id', $polaJam->id)->where('hari', $hariKemarinName)->where('urutan', 4)->first();

        if ($jam1Kemarin && $jam4Kemarin) {
            $jadwalMatematikaA = JadwalPelajaran::where('kelas_id', $kelasA->id)->where('jam_pelajaran_id', $jam1Kemarin->id)->first();
            $jadwalIpasA = JadwalPelajaran::where('kelas_id', $kelasA->id)->where('jam_pelajaran_id', $jam4Kemarin->id)->first();

            $guruHendra = User::where('email', 'hendra.gunawan@demo.test')->first()?->guru;
            $guruMaya = User::where('email', 'maya.anggraini@demo.test')->first()?->guru;
            $mapelMatematika = MataPelajaran::where('lembaga_id', $sd->id)->where('nama', 'Matematika')->first();
            $mapelIPAS = MataPelajaran::where('lembaga_id', $sd->id)->where('nama', 'Ilmu Pengetahuan Alam dan Sosial (IPAS)')->first();

            if ($jadwalMatematikaA && $guruHendra && $mapelMatematika) {
                SesiPembelajaran::firstOrCreate(
                    [
                        'jadwal_pelajaran_id' => $jadwalMatematikaA->id,
                        'tanggal' => $kemarin->toDateString(),
                    ],
                    [
                        'kelas_id' => $kelasA->id,
                        'guru_id' => $guruHendra->id,
                        'mata_pelajaran_id' => $mapelMatematika->id,
                        'jam_mulai' => '07:00:00',
                        'jam_selesai' => '08:20:00',
                        'materi' => 'Mengenal Bilangan Cacah dan Operasi Penjumlahan.',
                        'status' => 'terlaksana',
                    ]
                );
            }

            if ($jadwalIpasA && $guruMaya && $mapelIPAS) {
                SesiPembelajaran::firstOrCreate(
                    [
                        'jadwal_pelajaran_id' => $jadwalIpasA->id,
                        'tanggal' => $kemarin->toDateString(),
                    ],
                    [
                        'kelas_id' => $kelasA->id,
                        'guru_id' => $guruMaya->id,
                        'mata_pelajaran_id' => $mapelIPAS->id,
                        'jam_mulai' => '08:50:00',
                        'jam_selesai' => '10:10:00',
                        'materi' => 'Mengenal Lingkungan Sekitar dan Makhluk Hidup.',
                        'status' => 'terlaksana',
                    ]
                );
            }
        }
    }

    private function seedGenericSesi(Lembaga $lembaga, TahunAjaran $aktif, PolaJam $polaJam, Carbon $kemarin, string $hariKemarinName): void
    {
        $kelasList = Kelas::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $aktif->id)->get();

        foreach ($kelasList as $kelas) {
            $jadwal = JadwalPelajaran::where('kelas_id', $kelas->id)->first();
            if ($jadwal && $jadwal->jamPelajaran) {
                SesiPembelajaran::firstOrCreate(
                    [
                        'jadwal_pelajaran_id' => $jadwal->id,
                        'tanggal' => $kemarin->toDateString(),
                    ],
                    [
                        'kelas_id' => $kelas->id,
                        'guru_id' => $jadwal->guru_id,
                        'mata_pelajaran_id' => $jadwal->mata_pelajaran_id,
                        'jam_mulai' => $jadwal->jamPelajaran->jam_mulai,
                        'jam_selesai' => $jadwal->jamPelajaran->jam_selesai,
                        'materi' => 'Kegiatan Belajar Mengajar Rutin dan Interaktif.',
                        'status' => 'terlaksana',
                    ]
                );
            }
        }
    }
}
