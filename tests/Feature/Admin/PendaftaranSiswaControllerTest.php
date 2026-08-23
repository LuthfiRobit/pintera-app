<?php

use App\Enums\SumberDataSiswa;
use App\Models\AkunPendaftar;
use App\Models\CalonMurid;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\Role;
use App\Models\Siswa;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

function buatPendaftaranAktif(Lembaga $lembaga, TahunAjaran $tahunAjaran): Pendaftaran
{
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler '.uniqid()]);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Gelombang '.uniqid(), 'tanggal_buka' => now()->subDays(10), 'tanggal_tutup' => now()->addDays(10), 'kuota' => 30,
    ]);
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id, 'nama_lengkap' => 'Calon Siswa '.uniqid()]);
    $akun = AkunPendaftar::factory()->create();

    $pendaftaran = Pendaftaran::create([
        'calon_murid_id' => $calonMurid->id,
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id,
        'gelombang_ppdb_id' => $gelombang->id,
        'akun_pendaftar_id' => $akun->id,
        'email_pendaftaran' => $akun->email,
        'kode_pendaftaran' => 'PDFTR-'.uniqid(),
        'submitted_at' => now(),
        'status' => 'diterima',
    ]);

    // Pendaftaran::isAktif() requires a paid daftar_ulang tagihan on top of
    // status = 'diterima' — without this the siapDidaftarkanSebagaiSiswa
    // scope excludes the row entirely, so create one here.
    Tagihan::factory()->create([
        'pendaftaran_id' => $pendaftaran->id,
        'kategori' => 'daftar_ulang',
        'total_tagihan' => 150000,
        'status' => 'lunas',
    ]);

    return $pendaftaran;
}

function actingAsSpmbDaftarManager(Lembaga $lembaga): User
{
    foreach (['siswa.spmb-daftar'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['siswa.spmb-daftar']);
    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access without siswa.spmb-daftar permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.siswa.spmb-daftar.index'))->assertForbidden();
});

it('lists only pendaftaran that are aktif and not yet converted to siswa', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsSpmbDaftarManager($lembaga);

    $pendaftaran = buatPendaftaranAktif($lembaga, $tahunAjaran);

    $response = $this->actingAs($manager)->get(route('admin.siswa.spmb-daftar.index'));

    $response->assertOk();
    $response->assertViewHas('pendaftaranList', function ($list) use ($pendaftaran) {
        return $list->contains('id', $pendaftaran->id);
    });
});

it('batch-creates siswa from checked pendaftaran with a shared target kelas', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $manager = actingAsSpmbDaftarManager($lembaga);

    $pendaftaranA = buatPendaftaranAktif($lembaga, $tahunAjaran);
    $pendaftaranB = buatPendaftaranAktif($lembaga, $tahunAjaran);

    $this->actingAs($manager)->post(route('admin.siswa.spmb-daftar.store'), [
        'kelas_id' => $kelas->id,
        'pendaftaran_ids' => [$pendaftaranA->id, $pendaftaranB->id],
        'nis' => [
            $pendaftaranA->id => '2026101',
            $pendaftaranB->id => '2026102',
        ],
    ])->assertRedirect(route('admin.siswa.index'));

    $siswaA = Siswa::where('pendaftaran_asal_id', $pendaftaranA->id)->firstOrFail();
    $siswaB = Siswa::where('pendaftaran_asal_id', $pendaftaranB->id)->firstOrFail();

    expect($siswaA->sumber_data)->toBe(SumberDataSiswa::Spmb);
    expect($siswaA->kelas_id)->toBe($kelas->id);
    expect($siswaA->calon_murid_id)->toBe($pendaftaranA->calon_murid_id);
    expect($siswaA->nis)->toBe('2026101');
    expect($siswaB->nis)->toBe('2026102');
});

it('creates a login account for the siswa promoted from an accepted pendaftaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'kode_lembaga' => 'SMA1']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $manager = actingAsSpmbDaftarManager($lembaga);

    $pendaftaran = buatPendaftaranAktif($lembaga, $tahunAjaran);
    $nisSubmitted = '2026501';

    $this->actingAs($manager)->post(route('admin.siswa.spmb-daftar.store'), [
        'kelas_id' => $kelas->id,
        'pendaftaran_ids' => [$pendaftaran->id],
        'nis' => [$pendaftaran->id => $nisSubmitted],
    ])->assertRedirect(route('admin.siswa.index'));

    $siswa = Siswa::where('pendaftaran_asal_id', $pendaftaran->id)->firstOrFail();

    expect($siswa->user_id)->not->toBeNull();
    expect($siswa->user->username)->toBe($lembaga->kode_lembaga.'-'.$nisSubmitted);
    expect(Hash::check($nisSubmitted, $siswa->user->password))->toBeTrue();
});

