<?php

// tests/Feature/Admin/AttendanceConfigurationControllerTest.php

use App\Domains\Identity\Models\Person;
use App\Domains\Sdm\Models\AttendanceMethodConfiguration;
use App\Domains\Sdm\Models\AttendancePoint;
use App\Domains\Sdm\Models\AttendancePolicy;
use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

if (! function_exists('actingAsAdminSdm')) {
    function actingAsAdminSdm(Lembaga $lembaga): User
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

it('lets an admin_sdm enable the qr method for their own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = actingAsAdminSdm($lembaga);

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.konfigurasi.metode'), [
        'method' => 'qr', 'is_enabled' => '1',
    ])->assertRedirect();

    expect(AttendanceMethodConfiguration::where('lembaga_id', $lembaga->id)->where('method', 'qr')->first()?->is_enabled)->toBeTrue();
});

it('lets an admin_sdm add an attendance point for their own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = actingAsAdminSdm($lembaga);

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.titik.store'), ['nama' => 'Gerbang Utama'])
        ->assertRedirect();

    expect(AttendancePoint::where('lembaga_id', $lembaga->id)->where('nama', 'Gerbang Utama')->exists())->toBeTrue();
});

it('rejects an admin without kehadiran-sdm.kelola-konfigurasi permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $noPermissionUser = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($noPermissionUser)->post(route('admin.kehadiran-sdm.titik.store'), ['nama' => 'Gerbang Utama'])
        ->assertForbidden();
});

it('shows the yayasan-level default method configuration (lembaga_id null) to a lembaga-scoped admin_sdm', function () {
    // Regression guard: TenantScope forces `lembaga_id = actingUser->lembaga_id` as a
    // top-level AND for a scope_level:lembaga actor, so a naive query would never surface
    // the yayasan default row (lembaga_id IS NULL) no matter what OR clause is added on top.
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = actingAsAdminSdm($lembaga);

    AttendanceMethodConfiguration::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'method' => 'qr', 'is_enabled' => true,
    ]);

    $this->actingAs($admin)->get(route('admin.kehadiran-sdm.konfigurasi.index'))
        ->assertOk()
        ->assertViewHas('konfigurasi', function ($konfigurasi) {
            return $konfigurasi->contains(fn ($row) => $row->method->value === 'qr' && $row->lembaga_id === null && $row->is_enabled === true);
        });
});

function actingAsAdminSdmYayasan(Yayasan $yayasan): User
{
    foreach (['kehadiran-sdm.view', 'kehadiran-sdm.kelola-konfigurasi'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_sdm_yayasan_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['kehadiran-sdm.view', 'kehadiran-sdm.kelola-konfigurasi']);

    $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $user->assignRole($role);

    return $user;
}

it('shows AttendancePolicy from ALL lembaga plus the national one when yayasan scope has no active lembaga selected', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaX = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaY = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    AttendancePolicy::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'jenis_ptk' => 'guru_kelas',
        'jam_masuk' => '07:00', 'jam_pulang' => '15:00', 'toleransi_menit' => 15, 'hari_kerja' => ['senin', 'selasa'],
    ]);
    AttendancePolicy::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembagaX->id, 'jenis_ptk' => 'guru_kelas',
        'jam_masuk' => '06:45', 'jam_pulang' => '15:00', 'toleransi_menit' => 10, 'hari_kerja' => ['senin'],
    ]);
    AttendancePolicy::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembagaY->id, 'jenis_ptk' => 'guru_kelas',
        'jam_masuk' => '07:15', 'jam_pulang' => '15:30', 'toleransi_menit' => 20, 'hari_kerja' => ['selasa'],
    ]);

    $user = actingAsAdminSdmYayasan($yayasan);

    $response = $this->actingAs($user)->get(route('admin.kehadiran-sdm.konfigurasi.index'));

    $response->assertOk();
    $response->assertViewHas('policyList', function ($policyList) {
        return $policyList->count() === 3;
    });
});

it('includes pool karyawan in the karyawanList picker when a lembaga is active', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = actingAsAdminSdm($lembaga);

    $person = Person::factory()->create(['yayasan_id' => $yayasan->id]);
    $karyawanPool = Karyawan::create([
        'person_id' => $person->id, 'yayasan_id' => $yayasan->id, 'lembaga_id' => null,
        'jenis_karyawan_id' => JenisKaryawanMaster::factory()->create(['yayasan_id' => $yayasan->id])->id,
        'status_aktif' => 'aktif',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.kehadiran-sdm.konfigurasi.index'));

    $response->assertOk();
    $ids = collect($response->viewData('karyawanList'))->pluck('id');
    expect($ids)->toContain((string) $karyawanPool->id);
});
