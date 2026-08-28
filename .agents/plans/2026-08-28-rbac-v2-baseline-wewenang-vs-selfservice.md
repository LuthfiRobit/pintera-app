# RBAC v2 — Fix `kasus.view` Baseline vs Assignment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Hapus `kasus.view` dari baseline role `pegawai_lembaga`/`pegawai_yayasan`, hapus 8 gate permission `kasus.view` yang redundan dengan Policy, dan tambah `KasusPolicy::viewAny()` sebagai gate collection-level berbasis capability ATAU fakta domain (`konselor_karyawan_id`) — supaya karyawan yang tidak pernah ditugaskan konselor (mis. satpam) tidak lagi otomatis dapat akses ke menu Kasus Pendampingan, sementara karyawan pool yang sungguh ditugaskan tetap bisa bekerja.

**Architecture:** Permission tetap dipakai sebagai capability-gate untuk baseline role (`guru`/`siswa`/`orang_tua` tidak berubah). Untuk `pegawai_lembaga`/`pegawai_yayasan`, capability `kasus.view` dicabut dari baseline; akses collection-level (`kasus.index`) sekarang diputuskan lewat `KasusPolicy::viewAny()` yang mengembalikan `true` kalau user punya capability `kasus.view` (lewat role apa pun) ATAU minimal satu record `Kasus.konselor_karyawan_id` menunjuk ke karyawan tersebut. Otorisasi per-resource (`show`, `sesi`, `tugas`, dll) TIDAK berubah — itu semua sudah diputuskan `KasusPolicy::view()`/`isKonselor()`/`kelolaSesiTugas()` berbasis relasi data, gate `kasus.view` di depannya cuma redundan dan dihapus.

**Tech Stack:** Laravel 12, PHP 8.3, Spatie Laravel Permission, Pest v4, MySQL.

## Global Constraints

- Baseline `guru`, `siswa`, `orang_tua` di `RoleSeeder.php` **TIDAK BOLEH diubah** sama sekali.
- `Admin\KasusController` (semua method) **TIDAK BOLEH diubah** sama sekali.
- `ListKasusUntukUserAction` **TIDAK BOLEH diubah** — query scoping-nya sudah benar dan jadi acuan definisi "assigned" di spec §1.5.
- `KasusPolicy::isKonselor()`, `view()`, `downloadLampiran()`, `kelolaSesiTugas()` **TIDAK BOLEH diubah** — hanya menambah method baru `viewAny()`.
- Tidak ada migration/schema baru. Tidak ada role baru. Tidak ada `assignRole()`/`removeRole()` baru di `AssignKonselorAction` atau di manapun.
- `viewAny()` HARUS memakai OR dua kondisi independen: `$user->can('kasus.view')` ATAU fakta domain (`konselor_karyawan_id`) — bukan salah satu menggantikan yang lain (spec §2.2(c)).
- `withoutGlobalScope(TenantScope::class)` di `viewAny()` **WAJIB dipertahankan persis seperti di spec** — ini invariant yang sudah dianalisis dan disetujui, JANGAN dihapus atau "diperbaiki" dengan menambah filter `lembaga_id` manual.
- Definisi "assigned konselor" TIDAK memakai filter status apa pun (tanpa konsep "aktif") — `exists()` murni berbasis `konselor_karyawan_id`.
- Full spec: `.agents/specs/2026-08-28-rbac-v2-baseline-wewenang-vs-selfservice.md`.

---

### Task 1: `RoleSeeder.php` — keluarkan `kasus.view` dari baseline karyawan + guardrail allowlist test

**Files:**
- Modify: `database/seeders/RoleSeeder.php:170-172`
- Test: `tests/Unit/RoleSeederTest.php`

**Interfaces:**
- Consumes: tidak ada (task pertama, tidak bergantung task lain).
- Produces: baseline permission list final untuk `guru`, `pegawai_lembaga`, `pegawai_yayasan`, `siswa`, `orang_tua` — dipakai sebagai acuan test Task 4 (4.5, 4.5b) dan Task 5 (4.1-4.6).

- [ ] **Step 1: Baca ulang `RoleSeeder.php` baris 159-172 untuk konfirmasi baseline sebelum edit**

Jalankan:
```bash
sed -n '159,172p' database/seeders/RoleSeeder.php
```
Pastikan isinya PERSIS:
```php
            if ($name === 'orang_tua') {
                $role->givePermissionTo([
                    'kasus.ajukan', 'kasus.view', 'kasus.consent',
                    'keuangan.akses',
                ]);
            }

            if ($name === 'siswa') {
                $role->givePermissionTo(['kasus.view']);
            }

            if (in_array($name, ['pegawai_lembaga', 'pegawai_yayasan'], true)) {
                $role->givePermissionTo(['kasus.view', 'kehadiran-sdm.lihat-qr-sendiri', 'kehadiran-sdm.izin.ajukan', 'kehadiran-sdm.izin.lihat-sendiri']);
            }
```
Kalau berbeda, STOP dan laporkan ke user — jangan lanjut edit di atas asumsi yang salah.

- [ ] **Step 2: Tulis 5 test allowlist baseline (gagal dulu karena baseline masih lama)**

