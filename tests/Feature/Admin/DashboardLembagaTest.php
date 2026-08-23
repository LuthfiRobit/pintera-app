<?php
// tests/Feature/Admin/DashboardLembagaTest.php

use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\TahunAjaran;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('shows the spmb section only for a user with spmb-pendaftaran.view, and hides the keuangan section without tagihan.view', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = TahunAjaran::create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true]);
    Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'status' => 'diterima']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Total Pendaftar');
    $response->assertDontSee('Rp Terkumpul');
});

it('shows both spmb and keuangan sections for admin_keuangan, with correct numbers', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = TahunAjaran::create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true]);
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'status' => 'diterima']);
    Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 150000, 'status' => 'lunas']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Total Pendaftar');
    $response->assertSee('Rp Terkumpul');
    $response->assertSee('Rp 150.000');
});

it('does not leak another lembaga data into the dashboard numbers', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = TahunAjaran::create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true]);
    $lembagaLain = Lembaga::factory()->create();
    $tahunAjaranLain = TahunAjaran::create(['lembaga_id' => $lembagaLain->id, 'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true]);
    Pendaftaran::factory()->count(3)->create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $tahunAjaranLain->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    // Note: assertDontSeeText('3', false) as originally specified false-positives on the
    // static "(30 Hari Terakhir)" trend panel label, which always renders once the SPMB
    // section is visible and has nothing to do with tenant leakage. assertDontSee('>3<', ...)
    // checks the raw HTML for an element whose entire text content is "3" — which is how a
    // leaked count would actually render in a stat tile (e.g. <p ...>3</p>) — without
    // tripping on unrelated prose or markup that merely contains the digit 3.
    $response->assertDontSee('>3<', false);
});

it('renders the lembaga dashboard without error when there is no active tahun ajaran', function () {
    $lembaga = Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

it('shows both sections for kepala_sekolah too, since the gating is permission-based not role-based', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = TahunAjaran::create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true]);
    Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'status' => 'diterima']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('kepala_sekolah');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Total Pendaftar');
    $response->assertSee('Rp Terkumpul');
});

it('does not change the guru dashboard', function () {
    $lembaga = Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('guru');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('Total Pendaftar');
    $response->assertDontSee('Rp Terkumpul');
});
