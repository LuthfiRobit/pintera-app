<?php

use App\Models\CalonMurid;
use App\Models\GelombangPpdb;
use App\Models\HasilSeleksi;
use App\Models\JalurPpdb;
use App\Models\JenisTesMaster;
use App\Models\Pendaftaran;
use App\Models\SeleksiPpdb;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder())->run();
});

it('shows all peserta for a chosen seleksi_ppdb with their existing nilai prefilled', function () {
    [$lembaga, $jalur, $gelombang, $pendaftaranA] = buatPendaftaranUntukAdmin(namaCalon: 'Peserta A');
    [, , , $pendaftaranB] = buatPendaftaranUntukAdmin($lembaga, 'Peserta B');
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id, 'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => now()->addWeek(), 'kriteria_kelulusan' => 'Nilai minimal 65', 'bobot' => 60,
    ]);
    // Peserta B is in a different jalur/gelombang pairing's pendaftaran created independently — re-tie it to the same jalur/gelombang as A for this test.
    $pendaftaranB->update(['jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id]);
    HasilSeleksi::create(['pendaftaran_id' => $pendaftaranA->id, 'seleksi_ppdb_id' => $seleksi->id, 'nilai' => 88]);

    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $response = $this->actingAs($user)->get(route('admin.spmb-pendaftaran.nilai-massal', ['seleksi_ppdb_id' => $seleksi->id]));

    $response->assertOk()->assertSee('Peserta A')->assertSee('Peserta B')->assertSee('88');
});

it('bulk-saves nilai for multiple pendaftaran without duplicating existing hasil_seleksi rows', function () {
    [$lembaga, $jalur, $gelombang, $pendaftaranA] = buatPendaftaranUntukAdmin(namaCalon: 'Peserta A');
    [, , , $pendaftaranB] = buatPendaftaranUntukAdmin($lembaga, 'Peserta B');
    $pendaftaranB->update(['jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id]);
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id, 'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => now()->addWeek(), 'kriteria_kelulusan' => 'Nilai minimal 65', 'bobot' => 60,
    ]);
    HasilSeleksi::create(['pendaftaran_id' => $pendaftaranA->id, 'seleksi_ppdb_id' => $seleksi->id, 'nilai' => 70]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $response = $this->actingAs($user)->postJson(route('admin.spmb-pendaftaran.nilai-massal.store'), [
        'seleksi_ppdb_id' => $seleksi->id,
        'nilai' => [
            $pendaftaranA->id => 95,
            $pendaftaranB->id => 82,
        ],
    ]);

    $response->assertOk();
    expect(HasilSeleksi::where('seleksi_ppdb_id', $seleksi->id)->count())->toBe(2);
    expect((float) HasilSeleksi::where('pendaftaran_id', $pendaftaranA->id)->first()->nilai)->toBe(95.0);
    expect((float) HasilSeleksi::where('pendaftaran_id', $pendaftaranB->id)->first()->nilai)->toBe(82.0);
});

it('re-saving the same batch updates existing hasil_seleksi rows instead of duplicating them', function () {
    [$lembaga, $jalur, $gelombang, $pendaftaranA] = buatPendaftaranUntukAdmin(namaCalon: 'Peserta A');
    [, , , $pendaftaranB] = buatPendaftaranUntukAdmin($lembaga, 'Peserta B');
    $pendaftaranB->update(['jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id]);
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id, 'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => now()->addWeek(), 'kriteria_kelulusan' => 'Nilai minimal 65', 'bobot' => 60,
    ]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $payload = [
        'seleksi_ppdb_id' => $seleksi->id,
        'nilai' => [
            $pendaftaranA->id => 60,
            $pendaftaranB->id => 70,
        ],
    ];

    $this->actingAs($user)->postJson(route('admin.spmb-pendaftaran.nilai-massal.store'), $payload)->assertOk();

    $payload['nilai'][$pendaftaranA->id] = 95;
    $payload['nilai'][$pendaftaranB->id] = 82;

    $this->actingAs($user)->postJson(route('admin.spmb-pendaftaran.nilai-massal.store'), $payload)->assertOk();

    expect(HasilSeleksi::where('seleksi_ppdb_id', $seleksi->id)->count())->toBe(2);
    expect((float) HasilSeleksi::where('pendaftaran_id', $pendaftaranA->id)->first()->nilai)->toBe(95.0);
    expect((float) HasilSeleksi::where('pendaftaran_id', $pendaftaranB->id)->first()->nilai)->toBe(82.0);
});

it('silently skips pendaftaran_ids not tied to the chosen seleksi_ppdb jalur/gelombang or a different lembaga', function () {
    [$lembaga, $jalur, $gelombang, $pendaftaranA] = buatPendaftaranUntukAdmin(namaCalon: 'Peserta A');
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id, 'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => now()->addWeek(), 'kriteria_kelulusan' => 'Nilai minimal 65', 'bobot' => 60,
    ]);

    // Pendaftaran in the SAME lembaga but a DIFFERENT jalur/gelombang than the chosen seleksi_ppdb.
    // (buatPendaftaranUntukAdmin() always reuses the 'Reguler'/'Gelombang 1' jalur/gelombang for a
    // given lembaga, so a genuinely different pairing has to be built by hand here.)
    $jalurLain = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $jalur->tahun_ajaran_id, 'nama' => 'Jalur Lain']);
    $gelombangLain = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $jalur->tahun_ajaran_id, 'nama' => 'Gelombang Lain',
        'tanggal_buka' => now()->subDay(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 40,
    ]);
    $calonMuridLain = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id, 'nama_lengkap' => 'Peserta Luar Gelombang']);
    $pendaftaranLuarGelombang = Pendaftaran::create([
        'calon_murid_id' => $calonMuridLain->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $jalur->tahun_ajaran_id,
        'jalur_ppdb_id' => $jalurLain->id, 'gelombang_ppdb_id' => $gelombangLain->id,
        'kode_pendaftaran' => 'REG-2026-'.random_int(10000, 99999), 'email_pendaftaran' => 'wali2@example.test',
        'status' => 'menunggu_verifikasi', 'submitted_at' => now(),
    ]);

    // Pendaftaran belonging to a DIFFERENT lembaga entirely.
    [, , , $pendaftaranLuarLembaga] = buatPendaftaranUntukAdmin(null, 'Peserta Lembaga Lain');

    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $response = $this->actingAs($user)->postJson(route('admin.spmb-pendaftaran.nilai-massal.store'), [
        'seleksi_ppdb_id' => $seleksi->id,
        'nilai' => [
            $pendaftaranA->id => 90,
            $pendaftaranLuarGelombang->id => 77,
            $pendaftaranLuarLembaga->id => 66,
        ],
    ]);

    $response->assertOk();
    expect(HasilSeleksi::where('seleksi_ppdb_id', $seleksi->id)->count())->toBe(1);
    expect(HasilSeleksi::where('pendaftaran_id', $pendaftaranA->id)->exists())->toBeTrue();
    expect(HasilSeleksi::where('pendaftaran_id', $pendaftaranLuarGelombang->id)->exists())->toBeFalse();
    expect(HasilSeleksi::where('pendaftaran_id', $pendaftaranLuarLembaga->id)->exists())->toBeFalse();
});