Tambahkan di akhir `tests/Unit/RoleSeederTest.php` (sebelum closing tag kalau ada, atau di akhir file):
```php
it('gives guru EXACTLY the self-service baseline permission set', function () {
    (new RoleSeeder)->run();

    $guru = Role::where('name', 'guru')->firstOrFail();
    expect($guru->permissions()->pluck('name')->sort()->values()->all())->toBe([
        'asesmen.kelola',
        'kasus.ajukan',
        'kasus.view',
        'kehadiran-sdm.izin.ajukan',
        'kehadiran-sdm.izin.lihat-sendiri',
        'kehadiran-sdm.lihat-qr-sendiri',
        'komponen-penilaian.kelola-sendiri',
        'presensi.isi',
        'rapor.ajukan',
        'rapor.input-wali',
        'rpp.kelola',
        'rpp.view',
    ]);
});

it('gives pegawai_lembaga EXACTLY the self-service baseline permission set (no kasus.view)', function () {
    (new RoleSeeder)->run();

    $role = Role::where('name', 'pegawai_lembaga')->firstOrFail();
    expect($role->permissions()->pluck('name')->sort()->values()->all())->toBe([
        'kehadiran-sdm.izin.ajukan',
        'kehadiran-sdm.izin.lihat-sendiri',
        'kehadiran-sdm.lihat-qr-sendiri',
    ]);
});

it('gives pegawai_yayasan EXACTLY the self-service baseline permission set (no kasus.view)', function () {
    (new RoleSeeder)->run();

    $role = Role::where('name', 'pegawai_yayasan')->firstOrFail();
    expect($role->permissions()->pluck('name')->sort()->values()->all())->toBe([
        'kehadiran-sdm.izin.ajukan',
        'kehadiran-sdm.izin.lihat-sendiri',
        'kehadiran-sdm.lihat-qr-sendiri',
    ]);
});

it('gives siswa EXACTLY the self-service baseline permission set', function () {
    (new RoleSeeder)->run();

    $siswa = Role::where('name', 'siswa')->firstOrFail();
    expect($siswa->permissions()->pluck('name')->sort()->values()->all())->toBe([
        'kasus.view',
    ]);
});

it('gives orang_tua EXACTLY the self-service baseline permission set', function () {
    (new RoleSeeder)->run();

    $orangTua = Role::where('name', 'orang_tua')->firstOrFail();
    expect($orangTua->permissions()->pluck('name')->sort()->values()->all())->toBe([
        'kasus.ajukan',
        'kasus.consent',
        'kasus.view',
        'keuangan.akses',
    ]);
});
```

- [ ] **Step 2b: Jalankan test baru, pastikan `pegawai_lembaga`/`pegawai_yayasan` GAGAL (bukti test menangkap baseline lama)**

Run: `php artisan test --filter="gives pegawai_lembaga EXACTLY" --compact`
Expected: FAIL — array actual masih mengandung `'kasus.view'` di posisi awal, tidak cocok dengan array expected yang sudah tidak punya `kasus.view`.

Run: `php artisan test --filter="gives guru EXACTLY" --compact`
Run: `php artisan test --filter="gives siswa EXACTLY" --compact`
Run: `php artisan test --filter="gives orang_tua EXACTLY" --compact`
Expected: ketiganya PASS (baseline mereka tidak berubah task ini).

- [ ] **Step 3: Edit `RoleSeeder.php` — keluarkan `kasus.view` dari baseline karyawan**

Ubah baris 170-172 dari:
```php
            if (in_array($name, ['pegawai_lembaga', 'pegawai_yayasan'], true)) {
                $role->givePermissionTo(['kasus.view', 'kehadiran-sdm.lihat-qr-sendiri', 'kehadiran-sdm.izin.ajukan', 'kehadiran-sdm.izin.lihat-sendiri']);
            }
```
menjadi:
```php
            if (in_array($name, ['pegawai_lembaga', 'pegawai_yayasan'], true)) {
                $role->givePermissionTo(['kehadiran-sdm.lihat-qr-sendiri', 'kehadiran-sdm.izin.ajukan', 'kehadiran-sdm.izin.lihat-sendiri']);
            }
```
Baris lain (`guru`, `siswa`, `orang_tua`) TIDAK disentuh.

- [ ] **Step 4: Jalankan `php -l` untuk cek syntax**

Run: `php -l database/seeders/RoleSeeder.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Jalankan ulang 5 test allowlist, semua HARUS PASS sekarang**

Run: `php artisan test --filter="EXACTLY the self-service baseline" --compact`
Expected: 5 passed, 0 failed.

- [ ] **Step 6: Jalankan seluruh `RoleSeederTest.php` untuk pastikan tidak ada regresi ke test lama di file yang sama**

Run: `php artisan test tests/Unit/RoleSeederTest.php --compact`
Expected: semua test di file ini PASS (termasuk test lama seperti `it('is idempotent when run twice')`, `it('seeds operator_akademik with the correct 54 academic-management permissions')`, dll — jumlah permission role lain tidak terpengaruh perubahan ini).

- [ ] **Step 7: Commit**

```bash
git add database/seeders/RoleSeeder.php tests/Unit/RoleSeederTest.php
git commit -m "$(cat <<'EOF'
fix(rbac): keluarkan kasus.view dari baseline pegawai_lembaga/pegawai_yayasan

Karyawan (termasuk satpam/cleaning service/sopir) tidak lagi otomatis
punya akses ke menu Kasus Pendampingan lewat baseline. Akses konselor
karyawan yang sungguh ditugaskan akan dipulihkan lewat KasusPolicy::viewAny()
di task berikutnya, berbasis relasi data (konselor_karyawan_id), bukan
permission global.

