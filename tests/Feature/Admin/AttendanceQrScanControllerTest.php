<?php
// tests/Feature/Admin/AttendanceQrScanControllerTest.php

use App\Domains\Sdm\Actions\GenerateEmployeeQrTokenAction;
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

if (! function_exists('actingAsPetugasScan')) {
    function actingAsPetugasScan(Lembaga $lembaga): User
    {
        Permission::firstOrCreate(['name' => 'kehadiran-sdm.catat', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
        $role->givePermissionTo(['kehadiran-sdm.catat']);

        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $user->assignRole($role);

        return $user;
    }
}

it('records attendance via qr scan endpoint for an employee in the same lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => []]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $petugas = actingAsPetugasScan($lembaga);
    $qr = app(GenerateEmployeeQrTokenAction::class)->execute($guru);

    $this->actingAs($petugas)->postJson(route('admin.kehadiran-sdm.scan.store'), [
        'token' => $qr->token, 'arah' => 'masuk',
    ])->assertOk();

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeTrue();
});

it('returns a 422 json error for an invalid token via the scan endpoint', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $petugas = actingAsPetugasScan($lembaga);

    $this->actingAs($petugas)->postJson(route('admin.kehadiran-sdm.scan.store'), [
        'token' => 'tidak-ada', 'arah' => 'masuk',
    ])->assertStatus(422)->assertJson(['message' => 'QR tidak valid atau sudah tidak aktif.']);
});
