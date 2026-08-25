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

it('shows both spmb and keuangan sections for bendahara_lembaga, with correct numbers', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = TahunAjaran::create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true]);
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'status' => 'diterima']);
    Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 150000, 'status' => 'lunas']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

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
    $user->assignRole('bendahara_lembaga');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('>3<', false);
});

it('renders the lembaga dashboard without error when there is no active tahun ajaran', function () {
    $lembaga = Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

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

it('shows the rapor fill progress table only for a user with komponen-penilaian.kelola permission', function () {
    $lembaga = Lembaga::factory()->create();
    $kelas = \App\Models\Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Kelas Uji Progress']);

    $userDitolak = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userDitolak->assignRole('admin_administrasi');

    $resDitolak = $this->actingAs($userDitolak)->get(route('dashboard'));
    $resDitolak->assertOk();
    $resDitolak->assertDontSee('Progress Pengumpulan Nilai per Kelas');

    $userDiizinkan = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userDiizinkan->assignRole('kepala_sekolah');

    $resDiizinkan = $this->actingAs($userDiizinkan)->get(route('dashboard'));
    $resDiizinkan->assertOk();
    $resDiizinkan->assertSee('Progress Pengumpulan Nilai per Kelas');
    $resDiizinkan->assertSee('Kelas Uji Progress');
});
