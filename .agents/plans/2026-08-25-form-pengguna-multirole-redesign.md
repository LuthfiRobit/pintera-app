# Redesain Form Create/Edit Pengguna — Multi-Role Checkbox & Redirect Siswa/Orang Tua Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengganti select Role tunggal di form create/edit Pengguna dengan checkbox multi-role terkelompok per scope, menutup bug destruktif `syncRoles()` yang menghapus baseline `pegawai_lembaga`, mengeluarkan `siswa`/`orang_tua` dari form generik ini (diarahkan ke modul masing-masing), dan membersihkan dead code tampilan role.

**Architecture:** Backend: `UserController::store()`/`update()` menerima `roles[]` (array), auto-hitung baseline carrier via method baru `baselineCarrierRole()` (berbasis `scope_level`, bukan hardcode nama), validasi per-role rank-gating dalam loop. Frontend: checkbox grup (Platform/Yayasan/Lembaga/Staf) reuse taksonomi yang sama dengan chip halaman list. Halaman list: link Edit + toggle-active dikondisikan per role (siswa/orang_tua diarahkan ke modul masing-masing).

**Tech Stack:** Laravel 12, Pest, Blade.

## Global Constraints

- Baseline kode: commit `9322144` di branch `rbac-v2`. Kalau isi file berbeda signifikan dari yang dikutip plan, STOP, laporkan ke user.
- Role scope-carrier (`pegawai_lembaga`, `pegawai_yayasan`) TIDAK PERNAH ditampilkan sebagai checkbox — selalu dihitung otomatis backend berdasarkan `scope_level` role yang dipilih (`lembaga`/`diri_sendiri`) DAN `lembaga_id` user tidak null. Berbasis `scope_level`, BUKAN hardcode daftar nama role (supaya otomatis berlaku untuk role fungsional baru di masa depan).
- `pegawai_yayasan` TIDAK PERNAH di-auto-assign dari form ini (form ini untuk staf ber-`lembaga_id`, bukan alur pool karyawan yayasan — itu tetap domain `AkunKaryawanGenerator`/`KaryawanController`, TIDAK disentuh plan ini).
- `siswa`/`orang_tua` TIDAK PERNAH muncul di pilihan role form ini, dan ditolak eksplisit di validasi backend (defense-in-depth).
- Minimal 1 role wajib dipilih.
- TIDAK mengubah modul "Data Siswa" atau `OrangTuaController` — hanya menambahkan link DARI halaman Pengguna KE modul tersebut.
- TIDAK mengubah `AkunKaryawanGenerator`/`KaryawanController`.
- Test scoped SEBELUM commit. Full suite HANYA di task terakhir, izin eksplisit user dulu.

---

## Task 1: `User::functionalRoles()` — Accessor Role Fungsional

**Files:**
- Modify: `app/Models/User.php`
- Test: `tests/Unit/UserScopeTest.php`

**Interfaces:**
- Produces: `User::functionalRoles(): \Illuminate\Support\Collection` — dipakai Task 5, 6, 7, 8.

- [ ] **Step 1: Baca ulang file existing, konfirmasi struktur class sama dengan baseline**

```bash
grep -n "public function widestScopeLevel" -A 10 app/Models/User.php
```

- [ ] **Step 2: Tulis test yang gagal dulu**

Tambahkan ke `tests/Unit/UserScopeTest.php` (setelah test terakhir):

```php
it('excludes scope-carrier roles from functionalRoles()', function () {
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    Role::firstOrCreate(['name' => 'pegawai_lembaga', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $user = User::factory()->create();
    $user->assignRole(['guru', 'pegawai_lembaga']);

    expect($user->functionalRoles()->pluck('name')->all())->toBe(['guru']);
});

it('returns an empty functionalRoles collection when a user only has scope-carrier roles', function () {
    Role::firstOrCreate(['name' => 'pegawai_yayasan', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);

    $user = User::factory()->create();
    $user->assignRole('pegawai_yayasan');

    expect($user->functionalRoles())->toBeEmpty();
});
```

- [ ] **Step 3: Jalankan test, konfirmasi gagal**

```bash
php artisan test tests/Unit/UserScopeTest.php --filter "functionalRoles"
```
Expected: FAIL dengan error method `functionalRoles()` tidak ditemukan.

- [ ] **Step 4: Tambahkan method**

Di `app/Models/User.php`, tambahkan method baru PERSIS SETELAH method `widestScopeLevel()`:

```php

    /**
     * Role fungsional user (mengecualikan pegawai_lembaga/pegawai_yayasan --
     * role scope-carrier yang bukan identitas pekerjaan, murni penentu
     * widestScopeLevel()). Dipakai untuk tampilan UI (daftar Pengguna, form
     * edit) supaya tidak menampilkan role teknis yang membingungkan.
     */
    public function functionalRoles(): \Illuminate\Support\Collection
    {
        return $this->roles->whereNotIn('name', ['pegawai_lembaga', 'pegawai_yayasan']);
    }
```

- [ ] **Step 5: Jalankan test, konfirmasi lulus**

```bash
php artisan test tests/Unit/UserScopeTest.php
```
Expected: semua PASS (termasuk test lama).

- [ ] **Step 6: Commit**

```bash
git add app/Models/User.php tests/Unit/UserScopeTest.php
git commit -m "feat(rbac): tambah User::functionalRoles() accessor mengecualikan role scope-carrier"
```

---

## Task 2: `UserController` — Helper `assignableRoles()`, `formRoleGroups()`, `groupRolesForForm()`, `baselineCarrierRole()`

**Files:**
- Modify: `app/Http/Controllers/Admin/UserController.php`

**Interfaces:**
- Produces: 4 method privat baru dipakai Task 3 (`create()`/`store()`) dan Task 4 (`edit()`/`update()`).

- [ ] **Step 1: Baca ulang file existing, konfirmasi method `scopeRank()` di akhir file sama dengan baseline**

