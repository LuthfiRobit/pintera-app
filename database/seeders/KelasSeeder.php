<?php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\PolaJam;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class KelasSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::all() as $lembaga) {
            $polaJam = PolaJam::where('lembaga_id', $lembaga->id)->first();
            $gurus = Guru::where('lembaga_id', $lembaga->id)->get();
            $tahunAjaranList = TahunAjaran::where('lembaga_id', $lembaga->id)->get();

            $kelasConfigs = match ($lembaga->bentuk_pendidikan) {
                'KB' => [
                    ['nama' => 'KB A-1', 'tingkat' => 'A'],
                    ['nama' => 'KB B-1', 'tingkat' => 'B'],
                ],
                'TK' => [
                    ['nama' => 'TK A-1', 'tingkat' => 'A'],
                    ['nama' => 'TK B-1', 'tingkat' => 'B'],
                ],
                'SD' => [
                    ['nama' => 'Kelas 1-A', 'tingkat' => '1'],
                    ['nama' => 'Kelas 2-A', 'tingkat' => '2'],
                    ['nama' => 'Kelas 3-A', 'tingkat' => '3'],
                    ['nama' => 'Kelas 4-A', 'tingkat' => '4'],
                    ['nama' => 'Kelas 5-A', 'tingkat' => '5'],
                    ['nama' => 'Kelas 6-A', 'tingkat' => '6'],
                ],
                default => [
                    ['nama' => 'VII-A', 'tingkat' => '7'],
                    ['nama' => 'VII-B', 'tingkat' => '7'],
                    ['nama' => 'VIII-A', 'tingkat' => '8'],
                    ['nama' => 'VIII-B', 'tingkat' => '8'],
                    ['nama' => 'IX-A', 'tingkat' => '9'],
                ],
            };

            foreach ($tahunAjaranList as $ta) {
                foreach ($kelasConfigs as $idx => $config) {
                    $waliKelasId = $gurus->isNotEmpty() ? $gurus[$idx % $gurus->count()]->id : null;

                    $kelas = Kelas::firstOrCreate(
                        ['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'nama' => $config['nama']],
                        ['tingkat' => $config['tingkat'], 'pola_jam_id' => $polaJam?->id, 'wali_kelas_guru_id' => $waliKelasId]
                    );

                    if ($kelas->pola_jam_id !== $polaJam?->id || $kelas->wali_kelas_guru_id !== $waliKelasId) {
                        $kelas->update(['pola_jam_id' => $polaJam?->id, 'wali_kelas_guru_id' => $waliKelasId]);
                    }
                }
            }
        }
    }
}
