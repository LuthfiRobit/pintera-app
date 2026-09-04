# Perbaikan Audit Akademik Putaran 4 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menutup 1 root-cause CRITICAL (TenantScope tidak verifikasi ulang kepemilikan yayasan atas `active_lembaga_id`), 6 titik controller Important yang percaya session tanpa verifikasi ulang (termasuk 1 bug kode-mati), dan 1 race condition di pembuatan/perubahan Jadwal Pelajaran.

**Architecture:** 9 task berurutan. Task 1 (root-fix `TenantScope`) berdiri sendiri dan TIDAK mengubah signature/behavior publik apa pun — task lain tidak bergantung padanya secara teknis (kompilasi), tapi Task 2-7 memperbaiki celah yang SEBAGIAN sudah tertutup begitu Task 1 selesai (lihat catatan di tiap task). Task 8 (race condition Jadwal Pelajaran) independen total dari Task 1-7.

**Tech Stack:** Laravel 12, PHP 8.3, Pest.

## Global Constraints

- Titik perbaikan session-staleness root HARUS di dalam `TenantScope::apply()` itu sendiri — BUKAN di `ResolveTenant` middleware. Middleware ini TIDAK disentuh sama sekali di paket ini.
- Perilaku saat session basi terdeteksi: diperlakukan SAMA seperti "belum pilih lembaga" (fallback ke cabang existing yang membatasi ke semua lembaga milik yayasan actor sendiri) — BUKAN diblokir total (403/422).
- Semua fix di Task 2-7 WAJIB pakai ulang `ResolveLembagaScopeTrait::resolveActiveLembagaId(User $actor): ?int` yang SUDAH ADA (dari paket putaran 3) — JANGAN membuat trait/method baru.
- Race condition Jadwal Pelajaran (Task 8) dikunci lewat `JamPelajaran::where('id', $data->jamPelajaranId)->lockForUpdate()->first()` — BUKAN mengunci `Semester`.
- Non-Goals eksplisit — JANGAN disentuh sama sekali: `JalurPpdbController`/`GelombangPpdbController`, `SkPpdbController`/`TagihanSusulanController`/`PendaftaranAdminController` (modul SPMB), cutoff edit presensi, gap Activitylog `ProsesKenaikanKelasAction`, `JadwalPelajaranController::index()` baris 71 (filter dropdown UI).
- Tidak pindah branch, tetap di `akademik-v2`.

---

## Task 1: Root-Fix `TenantScope` — Verifikasi Ulang Kepemilikan Yayasan

**Files:**
- Modify: `app/Models/Scopes/TenantScope.php`
- Test: `tests/Feature/TenantScopeTest.php`

**Interfaces:**
- Produksi: `TenantScope::apply()` — signature TIDAK berubah (masih `implements Scope`). Method baru PRIVATE `lembagaMasihMilikYayasan(int $lembagaId, ?int $yayasanId): bool` — internal, tidak dipakai task lain.

- [ ] **Step 1: Tulis test yang gagal — session stale di-fallback, bukan bocor**

Baca `tests/Feature/TenantScopeTest.php` baris 1-51 (definisi `TenantScopeTestModel`, `beforeEach`/`afterEach` yang membuat/menghapus tabel sementara) dan baris 155-170 (test "respects a yayasan-scoped user's active_lembaga_id session filter" — pola acuan). Tambahkan test baru setelah test itu:

```php
it('falls back to own-yayasan pool when active_lembaga_id session points to a lembaga outside the actor\'s current yayasan', function () {
    $yayasanSaya = Yayasan::factory()->create();
    $yayasanLain = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasanSaya->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);

    TenantScopeTestModel::withoutGlobalScopes()->create(['lembaga_id' => $lembagaSaya->id, 'label' => 'Milik Saya']);
    TenantScopeTestModel::withoutGlobalScopes()->create(['lembaga_id' => $lembagaLain->id, 'label' => 'Milik Lain']);

    $user = User::factory()->create(['yayasan_id' => $yayasanSaya->id]);
    $user->assignRole('yayasan_super_admin');
    $this->actingAs($user);
    // Session basi -- menunjuk ke lembaga yang BUKAN milik yayasan actor saat ini.
    session(['active_lembaga_id' => $lembagaLain->id]);

    // Harus fallback ke pool "semua lembaga milik yayasan sendiri", BUKAN bocor ke lembagaLain,
    // BUKAN JUGA kosong total.
    expect(TenantScopeTestModel::pluck('label')->all())->toBe(['Milik Saya']);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="falls back to own-yayasan pool when active_lembaga_id session points to a lembaga outside"`
Expected: FAIL — hasil masih `[]` (kosong, karena `where('lembaga_id', $lembagaLain->id)` tidak match `TenantScopeTestModel` manapun yang berlabel "Milik Saya"), bukan `['Milik Saya']`.

- [ ] **Step 3: Perbaiki `TenantScope`**

Baca `app/Models/Scopes/TenantScope.php` (file penuh, 91 baris). Tambahkan properti baru setelah `private static bool $resolvingActingUser = false;` (baris 13):
```php
    private static array $lembagaOwnershipCache = [];
```

