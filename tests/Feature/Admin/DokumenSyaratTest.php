<?php

use App\Models\DokumenSyaratPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function buatJalurUntukDokumen(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    foreach (['dokumen-syarat.create', 'dokumen-syarat.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['dokumen-syarat.create', 'dokumen-syarat.delete']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Afirmasi']);

    return [$lembaga, $user, $jalur];
}

it('adds a required dokumen syarat to a jalur', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukDokumen();

    $this->actingAs($user)->post(route('admin.dokumen-syarat.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'nama_dokumen' => 'Surat Keterangan Tidak Mampu',
        'wajib' => '1',
    ])->assertRedirect(route('admin.jalur-ppdb.edit', $jalur));

    $dokumen = DokumenSyaratPpdb::first();
    expect($dokumen->nama_dokumen)->toBe('Surat Keterangan Tidak Mampu');
    expect($dokumen->wajib)->toBeTrue();
    expect($dokumen->urutan)->toBe(0);
});

it('rejects a dokumen syarat targeting a jalur in another lembaga', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukDokumen();
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    $otherTahun = TahunAjaran::withoutGlobalScopes()->create([
        'lembaga_id' => $otherLembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $otherJalur = JalurPpdb::withoutGlobalScopes()->create([
        'lembaga_id' => $otherLembaga->id, 'tahun_ajaran_id' => $otherTahun->id, 'nama' => 'Reguler',
    ]);

    $this->actingAs($user)->post(route('admin.dokumen-syarat.store'), [
        'jalur_ppdb_id' => $otherJalur->id,
        'nama_dokumen' => 'Akta Kelahiran',
    ])->assertSessionHasErrors('jalur_ppdb_id');

    expect(DokumenSyaratPpdb::count())->toBe(0);
});

it('deletes a dokumen syarat', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukDokumen();
    $this->actingAs($user);
    $dokumen = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);

    $this->delete(route('admin.dokumen-syarat.destroy', $dokumen))->assertRedirect(route('admin.jalur-ppdb.edit', $jalur));

    expect(DokumenSyaratPpdb::find($dokumen->id))->toBeNull();
});

it('denies access without the manage-ppdb permission', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukDokumen();
    $noRoleUser = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($noRoleUser)->post(route('admin.dokumen-syarat.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'nama_dokumen' => 'Akta Kelahiran',
    ])->assertForbidden();
});
