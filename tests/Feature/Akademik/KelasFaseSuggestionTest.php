<?php

use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function buatUserSuggestion(string $bentukPendidikanLembaga): array
{
    Permission::firstOrCreate(['name' => 'kelas.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'kelas.create', 'guard_name' => 'web']);

    $role = Role::firstOrCreate(['name' => 'operator_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kelas.view', 'kelas.create']);

    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => $bentukPendidikanLembaga]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    return [$user, $lembaga];
}

it('returns suggested fase based on user lembaga bentuk_pendidikan and query param tingkat', function () {
    $faseA = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $faseA->id]);

    [$user, $lembaga] = buatUserSuggestion('SD');

    $response = $this->actingAs($user)->getJson(route('admin.kelas.fase-suggestion', ['tingkat' => '1']));

    $response->assertOk()
        ->assertJson([
            'suggestion' => ['id' => $faseA->id, 'kode' => 'a', 'nama' => 'Fase A'],
        ]);
});

it('returns a null suggestion when no mapping matches', function () {
    [$user, $lembaga] = buatUserSuggestion('SLB');

    $response = $this->actingAs($user)->getJson(route('admin.kelas.fase-suggestion', ['tingkat' => '6']));

    $response->assertOk()->assertJson(['suggestion' => null]);
});

it('requires authenticated user with kelas.view or kelas.create permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson(route('admin.kelas.fase-suggestion', ['tingkat' => '1']))
        ->assertForbidden();
});
