<?php
// tests/Feature/Admin/AttendancePolicyControllerTest.php

use App\Domains\Sdm\Models\AttendancePolicy;
use App\Models\JenisKaryawanMaster;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

if (! function_exists('actingAsAdminSdmPolicy')) {
    function actingAsAdminSdmPolicy(Lembaga $lembaga): User
    {
        foreach (['kehadiran-sdm.view', 'kehadiran-sdm.kelola-konfigurasi'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
        $role->givePermissionTo(['kehadiran-sdm.view', 'kehadiran-sdm.kelola-konfigurasi']);

        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $user->assignRole($role);

        return $user;
    }
}

it('lets an admin_sdm create a lembaga-scoped policy for a jenis_ptk category', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = actingAsAdminSdmPolicy($lembaga);

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.policy.store'), [
        'kategori_tipe' => 'guru', 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:00', 'toleransi_menit' => 15,
    ])->assertRedirect();

    expect(AttendancePolicy::where('lembaga_id', $lembaga->id)->where('jenis_ptk', 'guru_kelas')->exists())->toBeTrue();
});

it('lets an admin_sdm create a lembaga-scoped policy for a jenis_karyawan_id category with hari_kerja override', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $jenisKaryawan = JenisKaryawanMaster::factory()->create();
    $admin = actingAsAdminSdmPolicy($lembaga);

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.policy.store'), [
        'kategori_tipe' => 'karyawan', 'jenis_karyawan_id' => $jenisKaryawan->id,
        'jam_masuk' => '18:00', 'toleransi_menit' => 10, 'hari_kerja' => [0, 1, 2, 3, 4, 5, 6],
    ])->assertRedirect();

    $policy = AttendancePolicy::where('lembaga_id', $lembaga->id)->where('jenis_karyawan_id', $jenisKaryawan->id)->first();
    expect($policy)->not->toBeNull();
    expect($policy->hari_kerja)->toBe([0, 1, 2, 3, 4, 5, 6]);
});

it('rejects creating a duplicate policy for the same category and scope', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = actingAsAdminSdmPolicy($lembaga);
    AttendancePolicy::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:00', 'toleransi_menit' => 0]);

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.policy.store'), [
        'kategori_tipe' => 'guru', 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:30', 'toleransi_menit' => 5,
    ])->assertSessionHasErrors('kategori_tipe');

    expect(AttendancePolicy::where('lembaga_id', $lembaga->id)->where('jenis_ptk', 'guru_kelas')->count())->toBe(1);
});

it('rejects an admin_sdm (scope_level lembaga) trying to create a national policy', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = actingAsAdminSdmPolicy($lembaga);

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.policy.store'), [
        'kategori_tipe' => 'guru', 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:00', 'toleransi_menit' => 0, 'is_nasional' => true,
    ])->assertForbidden();

    expect(AttendancePolicy::whereNull('lembaga_id')->where('jenis_ptk', 'guru_kelas')->exists())->toBeFalse();
});

it('lets an admin_sdm update the jam_masuk and toleransi of an existing policy without changing its category', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = actingAsAdminSdmPolicy($lembaga);
    $policy = AttendancePolicy::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:00', 'toleransi_menit' => 0]);

    $this->actingAs($admin)->put(route('admin.kehadiran-sdm.policy.update', $policy), [
        'jam_masuk' => '07:30', 'toleransi_menit' => 20,
    ])->assertRedirect();

    $policy->refresh();
    expect($policy->jam_masuk)->toBe('07:30:00');
    expect($policy->toleransi_menit)->toBe(20);
    expect($policy->jenis_ptk)->toBe('guru_kelas');
});

it('rejects an admin without kehadiran-sdm.kelola-konfigurasi permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $noPermissionUser = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($noPermissionUser)->post(route('admin.kehadiran-sdm.policy.store'), [
        'kategori_tipe' => 'guru', 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:00', 'toleransi_menit' => 0,
    ])->assertForbidden();
});
