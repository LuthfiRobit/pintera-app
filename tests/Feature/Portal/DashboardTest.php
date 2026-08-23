<?php
// tests/Feature/Portal/DashboardTest.php

use App\Models\AkunPendaftar;
use App\Models\CalonMurid;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function buatPendaftaranUntukAkun(AkunPendaftar $akun, string $nama = 'Ahmad Fauzan', ?string $status = null): Pendaftaran
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id, 'nama_lengkap' => $nama]);

    return Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id,
        'lembaga_id' => $lembaga->id,
        'akun_pendaftar_id' => $akun->id,
        'email_pendaftaran' => $akun->email,
        'status' => $status ?? 'menunggu_verifikasi',
    ]);
}

it('redirects an unverified akun away from the dashboard to the otp page', function () {
    $akun = AkunPendaftar::factory()->unverified()->create();

    $this->actingAs($akun, 'portal')->get(route('portal.dashboard'))
        ->assertRedirect(route('portal.verifikasi-otp'));
});

it('shows only the pendaftaran linked to the logged-in akun', function () {
    $akunSaya = AkunPendaftar::factory()->create();
    buatPendaftaranUntukAkun($akunSaya, 'Punya Saya');
    $akunLain = AkunPendaftar::factory()->create();
    buatPendaftaranUntukAkun($akunLain, 'Punya Orang Lain');

    $response = $this->actingAs($akunSaya, 'portal')->get(route('portal.dashboard'));

    $response->assertOk();
    $response->assertSee('Punya Saya');
    $response->assertDontSee('Punya Orang Lain');
});

it('shows an empty state when no pendaftaran is linked', function () {
    $akun = AkunPendaftar::factory()->create();

    $this->actingAs($akun, 'portal')->get(route('portal.dashboard'))->assertOk();
});

it('allows downloading the bukti pendaftaran pdf for a pendaftaran the akun owns', function () {
    $akun = AkunPendaftar::factory()->create();
    $pendaftaran = buatPendaftaranUntukAkun($akun);

    $this->actingAs($akun, 'portal')
        ->get(route('portal.pendaftaran.bukti', $pendaftaran))
        ->assertOk();
});

it('404s when trying to download a pendaftaran belonging to a different akun', function () {
    $akunLain = AkunPendaftar::factory()->create();
    $pendaftaranOrangLain = buatPendaftaranUntukAkun($akunLain);
    $akunSaya = AkunPendaftar::factory()->create();

    $this->actingAs($akunSaya, 'portal')
        ->get(route('portal.pendaftaran.bukti', $pendaftaranOrangLain))
        ->assertNotFound();
});

it('shows a wizard progress card at 0% instead of redirecting when there is no pendaftaran and the session has a fresh lembaga+jalur choice', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    $akun = loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $response = $this->get(route('portal.dashboard'));

    $response->assertOk();
    $response->assertSee('Lengkapi Data Pendaftaran');
    $response->assertSee('0%');
    $response->assertSee(route('portal.wizard.data-diri'), false);
});

it('does not show a progress card when the session points to a lembaga or jalur that no longer exists', function () {
    $akun = AkunPendaftar::factory()->create();
    session(['spmb_pilihan.lembaga_id' => 999999, 'spmb_pilihan.jalur_id' => 999999]);

    $response = $this->actingAs($akun, 'portal')->get(route('portal.dashboard'));

    $response->assertOk();
    $response->assertDontSee('Lengkapi Data Pendaftaran');
});

it('shows a progress card when the account has a decided pendaftaran but the session points to a different, not-yet-registered jalur', function () {
    [$lembaga, $tahunAjaran, $jalurA, $gelombang] = buatLembagaDenganGelombangBuka();
    $jalurB = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi']);
    $akun = AkunPendaftar::factory()->create();
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id,
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalurA->id,
        'gelombang_ppdb_id' => $gelombang->id,
        'akun_pendaftar_id' => $akun->id,
        'email_pendaftaran' => $akun->email,
        'status' => 'diterima',
    ]);
    session(['spmb_pilihan.lembaga_id' => $lembaga->id, 'spmb_pilihan.jalur_id' => $jalurB->id]);

    $response = $this->actingAs($akun, 'portal')->get(route('portal.dashboard'));

    $response->assertOk();
    $response->assertSee('Lengkapi Data Pendaftaran');
    $response->assertSee('Jalur Prestasi');
});