+5 test allowlist guardrail exact-permission-set untuk 5 baseline role.
EOF
)"
```

---

### Task 2: `KasusPolicy::viewAny()` — gate collection-level baru

**Files:**
- Modify: `app/Domains/Kasus/Policies/KasusPolicy.php`
- Test: `tests/Unit/Domains/Kasus/Policies/KasusPolicyTest.php`

**Interfaces:**
- Consumes: `Kasus` model (`app/Domains/Kasus/Models/Kasus.php`, kolom `konselor_karyawan_id`), `User::karyawan()` relation, `TenantScope` (`App\Models\Scopes\TenantScope`).
- Produces: `KasusPolicy::viewAny(User $user): bool` — dipakai Task 3 di `KasusController::index()` via `$this->authorize('viewAny', Kasus::class)`.

- [ ] **Step 1: Baca `tests/Unit/Domains/Kasus/Policies/KasusPolicyTest.php` untuk cocokkan gaya test existing**

Run: `sed -n '1,40p' tests/Unit/Domains/Kasus/Policies/KasusPolicyTest.php`

Gunakan pola factory/setup yang sama (lihat file itu) untuk konsistensi. Kalau file tidak ada test yang bisa dicontoh untuk `Karyawan`, gunakan pola dari `tests/Feature/KasusKonselorAksesTest.php` (`Karyawan::withoutGlobalScopes()->create([...])`, `JenisKaryawanMaster::factory()->create(['is_konselor' => true])`).

- [ ] **Step 2: Tulis failing test untuk `viewAny()` — 4 skenario**

Tambahkan di `tests/Unit/Domains/Kasus/Policies/KasusPolicyTest.php`:
```php
use App\Domains\Kasus\Enums\StatusKasus;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Policies\KasusPolicy;
use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('viewAny returns true when user has kasus.view capability, regardless of konselor history', function () {
    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_bk', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kasus.view']);

    $lembaga = Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('guru_bk');

    expect((new KasusPolicy)->viewAny($user))->toBeTrue();
});

it('viewAny returns true when karyawan is konselor on at least one kasus, without kasus.view capability', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $user = User::factory()->create(['lembaga_id' => null]);
    $jenis = JenisKaryawanMaster::factory()->create(['is_konselor' => true]);
    $karyawan = Karyawan::withoutGlobalScopes()->create([
        'user_id' => $user->id, 'yayasan_id' => $yayasan->id, 'lembaga_id' => null,
        'jenis_karyawan_id' => $jenis->id, 'nama' => 'Karyawan Pool',
        'nik' => fake()->unique()->numerify('################'), 'status_aktif' => 'aktif',
    ]);
    Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
        'status' => StatusKasus::Ditugaskan, 'konselor_karyawan_id' => $karyawan->id,
    ]);

    expect((new KasusPolicy)->viewAny($user))->toBeTrue();
});

it('viewAny returns false for karyawan with no kasus.view capability and never assigned as konselor', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenis = JenisKaryawanMaster::factory()->create(['is_konselor' => false]);
    Karyawan::withoutGlobalScopes()->create([
        'user_id' => $user->id, 'lembaga_id' => $lembaga->id,
        'jenis_karyawan_id' => $jenis->id, 'nama' => 'Satpam',
        'nik' => fake()->unique()->numerify('################'), 'status_aktif' => 'aktif',
    ]);

    expect((new KasusPolicy)->viewAny($user))->toBeFalse();
});

it('viewAny returns true for karyawan whose only konselor assignment is on a Selesai kasus (no status filter)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $user = User::factory()->create(['lembaga_id' => null]);
    $jenis = JenisKaryawanMaster::factory()->create(['is_konselor' => true]);
    $karyawan = Karyawan::withoutGlobalScopes()->create([
        'user_id' => $user->id, 'yayasan_id' => $yayasan->id, 'lembaga_id' => null,
        'jenis_karyawan_id' => $jenis->id, 'nama' => 'Karyawan Pool Selesai',
        'nik' => fake()->unique()->numerify('################'), 'status_aktif' => 'aktif',
    ]);
    Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
        'status' => StatusKasus::Selesai, 'konselor_karyawan_id' => $karyawan->id,
    ]);

    expect((new KasusPolicy)->viewAny($user))->toBeTrue();
});
```

- [ ] **Step 3: Jalankan test, pastikan GAGAL karena method belum ada**

Run: `php artisan test --filter="viewAny" --compact`
Expected: FAIL dengan error `Call to undefined method App\Domains\Kasus\Policies\KasusPolicy::viewAny()`

- [ ] **Step 4: Tambahkan method `viewAny()` ke `KasusPolicy.php`**

Tambahkan di `app/Domains/Kasus/Policies/KasusPolicy.php`, setelah method `kelolaSesiTugas()` (sebelum closing brace class), dan tambahkan `use App\Models\Scopes\TenantScope;` ke bagian `use` di atas file (ikuti gaya import lain di file ini yang pakai `use` statement, bukan FQCN inline, untuk konsistensi dengan method baru — method `isKonselor()` existing memang pakai FQCN inline dan TIDAK diubah):

```php
use App\Models\Scopes\TenantScope;
```
(tambahkan baris ini di blok `use` paling atas file, setelah `use App\Models\User;`)

```php
    /**
     * Gate collection-level untuk kasus.index. True kalau user punya capability
     * kasus.view (lewat role apa pun — guru/siswa/orang_tua baseline, atau
     * guru_bk/wakasek_kesiswaan/operator_akademik assignment eksplisit) ATAU
     * minimal satu Kasus punya konselor_karyawan_id = karyawan user ini —
     * fakta domain, bukan role/permission. withoutGlobalScope dipakai sengaja
     * karena karyawan pool level-yayasan (lembaga_id null) valid menangani
     * kasus lintas lembaga dalam yayasannya; validitas hubungan tetap dijaga
     * oleh konselor_karyawan_id itu sendiri, bukan TenantScope. Tidak ada
     * filter status — assignment konselor bersifat historis mengikuti
     * perilaku ListKasusUntukUserAction yang sudah ada.
     */
    public function viewAny(User $user): bool
    {
        if ($user->can('kasus.view')) {
            return true;
        }

        $karyawanId = $user->karyawan()->withoutGlobalScope(TenantScope::class)->first()?->id;

        return $karyawanId !== null && Kasus::withoutGlobalScope(TenantScope::class)
            ->where('konselor_karyawan_id', $karyawanId)
            ->exists();
    }