it('denies mass nilai entry page access without the nilai-seleksi permission, even with other spmb-pendaftaran permissions', function () {
    [$lembaga, $jalur, $gelombang] = buatPendaftaranUntukAdmin();
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id, 'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => now()->addWeek(), 'kriteria_kelulusan' => 'Nilai minimal 65', 'bobot' => 60,
    ]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->givePermissionTo(['spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.tetapkan-keputusan']);

    $this->actingAs($user)->get(route('admin.spmb-pendaftaran.nilai-massal', ['seleksi_ppdb_id' => $seleksi->id]))
        ->assertForbidden();
});

it('shows peserta and bulk-saves nilai for a yayasan-scoped user once an active lembaga is selected in session', function () {
    [$lembaga, $jalur, $gelombang, $pendaftaranA] = buatPendaftaranUntukAdmin(namaCalon: 'Peserta A');
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id, 'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => now()->addWeek(), 'kriteria_kelulusan' => 'Nilai minimal 65', 'bobot' => 60,
    ]);
    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('yayasan_super_admin');

    $pageResponse = $this->actingAs($user)
        ->withSession(['active_lembaga_id' => $lembaga->id])
        ->get(route('admin.spmb-pendaftaran.nilai-massal', ['seleksi_ppdb_id' => $seleksi->id]));
    $pageResponse->assertOk()->assertSee('Peserta A');

    $storeResponse = $this->actingAs($user)
        ->withSession(['active_lembaga_id' => $lembaga->id])
        ->postJson(route('admin.spmb-pendaftaran.nilai-massal.store'), [
            'seleksi_ppdb_id' => $seleksi->id,
            'nilai' => [$pendaftaranA->id => 88],
        ]);
    $storeResponse->assertOk();
    expect((float) HasilSeleksi::where('pendaftaran_id', $pendaftaranA->id)->first()->nilai)->toBe(88.0);
});

it('returns an empty peserta list on GET and a 422 on POST, not a 500, for a yayasan-scoped user with no active lembaga selected', function () {
    [$lembaga, $jalur, $gelombang, $pendaftaranA] = buatPendaftaranUntukAdmin(namaCalon: 'Peserta A');
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id, 'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => now()->addWeek(), 'kriteria_kelulusan' => 'Nilai minimal 65', 'bobot' => 60,
    ]);
    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('yayasan_super_admin');

    $pageResponse = $this->actingAs($user)
        ->get(route('admin.spmb-pendaftaran.nilai-massal', ['seleksi_ppdb_id' => $seleksi->id]));
    $pageResponse->assertOk();

    // A non-empty, otherwise-valid payload: without the guard this reaches
    // SeleksiPpdb::where('lembaga_id', null)->findOrFail(...), which throws
    // (404), not 422 — so this only passes 422 once the guard short-circuits
    // before that query runs. An empty 'nilai' array would 422 anyway on the
    // "required" rule alone and wouldn't prove the guard did anything.
    $storeResponse = $this->actingAs($user)->postJson(route('admin.spmb-pendaftaran.nilai-massal.store'), [
        'seleksi_ppdb_id' => $seleksi->id,
        'nilai' => [$pendaftaranA->id => 80],
    ]);
    $storeResponse->assertStatus(422);
});

it('denies mass nilai entry without the nilai-seleksi permission, even with other spmb-pendaftaran permissions', function () {
    [$lembaga, $jalur, $gelombang] = buatPendaftaranUntukAdmin();
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id, 'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => now()->addWeek(), 'kriteria_kelulusan' => 'Nilai minimal 65', 'bobot' => 60,
    ]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->givePermissionTo(['spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.tetapkan-keputusan']);

    $this->actingAs($user)->postJson(route('admin.spmb-pendaftaran.nilai-massal.store'), [
        'seleksi_ppdb_id' => $seleksi->id,
        'nilai' => [],
    ])->assertForbidden();
});