```bash
grep -n "private function scopeRank" -A 6 app/Http/Controllers/Admin/UserController.php
```

- [ ] **Step 2: Tambah 4 method privat baru PERSIS SEBELUM `private function scopeRank`**

```php
    /**
     * Daftar role yang boleh ditampilkan/dipilih di form Pengguna (create/edit).
     * Mengecualikan siswa/orang_tua (punya modul pembuatan akun tersendiri) dan
     * pegawai_lembaga/pegawai_yayasan (scope-carrier, auto-assign, tidak pernah
     * dipilih manual) -- lihat baselineCarrierRole(). Filter rank tetap berlaku
     * per-role individual karena role dalam satu grup checkbox (lihat
     * formRoleGroups()) bisa punya scope_level berbeda (mis. guru = diri_sendiri).
     */
    private function assignableRoles(int $actingRank): \Illuminate\Support\Collection
    {
        $excluded = ['siswa', 'orang_tua', 'pegawai_lembaga', 'pegawai_yayasan'];

        return Role::all()
            ->reject(fn ($role) => in_array($role->name, $excluded, true))
            ->filter(fn ($role) => $this->scopeRank($role->scope_level) <= $actingRank)
            ->values();
    }

    /**
     * Taksonomi grup checkbox role di form Pengguna -- sama persis dengan
     * taksonomi chip di halaman list (spec 2026-08-25-rbac-pengguna-scope-filter
     * §4), MINUS carrier role dan MINUS siswa/orang_tua (tidak pernah muncul di
     * form ini).
     */
    private function formRoleGroups(): array
    {
        return [
            'Platform' => ['platform_super_admin'],
            'Yayasan' => ['yayasan_super_admin', 'bendahara_yayasan'],
            'Lembaga' => ['kepala_sekolah', 'wakasek_kurikulum', 'wakasek_kesiswaan', 'operator_akademik', 'admin_sdm', 'bendahara_lembaga', 'admin_sarpras', 'admin_administrasi'],
            'Staf' => ['guru', 'wali_kelas', 'guru_bk'],
        ];
    }

    /**
     * Kelompokkan $roles (hasil assignableRoles()) ke label grup formRoleGroups(),
     * membuang grup yang kosong (mis. grup Platform disembunyikan sama sekali
     * untuk actor lembaga-scope karena role platform_super_admin sudah difilter
     * habis duluan oleh scopeRank di assignableRoles()).
     */
    private function groupRolesForForm(\Illuminate\Support\Collection $roles): \Illuminate\Support\Collection
    {
        return collect($this->formRoleGroups())
            ->map(fn ($names) => $roles->whereIn('name', $names)->values())
            ->filter(fn ($group) => $group->isNotEmpty());
    }

    /**
     * Role scope-carrier yang WAJIB otomatis ditambahkan berdampingan dengan role
     * fungsional yang dipilih -- meniru invariant AkunKaryawanGenerator (spec RBAC
     * v2 §5.5, §7). Berbasis scope_level (bukan hardcode nama role) supaya
     * otomatis berlaku untuk role fungsional baru di masa depan. pegawai_yayasan
     * TIDAK PERNAH dikembalikan di sini -- form ini untuk staf ber-lembaga_id,
     * bukan alur pool karyawan yayasan.
     */
    private function baselineCarrierRole(\Illuminate\Support\Collection $selectedRoles, ?int $lembagaId): ?string
    {
        if ($lembagaId === null) {
            return null;
        }

        $needsCarrier = $selectedRoles->contains(fn ($role) => in_array($role->scope_level, ['lembaga', 'diri_sendiri'], true));

        return $needsCarrier ? 'pegawai_lembaga' : null;
    }

```

- [ ] **Step 3: Verifikasi syntax**

```bash
php -l app/Http/Controllers/Admin/UserController.php
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Admin/UserController.php
git commit -m "feat(rbac): tambah helper assignableRoles/formRoleGroups/groupRolesForForm/baselineCarrierRole"
```

Tidak ada test terpisah untuk task ini -- ke-4 method privat ini diuji tidak langsung lewat Task 3/4/5's feature test (method privat, tidak ada akses langsung dari test tanpa reflection, dan reflection test terhadap privat method bukan pola project ini).

---

## Task 3: `UserController::create()`/`store()` — Multi-Role, Baseline Auto-Assign, Tolak Siswa/Orang Tua

**Files:**
- Modify: `app/Http/Controllers/Admin/UserController.php`
- Modify: `tests/Feature/Admin/UserManagementTest.php`

**Interfaces:**
- Consumes: `assignableRoles()`, `groupRolesForForm()`, `baselineCarrierRole()` (Task 2).
- Produces: view `admin.users.create` sekarang menerima `rolesByGroup` (bukan `roles`) -- dipakai Task 6 (Blade).

- [ ] **Step 1: Baca ulang method `create()`/`store()` existing**

```bash
grep -n "public function create\|public function store" -A 40 app/Http/Controllers/Admin/UserController.php
```

- [ ] **Step 2: Ganti method `create()`**

Ganti:
```php
    public function create(Request $request): View
    {
        $this->authorize('users.create');

        $actingRank = $this->scopeRank($request->user()->widestScopeLevel());
        $roles = Role::all()->filter(fn ($role) => $this->scopeRank($role->scope_level) <= $actingRank)->values();

        return view('admin.users.create', [
            'roles' => $roles,
            'lembaga' => $request->user()->widestScopeLevel() === 'yayasan'
                ? Lembaga::withoutGlobalScopes()->get()
                : collect(),
        ]);
    }
```
Menjadi:
```php
    public function create(Request $request): View
    {
        $this->authorize('users.create');

        $actingRank = $this->scopeRank($request->user()->widestScopeLevel());

        return view('admin.users.create', [
            'rolesByGroup' => $this->groupRolesForForm($this->assignableRoles($actingRank)),
            'lembaga' => $request->user()->widestScopeLevel() === 'yayasan'
                ? Lembaga::withoutGlobalScopes()->get()
                : collect(),
        ]);
    }
```

