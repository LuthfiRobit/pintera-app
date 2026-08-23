<?php
// tests/Unit/DashboardStatsServiceTest.php

use App\Models\Cicilan;
use App\Models\Lembaga;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\Role;
use App\Domains\Keuangan\Models\SkemaCicilan;
use App\Models\TahunAjaran;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\User;
use App\Services\DashboardStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function siapkanTahunAjaranAktifUntukDashboard(Lembaga $lembaga): TahunAjaran
{
    return TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
}

it('counts pendaftaran per status for the active tahun ajaran only, scoped to the given lembaga', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = siapkanTahunAjaranAktifUntukDashboard($lembaga);
    $lembagaLain = Lembaga::factory()->create();
    $tahunAjaranLain = siapkanTahunAjaranAktifUntukDashboard($lembagaLain);

    Pendaftaran::factory()->count(2)->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'status' => 'menunggu_verifikasi']);
    Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'status' => 'diterima']);
    Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'status' => 'ditolak']);
    Pendaftaran::factory()->create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $tahunAjaranLain->id, 'status' => 'diterima']);

    $hasil = app(DashboardStatsService::class)->statistikSpmb($lembaga->id);

    expect($hasil)->toBe(['total' => 4, 'menunggu_verifikasi' => 2, 'diterima' => 1, 'ditolak' => 1]);
});

it('returns all-zero spmb stats when the lembaga has no active tahun ajaran', function () {
    $lembaga = Lembaga::factory()->create();

    $hasil = app(DashboardStatsService::class)->statistikSpmb($lembaga->id);

    expect($hasil)->toBe(['total' => 0, 'menunggu_verifikasi' => 0, 'diterima' => 0, 'ditolak' => 0]);
});

it('builds a 30-point daily trend including days with zero pendaftaran, oldest first', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = siapkanTahunAjaranAktifUntukDashboard($lembaga);
    Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'submitted_at' => now()]);
    Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'submitted_at' => now()]);
    Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'submitted_at' => now()->subDays(35)]);

    $hasil = app(DashboardStatsService::class)->trenPendaftaranHarian($lembaga->id);

    expect($hasil['labels'])->toHaveCount(30);
    expect($hasil['data'])->toHaveCount(30);
    expect($hasil['data'][29])->toBe(2);
    expect(array_sum($hasil['data']))->toBe(2);
});

it('computes rpTerkumpul and rpBelumLunas from tagihan status, scoped to lembaga and active tahun ajaran', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = siapkanTahunAjaranAktifUntukDashboard($lembaga);
    $pendaftaranLunas = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $pendaftaranCicil = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $pendaftaranBelum = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    Tagihan::create(['pendaftaran_id' => $pendaftaranLunas->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 150000, 'status' => 'lunas']);
    Tagihan::create(['pendaftaran_id' => $pendaftaranCicil->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 900000, 'status' => 'dicicil']);
    Tagihan::create(['pendaftaran_id' => $pendaftaranBelum->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 150000, 'status' => 'belum_bayar']);

    $hasil = app(DashboardStatsService::class)->statistikKeuangan($lembaga->id);

    expect($hasil['rpTerkumpul'])->toBe(150000);
    expect($hasil['rpBelumLunas'])->toBe(1050000);
    expect($hasil['donut'])->toBe(['belum_bayar' => 1, 'dicicil' => 1, 'lunas' => 1]);
});

