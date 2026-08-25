# Halaman Pengguna — Filter Scope Chip & Visibilitas Lintas-Tenant Platform Admin Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menyempurnakan halaman Pengguna (`admin.users.index`) dengan 7 chip filter kategori (Semua/Platform/Yayasan/Lembaga/Staf/Orang Tua/Siswa), select Role dinamis sesuai chip aktif, search yang mencakup username, dan memperbaiki `widestScopeLevel()`/`TenantScope` supaya `platform_super_admin` benar-benar bisa melihat user lintas-tenant (satu-satunya scope yang boleh begitu, dan HANYA untuk model `User`).

**Architecture:** Perbaikan backend terpusat di 3 file inti (`User::widestScopeLevel()`, `TenantScope::apply()`, `UserController::scopeRank()`) yang menjadi fondasi scope-awareness, lalu perluasan `UserController::index()` dengan query param `scope_group` + count per chip, lalu perluasan Blade+JS yang REUSE komponen Alpine `dataTableFilter` yang sudah ada (bukan komponen baru).

**Tech Stack:** Laravel 12, Pest, Alpine.js, Tom Select.

## Global Constraints

- Baseline kode: commit `f6c2ec4` di branch `rbac-v2`. Kalau isi file yang dikutip plan BEDA signifikan dari baseline, STOP, laporkan ke user.
- **Bypass TenantScope untuk `platform_super_admin` HANYA berlaku untuk model `User`.** Model lain yang memakai `BelongsToTenant` (Karyawan, Kelas, Guru, Siswa, dst) TETAP terbatasi tenant seperti sekarang, termasuk untuk scope `platform`. Ini keputusan eksplisit user (lihat spec §3 Non-Goals) — JANGAN generalisasi bypass ke `TenantScope` secara umum.
- **TIDAK mengubah** halaman "Data Siswa", switcher `active_lembaga_id`, atau perilaku `create()`/`store()`/`edit()`/`update()` di `UserController` — cakupan plan ini murni `index()` (halaman daftar) dan 3 fondasi scope (`widestScopeLevel`, `TenantScope`, `scopeRank`).
- Precedence chip: role pertama yang cocok pada urutan Platform → Yayasan → Lembaga → Staf → Orang Tua → Siswa (tabel lengkap di Task 4).
- Test scoped SEBELUM commit. Full suite HANYA di task terakhir, izin eksplisit user dulu.
- Reuse komponen Alpine `dataTableFilter` (`resources/js/data-table-filter.js`) yang sudah dipakai 5+ halaman lain — JANGAN buat komponen Alpine baru untuk chip ini.

---

## Task 1: Perbaiki `User::widestScopeLevel()` — Tambah Cabang `platform`

**Files:**
- Modify: `app/Models/User.php`
- Test: `tests/Unit/UserScopeTest.php`

**Interfaces:**
- Produces: `User::widestScopeLevel(): string` sekarang bisa mengembalikan `'platform'`, dipakai Task 2 (`TenantScope`) dan Task 3 (`scopeRank`).

- [ ] **Step 1: Baca ulang file existing, konfirmasi method sama dengan baseline**

```bash
grep -n "widestScopeLevel" -A 8 app/Models/User.php
```
Expected baseline:
```php
public function widestScopeLevel(): string
{
    $levels = $this->roles->pluck('scope_level');

    return match (true) {
        $levels->contains('yayasan') || $this->hasRole(['yayasan_super_admin', 'super_admin', 'bendahara_yayasan']) => 'yayasan',
        $levels->contains('lembaga') => 'lembaga',
        default => 'diri_sendiri',
    };
}
```

- [ ] **Step 2: Tulis test yang gagal dulu**

Tambahkan ke `tests/Unit/UserScopeTest.php` (setelah test terakhir yang ada):

```php
it('returns platform as the widest scope when user has a platform-scoped role', function () {
    Role::firstOrCreate(['name' => 'platform_super_admin', 'guard_name' => 'web'], ['scope_level' => 'platform', 'is_protected' => true]);

    $user = User::factory()->create();
    $user->assignRole('platform_super_admin');

    expect($user->widestScopeLevel())->toBe('platform');
});

it('prefers platform over yayasan when a user somehow has both scopes', function () {
    Role::firstOrCreate(['name' => 'platform_super_admin', 'guard_name' => 'web'], ['scope_level' => 'platform', 'is_protected' => true]);
    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);

    $user = User::factory()->create();
    $user->assignRole(['platform_super_admin', 'yayasan_super_admin']);

    expect($user->widestScopeLevel())->toBe('platform');
});
```

- [ ] **Step 3: Jalankan test, konfirmasi gagal**

```bash
php artisan test tests/Unit/UserScopeTest.php
```
Expected: 2 test baru FAIL (widestScopeLevel belum mengenali `platform`, jatuh ke `default => 'diri_sendiri'`).

- [ ] **Step 4: Ubah method**

Ganti isi method `widestScopeLevel()` di `app/Models/User.php`:

```php
    public function widestScopeLevel(): string
    {
        $levels = $this->roles->pluck('scope_level');

        return match (true) {
            $levels->contains('platform') => 'platform',
            $levels->contains('yayasan') || $this->hasRole(['yayasan_super_admin', 'super_admin', 'bendahara_yayasan']) => 'yayasan',
            $levels->contains('lembaga') => 'lembaga',
            default => 'diri_sendiri',
        };
    }
```

- [ ] **Step 5: Jalankan test, konfirmasi lulus**

