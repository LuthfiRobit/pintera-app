<?php
// tests/Feature/Portal/AutoLinkTest.php

use App\Models\AkunPendaftar;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use App\Services\PendaftaranWizardSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('auto-links a new pendaftaran submitted through the m2 wizard to an already-verified akun with the same email', function () {
    Storage::fake('public');
    $akun = AkunPendaftar::factory()->create(['email' => 'ahmad@example.test']);

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

    $wizardSession = app(PendaftaranWizardSession::class);
    $wizardSession->put($lembaga, $jalur, [
        'email_pendaftaran' => 'ahmad@example.test',
        'nik' => '3200000000000001',
        'data_pribadi' => ['nama_lengkap' => 'Ahmad Fauzan', 'jenis_kelamin' => 'L', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '2012-01-01', 'agama' => 'Islam'],
        'alamat' => ['alamat_jalan' => 'Jl. Mawar', 'desa_kelurahan' => 'A', 'kecamatan' => 'B', 'kabupaten_kota' => 'C', 'provinsi' => 'D'],
        'keluarga' => [['jenis' => 'ayah', 'nama' => 'Bapak Ahmad']],
    ]);

    $this->post(route('spmb.submit', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]));

    $pendaftaran = \App\Models\Pendaftaran::where('email_pendaftaran', 'ahmad@example.test')->first();
    expect($pendaftaran)->not->toBeNull();
    expect($pendaftaran->akun_pendaftar_id)->toBe($akun->id);
});
