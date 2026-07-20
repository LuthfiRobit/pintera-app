<?php
// tests/Feature/Spmb/PortalEntryTest.php

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

it('shows the jalur list and the active tahun ajaran for a lembaga with an open gelombang, with an enabled daftar action', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();

    $response = $this->get("/spmb/{$lembaga->slug}");

    $response->assertOk();
    $response->assertSee($jalur->nama);
    $response->assertSee($tahunAjaran->nama);
    $response->assertDontSee('Belum Dibuka');
});

it('shows jalur informationally with a disabled daftar action when no gelombang is currently open', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler', 'status_aktif' => true]);
    GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang Lalu',
        'tanggal_buka' => now()->subMonths(2), 'tanggal_tutup' => now()->subMonth(), 'kuota' => 40,
    ]);

    $response = $this->get("/spmb/{$lembaga->slug}");

    $response->assertOk();
    $response->assertSee($jalur->nama);
    $response->assertSee('Belum Dibuka');
});

it('picks the gelombang with the earliest tanggal_buka when two overlap', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombangAwal] = buatLembagaDenganGelombangBuka();
    GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang Lebih Awal',
        'tanggal_buka' => now()->subWeek(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 20,
    ]);

    $response = $this->get("/spmb/{$lembaga->slug}");

    $response->assertOk();
    $response->assertSee('Gelombang Lebih Awal');
});

it('404s for an unknown lembaga slug', function () {
    $this->get('/spmb/sekolah-tidak-ada')->assertNotFound();
});

it('only shows jalur connected to the active gelombang when that gelombang is restricted', function () {
    [$lembaga, $tahunAjaran, $jalurTerhubung, $gelombang] = buatLembagaDenganGelombangBuka();
    $jalurLain = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi', 'status_aktif' => true]);
    $gelombang->jalur()->attach($jalurTerhubung->id);

    $response = $this->get("/spmb/{$lembaga->slug}");

    $response->assertOk();
    $response->assertSee($jalurTerhubung->nama);
    $response->assertDontSee($jalurLain->nama);
});

it('shows all active jalur when the open gelombang has no restriction rows', function () {
    [$lembaga, $tahunAjaran, $jalurSatu] = buatLembagaDenganGelombangBuka();
    $jalurDua = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi', 'status_aktif' => true]);

    $response = $this->get("/spmb/{$lembaga->slug}");

    $response->assertOk();
    $response->assertSee($jalurSatu->nama);
    $response->assertSee($jalurDua->nama);
});

it('shows the real nominal, Gratis, or Menunggu Konfirmasi Admin for each biaya pendaftaran state', function () {
    [$lembaga, $tahunAjaran, $jalurBerbayar] = buatLembagaDenganGelombangBuka();
    $jalurGratis = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Afirmasi', 'status_aktif' => true]);
    $jalurBelumDikonfigurasi = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi', 'status_aktif' => true]);

    $jenisPendaftaran = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisPendaftaran->id, 'jalur_ppdb_id' => $jalurBerbayar->id, 'nominal' => 150000]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisPendaftaran->id, 'jalur_ppdb_id' => $jalurGratis->id, 'nominal' => 0]);

    $response = $this->get("/spmb/{$lembaga->slug}");

    $response->assertOk();
    $response->assertSee('Rp150.000');
    $response->assertSee('Gratis');
    $response->assertSee('Menunggu Konfirmasi Admin');
});

it('links to the existing CekStatusController status form for this lembaga', function () {
    [$lembaga] = buatLembagaDenganGelombangBuka();

    $this->get("/spmb/{$lembaga->slug}")
        ->assertOk()
        ->assertSee(route('spmb.status.form', ['lembagaSlug' => $lembaga->slug]), false);
});

it('does not show jalur belonging to an inactive tahun ajaran', function () {
    [$lembaga, , $jalurAktif] = buatLembagaDenganGelombangBuka();
    $tahunAjaranLama = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2024/2025',
        'tanggal_mulai' => '2024-07-01', 'tanggal_selesai' => '2025-06-30', 'status_aktif' => false,
    ]);
    $jalurLama = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaranLama->id, 'nama' => 'Jalur Tahun Lalu', 'status_aktif' => true]);

    $response = $this->get("/spmb/{$lembaga->slug}");

    $response->assertOk();
    $response->assertSee($jalurAktif->nama);
    $response->assertDontSee($jalurLama->nama);
});
