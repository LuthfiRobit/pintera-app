<?php

use App\Enums\TipeMataPelajaran;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsMataPelajaranManager(Lembaga $lembaga): User
{
    foreach (['mata-pelajaran.view', 'mata-pelajaran.create', 'mata-pelajaran.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['mata-pelajaran.view', 'mata-pelajaran.create', 'mata-pelajaran.edit']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access to a user without mata-pelajaran.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.mata-pelajaran.index'))->assertForbidden();
});

it('creates a mata pelajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsMataPelajaranManager($lembaga);

    $this->actingAs($manager)->post(route('admin.mata-pelajaran.store'), [
        'nama' => 'Matematika',
        'tipe' => TipeMataPelajaran::Mapel->value,
    ])->assertRedirect(route('admin.mata-pelajaran.index'));

    expect(MataPelajaran::where('nama', 'Matematika')->exists())->toBeTrue();
});

it('only lists mata pelajaran belonging to the acting manager\'s own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsMataPelajaranManager($lembagaA);

    MataPelajaran::create(['lembaga_id' => $lembagaA->id, 'nama' => 'Mapel Lembaga A', 'tipe' => TipeMataPelajaran::Mapel->value]);
    MataPelajaran::withoutGlobalScopes()->create(['lembaga_id' => $lembagaB->id, 'nama' => 'Mapel Lembaga B', 'tipe' => TipeMataPelajaran::Mapel->value]);

    $response = $this->actingAs($manager)->get(route('admin.mata-pelajaran.index'));

    $response->assertSee('Mapel Lembaga A');
    $response->assertDontSee('Mapel Lembaga B');
});

it('updates a mata pelajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsMataPelajaranManager($lembaga);
    $mapel = MataPelajaran::create(['lembaga_id' => $lembaga->id, 'nama' => 'IPA', 'tipe' => TipeMataPelajaran::Mapel->value]);

    $this->actingAs($manager)->put(route('admin.mata-pelajaran.update', $mapel), [
        'nama' => 'Ilmu Pengetahuan Alam',
        'tipe' => TipeMataPelajaran::Mapel->value,
    ])->assertRedirect(route('admin.mata-pelajaran.index'));

    expect($mapel->fresh()->nama)->toBe('Ilmu Pengetahuan Alam');
});