it('counts pembayaran menunggu verifikasi through both the direct tagihan and the cicilan ownership path', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = siapkanTahunAjaranAktifUntukDashboard($lembaga);
    $pendaftaranLangsung = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $tagihanLangsung = Tagihan::create(['pendaftaran_id' => $pendaftaranLangsung->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 150000, 'status' => 'belum_bayar']);
    Pembayaran::create(['tagihan_id' => $tagihanLangsung->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi']);

    $pendaftaranCicil = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $tagihanCicil = Tagihan::create(['pendaftaran_id' => $pendaftaranCicil->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 900000, 'status' => 'dicicil']);
    $skema = SkemaCicilan::create(['tagihan_id' => $tagihanCicil->id, 'jumlah_termin' => 3, 'dibuat_oleh' => 'calon_siswa']);
    $termin = Cicilan::create(['skema_cicilan_id' => $skema->id, 'urutan' => 1, 'nominal' => 300000, 'jatuh_tempo' => now(), 'status' => 'belum_bayar']);
    Pembayaran::create(['cicilan_id' => $termin->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi']);

    $hasil = app(DashboardStatsService::class)->statistikKeuangan($lembaga->id);

    expect($hasil['pembayaranMenungguVerifikasi'])->toBe(2);
});

it('returns all-zero keuangan stats when the lembaga has no active tahun ajaran', function () {
    $lembaga = Lembaga::factory()->create();

    $hasil = app(DashboardStatsService::class)->statistikKeuangan($lembaga->id);

    expect($hasil)->toBe([
        'rpTerkumpul' => 0, 'rpBelumLunas' => 0, 'pembayaranMenungguVerifikasi' => 0,
        'donut' => ['belum_bayar' => 0, 'dicicil' => 0, 'lunas' => 0],
    ]);
});

it('does not leak keuangan data from another lembaga into the requested lembaga\'s stats', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = siapkanTahunAjaranAktifUntukDashboard($lembaga);
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 150000, 'status' => 'lunas']);

    $lembagaLain = Lembaga::factory()->create();
    $tahunAjaranLain = siapkanTahunAjaranAktifUntukDashboard($lembagaLain);
    $pendaftaranLain = Pendaftaran::factory()->create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $tahunAjaranLain->id]);
    $tagihanLain = Tagihan::create(['pendaftaran_id' => $pendaftaranLain->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 900000, 'status' => 'dicicil']);
    Pembayaran::create(['tagihan_id' => $tagihanLain->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi']);
    $skemaLain = SkemaCicilan::create(['tagihan_id' => $tagihanLain->id, 'jumlah_termin' => 3, 'dibuat_oleh' => 'calon_siswa']);
    $terminLain = Cicilan::create(['skema_cicilan_id' => $skemaLain->id, 'urutan' => 1, 'nominal' => 300000, 'jatuh_tempo' => now(), 'status' => 'belum_bayar']);
    Pembayaran::create(['cicilan_id' => $terminLain->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi']);

    $hasil = app(DashboardStatsService::class)->statistikKeuangan($lembaga->id);

    expect($hasil['rpTerkumpul'])->toBe(150000);
    expect($hasil['rpBelumLunas'])->toBe(0);
    expect($hasil['donut'])->toBe(['belum_bayar' => 0, 'dicicil' => 0, 'lunas' => 1]);
    expect($hasil['pembayaranMenungguVerifikasi'])->toBe(0);
});

it('does not include pendaftaran from another lembaga in the daily trend', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = siapkanTahunAjaranAktifUntukDashboard($lembaga);
    Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'submitted_at' => now()]);

    $lembagaLain = Lembaga::factory()->create();
    $tahunAjaranLain = siapkanTahunAjaranAktifUntukDashboard($lembagaLain);
    Pendaftaran::factory()->count(5)->create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $tahunAjaranLain->id, 'submitted_at' => now()]);

    $hasil = app(DashboardStatsService::class)->trenPendaftaranHarian($lembaga->id);

    expect($hasil['data'])->toHaveCount(30);
    expect(array_sum($hasil['data']))->toBe(1);
    expect($hasil['data'][29])->toBe(1);
});

it('bypasses TahunAjaran tenant scoping: statistikSpmb still finds the requested lembaga\'s active tahun ajaran when the acting user belongs to a different lembaga', function () {
    Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $lembagaActing = Lembaga::factory()->create();
    $actingUser = User::factory()->create(['lembaga_id' => $lembagaActing->id]);
    $actingUser->assignRole('admin_administrasi');
    $this->actingAs($actingUser);

    $lembagaTarget = Lembaga::factory()->create();
    $tahunAjaranTarget = siapkanTahunAjaranAktifUntukDashboard($lembagaTarget);
    Pendaftaran::factory()->count(3)->create(['lembaga_id' => $lembagaTarget->id, 'tahun_ajaran_id' => $tahunAjaranTarget->id, 'status' => 'diterima']);

    $hasil = app(DashboardStatsService::class)->statistikSpmb($lembagaTarget->id);

    expect($hasil)->toBe(['total' => 3, 'menunggu_verifikasi' => 0, 'diterima' => 3, 'ditolak' => 0]);
});

it('bypasses TahunAjaran tenant scoping: statistikKeuangan still finds the requested lembaga\'s active tahun ajaran when the acting user belongs to a different lembaga', function () {
    Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $lembagaActing = Lembaga::factory()->create();
    $actingUser = User::factory()->create(['lembaga_id' => $lembagaActing->id]);
    $actingUser->assignRole('admin_administrasi');
    $this->actingAs($actingUser);

    $lembagaTarget = Lembaga::factory()->create();
    $tahunAjaranTarget = siapkanTahunAjaranAktifUntukDashboard($lembagaTarget);
    $pendaftaranTarget = Pendaftaran::factory()->create(['lembaga_id' => $lembagaTarget->id, 'tahun_ajaran_id' => $tahunAjaranTarget->id]);
    Tagihan::create(['pendaftaran_id' => $pendaftaranTarget->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 150000, 'status' => 'lunas']);

    $hasil = app(DashboardStatsService::class)->statistikKeuangan($lembagaTarget->id);

    expect($hasil['rpTerkumpul'])->toBe(150000);
    expect($hasil['donut'])->toBe(['belum_bayar' => 0, 'dicicil' => 0, 'lunas' => 1]);
});