```bash
php artisan test tests/Unit/UserScopeTest.php
```
Expected: semua test PASS (termasuk 4 test lama yang sudah ada — pastikan tidak ada regresi).

- [ ] **Step 6: Commit**

```bash
git add app/Models/User.php tests/Unit/UserScopeTest.php
git commit -m "feat(rbac): widestScopeLevel() mengenali scope platform"
```

---

## Task 2: Perbaiki `TenantScope` — Bypass Khusus Model `User` untuk Scope `platform`

**Files:**
- Modify: `app/Models/Scopes/TenantScope.php`
- Test: `tests/Unit/TenantScopePlatformBypassTest.php` (baru)

**Interfaces:**
- Consumes: `User::widestScopeLevel()` (Task 1, sekarang bisa return `'platform'`).
- Produces: query `User::` tidak lagi difilter tenant sama sekali ketika acting user berscope `platform`. Model lain (`Karyawan`, dst) TIDAK terpengaruh.

- [ ] **Step 1: Baca ulang file existing, konfirmasi struktur method `apply()` sama dengan baseline**

```bash
grep -n "public function apply" -A 40 app/Models/Scopes/TenantScope.php
```
Baseline sudah dikutip lengkap di eksplorasi sebelumnya — pastikan urutan: re-entrancy guard → resolve `$actingUser` → cabang `yayasan` → fallback `where lembaga_id = $actingUser->lembaga_id`.

- [ ] **Step 2: Tulis test yang gagal dulu**

Buat file baru `tests/Unit/TenantScopePlatformBypassTest.php`:

```php
<?php

use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function buatPlatformSuperAdmin(): User
{
    Role::firstOrCreate(['name' => 'platform_super_admin', 'guard_name' => 'web'], ['scope_level' => 'platform', 'is_protected' => true]);
    $admin = User::factory()->create();
    $admin->assignRole('platform_super_admin');

    return $admin;
}

it('lets a platform_super_admin see User rows across multiple yayasan and lembaga', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $userA = User::factory()->create(['lembaga_id' => $lembagaA->id, 'email' => 'usera@example.test']);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $userB = User::factory()->create(['lembaga_id' => $lembagaB->id, 'email' => 'userb@example.test']);

    $platformAdmin = buatPlatformSuperAdmin();

    $this->actingAs($platformAdmin);

    $visibleEmails = User::pluck('email');

    expect($visibleEmails)->toContain('usera@example.test');
    expect($visibleEmails)->toContain('userb@example.test');
});

it('does not extend the platform bypass to other tenant-scoped models like Karyawan', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    Karyawan::factory()->create(['yayasan_id' => $yayasanA->id, 'lembaga_id' => $lembagaA->id, 'nik' => '1111111111111111']);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    Karyawan::factory()->create(['yayasan_id' => $yayasanB->id, 'lembaga_id' => $lembagaB->id, 'nik' => '2222222222222222']);

    $platformAdmin = buatPlatformSuperAdmin();

    $this->actingAs($platformAdmin);

    // platformAdmin punya lembaga_id null, jadi cabang default (where lembaga_id = null)
    // tetap berlaku untuk Karyawan -- membuktikan bypass TIDAK menyebar ke model lain.
    expect(Karyawan::count())->toBe(0);
});

it('still isolates a yayasan-scoped admin to their own yayasan after the platform bypass is added', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $userA = User::factory()->create(['lembaga_id' => $lembagaA->id, 'email' => 'staffa@example.test']);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    User::factory()->create(['lembaga_id' => $lembagaB->id, 'email' => 'staffb@example.test']);

    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $managerA = User::factory()->create(['yayasan_id' => $yayasanA->id]);
    $managerA->assignRole('yayasan_super_admin');

    $this->actingAs($managerA);

    $visibleEmails = User::pluck('email');

    expect($visibleEmails)->toContain('staffa@example.test');
    expect($visibleEmails)->not->toContain('staffb@example.test');
});
```

- [ ] **Step 3: Jalankan test, konfirmasi test pertama gagal**

```bash
php artisan test tests/Unit/TenantScopePlatformBypassTest.php
```
Expected: test pertama ("lets a platform_super_admin see...") FAIL (platform admin saat ini jatuh ke cabang default, `lembaga_id = null`, tidak melihat `usera@example.test`/`userb@example.test`). 2 test lain kemungkinan sudah PASS (perilaku existing tidak berubah) — itu wajar, jadi baseline untuk regresi.

- [ ] **Step 4: Ubah `TenantScope::apply()`**

Di `app/Models/Scopes/TenantScope.php`, tambahkan blok baru PERSIS SETELAH baris `if (! $actingUser) { return; }` dan SEBELUM baris `if ($actingUser->widestScopeLevel() === 'yayasan') {`:

```php
        if ($actingUser->widestScopeLevel() === 'platform' && $model instanceof User) {
            return;
        }

```

- [ ] **Step 5: Jalankan test, konfirmasi semua lulus**

```bash
php artisan test tests/Unit/TenantScopePlatformBypassTest.php
```
Expected: 3 test PASS.

- [ ] **Step 6: Jalankan test regresi terkait TenantScope**

```bash
php artisan test tests/Unit/UserScopeTest.php tests/Feature/Admin/UserManagementTest.php
```
Expected: semua PASS, tidak ada regresi pada isolasi tenant existing.

- [ ] **Step 7: Commit**

```bash
git add app/Models/Scopes/TenantScope.php tests/Unit/TenantScopePlatformBypassTest.php
git commit -m "feat(rbac): TenantScope bypass khusus model User untuk scope platform"
```