```

- [ ] **Step 5: Jalankan `php -l` untuk cek syntax**

Run: `php -l app/Domains/Kasus/Policies/KasusPolicy.php`
Expected: `No syntax errors detected`

- [ ] **Step 6: Jalankan test `viewAny`, semua HARUS PASS**

Run: `php artisan test --filter="viewAny" --compact`
Expected: 4 passed, 0 failed.

- [ ] **Step 7: Jalankan seluruh `KasusPolicyTest.php` untuk pastikan tidak ada regresi ke test method lain (`isKonselor`, `view`, `downloadLampiran`, `kelolaSesiTugas`)**

Run: `php artisan test tests/Unit/Domains/Kasus/Policies/KasusPolicyTest.php --compact`
Expected: semua PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Domains/Kasus/Policies/KasusPolicy.php tests/Unit/Domains/Kasus/Policies/KasusPolicyTest.php
git commit -m "$(cat <<'EOF'
feat(rbac): tambah KasusPolicy::viewAny() untuk gate collection-level kasus.index

true kalau user->can('kasus.view') ATAU punya minimal satu Kasus dengan
konselor_karyawan_id miliknya (fakta domain, tanpa filter status).
withoutGlobalScope(TenantScope) dipertahankan sengaja untuk karyawan pool
level-yayasan (lembaga_id null) — konsisten dengan pola isKonselor() dan
Route::bind('kasus', ...) yang sudah ada.
EOF
)"
```

---

### Task 3: `KasusController::index()` — pakai `viewAny()`, hapus gate redundan di 8 endpoint

**Files:**
- Modify: `app/Http/Controllers/KasusController.php` (index + show)
- Modify: `app/Http/Controllers/KasusSesiController.php` (store + updateStatus)
- Modify: `app/Http/Controllers/KasusTugasController.php` (store + markSelesai)
- Modify: `app/Http/Controllers/KasusTugasBatchPreviewController.php` (preview)
- Modify: `app/Http/Controllers/KasusTugasSubmissionController.php` (store + review + download)
- Modify: `app/Http/Controllers/KasusEvaluasiController.php` (store)

**Interfaces:**
- Consumes: `KasusPolicy::viewAny()` dari Task 2.
- Produces: tidak ada interface baru untuk task lain — task ini murni menghapus gate lama dan mengganti satu gate di `index()`.

- [ ] **Step 1: `KasusController.php` — ganti gate di `index()`**

Baris 23-27 saat ini:
```php
    public function index(Request $request, ListKasusUntukUserAction $action): View
    {
        $this->authorize('kasus.view');

        $kasusList = $action->execute($request->user());
```
Ganti menjadi:
```php
    public function index(Request $request, ListKasusUntukUserAction $action): View
    {
        $this->authorize('viewAny', Kasus::class);

        $kasusList = $action->execute($request->user());
```
(`Kasus` dan `TenantScope` sudah di-import di file ini — tidak perlu tambah `use` baru.)

- [ ] **Step 2: `KasusController.php` — hapus gate redundan di `show()`**

Baris 108-110 saat ini:
```php
    public function show(Kasus $kasus, KasusPolicy $policy): View
    {
        $this->authorize('kasus.view');

        $user = auth()->user();
```
Ganti menjadi (hapus baris `$this->authorize('kasus.view');` dan baris kosong setelahnya):
```php
    public function show(Kasus $kasus, KasusPolicy $policy): View
    {
        $user = auth()->user();
```

- [ ] **Step 3: `KasusSesiController.php` — hapus gate redundan di `store()` dan `updateStatus()`**

Baris 21-24 saat ini:
```php
    public function store(Request $request, Kasus $kasus, JadwalkanSesiAction $action): RedirectResponse
    {
        $this->authorize('kasus.view');
        $this->authorize('kelolaSesiTugas', $kasus);
        abort_if($kasus->trashed(), 404);
```
Ganti menjadi:
```php
    public function store(Request $request, Kasus $kasus, JadwalkanSesiAction $action): RedirectResponse
    {
        $this->authorize('kelolaSesiTugas', $kasus);
        abort_if($kasus->trashed(), 404);
```

Baris 40-43 saat ini:
```php
    public function updateStatus(Request $request, Kasus $kasus, KasusSesi $kasusSesi, UpdateStatusSesiAction $action): RedirectResponse
    {
        $this->authorize('kasus.view');
        $this->authorize('kelolaSesiTugas', $kasus);
        abort_if($kasusSesi->kasus_id !== $kasus->id, 404);
```
Ganti menjadi:
```php
    public function updateStatus(Request $request, Kasus $kasus, KasusSesi $kasusSesi, UpdateStatusSesiAction $action): RedirectResponse
    {
        $this->authorize('kelolaSesiTugas', $kasus);
        abort_if($kasusSesi->kasus_id !== $kasus->id, 404);
```

- [ ] **Step 4: `KasusTugasController.php` — hapus gate redundan di `store()` dan `markSelesai()`**

