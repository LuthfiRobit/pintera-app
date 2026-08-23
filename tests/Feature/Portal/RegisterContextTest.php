<?php
// tests/Feature/Portal/RegisterContextTest.php

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Domains\Keuangan\Models\NominalTagihanJalur;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

it('shows no context chip or sidebar when no jalur is selected in session', function () {
    $this->get(route('portal.register'))
        ->assertOk()
        ->assertDontSee('Mendaftar untuk')
        ->assertDontSee('Jalur Lain yang Tersedia');
});

it('shows the context chip and sidebar when a jalur is selected in session', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    $jalurLain = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi', 'status_aktif' => true]);

    $this->withSession(['spmb_pilihan.lembaga_id' => $lembaga->id, 'spmb_pilihan.jalur_id' => $jalur->id])
        ->get(route('portal.register'))
        ->assertOk()
        ->assertSee('Mendaftar untuk')
        ->assertSee($lembaga->nama)
        ->assertSee($jalur->nama)
        ->assertSee('Jalur Lain yang Tersedia')
        ->assertSee($jalurLain->nama);
});

it('excludes the currently selected jalur from the sidebar list', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();

    $response = $this->withSession(['spmb_pilihan.lembaga_id' => $lembaga->id, 'spmb_pilihan.jalur_id' => $jalur->id])
        ->get(route('portal.register'));

    $response->assertOk();
    $response->assertSee('Dipilih');
});

it('shows the three biaya pendaftaran states in the sidebar', function () {
    [$lembaga, $tahunAjaran, $jalurTerpilih] = buatLembagaDenganGelombangBuka();
    $jalurGratis = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Afirmasi', 'status_aktif' => true]);
    $jalurBelumDikonfigurasi = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi', 'status_aktif' => true]);

    $jenisPendaftaran = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisPendaftaran->id, 'jalur_ppdb_id' => $jalurGratis->id, 'nominal' => 0]);

    $response = $this->withSession(['spmb_pilihan.lembaga_id' => $lembaga->id, 'spmb_pilihan.jalur_id' => $jalurTerpilih->id])
        ->get(route('portal.register'));

    $response->assertOk();
    $response->assertSee('Gratis');
    $response->assertSee('Menunggu Konfirmasi');
});

it('switches the selected jalur in session and redirects back to register', function () {
    [$lembaga, $tahunAjaran, $jalurAwal] = buatLembagaDenganGelombangBuka();
    $jalurBaru = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi', 'status_aktif' => true]);

    $response = $this->withSession(['spmb_pilihan.lembaga_id' => $lembaga->id, 'spmb_pilihan.jalur_id' => $jalurAwal->id])
        ->post(route('spmb.register.ganti-jalur', ['jalur' => $jalurBaru->id]));

    $response->assertRedirect(route('spmb.register'));
    $response->assertSessionHas('spmb_pilihan.jalur_id', $jalurBaru->id);
    $response->assertSessionHas('spmb_pilihan.lembaga_id', $lembaga->id);
});

it('404s ganti-jalur when the jalur does not belong to the lembaga in session', function () {
    [$lembagaSatu] = buatLembagaDenganGelombangBuka();
    [, , $jalurLembagaDua] = buatLembagaDenganGelombangBuka();

    $response = $this->withSession(['spmb_pilihan.lembaga_id' => $lembagaSatu->id, 'spmb_pilihan.jalur_id' => 1])
        ->post(route('spmb.register.ganti-jalur', ['jalur' => $jalurLembagaDua->id]));

    $response->assertNotFound();
});

it('requires no_hp_wa and terms acceptance on registration', function () {
    $response = $this->post(route('portal.register'), [
        'nama' => 'Tanpa Data',
        'email' => 'tanpadata@example.test',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
    ]);

    $response->assertSessionHasErrors(['no_hp_wa', 'terms']);
});

it('saves no_hp_wa when registering with full data', function () {
    $response = $this->post(route('portal.register'), [
        'nama' => 'Lengkap',
        'email' => 'lengkap@example.test',
        'no_hp_wa' => '081234567890',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'terms' => '1',
    ]);

    $response->assertRedirect(route('portal.verifikasi-otp'));
    expect(\App\Models\AkunPendaftar::where('email', 'lengkap@example.test')->first()->no_hp_wa)->toBe('081234567890');
});