---

## Task 3: Perbaiki `UserController::scopeRank()` — Tambah `platform`

**Files:**
- Modify: `app/Http/Controllers/Admin/UserController.php`
- Test: `tests/Feature/Admin/UserManagementTest.php`

**Interfaces:**
- Consumes: `User::widestScopeLevel()` (Task 1).
- Produces: `scopeRank('platform')` sekarang `4` (tertinggi), dipakai oleh `create()`/`store()`/`edit()`/`update()` untuk gate rank-assignment role (TIDAK diubah logic-nya, hanya nilai rank baru ditambah).

- [ ] **Step 1: Baca ulang method existing**

```bash
grep -n "private function scopeRank" -A 6 app/Http/Controllers/Admin/UserController.php
```
Expected baseline:
```php
    private function scopeRank(string $level): int
    {
        return match ($level) {
            'yayasan' => 3,
            'lembaga' => 2,
            default => 1, // diri_sendiri
        };
    }
```

- [ ] **Step 2: Tulis test yang gagal dulu**

Tambahkan ke `tests/Feature/Admin/UserManagementTest.php` (setelah test terakhir yang ada):

```php
it('lets a platform_super_admin assign a yayasan-scoped role to a new user', function () {
    foreach (['users.view', 'users.create', 'users.edit', 'users.toggle-active'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $platformRole = Role::firstOrCreate(['name' => 'platform_super_admin', 'guard_name' => 'web'], ['scope_level' => 'platform', 'is_protected' => true]);
    $platformRole->givePermissionTo(['users.view', 'users.create', 'users.edit', 'users.toggle-active']);
    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);

    $platformAdmin = User::factory()->create();
    $platformAdmin->assignRole($platformRole);

    $this->actingAs($platformAdmin)->post(route('admin.users.store'), [
        'name' => 'Admin Yayasan Baru dari Platform',
        'email' => 'dariplatform@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'yayasan_super_admin',
    ])->assertRedirect(route('admin.users.index'));

    expect(User::withoutGlobalScopes()->where('email', 'dariplatform@example.test')->exists())->toBeTrue();
});
```

- [ ] **Step 3: Jalankan test, konfirmasi gagal**

```bash
php artisan test tests/Feature/Admin/UserManagementTest.php --filter "lets a platform_super_admin assign"
```
Expected: FAIL — `scopeRank('platform')` saat ini jatuh ke `default => 1`, lebih rendah dari `scopeRank('yayasan') = 3`, sehingga request ditolak dengan error "scope lebih luas dari scope Anda sendiri" alih-alih redirect sukses.

- [ ] **Step 4: Ubah method**

Ganti isi method `scopeRank()` di `app/Http/Controllers/Admin/UserController.php`:

```php
    private function scopeRank(string $level): int
    {
        return match ($level) {
            'platform' => 4,
            'yayasan' => 3,
            'lembaga' => 2,
            default => 1, // diri_sendiri
        };
    }
```

- [ ] **Step 5: Jalankan test, konfirmasi lulus**

```bash
php artisan test tests/Feature/Admin/UserManagementTest.php
```
Expected: semua PASS (termasuk seluruh test lama di file ini — tidak ada regresi).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/UserController.php tests/Feature/Admin/UserManagementTest.php
git commit -m "feat(rbac): scopeRank() beri platform rank tertinggi (4)"
```

---

## Task 4: `UserController::index()` — Query Param `scope_group`, Hapus Exclude Siswa, Search Username, Count per Chip

**Files:**
- Modify: `app/Http/Controllers/Admin/UserController.php`

**Interfaces:**
- Produces: method privat baru `scopeGroups(): array` (dipakai Task 6 lewat data yang di-passing ke view); view `admin.users.index` dan `admin.users._daftar` sekarang menerima variabel baru: `$scopeGroup`, `$scopeCounts` (array assoc 7 key: `semua`, `platform`, `yayasan`, `lembaga`, `staf`, `orang_tua`, `siswa`), `$isPlatformViewer` (bool), `$rolesByGroup` (array assoc, tiap value array nama role dalam grup itu).

- [ ] **Step 1: Baca ulang method `index()` existing**

```bash
grep -n "public function index" -A 40 app/Http/Controllers/Admin/UserController.php
```
Konfirmasi sama dengan yang dikutip di §3.1 spec (baca `.agents/specs/2026-08-25-rbac-pengguna-scope-filter.md` §6.1 kalau perlu rujukan lengkap).

- [ ] **Step 2: Ganti seluruh method `index()` dan tambah method privat baru**

Ganti method `index()`:

```php
    public function index(Request $request): View|\Illuminate\Http\JsonResponse
    {
        $this->authorize('users.view');

        $search = $request->input('search');
        $roleFilter = $request->input('role');
        $scopeGroup = $request->input('scope_group');
        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

        $groups = $this->scopeGroups();

        $query = User::with('roles', 'lembaga', 'yayasan')
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%")))
            ->when($roleFilter, fn ($q) => $q->whereHas('roles', fn ($q2) => $q2->where('name', $roleFilter)))
            ->when($scopeGroup && isset($groups[$scopeGroup]), fn ($q) => $q->whereHas('roles', fn ($q2) => $q2->whereIn('name', $groups[$scopeGroup])))
            ->orderBy('name');

        $users = $query->paginate($perPage)->withQueryString();

        $totalUsers = User::count();
        $totalAktif = User::where('is_active', true)->count();
        $totalNonaktif = User::where('is_active', false)->count();

        $scopeCounts = ['semua' => $totalUsers];
        foreach ($groups as $groupName => $roleNames) {
            $scopeCounts[$groupName] = User::whereHas('roles', fn ($q) => $q->whereIn('name', $roleNames))->count();
        }

        $isPlatformViewer = $request->user()->widestScopeLevel() === 'platform';

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('admin.users._daftar', [
                'users' => $users,
                'perPage' => $perPage,
                'isPlatformViewer' => $isPlatformViewer,
            ]);
        }

        $availableRoles = Role::orderBy('name')->get();

        $rolesByGroup = ['semua' => $availableRoles->pluck('name')->values()];
        foreach ($groups as $groupName => $roleNames) {
            $rolesByGroup[$groupName] = $availableRoles->whereIn('name', $roleNames)->pluck('name')->values();
        }

        return view('admin.users.index', [
            'users' => $users,
            'perPage' => $perPage,
            'search' => $search,
            'roleFilter' => $roleFilter,
            'scopeGroup' => $scopeGroup,
            'availableRoles' => $availableRoles,
            'rolesByGroup' => $rolesByGroup,
            'scopeCounts' => $scopeCounts,
            'isPlatformViewer' => $isPlatformViewer,
            'totalUsers' => $totalUsers,
            'totalAktif' => $totalAktif,
            'totalNonaktif' => $totalNonaktif,
        ]);
    }

    /**
     * Peta kategori chip filter ke daftar nama role, sesuai precedence RBAC v2
     * (spec .agents/specs/2026-08-25-rbac-pengguna-scope-filter.md §4).
     */
    private function scopeGroups(): array
    {
        return [
            'platform' => ['platform_super_admin'],
            'yayasan' => ['yayasan_super_admin', 'bendahara_yayasan', 'pegawai_yayasan'],
            'lembaga' => ['kepala_sekolah', 'wakasek_kurikulum', 'wakasek_kesiswaan', 'operator_akademik', 'admin_sdm', 'bendahara_lembaga', 'admin_sarpras', 'admin_administrasi', 'pegawai_lembaga'],
            'staf' => ['guru', 'wali_kelas', 'guru_bk'],
            'orang_tua' => ['orang_tua'],
            'siswa' => ['siswa'],
        ];
    }
