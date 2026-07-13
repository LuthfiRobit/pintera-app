<?php

use App\Models\JenisTesMaster;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function buatAdminPpdb(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    foreach (['jenis-tes.view', 'jenis-tes.create', 'jenis-tes.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['jenis-tes.view', 'jenis-tes.create', 'jenis-tes.delete']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    return [$lembaga, $user];
}

function buatYayasanSuperAdminDenganLembagaAktif(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    foreach (['jenis-tes.view', 'jenis-tes.create', 'jenis-tes.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['jenis-tes.view', 'jenis-tes.create', 'jenis-tes.delete']);
    $user = User::factory()->create();
    $user->assignRole($role);

    test()->actingAs($user);
    test()->get('/dashboard?switch_lembaga='.$lembaga->id);

    return [$lembaga, $user];
}

it('denies access without the manage-ppdb permission', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->get(route('admin.jenis-tes.index'))->assertForbidden();
});

it('creates a jenis tes scoped to the acting user lembaga', function () {
    [$lembaga, $user] = buatAdminPpdb();

    $this->actingAs($user)
        ->post(route('admin.jenis-tes.store'), ['nama' => 'Tes Tulis', 'deskripsi' => 'Tes tertulis akademik'])
        ->assertRedirect(route('admin.jenis-tes.index'));

    $jenisTes = JenisTesMaster::first();
    expect($jenisTes->nama)->toBe('Tes Tulis');
    expect($jenisTes->lembaga_id)->toBe($lembaga->id);
});

it('lets a yayasan-scoped admin with an active lembaga create a jenis tes scoped to that lembaga', function () {
    [$lembaga, $user] = buatYayasanSuperAdminDenganLembagaAktif();

    $this->actingAs($user)
        ->post(route('admin.jenis-tes.store'), ['nama' => 'Tes Tulis', 'deskripsi' => 'Tes tertulis akademik'])
        ->assertRedirect(route('admin.jenis-tes.index'));

    $jenisTes = JenisTesMaster::withoutGlobalScopes()->first();
    expect($jenisTes->nama)->toBe('Tes Tulis');
    expect($jenisTes->lembaga_id)->toBe($lembaga->id);
});

it('returns a validation error instead of crashing when nama is duplicated for the same lembaga', function () {
    [$lembaga, $user] = buatAdminPpdb();

    $this->actingAs($user)
        ->post(route('admin.jenis-tes.store'), ['nama' => 'Tes Tulis'])
        ->assertRedirect(route('admin.jenis-tes.index'));

    $this->actingAs($user)
        ->post(route('admin.jenis-tes.store'), ['nama' => 'Tes Tulis'])
        ->assertSessionHasErrors('nama');

    expect(JenisTesMaster::count())->toBe(1);
});

it('404s when a lembaga-scoped admin tries to delete a jenis tes belonging to another lembaga', function () {
    [$lembaga, $user] = buatAdminPpdb();
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);

    $otherJenisTes = JenisTesMaster::withoutGlobalScopes()->create([
        'lembaga_id' => $otherLembaga->id,
        'nama' => 'Wawancara',
    ]);

    $this->actingAs($user)
        ->delete(route('admin.jenis-tes.destroy', $otherJenisTes))
        ->assertNotFound();
});

it('blocks deleting a jenis tes that is still referenced by a seleksi row', function () {
    [$lembaga, $user] = buatAdminPpdb();
    $this->actingAs($user);

    $tahunAjaran = \App\Models\TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = \App\Models\JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $gelombang = \App\Models\GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => '2026-01-01', 'tanggal_tutup' => '2026-02-01', 'kuota' => 20,
    ]);
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);
    \App\Models\SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'jenis_tes_master_id' => $jenisTes->id, 'jadwal' => '2026-01-15 09:00:00',
    ]);

    $this->delete(route('admin.jenis-tes.destroy', $jenisTes))
        ->assertRedirect(route('admin.jenis-tes.index'))
        ->assertSessionHasErrors('jenis_tes');

    expect(JenisTesMaster::find($jenisTes->id))->not->toBeNull();
});
