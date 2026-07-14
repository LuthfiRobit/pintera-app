<?php

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

function buatLembagaDenganGelombangBuka(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now()->subDay(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 40,
    ]);

    return [$lembaga, $tahunAjaran, $jalur, $gelombang];
}

it('shows the jalur list for a lembaga with an open gelombang', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();

    $this->get("/spmb/{$lembaga->slug}")
        ->assertOk()
        ->assertSee($jalur->nama)
        ->assertDontSee('belum dibuka');
});

it('shows a closed-registration page when no gelombang is currently open', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang Lalu',
        'tanggal_buka' => now()->subMonths(2), 'tanggal_tutup' => now()->subMonth(), 'kuota' => 40,
    ]);

    $this->get("/spmb/{$lembaga->slug}")->assertOk()->assertSee('belum dibuka', false);
});

it('picks the gelombang with the earliest tanggal_buka when two overlap', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang Lebih Awal',
        'tanggal_buka' => now()->subWeek(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 20,
    ]);

    $response = $this->get("/spmb/{$lembaga->slug}/{$jalur->id}/mulai");

    $response->assertOk();
});

it('404s for an unknown lembaga slug', function () {
    $this->get('/spmb/sekolah-tidak-ada')->assertNotFound();
});