- [ ] **Step 3: Ganti method `store()`**

Ganti:
```php
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('users.create');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'lembaga_id' => ['nullable', 'exists:lembaga,id'],
            'role' => ['required', 'exists:roles,name'],
        ]);

        $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? ($data['lembaga_id'] ?? null)
            : $request->user()->lembaga_id;

        $selectedRole = Role::where('name', $data['role'])->firstOrFail();

        $actingRank = $this->scopeRank($request->user()->widestScopeLevel());
        if ($this->scopeRank($selectedRole->scope_level) > $actingRank) {
            return back()->withErrors(['role' => 'Anda tidak dapat memberikan role dengan scope lebih luas dari scope Anda sendiri.'])->withInput();
        }

        if ($selectedRole->scope_level !== 'yayasan' && $lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Lembaga wajib diisi untuk role ini.'])->withInput();
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'lembaga_id' => $lembagaId,
            'yayasan_id' => $selectedRole->scope_level === 'yayasan' ? $request->user()->yayasan_id : null,
        ]);

        $user->assignRole($data['role']);

        return redirect()->route('admin.users.index')->with('status', 'Akun staff berhasil dibuat.');
    }
```
Menjadi:
```php
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('users.create');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'lembaga_id' => ['nullable', 'exists:lembaga,id'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['exists:roles,name', Rule::notIn(['siswa', 'orang_tua', 'pegawai_lembaga', 'pegawai_yayasan'])],
        ]);

        $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? ($data['lembaga_id'] ?? null)
            : $request->user()->lembaga_id;

        $selectedRoles = Role::whereIn('name', $data['roles'])->get();

        $actingRank = $this->scopeRank($request->user()->widestScopeLevel());
        foreach ($selectedRoles as $selectedRole) {
            if ($this->scopeRank($selectedRole->scope_level) > $actingRank) {
                return back()->withErrors(['roles' => 'Anda tidak dapat memberikan role dengan scope lebih luas dari scope Anda sendiri.'])->withInput();
            }
        }

        $needsLembaga = $selectedRoles->contains(fn ($role) => $role->scope_level !== 'yayasan');
        if ($needsLembaga && $lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Lembaga wajib diisi untuk role ini.'])->withInput();
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'lembaga_id' => $lembagaId,
            'yayasan_id' => $selectedRoles->contains(fn ($role) => $role->scope_level === 'yayasan') ? $request->user()->yayasan_id : null,
        ]);

        $baselineRole = $this->baselineCarrierRole($selectedRoles, $lembagaId);
        $user->assignRole(array_filter([...$data['roles'], $baselineRole]));

        return redirect()->route('admin.users.index')->with('status', 'Akun staff berhasil dibuat.');
    }
```

- [ ] **Step 4: Tambah import `Rule`**

Di bagian `use` paling atas file, tambahkan setelah `use Illuminate\Support\Facades\Hash;`:
```php
use Illuminate\Validation\Rule;
```

- [ ] **Step 5: Verifikasi syntax**

```bash
php -l app/Http/Controllers/Admin/UserController.php
```

- [ ] **Step 6: Update SEMUA test existing yang memakai param `role` singular di `store()`**

Di `tests/Feature/Admin/UserManagementTest.php`, ganti SETIAP kemunculan `'role' => 'xxx'` di dalam `->post(route('admin.users.store'), [...])` menjadi `'roles' => ['xxx']`. Daftar test yang terdampak (6 test):

1. `'lets a yayasan-scoped manager create a staff account with a role and a lembaga'` — baris `'role' => 'kepala_sekolah',` → `'roles' => ['kepala_sekolah'],`
2. `'forces lembaga_id to the acting lembaga-scoped manager\'s own lembaga, ignoring submitted input'` — `'role' => 'bendahara_lembaga',` → `'roles' => ['bendahara_lembaga'],`
3. `'requires a lembaga when creating a user with a lembaga-scoped role'` — `'role' => 'kepala_sekolah',` → `'roles' => ['kepala_sekolah'],`
4. `'sets yayasan_id on a newly created yayasan-scoped staff account, inherited from the acting manager'` — `'role' => 'yayasan_super_admin',` → `'roles' => ['yayasan_super_admin'],`
5. `'leaves yayasan_id null when creating a lembaga-scoped staff account'` — `'role' => 'kepala_sekolah',` → `'roles' => ['kepala_sekolah'],`
6. `'lets a platform_super_admin assign a yayasan-scoped role to a new user'` — `'role' => 'yayasan_super_admin',` → `'roles' => ['yayasan_super_admin'],`

Untuk test `'refuses to let a lembaga-scoped manager assign a yayasan-scoped role to a new user'`, ganti:
```php
    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Sneaky User',
        'email' => 'sneaky@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'yayasan_super_admin',
    ])->assertSessionHasErrors('role');
```
Menjadi:
```php
    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Sneaky User',
        'email' => 'sneaky@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'roles' => ['yayasan_super_admin'],
    ])->assertSessionHasErrors('roles');
```

- [ ] **Step 7: Jalankan test, konfirmasi lulus**