Baris 20-24 saat ini:
```php
    public function store(StoreKasusTugasBatchRequest $request, Kasus $kasus, BeriTugasBatchAction $action): RedirectResponse
    {
        $this->authorize('kasus.view');
        $this->authorize('kelolaSesiTugas', $kasus);
        abort_if($kasus->trashed(), 404);
```
Ganti menjadi:
```php
    public function store(StoreKasusTugasBatchRequest $request, Kasus $kasus, BeriTugasBatchAction $action): RedirectResponse
    {
        $this->authorize('kelolaSesiTugas', $kasus);
        abort_if($kasus->trashed(), 404);
```

Baris 41-45 saat ini:
```php
    public function markSelesai(Kasus $kasus, KasusTugas $kasusTugas, TandaiTugasSelesaiAction $action): RedirectResponse
    {
        $this->authorize('kasus.view');
        abort_if($kasusTugas->kasus_id !== $kasus->id, 404);
        $this->authorize('kelolaSesiTugas', $kasus);
```
Ganti menjadi:
```php
    public function markSelesai(Kasus $kasus, KasusTugas $kasusTugas, TandaiTugasSelesaiAction $action): RedirectResponse
    {
        abort_if($kasusTugas->kasus_id !== $kasus->id, 404);
        $this->authorize('kelolaSesiTugas', $kasus);
```

- [ ] **Step 5: `KasusTugasBatchPreviewController.php` — hapus gate redundan di `preview()`**

Baris 18-22 saat ini:
```php
    public function preview(PreviewKasusTugasBatchRequest $request, Kasus $kasus, TugasBatchGenerator $generator): JsonResponse
    {
        $this->authorize('kasus.view');
        $this->authorize('kelolaSesiTugas', $kasus);
        abort_if($kasus->trashed(), 404);
```
Ganti menjadi:
```php
    public function preview(PreviewKasusTugasBatchRequest $request, Kasus $kasus, TugasBatchGenerator $generator): JsonResponse
    {
        $this->authorize('kelolaSesiTugas', $kasus);
        abort_if($kasus->trashed(), 404);
```

- [ ] **Step 6: `KasusTugasSubmissionController.php` — hapus gate redundan di `store()`, `review()`, `download()`**

Baris 26-29 saat ini:
```php
    public function store(Request $request, Kasus $kasus, KasusTugas $kasusTugas, SubmitBuktiTugasAction $action): RedirectResponse
    {
        $this->authorize('kasus.view');
        abort_if($kasusTugas->kasus_id !== $kasus->id, 404);
```
Ganti menjadi:
```php
    public function store(Request $request, Kasus $kasus, KasusTugas $kasusTugas, SubmitBuktiTugasAction $action): RedirectResponse
    {
        abort_if($kasusTugas->kasus_id !== $kasus->id, 404);
```

Baris 67-70 saat ini:
```php
    public function review(Request $request, Kasus $kasus, KasusTugas $kasusTugas, KasusTugasSubmission $kasusTugasSubmission, ReviewSubmissionAction $action): RedirectResponse
    {
        $this->authorize('kasus.view');
        abort_if($kasusTugas->kasus_id !== $kasus->id, 404);
```
Ganti menjadi:
```php
    public function review(Request $request, Kasus $kasus, KasusTugas $kasusTugas, KasusTugasSubmission $kasusTugasSubmission, ReviewSubmissionAction $action): RedirectResponse
    {
        abort_if($kasusTugas->kasus_id !== $kasus->id, 404);
```

Baris 87-90 saat ini:
```php
    public function download(Kasus $kasus, KasusTugas $kasusTugas, KasusTugasSubmission $kasusTugasSubmission, KasusPolicy $policy): StreamedResponse
    {
        $this->authorize('kasus.view');
        abort_if($kasusTugas->kasus_id !== $kasus->id, 404);
```
Ganti menjadi:
```php
    public function download(Kasus $kasus, KasusTugas $kasusTugas, KasusTugasSubmission $kasusTugasSubmission, KasusPolicy $policy): StreamedResponse
    {
        abort_if($kasusTugas->kasus_id !== $kasus->id, 404);
```

- [ ] **Step 7: `KasusEvaluasiController.php` — hapus gate redundan di `store()`**

Baris 18-21 saat ini:
```php
    public function store(Request $request, Kasus $kasus, CatatEvaluasiAction $action, KasusPolicy $policy): RedirectResponse
    {
        $this->authorize('kasus.view');
        abort_if($kasus->trashed(), 404);
```
Ganti menjadi:
```php
    public function store(Request $request, Kasus $kasus, CatatEvaluasiAction $action, KasusPolicy $policy): RedirectResponse
    {
        abort_if($kasus->trashed(), 404);
```

- [ ] **Step 8: `php -l` untuk semua 6 file yang diedit**

Run:
```bash
php -l app/Http/Controllers/KasusController.php
php -l app/Http/Controllers/KasusSesiController.php
php -l app/Http/Controllers/KasusTugasController.php
php -l app/Http/Controllers/KasusTugasBatchPreviewController.php
php -l app/Http/Controllers/KasusTugasSubmissionController.php
php -l app/Http/Controllers/KasusEvaluasiController.php
```
Expected: `No syntax errors detected` untuk semuanya.

- [ ] **Step 9: Jalankan test existing yang menyentuh Kasus, pastikan TIDAK ADA regresi (belum ada test baru di task ini)**

