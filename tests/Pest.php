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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function buatLembagaDenganGelombangBuka(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now()->subDay(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 40,
    ]);

    return [$lembaga, $tahunAjaran, $jalur, $gelombang];
}

function loginAkunDenganPilihanSpmb(Lembaga $lembaga, JalurPpdb $jalur): \App\Models\AkunPendaftar
{
    $akun = \App\Models\AkunPendaftar::factory()->create();
    session(['spmb_pilihan.lembaga_id' => $lembaga->id, 'spmb_pilihan.jalur_id' => $jalur->id]);
    test()->actingAs($akun, 'portal');

    return $akun;
}

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

// `actingAsOrangTuaManager()` (defined in OrangTuaCrudTest.php) grants an
// `admin_akademik` role scoped to 'lembaga', but the manager it creates has
// no `lembaga_id`. Siswa uses the BelongsToTenant trait / TenantScope, which
// filters every query by the acting user's lembaga_id — so with a null
// lembaga_id, route-model-binding on {siswa} never resolves (404) regardless
// of controller logic. Widening the manager to a 'yayasan'-scoped role
// bypasses the per-lembaga filter (TenantScope only constrains yayasan-level
// users when `active_lembaga_id` is set in session), which also lets this
// manager link the same orang tua across siswa in different lembaga. Use this
// helper (instead of `actingAsOrangTuaManager()`) for any test hitting a
// {siswa}-bound route.
function actingAsSiswaOrangTuaManager(): User
{
    $manager = actingAsOrangTuaManager();
    $yayasanRole = Role::firstOrCreate(
        ['name' => 'yayasan_admin_ortu_link', 'guard_name' => 'web'],
        ['scope_level' => 'yayasan']
    );
    $manager->assignRole($yayasanRole);

    return $manager;
}
