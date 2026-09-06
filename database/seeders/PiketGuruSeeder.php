<?php

// database/seeders/PiketGuruSeeder.php

namespace Database\Seeders;

use App\Domains\Akademik\Actions\Piket\GenerateJadwalPiketHarianAction;
use App\Domains\Akademik\Models\JadwalPiketMingguan;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Database\Seeder;

class PiketGuruSeeder extends Seeder
{
    public function run(): void
    {
        $generateAction = app(GenerateJadwalPiketHarianAction::class);

        foreach (Lembaga::all() as $lembaga) {
            $semesterAktif = Semester::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();

            if (! $semesterAktif) {
                continue;
            }

            if ($lembaga->npsn === '20223333') {
                $this->seedSdPiket($lembaga, $semesterAktif, $generateAction);
            } else {
                $this->seedGenericPiket($lembaga, $semesterAktif, $generateAction);
            }
        }
    }

    /**
     * 3 guru mapel spesialis (bukan wali kelas, jadi natural "float" lintas kelas) bergilir
     * piket Senin-Jumat: hendra.gunawan (Senin, Rabu), maya.anggraini (Selasa, Kamis),
     * taufik.hidayat (Jumat). dibuat_oleh_user_id memakai akun operator_akademik demo
     * (kurikulum.sd@demo.test) -- akun yang memang punya permission piket.kelola.
     */
    private function seedSdPiket(Lembaga $sd, Semester $semesterAktif, GenerateJadwalPiketHarianAction $generateAction): void
    {
        $dibuatOleh = User::where('email', 'kurikulum.sd@demo.test')->first();
        $hendra = User::where('email', 'hendra.gunawan@demo.test')->first()?->guru;
        $maya = User::where('email', 'maya.anggraini@demo.test')->first()?->guru;
        $taufik = User::where('email', 'taufik.hidayat@demo.test')->first()?->guru;

        if (! $dibuatOleh || ! $hendra || ! $maya || ! $taufik) {
            $this->command?->warn('PiketGuruSeeder: akun demo guru/operator akademik SDIT belum lengkap, dilewati.');

            return;
        }

        // hari: 1=Senin, 2=Selasa, 3=Rabu, 4=Kamis, 5=Jumat (dayOfWeek Carbon).
        $rotasi = [
            ['guru' => $hendra, 'hari' => 1],
            ['guru' => $maya, 'hari' => 2],
            ['guru' => $hendra, 'hari' => 3],
            ['guru' => $maya, 'hari' => 4],
            ['guru' => $taufik, 'hari' => 5],
        ];

        $this->buatJadwalDanGenerate($sd, $semesterAktif, $dibuatOleh, $rotasi, $generateAction);
    }

    /**
     * Fallback generic: butuh minimal 2 guru berbeda supaya rotasi piket bermakna (kalau
     * cuma 1 guru, tidak ada "guru lain" yang bisa menggantikan -- dilewati saja).
     */
    private function seedGenericPiket(Lembaga $lembaga, Semester $semesterAktif, GenerateJadwalPiketHarianAction $generateAction): void
    {
        $guruList = Guru::where('lembaga_id', $lembaga->id)->orderByNama()->take(2)->get();

        if ($guruList->count() < 2) {
            return;
        }

        $dibuatOleh = User::where('lembaga_id', $lembaga->id)->first();

        if (! $dibuatOleh) {
            return;
        }

        [$guruA, $guruB] = $guruList;

        // Rotasi sederhana 2 guru: A Senin/Rabu/Jumat, B Selasa/Kamis.
        $rotasi = [
            ['guru' => $guruA, 'hari' => 1],
            ['guru' => $guruB, 'hari' => 2],
            ['guru' => $guruA, 'hari' => 3],
            ['guru' => $guruB, 'hari' => 4],
            ['guru' => $guruA, 'hari' => 5],
        ];

        $this->buatJadwalDanGenerate($lembaga, $semesterAktif, $dibuatOleh, $rotasi, $generateAction);
    }

    /**
     * @param  array<int, array{guru: Guru, hari: int}>  $rotasi
     */
    private function buatJadwalDanGenerate(Lembaga $lembaga, Semester $semesterAktif, User $dibuatOleh, array $rotasi, GenerateJadwalPiketHarianAction $generateAction): void
    {
        foreach ($rotasi as $baris) {
            $jadwal = JadwalPiketMingguan::firstOrCreate(
                [
                    'lembaga_id' => $lembaga->id,
                    'guru_id' => $baris['guru']->id,
                    'hari' => $baris['hari'],
                    'semester_id' => $semesterAktif->id,
                ],
                [
                    'dibuat_oleh_user_id' => $dibuatOleh->id,
                ]
            );

            // Idempotent (firstOrCreate di dalam Action) -- aman dipanggil ulang tiap
            // migrate:fresh --seed, tidak akan duplikat PiketHarian.
            $generateAction->execute($jadwal);
        }
    }
}
