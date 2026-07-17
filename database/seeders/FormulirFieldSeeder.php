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
        $smp = Lembaga::where('npsn', '20223344')->firstOrFail();
        $sma = Lembaga::where('npsn', '20223355')->firstOrFail();

        foreach (TahunAjaran::where('lembaga_id', $smp->id)->get() as $tahunAjaran) {
            $this->seedFormulir($smp, $tahunAjaran, $this->formulirSmp());
        }

        $smaBaru = TahunAjaran::where('lembaga_id', $sma->id)->where('status_aktif', true)->firstOrFail();
        $this->seedFormulir($sma, $smaBaru, $this->formulirSma());
    }

    /**
     * @return array<string, array<int, array{label: string, field_type: string, is_required: bool, options: ?array}>>
     */
    private function formulirSmp(): array
    {
        return [
            'Reguler' => [
                ['label' => 'Sekolah Asal', 'field_type' => 'text', 'is_required' => true, 'options' => null],
                ['label' => 'Nilai Rata-rata Rapor', 'field_type' => 'number', 'is_required' => true, 'options' => null],
            ],
            'Prestasi' => [
                ['label' => 'Jenis Prestasi', 'field_type' => 'select', 'is_required' => true, 'options' => ['Akademik', 'Non-Akademik', 'Keagamaan']],
                ['label' => 'Uraian Prestasi', 'field_type' => 'textarea', 'is_required' => true, 'options' => null],
                ['label' => 'Sertifikat Pendukung', 'field_type' => 'file', 'is_required' => true, 'options' => null],
            ],
            'Afirmasi' => [],
        ];
    }

    /**
     * @return array<string, array<int, array{label: string, field_type: string, is_required: bool, options: ?array}>>
     */
    private function formulirSma(): array
    {
        return [
            'Reguler' => [
                ['label' => 'Sekolah Asal', 'field_type' => 'text', 'is_required' => true, 'options' => null],
                ['label' => 'Pilihan Jurusan', 'field_type' => 'select', 'is_required' => true, 'options' => ['IPA', 'IPS']],
            ],
            'Prestasi' => [
                ['label' => 'Tingkat Prestasi', 'field_type' => 'select', 'is_required' => true, 'options' => ['Kabupaten/Kota', 'Provinsi', 'Nasional', 'Internasional']],
                ['label' => 'Uraian Prestasi', 'field_type' => 'textarea', 'is_required' => true, 'options' => null],
            ],
            'Afirmasi' => [],
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
