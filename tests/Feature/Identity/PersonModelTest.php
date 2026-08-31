<?php

use App\Domains\Identity\Models\Person;
use App\Models\Lembaga;
use App\Models\Role;
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
