<?php

use App\Models\DokumenPendaftaran;
use App\Models\DokumenSyaratPpdb;
use App\Models\HasilSeleksi;
use App\Models\JenisTesMaster;
use App\Models\SeleksiPpdb;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('shows the detail page with calon murid data, dokumen, and nilai panels', function () {
    [$lembaga, $jalur, , $pendaftaran] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $this->actingAs($user)->get(route('admin.spmb-pendaftaran.show', $pendaftaran))
        ->assertOk()
        ->assertSee($pendaftaran->calonMurid->nama_lengkap);
});

it('lets bendahara_lembaga view the detail page, since the tagihan/pembayaran panel lives here', function () {
    [$lembaga, , , $pendaftaran] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

    $this->actingAs($user)->get(route('admin.spmb-pendaftaran.show', $pendaftaran))
        ->assertOk()
        ->assertSee($pendaftaran->calonMurid->nama_lengkap);
});

it('404s the detail page for a pendaftaran belonging to a different lembaga', function () {
    [$lembagaA] = buatPendaftaranUntukAdmin();
    [, , , $pendaftaranB] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $user->assignRole('admin_administrasi');

    $this->actingAs($user)->get(route('admin.spmb-pendaftaran.show', $pendaftaranB))->assertNotFound();
});

it('verifies a dokumen with a required catatan when rejecting', function () {
    [$lembaga, $jalur, , $pendaftaran] = buatPendaftaranUntukAdmin();
    $syarat = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    $dokumen = DokumenPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id, 'dokumen_syarat_ppdb_id' => $syarat->id,
        'file_path' => 'x.pdf', 'nama_file_asli' => 'x.pdf', 'mime_type' => 'application/pdf', 'ukuran_bytes' => 10,
    ]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $rejectWithoutCatatan = $this->actingAs($user)->postJson(
        route('admin.spmb-pendaftaran.verifikasi-dokumen', [$pendaftaran, $dokumen]),
        ['status_verifikasi' => 'ditolak']
    );
    $rejectWithoutCatatan->assertStatus(422);

    $reject = $this->actingAs($user)->postJson(
        route('admin.spmb-pendaftaran.verifikasi-dokumen', [$pendaftaran, $dokumen]),
        ['status_verifikasi' => 'ditolak', 'catatan_verifikasi' => 'Foto buram.']
    );
    $reject->assertOk();

    $dokumen->refresh();
    expect($dokumen->status_verifikasi)->toBe('ditolak');
    expect($dokumen->catatan_verifikasi)->toBe('Foto buram.');
    expect($dokumen->diverifikasi_oleh_user_id)->toBe($user->id);
});

it('denies dokumen verification without the verifikasi-dokumen permission, even with other spmb-pendaftaran permissions', function () {
    [$lembaga, $jalur, , $pendaftaran] = buatPendaftaranUntukAdmin();
    $syarat = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    $dokumen = DokumenPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id, 'dokumen_syarat_ppdb_id' => $syarat->id,
        'file_path' => 'x.pdf', 'nama_file_asli' => 'x.pdf', 'mime_type' => 'application/pdf', 'ukuran_bytes' => 10,
    ]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->givePermissionTo(['spmb-pendaftaran.view', 'spmb-pendaftaran.nilai-seleksi', 'spmb-pendaftaran.tetapkan-keputusan']);

    $this->actingAs($user)->postJson(
        route('admin.spmb-pendaftaran.verifikasi-dokumen', [$pendaftaran, $dokumen]),
        ['status_verifikasi' => 'diterima']
    )->assertForbidden();
});

it('denies nilai entry without the nilai-seleksi permission, even with other spmb-pendaftaran permissions', function () {
    [$lembaga, $jalur, $gelombang, $pendaftaran] = buatPendaftaranUntukAdmin();
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id, 'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => now()->addWeek(), 'kriteria_kelulusan' => 'Nilai minimal 65', 'bobot' => 60,
    ]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->givePermissionTo(['spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.tetapkan-keputusan']);

    $this->actingAs($user)->postJson(
        route('admin.spmb-pendaftaran.nilai', $pendaftaran),
        ['seleksi_ppdb_id' => $seleksi->id, 'nilai' => 80]
    )->assertForbidden();
});

it('saves nilai via updateOrCreate, never duplicating for the same seleksi_ppdb', function () {
    [$lembaga, $jalur, $gelombang, $pendaftaran] = buatPendaftaranUntukAdmin();
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id, 'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => now()->addWeek(), 'kriteria_kelulusan' => 'Nilai minimal 65', 'bobot' => 60,
    ]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $this->actingAs($user)->postJson(
        route('admin.spmb-pendaftaran.nilai', $pendaftaran),
        ['seleksi_ppdb_id' => $seleksi->id, 'nilai' => 80, 'catatan' => 'Baik']
    )->assertOk();

    $this->actingAs($user)->postJson(
        route('admin.spmb-pendaftaran.nilai', $pendaftaran),
        ['seleksi_ppdb_id' => $seleksi->id, 'nilai' => 90]
    )->assertOk();

    expect(HasilSeleksi::where('pendaftaran_id', $pendaftaran->id)->count())->toBe(1);
    expect((float) HasilSeleksi::first()->nilai)->toBe(90.0);
});