```

Catatan: `$totalUsers`/`$totalAktif`/`$totalNonaktif` SEKARANG tanpa `whereDoesntHave(..., 'siswa')` — perubahan perilaku yang disengaja (siswa sekarang ikut terhitung di stat card "Total Akun"), konsisten dengan spec §6.1.

- [ ] **Step 3: Verifikasi syntax**

```bash
php -l app/Http/Controllers/Admin/UserController.php
```

- [ ] **Step 4: Jalankan test yang sudah ada (akan ada yang gagal, itu diharapkan — diperbaiki di Task 5)**

```bash
php artisan test tests/Feature/Admin/UserManagementTest.php
```
Expected: test `'excludes siswa accounts from the staff Pengguna list'` FAIL (siswa sekarang ikut muncul di chip Semua/default) — INI DIHARAPKAN, jangan perbaiki kode untuk membuatnya lulus, perbaiki TEST-nya di Task 5. Test lain harus tetap PASS.

- [ ] **Step 5: Commit (test yang gagal di atas akan diperbaiki di task berikutnya, JANGAN commit dulu sebelum Task 5 selesai — lanjut langsung ke Task 5 tanpa commit terpisah di sini)**

Task 4 dan Task 5 di-commit BERSAMA di akhir Task 5 (satu commit gabungan), karena Task 4 sengaja membuat 1 test lama gagal sampai Task 5 memperbaikinya — memisah commit di sini akan meninggalkan working tree dengan test merah.

---

## Task 5: Perbaiki Test Existing yang Terdampak Perubahan Perilaku Siswa

**Files:**
- Modify: `tests/Feature/Admin/UserManagementTest.php`

**Interfaces:**
- Consumes: `UserController::index()` (Task 4, sekarang menerima `scope_group` dan tidak lagi exclude siswa secara permanen).

- [ ] **Step 1: Baca ulang test yang perlu diperbaiki**

```bash
grep -n "excludes siswa accounts" -A 20 tests/Feature/Admin/UserManagementTest.php
```

- [ ] **Step 2: Ganti test tersebut**

Cari blok test:
```php
it('excludes siswa accounts from the staff Pengguna list', function () {
    $manager = actingAsUserManager();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);

    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $siswaUser = User::factory()->create(['username' => 'siswa.excluded', 'email' => 'siswa.excluded@example.test', 'lembaga_id' => $lembaga->id]);
    $siswaUser->assignRole('siswa');

    $staffUser = User::factory()->create(['username' => 'staff.included', 'email' => 'staff.included@example.test', 'lembaga_id' => $lembaga->id]);
    $staffUser->assignRole('admin_administrasi');

    $response = $this->actingAs($manager)->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertDontSee($siswaUser->username);
    $response->assertDontSee('siswa.excluded@example.test');
    $response->assertSee('staff.included@example.test');
});
```

Ganti dengan (perilaku baru: default/chip Semua MENAMPILKAN siswa; chip `lembaga` MENGECUALIKAN siswa; chip `siswa` HANYA menampilkan siswa):

```php
it('includes siswa accounts in the default (Semua) Pengguna list', function () {
    $manager = actingAsUserManager();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);

    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $siswaUser = User::factory()->create(['username' => 'siswa.included', 'email' => 'siswa.included@example.test', 'lembaga_id' => $lembaga->id]);
    $siswaUser->assignRole('siswa');

    $staffUser = User::factory()->create(['username' => 'staff.included', 'email' => 'staff.included@example.test', 'lembaga_id' => $lembaga->id]);
    $staffUser->assignRole('admin_administrasi');

    $response = $this->actingAs($manager)->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertSee('siswa.included@example.test');
    $response->assertSee('staff.included@example.test');
});