it('advances the progress percentage and continue link as wizard session steps are completed', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    $akun = loginAkunDenganPilihanSpmb($lembaga, $jalur);
    session(["spmb_wizard.{$lembaga->id}.{$jalur->id}" => [
        'data_pribadi' => ['nama_lengkap' => 'Ahmad'],
        'jawaban_formulir' => [],
    ]]);

    $response = $this->get(route('portal.dashboard'));

    $response->assertOk();
    $response->assertSee('50%');
    $response->assertSee(route('portal.wizard.dokumen'), false);
});

it('shows the dokumen-terupload count against the jalur\'s required syarat', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    foreach (['Kartu Keluarga', 'Akta Kelahiran', 'Ijazah'] as $urutan => $namaDokumen) {
        \App\Models\DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'lembaga_id' => $lembaga->id, 'nama_dokumen' => $namaDokumen, 'wajib' => true, 'urutan' => $urutan]);
    }
    $akun = loginAkunDenganPilihanSpmb($lembaga, $jalur);
    session(["spmb_wizard.{$lembaga->id}.{$jalur->id}" => [
        'dokumen' => [1 => ['file_path' => 'x'], 2 => ['file_path' => 'y']],
    ]]);

    $this->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee('2 / 3');
});

it('shows the batas gelombang date on the progress card', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    $akun = loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee($gelombang->tanggal_tutup->translatedFormat('d M Y'));
});

it('shows the biaya pendaftaran nominal without a payment-status claim on the progress card', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    $jenisTagihan = \App\Domains\Keuangan\Models\JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran']);
    \App\Domains\Keuangan\Models\NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 150000]);
    $akun = loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $response = $this->get(route('portal.dashboard'));

    $response->assertOk();
    $response->assertSee('Rp150.000');
    $response->assertDontSee('Belum Dibayar');
});

it('clears the session and shows a status message instead of redirecting when the choice exactly matches a jalur the account already registered', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    $akun = AkunPendaftar::factory()->create();
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id,
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id,
        'gelombang_ppdb_id' => $gelombang->id,
        'akun_pendaftar_id' => $akun->id,
        'email_pendaftaran' => $akun->email,
        'status' => 'diterima',
    ]);
    session(['spmb_pilihan.lembaga_id' => $lembaga->id, 'spmb_pilihan.jalur_id' => $jalur->id]);

    $response = $this->actingAs($akun, 'portal')->get(route('portal.dashboard'));

    $response->assertOk();
    $response->assertSee('sudah terdaftar');
    expect(session('spmb_pilihan.lembaga_id'))->toBeNull();
    expect(session('spmb_pilihan.jalur_id'))->toBeNull();
});

it('clears the session and blocks a new jalur choice while an existing pendaftaran is still awaiting a decision', function () {
    [$lembaga, $tahunAjaran, $jalurA, $gelombang] = buatLembagaDenganGelombangBuka();
    $jalurB = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi']);
    $akun = AkunPendaftar::factory()->create();
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id,
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalurA->id,
        'gelombang_ppdb_id' => $gelombang->id,
        'akun_pendaftar_id' => $akun->id,
        'email_pendaftaran' => $akun->email,
        'status' => 'menunggu_verifikasi',
    ]);
    session(['spmb_pilihan.lembaga_id' => $lembaga->id, 'spmb_pilihan.jalur_id' => $jalurB->id]);

    $response = $this->actingAs($akun, 'portal')->get(route('portal.dashboard'));

    $response->assertOk();
    $response->assertSee('menunggu keputusan');
    expect(session('spmb_pilihan.lembaga_id'))->toBeNull();
    expect(session('spmb_pilihan.jalur_id'))->toBeNull();
});

it('redirects a guest to login when accessing the dashboard', function () {
    $this->get(route('portal.dashboard'))
        ->assertRedirect(route('login'));
});

it('shows the correct label for every pendaftaran status', function () {
    $akun = AkunPendaftar::factory()->create();
    buatPendaftaranUntukAkun($akun, 'Calon Menunggu', 'menunggu_verifikasi');
    buatPendaftaranUntukAkun($akun, 'Calon Diterima', 'diterima');
    buatPendaftaranUntukAkun($akun, 'Calon Ditolak', 'ditolak');
    buatPendaftaranUntukAkun($akun, 'Calon Daftar Ulang', 'daftar_ulang');
    buatPendaftaranUntukAkun($akun, 'Calon Aktif', 'aktif');

    $response = $this->actingAs($akun, 'portal')->get(route('portal.dashboard'));

    $response->assertOk();
    $response->assertSee('Menunggu Verifikasi');
    $response->assertSee('Diterima');
    $response->assertSee('Ditolak');
    $response->assertSee('Daftar Ulang');
    $response->assertSee('Aktif');
});

