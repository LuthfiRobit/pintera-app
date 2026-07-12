<?php

use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsGuruManager(Lembaga $lembaga): User
{
    Permission::firstOrCreate(['name' => 'manage-guru', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('manage-guru');

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access to a user without manage-guru permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.guru.index'))->assertForbidden();
});

it('only offers users with the guru role and no existing profile when creating', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembaga);

    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $eligible = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $eligible->assignRole('guru');

    $notGuru = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($manager)->get(route('admin.guru.create'));

    $response->assertOk();
    $response->assertViewHas('eligibleUsers', function ($users) use ($eligible, $notGuru) {
        return $users->contains('id', $eligible->id) && ! $users->contains('id', $notGuru->id);
    });
});

it('creates a guru profile for an eligible user', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembaga);

    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $eligible = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $eligible->assignRole('guru');

    $this->actingAs($manager)->post(route('admin.guru.store'), [
        'user_id' => $eligible->id,
        'nik' => '3201234567891234',
        'nama' => 'Guru Baru',
        'jenis_kelamin' => 'P',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ])->assertRedirect(route('admin.guru.index'));

    expect(Guru::where('user_id', $eligible->id)->exists())->toBeTrue();
});

it('only lists guru belonging to the acting lembaga-scoped manager\'s own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembagaA);

    Guru::create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembagaA->id])->id,
        'lembaga_id' => $lembagaA->id,
        'nik' => '3201234567895555',
        'nama' => 'Guru Lembaga A',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    Guru::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembagaB->id])->id,
        'lembaga_id' => $lembagaB->id,
        'nik' => '3201234567896666',
        'nama' => 'Guru Lembaga B',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    $response = $this->actingAs($manager)->get(route('admin.guru.index'));

    $response->assertSee('Guru Lembaga A');
    $response->assertDontSee('Guru Lembaga B');
});

it('shows a friendly validation error instead of a 500 when creating a guru with a duplicate NIK', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembaga);

    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $firstUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $firstUser->assignRole('guru');

    Guru::create([
        'user_id' => $firstUser->id,
        'lembaga_id' => $lembaga->id,
        'nik' => '3201234567899999',
        'nama' => 'Guru Pertama',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    $secondUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $secondUser->assignRole('guru');

    $this->actingAs($manager)->post(route('admin.guru.store'), [
        'user_id' => $secondUser->id,
        'nik' => '3201234567899999',
        'nama' => 'Guru Kedua',
        'jenis_kelamin' => 'P',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ])->assertSessionHasErrors('nik');

    expect(Guru::where('user_id', $secondUser->id)->exists())->toBeFalse();
});

it('allows updating a guru while keeping their own unchanged NIK', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembaga);

    $guru = Guru::create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id,
        'nik' => '3201234567898888',
        'nama' => 'Guru Uji Update',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    $this->actingAs($manager)->put(route('admin.guru.update', $guru), [
        'nik' => '3201234567898888',
        'nama' => 'Guru Uji Update Baru',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ])->assertRedirect(route('admin.guru.index'));

    expect($guru->fresh()->nama)->toBe('Guru Uji Update Baru');
});