it('excludes siswa accounts when the lembaga scope chip is active', function () {
    $manager = actingAsUserManager();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);

    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $siswaUser = User::factory()->create(['username' => 'siswa.hidden', 'email' => 'siswa.hidden@example.test', 'lembaga_id' => $lembaga->id]);
    $siswaUser->assignRole('siswa');

    $staffUser = User::factory()->create(['username' => 'staff.shown', 'email' => 'staff.shown@example.test', 'lembaga_id' => $lembaga->id]);
    $staffUser->assignRole('admin_administrasi');

    $response = $this->actingAs($manager)->get(route('admin.users.index', ['scope_group' => 'lembaga']));

    $response->assertOk();
    $response->assertDontSee('siswa.hidden@example.test');
    $response->assertSee('staff.shown@example.test');
});

it('shows only siswa accounts when the siswa scope chip is active', function () {
    $manager = actingAsUserManager();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);

    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $siswaUser = User::factory()->create(['username' => 'siswa.only', 'email' => 'siswa.only@example.test', 'lembaga_id' => $lembaga->id]);
    $siswaUser->assignRole('siswa');

    $staffUser = User::factory()->create(['username' => 'staff.excluded', 'email' => 'staff.excluded@example.test', 'lembaga_id' => $lembaga->id]);
    $staffUser->assignRole('admin_administrasi');

    $response = $this->actingAs($manager)->get(route('admin.users.index', ['scope_group' => 'siswa']));

    $response->assertOk();
    $response->assertSee('siswa.only@example.test');
    $response->assertDontSee('staff.excluded@example.test');
});
```

- [ ] **Step 3: Jalankan test, konfirmasi lulus**

```bash
php artisan test tests/Feature/Admin/UserManagementTest.php
```
Expected: semua PASS.

- [ ] **Step 4: Commit gabungan Task 4 + Task 5**

```bash
git add app/Http/Controllers/Admin/UserController.php tests/Feature/Admin/UserManagementTest.php
git commit -m "feat(rbac): UserController index tambah scope_group filter, search username, siswa tidak lagi selalu dikecualikan"
```

---

## Task 6: Blade `index.blade.php` — Chip Scope, Placeholder Search, Data Role-per-Grup

**Files:**
- Modify: `resources/views/admin/users/index.blade.php`

**Interfaces:**
- Consumes: `$scopeGroup`, `$scopeCounts`, `$rolesByGroup`, `$isPlatformViewer` (Task 4).
- Produces: config Alpine `dataTableFilter` sekarang menerima `filters.scope_group` dan `roleGroups` — dipakai Task 8 (JS).

- [ ] **Step 1: Baca ulang file existing**

```bash
cat resources/views/admin/users/index.blade.php
```
Konfirmasi sama dengan baseline yang sudah dikutip di eksplorasi.

- [ ] **Step 2: Ubah bagian `x-data` dan tambahkan blok chip + ubah placeholder search**

Ganti baris pembuka:
```html
    <div class="mx-auto max-w-6xl space-y-4" x-data="dataTableFilter({
        filters: {
            search: @js($search),
            role: @js($roleFilter),
        },
        perPage: @js($perPage),
        indexUrlBase: @js(route('admin.users.index')),
    })">
```
Menjadi:
```html
    <div class="mx-auto max-w-6xl space-y-4" x-data="dataTableFilter({
        filters: {
            search: @js($search),
            role: @js($roleFilter),
            scope_group: @js($scopeGroup),
        },
        perPage: @js($perPage),
        indexUrlBase: @js(route('admin.users.index')),
        roleGroups: @js($rolesByGroup),
    })">
```

Ganti placeholder search:
```html
                        <input x-model="filters.search" @input.debounce.500ms="muatUlangDaftar()" type="text" placeholder="Cari nama atau email..." class="w-full border-0 bg-transparent p-0 text-xs sm:text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0">
```
Menjadi:
```html
                        <input x-model="filters.search" @input.debounce.500ms="muatUlangDaftar()" type="text" placeholder="Cari nama, email, atau username..." class="w-full border-0 bg-transparent p-0 text-xs sm:text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0">
```

Tambahkan blok chip PERSIS SETELAH `<div class="grid grid-cols-1 gap-4 lg:grid-cols-4 lg:items-end">...</div>` (penutup grid search+role select) dan SEBELUM penutup `</div>` dari card filter (`<div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">`):

```html
            <div class="mt-4 flex items-center gap-2 overflow-x-auto border-t border-gray-100 pt-3">
                @php
                    $chipLabels = [
                        '' => 'Semua',
                        'platform' => 'Platform',
                        'yayasan' => 'Yayasan',
                        'lembaga' => 'Lembaga',
                        'staf' => 'Staf',
                        'orang_tua' => 'Orang Tua',
                        'siswa' => 'Siswa',
                    ];
                @endphp
                @foreach ($chipLabels as $chipValue => $chipLabel)
                    @php $chipCountKey = $chipValue === '' ? 'semua' : $chipValue; @endphp
                    <button
                        type="button"
                        @click="setScopeGroup(@js($chipValue))"
                        :class="(filters.scope_group ?? '') === @js($chipValue) ? 'bg-brand-50 font-semibold text-brand-600 border-brand-200 shadow-2xs' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border-gray-200'"
                        class="flex items-center gap-2 whitespace-nowrap rounded-lg border px-3.5 py-1.5 text-xs transition-all"
                    >
                        <span>{{ $chipLabel }}</span>
                        <span
                            :class="(filters.scope_group ?? '') === @js($chipValue) ? 'bg-brand-100 text-brand-700' : 'bg-gray-200 text-gray-700'"
                            class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                        >{{ $scopeCounts[$chipCountKey] ?? 0 }}</span>
                    </button>
                @endforeach
            </div>
```

