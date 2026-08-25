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
use Spatie\Permission\Models\Permission;
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

// Shared fixture for OrangTuaCrudTest.php and SiswaOrangTuaLinkingTest.php: a manager with
// full orang-tua.* permissions plus siswa.edit (SiswaOrangTuaController requires both, since
// it mutates a specific student's data — see Finding 3 of the final review).
function actingAsOrangTuaManager(): User
{
    foreach (['orang-tua.view', 'orang-tua.create', 'orang-tua.edit', 'siswa.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'operator_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['orang-tua.view', 'orang-tua.create', 'orang-tua.edit', 'siswa.edit']);
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $manager = User::factory()->create();
    $manager->assignRole($role);

    return $manager;
}

// `actingAsSiswaOrangTuaManager()` grants an `operator_akademik` role scoped to 'lembaga', but
// the manager it creates has no `lembaga_id`. Siswa uses the BelongsToTenant trait /
// TenantScope, which filters every query by the acting user's lembaga_id — so with a null
// lembaga_id, route-model-binding on {siswa} never resolves (404) regardless of controller
// logic. Widening the manager to a 'yayasan'-scoped role, WITH a `yayasan_id` set (a
// correctly-provisioned yayasan-scope account always has one — see UserController::store()),
// lets this manager cross-link the same orang tua across siswa in different lembaga UNDER
// THAT SAME YAYASAN (TenantScope now scopes an empty active_lembaga_id session down to the
// actor's own yayasan, not left unfiltered). Use this helper (instead of
// `actingAsOrangTuaManager()`) for any test hitting a {siswa}-bound route where cross-lembaga
// behavior for a yayasan-level user is intended — it does NOT exercise tenant isolation for
// an ordinary lembaga-scoped admin; see the dedicated tenant-isolation test in
// SiswaOrangTuaLinkingTest.php for that case. Callers MUST create any {siswa} this manager
// needs to reach under a Lembaga belonging to `$manager->yayasan_id` (see
// `buatSiswaUntukTautan()` in SiswaOrangTuaLinkingTest.php).
function actingAsSiswaOrangTuaManager(): User
{
    $manager = actingAsOrangTuaManager();
    $yayasanRole = Role::firstOrCreate(
        ['name' => 'yayasan_admin_ortu_link', 'guard_name' => 'web'],
        ['scope_level' => 'yayasan']
    );
    $manager->update(['yayasan_id' => \App\Models\Yayasan::factory()->create()->id]);
    $manager->assignRole($yayasanRole);

    return $manager;
}
