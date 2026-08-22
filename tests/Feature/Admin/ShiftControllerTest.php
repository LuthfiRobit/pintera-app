<?php
// tests/Feature/Admin/ShiftControllerTest.php

use App\Domains\Sdm\Models\JenisShift;
use App\Domains\Sdm\Models\PenugasanShift;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

if (! function_exists('actingAsAdminSdmShift')) {
    function actingAsAdminSdmShift(Lembaga $lembaga): User
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

it('lets an admin_sdm create a jenis_shift for their own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = actingAsAdminSdmShift($lembaga);

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.jenis-shift.store'), [
        'nama' => 'Shift Pagi', 'jam_masuk' => '06:00', 'jam_pulang' => '14:00',
    ])->assertRedirect();

    expect(JenisShift::where('lembaga_id', $lembaga->id)->where('nama', 'Shift Pagi')->exists())->toBeTrue();
});

it('lets an admin_sdm assign a shift to a guru in their own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisShift = JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Pagi', 'jam_masuk' => '06:00', 'jam_pulang' => '14:00']);
    $admin = actingAsAdminSdmShift($lembaga);

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.penugasan-shift.store'), [
        'pegawai_tipe' => 'guru', 'pegawai_id' => $guru->id, 'jenis_shift_id' => $jenisShift->id,
        'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-09-07',
    ])->assertRedirect();

    expect(PenugasanShift::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeTrue();
});

it('404s when assigning a shift to a guru from a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guruLembagaB = Guru::factory()->create(['lembaga_id' => $lembagaB->id]);
    $jenisShift = JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembagaA->id, 'nama' => 'Shift Pagi', 'jam_masuk' => '06:00', 'jam_pulang' => '14:00']);
    $adminLembagaA = actingAsAdminSdmShift($lembagaA);

    $this->actingAs($adminLembagaA)->post(route('admin.kehadiran-sdm.penugasan-shift.store'), [
        'pegawai_tipe' => 'guru', 'pegawai_id' => $guruLembagaB->id, 'jenis_shift_id' => $jenisShift->id,
        'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-09-07',
    ])->assertNotFound();

    expect(PenugasanShift::where('pegawai_type', Guru::class)->where('pegawai_id', $guruLembagaB->id)->exists())->toBeFalse();
});

it('returns a session error (not a 500) when creating an overlapping shift assignment via the endpoint', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisShift = JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Pagi', 'jam_masuk' => '06:00', 'jam_pulang' => '14:00']);
    $admin = actingAsAdminSdmShift($lembaga);
    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.penugasan-shift.store'), [
        'pegawai_tipe' => 'guru', 'pegawai_id' => $guru->id, 'jenis_shift_id' => $jenisShift->id,
        'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-09-07',
    ]);

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.penugasan-shift.store'), [
        'pegawai_tipe' => 'guru', 'pegawai_id' => $guru->id, 'jenis_shift_id' => $jenisShift->id,
        'tanggal_mulai' => '2026-09-05', 'tanggal_selesai' => '2026-09-10',
    ])->assertSessionHasErrors('tanggal_mulai');

    expect(PenugasanShift::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->count())->toBe(1);
});

it('rejects an admin without kehadiran-sdm.kelola-konfigurasi permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $noPermissionUser = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($noPermissionUser)->post(route('admin.kehadiran-sdm.jenis-shift.store'), [
        'nama' => 'Shift Pagi', 'jam_masuk' => '06:00', 'jam_pulang' => '14:00',
    ])->assertForbidden();
});
