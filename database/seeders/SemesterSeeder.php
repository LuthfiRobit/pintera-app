<?php
// database/seeders/SemesterSeeder.php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class SemesterSeeder extends Seeder
{
    public function run(): void
    {
        $lembagaList = Lembaga::all();

        foreach ($lembagaList as $lembaga) {
            foreach (TahunAjaran::where('lembaga_id', $lembaga->id)->get() as $tahunAjaran) {
                $tahunGanjil = (int) explode('/', $tahunAjaran->nama)[0];
                $ganjilAktif = $tahunAjaran->status_aktif;

                Semester::firstOrCreate(
                    ['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil'],
                    [
                        'lembaga_id' => $lembaga->id,
                        'urutan' => 1,
                        'kode_dapodik' => $tahunGanjil.'1',
                        'tanggal_mulai' => $tahunGanjil.'-07-01',
                        'tanggal_selesai' => $tahunGanjil.'-12-20',
                        'status_aktif' => $ganjilAktif,
                    ]
                );

                Semester::firstOrCreate(
                    ['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Genap'],
                    [
                        'lembaga_id' => $lembaga->id,
                        'urutan' => 2,
                        'kode_dapodik' => $tahunGanjil.'2',
                        'tanggal_mulai' => ($tahunGanjil + 1).'-01-05',
                        'tanggal_selesai' => ($tahunGanjil + 1).'-06-30',
                        'status_aktif' => false,
                    ]
                );
            }
        }
    }
}