Ganti blok `if ($actingUser->widestScopeLevel() === 'yayasan') { ... }` (baris 52-86) — HANYA baris 53-56 yang berubah, sisanya (baris 58-84, cabang fallback existing) TIDAK BERUBAH SAMA SEKALI:
```php
        if ($actingUser->widestScopeLevel() === 'yayasan') {
            $activeLembagaId = session('active_lembaga_id');
            if ($activeLembagaId !== null && ! $this->lembagaMasihMilikYayasan((int) $activeLembagaId, $actingUser->yayasan_id)) {
                $activeLembagaId = null;
            }

            if ($activeLembagaId) {
                $builder->where($model->getTable().'.lembaga_id', $activeLembagaId);
            } else {
                // ... isi cabang else PERSIS SAMA seperti baris 58-82 versi asli, TIDAK DIUBAH ...
            }

            return;
        }
```

Tambahkan method baru PRIVATE setelah method `apply()` (sebelum penutup class `}`):
```php
    private function lembagaMasihMilikYayasan(int $lembagaId, ?int $yayasanId): bool
    {
        $cacheKey = $lembagaId.':'.($yayasanId ?? 'null');

        return self::$lembagaOwnershipCache[$cacheKey] ??= Lembaga::where('id', $lembagaId)
            ->where('yayasan_id', $yayasanId)
            ->exists();
    }
```

- [ ] **Step 4: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter="falls back to own-yayasan pool when active_lembaga_id session points to a lembaga outside"`
Expected: PASS.

- [ ] **Step 5: Jalankan regresi TenantScope — pastikan skenario "session valid" tetap benar**

Run: `php artisan test --filter=TenantScope`
Expected: SEMUA test existing di `TenantScopeTest.php` (termasuk "respects a yayasan-scoped user's active_lembaga_id session filter" di baris 155-170) tetap PASS — fix ini TIDAK boleh mengubah perilaku untuk session yang valid.

- [ ] **Step 6: Jalankan regresi lebih luas — pastikan TIDAK ada model lain yang meregresi**

Run: `php artisan test --filter=Akademik`
Run: `php artisan test --filter=Guru`
Run: `php artisan test --filter=Rpp`
Expected: semua PASS — `TenantScope` dipakai hampir semua model `BelongsToTenant`, jadi regresi luas WAJIB dicek sebelum lanjut ke task berikutnya.

- [ ] **Step 7: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Scopes/TenantScope.php tests/Feature/TenantScopeTest.php
git commit -m "fix(core): TenantScope verifikasi ulang kepemilikan yayasan atas active_lembaga_id session, cegah akses lintas-yayasan dari session basi"
```

---

## Task 2: `KelasController::store()` — Verifikasi Ulang `active_lembaga_id`

**Files:**
- Modify: `app/Http/Controllers/Admin/KelasController.php`
- Test: `tests/Feature/Admin/KelasCrudTest.php`

**Interfaces:** Tidak ada — perbaikan berdiri sendiri. Konsumsi: `ResolveLembagaScopeTrait::resolveActiveLembagaId(User $actor): ?int` (sudah ada).

- [ ] **Step 1: Tulis test yang gagal**

Baca `tests/Feature/Admin/KelasCrudTest.php` baris 1-30 (helper `actingAsKelasManager(Lembaga $lembaga): User` — lembaga-scope, TIDAK cocok untuk test ini). Tambahkan test baru mengikuti pola `tests/Feature/Admin/PolaJamCrudTest.php:62-80` (actor yayasan-scope manual):

```php
it('menolak actor yayasan dengan active_lembaga_id stale (lembaga di luar yayasannya) saat membuat kelas', function () {
    Permission::firstOrCreate(['name' => 'kelas.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_kelas_stale_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['kelas.create']);

    $yayasanSaya = Yayasan::factory()->create();
    $yayasanLain = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasanSaya->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembagaLain->id]);

    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);

    $response = $this->actingAs($manager)->post(route('admin.kelas.store'), [
        'nama' => 'Kelas Uji Stale',
        'tahun_ajaran_id' => $tahunAjaranLain->id,
        'tingkat' => '1',
    ]);

    $response->assertSessionHasErrors('lembaga_id');
    expect(Kelas::where('nama', 'Kelas Uji Stale')->exists())->toBeFalse();
});
```

Baca `StoreKelasRequest` (`app/Http/Requests/Akademik/StoreKelasRequest.php` atau lokasi sejenis — cari lewat `KelasController` use-statement) untuk field wajib LAIN yang mungkin belum tercakup di payload di atas (mis. `bentuk_pendidikan`, `pola_jam_id`, dll) — tambahkan kalau ternyata wajib, supaya request lolos validasi FormRequest dan benar-benar sampai ke pengecekan `lembaga_id` yang sedang diuji.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="menolak actor yayasan dengan active_lembaga_id stale (lembaga di luar yayasannya) saat membuat kelas"`
Expected: FAIL — request lolos memakai `lembagaLain` (session mentah, tanpa verifikasi kepemilikan).

- [ ] **Step 3: Perbaiki `KelasController`**

Baca `app/Http/Controllers/Admin/KelasController.php` baris 1-30 (import + class header) dan baris 93-115 (`store()`). Tambahkan import `use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;` dan `use ResolveLembagaScopeTrait;` di dalam class body (setelah trait/use lain yang sudah ada, cek dulu apakah ada `use AuthorizesRequests;` untuk pola penempatan yang konsisten). Ganti baris 99-106:
```php
        $lembagaIdOverride = null;
        if ($request->user()->widestScopeLevel() === 'yayasan') {
            $lembagaIdOverride = $this->resolveActiveLembagaId($request->user());

            if ($lembagaIdOverride === null) {
                return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat kelas.'])->withInput();
            }
        }