- [ ] **Step 3: Verifikasi syntax Blade (render halaman via test di Task 9 nanti akan memverifikasi ini lebih jauh; untuk sekarang cukup cek tidak ada typo tag)**

```bash
php artisan view:clear
```
Expected: tidak ada error compile saat halaman diakses (diverifikasi lebih lanjut di Task 9's test render).

- [ ] **Step 4: Commit**

```bash
git add resources/views/admin/users/index.blade.php
git commit -m "feat(rbac): tambah 7 chip filter scope + placeholder search username di halaman Pengguna"
```

---

## Task 7: Blade `_daftar.blade.php` — Kolom Yayasan/Lembaga Kondisional

**Files:**
- Modify: `resources/views/admin/users/_daftar.blade.php`

**Interfaces:**
- Consumes: `$isPlatformViewer` (Task 4).

- [ ] **Step 1: Baca ulang file existing**

```bash
cat resources/views/admin/users/_daftar.blade.php
```

- [ ] **Step 2: Ubah header tabel**

Ganti:
```html
            <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                <th class="sticky left-0 z-10 bg-white px-5 py-3 w-32">Aksi</th>
                <th class="px-5 py-3">Nama</th>
                <th class="px-5 py-3">Email</th>
                <th class="px-5 py-3">Role</th>
                <th class="px-5 py-3">Lembaga</th>
                <th class="px-5 py-3">Status</th>
            </tr>
```
Menjadi:
```html
            <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                <th class="sticky left-0 z-10 bg-white px-5 py-3 w-32">Aksi</th>
                <th class="px-5 py-3">Nama</th>
                <th class="px-5 py-3">Email</th>
                <th class="px-5 py-3">Role</th>
                @if ($isPlatformViewer)
                    <th class="px-5 py-3">Yayasan</th>
                @endif
                <th class="px-5 py-3">Lembaga</th>
                <th class="px-5 py-3">Status</th>
            </tr>
```

- [ ] **Step 3: Ubah baris data & colspan kosong**

Ganti:
```html
                    <td class="px-5 py-3 align-top text-gray-500">{{ $user->roles->pluck('name')->implode(', ') }}</td>
                    <td class="px-5 py-3 align-top text-gray-500">{{ $user->lembaga?->nama ?? '—' }}</td>
```
Menjadi:
```html
                    <td class="px-5 py-3 align-top text-gray-500">{{ $user->roles->pluck('name')->implode(', ') }}</td>
                    @if ($isPlatformViewer)
                        <td class="px-5 py-3 align-top text-gray-500">{{ $user->yayasan?->nama ?? '—' }}</td>
                    @endif
                    <td class="px-5 py-3 align-top text-gray-500">{{ $user->lembaga?->nama ?? '—' }}</td>
```

Ganti:
```html
                    <td colspan="6" class="px-5 py-8 text-center text-gray-500">Tidak ada akun yang ditemukan.</td>
```
Menjadi:
```html
                    <td colspan="{{ $isPlatformViewer ? 7 : 6 }}" class="px-5 py-8 text-center text-gray-500">Tidak ada akun yang ditemukan.</td>
```

- [ ] **Step 4: Commit**

```bash
git add resources/views/admin/users/_daftar.blade.php
git commit -m "feat(rbac): kolom Yayasan kondisional di tabel Pengguna untuk viewer platform_super_admin"
```

---

## Task 8: JS `data-table-filter.js` — Method `setScopeGroup()` & Refresh Opsi Role Dinamis

**Files:**
- Modify: `resources/js/data-table-filter.js`

**Interfaces:**
- Consumes: `config.roleGroups` (Task 6, assoc object `{semua: [...], platform: [...], ...}`).
- Produces: method `setScopeGroup(group: string)` dan `refreshRoleOptions(group: string)` di komponen `dataTableFilter` — dipakai oleh chip button di Task 6's Blade (`@click="setScopeGroup(...)"`).

**PENTING**: `dataTableFilter()` adalah komponen SHARED dipakai halaman Peran/Siswa/Guru/Kelas/Lembaga juga — perubahan ini HARUS backward-compatible (halaman lain yang tidak passing `roleGroups`/tidak punya chip scope TIDAK BOLEH rusak).

- [ ] **Step 1: Baca ulang file existing**

```bash
cat resources/js/data-table-filter.js
```
Konfirmasi sama dengan baseline yang sudah dikutip (function `dataTableFilter(config)`, property `filters`/`perPage`/`indexUrlBase`/`tomSelects`, method `initFilterSelect`/`muatUlangDaftar`).

- [ ] **Step 2: Tambah property `roleGroups` dan 2 method baru**

Ganti:
```js
export function dataTableFilter(config) {
    return {
        filters: config.filters || {},
        perPage: config.perPage ?? 20,
        indexUrlBase: config.indexUrlBase,
        tomSelects: {},

        initFilterSelect(el, fieldName, isSearchable = false) {
```
Menjadi:
```js
export function dataTableFilter(config) {
    return {
        filters: config.filters || {},
        perPage: config.perPage ?? 20,
        indexUrlBase: config.indexUrlBase,
        roleGroups: config.roleGroups || {},
        tomSelects: {},

        setScopeGroup(group) {
            this.filters.scope_group = group;
            this.filters.role = '';
            this.refreshRoleOptions(group);
            this.muatUlangDaftar();
        },

        refreshRoleOptions(group) {
            const ts = this.tomSelects.role;
            if (!ts) return;

            const roles = (group && this.roleGroups[group]) || this.roleGroups.semua || [];

            ts.clear(true);
            ts.clearOptions();
            ts.addOption({ value: '', text: 'Semua Role' });
            roles.forEach((roleName) => ts.addOption({ value: roleName, text: roleName }));
            ts.refreshOptions(false);
        },

        initFilterSelect(el, fieldName, isSearchable = false) {
```

- [ ] **Step 3: Verifikasi syntax (build frontend)**

```bash
npm run build
```
Expected: build sukses tanpa error.

- [ ] **Step 4: Jalankan test halaman-halaman lain yang memakai `dataTableFilter` untuk memastikan tidak ada regresi (per Global Constraints — komponen ini shared)**

```bash
php artisan test tests/Feature/Admin/RoleManagementTest.php
```
(Kalau file test ini tidak ada, cari nama test yang menyentuh `admin.roles.index` dengan `grep -rl "admin.roles.index" tests --include="*.php"` dan jalankan itu sebagai gantinya — tujuannya memastikan halaman Peran, yang memakai komponen JS sama, tidak rusak oleh perubahan ini.)
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/data-table-filter.js
git commit -m "feat(rbac): dataTableFilter tambah setScopeGroup() + refresh opsi role dinamis"
```

---

## Task 9: Test Feature Baru — Chip Filtering, Count Badge, Visibilitas Lintas-Tenant Platform Admin

**Files:**
- Create: `tests/Feature/Admin/UserPenggunaScopeChipTest.php`

**Interfaces:**
- Consumes: `UserController::index()` (Task 4), `TenantScope` (Task 2), `widestScopeLevel()` (Task 1).

- [ ] **Step 1: Baca ulang `actingAsUserManager()` helper di `tests/Feature/Admin/UserManagementTest.php` untuk pola setup permission yang konsisten**

```bash
grep -n "function actingAsUserManager" -A 18 tests/Feature/Admin/UserManagementTest.php
```

- [ ] **Step 2: Tulis file test baru**

```php
<?php

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function seedSemuaRoleUntukChipTest(): void
{
    $roles = [
        'platform_super_admin' => 'platform',
        'yayasan_super_admin' => 'yayasan',
        'bendahara_yayasan' => 'yayasan',
        'pegawai_yayasan' => 'yayasan',
        'kepala_sekolah' => 'lembaga',
        'admin_administrasi' => 'lembaga',
        'pegawai_lembaga' => 'lembaga',
        'guru' => 'diri_sendiri',
        'wali_kelas' => 'lembaga',
        'orang_tua' => 'diri_sendiri',
        'siswa' => 'diri_sendiri',
    ];

    foreach ($roles as $name => $scopeLevel) {
        Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['scope_level' => $scopeLevel, 'is_protected' => in_array($name, ['platform_super_admin', 'yayasan_super_admin'], true)]);
    }
}