Run: `php artisan test tests/Feature/KasusKonselorAksesTest.php tests/Feature/KasusTugasReviewTest.php tests/Feature/KasusEvaluasiTest.php tests/Feature/DashboardKasusTest.php --compact`
Expected: semua PASS — karena `KasusKonselorAksesTest.php` meng-assign `kasus.view` manual di dalam test-nya sendiri (tidak lewat `RoleSeeder`), gate lama yang dihapus tidak mempengaruhi hasil test ini; gate baru (`viewAny` via Task 2) menggantikan dengan hasil sama untuk kasus yang sudah punya `kasus.view` manual.

Kalau ada file test lain yang menyentuh `Kasus*Controller` selain 4 file di atas, cari dengan:
```bash
grep -rl "kasus\.\(sesi\|tugas\|evaluasi\)\|KasusSesiController\|KasusTugasController\|KasusEvaluasiController\|KasusTugasSubmissionController\|KasusTugasBatchPreviewController" tests/
```
dan jalankan juga file yang ditemukan.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/KasusController.php app/Http/Controllers/KasusSesiController.php app/Http/Controllers/KasusTugasController.php app/Http/Controllers/KasusTugasBatchPreviewController.php app/Http/Controllers/KasusTugasSubmissionController.php app/Http/Controllers/KasusEvaluasiController.php
git commit -m "$(cat <<'EOF'
refactor(rbac): hapus gate kasus.view yang redundan, pakai viewAny() di index()

8 endpoint (show/sesi.store/sesi.updateStatus/tugas.store/tugas.markSelesai/
batchPreview/submission.store/submission.review/submission.download/
evaluasi.store) sudah punya otorisasi sesungguhnya lewat KasusPolicy
(view/isKonselor/kelolaSesiTugas/downloadLampiran) atau inline check —
gate kasus.view di depannya tidak pernah dirujuk otorisasi tsb, jadi dihapus.
kasus.index sekarang pakai authorize('viewAny', Kasus::class).
EOF
)"
```

---

### Task 4: Test reproduksi bug + regresi negatif (spec §4.2, §4.2b, §4.3, §4.4, §4.5, §4.5b)

**Files:**
- Modify: `tests/Feature/KasusKonselorAksesTest.php`

**Interfaces:**
- Consumes: `RoleSeeder` (Task 1), `KasusPolicy::viewAny()` (Task 2), controller changes (Task 3).
- Produces: tidak ada interface baru — task terakhir sebelum full regression check.

- [ ] **Step 1: Tulis failing test — reproduksi bug asli (karyawan pool via RoleSeeder asli, semua 8 endpoint bisa diakses)**

Tambahkan fungsi helper dan test baru di akhir `tests/Feature/KasusKonselorAksesTest.php`:
```php
function buatKaryawanPoolViaRoleSeederAsli(Yayasan $yayasan): array
{
    (new \Database\Seeders\RoleSeeder)->run();
    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('pegawai_yayasan');
    $jenis = \App\Domains\Sdm\Models\JenisKaryawanMaster::factory()->create(['is_konselor' => true]);
    $karyawan = Karyawan::withoutGlobalScopes()->create([
        'user_id' => $user->id, 'yayasan_id' => $yayasan->id, 'lembaga_id' => null,
        'jenis_karyawan_id' => $jenis->id, 'nama' => 'Karyawan Pool Asli',
        'nik' => fake()->unique()->numerify('################'), 'status_aktif' => 'aktif',
    ]);

    return [$user, $karyawan];
}