```bash
php artisan test tests/Feature/Admin/UserManagementTest.php
```
Expected: sebagian besar PASS. Test `update()`-related (`'lets a user manager update...'`, `'sets yayasan_id when updating...'`, `'404s on edit, update, and toggle-active...'`) masih memakai `'role'` singular untuk PUT request -- INI DIHARAPKAN GAGAL sampai Task 4 selesai (yang mengubah `update()`), JANGAN diperbaiki di task ini.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/UserController.php tests/Feature/Admin/UserManagementTest.php
git commit -m "feat(rbac): create/store Pengguna dukung multi-role checkbox + baseline carrier otomatis"
```

---

## Task 4: `UserController::edit()`/`update()`/`toggleActive()` — Multi-Role, Guard Diperluas ke Orang Tua

**Files:**
- Modify: `app/Http/Controllers/Admin/UserController.php`
- Modify: `tests/Feature/Admin/UserManagementTest.php`

**Interfaces:**
- Consumes: `assignableRoles()`, `groupRolesForForm()`, `baselineCarrierRole()` (Task 2).
- Produces: view `admin.users.edit` sekarang menerima `rolesByGroup` (bukan `roles`).

- [ ] **Step 1: Baca ulang method `edit()`/`update()`/`toggleActive()` existing**

```bash
grep -n "public function edit\|public function update\|public function toggleActive" -A 35 app/Http/Controllers/Admin/UserController.php
```

- [ ] **Step 2: Ganti method `edit()`**

Ganti:
```php
    public function edit(Request $request, User $user): View
    {
        $this->authorize('users.edit');
        abort_if($user->hasRole('siswa'), 404);

        $actingRank = $this->scopeRank($request->user()->widestScopeLevel());
        $roles = Role::all()->filter(fn ($role) => $this->scopeRank($role->scope_level) <= $actingRank)->values();

        return view('admin.users.edit', [
            'targetUser' => $user,
            'roles' => $roles,
        ]);
    }
```
Menjadi:
```php
    public function edit(Request $request, User $user): View
    {
        $this->authorize('users.edit');
        abort_if($user->hasRole(['siswa', 'orang_tua']), 404);

        $actingRank = $this->scopeRank($request->user()->widestScopeLevel());

        return view('admin.users.edit', [
            'targetUser' => $user,
            'rolesByGroup' => $this->groupRolesForForm($this->assignableRoles($actingRank)),
        ]);
    }
```

- [ ] **Step 3: Ganti method `update()`**

Ganti:
```php
    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('users.edit');
        abort_if($user->hasRole('siswa'), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$user->id],
            'role' => ['required', 'exists:roles,name'],
        ]);

        $selectedRole = Role::where('name', $data['role'])->firstOrFail();
        $actingRank = $this->scopeRank($request->user()->widestScopeLevel());
        if ($this->scopeRank($selectedRole->scope_level) > $actingRank) {
            return back()->withErrors(['role' => 'Anda tidak dapat memberikan role dengan scope lebih luas dari scope Anda sendiri.'])->withInput();
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'yayasan_id' => $selectedRole->scope_level === 'yayasan' ? $request->user()->yayasan_id : null,
        ]);
        $user->syncRoles([$data['role']]);

        return redirect()->route('admin.users.index')->with('status', 'Akun staff berhasil diperbarui.');
    }
```
Menjadi:
```php
    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('users.edit');
        abort_if($user->hasRole(['siswa', 'orang_tua']), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$user->id],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['exists:roles,name', Rule::notIn(['siswa', 'orang_tua', 'pegawai_lembaga', 'pegawai_yayasan'])],
        ]);

        $selectedRoles = Role::whereIn('name', $data['roles'])->get();
        $actingRank = $this->scopeRank($request->user()->widestScopeLevel());
        foreach ($selectedRoles as $selectedRole) {
            if ($this->scopeRank($selectedRole->scope_level) > $actingRank) {
                return back()->withErrors(['roles' => 'Anda tidak dapat memberikan role dengan scope lebih luas dari scope Anda sendiri.'])->withInput();
            }
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'yayasan_id' => $selectedRoles->contains(fn ($role) => $role->scope_level === 'yayasan') ? $request->user()->yayasan_id : null,
        ]);

        $baselineRole = $this->baselineCarrierRole($selectedRoles, $user->lembaga_id);
        $user->syncRoles(array_filter([...$data['roles'], $baselineRole]));

        return redirect()->route('admin.users.index')->with('status', 'Akun staff berhasil diperbarui.');
    }
```

- [ ] **Step 4: Ganti method `toggleActive()`**

Ganti:
```php
    public function toggleActive(User $user): RedirectResponse
    {
        $this->authorize('users.toggle-active');
        abort_if($user->hasRole('siswa'), 404);

        $user->update(['is_active' => ! $user->is_active]);

        return redirect()->route('admin.users.index')->with('status', 'Status akun berhasil diperbarui.');
    }
```
Menjadi:
```php
    public function toggleActive(User $user): RedirectResponse
    {
        $this->authorize('users.toggle-active');
        abort_if($user->hasRole(['siswa', 'orang_tua']), 404);

        $user->update(['is_active' => ! $user->is_active]);

        return redirect()->route('admin.users.index')->with('status', 'Status akun berhasil diperbarui.');
    }
```

- [ ] **Step 5: Verifikasi syntax**

```bash
php -l app/Http/Controllers/Admin/UserController.php
```

- [ ] **Step 6: Update test existing yang memakai param `role` singular di `update()`**

Di `tests/Feature/Admin/UserManagementTest.php`:

1. `'lets a user manager update an existing staff account\'s name and email'` — ganti `'role' => 'kepala_sekolah',` (di dalam `->put(...)`) → `'roles' => ['kepala_sekolah'],`
2. `'sets yayasan_id when updating a staff account to a yayasan-scoped role'` — ganti `'role' => 'yayasan_super_admin',` → `'roles' => ['yayasan_super_admin'],`
3. `'404s on edit, update, and toggle-active for a siswa-role user, since siswa accounts are managed only from the Siswa module'` — ganti `'role' => 'siswa',` → `'roles' => ['siswa'],` (request ini tetap 404 duluan lewat `abort_if`, sebelum validasi diperiksa, jadi hasil tetap sama).

- [ ] **Step 7: Jalankan test, konfirmasi lulus**

```bash
php artisan test tests/Feature/Admin/UserManagementTest.php
```
Expected: semua PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/UserController.php tests/Feature/Admin/UserManagementTest.php
git commit -m "fix(rbac): edit/update Pengguna dukung multi-role tanpa menghapus baseline carrier, guard orang_tua"
```

