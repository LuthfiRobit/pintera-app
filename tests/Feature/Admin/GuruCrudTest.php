<?php

use App\Domains\Identity\Models\Person;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

// Identity data (nama, nik, ...) now lives on Person, not on the guru legacy columns (no
// dual-write via CreatePersonAction/UpdatePersonAction), so tests created through the
// controller must look Guru up via its person relation instead of `Guru::where('nama', ...)`.
function findGuruByNama(string $nama): ?Guru
{
    $person = Person::withoutGlobalScopes()->where('nama_lengkap', $nama)->first();

    return $person ? Guru::where('person_id', $person->id)->first() : null;
}

function actingAsGuruManager(Lembaga $lembaga): User
{
    foreach (['guru.view', 'guru.create', 'guru.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['guru.view', 'guru.create', 'guru.edit']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

function guruFormPayload(array $overrides = []): array
{
    return array_merge([
        'nik' => '3201234567891234',
        'nip' => '198501012010011001',
        'nama' => 'Guru Baru',
        'email' => 'guru.baru@permata.sch.id',
        'jenis_kelamin' => 'P',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ], $overrides);
}

it('denies access to a user without guru.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.guru.index'))->assertForbidden();
});

it('creates both a User account and a Guru profile in one submit, with NIP as the hashed password', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembaga);
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $this->actingAs($manager)->post(route('admin.guru.store'), guruFormPayload())
        ->assertRedirect(route('admin.guru.index'));

    $guru = findGuruByNama('Guru Baru');
    expect($guru)->not->toBeNull();
    expect($guru->lembaga_id)->toBe($lembaga->id);
    expect($guru->status_aktif)->toBe('aktif');

    $user = $guru->user;
    expect($user)->not->toBeNull();
    expect($user->email)->toBe('guru.baru@permata.sch.id');
    expect($user->lembaga_id)->toBe($lembaga->id);
    expect(Hash::check('198501012010011001', $user->password))->toBeTrue();
    expect($user->hasRole('guru'))->toBeTrue();
});

it('rejects creating a guru without a NIP', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembaga);

    $this->actingAs($manager)->post(route('admin.guru.store'), guruFormPayload(['nip' => '']))
        ->assertSessionHasErrors('nip');

    expect(findGuruByNama('Guru Baru') !== null)->toBeFalse();
});

it('rejects creating a guru with an email already used by another account', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembaga);
    User::factory()->create(['email' => 'guru.baru@permata.sch.id']);

    $this->actingAs($manager)->post(route('admin.guru.store'), guruFormPayload())
        ->assertSessionHasErrors('email');

    expect(findGuruByNama('Guru Baru') !== null)->toBeFalse();
});

it('shows a friendly validation error instead of a 500 when creating a guru with a duplicate NIK', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembaga);
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $this->actingAs($manager)->post(route('admin.guru.store'), guruFormPayload())->assertRedirect();

    $this->actingAs($manager)->post(route('admin.guru.store'), guruFormPayload([
        'nama' => 'Guru Kedua',
        'email' => 'guru.kedua@permata.sch.id',
    ]))->assertSessionHasErrors('nik');

    expect(findGuruByNama('Guru Kedua') !== null)->toBeFalse();
});

it('only lists guru belonging to the acting lembaga-scoped manager\'s own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembagaA);

    Guru::factory()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembagaA->id])->id,
        'lembaga_id' => $lembagaA->id,
        'nik' => '3201234567895555',
        'nama' => 'Guru Lembaga A',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    Guru::factory()->create([
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

it('filters the index by search, jenis_ptk, and status_aktif', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembaga);

    Guru::factory()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id, 'nik' => '3201234567897777', 'nama' => 'Budi Santoso',
        'jenis_kelamin' => 'L', 'jenis_ptk' => 'guru_mapel', 'status_kepegawaian' => 'PNS', 'status_aktif' => 'aktif',
    ]);
    Guru::factory()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id, 'nik' => '3201234567898888', 'nama' => 'Siti Rahmawati',
        'jenis_kelamin' => 'P', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY', 'status_aktif' => 'non_aktif',
    ]);

    $bySearch = $this->actingAs($manager)->get(route('admin.guru.index', ['search' => 'Budi']));
    $bySearch->assertSee('Budi Santoso')->assertDontSee('Siti Rahmawati');

    $byJenisPtk = $this->actingAs($manager)->get(route('admin.guru.index', ['jenis_ptk' => 'guru_kelas']));
    $byJenisPtk->assertSee('Siti Rahmawati')->assertDontSee('Budi Santoso');

    $byStatus = $this->actingAs($manager)->get(route('admin.guru.index', ['status_aktif' => 'non_aktif']));
    $byStatus->assertSee('Siti Rahmawati')->assertDontSee('Budi Santoso');
});