it('does not create a siswa for a pendaftaran that was not checked', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $manager = actingAsSpmbDaftarManager($lembaga);

    $pendaftaranChecked = buatPendaftaranAktif($lembaga, $tahunAjaran);
    $pendaftaranUnchecked = buatPendaftaranAktif($lembaga, $tahunAjaran);

    $this->actingAs($manager)->post(route('admin.siswa.spmb-daftar.store'), [
        'kelas_id' => $kelas->id,
        'pendaftaran_ids' => [$pendaftaranChecked->id],
        'nis' => [$pendaftaranChecked->id => '2026201'],
    ]);

    expect(Siswa::where('pendaftaran_asal_id', $pendaftaranChecked->id)->exists())->toBeTrue();
    expect(Siswa::where('pendaftaran_asal_id', $pendaftaranUnchecked->id)->exists())->toBeFalse();
});

it('rejects registering a pendaftaran into a kelas belonging to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranSaya = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $kelasLain = Kelas::withoutGlobalScopes()->create([
        'lembaga_id' => $lembagaLain->id,
        'tahun_ajaran_id' => $tahunAjaranLain->id,
        'nama' => 'Kelas Lembaga Lain',
    ]);
    $manager = actingAsSpmbDaftarManager($lembagaSaya);

    $pendaftaran = buatPendaftaranAktif($lembagaSaya, $tahunAjaranSaya);

    $this->actingAs($manager)->post(route('admin.siswa.spmb-daftar.store'), [
        'kelas_id' => $kelasLain->id,
        'pendaftaran_ids' => [$pendaftaran->id],
        'nis' => [$pendaftaran->id => '2026401'],
    ])->assertNotFound();

    expect(Siswa::where('pendaftaran_asal_id', $pendaftaran->id)->exists())->toBeFalse();
});

it('rejects the whole batch with a validation error and creates zero siswa when a submitted NIS already belongs to an existing siswa', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $manager = actingAsSpmbDaftarManager($lembaga);

    $pendaftaranA = buatPendaftaranAktif($lembaga, $tahunAjaran);
    $pendaftaranB = buatPendaftaranAktif($lembaga, $tahunAjaran);

    // The second row's hand-typed NIS collides with an existing student. This must be
    // caught by validation BEFORE the transaction opens — not surface as an uncaught
    // QueryException — so neither siswa in the batch gets created.
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nis' => '2026302']);

    $this->actingAs($manager)->post(route('admin.siswa.spmb-daftar.store'), [
        'kelas_id' => $kelas->id,
        'pendaftaran_ids' => [$pendaftaranA->id, $pendaftaranB->id],
        'nis' => [
            $pendaftaranA->id => '2026301',
            $pendaftaranB->id => '2026302',
        ],
    ])->assertSessionHasErrors('nis');

    expect(Siswa::where('pendaftaran_asal_id', $pendaftaranA->id)->exists())->toBeFalse();
    expect(Siswa::where('pendaftaran_asal_id', $pendaftaranB->id)->exists())->toBeFalse();
});

it('rejects a blank NIS instead of creating an account with an empty password', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $manager = actingAsSpmbDaftarManager($lembaga);

    $pendaftaran = buatPendaftaranAktif($lembaga, $tahunAjaran);

    $this->actingAs($manager)->post(route('admin.siswa.spmb-daftar.store'), [
        'kelas_id' => $kelas->id,
        'pendaftaran_ids' => [$pendaftaran->id],
        'nis' => [$pendaftaran->id => ''],
    ])->assertSessionHasErrors('nis.'.$pendaftaran->id);

    expect(Siswa::where('pendaftaran_asal_id', $pendaftaran->id)->exists())->toBeFalse();
});

it('rejects two identical NIS values submitted for two different pendaftaran in the same batch', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $manager = actingAsSpmbDaftarManager($lembaga);

    $pendaftaranA = buatPendaftaranAktif($lembaga, $tahunAjaran);
    $pendaftaranB = buatPendaftaranAktif($lembaga, $tahunAjaran);

    $this->actingAs($manager)->post(route('admin.siswa.spmb-daftar.store'), [
        'kelas_id' => $kelas->id,
        'pendaftaran_ids' => [$pendaftaranA->id, $pendaftaranB->id],
        'nis' => [
            $pendaftaranA->id => '2026901',
            $pendaftaranB->id => '2026901',
        ],
    ])->assertSessionHasErrors('nis');

    expect(Siswa::where('pendaftaran_asal_id', $pendaftaranA->id)->exists())->toBeFalse();
    expect(Siswa::where('pendaftaran_asal_id', $pendaftaranB->id)->exists())->toBeFalse();
});