---

## Task 5: Test Regresi Bug Destruktif & Baseline Carrier

**Files:**
- Modify: `tests/Feature/Admin/UserManagementTest.php`

**Interfaces:**
- Consumes: `UserController::store()`/`update()` (Task 3, 4), `User::functionalRoles()` (Task 1).

- [ ] **Step 1: Tambahkan 5 test baru** (setelah test terakhir di file)

```php
it('does not strip the pegawai_lembaga baseline role when updating a guru account that only ever had "guru" assigned directly', function () {
    $manager = actingAsUserManager();
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    Role::firstOrCreate(['name' => 'pegawai_lembaga', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);
    $guru = User::factory()->create(['name' => 'Guru Lama', 'email' => 'gurulama@example.test', 'lembaga_id' => $lembaga->id]);
    $guru->assignRole('guru');

    expect($guru->hasRole('pegawai_lembaga'))->toBeFalse();

    $this->actingAs($manager)->put(route('admin.users.update', $guru), [
        'name' => 'Guru Baru',
        'email' => 'gurulama@example.test',
        'roles' => ['guru'],
    ])->assertRedirect(route('admin.users.index'));

    $updated = $guru->fresh();
    expect($updated->hasRole('guru'))->toBeTrue();
    expect($updated->hasRole('pegawai_lembaga'))->toBeTrue();
});

it('assigns multiple functional roles at once plus a single shared pegawai_lembaga baseline', function () {
    $manager = actingAsUserManager();
    Role::firstOrCreate(['name' => 'wakasek_kurikulum', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    Role::firstOrCreate(['name' => 'pegawai_lembaga', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Wakasek Merangkap Guru',
        'email' => 'wakasekguru@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'lembaga_id' => $lembaga->id,
        'roles' => ['wakasek_kurikulum', 'guru'],
    ])->assertRedirect(route('admin.users.index'));

    $created = User::withoutGlobalScopes()->where('email', 'wakasekguru@example.test')->first();
    expect($created->hasRole('wakasek_kurikulum'))->toBeTrue();
    expect($created->hasRole('guru'))->toBeTrue();
    expect($created->hasRole('pegawai_lembaga'))->toBeTrue();
    expect($created->roles()->count())->toBe(3);
});

it('does not add any carrier baseline role for a purely yayasan-scoped role selection', function () {
    $manager = actingAsUserManager();
    Role::firstOrCreate(['name' => 'bendahara_yayasan', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Bendahara Yayasan Baru',
        'email' => 'bendaharayayasan@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'roles' => ['bendahara_yayasan'],
    ])->assertRedirect(route('admin.users.index'));

    $created = User::withoutGlobalScopes()->where('email', 'bendaharayayasan@example.test')->first();
    expect($created->hasRole('pegawai_lembaga'))->toBeFalse();
    expect($created->hasRole('pegawai_yayasan'))->toBeFalse();
});

it('rejects siswa, orang_tua, and carrier role names submitted directly via the roles array', function () {
    $manager = actingAsUserManager();
    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Percobaan Siswa',
        'email' => 'percobaansiswa@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'roles' => ['siswa'],
    ])->assertSessionHasErrors('roles.0');

    expect(User::withoutGlobalScopes()->where('email', 'percobaansiswa@example.test')->exists())->toBeFalse();
});

it('rejects a multi-role selection when any single role exceeds the acting manager\'s scope rank', function () {
    foreach (['users.view', 'users.create', 'users.edit', 'users.toggle-active'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $lembagaRole = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $lembagaRole->givePermissionTo(['users.view', 'users.create', 'users.edit', 'users.toggle-active']);
    Role::firstOrCreate(['name' => 'bendahara_lembaga', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($lembagaRole);

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Percobaan Campuran',
        'email' => 'percobaancampuran@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'lembaga_id' => $lembaga->id,
        'roles' => ['bendahara_lembaga', 'yayasan_super_admin'],
    ])->assertSessionHasErrors('roles');

    expect(User::withoutGlobalScopes()->where('email', 'percobaancampuran@example.test')->exists())->toBeFalse();
});
```

- [ ] **Step 2: Jalankan test, konfirmasi lulus**

```bash
php artisan test tests/Feature/Admin/UserManagementTest.php
```
Expected: semua PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Admin/UserManagementTest.php
git commit -m "test(rbac): regresi bug destruktif syncRoles, multi-role assignment, baseline carrier, validasi role terlarang"
```

---

## Task 6: Guard `orang_tua` — Test Baru Meniru Pola Siswa

**Files:**
- Modify: `tests/Feature/Admin/UserManagementTest.php`

- [ ] **Step 1: Baca ulang test siswa yang sudah ada sebagai referensi pola**

```bash
grep -n "404s on edit, update, and toggle-active for a siswa-role user" -A 20 tests/Feature/Admin/UserManagementTest.php
```

- [ ] **Step 2: Tambahkan test baru untuk orang_tua** (setelah test siswa yang sudah ada)

```php
it('404s on edit, update, and toggle-active for an orang_tua-role user, since orang tua accounts are managed only from the Orang Tua module', function () {
    $manager = actingAsUserManager();

    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $orangTuaUser = User::factory()->create(['username' => 'ortu.guarded', 'email' => null]);
    $orangTuaUser->assignRole('orang_tua');

    $this->actingAs($manager)->get(route('admin.users.edit', $orangTuaUser))->assertNotFound();

    $this->actingAs($manager)->put(route('admin.users.update', $orangTuaUser), [
        'name' => 'Hacked Name',
        'email' => 'hacked2@example.test',
        'roles' => ['orang_tua'],
    ])->assertNotFound();

    $this->actingAs($manager)->patch(route('admin.users.toggle-active', $orangTuaUser))->assertNotFound();

    $fresh = $orangTuaUser->fresh();
    expect($fresh->name)->not->toBe('Hacked Name');
    expect($fresh->hasRole('orang_tua'))->toBeTrue();
});
```

- [ ] **Step 3: Jalankan test, konfirmasi lulus**

```bash
php artisan test tests/Feature/Admin/UserManagementTest.php
```
Expected: semua PASS.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Admin/UserManagementTest.php
git commit -m "test(rbac): guard edit/update/toggle-active untuk akun orang_tua meniru pola siswa"
```