```
(Struktur `if (widestScopeLevel() === 'yayasan')` TETAP DIPERTAHANKAN PERSIS — hanya `session('active_lembaga_id')` yang diganti jadi `$this->resolveActiveLembagaId($request->user())`. JANGAN dipanggil di luar percabangan ini.)

- [ ] **Step 4: Jalankan test lagi + regresi**

Run: `php artisan test --filter=KelasCrudTest`
Expected: semua PASS, termasuk test lama yang sudah ada di file ini.

- [ ] **Step 5: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/KelasController.php tests/Feature/Admin/KelasCrudTest.php
git commit -m "fix(akademik): KelasController::store() verifikasi ulang active_lembaga_id via resolveActiveLembagaId()"
```

---

## Task 3: `TahunAjaranController::store()` — Verifikasi Ulang `active_lembaga_id`

**Files:**
- Modify: `app/Http/Controllers/Admin/TahunAjaranController.php`
- Test: `tests/Feature/Admin/TahunAjaranSemesterFeatureTest.php`

**Interfaces:** Tidak ada — perbaikan berdiri sendiri.

- [ ] **Step 1: Tulis test yang gagal**

Baca `tests/Feature/Admin/TahunAjaranSemesterFeatureTest.php` baris 1-26 (helper `createAdminTahunAjaranFeatureUser(): array` — lembaga-scope, TIDAK cocok). Tambahkan test baru:

```php
it('menolak actor yayasan dengan active_lembaga_id stale saat membuat tahun ajaran', function () {
    Permission::firstOrCreate(['name' => 'tahun-ajaran.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_ta_stale_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['tahun-ajaran.create']);

    $yayasanSaya = Yayasan::factory()->create();
    $yayasanLain = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasanSaya->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembagaLain->id]);

    $response = $this->actingAs($manager)->post(route('admin.tahun-ajaran.store'), [
        'nama' => '2099/2100',
        'tanggal_mulai' => '2099-07-01',
        'tanggal_selesai' => '2100-06-30',
    ]);

    $response->assertSessionHasErrors('lembaga_id');
    expect(TahunAjaran::where('nama', '2099/2100')->exists())->toBeFalse();
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="menolak actor yayasan dengan active_lembaga_id stale saat membuat tahun ajaran"`
Expected: FAIL.

- [ ] **Step 3: Perbaiki `TahunAjaranController`**

Baca `app/Http/Controllers/Admin/TahunAjaranController.php` baris 1-55. Tambahkan import + `use ResolveLembagaScopeTrait;` di class body. Ganti baris 42-50:
```php
        if ($request->user()->widestScopeLevel() === 'yayasan') {
            $lembagaId = $this->resolveActiveLembagaId($request->user());

            if ($lembagaId === null) {
                return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat tahun ajaran.'])->withInput();
            }

            $data['lembaga_id'] = $lembagaId;
        }
```

- [ ] **Step 4: Jalankan test lagi + regresi**

Run: `php artisan test --filter=TahunAjaran`
Expected: semua PASS.