it('tetapkan keputusan sets status, catatan, and audit fields, even with unverified documents', function () {
    [$lembaga, $jalur, , $pendaftaran] = buatPendaftaranUntukAdmin();
    DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('kepala_sekolah');

    $response = $this->actingAs($user)->postJson(
        route('admin.spmb-pendaftaran.keputusan', $pendaftaran),
        ['status' => 'diterima', 'catatan_keputusan' => 'Nilai memenuhi syarat.']
    );

    $response->assertOk();
    $pendaftaran->refresh();
    expect($pendaftaran->status)->toBe('diterima');
    expect($pendaftaran->catatan_keputusan)->toBe('Nilai memenuhi syarat.');
    expect($pendaftaran->ditetapkan_oleh_user_id)->toBe($user->id);
    expect($pendaftaran->ditetapkan_pada)->not->toBeNull();
});

it('denies tetapkan keputusan without the tetapkan-keputusan permission', function () {
    [$lembaga, , , $pendaftaran] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $this->actingAs($user)->postJson(
        route('admin.spmb-pendaftaran.keputusan', $pendaftaran),
        ['status' => 'diterima']
    )->assertForbidden();
});

it('404s dokumen verification for a pendaftaran belonging to a different lembaga', function () {
    [$lembagaA] = buatPendaftaranUntukAdmin();
    [, $jalurB, , $pendaftaranB] = buatPendaftaranUntukAdmin();
    $syaratB = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalurB->id, 'nama_dokumen' => 'Akta Kelahiran']);
    $dokumenB = DokumenPendaftaran::create([
        'pendaftaran_id' => $pendaftaranB->id, 'dokumen_syarat_ppdb_id' => $syaratB->id,
        'file_path' => 'x.pdf', 'nama_file_asli' => 'x.pdf', 'mime_type' => 'application/pdf', 'ukuran_bytes' => 10,
    ]);
    $user = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $user->assignRole('admin_administrasi');

    $this->actingAs($user)->postJson(
        route('admin.spmb-pendaftaran.verifikasi-dokumen', [$pendaftaranB, $dokumenB]),
        ['status_verifikasi' => 'diterima']
    )->assertNotFound();
});

it('404s nilai entry for a pendaftaran belonging to a different lembaga', function () {
    [$lembagaA] = buatPendaftaranUntukAdmin();
    [, , , $pendaftaranB] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $user->assignRole('admin_administrasi');

    $this->actingAs($user)->postJson(
        route('admin.spmb-pendaftaran.nilai', $pendaftaranB),
        ['seleksi_ppdb_id' => 1, 'nilai' => 80]
    )->assertNotFound();
});

it('404s tetapkan keputusan for a pendaftaran belonging to a different lembaga', function () {
    [$lembagaA] = buatPendaftaranUntukAdmin();
    [, , , $pendaftaranB] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $user->assignRole('kepala_sekolah');

    $this->actingAs($user)->postJson(
        route('admin.spmb-pendaftaran.keputusan', $pendaftaranB),
        ['status' => 'diterima']
    )->assertNotFound();
});

it('shows the detail page and verifies a dokumen for a yayasan-scoped user once an active lembaga is selected in session', function () {
    [$lembaga, $jalur, , $pendaftaran] = buatPendaftaranUntukAdmin();
    $syarat = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    $dokumen = DokumenPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id, 'dokumen_syarat_ppdb_id' => $syarat->id,
        'file_path' => 'x.pdf', 'nama_file_asli' => 'x.pdf', 'mime_type' => 'application/pdf', 'ukuran_bytes' => 10,
    ]);
    $user = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $lembaga->yayasan_id]);
    $user->assignRole('yayasan_super_admin');

    $showResponse = $this->actingAs($user)
        ->withSession(['active_lembaga_id' => $lembaga->id])
        ->get(route('admin.spmb-pendaftaran.show', $pendaftaran));
    $showResponse->assertOk()->assertSee($pendaftaran->calonMurid->nama_lengkap);

    $verifyResponse = $this->actingAs($user)
        ->withSession(['active_lembaga_id' => $lembaga->id])
        ->postJson(
            route('admin.spmb-pendaftaran.verifikasi-dokumen', [$pendaftaran, $dokumen]),
            ['status_verifikasi' => 'diterima']
        );
    $verifyResponse->assertOk();
    expect($dokumen->fresh()->status_verifikasi)->toBe('diterima');
});

it('404s the detail page, not a 500, for a yayasan-scoped user with no active lembaga selected', function () {
    [$lembaga, , , $pendaftaran] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $lembaga->yayasan_id]);
    $user->assignRole('yayasan_super_admin');

    $this->actingAs($user)->get(route('admin.spmb-pendaftaran.show', $pendaftaran))->assertNotFound();
});

it('verifies a dokumen without requiring catatan when accepting', function () {
    [$lembaga, $jalur, , $pendaftaran] = buatPendaftaranUntukAdmin();
    $syarat = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    $dokumen = DokumenPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id, 'dokumen_syarat_ppdb_id' => $syarat->id,
        'file_path' => 'x.pdf', 'nama_file_asli' => 'x.pdf', 'mime_type' => 'application/pdf', 'ukuran_bytes' => 10,
    ]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $response = $this->actingAs($user)->postJson(
        route('admin.spmb-pendaftaran.verifikasi-dokumen', [$pendaftaran, $dokumen]),
        ['status_verifikasi' => 'diterima']
    );

    $response->assertOk();
    expect($dokumen->fresh()->status_verifikasi)->toBe('diterima');
});
