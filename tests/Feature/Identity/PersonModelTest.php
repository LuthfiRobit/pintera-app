<?php

use App\Domains\Identity\Models\Person;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;

it('computes nik_hash on save', function () {
    $person = Person::factory()->create(['nik' => '1234567890123456']);

    expect($person->nik_hash)->toBe(hash('sha256', '1234567890123456'));
});

it('scopes persons to the acting yayasan_id like other tenant models', function () {
    $yayasanA = Yayasan::factory()->create();
    $yayasanB = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);

    Person::factory()->create(['yayasan_id' => $yayasanA->id]);
    Person::factory()->create(['yayasan_id' => $yayasanB->id]);

    $admin = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $this->actingAs($admin);

    expect(Person::count())->toBe(1);
});

it('bypasses the scope entirely for platform-level actors', function () {
    $yayasanA = Yayasan::factory()->create();
    $yayasanB = Yayasan::factory()->create();

    Person::factory()->create(['yayasan_id' => $yayasanA->id]);
    Person::factory()->create(['yayasan_id' => $yayasanB->id]);

    $role = Role::firstOrCreate(['name' => 'platform_super_admin', 'guard_name' => 'web'], ['scope_level' => 'platform']);
    $admin = User::factory()->create();
    $admin->assignRole($role);
    $this->actingAs($admin);

    expect(Person::count())->toBe(2);
});

it('fails closed (returns zero rows) when the acting user has no resolvable yayasan_id', function () {
    $yayasan = Yayasan::factory()->create();
    Person::factory()->create(['yayasan_id' => $yayasan->id]);

    // A user with no lembaga_id and no yayasan_id of their own -- yayasan_id cannot be resolved.
    $admin = User::factory()->create(['lembaga_id' => null]);
    $this->actingAs($admin);

    expect(Person::count())->toBe(0);
});

// Regression tests for the fail-closed bug found via KasusListingTest: siswa- and
// orang_tua-role personal accounts have neither yayasan_id nor lembaga_id set directly
// on their users row (unlike guru/karyawan/staff accounts), so YayasanScope's original
// resolution chain fell through to "fail closed" for them on every Person-backed read.

it('resolves the yayasan boundary for a siswa-role actor via their own Siswa profile', function () {
    $yayasan = Yayasan::factory()->create();
    $otherYayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $siswaUser = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => null]);
    $siswaUser->assignRole(Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']));
    $person = Person::factory()->create(['yayasan_id' => $yayasan->id, 'user_id' => $siswaUser->id, 'nama_lengkap' => 'Siswa Aktor']);
    Siswa::factory()->create(['person_id' => $person->id, 'lembaga_id' => $lembaga->id]);

    Person::factory()->create(['yayasan_id' => $yayasan->id, 'nama_lengkap' => 'Person Sesama Yayasan']);
    Person::factory()->create(['yayasan_id' => $otherYayasan->id, 'nama_lengkap' => 'Person Yayasan Lain']);

    $this->actingAs($siswaUser);

    $names = Person::pluck('nama_lengkap');
    expect($names)->toContain('Siswa Aktor')
        ->toContain('Person Sesama Yayasan')
        ->not->toContain('Person Yayasan Lain');
});

it('resolves the yayasan boundary for an orang_tua-role actor via their linked Siswa, matching the real KasusListingTest bug scenario', function () {
    $yayasan = Yayasan::factory()->create();
    $otherYayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $ortuUser = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => null]);
    $ortuUser->assignRole(Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']));
    $ortuPerson = Person::factory()->create(['yayasan_id' => $yayasan->id, 'user_id' => $ortuUser->id]);
    $orangTua = OrangTua::factory()->create(['person_id' => $ortuPerson->id]);

    $anakPerson = Person::factory()->create(['yayasan_id' => $yayasan->id, 'nama_lengkap' => 'Anak Orang Tua']);
    $anak = Siswa::factory()->create(['person_id' => $anakPerson->id, 'lembaga_id' => $lembaga->id]);
    $anak->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    Person::factory()->create(['yayasan_id' => $otherYayasan->id, 'nama_lengkap' => 'Person Yayasan Lain']);

    $this->actingAs($ortuUser);

    // Real bug scenario: Siswa::person is a plain belongsTo, scoped by YayasanScope --
    // this must resolve, not silently null out $anak->nama_lengkap.
    expect($anak->fresh()->nama_lengkap)->toBe('Anak Orang Tua');

    $names = Person::pluck('nama_lengkap');
    expect($names)->toContain('Anak Orang Tua')
        ->not->toContain('Person Yayasan Lain');
});

it('still fails closed for a siswa-role actor whose account has no linked Siswa row at all', function () {
    $yayasan = Yayasan::factory()->create();
    Person::factory()->create(['yayasan_id' => $yayasan->id]);

    $brokenSiswaUser = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => null]);
    $brokenSiswaUser->assignRole(Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']));
    // No Person/Siswa row linked to this user at all -- profile chain is broken.

    $this->actingAs($brokenSiswaUser);

    expect(Person::count())->toBe(0);
});

it('still fails closed for an orang_tua-role actor with no linked children', function () {
    $yayasan = Yayasan::factory()->create();
    Person::factory()->create(['yayasan_id' => $yayasan->id]);

    $lonelyOrtuUser = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => null]);
    $lonelyOrtuUser->assignRole(Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']));
    $lonelyOrtuPerson = Person::factory()->create(['yayasan_id' => $yayasan->id, 'user_id' => $lonelyOrtuUser->id]);
    OrangTua::factory()->create(['person_id' => $lonelyOrtuPerson->id]);
    // OrangTua row exists but has no siswa_orang_tua pivot rows -- chain still breaks.

    $this->actingAs($lonelyOrtuUser);

    expect(Person::count())->toBe(0);
});
