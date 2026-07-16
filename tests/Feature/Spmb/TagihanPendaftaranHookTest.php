<?php
// tests/Feature/Spmb/TagihanPendaftaranHookTest.php

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use App\Models\Pendaftaran;
use App\Models\Tagihan;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use App\Services\PendaftaranWizardSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('generates a tagihan pendaftaran automatically when the m2 wizard submit succeeds', function () {
    Storage::fake('public');
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now()->subDay(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 40,
    ]);
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 150000]);

    app(PendaftaranWizardSession::class)->put($lembaga, $jalur, [
        'email_pendaftaran' => 'ahmad@example.test',
        'nik' => '3200000000000001',
        'data_pribadi' => ['nama_lengkap' => 'Ahmad Fauzan', 'jenis_kelamin' => 'L', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '2012-01-01', 'agama' => 'Islam'],
        'alamat' => ['alamat_jalan' => 'Jl. Mawar', 'desa_kelurahan' => 'A', 'kecamatan' => 'B', 'kabupaten_kota' => 'C', 'provinsi' => 'D'],
        'keluarga' => [['jenis' => 'ayah', 'nama' => 'Bapak Ahmad']],
    ]);

    $this->post(route('spmb.submit', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]));

    $pendaftaran = Pendaftaran::where('email_pendaftaran', 'ahmad@example.test')->firstOrFail();
    $tagihan = Tagihan::where('pendaftaran_id', $pendaftaran->id)->where('kategori', 'pendaftaran')->first();
    expect($tagihan)->not->toBeNull();
    expect((float) $tagihan->total_tagihan)->toBe(150000.0);
});

it('still submits successfully even when no jenis tagihan is configured for the jalur', function () {
    Storage::fake('public');
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now()->subDay(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 40,
    ]);

    app(PendaftaranWizardSession::class)->put($lembaga, $jalur, [
        'email_pendaftaran' => 'budi@example.test',
        'nik' => '3200000000000002',
        'data_pribadi' => ['nama_lengkap' => 'Budi Santoso', 'jenis_kelamin' => 'L', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '2012-01-01', 'agama' => 'Islam'],
        'alamat' => ['alamat_jalan' => 'Jl. Melati', 'desa_kelurahan' => 'A', 'kecamatan' => 'B', 'kabupaten_kota' => 'C', 'provinsi' => 'D'],
        'keluarga' => [['jenis' => 'ayah', 'nama' => 'Bapak Budi']],
    ]);

    $response = $this->post(route('spmb.submit', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]));

    $response->assertRedirect();
    $pendaftaran = Pendaftaran::where('email_pendaftaran', 'budi@example.test')->firstOrFail();
    $this->assertDatabaseMissing('tagihan', ['pendaftaran_id' => $pendaftaran->id]);
});
