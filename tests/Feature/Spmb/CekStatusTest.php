<?php

use App\Models\CalonMurid;
use App\Models\Pendaftaran;

it('shows the status form', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();

    $this->get("/spmb/{$lembaga->slug}/status")->assertOk();
});

it('shows the pendaftaran summary and status when kode and email match', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id, 'nama_lengkap' => 'Ahmad Fauzan']);
    $pendaftaran = Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00001', 'email_pendaftaran' => 'wali@example.test',
    ]);

    $this->post("/spmb/{$lembaga->slug}/status", [
        'kode_pendaftaran' => 'REG-2026-00001', 'email' => 'wali@example.test',
    ])->assertOk()->assertSee('Ahmad Fauzan')->assertSee('Menunggu Verifikasi');
});

it('rejects a kode+email combination that does not match', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00001', 'email_pendaftaran' => 'wali@example.test',
    ]);

    $this->post("/spmb/{$lembaga->slug}/status", [
        'kode_pendaftaran' => 'REG-2026-00001', 'email' => 'salah@example.test',
    ])->assertSessionHasErrors('kode_pendaftaran');
});

it('downloads a pdf bukti pendaftaran when kode and email match', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00001', 'email_pendaftaran' => 'wali@example.test',
    ]);

    $response = $this->get("/spmb/{$lembaga->slug}/bukti/REG-2026-00001?email=wali@example.test");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('404s the pdf download when the email does not match', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00001', 'email_pendaftaran' => 'wali@example.test',
    ]);

    $this->get("/spmb/{$lembaga->slug}/bukti/REG-2026-00001?email=salah@example.test")->assertNotFound();
});
