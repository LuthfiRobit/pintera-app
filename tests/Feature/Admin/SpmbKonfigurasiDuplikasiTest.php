<?php

use App\Models\DokumenSyaratPpdb;
use App\Models\FormulirField;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\JenisTesMaster;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\SeleksiPpdb;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function buatKonteksDuplikasi(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Permission::firstOrCreate(['name' => 'manage-ppdb', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('manage-ppdb');
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $tahunLama = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2025/2026',
        'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'status_aktif' => false,
    ]);
    $tahunBaru = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);

    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => '2025-08-01', 'tanggal_tutup' => '2025-09-01', 'kuota' => 30,
    ]);
    $jalur = JalurPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id,
        'nama' => 'Prestasi', 'deskripsi' => 'Jalur nilai rapor', 'status_aktif' => true,
    ]);
    FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Nilai Rata-rata Rapor', 'field_type' => 'number', 'urutan' => 0]);
    DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Fotokopi Rapor', 'wajib' => true, 'urutan' => 0]);
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Wawancara']);
    SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'jenis_tes_master_id' => $jenisTes->id, 'jadwal' => '2025-08-20 09:00:00',
        'kriteria_kelulusan' => 'Lolos wawancara', 'bobot' => 30,
    ]);

    return [$lembaga, $user, $tahunLama, $tahunBaru, $jenisTes];
}

it('duplicates the entire SPMB configuration chain into the target tahun ajaran', function () {
    [$lembaga, $user, $tahunLama, $tahunBaru, $jenisTes] = buatKonteksDuplikasi();

    $this->actingAs($user)->post(route('admin.spmb-konfigurasi.duplikasi'), [
        'tahun_ajaran_sumber_id' => $tahunLama->id,
    ])->assertRedirect();

    expect(GelombangPpdb::where('tahun_ajaran_id', $tahunBaru->id)->count())->toBe(1);
    expect(JalurPpdb::where('tahun_ajaran_id', $tahunBaru->id)->count())->toBe(1);

    $jalurBaru = JalurPpdb::where('tahun_ajaran_id', $tahunBaru->id)->first();
    expect($jalurBaru->nama)->toBe('Prestasi');
    expect($jalurBaru->formulirField)->toHaveCount(1);
    expect($jalurBaru->dokumenSyarat)->toHaveCount(1);
    expect($jalurBaru->seleksi)->toHaveCount(1);

    $gelombangBaru = GelombangPpdb::where('tahun_ajaran_id', $tahunBaru->id)->first();
    expect($gelombangBaru->tanggal_buka->format('Y-m-d'))->toBe('2026-08-01');
    expect($gelombangBaru->tanggal_tutup->format('Y-m-d'))->toBe('2026-09-01');

    $seleksiBaru = $jalurBaru->seleksi->first();
    expect($seleksiBaru->gelombang_ppdb_id)->toBe($gelombangBaru->id);
    expect($seleksiBaru->jenis_tes_master_id)->toBe($jenisTes->id);
});

it('refuses to duplicate into a tahun ajaran that already has gelombang or jalur data', function () {
    [$lembaga, $user, $tahunLama, $tahunBaru] = buatKonteksDuplikasi();
    JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id, 'nama' => 'Sudah Ada']);

    $this->actingAs($user)->post(route('admin.spmb-konfigurasi.duplikasi'), [
        'tahun_ajaran_sumber_id' => $tahunLama->id,
    ])->assertSessionHasErrors('tahun_ajaran_sumber_id');

    expect(JalurPpdb::where('tahun_ajaran_id', $tahunBaru->id)->count())->toBe(1);
});

it('refuses to duplicate into a tahun ajaran that already has gelombang data', function () {
    [$lembaga, $user, $tahunLama, $tahunBaru] = buatKonteksDuplikasi();
    GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id, 'nama' => 'Sudah Ada',
        'tanggal_buka' => '2026-08-01', 'tanggal_tutup' => '2026-09-01', 'kuota' => 10,
    ]);

    $this->actingAs($user)->post(route('admin.spmb-konfigurasi.duplikasi'), [
        'tahun_ajaran_sumber_id' => $tahunLama->id,
    ])->assertSessionHasErrors('tahun_ajaran_sumber_id');

    expect(GelombangPpdb::where('tahun_ajaran_id', $tahunBaru->id)->count())->toBe(1);
    expect(JalurPpdb::where('tahun_ajaran_id', $tahunBaru->id)->count())->toBe(0);
});

it('rejects duplicating from a tahun ajaran belonging to another lembaga', function () {
    [$lembaga, $user, $tahunLama, $tahunBaru] = buatKonteksDuplikasi();
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    $otherTahun = TahunAjaran::withoutGlobalScopes()->create([
        'lembaga_id' => $otherLembaga->id, 'nama' => '2025/2026',
        'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'status_aktif' => false,
    ]);

    $this->actingAs($user)->post(route('admin.spmb-konfigurasi.duplikasi'), [
        'tahun_ajaran_sumber_id' => $otherTahun->id,
    ])->assertSessionHasErrors('tahun_ajaran_sumber_id');

    expect(GelombangPpdb::where('tahun_ajaran_id', $tahunBaru->id)->count())->toBe(0);
});

it('denies access without the manage-ppdb permission', function () {
    [$lembaga, $user, $tahunLama, $tahunBaru] = buatKonteksDuplikasi();
    $noRoleUser = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($noRoleUser)->post(route('admin.spmb-konfigurasi.duplikasi'), [
        'tahun_ajaran_sumber_id' => $tahunLama->id,
    ])->assertForbidden();
});
