<?php
// tests/Feature/KaryawanDashboardTest.php

use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Lembaga;
use App\Models\Yayasan;
use App\Services\AkunKaryawanGenerator;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Artisan::call('permissions:sync');
    $this->seed(RoleSeeder::class);
});

it('shows a pegawai_yayasan account the karyawan placeholder dashboard, not the unrestricted yayasan dashboard', function () {
    $yayasan = Yayasan::factory()->create();
    $jenis = JenisKaryawanMaster::factory()->create();

    $karyawan = app(AkunKaryawanGenerator::class)->buat(
        'Konselor Pool',
        '3201234567891111',
        $yayasan->id,
        null,
        $jenis->id,
    );

    $karyawan->user()->update(['must_change_password' => false, 'email_verified_at' => now()]);

    $response = $this->actingAs($karyawan->user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertViewIs('admin.dashboard.karyawan');
    $response->assertViewHas('karyawan', fn ($k) => $k->id === $karyawan->id);
    $response->assertViewHas('izinCutiPending', 0);
});

it('shows a pegawai_lembaga account the karyawan placeholder dashboard, not the lembaga admin dashboard', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $jenis = JenisKaryawanMaster::factory()->create();

    $karyawan = app(AkunKaryawanGenerator::class)->buat(
        'Konselor Lembaga',
        '3201234567892222',
        $yayasan->id,
        $lembaga->id,
        $jenis->id,
    );

    $karyawan->user()->update(['must_change_password' => false, 'email_verified_at' => now()]);

    $response = $this->actingAs($karyawan->user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertViewIs('admin.dashboard.karyawan');
});

it('passes sisaKuotaCuti and shiftHariIni to the karyawan dashboard view', function () {
    $yayasan = Yayasan::factory()->create();
    $jenis = JenisKaryawanMaster::factory()->create();

    $karyawan = app(AkunKaryawanGenerator::class)->buat(
        'Staf HR',
        '3201234567893333',
        $yayasan->id,
        null,
        $jenis->id,
    );
    $karyawan->user()->update(['must_change_password' => false, 'email_verified_at' => now()]);

    $response = $this->actingAs($karyawan->user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Sisa Kuota Cuti');
    $response->assertSee('Shift Hari Ini');
});

