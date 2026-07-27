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

it('rejects creating a siswa with a kelas belonging to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $kelasLain = Kelas::withoutGlobalScopes()->create([
        'lembaga_id' => $lembagaLain->id,
        'tahun_ajaran_id' => $tahunAjaranLain->id,
        'nama' => 'Kelas Lembaga Lain',
    ]);
    $manager = actingAsSiswaManager($lembagaSaya);

    $this->actingAs($manager)->post(route('admin.siswa.store'), [
        'kelas_id' => $kelasLain->id,
        'nis' => '2026999',
        'nama_lengkap' => 'Siswa Campur Lembaga',
        'jenis_kelamin' => 'L',
    ])->assertNotFound();

    expect(Siswa::where('nama_lengkap', 'Siswa Campur Lembaga')->exists())->toBeFalse();
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

it('builds the kelas filter list from the acting lembaga-scoped user\'s own active tahun ajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => false]);
    $tahunAjaranAktif = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaranLama->id]);
    $kelasAktif = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaranAktif->id]);
    $manager = actingAsSiswaManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.siswa.index'));

    $response->assertViewHas('kelasList', function ($list) use ($kelasAktif, $kelasLama) {
        return $list->contains('id', $kelasAktif->id) && ! $list->contains('id', $kelasLama->id);
    });
});

it('leaves the kelas filter list empty for a yayasan-scoped user with no active lembaga selected', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id, 'status_aktif' => true]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id, 'status_aktif' => true]);
    Kelas::factory()->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaranA->id]);
    Kelas::factory()->create(['lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $tahunAjaranB->id]);

    Permission::firstOrCreate(['name' => 'siswa.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_siswa_index_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['siswa.view']);
    $manager = User::factory()->create(['lembaga_id' => null]);
    $manager->assignRole($role);
    // No active_lembaga_id in session — before the fix, this would have picked
    // an arbitrary lembaga's active tahun ajaran to build the kelas list from.

    $response = $this->actingAs($manager)->get(route('admin.siswa.index'));

    $response->assertViewHas('kelasList', fn ($list) => $list->isEmpty());
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
