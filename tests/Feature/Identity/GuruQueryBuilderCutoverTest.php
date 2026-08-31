<?php

use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsGuruManagerForQueryTest(Lembaga $lembaga): User
{
    Permission::firstOrCreate(['name' => 'guru.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['guru.view']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('searches guru by person nama_lengkap, nip, and nuptk', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManagerForQueryTest($lembaga);

    $guru1 = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Ahmad Dahlan', 'nip' => '19800101']);
    $guru2 = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Budi Utomo', 'nuptk' => '99887766']);
    $guru3 = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Chairil Anwar']);

    $response = $this->actingAs($manager)->get(route('admin.guru.index', ['search' => 'Dahlan']));
    $response->assertOk();
    $response->assertSee('Ahmad Dahlan');
    $response->assertDontSee('Budi Utomo');

    $response2 = $this->actingAs($manager)->get(route('admin.guru.index', ['search' => '99887766']));
    $response2->assertOk();
    $response2->assertSee('Budi Utomo');
    $response2->assertDontSee('Chairil Anwar');
});

it('orders guru by person nama_lengkap correctly', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $g1 = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Zulfa']);
    $g2 = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Adam']);
    $g3 = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Maya']);

    $ordered = Guru::where('lembaga_id', $lembaga->id)->orderByNama()->get();
    expect($ordered->pluck('nama')->toArray())->toBe(['Adam', 'Maya', 'Zulfa']);
});
