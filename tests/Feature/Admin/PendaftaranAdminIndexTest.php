<?php

use App\Models\CalonMurid;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder())->run();
});

function buatPendaftaranUntukAdmin(?Lembaga $lembaga = null, string $namaCalon = 'Ahmad Fauzan', string $status = 'menunggu_verifikasi'): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = $lembaga ?? Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    // firstOrCreate: buatPendaftaranUntukAdmin() is called multiple times with the same
    // $lembaga in some tests, and tahun_ajaran/jalur_ppdb/gelombang_ppdb each carry a unique
    // constraint that a plain create() would violate on the second call.
    $tahunAjaran = TahunAjaran::firstOrCreate(
        ['lembaga_id' => $lembaga->id, 'nama' => '2026/2027'],
        ['tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true],
    );
    $jalur = JalurPpdb::firstOrCreate(
        ['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler'],
    );
    $gelombang = GelombangPpdb::firstOrCreate(
        ['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1'],
        ['tanggal_buka' => now()->subDay(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 40],
    );
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id, 'nama_lengkap' => $namaCalon]);
    $pendaftaran = Pendaftaran::create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-'.random_int(10000, 99999), 'email_pendaftaran' => 'wali@example.test',
        'status' => $status, 'submitted_at' => now(),
    ]);

    return [$lembaga, $jalur, $gelombang, $pendaftaran];
}

it('denies access to the index page without the spmb-pendaftaran.view permission', function () {
    [$lembaga] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->get(route('admin.spmb-pendaftaran.index'))->assertForbidden();
});

it('denies access to the data endpoint without the spmb-pendaftaran.view permission', function () {
    [$lembaga] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->getJson(route('admin.spmb-pendaftaran.data'))->assertForbidden();
});

it('shows the index page with the view permission', function () {
    [$lembaga] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $this->actingAs($user)->get(route('admin.spmb-pendaftaran.index'))->assertOk();
});

it('returns only pendaftaran belonging to the acting user lembaga, searchable and paginated', function () {
    [$lembagaA] = buatPendaftaranUntukAdmin(namaCalon: 'Ahmad Fauzan');
    [$lembagaB] = buatPendaftaranUntukAdmin(namaCalon: 'Budi Santoso');
    $user = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $user->assignRole('admin_administrasi');

    $response = $this->actingAs($user)->getJson(route('admin.spmb-pendaftaran.data'));

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('nama_calon_murid');
    expect($names)->toContain('Ahmad Fauzan');
    expect($names)->not->toContain('Budi Santoso');

    $searchResponse = $this->actingAs($user)->getJson(route('admin.spmb-pendaftaran.data', ['search' => 'Ahmad']));
    expect(collect($searchResponse->json('data'))->pluck('nama_calon_murid'))->toContain('Ahmad Fauzan');

    $missResponse = $this->actingAs($user)->getJson(route('admin.spmb-pendaftaran.data', ['search' => 'Zzz Tidak Ada']));
    expect($missResponse->json('data'))->toBeEmpty();
});

it('filters by status', function () {
    [$lembaga, , , $diterima] = buatPendaftaranUntukAdmin(namaCalon: 'Sudah Diterima', status: 'diterima');
    buatPendaftaranUntukAdmin($lembaga, 'Masih Menunggu', 'menunggu_verifikasi');
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $response = $this->actingAs($user)->getJson(route('admin.spmb-pendaftaran.data', ['status' => 'diterima']));

    $names = collect($response->json('data'))->pluck('nama_calon_murid');
    expect($names)->toContain('Sudah Diterima');
    expect($names)->not->toContain('Masih Menunggu');
});

it('includes a dokumen progress count per row', function () {
    [$lembaga, $jalur, , $pendaftaran] = buatPendaftaranUntukAdmin();
    $syarat = \App\Models\DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    \App\Models\DokumenPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id, 'dokumen_syarat_ppdb_id' => $syarat->id,
        'file_path' => 'x.pdf', 'nama_file_asli' => 'x.pdf', 'mime_type' => 'application/pdf', 'ukuran_bytes' => 10,
        'status_verifikasi' => 'diterima',
    ]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $response = $this->actingAs($user)->getJson(route('admin.spmb-pendaftaran.data'));

    $row = collect($response->json('data'))->firstWhere('id', $pendaftaran->id);
    expect($row['dokumen_terverifikasi'])->toBe(1);
    expect($row['dokumen_total'])->toBe(1);
});
