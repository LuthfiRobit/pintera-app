<?php

use App\Models\JamPelajaran;
use App\Models\Lembaga;
use App\Models\PolaJam;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsPolaJamManager(Lembaga $lembaga): User
{
    foreach (['pola-jam.view', 'pola-jam.create', 'jam-pelajaran.create'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['pola-jam.view', 'pola-jam.create', 'jam-pelajaran.create']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access without pola-jam.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.pola-jam.index'))->assertForbidden();
});

it('creates a pola jam', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);

    $this->actingAs($manager)->post(route('admin.pola-jam.store'), [
        'nama' => 'Kelas Tinggi 4-6',
    ])->assertRedirect(route('admin.pola-jam.index'));

    expect(PolaJam::where('nama', 'Kelas Tinggi 4-6')->exists())->toBeTrue();
});

it('adds a jam pelajaran slot to an existing pola jam', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->post(route('admin.jam-pelajaran.store'), [
        'pola_jam_id' => $pola->id,
        'hari' => 'senin',
        'urutan' => 1,
        'label' => 'Upacara',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:35',
        'is_pelajaran' => '0',
    ])->assertRedirect(route('admin.pola-jam.index'));

    expect(JamPelajaran::where('pola_jam_id', $pola->id)->where('label', 'Upacara')->exists())->toBeTrue();
});