---

## Task 7: Blade `_form.blade.php` — Checkbox Grup Per Scope

**Files:**
- Modify: `resources/views/admin/users/_form.blade.php`

**Interfaces:**
- Consumes: `$rolesByGroup` (Task 3, 4 -- Collection keyed by label grup, value Collection of Role model), `$targetUser` (existing, null untuk create).

- [ ] **Step 1: Baca ulang file existing**

```bash
cat resources/views/admin/users/_form.blade.php
```

- [ ] **Step 2: Ganti seluruh blok select Role**

Ganti:
```blade
<div>
    <x-input-label for="role" value="Role / Peran Akses" />
    <x-select id="role" name="role" class="mt-1.5" required>
        <option value="" disabled {{ !$targetUser ? 'selected' : '' }}>Pilih peran akses...</option>
        @foreach ($roles as $roleOption)
            <option value="{{ $roleOption->name }}" @selected(old('role', $targetUser?->roles->first()->name ?? null) === $roleOption->name)>
                {{ $roleOption->name }}
            </option>
        @endforeach
    </x-select>
    <x-input-error :messages="$errors->get('role')" class="mt-1.5" />
</div>
```
Menjadi:
```blade
<div>
    <x-input-label value="Role / Peran Akses" />
    <p class="mt-0.5 text-xs text-gray-500">Pilih satu atau lebih peran fungsional. Role teknis (scope pegawai) ditambahkan otomatis oleh sistem.</p>
    @php
        $checkedRoles = old('roles', $targetUser?->functionalRoles()->pluck('name')->toArray() ?? []);
    @endphp
    <div class="mt-2 space-y-4">
        @foreach ($rolesByGroup as $groupLabel => $groupRoles)
            <div>
                <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ $groupLabel }}</p>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach ($groupRoles as $roleOption)
                        <label class="flex items-center gap-2.5 rounded-lg border border-gray-200 px-3.5 py-2.5 text-sm text-gray-700 transition hover:bg-gray-50">
                            <input
                                type="checkbox"
                                name="roles[]"
                                value="{{ $roleOption->name }}"
                                @checked(in_array($roleOption->name, $checkedRoles, true))
                                class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                            >
                            <span>{{ $roleOption->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
    <x-input-error :messages="$errors->get('roles')" class="mt-1.5" />
    <x-input-error :messages="$errors->get('roles.*')" class="mt-1.5" />
</div>
```

- [ ] **Step 3: Verifikasi tidak ada referensi tersisa ke variabel `$roles`/`role` lama**

```bash
grep -n "\$roles\b\|name=\"role\"" resources/views/admin/users/_form.blade.php
```
Expected: KOSONG (semua sudah jadi `$rolesByGroup`/`roles[]`).

- [ ] **Step 4: Commit**

```bash
git add resources/views/admin/users/_form.blade.php
git commit -m "feat(rbac): ganti select Role tunggal jadi checkbox multi-role terkelompok per scope"
```

---

## Task 8: Blade `edit.blade.php` & `tabs/profil.blade.php` — Perbaiki Tampilan Role & Hapus Dead Code

**Files:**
- Modify: `resources/views/admin/users/edit.blade.php`
- Modify: `resources/views/admin/users/tabs/profil.blade.php`

**Interfaces:**
- Consumes: `User::functionalRoles()` (Task 1).

- [ ] **Step 1: Baca ulang kedua file existing**

```bash
grep -n "roles->first" resources/views/admin/users/edit.blade.php resources/views/admin/users/tabs/profil.blade.php
```
Expected 3 kemunculan: 1 di `edit.blade.php` (hero card), 2 di `tabs/profil.blade.php` (view-mode dd + dead code if-condition).

- [ ] **Step 2: Perbaiki `edit.blade.php` hero card**

Ganti:
```blade
                        <span class="flex items-center gap-1.5 font-mono">
                            <x-icon name="shield_person" class="h-4 w-4 text-gray-400" />
                            Role: <strong class="text-gray-900">{{ $targetUser->roles->first()?->name ?: 'Belum diatur' }}</strong>
                        </span>
```
Menjadi:
```blade
                        <span class="flex items-center gap-1.5 font-mono">
                            <x-icon name="shield_person" class="h-4 w-4 text-gray-400" />
                            Role: <strong class="text-gray-900">{{ $targetUser->functionalRoles()->pluck('name')->implode(', ') ?: 'Belum diatur' }}</strong>
                        </span>
```

- [ ] **Step 3: Perbaiki `tabs/profil.blade.php` — hapus dead code, perbaiki tampilan role**

Ganti:
```blade
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Role / Peran Utama</dt>
                        <dd class="font-mono text-gray-900">{{ $targetUser->roles->first()?->name ?: 'Tidak Ada Akses' }}</dd>
                    </div>
                    @if ($targetUser->roles->first()?->name === 'Lembaga / Sekolah')
                        <div class="flex justify-between py-2.5">
                            <dt class="text-gray-500">Lembaga Tertaut</dt>
                            <dd class="text-gray-900">{{ $targetUser->karyawan?->lembaga?->nama ?: 'Bukan Karyawan Aktif' }}</dd>
                        </div>
                    @endif
```
Menjadi:
```blade
                    <div class="flex justify-between py-2.5">
                        <dt class="text-gray-500">Role / Peran Akses</dt>
                        <dd class="font-mono text-gray-900">{{ $targetUser->functionalRoles()->pluck('name')->implode(', ') ?: 'Tidak Ada Akses' }}</dd>
                    </div>
                    @if ($targetUser->lembaga_id)
                        <div class="flex justify-between py-2.5">
                            <dt class="text-gray-500">Lembaga Tertaut</dt>
                            <dd class="text-gray-900">{{ $targetUser->lembaga?->nama ?: '—' }}</dd>
                        </div>
                    @endif
```

