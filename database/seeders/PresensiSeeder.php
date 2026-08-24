<?php
// database/seeders/PresensiSeeder.php

namespace Database\Seeders;

use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
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
                    // Variasi status: 1 dari setiap 10 siswa sakit, 1 dari setiap 15 izin, sisanya hadir.
                    // Modulo deterministik (idempotent), bukan random -- supaya migrate:fresh --seed
                    // berulang menghasilkan data identik.
                    if ($lembaga->npsn === '20223333' && $index % 10 === 0) {
                        Presensi::firstOrCreate(
                            ['sesi_pembelajaran_id' => $sesi->id, 'siswa_id' => $siswa->id],
                            ['status' => 'sakit', 'keterangan' => 'Demam, surat dokter menyusul']
                        );
                    } elseif ($lembaga->npsn === '20223333' && $index % 15 === 1) {
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
