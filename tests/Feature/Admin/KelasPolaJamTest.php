<?php

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\PolaJam;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('assigns a pola jam to a kelas on create', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    foreach (['kelas.view', 'kelas.create', 'kelas.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kelas.view', 'kelas.create', 'kelas.edit']);
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => '6A',
        'pola_jam_id' => $pola->id,
    ])->assertRedirect(route('admin.kelas.index'));

    $kelas = Kelas::where('nama', '6A')->firstOrFail();
    expect($kelas->polaJam->id)->toBe($pola->id);
});