function buatPlatformAdminUntukChipTest(): User
{
    foreach (['users.view', 'users.create', 'users.edit', 'users.toggle-active'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    seedSemuaRoleUntukChipTest();
    $role = Role::where('name', 'platform_super_admin')->firstOrFail();
    $role->givePermissionTo(['users.view', 'users.create', 'users.edit', 'users.toggle-active']);

    $admin = User::factory()->create();
    $admin->assignRole($role);

    return $admin;
}

it('shows only pegawai_lembaga-family accounts and their count when the lembaga chip is active', function () {
    $admin = buatPlatformAdminUntukChipTest();
    $lembaga = Lembaga::factory()->create();

    $kepsek = User::factory()->create(['lembaga_id' => $lembaga->id, 'email' => 'kepsek.chip@example.test']);
    $kepsek->assignRole(['kepala_sekolah', 'pegawai_lembaga']);

    $guru = User::factory()->create(['lembaga_id' => $lembaga->id, 'email' => 'guru.chip@example.test']);
    $guru->assignRole(['guru', 'pegawai_lembaga']);

    $response = $this->actingAs($admin)->get(route('admin.users.index', ['scope_group' => 'lembaga']));

    $response->assertOk();
    $response->assertSee('kepsek.chip@example.test');
    $response->assertDontSee('guru.chip@example.test');
});

it('shows only staf-family accounts when the staf chip is active, excluding pegawai_lembaga-only admins', function () {
    $admin = buatPlatformAdminUntukChipTest();
    $lembaga = Lembaga::factory()->create();

    $kepsek = User::factory()->create(['lembaga_id' => $lembaga->id, 'email' => 'kepsek.staf@example.test']);
    $kepsek->assignRole(['kepala_sekolah', 'pegawai_lembaga']);

    $guru = User::factory()->create(['lembaga_id' => $lembaga->id, 'email' => 'guru.staf@example.test']);
    $guru->assignRole(['guru', 'pegawai_lembaga']);

    $response = $this->actingAs($admin)->get(route('admin.users.index', ['scope_group' => 'staf']));

    $response->assertOk();
    $response->assertSee('guru.staf@example.test');
    $response->assertDontSee('kepsek.staf@example.test');
});

it('shows only platform_super_admin accounts when the platform chip is active', function () {
    $admin = buatPlatformAdminUntukChipTest();
    $lembaga = Lembaga::factory()->create();

    $yayasanAdmin = User::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id, 'email' => 'yayasan.platformchip@example.test']);
    $yayasanAdmin->assignRole('yayasan_super_admin');

    $response = $this->actingAs($admin)->get(route('admin.users.index', ['scope_group' => 'platform']));

    $response->assertOk();
    $response->assertSee($admin->email);
    $response->assertDontSee('yayasan.platformchip@example.test');
});

