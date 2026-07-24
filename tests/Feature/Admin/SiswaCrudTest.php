<?php

use App\Enums\SumberDataSiswa;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsSiswaManager(Lembaga $lembaga): User
{
    foreach (['siswa.view', 'siswa.create', 'siswa.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['siswa.view', 'siswa.create', 'siswa.edit']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access to a user without siswa.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.siswa.index'))->assertForbidden();
});

it('creates a siswa manually with sumber_data forced to manual', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $manager = actingAsSiswaManager($lembaga);

    $this->actingAs($manager)->post(route('admin.siswa.store'), [
        'kelas_id' => $kelas->id,
        'nis' => '2026001',
        'nisn' => '0012345678',
        'nama_lengkap' => 'Budi Santoso',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Jakarta',
        'tanggal_lahir' => '2015-03-10',
        'agama' => 'Islam',
    ])->assertRedirect(route('admin.siswa.index'));

    $siswa = Siswa::where('nis', '2026001')->firstOrFail();
    expect($siswa->sumber_data)->toBe(SumberDataSiswa::Manual);
    expect($siswa->kelas_id)->toBe($kelas->id);
});

it('rejects a duplicate NIS within the same lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsSiswaManager($lembaga);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nis' => '2026001']);

    $this->actingAs($manager)->post(route('admin.siswa.store'), [
        'nis' => '2026001',
        'nama_lengkap' => 'Siswa Kedua',
        'jenis_kelamin' => 'P',
    ])->assertSessionHasErrors('nis');
});

it('only lists siswa belonging to the acting manager\'s own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsSiswaManager($lembagaA);

    Siswa::factory()->create(['lembaga_id' => $lembagaA->id, 'nama_lengkap' => 'Siswa Lembaga A']);
    Siswa::withoutGlobalScopes()->create(array_merge(
        Siswa::factory()->raw(),
        ['lembaga_id' => $lembagaB->id, 'nama_lengkap' => 'Siswa Lembaga B', 'nis' => '9999999']
    ));

    $response = $this->actingAs($manager)->get(route('admin.siswa.index'));

    $response->assertSee('Siswa Lembaga A');
    $response->assertDontSee('Siswa Lembaga B');
});

it('updates a siswa', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsSiswaManager($lembaga);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nis' => '2026005']);

    $this->actingAs($manager)->put(route('admin.siswa.update', $siswa), [
        'nis' => '2026005',
        'nama_lengkap' => 'Nama Diperbarui',
        'jenis_kelamin' => 'L',
    ])->assertRedirect(route('admin.siswa.index'));

    expect($siswa->fresh()->nama_lengkap)->toBe('Nama Diperbarui');
});
