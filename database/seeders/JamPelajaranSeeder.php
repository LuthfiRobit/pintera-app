<?php

namespace Database\Seeders;

use App\Models\JamPelajaran;
use App\Models\Lembaga;
use App\Models\PolaJam;
use Illuminate\Database\Seeder;

class JamPelajaranSeeder extends Seeder
{
    public function run(): void
    {
        $daftarHari = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'];

        foreach (Lembaga::all() as $lembaga) {
            $polaJam = PolaJam::where('lembaga_id', $lembaga->id)->first();

            if (! $polaJam) {
                continue;
            }

            $slots = match ($lembaga->bentuk_pendidikan) {
                'KB', 'TK' => [
                    ['urutan' => 1, 'label' => 'Jam ke-1', 'mulai' => '08:00:00', 'selesai' => '08:30:00', 'is_pelajaran' => true],
                    ['urutan' => 2, 'label' => 'Jam ke-2', 'mulai' => '08:30:00', 'selesai' => '09:00:00', 'is_pelajaran' => true],
                    ['urutan' => 3, 'label' => 'Istirahat', 'mulai' => '09:00:00', 'selesai' => '09:30:00', 'is_pelajaran' => false],
                    ['urutan' => 4, 'label' => 'Jam ke-3', 'mulai' => '09:30:00', 'selesai' => '10:00:00', 'is_pelajaran' => true],
                ],
                'SD' => [
                    ['urutan' => 1, 'label' => 'Jam ke-1', 'mulai' => '07:30:00', 'selesai' => '08:05:00', 'is_pelajaran' => true],
                    ['urutan' => 2, 'label' => 'Jam ke-2', 'mulai' => '08:05:00', 'selesai' => '08:40:00', 'is_pelajaran' => true],
                    ['urutan' => 3, 'label' => 'Jam ke-3', 'mulai' => '08:40:00', 'selesai' => '09:15:00', 'is_pelajaran' => true],
                    ['urutan' => 4, 'label' => 'Istirahat', 'mulai' => '09:15:00', 'selesai' => '09:45:00', 'is_pelajaran' => false],
                    ['urutan' => 5, 'label' => 'Jam ke-4', 'mulai' => '09:45:00', 'selesai' => '10:20:00', 'is_pelajaran' => true],
                    ['urutan' => 6, 'label' => 'Jam ke-5', 'mulai' => '10:20:00', 'selesai' => '10:55:00', 'is_pelajaran' => true],
                    ['urutan' => 7, 'label' => 'Jam ke-6', 'mulai' => '10:55:00', 'selesai' => '11:30:00', 'is_pelajaran' => true],
                ],
                default => [
                    ['urutan' => 1, 'label' => 'Jam ke-1', 'mulai' => '07:00:00', 'selesai' => '07:40:00', 'is_pelajaran' => true],
                    ['urutan' => 2, 'label' => 'Jam ke-2', 'mulai' => '07:40:00', 'selesai' => '08:20:00', 'is_pelajaran' => true],
                    ['urutan' => 3, 'label' => 'Istirahat', 'mulai' => '08:20:00', 'selesai' => '08:50:00', 'is_pelajaran' => false],
                    ['urutan' => 4, 'label' => 'Jam ke-3', 'mulai' => '08:50:00', 'selesai' => '09:30:00', 'is_pelajaran' => true],
                    ['urutan' => 5, 'label' => 'Jam ke-4', 'mulai' => '09:30:00', 'selesai' => '10:10:00', 'is_pelajaran' => true],
                    ['urutan' => 6, 'label' => 'Jam ke-5', 'mulai' => '10:10:00', 'selesai' => '10:50:00', 'is_pelajaran' => true],
                    ['urutan' => 7, 'label' => 'Jam ke-6', 'mulai' => '10:50:00', 'selesai' => '11:30:00', 'is_pelajaran' => true],
                    ['urutan' => 8, 'label' => 'Jam ke-7', 'mulai' => '11:30:00', 'selesai' => '12:10:00', 'is_pelajaran' => true],
                    ['urutan' => 9, 'label' => 'Jam ke-8', 'mulai' => '12:10:00', 'selesai' => '12:50:00', 'is_pelajaran' => true],
                ],
            };

            foreach ($daftarHari as $hari) {
                foreach ($slots as $slot) {
                    JamPelajaran::firstOrCreate(
                        ['pola_jam_id' => $polaJam->id, 'hari' => $hari, 'urutan' => $slot['urutan']],
                        [
                            'label' => $slot['label'],
                            'jam_mulai' => $slot['mulai'],
                            'jam_selesai' => $slot['selesai'],
                            'is_pelajaran' => $slot['is_pelajaran'],
                        ]
                    );
                }
            }
        }
    }
}
