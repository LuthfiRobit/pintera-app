<?php
// database/seeders/FormulirFieldSeeder.php

namespace Database\Seeders;

use App\Models\FormulirField;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class FormulirFieldSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Lembaga::all() as $lembaga) {
            $formulirConfig = match ($lembaga->bentuk_pendidikan) {
                'KB', 'TK' => $this->formulirPaud(),
                'SD' => $this->formulirSd(),
                default => $this->formulirSmp(),
            };

            foreach (TahunAjaran::where('lembaga_id', $lembaga->id)->get() as $tahunAjaran) {
                $this->seedFormulir($lembaga, $tahunAjaran, $formulirConfig);
            }
        }
    }

    private function formulirPaud(): array
    {
        return [
            'Reguler' => [
                ['label' => 'Nama Panggilan Anak', 'field_type' => 'text', 'is_required' => true, 'options' => null],
                ['label' => 'Usia Saat Mendaftar (Tahun/Bulan)', 'field_type' => 'text', 'is_required' => true, 'options' => null],
                ['label' => 'Riwayat Kesehatan / Alergi', 'field_type' => 'textarea', 'is_required' => false, 'options' => null],
            ],
            'Prestasi' => [
                ['label' => 'Prestasi / Lomba', 'field_type' => 'text', 'is_required' => true, 'options' => null],
            ],
            'Afirmasi' => [],
        ];
    }

    private function formulirSd(): array
    {
        return [
            'Reguler' => [
                ['label' => 'Asal TK / PAUD', 'field_type' => 'text', 'is_required' => true, 'options' => null],
                ['label' => 'Usia Saat Mendaftar (Tahun)', 'field_type' => 'number', 'is_required' => true, 'options' => null],
                ['label' => 'Provinsi Tempat Tinggal', 'field_type' => 'select', 'is_required' => true, 'options' => $this->provinsiOptions()],
                ['label' => 'Catatan Khusus Perkembangan', 'field_type' => 'textarea', 'is_required' => false, 'options' => null],
            ],
            'Prestasi' => [
                ['label' => 'Tingkat Prestasi', 'field_type' => 'select', 'is_required' => true, 'options' => ['Kecamatan', 'Kabupaten/Kota', 'Provinsi']],
                ['label' => 'Uraian Prestasi', 'field_type' => 'textarea', 'is_required' => true, 'options' => null],
            ],
            'Afirmasi' => [],
        ];
    }

    private function formulirSmp(): array
    {
        return [
            'Reguler' => [
                ['label' => 'Sekolah Asal', 'field_type' => 'text', 'is_required' => true, 'options' => null],
                ['label' => 'Nilai Rata-rata Rapor', 'field_type' => 'number', 'is_required' => true, 'options' => null],
                ['label' => 'Tanggal Kelulusan Sekolah Asal', 'field_type' => 'date', 'is_required' => true, 'options' => null],
                ['label' => 'Provinsi Asal Sekolah', 'field_type' => 'select', 'is_required' => true, 'options' => $this->provinsiOptions()],
                ['label' => 'Alamat Lengkap Sekolah Asal', 'field_type' => 'textarea', 'is_required' => false, 'options' => null],
                ['label' => 'Scan Rapor Terakhir', 'field_type' => 'file', 'is_required' => true, 'options' => null],
            ],
            'Prestasi' => [
                ['label' => 'Jenis Prestasi', 'field_type' => 'select', 'is_required' => true, 'options' => ['Akademik', 'Non-Akademik', 'Keagamaan']],
                ['label' => 'Tanggal Perolehan Prestasi', 'field_type' => 'date', 'is_required' => true, 'options' => null],
                ['label' => 'Uraian Prestasi', 'field_type' => 'textarea', 'is_required' => true, 'options' => null],
                ['label' => 'Sertifikat Pendukung', 'field_type' => 'file', 'is_required' => true, 'options' => null],
            ],
            'Afirmasi' => [],
        ];
    }

    private function provinsiOptions(): array
    {
        return [
            'Aceh', 'Sumatera Utara', 'Sumatera Barat', 'Riau', 'Kepulauan Riau', 'Jambi',
            'Sumatera Selatan', 'Bangka Belitung', 'Bengkulu', 'Lampung', 'DKI Jakarta',
            'Jawa Barat', 'Jawa Tengah', 'DI Yogyakarta', 'Jawa Timur', 'Banten', 'Bali',
            'Nusa Tenggara Barat', 'Nusa Tenggara Timur', 'Kalimantan Barat', 'Kalimantan Tengah',
            'Kalimantan Selatan', 'Kalimantan Timur', 'Kalimantan Utara', 'Sulawesi Utara',
            'Sulawesi Tengah', 'Sulawesi Selatan', 'Sulawesi Tenggara', 'Gorontalo', 'Sulawesi Barat',
            'Maluku', 'Maluku Utara', 'Papua', 'Papua Barat', 'Papua Selatan', 'Papua Tengah',
            'Papua Pegunungan', 'Papua Barat Daya',
        ];
    }

    private function seedFormulir(Lembaga $lembaga, TahunAjaran $tahunAjaran, array $formulirPerJalur): void
    {
        foreach ($formulirPerJalur as $namaJalur => $fields) {
            $jalur = JalurPpdb::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $tahunAjaran->id)->where('nama', $namaJalur)->first();

            if (! $jalur) {
                continue;
            }

            foreach ($fields as $urutan => $field) {
                FormulirField::firstOrCreate(
                    ['jalur_ppdb_id' => $jalur->id, 'label' => $field['label']],
                    [
                        'lembaga_id' => $lembaga->id,
                        'field_type' => $field['field_type'],
                        'options' => $field['options'],
                        'is_required' => $field['is_required'],
                        'urutan' => $urutan,
                    ]
                );
            }
        }
    }
}
