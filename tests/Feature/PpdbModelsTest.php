<?php

use App\Models\DokumenSyaratPpdb;
use App\Models\FormulirField;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\SeleksiPpdb;
use App\Models\TahunAjaran;
use App\Models\GelombangPpdb;
use App\Models\JenisTesMaster;
use App\Models\User;
use App\Models\Yayasan;

function buatKonteksLembaga(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id,
        'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-06-30',
        'status_aktif' => true,
    ]);

    return [$lembaga, $user, $tahunAjaran];
}

function buatAktorYayasan(): User
{
    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $user = User::factory()->create();
    $user->assignRole('yayasan_super_admin');

    return $user;
}

it('copies lembaga_id from the parent jalur onto a new formulir field', function () {
    [$lembaga, , $tahunAjaran] = buatKonteksLembaga();
    test()->actingAs(buatAktorYayasan());

    $jalur = JalurPpdb::create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Reguler',
    ]);

    $field = FormulirField::create([
        'jalur_ppdb_id' => $jalur->id,
        'label' => 'Nomor Hafalan Juz',
        'field_type' => 'number',
    ]);

    expect($field->lembaga_id)->toBe($lembaga->id);
});

it('copies lembaga_id from the parent jalur onto a new dokumen syarat', function () {
    [$lembaga, , $tahunAjaran] = buatKonteksLembaga();
    test()->actingAs(buatAktorYayasan());

    $jalur = JalurPpdb::create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Tahfidz',
    ]);

    $dokumen = DokumenSyaratPpdb::create([
        'jalur_ppdb_id' => $jalur->id,
        'nama_dokumen' => 'Sertifikat Hafalan',
    ]);

    expect($dokumen->lembaga_id)->toBe($lembaga->id);
});

it('copies lembaga_id from the parent jalur onto a new seleksi row', function () {
    [$lembaga, , $tahunAjaran] = buatKonteksLembaga();
    test()->actingAs(buatAktorYayasan());

    $jalur = JalurPpdb::create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Prestasi',
    ]);

    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Gelombang 1',
        'tanggal_buka' => '2026-01-01',
        'tanggal_tutup' => '2026-02-01',
        'kuota' => 30,
    ]);

    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);

    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id,
        'gelombang_ppdb_id' => $gelombang->id,
        'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => '2026-01-15 09:00:00',
    ]);

    expect($seleksi->lembaga_id)->toBe($lembaga->id);
});

it('loads a jalur with its formulir field, dokumen syarat, and seleksi relations', function () {
    [$lembaga, $user, $tahunAjaran] = buatKonteksLembaga();
    test()->actingAs($user);

    $jalur = JalurPpdb::create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Afirmasi',
    ]);

    FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Alasan', 'field_type' => 'textarea']);
    DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Surat Keterangan Tidak Mampu']);

    expect($jalur->fresh()->formulirField)->toHaveCount(1);
    expect($jalur->fresh()->dokumenSyarat)->toHaveCount(1);
});
