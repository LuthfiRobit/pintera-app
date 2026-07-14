<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder())->run();
});

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

it('returns pendaftaran data for a yayasan-scoped user once an active lembaga is selected in session', function () {
    [$lembaga] = buatPendaftaranUntukAdmin(namaCalon: 'Ahmad Fauzan');
    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('yayasan_super_admin');

    $response = $this->actingAs($user)
        ->withSession(['active_lembaga_id' => $lembaga->id])
        ->getJson(route('admin.spmb-pendaftaran.data'));

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('nama_calon_murid'))->toContain('Ahmad Fauzan');
});

it('returns an empty data array, not a 500, for a yayasan-scoped user with no active lembaga selected', function () {
    buatPendaftaranUntukAdmin(namaCalon: 'Ahmad Fauzan');
    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('yayasan_super_admin');

    $response = $this->actingAs($user)->getJson(route('admin.spmb-pendaftaran.data'));

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
    // Assert the full zeroed meta shape, not just total === 0: an unguarded
    // ->where('lembaga_id', null) query also happens to return zero rows (no
    // Pendaftaran literally has a NULL lembaga_id), which would make a
    // total-only assertion pass even without the guard. Laravel's paginate()
    // on that unguarded query still reports current_page=1/per_page=15, so
    // pinning the whole meta array to zero only passes once the guard
    // short-circuits the query entirely.
    expect($response->json('meta'))->toBe([
        'current_page' => 0,
        'last_page' => 0,
        'per_page' => 0,
        'total' => 0,
    ]);
});
