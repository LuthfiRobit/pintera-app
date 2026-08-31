<?php

use App\Domains\Identity\Models\Person;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;

it('scopes OrangTua to the acting lembaga-level admin yayasan', function () {
    $yayasanA = Yayasan::factory()->create();
    $yayasanB = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);

    OrangTua::factory()->create(['person_id' => Person::factory()->create(['yayasan_id' => $yayasanA->id])->id]);
    OrangTua::factory()->create(['person_id' => Person::factory()->create(['yayasan_id' => $yayasanB->id])->id]);

    $admin = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $this->actingAs($admin);

    expect(OrangTua::count())->toBe(1);
});

it('scopes OrangTua to the acting yayasan-level admin own yayasan_id, closing the cross-tenant leak', function () {
    $yayasanA = Yayasan::factory()->create();
    $yayasanB = Yayasan::factory()->create();

    OrangTua::factory()->create(['person_id' => Person::factory()->create(['yayasan_id' => $yayasanA->id])->id]);
    OrangTua::factory()->create(['person_id' => Person::factory()->create(['yayasan_id' => $yayasanB->id])->id]);

    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $admin = User::factory()->create(['yayasan_id' => $yayasanA->id]);
    $admin->assignRole($role);
    $this->actingAs($admin);

    expect(OrangTua::count())->toBe(1);
});

it('does not scope OrangTua for platform-level actors', function () {
    OrangTua::factory()->create(['person_id' => Person::factory()->create()->id]);
    OrangTua::factory()->create(['person_id' => Person::factory()->create()->id]);

    $role = Role::firstOrCreate(['name' => 'platform_super_admin', 'guard_name' => 'web'], ['scope_level' => 'platform']);
    $admin = User::factory()->create();
    $admin->assignRole($role);
    $this->actingAs($admin);

    expect(OrangTua::count())->toBe(2);
});
