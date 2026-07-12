<?php

use App\Models\Lembaga;
use App\Models\LembagaDataPeriodik;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

it('relates data periodik to a lembaga and a semester', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');
    $this->actingAs($user);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id,
        'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-06-30',
        'status_aktif' => true,
    ]);

    $semester = Semester::create([
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Ganjil',
        'urutan' => 1,
        'kode_dapodik' => '20261',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-01-15',
    ]);

    LembagaDataPeriodik::create([
        'lembaga_id' => $lembaga->id,
        'semester_id' => $semester->id,
        'waktu_penyelenggaraan' => 'pagi',
        'jumlah_tempat_cuci_tangan' => 4,
        'jumlah_jamban' => 6,
    ]);

    expect($lembaga->dataPeriodik)->toHaveCount(1);
    expect($semester->dataPeriodikLembaga)->toHaveCount(1);
});
