<?php

use App\Models\GelombangPpdb;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function buatAdminPpdbDenganTahunAktif(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Permission::firstOrCreate(['name' => 'manage-ppdb', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('manage-ppdb');
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);

    return [$lembaga, $user, $tahunAjaran];
}

it('shows an empty-state prompt when the lembaga has no active tahun ajaran', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    Permission::firstOrCreate(['name' => 'manage-ppdb', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('manage-ppdb');
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $this->actingAs($user)->get(route('admin.gelombang-ppdb.index'))
        ->assertOk()
        ->assertSee('Aktifkan tahun ajaran');
});

it('creates a gelombang scoped to the active tahun ajaran', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminPpdbDenganTahunAktif();

    $this->actingAs($user)->post(route('admin.gelombang-ppdb.store'), [
        'nama' => 'Gelombang 1',
        'tanggal_buka' => '2026-08-01',
        'tanggal_tutup' => '2026-09-01',
        'kuota' => 40,
    ])->assertRedirect(route('admin.gelombang-ppdb.index'));

    $gelombang = GelombangPpdb::first();
    expect($gelombang->tahun_ajaran_id)->toBe($tahunAjaran->id);
    expect($gelombang->lembaga_id)->toBe($lembaga->id);
});

it('rejects a gelombang whose tanggal_tutup is before tanggal_buka', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminPpdbDenganTahunAktif();

    $this->actingAs($user)->post(route('admin.gelombang-ppdb.store'), [
        'nama' => 'Gelombang 1',
        'tanggal_buka' => '2026-09-01',
        'tanggal_tutup' => '2026-08-01',
        'kuota' => 40,
    ])->assertSessionHasErrors('tanggal_tutup');

    expect(GelombangPpdb::count())->toBe(0);
});

it('404s when a lembaga-scoped admin opens the edit page for a gelombang in another lembaga', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminPpdbDenganTahunAktif();
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    $otherTahun = TahunAjaran::withoutGlobalScopes()->create([
        'lembaga_id' => $otherLembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $otherGelombang = GelombangPpdb::withoutGlobalScopes()->create([
        'lembaga_id' => $otherLembaga->id, 'tahun_ajaran_id' => $otherTahun->id,
        'nama' => 'Gelombang 1', 'tanggal_buka' => '2026-08-01', 'tanggal_tutup' => '2026-09-01', 'kuota' => 40,
    ]);

    $this->actingAs($user)->get(route('admin.gelombang-ppdb.edit', $otherGelombang))->assertNotFound();
});

it('denies access without the manage-ppdb permission', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminPpdbDenganTahunAktif();
    $noRoleUser = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($noRoleUser)->get(route('admin.gelombang-ppdb.index'))->assertForbidden();
});