- [ ] **Step 4: Verifikasi tidak ada sisa `roles->first()` atau string `'Lembaga / Sekolah'`**

```bash
grep -rn "roles->first\|Lembaga / Sekolah" resources/views/admin/users/
```
Expected: KOSONG.

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/users/edit.blade.php resources/views/admin/users/tabs/profil.blade.php
git commit -m "fix(rbac): perbaiki tampilan role pakai functionalRoles(), hapus dead code Lembaga Tertaut"
```

---

## Task 9: Blade `_daftar.blade.php` — Redirect Siswa/Orang Tua, Sembunyikan Toggle-Active

**Files:**
- Modify: `resources/views/admin/users/_daftar.blade.php`

**Interfaces:**
- Consumes: `User::functionalRoles()` (Task 1), relasi `User::siswa()`/`User::orangTua()` (existing).

- [ ] **Step 1: Baca ulang file existing secara utuh**

```bash
cat resources/views/admin/users/_daftar.blade.php
```

- [ ] **Step 2: Ganti kolom Role**

Ganti:
```blade
                    <td class="px-5 py-3 align-top text-gray-500">{{ $user->roles->pluck('name')->implode(', ') }}</td>
```
Menjadi:
```blade
                    <td class="px-5 py-3 align-top text-gray-500">{{ $user->functionalRoles()->pluck('name')->implode(', ') ?: '—' }}</td>
```

- [ ] **Step 3: Ganti seluruh isi `<x-table-actions>` per baris**

Ganti:
```blade
                    <td class="sticky left-0 z-10 bg-white px-5 py-3 align-top">
                            <x-table-actions>
                                <a href="{{ route('admin.users.edit', $user) }}" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                    <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                    Edit Akun
                                </a>
                                <form
                                    method="POST"
                                    action="{{ route('admin.users.toggle-active', $user) }}"
                                    x-data
                                    @submit.prevent="confirmDialog('Ubah Status Akun?', @js('Ubah status akun menjadi ' . ($user->is_active ? 'Nonaktif' : 'Aktif') . '?'), { confirmLabel: 'Ya, Ubah', isDanger: {{ $user->is_active ? 'true' : 'false' }} }).then(confirmed => { if (confirmed) $el.submit() })"
                                >
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 {{ $user->is_active ? 'text-red-600 hover:bg-red-50 focus:bg-red-50' : 'text-green-600 hover:bg-green-50 focus:bg-green-50' }} transition duration-150 ease-in-out focus:outline-none">
                                        <x-icon name="{{ $user->is_active ? 'block' : 'check_circle' }}" class="h-4 w-4 {{ $user->is_active ? 'text-red-500' : 'text-green-500' }}" />
                                        {{ $user->is_active ? 'Nonaktifkan Akun' : 'Aktifkan Akun' }}
                                    </button>
                                </form>
                            </x-table-actions>
                    </td>
```
Menjadi:
```blade
                    <td class="sticky left-0 z-10 bg-white px-5 py-3 align-top">
                            <x-table-actions>
                                @if ($user->hasRole('siswa'))
                                    @if ($user->siswa)
                                        <a href="{{ route('admin.siswa.edit', $user->siswa) }}" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                            <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                            Edit di Data Siswa
                                        </a>
                                    @endif
                                @elseif ($user->hasRole('orang_tua'))
                                    @if ($user->orangTua)
                                        <a href="{{ route('admin.orang-tua.edit', $user->orangTua) }}" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                            <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                            Edit di Modul Orang Tua
                                        </a>
                                    @endif
                                @else
                                    <a href="{{ route('admin.users.edit', $user) }}" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                        <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                        Edit Akun
                                    </a>
                                    <form
                                        method="POST"
                                        action="{{ route('admin.users.toggle-active', $user) }}"
                                        x-data
                                        @submit.prevent="confirmDialog('Ubah Status Akun?', @js('Ubah status akun menjadi ' . ($user->is_active ? 'Nonaktif' : 'Aktif') . '?'), { confirmLabel: 'Ya, Ubah', isDanger: {{ $user->is_active ? 'true' : 'false' }} }).then(confirmed => { if (confirmed) $el.submit() })"
                                    >
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 {{ $user->is_active ? 'text-red-600 hover:bg-red-50 focus:bg-red-50' : 'text-green-600 hover:bg-green-50 focus:bg-green-50' }} transition duration-150 ease-in-out focus:outline-none">
                                            <x-icon name="{{ $user->is_active ? 'block' : 'check_circle' }}" class="h-4 w-4 {{ $user->is_active ? 'text-red-500' : 'text-green-500' }}" />
                                            {{ $user->is_active ? 'Nonaktifkan Akun' : 'Aktifkan Akun' }}
                                        </button>
                                    </form>
                                @endif
                            </x-table-actions>
                    </td>
```

- [ ] **Step 4: Verifikasi syntax Blade**

```bash
php artisan view:clear
```

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/users/_daftar.blade.php
git commit -m "feat(rbac): redirect Edit siswa/orang_tua ke modul masing-masing, sembunyikan toggle-active"
```

---

## Task 10: Test Redirect Link & Dead Code Fix di Halaman List

**Files:**
- Create: `tests/Feature/Admin/UserPenggunaFormRedesignTest.php`

**Interfaces:**
- Consumes: `UserController::index()` (existing, tidak diubah plan ini), routing `admin.siswa.edit`/`admin.orang-tua.edit` (existing).

- [ ] **Step 1: Baca ulang factory `Siswa`/`OrangTua` untuk field wajib**

