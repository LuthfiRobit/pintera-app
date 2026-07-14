<?php

use App\Models\DokumenPendaftaran;
use App\Models\DokumenSyaratPpdb;
use App\Models\FormulirField;
use App\Models\JawabanFormulirPendaftaran;
use App\Models\Pendaftaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('stores a jawaban for a dynamic formulir field', function () {
    $pendaftaran = Pendaftaran::factory()->create();
    $field = FormulirField::create([
        'jalur_ppdb_id' => $pendaftaran->jalur_ppdb_id,
        'label' => 'Nilai Rata-rata Rapor',
        'field_type' => 'number',
    ]);

    $jawaban = JawabanFormulirPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id,
        'formulir_field_id' => $field->id,
        'nilai' => '88.5',
    ]);

    expect($pendaftaran->jawabanFormulir()->first()->nilai)->toBe('88.5');
    expect($jawaban->formulirField->label)->toBe('Nilai Rata-rata Rapor');
});

it('stores a dokumen pendaftaran with a default belum_diverifikasi status', function () {
    $pendaftaran = Pendaftaran::factory()->create();
    $syarat = DokumenSyaratPpdb::create([
        'jalur_ppdb_id' => $pendaftaran->jalur_ppdb_id,
        'nama_dokumen' => 'Akta Kelahiran',
    ]);

    $dokumen = DokumenPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id,
        'dokumen_syarat_ppdb_id' => $syarat->id,
        'file_path' => 'pendaftaran/1/akta.pdf',
        'nama_file_asli' => 'akta-ahmad.pdf',
        'mime_type' => 'application/pdf',
        'ukuran_bytes' => 102400,
    ]);

    expect($dokumen->status_verifikasi)->toBe('belum_diverifikasi');
    expect($pendaftaran->dokumen()->first()->nama_file_asli)->toBe('akta-ahmad.pdf');
    expect($dokumen->dokumenSyaratPpdb->nama_dokumen)->toBe('Akta Kelahiran');
});
