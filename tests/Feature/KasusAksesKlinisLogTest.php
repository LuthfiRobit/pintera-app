<?php
// tests/Feature/KasusAksesKlinisLogTest.php

use App\Enums\StatusKasus;
use App\Models\Guru;
use App\Domains\Kasus\Models\Kasus;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function actingAsKasusKonselor(Lembaga $lembaga): array
{
    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('kasus.view');

    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);
    $guru = Guru::withoutGlobalScopes()->create([
        'user_id' => $user->id,
        'lembaga_id' => $lembaga->id,
        'nik' => fake()->unique()->numerify('################'),
        'nama' => 'Konselor Test',
        'nip' => '1234567890',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_bk',
    ]);

    return [$user, $guru];
}

it('logs an akses_klinis activity when the assigned konselor opens the kasus detail page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    [$user, $guru] = actingAsKasusKonselor($lembaga);
    $kasus = Kasus::factory()->create([
        'siswa_id' => $siswa->id,
        'lembaga_id' => $lembaga->id,
        'status' => StatusKasus::Berjalan,
        'konselor_guru_id' => $guru->id,
    ]);

    expect(Activity::where('log_name', 'akses_klinis')->count())->toBe(0);

    $this->actingAs($user)->get(route('kasus.show', $kasus))->assertOk();

    expect(Activity::where('log_name', 'akses_klinis')->count())->toBe(1);
    $log = Activity::where('log_name', 'akses_klinis')->first();
    expect($log->causer_id)->toBe($user->id);
    expect($log->subject_id)->toBe($kasus->id);
    expect($log->subject_type)->toBe(Kasus::class);
});

it('does NOT log akses_klinis for a user who is denied access to the kasus', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create([
        'siswa_id' => $siswa->id,
        'lembaga_id' => $lembaga->id,
        'status' => StatusKasus::Berjalan,
    ]);

    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('kasus.view');
    $strangerUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $strangerUser->assignRole($role);

    $this->actingAs($strangerUser)->get(route('kasus.show', $kasus))->assertNotFound();

    expect(Activity::where('log_name', 'akses_klinis')->count())->toBe(0);
});