```bash
cat database/factories/SiswaFactory.php
cat database/factories/OrangTuaFactory.php
```

- [ ] **Step 2: Tulis file test baru**

```php
<?php

use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsUserManagerForFormRedesignTest(): User
{
    foreach (['users.view', 'users.create', 'users.edit', 'users.toggle-active'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(
        ['name' => 'yayasan_super_admin', 'guard_name' => 'web'],
        ['scope_level' => 'yayasan', 'is_protected' => true]
    );
    $role->givePermissionTo(['users.view', 'users.create', 'users.edit', 'users.toggle-active']);

    $user = User::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $user->assignRole($role);

    return $user;
}

it('links a siswa row to the Data Siswa edit route instead of admin.users.edit', function () {
    $manager = actingAsUserManagerForFormRedesignTest();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);

    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $siswaUser = User::factory()->create(['username' => 'siswa.linktest', 'email' => null, 'lembaga_id' => $lembaga->id]);
    $siswaUser->assignRole('siswa');
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $siswaUser->id]);

    $response = $this->actingAs($manager)->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertSee(route('admin.siswa.edit', $siswa), false);
    $response->assertDontSee(route('admin.users.edit', $siswaUser), false);
});

it('links an orang_tua row to the Orang Tua module edit route instead of admin.users.edit', function () {
    $manager = actingAsUserManagerForFormRedesignTest();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);

    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTuaUser = User::factory()->create(['lembaga_id' => null, 'name' => 'Ortu Link Test']);
    $orangTuaUser->assignRole('orang_tua');
    $orangTua = OrangTua::factory()->create(['user_id' => $orangTuaUser->id]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $response = $this->actingAs($manager)->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertSee(route('admin.orang-tua.edit', $orangTua), false);
    $response->assertDontSee(route('admin.users.edit', $orangTuaUser), false);
});

it('does not show a toggle-active action for siswa or orang_tua rows', function () {
    $manager = actingAsUserManagerForFormRedesignTest();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);

    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $siswaUser = User::factory()->create(['username' => 'siswa.notoggle', 'email' => null, 'lembaga_id' => $lembaga->id]);
    $siswaUser->assignRole('siswa');

    $response = $this->actingAs($manager)->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertDontSee(route('admin.users.toggle-active', $siswaUser), false);
});

it('renders Lembaga Tertaut on the profile tab based on lembaga_id, not the old dead-code role string comparison', function () {
    $manager = actingAsUserManagerForFormRedesignTest();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id, 'nama' => 'SD Uji Coba']);

    Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $kepsek = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $kepsek->assignRole('kepala_sekolah');

    $response = $this->actingAs($manager)->get(route('admin.users.edit', $kepsek));

    $response->assertOk();
    $response->assertSee('Lembaga Tertaut');
    $response->assertSee('SD Uji Coba');
});
```

Catatan: `Siswa::user_id` sudah dikonfirmasi ada di `$fillable` model (`app/Models/Siswa.php`) dan relasi `Siswa::user()` (`BelongsTo`) sudah ada -- kode di atas TIDAK perlu tebakan tambahan.

- [ ] **Step 3: Jalankan test, konfirmasi lulus**

```bash
php artisan test tests/Feature/Admin/UserPenggunaFormRedesignTest.php
```
Expected: semua PASS. Kalau ADA yang gagal karena asumsi struktur relasi/factory salah, STOP, verifikasi struktur aktual dulu (jangan paksakan assertion yang salah demi lolos).

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Admin/UserPenggunaFormRedesignTest.php
git commit -m "test(rbac): redirect link siswa/orang_tua di list Pengguna, dead code Lembaga Tertaut"
```

---

## Task 11: Verifikasi Akhir & Handoff Log

**Files:**
- Create: `.agents/logs/2026-08-25-form-pengguna-multirole-redesign.md`

- [ ] **Step 1: Grep verifikasi tidak ada sisa referensi param lama**

```bash
grep -rn "name=\"role\"\|'role' =>" app/Http/Controllers/Admin/UserController.php resources/views/admin/users/
```
Expected: KOSONG (semua sudah jadi `roles`/`roles[]`).

```bash
grep -rn "roles->first\|Lembaga / Sekolah" resources/views/admin/users/
```
Expected: KOSONG.

- [ ] **Step 2: Jalankan seluruh test yang disentuh plan ini sekaligus**

```bash
php artisan test tests/Unit/UserScopeTest.php tests/Feature/Admin/UserManagementTest.php tests/Feature/Admin/UserPenggunaScopeChipTest.php tests/Feature/Admin/UserPenggunaFormRedesignTest.php tests/Feature/Admin/OrangTuaCrudTest.php
```
Expected: 0 failed. Catat angka pasti.

- [ ] **Step 3: Minta izin user untuk full test suite**

Tanya ke user: "Task 1-10 selesai, test yang disentuh plan ini hijau, grep verifikasi kosong. Boleh saya jalankan full test suite untuk verifikasi akhir?" — TUNGGU jawaban eksplisit.

- [ ] **Step 4: Jalankan full suite SOLO**

```bash
php artisan test
```
Catat angka PASTI passed/failed/duration.

- [ ] **Step 5: Tulis handoff log**

Buat `.agents/logs/2026-08-25-form-pengguna-multirole-redesign.md` (Bahasa Indonesia): ringkasan Task 1-10 dengan commit hash, hasil grep Step 1 (kosong), hasil test Step 2 dan Step 4 (angka pasti, jangan dicampur), daftar keputusan penting (baseline carrier berbasis scope_level bukan hardcode nama, redirect siswa/orang_tua, checkbox multi-role).

- [ ] **Step 6: Commit**

```bash
git add .agents/logs/2026-08-25-form-pengguna-multirole-redesign.md
git commit -m "docs(rbac): handoff log redesain form Pengguna multi-role & redirect siswa/orang tua"
```
