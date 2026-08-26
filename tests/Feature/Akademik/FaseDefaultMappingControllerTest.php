<?php

use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function buatUserDenganRole(string $roleName, ?int $lembagaId = null): User
{
    foreach (['fase-mapping.view', 'fase-mapping.create', 'fase-mapping.edit', 'fase-mapping.delete'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web'], ['scope_level' => match ($roleName) {
        'operator_akademik' => 'lembaga',
        'yayasan_super_admin' => 'yayasan',
        default => 'lembaga',
    }]);

    if ($roleName === 'operator_akademik') {
        $role->givePermissionTo(['fase-mapping.view', 'fase-mapping.create', 'fase-mapping.edit', 'fase-mapping.delete']);
    }
    if ($roleName === 'yayasan_super_admin') {
        $role->givePermissionTo(Permission::query()->pluck('name')->all());
    }

    $user = User::factory()->create(['lembaga_id' => $lembagaId]);
    $user->assignRole($role);

    return $user;
}

it('lets a lembaga-scope user create a mapping that is force-scoped to their own lembaga even if a different lembaga_id is sent', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $lembagaSendiri = Lembaga::factory()->create();
    $lembagaLain = Lembaga::factory()->create();
    $user = buatUserDenganRole('operator_akademik', $lembagaSendiri->id);

    $this->actingAs($user)->post(route('admin.fase-mapping.store'), [
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'fase_id' => $fase->id,
        'lembaga_id' => $lembagaLain->id, // dicoba dipaksakan, harus diabaikan
    ])->assertRedirect(route('admin.fase-mapping.index'));

    $mapping = FaseDefaultMapping::first();
    expect($mapping->lembaga_id)->toBe($lembagaSendiri->id);
});

it('rejects a lembaga-scope user trying to create a platform-wide mapping (lembaga_id null)', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $lembaga = Lembaga::factory()->create();
    $user = buatUserDenganRole('operator_akademik', $lembaga->id);

    $this->actingAs($user)->post(route('admin.fase-mapping.store'), [
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'fase_id' => $fase->id,
        'lembaga_id' => '',
    ]);

    // Server memaksa lembaga_id ke lembaga user (bukan null) -- lihat controller.
    expect(FaseDefaultMapping::first()->lembaga_id)->toBe($lembaga->id);
});

it('lets a yayasan-scope user create a platform-wide mapping', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $user = buatUserDenganRole('yayasan_super_admin');

    $this->actingAs($user)->post(route('admin.fase-mapping.store'), [
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'fase_id' => $fase->id,
        'lembaga_id' => '',
    ])->assertRedirect(route('admin.fase-mapping.index'));

    expect(FaseDefaultMapping::first()->lembaga_id)->toBeNull();
});

it('lets a yayasan-scope user create a mapping for any specific lembaga', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $lembagaTarget = Lembaga::factory()->create();
    $user = buatUserDenganRole('yayasan_super_admin');

    $this->actingAs($user)->post(route('admin.fase-mapping.store'), [
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'fase_id' => $fase->id,
        'lembaga_id' => $lembagaTarget->id,
    ])->assertRedirect(route('admin.fase-mapping.index'));

    expect(FaseDefaultMapping::first()->lembaga_id)->toBe($lembagaTarget->id);
});

it('rejects a duplicate mapping in the same scope with a clear validation error', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $lembaga = Lembaga::factory()->create();
    $user = buatUserDenganRole('operator_akademik', $lembaga->id);
    FaseDefaultMapping::create(['lembaga_id' => $lembaga->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);

    $this->actingAs($user)->post(route('admin.fase-mapping.store'), [
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'fase_id' => $fase->id,
    ])->assertSessionHasErrors('bentuk_pendidikan');

    expect(FaseDefaultMapping::count())->toBe(1);
});

it('forbids a lembaga-scope user from editing another lembaga\'s mapping', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $lembagaA = Lembaga::factory()->create();
    $lembagaB = Lembaga::factory()->create();
    $mapping = FaseDefaultMapping::create(['lembaga_id' => $lembagaA->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);
    $userB = buatUserDenganRole('operator_akademik', $lembagaB->id);

    $this->actingAs($userB)->put(route('admin.fase-mapping.update', $mapping), [
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '2',
        'fase_id' => $fase->id,
    ])->assertForbidden();

    expect(FaseDefaultMapping::find($mapping->id)->tingkat)->toBe('1');
});

it('forbids a lembaga-scope user from deleting a platform-wide mapping', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $mapping = FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);
    $lembaga = Lembaga::factory()->create();
    $user = buatUserDenganRole('operator_akademik', $lembaga->id);

    $this->actingAs($user)->delete(route('admin.fase-mapping.destroy', $mapping))->assertForbidden();

    expect(FaseDefaultMapping::find($mapping->id))->not->toBeNull();
});

it('forbids a user without fase-mapping permission from accessing the index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.fase-mapping.index'))->assertForbidden();
});

it('lets a yayasan-scope user delete any lembaga\'s mapping', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $lembaga = Lembaga::factory()->create();
    $mapping = FaseDefaultMapping::create(['lembaga_id' => $lembaga->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);
    $user = buatUserDenganRole('yayasan_super_admin');

    $this->actingAs($user)->delete(route('admin.fase-mapping.destroy', $mapping))->assertRedirect(route('admin.fase-mapping.index'));

    expect(FaseDefaultMapping::find($mapping->id))->toBeNull();
});
