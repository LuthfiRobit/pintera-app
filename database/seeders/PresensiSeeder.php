<?php
// database/seeders/PresensiSeeder.php

namespace Database\Seeders;

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Presensi;
use App\Models\SesiPembelajaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class PresensiSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::all() as $lembaga) {
            $aktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();
            if (! $aktif) {
                continue;
            }

            $kelasIds = Kelas::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $aktif->id)->pluck('id');
            $sesiList = SesiPembelajaran::whereIn('kelas_id', $kelasIds)->get();

            foreach ($sesiList as $sesi) {
                $siswaList = Siswa::where('kelas_id', $sesi->kelas_id)->get();

                foreach ($siswaList as $index => $siswa) {
                    if ($lembaga->npsn === '20223344' && $sesi->mataPelajaran && str_contains($sesi->mataPelajaran->nama, 'Matematika') && $siswa->nis === '2627001') {
                        Presensi::firstOrCreate(
                            ['sesi_pembelajaran_id' => $sesi->id, 'siswa_id' => $siswa->id],
                            ['status' => 'sakit', 'keterangan' => 'Demam tinggi']
                        );
                    } elseif ($lembaga->npsn === '20223344' && $sesi->mataPelajaran && str_contains($sesi->mataPelajaran->nama, 'IPA') && $siswa->nis === '2627002') {
                        Presensi::firstOrCreate(
                            ['sesi_pembelajaran_id' => $sesi->id, 'siswa_id' => $siswa->id],
                            ['status' => 'izin', 'keterangan' => 'Acara keluarga']
                        );
                    } else {
                        Presensi::firstOrCreate(
                            ['sesi_pembelajaran_id' => $sesi->id, 'siswa_id' => $siswa->id],
                            ['status' => 'hadir', 'keterangan' => null]
                        );
                    }
                }
            }
        }
    }
}
