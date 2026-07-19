<?php

use App\Models\GelombangPpdb;
use App\Models\HasilSeleksi;
use App\Models\JalurPpdb;
use App\Models\JenisTesMaster;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\SeleksiPpdb;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function buatKonteksSeleksi(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    foreach (['seleksi.create', 'seleksi.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['seleksi.create', 'seleksi.delete']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi']);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => '2026-08-01', 'tanggal_tutup' => '2026-09-01', 'kuota' => 40,
    ]);
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);

    return [$lembaga, $user, $tahunAjaran, $jalur, $gelombang, $jenisTes];
}

it('adds a seleksi schedule linking a jalur, gelombang, and jenis tes', function () {
    [$lembaga, $user, $tahunAjaran, $jalur, $gelombang, $jenisTes] = buatKonteksSeleksi();

    $this->actingAs($user)->post(route('admin.seleksi.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'gelombang_ppdb_id' => $gelombang->id,
        'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => '2026-08-15 09:00',
        'kriteria_kelulusan' => 'Nilai minimal 70',
        'bobot' => '40',
    ])->assertRedirect(route('admin.jalur-ppdb.edit', $jalur));

    $seleksi = SeleksiPpdb::first();
    expect($seleksi->gelombang_ppdb_id)->toBe($gelombang->id);
    expect($seleksi->jenis_tes_master_id)->toBe($jenisTes->id);
    expect((float) $seleksi->bobot)->toBe(40.0);
});

it('rejects a gelombang that belongs to a different tahun ajaran than the jalur', function () {
    [$lembaga, $user, $tahunAjaran, $jalur, $gelombang, $jenisTes] = buatKonteksSeleksi();

    $tahunLain = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2025/2026',
        'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'status_aktif' => false,
    ]);
    $gelombangTahunLain = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLain->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => '2025-08-01', 'tanggal_tutup' => '2025-09-01', 'kuota' => 40,
    ]);

    $this->actingAs($user)->post(route('admin.seleksi.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'gelombang_ppdb_id' => $gelombangTahunLain->id,
        'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => '2026-08-15 09:00',
    ])->assertSessionHasErrors('gelombang_ppdb_id');

    expect(SeleksiPpdb::count())->toBe(0);
});

it('rejects a jenis tes belonging to another lembaga', function () {
    [$lembaga, $user, $tahunAjaran, $jalur, $gelombang, $jenisTes] = buatKonteksSeleksi();
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    $otherJenisTes = JenisTesMaster::withoutGlobalScopes()->create(['lembaga_id' => $otherLembaga->id, 'nama' => 'Wawancara']);

    $this->actingAs($user)->post(route('admin.seleksi.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'gelombang_ppdb_id' => $gelombang->id,
        'jenis_tes_master_id' => $otherJenisTes->id,
        'jadwal' => '2026-08-15 09:00',
    ])->assertSessionHasErrors('jenis_tes_master_id');

    expect(SeleksiPpdb::count())->toBe(0);
});

it('deletes a seleksi row', function () {
    [$lembaga, $user, $tahunAjaran, $jalur, $gelombang, $jenisTes] = buatKonteksSeleksi();
    $this->actingAs($user);
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'jenis_tes_master_id' => $jenisTes->id, 'jadwal' => '2026-08-15 09:00:00',
    ]);

    $this->delete(route('admin.seleksi.destroy', $seleksi))->assertRedirect(route('admin.jalur-ppdb.edit', $jalur));

    expect(SeleksiPpdb::find($seleksi->id))->toBeNull();
});

it('denies access without the manage-ppdb permission', function () {
    [$lembaga, $user, $tahunAjaran, $jalur, $gelombang, $jenisTes] = buatKonteksSeleksi();
    $noRoleUser = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($noRoleUser)->post(route('admin.seleksi.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'gelombang_ppdb_id' => $gelombang->id,
        'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => '2026-08-15 09:00',
    ])->assertForbidden();
});

it('exposes the hasilSeleksi relation with real registrant result data', function () {
    [$lembaga, $user, $tahunAjaran, $jalur, $gelombang, $jenisTes] = buatKonteksSeleksi();
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'jenis_tes_master_id' => $jenisTes->id, 'jadwal' => '2026-08-15 09:00:00',
    ]);
    [, , , $pendaftaran] = buatPendaftaranUntukAdmin($lembaga);

    HasilSeleksi::create(['pendaftaran_id' => $pendaftaran->id, 'seleksi_ppdb_id' => $seleksi->id, 'nilai' => 80]);

    expect($seleksi->hasilSeleksi()->count())->toBe(1);
});

it('restricts deleting a seleksi_ppdb row at the database level when hasil_seleksi references it', function () {
    [$lembaga, $user, $tahunAjaran, $jalur, $gelombang, $jenisTes] = buatKonteksSeleksi();
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'jenis_tes_master_id' => $jenisTes->id, 'jadwal' => '2026-08-15 09:00:00',
    ]);
    [, , , $pendaftaran] = buatPendaftaranUntukAdmin($lembaga);
    HasilSeleksi::create(['pendaftaran_id' => $pendaftaran->id, 'seleksi_ppdb_id' => $seleksi->id, 'nilai' => 80]);

    expect(fn () => $seleksi->delete())->toThrow(\Illuminate\Database\QueryException::class);
    expect(SeleksiPpdb::find($seleksi->id))->not->toBeNull();
});
