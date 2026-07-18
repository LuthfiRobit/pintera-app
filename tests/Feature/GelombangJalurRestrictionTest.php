<?php

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

function buatGelombangDenganDuaJalur(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalurReguler = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $jalurPrestasi = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi']);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now()->subDay(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 40,
    ]);

    return [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang];
}

it('lets a gelombang be attached to specific jalur via the pivot', function () {
    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();

    $gelombang->jalur()->attach($jalurReguler->id);

    expect($gelombang->jalur()->pluck('jalur_ppdb.id')->all())->toBe([$jalurReguler->id]);
    expect($jalurReguler->gelombang()->pluck('gelombang_ppdb.id')->all())->toBe([$gelombang->id]);
    expect($jalurPrestasi->gelombang()->count())->toBe(0);
});

it('has zero pivot rows for a gelombang by default (unrestricted)', function () {
    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();

    expect($gelombang->jalur()->exists())->toBeFalse();
});

it('shows every active jalur to the public when the gelombang is unrestricted', function () {
    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();

    $this->get("/spmb/{$lembaga->slug}")
        ->assertOk()
        ->assertSee('Reguler')
        ->assertSee('Prestasi');
});

it('shows only the assigned jalur to the public when the gelombang is restricted', function () {
    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();
    $gelombang->jalur()->attach($jalurReguler->id);

    $this->get("/spmb/{$lembaga->slug}")
        ->assertOk()
        ->assertSee('Reguler')
        ->assertDontSee('Prestasi');
});

it('never shows an inactive jalur to the public even if explicitly assigned to the gelombang', function () {
    [$lembaga, $tahunAjaran, $jalurReguler, $jalurPrestasi, $gelombang] = buatGelombangDenganDuaJalur();
    $jalurPrestasi->update(['status_aktif' => false]);
    $gelombang->jalur()->attach([$jalurReguler->id, $jalurPrestasi->id]);

    $this->get("/spmb/{$lembaga->slug}")
        ->assertOk()
        ->assertSee('Reguler')
        ->assertDontSee('Prestasi');
});