it('displays correct scope chip counts matching the actual filtered results', function () {
    $admin = buatPlatformAdminUntukChipTest();
    $lembaga = Lembaga::factory()->create();

    $guru1 = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru1->assignRole(['guru', 'pegawai_lembaga']);
    $guru2 = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru2->assignRole(['guru', 'pegawai_lembaga']);

    $response = $this->actingAs($admin)->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertViewHas('scopeCounts', function ($scopeCounts) {
        return $scopeCounts['staf'] === 2;
    });
});

it('lets a platform_super_admin see users across multiple yayasan on the Pengguna page, with Yayasan column visible', function () {
    $admin = buatPlatformAdminUntukChipTest();

    $yayasanA = Yayasan::factory()->create(['nama' => 'Yayasan Alpha']);
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $userA = User::factory()->create(['lembaga_id' => $lembagaA->id, 'email' => 'usera.lintas@example.test']);
    $userA->assignRole(['guru', 'pegawai_lembaga']);

    $yayasanB = Yayasan::factory()->create(['nama' => 'Yayasan Beta']);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $userB = User::factory()->create(['lembaga_id' => $lembagaB->id, 'email' => 'userb.lintas@example.test']);
    $userB->assignRole(['guru', 'pegawai_lembaga']);

    $response = $this->actingAs($admin)->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertSee('usera.lintas@example.test');
    $response->assertSee('userb.lintas@example.test');
    $response->assertSee('Yayasan Alpha');
    $response->assertSee('Yayasan Beta');
});

it('does not show the Yayasan column for a non-platform viewer', function () {
    foreach (['users.view'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    seedSemuaRoleUntukChipTest();
    $role = Role::where('name', 'yayasan_super_admin')->firstOrFail();
    $role->givePermissionTo('users.view');

    $manager = User::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $manager->assignRole($role);

    $response = $this->actingAs($manager)->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertDontSee('<th class="px-5 py-3">Yayasan</th>', false);
});
```

- [ ] **Step 3: Jalankan test, konfirmasi lulus**

```bash
php artisan test tests/Feature/Admin/UserPenggunaScopeChipTest.php
```
Expected: semua PASS. Kalau ADA yang gagal karena asumsi factory/relasi tidak cocok dengan struktur aktual (mis. `Yayasan::factory()` tidak punya field `nama`), STOP, verifikasi struktur factory aktual dulu (`grep -n "nama" database/factories/YayasanFactory.php`), sesuaikan test — JANGAN paksakan assertion yang salah demi lolos.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Admin/UserPenggunaScopeChipTest.php
git commit -m "test(rbac): tambah test chip filtering, count badge, dan visibilitas lintas-tenant platform admin"
```

---

## Task 10: Verifikasi Akhir & Handoff Log

**Files:**
- Create: `.agents/logs/2026-08-25-rbac-pengguna-scope-filter.md`

- [ ] **Step 1: Jalankan seluruh test yang disentuh plan ini sekaligus**

```bash
php artisan test tests/Unit/UserScopeTest.php tests/Unit/TenantScopePlatformBypassTest.php tests/Feature/Admin/UserManagementTest.php tests/Feature/Admin/UserPenggunaScopeChipTest.php
```
Expected: 0 failed. Catat angka pasti (jumlah passed, assertions, durasi).

- [ ] **Step 2: Grep verifikasi tidak ada sisa pemakaian lama yang lupa diupdate**

```bash
grep -n "whereDoesntHave.*siswa" app/Http/Controllers/Admin/UserController.php
```
Expected: KOSONG (baris ini sudah dihapus semua di Task 4).

- [ ] **Step 3: Minta izin user untuk full test suite**

Tanya ke user: "Task 1-9 selesai, test yang disentuh plan ini hijau. Boleh saya jalankan full test suite untuk verifikasi akhir (memastikan perubahan TenantScope tidak berdampak ke bagian lain aplikasi)?" — TUNGGU jawaban eksplisit.

- [ ] **Step 4: Jalankan full suite SOLO**

```bash
php artisan test
```
Catat angka PASTI passed/failed/duration.

- [ ] **Step 5: Tulis handoff log**

Buat `.agents/logs/2026-08-25-rbac-pengguna-scope-filter.md` (Bahasa Indonesia): ringkasan 9 task dengan commit hash, hasil test Step 1 dan Step 4 (angka pasti, jangan dicampur), konfirmasi grep Step 2 kosong, daftar keputusan desain penting (precedence chip, bypass TenantScope hanya model User, siswa sekarang muncul default).

- [ ] **Step 6: Commit**

```bash
git add .agents/logs/2026-08-25-rbac-pengguna-scope-filter.md
git commit -m "docs(rbac): handoff log halaman Pengguna scope filter & visibilitas platform admin"
```
