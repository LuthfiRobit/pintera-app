<?php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\MataPelajaran;
use Illuminate\Database\Seeder;

class MataPelajaranSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::all() as $lembaga) {
            $subjects = match ($lembaga->bentuk_pendidikan) {
                'KB', 'TK' => [
                    ['kode' => 'NAM-01', 'nama' => 'Nilai Agama dan Moral (NAM)', 'kelompok' => 'umum'],
                    ['kode' => 'FM-01', 'nama' => 'Fisik Motorik (FM)', 'kelompok' => 'umum'],
                    ['kode' => 'KOG-01', 'nama' => 'Kognitif (KOG)', 'kelompok' => 'umum'],
                    ['kode' => 'BHS-01', 'nama' => 'Bahasa (BHS)', 'kelompok' => 'umum'],
                    ['kode' => 'SOSEM-01', 'nama' => 'Sosial Emosional (SOSEM)', 'kelompok' => 'umum'],
                    ['kode' => 'SENI-01', 'nama' => 'Seni (SENI)', 'kelompok' => 'umum'],
                ],
                'SD' => [
                    ['kode' => 'PAI-01', 'nama' => 'Pendidikan Agama Islam', 'kelompok' => 'umum'],
                    ['kode' => 'PNC-01', 'nama' => 'Pendidikan Pancasila', 'kelompok' => 'umum'],
                    ['kode' => 'IND-01', 'nama' => 'Bahasa Indonesia', 'kelompok' => 'umum'],
                    ['kode' => 'MTK-01', 'nama' => 'Matematika', 'kelompok' => 'umum'],
                    ['kode' => 'IPAS-01', 'nama' => 'Ilmu Pengetahuan Alam dan Sosial (IPAS)', 'kelompok' => 'umum'],
                    ['kode' => 'PJOK-01', 'nama' => 'Pendidikan Jasmani dan Olahraga', 'kelompok' => 'umum'],
                    ['kode' => 'SENI-01', 'nama' => 'Seni Budaya', 'kelompok' => 'umum'],
                    ['kode' => 'ING-01', 'nama' => 'Bahasa Inggris', 'kelompok' => 'mulok'],
                    ['kode' => 'BTA-01', 'nama' => "Baca Tulis Al-Qur'an (BTA)", 'kelompok' => 'mulok'],
                ],
                default => [
                    ['kode' => 'MTK-01', 'nama' => 'Matematika', 'kelompok' => 'umum'],
                    ['kode' => 'IPA-01', 'nama' => 'Ilmu Pengetahuan Alam (IPA)', 'kelompok' => 'umum'],
                    ['kode' => 'IND-01', 'nama' => 'Bahasa Indonesia', 'kelompok' => 'umum'],
                    ['kode' => 'PAI-01', 'nama' => 'Pendidikan Agama Islam', 'kelompok' => 'umum'],
                    ['kode' => 'PNC-01', 'nama' => 'Pendidikan Pancasila', 'kelompok' => 'umum'],
                    ['kode' => 'IPS-01', 'nama' => 'Ilmu Pengetahuan Sosial (IPS)', 'kelompok' => 'umum'],
                    ['kode' => 'ING-01', 'nama' => 'Bahasa Inggris', 'kelompok' => 'umum'],
                    ['kode' => 'INF-01', 'nama' => 'Informatika', 'kelompok' => 'umum'],
                    ['kode' => 'PJOK-01', 'nama' => 'PJOK', 'kelompok' => 'umum'],
                    ['kode' => 'SENI-01', 'nama' => 'Seni Budaya', 'kelompok' => 'umum'],
                    ['kode' => 'BTA-01', 'nama' => "Baca Tulis Al-Qur'an (BTA)", 'kelompok' => 'mulok'],
                ],
            };

            $noUrut = 1;
            foreach ($subjects as $item) {
                MataPelajaran::firstOrCreate(
                    ['lembaga_id' => $lembaga->id, 'kode' => $item['kode']],
                    [
                        'nama' => $item['nama'],
                        'no_urut' => $noUrut++,
                        'tipe' => 'mapel',
                        'kelompok' => $item['kelompok'],
                        'status' => 'aktif',
                    ]
                );
            }
        }
    }
}