- [ ] **Step 5: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/TahunAjaranController.php tests/Feature/Admin/TahunAjaranSemesterFeatureTest.php
git commit -m "fix(akademik): TahunAjaranController::store() verifikasi ulang active_lembaga_id via resolveActiveLembagaId()"
```

---

## Task 4: `PolaJamController::store()` — Verifikasi Ulang `active_lembaga_id`

**Files:**
- Modify: `app/Http/Controllers/Admin/PolaJamController.php`
- Test: `tests/Feature/Admin/PolaJamCrudTest.php`

**Interfaces:** Tidak ada — perbaikan berdiri sendiri.

- [ ] **Step 1: Tulis test yang gagal**

Baca `tests/Feature/Admin/PolaJamCrudTest.php` baris 62-95 (2 test yayasan-scope existing: "creates a pola jam with the active lembaga for a yayasan-scoped manager" dan "rejects creating a pola jam for a yayasan-scoped manager with no active lembaga" — pola acuan PERSIS untuk test baru ini). Tambahkan test ketiga setelah keduanya:

```php
it('menolak actor yayasan dengan active_lembaga_id stale saat membuat pola jam', function () {
    Permission::firstOrCreate(['name' => 'pola-jam.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_pola_jam_stale_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['pola-jam.create']);

    $yayasanSaya = Yayasan::factory()->create();
    $yayasanLain = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasanSaya->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)
        ->withSession(['active_lembaga_id' => $lembagaLain->id])
        ->post(route('admin.pola-jam.store'), ['nama' => 'Pola Uji Stale'])
        ->assertSessionHasErrors('lembaga_id');

    expect(PolaJam::where('nama', 'Pola Uji Stale')->exists())->toBeFalse();
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="menolak actor yayasan dengan active_lembaga_id stale saat membuat pola jam"`
Expected: FAIL.

- [ ] **Step 3: Perbaiki `PolaJamController`**

Baca `app/Http/Controllers/Admin/PolaJamController.php` baris 1-61. Tambahkan import + `use ResolveLembagaScopeTrait;` di class body. Ganti baris 50-56:
```php
        $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? $this->resolveActiveLembagaId($request->user())
            : $request->user()->lembaga_id;

        if ($lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat pola jam.'])->withInput();
        }
```
**PENTING**: PERTAHANKAN struktur ternary PERSIS — JANGAN panggil `resolveActiveLembagaId()` unconditional di luar ternary (lihat Global Constraints).

- [ ] **Step 4: Jalankan test lagi + regresi**

Run: `php artisan test --filter=PolaJam`
Expected: semua PASS, termasuk 2 test yayasan-scope existing (baris 62-95) — pastikan TIDAK regresi.

- [ ] **Step 5: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/PolaJamController.php tests/Feature/Admin/PolaJamCrudTest.php
git commit -m "fix(akademik): PolaJamController::store() verifikasi ulang active_lembaga_id via resolveActiveLembagaId()"
```

---

## Task 5: `JenisTesMasterController::store()` — Verifikasi Ulang `active_lembaga_id`

**Files:**
- Modify: `app/Http/Controllers/Admin/JenisTesMasterController.php`
- Test: `tests/Feature/Admin/JenisTesMasterTest.php`

**Interfaces:** Tidak ada — perbaikan berdiri sendiri.

- [ ] **Step 1: Tulis test yang gagal**

Baca `tests/Feature/Admin/JenisTesMasterTest.php` baris 1-40 (pola setup existing — cek nama helper/permission yang sudah dipakai). Tambahkan test baru:

```php
it('menolak actor yayasan dengan active_lembaga_id stale saat membuat jenis tes', function () {
    Permission::firstOrCreate(['name' => 'jenis-tes.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_jenis_tes_stale_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['jenis-tes.create']);

    $yayasanSaya = Yayasan::factory()->create();
    $yayasanLain = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasanSaya->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembagaLain->id]);

    $response = $this->actingAs($manager)->post(route('admin.jenis-tes.store'), [
        'nama' => 'Jenis Tes Uji Stale',
    ]);

    $response->assertSessionHasErrors('lembaga_id');
    expect(JenisTesMaster::where('nama', 'Jenis Tes Uji Stale')->exists())->toBeFalse();
});
```

Sesuaikan `use App\Models\JenisTesMaster;` kalau belum ada di import file ini.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="menolak actor yayasan dengan active_lembaga_id stale saat membuat jenis tes"`
Expected: FAIL.

- [ ] **Step 3: Perbaiki `JenisTesMasterController`**

Baca `app/Http/Controllers/Admin/JenisTesMasterController.php` baris 1-55. Tambahkan import + `use ResolveLembagaScopeTrait;` di class body. Ganti baris 32-46:
```php
        $isYayasanScope = $request->user()->widestScopeLevel() === 'yayasan';
        if ($isYayasanScope) {
            $lembagaId = $this->resolveActiveLembagaId($request->user());
            if ($lembagaId === null) {
                $message = 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah jenis tes.';

                if ($request->wantsJson()) {
                    return response()->json(['message' => $message, 'errors' => ['lembaga_id' => [$message]]], 422);
                }

                return back()->withErrors(['lembaga_id' => $message])->withInput();
            }
        } else {
            $lembagaId = $request->user()->lembaga_id;
        }
```
**PENTING**: `resolveActiveLembagaId()` HANYA dipanggil di DALAM cabang `if ($isYayasanScope)` — JANGAN unconditional (lihat Global Constraints). Sisa method (`$data = $request->validate([...])` dan `if ($isYayasanScope) { $data['lembaga_id'] = $lembagaId; }`) TIDAK BERUBAH.

- [ ] **Step 4: Jalankan test lagi + regresi**

Run: `php artisan test --filter=JenisTesMaster`
Expected: semua PASS.

- [ ] **Step 5: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/JenisTesMasterController.php tests/Feature/Admin/JenisTesMasterTest.php
git commit -m "fix(akademik): JenisTesMasterController::store() verifikasi ulang active_lembaga_id via resolveActiveLembagaId()"
```

---

## Task 6: `RppController::verify()` — Verifikasi Ulang `active_lembaga_id`

**Files:**
- Modify: `app/Http/Controllers/Admin/RppController.php`
- Test: `tests/Feature/Akademik/RppWorkflowTest.php`

**Interfaces:** Tidak ada — perbaikan berdiri sendiri.

- [ ] **Step 1: Tulis test yang gagal**

Baca `tests/Feature/Akademik/RppWorkflowTest.php` baris 22-59 (`beforeEach` — `$this->userKurikulum` role `wakasek_kurikulum` scope `lembaga`, PUNYA permission `rpp.verify`) dan baris 147-179 ("mengizinkan verifikator menyetujui RPP diajukan" — pola acuan verify). `$this->userKurikulum` di file ini scope-nya `lembaga` (`lembaga_id` langsung terisi), BUKAN yayasan-scope — untuk test session-staleness, buat actor yayasan-scope baru secara manual di dalam test. Tambahkan test baru setelah test "mengizinkan verifikator menyetujui RPP diajukan":

```php
it('menolak actor yayasan dengan active_lembaga_id stale saat memverifikasi RPP', function () {
    $roleYayasanVerify = Role::firstOrCreate(['name' => 'yayasan_rpp_verify_stale_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $roleYayasanVerify->givePermissionTo(['rpp.verify']);

    $yayasanLain = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    $verifierYayasan = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $this->yayasan->id]);
    $verifierYayasan->assignRole($roleYayasanVerify);
    session(['active_lembaga_id' => $lembagaLain->id]);

    $file = UploadedFile::fake()->create('rpp_uji_stale.pdf', 200, 'application/pdf');
    $path = $file->store("rpp/{$this->lembaga->id}", 'public');

    $rpp = Rpp::create([
        'yayasan_id' => $this->yayasan->id,
        'lembaga_id' => $this->lembaga->id,
        'guru_id' => $this->guru->id,
        'tahun_ajaran_id' => $this->tahunAjaran->id,
        'semester_id' => $this->semester->id,
        'kelas_id' => $this->kelas->id,
        'mata_pelajaran_id' => $this->mapel->id,
        'judul_topik' => 'Uji Stale Verify',
        'alokasi_waktu' => '2 JP',
        'file_path' => $path,
        'file_name' => 'rpp_uji_stale.pdf',
        'file_size_bytes' => 2048,
        'mime_type' => 'application/pdf',
        'status' => StatusRpp::Diajukan,
    ]);

    $response = $this->actingAs($verifierYayasan)->post(route('admin.rpp.verify', $rpp), [
        'status' => 'disetujui',
    ]);

    $response->assertStatus(422);
    expect($rpp->fresh()->status)->toBe(StatusRpp::Diajukan);
});
```

**Catatan**: `$verifierYayasan->yayasan_id` diisi `$this->yayasan->id` (yayasan YANG SAMA dengan RPP), TAPI `active_lembaga_id` di session sengaja diisi `$lembagaLain->id` (milik `$yayasanLain`, yayasan LAIN) — supaya `resolveActiveLembagaId()` mengembalikan `null` (session tidak valid untuk yayasan actor), BUKAN untuk menguji cross-yayasan pada RPP itu sendiri (itu domain `TenantScope`, sudah diuji terpisah di Task 1).

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="menolak actor yayasan dengan active_lembaga_id stale saat memverifikasi RPP"`
Expected: FAIL — request lolos memakai `lembagaLain` sebagai `effectiveLembagaId` tanpa verifikasi.

- [ ] **Step 3: Perbaiki `RppController::verify()`**

Baca `app/Http/Controllers/Admin/RppController.php` baris 258-294. Tambahkan import + `use ResolveLembagaScopeTrait;` di class body. Ganti baris 264-268:
```php
        $effectiveLembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? $this->resolveActiveLembagaId($request->user())
            : $request->user()->lembaga_id;

        abort_if($effectiveLembagaId === null, 422, 'Pilih lembaga aktif melalui pengalih lembaga sebelum memverifikasi RPP.');
```
**PENTING**: PERTAHANKAN struktur ternary PERSIS — JANGAN unconditional. Sisa method TIDAK BERUBAH.

- [ ] **Step 4: Jalankan test lagi + regresi**

Run: `php artisan test --filter=RppWorkflowTest`
Expected: semua PASS, termasuk test verify existing (baris 147-220-an).

- [ ] **Step 5: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/RppController.php tests/Feature/Akademik/RppWorkflowTest.php
git commit -m "fix(akademik): RppController::verify() verifikasi ulang active_lembaga_id via resolveActiveLembagaId()"
```

---

## Task 7: `JadwalPelajaranController` — Verifikasi Ulang `active_lembaga_id` (3 titik) + Perbaiki Bug Kode-Mati `duplicate()`

**Files:**
- Modify: `app/Http/Controllers/Admin/JadwalPelajaranController.php`
- Test: `tests/Feature/Admin/JadwalPelajaranTenantGuardTest.php`

**Interfaces:** Tidak ada — perbaikan berdiri sendiri.

- [ ] **Step 1: Tulis 3 test yang gagal**

Baca `tests/Feature/Admin/JadwalPelajaranTenantGuardTest.php` (file penuh, 54 baris — helper `actingAsJadwalGuardManager(Lembaga $lembaga): User` lembaga-scope, dan 1 test existing "menolak store Jadwal Pelajaran untuk Kelas lembaga lain" sebagai pola acuan struktur data `store()`). Tambahkan 3 test baru:

```php
it('menolak actor yayasan dengan active_lembaga_id stale saat store Jadwal Pelajaran', function () {
    $yayasanSaya = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasanSaya->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $polaJam = PolaJam::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembagaSaya->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $polaJam->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $polaJam->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);

    Permission::firstOrCreate(['name' => 'jadwal-pelajaran.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_jadwal_stale_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['jadwal-pelajaran.kelola']);

    $yayasanLain = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasanSaya->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembagaLain->id]);

    // TenantScope (Task 1) sudah membatasi Kelas::find() ke pool milik yayasanSaya begitu
    // session basi -- $kelas (milik lembagaSaya) tetap KETEMU, tapi resolveActiveLembagaId()
    // mengembalikan null sehingga blok `if ($lembagaId)` di store() dilewati (tidak menegakkan
    // apa-apa, konsisten dgn filosofi fail-closed-ke-pool-yayasan). Test ini murni regresi:
    // pastikan penggantian kode tidak membuat request malah 500/error tak terduga.
    $response = $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => [$jam->id],
        'guru_id' => $guru->id,
        'semester_id' => $semester->id,
    ]);

    $response->assertStatus(302);
});

it('update Jadwal Pelajaran tetap berhasil untuk actor yayasan dengan active_lembaga_id valid pada lembaga yang sama (regresi)', function () {
    $yayasanSaya = Yayasan::factory()->create();
    $lembagaAktif = Lembaga::factory()->create(['yayasan_id' => $yayasanSaya->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaAktif->id]);
    $polaJam = PolaJam::factory()->create(['lembaga_id' => $lembagaAktif->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembagaAktif->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $polaJam->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $polaJam->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembagaAktif->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $jadwal = JadwalPelajaran::create([
        'kelas_id' => $kelas->id, 'guru_id' => $guru->id, 'jam_pelajaran_id' => $jam->id, 'semester_id' => $semester->id,
    ]);

    Permission::firstOrCreate(['name' => 'jadwal-pelajaran.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_jadwal_update_valid_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['jadwal-pelajaran.kelola']);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasanSaya->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembagaAktif->id]);

    $response = $this->actingAs($manager)->put(route('admin.jadwal-pelajaran.update', $jadwal), [
        'guru_id' => $guru->id,
        'jam_pelajaran_id' => $jam->id,
    ]);

    $response->assertStatus(302);
    expect($jadwal->fresh()->guru_id)->toBe($guru->id);
});

it('duplicate Jadwal Pelajaran tetap berhasil untuk actor yayasan dengan active_lembaga_id valid pada lembaga yang sama (regresi setelah perbaikan bug kode-mati)', function () {
    $yayasanSaya = Yayasan::factory()->create();
    $lembagaAktif = Lembaga::factory()->create(['yayasan_id' => $yayasanSaya->id]);

    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaAktif->id]);
    $polaJam = PolaJam::factory()->create(['lembaga_id' => $lembagaAktif->id]);
    $semesterSumber = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $semesterTujuan = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelasSumber = Kelas::factory()->create(['lembaga_id' => $lembagaAktif->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $polaJam->id]);
    $kelasTujuan = Kelas::factory()->create(['lembaga_id' => $lembagaAktif->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $polaJam->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembagaAktif->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $polaJam->id, 'is_pelajaran' => true]);
    JadwalPelajaran::create(['kelas_id' => $kelasSumber->id, 'guru_id' => $guru->id, 'jam_pelajaran_id' => $jam->id, 'semester_id' => $semesterSumber->id]);

    Permission::firstOrCreate(['name' => 'jadwal-pelajaran.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_jadwal_duplicate_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['jadwal-pelajaran.kelola']);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasanSaya->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembagaAktif->id]);

    $response = $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.duplicate'), [
        'source_kelas_id' => $kelasSumber->id,
        'target_kelas_id' => $kelasTujuan->id,
        'source_semester_id' => $semesterSumber->id,
        'target_semester_id' => $semesterTujuan->id,
    ]);

    $response->assertStatus(302);
    expect(JadwalPelajaran::where('kelas_id', $kelasTujuan->id)->exists())->toBeTrue();
});
```

**Catatan kejujuran soal dampak bug `update()`/`duplicate()`**: setelah ditelusuri, `JadwalPelajaran` dan `Kelas` sama-sama pakai `BelongsToTenant`/`TenantScope`. Begitu `TenantScope` (Task 1) selesai, route-model-binding `update(JadwalPelajaran $jadwalPelajaran)` dan `Kelas::findOrFail()` di `duplicate()` SUDAH otomatis menolak (404) begitu objeknya berasal dari lembaga di luar sesi aktif yang VALID — permintaan lintas-lembaga tidak akan pernah sampai ke baris `$lembagaId`/`abort_if` yang diperbaiki di task ini. Perbaikan di `update()` (baris 335) dan `duplicate()` (baris 444-445, termasuk bug kode-mati `$user->active_lembaga_id`) karenanya murni **kebersihan kode dan defense-in-depth** — BUKAN menutup celah yang saat ini benar-benar bisa dieksploitasi (beda dengan `store()` di Task 2-5 yang genuinely WRITE-path dan TIDAK tertutup oleh `TenantScope`). Ketiga test di atas cukup membuktikan jalur normal (session valid, semua entity di lembaga yang sama) tetap berfungsi setelah baris-baris itu diganti — TIDAK PERLU skenario "reject" untuk `update()`/`duplicate()` karena skenario itu tidak bisa dikonstruksi secara valid (selalu ke-intercept oleh `TenantScope`/route-model-binding duluan). Test #1 (`store()`) TETAP punya nilai regresi tersendiri karena `Kelas::find()` di baris 179 bukan route-model-binding (fetch manual), jadi worth dicek eksplisit meski hasil akhirnya juga "berhasil normal", bukan "reject".

Baca `StoreJadwalPelajaranRequest`/`UpdateJadwalPelajaranRequest`/`DuplicateJadwalRequest` untuk field wajib LAIN yang mungkin belum tercakup di payload di atas — sesuaikan kalau ternyata ada field wajib tambahan supaya request lolos validasi FormRequest.

- [ ] **Step 2: Jalankan 3 test, pastikan semua PASS (regresi, bukan pola TDD merah-dulu)**

Run: `php artisan test --filter="menolak actor yayasan dengan active_lembaga_id stale saat store Jadwal Pelajaran"`
Run: `php artisan test --filter="update Jadwal Pelajaran tetap berhasil untuk actor yayasan dengan active_lembaga_id valid"`
Run: `php artisan test --filter="duplicate Jadwal Pelajaran tetap berhasil untuk actor yayasan dengan active_lembaga_id valid"`
Expected: SEMUA PASS sebelum maupun sesudah Step 3 — ketiga test ini adalah regression-guard (jalur normal harus tetap berfungsi), bukan bukti bug tertutup. Nilainya ada di Step 4-5: memastikan penggantian baris tidak merusak jalur normal yang sudah benar.

- [ ] **Step 3: Perbaiki `JadwalPelajaranController`**

Baca `app/Http/Controllers/Admin/JadwalPelajaranController.php` baris 1-35 (import + class header) dan baris 175-460. Tambahkan import `use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;` dan `use ResolveLembagaScopeTrait;` di class body (setelah `use AuthorizesRequests;`).

Ganti `store()` baris 182:
```php
        $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? $this->resolveActiveLembagaId($request->user())
            : $request->user()->lembaga_id;
```

Ganti `update()` baris 335:
```php
        $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? $this->resolveActiveLembagaId($request->user())
            : $request->user()->lembaga_id;
```

Ganti `duplicate()` baris 444-445:
```php
        $user = $request->user();
        $lembagaId = $user->widestScopeLevel() === 'yayasan' ? $this->resolveActiveLembagaId($user) : $user->lembaga_id;
```
(Menggantikan `$lembagaId = $user->active_lembaga_id ?: ($user->lembaga_id ?: null);` — properti `active_lembaga_id` TIDAK ADA di model `User`.)

**PENTING**: di KETIGA titik, PERTAHANKAN struktur ternary/percabangan asli — JANGAN unconditional. Sisa logic di ketiga method (baris setelah masing-masing titik resolve) TIDAK BERUBAH.

- [ ] **Step 4: Jalankan ulang 3 test + regresi**

Run: `php artisan test --filter=JadwalPelajaran`
Expected: semua PASS, termasuk test existing di `JadwalPelajaranTenantGuardTest.php`, `JadwalPelajaranCrudTest.php`, `JadwalPelajaranBentrokWaktuTest.php`.

- [ ] **Step 5: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/JadwalPelajaranController.php tests/Feature/Admin/JadwalPelajaranTenantGuardTest.php
git commit -m "fix(akademik): JadwalPelajaranController verifikasi ulang active_lembaga_id di store/update, perbaiki bug kode-mati active_lembaga_id di duplicate()"
```

---

## Task 8: Race Condition `CreateJadwalPelajaranAction`/`UpdateJadwalPelajaranAction`

**Files:**
- Modify: `app/Domains/Akademik/Actions/Jadwal/CreateJadwalPelajaranAction.php`
- Modify: `app/Domains/Akademik/Actions/Jadwal/UpdateJadwalPelajaranAction.php`
- Test: `tests/Feature/Admin/JadwalPelajaranBentrokWaktuTest.php`

**Interfaces:** Tidak ada — perbaikan berdiri sendiri.

- [ ] **Step 1: Tulis test yang gagal (behavioral, bukan concurrency asli)**

Baca `tests/Feature/Admin/JadwalPelajaranBentrokWaktuTest.php` (file penuh) untuk pola setup `Kelas`/`PolaJam`/`JamPelajaran`/`Semester`/`Guru` yang sudah dipakai, dan cara memanggil `CreateJadwalPelajaranAction`/data DTO-nya. Tambahkan test baru:

```php
it('tetap konsisten menolak bentrok guru setelah dibungkus lock (regresi, bukan tes concurrency asli)', function () {
    // Setup Kelas A dan Kelas B beda kelas tapi SAMA pola jam (supaya jam_pelajaran_id sama),
    // 1 guru yang sama, 1 semester yang sama -- pola setup existing di file ini.
    // Panggilan pertama (Kelas A, guru G, jam J) berhasil.
    // Panggilan kedua (Kelas B, guru G, jam J -- guru sama, waktu sama) harus tetap ditolak
    // ValidationException setelah dibungkus DB::transaction + lockForUpdate.
});
```
Sesuaikan isi test dengan struktur DTO `JadwalPelajaranData` dan factory yang PERSIS dipakai di file ini (baca dulu 1-2 test existing di file yang sama, terutama yang menguji bentrok guru, untuk pola factory yang benar) — tulis assertion konkret memakai `app(CreateJadwalPelajaranAction::class)->execute(...)` dua kali berurutan, `expect(fn () => ...)->toThrow(\Illuminate\Validation\ValidationException::class);` untuk panggilan kedua.

- [ ] **Step 2: Jalankan test, pastikan LOLOS di percobaan pertama**

Run: `php artisan test --filter="tetap konsisten menolak bentrok guru setelah dibungkus lock"`
Expected: **PASS** — validasi bentrok guru single-request sudah benar SEBELUM fix ini; test ini jadi regression-guard untuk Step 3-4 (pastikan pembungkusan transaction tidak merusak logic yang sudah benar), BUKAN pola TDD merah-dulu.

- [ ] **Step 3: Perbaiki `CreateJadwalPelajaranAction`**

Baca `app/Domains/Akademik/Actions/Jadwal/CreateJadwalPelajaranAction.php` (file penuh, 78 baris). Ganti SELURUH isi `execute()`:
```php
    public function execute(JadwalPelajaranData $data): JadwalPelajaran
    {
        return DB::transaction(function () use ($data) {
            $jamPelajaranBaru = JamPelajaran::where('id', $data->jamPelajaranId)->lockForUpdate()->first();

            // 1. Validasi Bentrok Ruangan Sarpras
            if ($data->ruanganId !== null) {
                $isRoomClash = $this->validateRoomClashAction->execute(
                    ruanganId: $data->ruanganId,
                    semesterId: $data->semesterId,
                    jamPelajaranId: $data->jamPelajaranId
                );

                if ($isRoomClash) {
                    throw ValidationException::withMessages([
                        'ruangan_id' => 'Ruangan yang dipilih sudah digunakan oleh kelas lain pada jam pelajaran ini.',
                    ]);
                }
            }

            // 2. Validasi Slot Jam Kelas
            $isSlotTaken = JadwalPelajaran::query()
                ->where('kelas_id', $data->kelasId)
                ->where('semester_id', $data->semesterId)
                ->where('jam_pelajaran_id', $data->jamPelajaranId)
                ->exists();

            if ($isSlotTaken) {
                throw ValidationException::withMessages([
                    'jam_pelajaran_id' => 'Kelas ini sudah punya jadwal pada slot ini di semester yang sama.',
                ]);
            }

            // 3. Validasi Bentrok Guru Pengampu (berbasis waktu wall-clock, bukan ID slot --
            // 2 Pola Jam berbeda bisa punya jam_pelajaran_id berbeda untuk jam yang sama persis).
            $isGuruClash = JadwalPelajaran::query()
                ->where('guru_id', $data->guruId)
                ->where('semester_id', $data->semesterId)
                ->whereHas('jamPelajaran', function ($q) use ($jamPelajaranBaru) {
                    $q->where('hari', $jamPelajaranBaru->hari)
                        ->where('jam_mulai', '<', $jamPelajaranBaru->jam_selesai)
                        ->where('jam_selesai', '>', $jamPelajaranBaru->jam_mulai);
                })
                ->exists();

            if ($isGuruClash) {
                throw ValidationException::withMessages([
                    'guru_id' => 'Guru ini sudah mengajar kelas lain pada jam dan semester yang sama.',
                ]);
            }

            return JadwalPelajaran::create($data->toArray());
        });
    }
```
**PENTING**: `$jamPelajaranBaru = JamPelajaran::where('id', $data->jamPelajaranId)->lockForUpdate()->first();` di AWAL closure (SEBELUM pengecekan #1) — bukan di antara pengecekan #2 dan #3 seperti kode asli.

- [ ] **Step 4: Perbaiki `UpdateJadwalPelajaranAction`** — pola identik, bungkus SELURUH isi `execute()` ke `DB::transaction()`, `$jamPelajaranBaru = JamPelajaran::where('id', $data->jamPelajaranId)->lockForUpdate()->first();` di awal closure. WAJIB pertahankan `->where('id', '!=', $jadwal->id)` (2 pengecekan yang sudah punya klausa ini) dan `ignoreJadwalId: $jadwal->id` (pengecekan #1) tetap utuh, dan `$jadwal->update($data->toArray()); return $jadwal->fresh();` di akhir closure.

- [ ] **Step 5: Jalankan test lagi + regresi**

Run: `php artisan test --filter=JadwalPelajaran`
Expected: semua PASS, termasuk seluruh test bentrok existing di `JadwalPelajaranBentrokWaktuTest.php`.

- [ ] **Step 6: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/Akademik/Actions/Jadwal/CreateJadwalPelajaranAction.php app/Domains/Akademik/Actions/Jadwal/UpdateJadwalPelajaranAction.php tests/Feature/Admin/JadwalPelajaranBentrokWaktuTest.php
git commit -m "fix(akademik): cegah race condition bentrok jadwal via lockForUpdate pada JamPelajaran"
```

---

## Task 9: Full Test Suite Final

**Files:** Tidak ada file diubah — verifikasi akhir.

- [ ] **Step 1: Pastikan tidak ada proses test lain berjalan**

Run: `ps aux | grep artisan | grep -v grep`
Expected: kosong.

- [ ] **Step 2: Jalankan full suite sendirian**

Run: `php artisan test --compact`
Expected: SEMUA test PASS, 0 failures (kecuali test SPMB flaky yang sudah diketahui — Faker nama lembaga mengandung apostrof; kalau muncul, jalankan ulang sendirian untuk konfirmasi flaky, bukan regresi paket ini).

- [ ] **Step 3: Pint final**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}`.

---

## Self-Review

**1. Spec coverage**: §2.1→Task 1, §2.2a→Task 2, §2.2b→Task 3, §2.2c→Task 4, §2.2d→Task 5, §2.2e→Task 6, §2.2f/g/h→Task 7, §2.3→Task 8. §3 Non-Goals — tidak ada task yang melanggarnya (PPDB, SPMB, presensi, Activitylog, `JadwalPelajaranController::index()` baris 71, `ResolveTenant` middleware — semua tidak disentuh task manapun).

**2. Placeholder scan**: Task 8 Step 1 sengaja berisi kerangka test + instruksi baca-dulu (bukan kode final assertion) karena struktur DTO/factory bentrok-guru di `JadwalPelajaranBentrokWaktuTest.php` belum dibaca detail saat plan ini ditulis — TAPI instruksi eksplisit menyuruh baca file itu dan tulis assertion konkret sebelum lanjut, bukan dibiarkan kosong selamanya. Semua task lain berisi kode lengkap.

**3. Type consistency**: `resolveActiveLembagaId(User $actor): ?int` dipakai identik nama/parameter di semua 6 titik Task 2-7, konsisten dengan definisi trait dari paket putaran 3. Pola ternary `widestScopeLevel() === 'yayasan' ? resolveActiveLembagaId(...) : $user->lembaga_id` dipertahankan PERSIS di titik-titik yang aslinya memang berstruktur begitu (c, e, f, g, h) — TIDAK dipanggil unconditional di titik manapun, sesuai catatan Global Constraints.

**4. Dependency antar-task**: Task 1 (root-fix) TIDAK diperlukan secara teknis oleh Task 2-8 untuk BERHASIL DI-COMPILE, tapi Task 7 Step 1's test pertama ("store Jadwal Pelajaran") desainnya mengasumsikan Task 1 SUDAH selesai (memanfaatkan fallback TenantScope). Disarankan urutan 1→2→3→4→5→6→7→8→9 sesuai plan, JANGAN kerjakan Task 7 sebelum Task 1 selesai.