it('updates guru profile fields without changing the linked User password', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembaga);

    $user = User::factory()->create(['lembaga_id' => $lembaga->id, 'email' => 'lama@permata.sch.id']);
    $originalHash = $user->password;
    $guru = Guru::factory()->create([
        'user_id' => $user->id, 'lembaga_id' => $lembaga->id, 'nik' => '3201234567898899',
        'nip' => '198001011990011001', 'nama' => 'Guru Uji Update', 'email' => 'lama@permata.sch.id',
        'jenis_kelamin' => 'L', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
    ]);

    $this->actingAs($manager)->put(route('admin.guru.update', $guru), guruFormPayload([
        'nama' => 'Guru Uji Update Baru',
        'email' => 'baru@permata.sch.id',
        'nip' => '999999999999999999',
    ]))->assertRedirect(route('admin.guru.index'));

    expect($guru->fresh()->nama)->toBe('Guru Uji Update Baru');
    expect($guru->fresh()->nip)->toBe('999999999999999999');
    expect($user->fresh()->email)->toBe('baru@permata.sch.id');
    expect($user->fresh()->password)->toBe($originalHash);
});

it('changes status_aktif via the dedicated status action, rejecting values outside the 4-state enum', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembaga);

    $guru = Guru::factory()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id, 'nik' => '3201234567899900', 'nama' => 'Guru Status',
        'jenis_kelamin' => 'L', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY', 'status_aktif' => 'aktif',
    ]);

    $this->actingAs($manager)->patch(route('admin.guru.update-status', $guru), ['status_aktif' => 'mutasi'])
        ->assertRedirect(route('admin.guru.index'));
    expect($guru->fresh()->status_aktif)->toBe('mutasi');

    $this->actingAs($manager)->patch(route('admin.guru.update-status', $guru), ['status_aktif' => 'not_a_real_status'])
        ->assertSessionHasErrors('status_aktif');
});

it('deactivates the linked User account when status changes away from aktif, and reactivates it on aktif', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembaga);

    $user = User::factory()->create(['lembaga_id' => $lembaga->id, 'is_active' => true]);
    $guru = Guru::factory()->create([
        'user_id' => $user->id, 'lembaga_id' => $lembaga->id, 'nik' => '3201234567899911',
        'nama' => 'Guru Pensiun', 'jenis_kelamin' => 'L', 'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY', 'status_aktif' => 'aktif',
    ]);

    $this->actingAs($manager)->patch(route('admin.guru.update-status', $guru), ['status_aktif' => 'pensiun']);
    expect($user->fresh()->is_active)->toBeFalse();

    $this->actingAs($manager)->patch(route('admin.guru.update-status', $guru), ['status_aktif' => 'aktif']);
    expect($user->fresh()->is_active)->toBeTrue();
});

it('rejects creating or updating a guru with a NUPTK already used by another guru, without a 500', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembaga);
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $this->actingAs($manager)->post(route('admin.guru.store'), guruFormPayload(['nuptk' => '1234567890123456']))
        ->assertRedirect();

    $this->actingAs($manager)->post(route('admin.guru.store'), guruFormPayload([
        'nama' => 'Guru Kedua', 'email' => 'guru.kedua@permata.sch.id', 'nuptk' => '1234567890123456',
    ]))->assertSessionHasErrors('nuptk');

    expect(findGuruByNama('Guru Kedua') !== null)->toBeFalse();
});

it('allows creating a guru with a blank NUPTK even when other guru already have a blank NUPTK', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembaga);
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $this->actingAs($manager)->post(route('admin.guru.store'), guruFormPayload())->assertRedirect();

    $this->actingAs($manager)->post(route('admin.guru.store'), guruFormPayload([
        'nik' => '3201234567891299', 'nama' => 'Guru Kedua Tanpa Nuptk', 'email' => 'guru.kedua-nonuptk@permata.sch.id',
    ]))->assertSessionDoesntHaveErrors('nuptk');

    expect(findGuruByNama('Guru Kedua Tanpa Nuptk') !== null)->toBeTrue();
});

it('denies status change without guru.edit permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id, 'nik' => '3201234567899901', 'nama' => 'Guru Lain',
        'jenis_kelamin' => 'L', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
    ]);

    $this->actingAs(User::factory()->create(['lembaga_id' => $lembaga->id]))
        ->patch(route('admin.guru.update-status', $guru), ['status_aktif' => 'non_aktif'])
        ->assertForbidden();
});

it('menolak actor yayasan dengan active_lembaga_id stale (lembaga di luar yayasannya) saat membuat data guru', function () {
    $yayasanSaya = Yayasan::factory()->create();
    $yayasanLain = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    Permission::firstOrCreate(['name' => 'guru.create', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasanSaya->id]);
    $manager->givePermissionTo('guru.create');
    $role = Role::firstOrCreate(['name' => 'yayasan_uji_guru', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembagaLain->id]);

    $response = $this->actingAs($manager)->post(route('admin.guru.store'), guruFormPayload(['nama' => 'Guru Uji Stale', 'email' => 'guru.uji.stale@example.test']));

    $response->assertSessionHasErrors('lembaga_id');
    expect(findGuruByNama('Guru Uji Stale'))->toBeNull();
});
