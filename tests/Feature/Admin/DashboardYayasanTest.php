<?php
// tests/Feature/Admin/DashboardYayasanTest.php

use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\TahunAjaran;
use App\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('shows consolidated totals summed across every lembaga under the yayasan', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunA = TahunAjaran::create(['lembaga_id' => $lembagaA->id, 'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true]);
    $tahunB = TahunAjaran::create(['lembaga_id' => $lembagaB->id, 'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true]);
    Pendaftaran::factory()->count(2)->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunA->id]);
    Pendaftaran::factory()->count(3)->create(['lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $tahunB->id]);
    $user = User::factory()->create();
    $user->assignRole('yayasan_super_admin');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee($lembagaA->nama);
    $response->assertSee($lembagaB->nama);
});

it('shows the lembaga dashboard, not the yayasan dashboard, once active_lembaga_id is set via switch_lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true]);
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 150000, 'status' => 'lunas']);
    $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $user->assignRole('yayasan_super_admin');

    $response = $this->actingAs($user)->get(route('dashboard', ['switch_lembaga' => $lembaga->id]));

    $response->assertOk();
    $response->assertSee('Rp 150.000');
    $response->assertDontSee('Dashboard Yayasan');
});

it('goes back to the yayasan dashboard once switch_lembaga=all is used', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $user->assignRole('yayasan_super_admin');

    $this->actingAs($user)->get(route('dashboard', ['switch_lembaga' => $lembaga->id]));
    $response = $this->actingAs($user)->get(route('dashboard', ['switch_lembaga' => 'all']));

    $response->assertOk();
    $response->assertSee('Dashboard Yayasan');
});

it('renders the yayasan dashboard without error when there is no lembaga in the system at all', function () {
    $user = User::factory()->create();
    $user->assignRole('yayasan_super_admin');

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});