it('shows a Daftar Lagi button linking to the welcome page when the account has a pendaftaran', function () {
    $akun = AkunPendaftar::factory()->create();
    buatPendaftaranUntukAkun($akun);

    $this->actingAs($akun, 'portal')->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee('Daftar Lagi')
        ->assertSee(route('spmb.welcome'), false);
});

it('shows an empty-state CTA linking to the welcome page when the account has no pendaftaran', function () {
    $akun = AkunPendaftar::factory()->create();

    $this->actingAs($akun, 'portal')->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee('Pilih Lembaga & Jalur')
        ->assertSee(route('spmb.welcome'), false);
});

it('renders the authenticated navbar on the dashboard', function () {
    $akun = AkunPendaftar::factory()->create();

    $this->actingAs($akun, 'portal')->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Riwayat')
        ->assertSee('Bantuan');
});

it('shows a status card for a pendaftaran already submitted and awaiting a decision, even with no session choice', function () {
    $akun = AkunPendaftar::factory()->create();
    $pendaftaran = buatPendaftaranUntukAkun($akun);

    $response = $this->actingAs($akun, 'portal')->get(route('portal.dashboard'));

    $response->assertOk();
    $response->assertSee('Menunggu Verifikasi');
    $response->assertSee('Unduh Bukti Pendaftaran');
    $response->assertSee(route('portal.pendaftaran.bukti', $pendaftaran), false);
});

it('shows the real dokumen-verified count on the submitted status card', function () {
    $akun = AkunPendaftar::factory()->create();
    $pendaftaran = buatPendaftaranUntukAkun($akun);
    $syarat = \App\Models\DokumenSyaratPpdb::create(['jalur_ppdb_id' => $pendaftaran->jalur_ppdb_id, 'lembaga_id' => $pendaftaran->lembaga_id, 'nama_dokumen' => 'Kartu Keluarga', 'wajib' => true, 'urutan' => 0]);
    \App\Models\DokumenPendaftaran::create(['pendaftaran_id' => $pendaftaran->id, 'dokumen_syarat_ppdb_id' => $syarat->id, 'file_path' => 'x', 'nama_file_asli' => 'kk.pdf', 'mime_type' => 'application/pdf', 'ukuran_bytes' => 1024, 'status_verifikasi' => 'diterima']);
    $syaratLain = \App\Models\DokumenSyaratPpdb::create(['jalur_ppdb_id' => $pendaftaran->jalur_ppdb_id, 'lembaga_id' => $pendaftaran->lembaga_id, 'nama_dokumen' => 'Akta Kelahiran', 'wajib' => true, 'urutan' => 1]);
    \App\Models\DokumenPendaftaran::create(['pendaftaran_id' => $pendaftaran->id, 'dokumen_syarat_ppdb_id' => $syaratLain->id, 'file_path' => 'y', 'nama_file_asli' => 'akta.pdf', 'mime_type' => 'application/pdf', 'ukuran_bytes' => 1024, 'status_verifikasi' => 'belum_diverifikasi']);

    $this->actingAs($akun, 'portal')->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee('1 / 2')
        ->assertSee('Dokumen Terverifikasi');
});

it('shows the real payment status on the submitted status card', function () {
    $akun = AkunPendaftar::factory()->create();
    $pendaftaran = buatPendaftaranUntukAkun($akun);
    \App\Domains\Keuangan\Models\Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 150000, 'status' => 'belum_bayar', 'jatuh_tempo' => now()->addWeek()]);

    $this->actingAs($akun, 'portal')->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee('Belum Dibayar')
        ->assertSee('Rp150.000');
});

it('still shows the submitted pendaftaran in the riwayat list alongside its own status card', function () {
    $akun = AkunPendaftar::factory()->create();
    buatPendaftaranUntukAkun($akun, 'Ahmad Fauzan');

    $this->actingAs($akun, 'portal')->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee('Riwayat Pendaftaran')
        ->assertSee('Ahmad Fauzan');
});
