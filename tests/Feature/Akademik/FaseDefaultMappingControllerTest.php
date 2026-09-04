<?php

declare(strict_types=1);

use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function buatUserDenganRole(string $roleName, ?int $lembagaId = null, ?int $yayasanId = null): User
{
    foreach (['fase-mapping.view', 'fase-mapping.create', 'fase-mapping.edit', 'fase-mapping.delete'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web'], ['scope_level' => match ($roleName) {
        'operator_akademik' => 'lembaga',
        'yayasan_super_admin' => 'yayasan',
        'platform_super_admin' => 'platform',
        default => 'lembaga',
    }]);

    if ($roleName === 'operator_akademik') {
        $role->givePermissionTo(['fase-mapping.view', 'fase-mapping.create', 'fase-mapping.edit', 'fase-mapping.delete']);
    }
    if (in_array($roleName, ['yayasan_super_admin', 'platform_super_admin'], true)) {
        $role->givePermissionTo(Permission::query()->pluck('name')->all());
    }

    $user = User::factory()->create(['lembaga_id' => $lembagaId, 'yayasan_id' => $yayasanId]);
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

it('memakai session active_lembaga_id untuk lembaga_id mapping yayasan, mengabaikan lembaga_id yang dikirim di body', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $yayasan = Yayasan::factory()->create();
    $lembagaAktif = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = buatUserDenganRole('yayasan_super_admin', null, $yayasan->id);
    session(['active_lembaga_id' => $lembagaAktif->id]);

    $this->actingAs($user)->post(route('admin.fase-mapping.store'), [
        'lembaga_id' => $lembagaLain->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'fase_id' => $fase->id,
    ])->assertRedirect(route('admin.fase-mapping.index'));

    expect(FaseDefaultMapping::first()->lembaga_id)->toBe($lembagaAktif->id);
});

it('menolak yayasan membuat mapping global -- hanya platform yang boleh', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $yayasan = Yayasan::factory()->create();
    $lembagaAktif = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = buatUserDenganRole('yayasan_super_admin', null, $yayasan->id);
    session(['active_lembaga_id' => $lembagaAktif->id]);

    $this->actingAs($user)->post(route('admin.fase-mapping.store'), [
        'lembaga_id' => '',
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'fase_id' => $fase->id,
    ]);

    expect(FaseDefaultMapping::first()?->lembaga_id)->toBe($lembagaAktif->id);
});

it('platform BISA membuat mapping global', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $user = buatUserDenganRole('platform_super_admin');

    $this->actingAs($user)->post(route('admin.fase-mapping.store'), [
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'fase_id' => $fase->id,
    ])->assertRedirect(route('admin.fase-mapping.index'));

    expect(FaseDefaultMapping::first()->lembaga_id)->toBeNull();
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

it('menolak yayasan A menghapus mapping milik lembaga di yayasan B', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $yayasanA = Yayasan::factory()->create();
    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $mapping = FaseDefaultMapping::create(['lembaga_id' => $lembagaB->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);
    $userA = buatUserDenganRole('yayasan_super_admin', null, $yayasanA->id);

    $this->actingAs($userA)->delete(route('admin.fase-mapping.destroy', $mapping))->assertForbidden();

    expect(FaseDefaultMapping::find($mapping->id))->not->toBeNull();
});

it('mengizinkan yayasan menghapus mapping milik lembaga di yayasannya sendiri', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $yayasan = Yayasan::factory()->create();
    $lembagaSendiri = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $mapping = FaseDefaultMapping::create(['lembaga_id' => $lembagaSendiri->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);
    $user = buatUserDenganRole('yayasan_super_admin', null, $yayasan->id);

    $this->actingAs($user)->delete(route('admin.fase-mapping.destroy', $mapping))->assertRedirect(route('admin.fase-mapping.index'));

    expect(FaseDefaultMapping::find($mapping->id))->toBeNull();
});

it('yayasan cuma lihat mapping global + milik yayasannya sendiri di index, TIDAK lihat milik yayasan lain', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $yayasanA = Yayasan::factory()->create();
    $yayasanB = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $mappingA = FaseDefaultMapping::create(['lembaga_id' => $lembagaA->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);
    $mappingB = FaseDefaultMapping::create(['lembaga_id' => $lembagaB->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);
    $mappingGlobal = FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SMP', 'tingkat' => '7', 'fase_id' => $fase->id]);
    $userA = buatUserDenganRole('yayasan_super_admin', null, $yayasanA->id);

    $response = $this->actingAs($userA)->get(route('admin.fase-mapping.index'));

    $response->assertViewHas('mappingList', function ($list) use ($mappingA, $mappingB, $mappingGlobal) {
        return $list->contains('id', $mappingA->id)
            && $list->contains('id', $mappingGlobal->id)
            && ! $list->contains('id', $mappingB->id);
    });
});

it('platform TETAP lihat SEMUA mapping lintas yayasan di index', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $yayasanA = Yayasan::factory()->create();
    $yayasanB = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $mappingA = FaseDefaultMapping::create(['lembaga_id' => $lembagaA->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);
    $mappingB = FaseDefaultMapping::create(['lembaga_id' => $lembagaB->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);
    $user = buatUserDenganRole('platform_super_admin');

    $response = $this->actingAs($user)->get(route('admin.fase-mapping.index'));

    $response->assertViewHas('mappingList', function ($list) use ($mappingA, $mappingB) {
        return $list->contains('id', $mappingA->id) && $list->contains('id', $mappingB->id);
    });
});