it('lets a real RoleSeeder-baseline pool karyawan (no manual permission grant) access all 8 kasus endpoints once assigned as konselor', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswaUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $siswaUser->id]);
    [$konselorUser, $karyawan] = buatKaryawanPoolViaRoleSeederAsli($yayasan);

    $kasus = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
        'status' => StatusKasus::Ditugaskan, 'konselor_karyawan_id' => $karyawan->id,
    ]);
    $tugas = \App\Domains\Kasus\Models\KasusTugas::create([
        'kasus_id' => $kasus->id, 'judul' => 'Tugas', 'instruksi' => 'Kerjakan',
        'frekuensi' => 'sekali', 'mulai_pada' => now()->toDateString(),
        'batas_selesai_pada' => now()->addDays(3)->toDateString(), 'status' => 'ditugaskan',
    ]);

    $this->actingAs($konselorUser);

    $this->get(route('kasus.index'))->assertOk();
    $this->get(route('kasus.show', $kasus))->assertOk();
    $this->post(route('kasus.sesi.store', $kasus), [
        'sesi' => [['dijadwalkan_pada' => now()->addDay()->format('Y-m-d H:i:s'), 'peserta' => 'siswa', 'lokasi_mode' => 'Ruang BK']],
    ])->assertRedirect(route('kasus.show', $kasus));
    $this->postJson(route('kasus.tugas.preview', $kasus), [
        'judul' => 'Preview', 'instruksi' => 'x', 'frekuensi' => 'sekali',
        'mulai_pada' => now()->toDateString(), 'batas_selesai_pada' => now()->addDays(3)->toDateString(),
    ])->assertOk();
    $this->post(route('kasus.tugas.store', $kasus), [
        'judul' => 'Tugas Baru', 'instruksi' => 'x', 'frekuensi' => 'sekali',
        'mulai_pada' => now()->toDateString(), 'batas_selesai_pada' => now()->addDays(3)->toDateString(),
    ])->assertRedirect(route('kasus.show', $kasus));
    $this->patch(route('kasus.tugas.selesai', [$kasus, $tugas]))->assertRedirect(route('kasus.show', $kasus));
    $this->post(route('kasus.evaluasi.store', $kasus), [
        'tanggal' => now()->format('Y-m-d H:i:s'), 'catatan' => 'Evaluasi', 'keputusan' => 'lanjut',
    ])->assertRedirect(route('kasus.show', $kasus));
});
```

Route yang dipakai di atas sudah diverifikasi persis dari `routes/kasus.php` (baris 23-36): `kasus.index`, `kasus.show`, `kasus.sesi.store` (POST), `kasus.tugas.preview` (POST), `kasus.tugas.store` (POST), `kasus.tugas.selesai` (PATCH — bukan POST), `kasus.evaluasi.store` (POST). Nama route `review`/`download` (`kasus.tugas.submission.review` PATCH, `kasus.tugas.submission.lampiran` GET) tidak dites di skenario ini karena butuh setup `KasusTugasSubmission` tambahan — cukup dites lewat coverage existing di `KasusTugasReviewTest.php` yang sudah jalan di Task 3 Step 9.

- [ ] **Step 2: Jalankan test Step 1, HARUS PASS (kalau gagal, ini bukti regresi nyata, bukan test yang salah — investigasi dulu sebelum lanjut)**

Run: `php artisan test --filter="lets a real RoleSeeder-baseline pool karyawan" --compact`
Expected: PASS. Kalau FAIL, cek route name dulu (Step 1 catatan), baru jika masih gagal STOP dan laporkan ke user — jangan melonggarkan gate manapun untuk meloloskan test ini.

- [ ] **Step 3: Tulis test regresi negatif — pool lintas-lembaga (spec §4.2b)**

Tambahkan:
```php
it('lets a yayasan-pool konselor open their assigned kasus but not an unrelated kasus in a sibling lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswaX = Siswa::factory()->create(['lembaga_id' => $lembagaA->id]);
    $siswaY = Siswa::factory()->create(['lembaga_id' => $lembagaB->id]);
    [$konselorUser, $karyawan] = buatKaryawanPoolViaRoleSeederAsli($yayasan);

    $kasusX = Kasus::create([
        'siswa_id' => $siswaX->id, 'lembaga_id' => $lembagaA->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Kasus X.',
        'status' => StatusKasus::Ditugaskan, 'konselor_karyawan_id' => $karyawan->id,
    ]);
    $kasusY = Kasus::create([
        'siswa_id' => $siswaY->id, 'lembaga_id' => $lembagaB->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Kasus Y, tidak ditugaskan ke karyawan ini.',
        'status' => StatusKasus::Diajukan,
    ]);

    $this->actingAs($konselorUser);
    $this->get(route('kasus.index'))->assertOk();
    $this->get(route('kasus.show', $kasusX))->assertOk();
    $this->get(route('kasus.show', $kasusY))->assertNotFound();
});
```

- [ ] **Step 4: Jalankan test Step 3, HARUS PASS**

Run: `php artisan test --filter="sibling lembaga" --compact`
Expected: PASS.

- [ ] **Step 5: Tulis test regresi negatif — karyawan biasa TANPA assignment (spec §4.3)**

Tambahkan:
```php
it('403s a pegawai_lembaga karyawan who was never assigned as a konselor on any kasus', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    (new \Database\Seeders\RoleSeeder)->run();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('pegawai_lembaga');
    $jenis = \App\Domains\Sdm\Models\JenisKaryawanMaster::factory()->create(['is_konselor' => false]);
    Karyawan::withoutGlobalScopes()->create([
        'user_id' => $user->id, 'lembaga_id' => $lembaga->id,
        'jenis_karyawan_id' => $jenis->id, 'nama' => 'Satpam',
        'nik' => fake()->unique()->numerify('################'), 'status_aktif' => 'aktif',
    ]);

    $this->actingAs($user)->get(route('kasus.index'))->assertForbidden();
});
```

- [ ] **Step 6: Jalankan test Step 5, HARUS PASS (ini reproduksi bug asli yang dilaporkan user — satpam tidak lagi bisa akses)**

Run: `php artisan test --filter="never assigned as a konselor" --compact`
Expected: PASS.

- [ ] **Step 7: Tulis test regresi negatif — assignment historis tetap terlihat (spec §4.4)**

Tambahkan:
```php
it('lets a karyawan whose only konselor assignment is on a Selesai kasus still open kasus.index', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    [$konselorUser, $karyawan] = buatKaryawanPoolViaRoleSeederAsli($yayasan);

    Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Kasus lama, sudah selesai.',
        'status' => StatusKasus::Selesai, 'konselor_karyawan_id' => $karyawan->id,
    ]);

    $this->actingAs($konselorUser)->get(route('kasus.index'))->assertOk();
});
```

- [ ] **Step 8: Jalankan test Step 7, HARUS PASS**

Run: `php artisan test --filter="only konselor assignment is on a Selesai kasus" --compact`
Expected: PASS.

- [ ] **Step 9: Tulis test regresi positif — capability eksplisit TANPA riwayat konselor (spec §4.5b)**

Tambahkan:
```php
it('lets a karyawan with kasus.view granted via an explicit extra role open kasus.index even with zero konselor history', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    (new \Database\Seeders\RoleSeeder)->run();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('pegawai_lembaga');
    $user->assignRole('guru_bk');
    $jenis = \App\Domains\Sdm\Models\JenisKaryawanMaster::factory()->create(['is_konselor' => false]);
    Karyawan::withoutGlobalScopes()->create([
        'user_id' => $user->id, 'lembaga_id' => $lembaga->id,
        'jenis_karyawan_id' => $jenis->id, 'nama' => 'Karyawan Guru BK Tambahan',
        'nik' => fake()->unique()->numerify('################'), 'status_aktif' => 'aktif',
    ]);

    $this->actingAs($user)->get(route('kasus.index'))->assertOk();
});
```

- [ ] **Step 10: Jalankan test Step 9, HARUS PASS**

Run: `php artisan test --filter="explicit extra role open kasus.index even with zero konselor history" --compact`
Expected: PASS.

- [ ] **Step 11: Jalankan seluruh `KasusKonselorAksesTest.php`**

Run: `php artisan test tests/Feature/KasusKonselorAksesTest.php --compact`
Expected: semua PASS (test lama + 5 test baru).

- [ ] **Step 12: Commit**

```bash
git add tests/Feature/KasusKonselorAksesTest.php
git commit -m "$(cat <<'EOF'
test(rbac): tambah test reproduksi bug + regresi negatif untuk kasus.view baseline fix

