<?php

use App\Models\DokumenPendaftaran;
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

it('exposes the dokumenPendaftaran relation with real registrant document data', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukDokumen();
    $dokumen = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    [, , , $pendaftaran] = buatPendaftaranUntukAdmin($lembaga);

    DokumenPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id,
        'dokumen_syarat_ppdb_id' => $dokumen->id,
        'file_path' => 'dokumen/akta.pdf',
        'nama_file_asli' => 'akta.pdf',
        'mime_type' => 'application/pdf',
        'ukuran_bytes' => 1024,
    ]);

    expect($dokumen->dokumenPendaftaran()->count())->toBe(1);
});

it('rejects deleting a dokumen syarat that already has a registrant document', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukDokumen();
    $dokumen = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    [, , , $pendaftaran] = buatPendaftaranUntukAdmin($lembaga);
    DokumenPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id, 'dokumen_syarat_ppdb_id' => $dokumen->id,
        'file_path' => 'dokumen/akta.pdf', 'nama_file_asli' => 'akta.pdf', 'mime_type' => 'application/pdf',
        'ukuran_bytes' => 123456,
    ]);

    $this->actingAs($user)->delete(route('admin.dokumen-syarat.destroy', $dokumen))
        ->assertSessionHasErrors('dokumen_syarat');

    expect(DokumenSyaratPpdb::find($dokumen->id))->not->toBeNull();
});

it('names the related document count in the deletion error message', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukDokumen();
    $dokumen = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    [, , , $pendaftaran] = buatPendaftaranUntukAdmin($lembaga);
    DokumenPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id, 'dokumen_syarat_ppdb_id' => $dokumen->id,
        'file_path' => 'dokumen/akta.pdf', 'nama_file_asli' => 'akta.pdf', 'mime_type' => 'application/pdf',
        'ukuran_bytes' => 123456,
    ]);

    $this->actingAs($user)->delete(route('admin.dokumen-syarat.destroy', $dokumen));

    expect(session('errors')->get('dokumen_syarat')[0])->toContain('1 dokumen');
});

it('responds with JSON on store when requested', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukDokumen();

    $response = $this->actingAs($user)->postJson(route('admin.dokumen-syarat.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'nama_dokumen' => 'Kartu Keluarga',
        'wajib' => '1',
    ]);

    $response->assertCreated();
    expect($response->json('data.nama_dokumen'))->toBe('Kartu Keluarga');
});

it('responds with a JSON 422 and the correct message when a blocked deletion is requested via AJAX', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukDokumen();
    $dokumen = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    [, , , $pendaftaran] = buatPendaftaranUntukAdmin($lembaga);
    DokumenPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id, 'dokumen_syarat_ppdb_id' => $dokumen->id,
        'file_path' => 'dokumen/akta.pdf', 'nama_file_asli' => 'akta.pdf', 'mime_type' => 'application/pdf',
        'ukuran_bytes' => 123456,
    ]);

    $blocked = $this->actingAs($user)->deleteJson(route('admin.dokumen-syarat.destroy', $dokumen));
    $blocked->assertStatus(422);
    expect($blocked->json('message'))->toContain('1 dokumen');
    expect(DokumenSyaratPpdb::find($dokumen->id))->not->toBeNull();
});

it('responds with a JSON success message when an unblocked deletion is requested via AJAX', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukDokumen();
    $dokumen = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Kartu Keluarga']);

    $response = $this->actingAs($user)->deleteJson(route('admin.dokumen-syarat.destroy', $dokumen));

    $response->assertOk();
    expect($response->json('message'))->toBe('Dokumen syarat berhasil dihapus.');
    expect(DokumenSyaratPpdb::find($dokumen->id))->toBeNull();
});