- karyawan pool via RoleSeeder asli (bukan manual grant) bisa akses 8 endpoint
  setelah ditugaskan konselor (bug reproduction, spec 4.2)
- karyawan pool lintas-lembaga tidak bisa buka kasus lembaga lain yang bukan
  tanggung jawabnya (spec 4.2b)
- karyawan biasa tanpa riwayat konselor 403 di kasus.index (spec 4.3, ini
  reproduksi persis bug satpam yang dilaporkan user)
- assignment historis (kasus Selesai) tetap terlihat, tanpa filter status
  (spec 4.4)
- capability eksplisit (kasus.view via role tambahan) tetap true walau nol
  riwayat konselor, membuktikan OR di viewAny() independen (spec 4.5b)
EOF
)"
```

---

### Task 5: Full regression check (checkpoint penutup)

**Files:**
- Tidak ada file yang diedit — task ini murni verifikasi.

**Interfaces:**
- Consumes: seluruh perubahan Task 1-4.
- Produces: konfirmasi akhir tidak ada regresi di luar domain Kasus/RBAC.

- [ ] **Step 1: Jalankan full test suite**

Run: `php artisan test --compact`
Expected: 0 failed. Catat angka pasti (jumlah passed/skipped) untuk laporan akhir.

- [ ] **Step 2: Kalau ada test GAGAL di luar file yang disebut Task 1-4, diagnosis dulu sebelum menyimpulkan**

Kemungkinan penyebab: ada test lain yang mem-bypass `RoleSeeder` dan menaruh assertion `kasus.view` ke `pegawai_lembaga`/`pegawai_yayasan` secara eksplisit (mis. lewat `RolePermissionAssignmentSeeder` full-sync test, atau test area lain yang kebetulan membuat user dengan role itu dan mengetes akses kasus). Cari dengan:
```bash
grep -rln "pegawai_lembaga\|pegawai_yayasan" tests/ | grep -v "KasusKonselorAksesTest\|RoleSeederTest"
```
Baca file yang ditemukan, tentukan apakah assertion-nya memang bergantung pada `kasus.view` ada di baseline (kalau ya, itu test yang perlu diperbarui fixture-nya mengikuti behavior baru — BUKAN kode guard yang dilonggarkan kembali). Kalau ragu, STOP dan laporkan ke user dengan nama file + assertion yang gagal, jangan menebak.

- [ ] **Step 3: Jalankan `vendor/bin/pint --dirty --format agent` untuk memastikan semua file PHP yang diedit sesuai style project**

Run: `vendor/bin/pint --dirty --format agent`
Expected: tidak ada error, file yang diformat (kalau ada) di-restage.

Kalau ada file yang diformat ulang oleh Pint:
```bash
git add -u
git commit -m "style: pint formatting untuk perubahan rbac-v2 kasus.view fix"
```

- [ ] **Step 4: Laporkan hasil akhir ke user**

Ringkasan yang WAJIB disampaikan:
- Angka pasti full suite (`X passed, Y skipped, 0 failed`).
- Daftar commit hash dari Task 1-5.
- Konfirmasi: satpam/karyawan tanpa riwayat konselor sekarang 403 di `kasus.index`; karyawan pool yang ditugaskan tetap bisa akses; baseline `guru`/`siswa`/`orang_tua`/`Admin\KasusController` tidak berubah.

---

## Self-Review (dilakukan penulis plan)

**Spec coverage**: §2.2(a) → Task 1. §2.2(b) → Task 3. §2.2(c) → Task 2. §2.2(d) → Task 3 Step 1. §2.2(e) (Admin tidak diubah) → tidak ada task, sesuai Non-Goals. §4.1 → dicek di Task 3 Step 9 & Task 5. §4.2/4.2b → Task 4 Step 1-4. §4.3 → Task 4 Step 5-6. §4.4 → Task 4 Step 7-8. §4.5 → dicek implisit (baseline guru/siswa/orang_tua tidak diubah, dibuktikan Task 1 Step 6). §4.5b → Task 4 Step 9-10. §4.6 → tidak ada task terpisah (spec eksplisit: tidak ada perubahan kode), diverifikasi lewat full suite Task 5. §4.7 → Task 1 Step 2. Semua section spec punya task yang menutupinya.

**Placeholder scan**: tidak ada "TBD"/"implement later" — semua step berisi kode lengkap atau command lengkap dengan expected output.

**Type consistency**: `viewAny(User $user): bool` konsisten dipakai di Task 2 (definisi) dan Task 3 Step 1 (`$this->authorize('viewAny', Kasus::class)`). Nama route di Task 4 (`kasus.tugas.preview`, `kasus.tugas.selesai` PATCH, dll) sudah diverifikasi langsung dari `routes/kasus.php` baris 23-36 sebelum plan ini ditulis — bukan tebakan.
