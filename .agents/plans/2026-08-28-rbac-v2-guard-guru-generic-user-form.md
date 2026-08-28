# RBAC v2 — Guard Role `guru` di Form Pengguna Generik Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cegah role `guru` di-assign lewat `Admin → Pengguna` (`UserController`) — satu-satunya jalur yang bisa menghasilkan `User(role=guru)` tanpa profil `Guru` ("profil belum tertaut") — tanpa merusak invariant baseline-carrier existing (`pegawai_lembaga` mengikuti guru) dan tanpa membatasi role fungsional lain (`guru_bk`, `wali_kelas`, dst) yang terbukti tidak butuh profil apa pun.

**Architecture:** Tiga perubahan terisolasi di satu file (`UserController.php`): (1) keluarkan `guru` dari daftar role yang tampil di form create/edit, (2) tolak eksplisit kalau `guru` tetap terkirim ke `store()`/`update()` dengan pesan yang menjelaskan jalur benar (`Admin → Guru`), (3) di `update()`, paksa role `guru` (kalau user sudah punya) tetap disertakan ke proses sync — supaya form ini tidak bisa diam-diam mencabut identitas guru existing saat admin cuma bermaksud menambah role lain.

**Tech Stack:** Laravel 12, PHP 8.3, Spatie Laravel Permission, Pest v4, MySQL.

## Global Constraints

- Radius perubahan HANYA `app/Http/Controllers/Admin/UserController.php`. TIDAK ada perubahan di `GuruController`, `AkunKaryawanGenerator`, `RoleSeeder`, model `Guru`/`Karyawan`/`User`, schema, atau data existing.
- `guru_bk` dan `wali_kelas` TIDAK BOLEH ikut diblokir — keduanya tetap harus bisa dipilih bebas di form Pengguna generik (acceptance criterion eksplisit, bukan detail insidental).
- 8 role administratif lain (`kepala_sekolah`, `wakasek_kurikulum`, `wakasek_kesiswaan`, `operator_akademik`, `admin_sdm`, `bendahara_lembaga`, `admin_sarpras`, `admin_administrasi`) TIDAK disentuh sama sekali.
- `Rule::notIn(['siswa', 'orang_tua', 'pegawai_lembaga', 'pegawai_yayasan'])` yang sudah ada di `store()`/`update()` TIDAK diubah — guard untuk `guru` adalah pengecekan terpisah, bukan ditambahkan ke array itu.
- `User::functionalRoles()` (`app/Models/User.php:115-118`) TIDAK diubah.
- Pesan error untuk penolakan `guru` harus PERSIS: `'Role Guru harus dibuat melalui Admin → Guru agar profil Guru dibuat dan tertaut dengan benar.'`
- Guard penolakan di `update()` memakai `$data['roles']` (array ASLI hasil validasi), BUKAN `$rolesToPersist` (array setelah preservasi paksa) — kalau salah pakai, update untuk guru existing akan SELALU ditolak.
- Full spec: `.agents/specs/2026-08-28-rbac-v2-guard-guru-generic-user-form.md`.

---

### Task 1: Keluarkan `guru` dari daftar role yang tampil di form (`assignableRoles()`)

**Files:**
- Modify: `app/Http/Controllers/Admin/UserController.php:297-305`
- Test: `tests/Feature/Admin/UserManagementTest.php`

**Interfaces:**
- Consumes: tidak ada (task pertama).
- Produces: `assignableRoles()` yang tidak lagi mengandung `guru` — dipakai oleh `create()`/`edit()` lewat `groupRolesForForm($this->assignableRoles($actingRank))`. Task 2 dan 3 bergantung pada perubahan `store()`/`update()` yang terpisah, tidak bergantung langsung ke method ini, tapi harus konsisten (guru tidak boleh dipilih di UI ATAU lolos validasi backend).

- [ ] **Step 1: Baca ulang baris 297-305 untuk konfirmasi baseline sebelum edit**

Jalankan:
```bash
sed -n '289,305p' app/Http/Controllers/Admin/UserController.php
```
Pastikan isinya PERSIS:
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
```
Kalau berbeda, STOP dan laporkan ke user — jangan lanjut edit di atas asumsi yang salah.

- [ ] **Step 2: Tulis failing test — `guru` tidak muncul di daftar role form create**

Tambahkan di akhir `tests/Feature/Admin/UserManagementTest.php`:
```php
it('does not show guru as a selectable role option on the create form', function () {
    $manager = actingAsUserManager();
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    Role::firstOrCreate(['name' => 'guru_bk', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    Role::firstOrCreate(['name' => 'wali_kelas', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $response = $this->actingAs($manager)->get(route('admin.users.create'));

    $response->assertOk();
    $rolesByGroup = $response->viewData('rolesByGroup');
    $allRoleNames = $rolesByGroup->flatten()->pluck('name')->values()->all();

    expect($allRoleNames)->not->toContain('guru');
    expect($allRoleNames)->toContain('guru_bk');
    expect($allRoleNames)->toContain('wali_kelas');
});
```

- [ ] **Step 3: Jalankan test, pastikan GAGAL (bukti `guru` masih muncul di baseline lama)**

Run: `php artisan test --filter="does not show guru as a selectable role option" --compact`
Expected: FAIL — `expect($allRoleNames)->not->toContain('guru')` gagal karena `guru` masih ada di `$excluded` yang belum diubah.

- [ ] **Step 4: Edit `assignableRoles()` — tambahkan `guru` ke `$excluded`**

Ubah baris 299 dari:
```php
        $excluded = ['siswa', 'orang_tua', 'pegawai_lembaga', 'pegawai_yayasan'];
```
menjadi:
```php
        $excluded = ['siswa', 'orang_tua', 'pegawai_lembaga', 'pegawai_yayasan', 'guru'];
```
Method docblock di atasnya (baris 289-296) TIDAK perlu diubah — tetap akurat secara umum, hanya menambah satu item ke daftar exclusion yang deskripsinya sudah cukup general.

- [ ] **Step 5: Jalankan `php -l` untuk cek syntax**

Run: `php -l app/Http/Controllers/Admin/UserController.php`
Expected: `No syntax errors detected`

- [ ] **Step 6: Jalankan test Step 2, HARUS PASS**

Run: `php artisan test --filter="does not show guru as a selectable role option" --compact`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/UserController.php tests/Feature/Admin/UserManagementTest.php
git commit -m "$(cat <<'EOF'
fix(rbac): keluarkan guru dari daftar role yang tampil di form Pengguna generik

Guru butuh profil Guru (guru.user_id) yang cuma bisa dibuat lewat
Admin -> Guru (GuruController). guru_bk/wali_kelas TIDAK ikut dikeluarkan
karena keduanya terbukti tidak butuh profil apa pun.
EOF
)"
```

---

### Task 2: Tolak `guru` di `store()` — server-side guard

**Files:**
- Modify: `app/Http/Controllers/Admin/UserController.php:189-232`
- Test: `tests/Feature/Admin/UserManagementTest.php`

**Interfaces:**
- Consumes: tidak ada dependency langsung ke Task 1 (guard ini independen dari apa yang tampil di UI — mencegah manipulasi request langsung).
- Produces: `store()` yang menolak `roles` mengandung `guru` sebelum `User` dibuat. Task 4 (rewrite test lama) bergantung pada perilaku ini.

- [ ] **Step 1: Tulis failing test — `guru` sendirian ditolak di `store()`**

Tambahkan:
```php
it('rejects creating a user with the guru role via the generic form', function () {
    $manager = actingAsUserManager();
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Percobaan Guru',
        'email' => 'percobaanguru@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'roles' => ['guru'],
    ])->assertSessionHasErrors('roles');

    $errors = session('errors');
    expect($errors->get('roles')[0])->toBe('Role Guru harus dibuat melalui Admin → Guru agar profil Guru dibuat dan tertaut dengan benar.');
    expect(User::withoutGlobalScopes()->where('email', 'percobaanguru@example.test')->exists())->toBeFalse();
});

it('rejects creating a user when guru is combined with another role', function () {
    $manager = actingAsUserManager();
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    Role::firstOrCreate(['name' => 'guru_bk', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Percobaan Guru Kombinasi',
        'email' => 'percobaangurukombinasi@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'roles' => ['guru', 'guru_bk'],
    ])->assertSessionHasErrors('roles');

    expect(User::withoutGlobalScopes()->where('email', 'percobaangurukombinasi@example.test')->exists())->toBeFalse();
});
```

- [ ] **Step 2: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter="rejects creating a user with the guru role|rejects creating a user when guru is combined" --compact`
Expected: FAIL — kedua request saat ini akan berhasil membuat `User` (belum ada guard), jadi `assertSessionHasErrors('roles')` gagal.

- [ ] **Step 3: Edit `store()` — tambah guard SETELAH validasi, SEBELUM resolusi `$lembagaId`**

Baris 189-201 saat ini:
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
```
Ganti menjadi (sisipkan guard baru di antara `$request->validate()` dan `$lembagaId = ...`):
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

        if (in_array('guru', $data['roles'], true)) {
            return back()->withErrors(['roles' => 'Role Guru harus dibuat melalui Admin → Guru agar profil Guru dibuat dan tertaut dengan benar.'])->withInput();
        }

        $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
```
Sisa method (dari `$lembagaId = ...` sampai akhir) TIDAK berubah.

- [ ] **Step 4: Jalankan `php -l`**

Run: `php -l app/Http/Controllers/Admin/UserController.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Jalankan test Step 1, HARUS PASS**

Run: `php artisan test --filter="rejects creating a user with the guru role|rejects creating a user when guru is combined" --compact`
Expected: 2 passed.

- [ ] **Step 6: Jalankan seluruh `UserManagementTest.php` untuk cek regresi di test `store()` lain**

Run: `php artisan test tests/Feature/Admin/UserManagementTest.php --compact`
Expected: test lain yang TIDAK menyentuh `guru` tetap PASS. Test di baris 361-405 (yang memakai `guru`) akan GAGAL di titik ini — itu DIHARAPKAN, ditangani di Task 4. JANGAN mencoba memperbaikinya di task ini.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/UserController.php tests/Feature/Admin/UserManagementTest.php
git commit -m "$(cat <<'EOF'
fix(rbac): tolak role guru di UserController::store() (server-side guard)

Mencegah manipulasi request langsung (bypass UI) yang masih bisa membuat
User(role=guru) tanpa profil Guru. Pesan error menjelaskan jalur benar
(Admin -> Guru). Berlaku juga saat guru dikombinasikan dengan role lain.
EOF
)"
```

---

### Task 3: Tolak penambahan `guru` di `update()` + preservasi paksa untuk user existing

**Files:**
- Modify: `app/Http/Controllers/Admin/UserController.php:247-277`
- Test: `tests/Feature/Admin/UserManagementTest.php`

**Interfaces:**
- Consumes: tidak ada dependency ke Task 1/2 selain konsistensi pesan error (harus identik dengan Task 2).
- Produces: `update()` yang (a) menolak permintaan MENAMBAHKAN `guru`, (b) TIDAK PERNAH mencabut `guru` dari user yang sudah punya, bahkan kalau `guru` tidak ada di `$data['roles']` yang dikirim. Task 4 bergantung pada perilaku ini.

- [ ] **Step 1: Tulis failing test — tolak penambahan `guru` lewat update() (spec §4.5)**

Tambahkan:
```php
it('rejects adding the guru role to an existing user via update', function () {
    $manager = actingAsUserManager();
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    Role::firstOrCreate(['name' => 'guru_bk', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);
    $staff = User::factory()->create(['name' => 'Staf BK', 'email' => 'stafbk@example.test', 'lembaga_id' => $lembaga->id]);
    $staff->assignRole('guru_bk');

    $this->actingAs($manager)->put(route('admin.users.update', $staff), [
        'name' => 'Staf BK',
        'email' => 'stafbk@example.test',
        'roles' => ['guru_bk', 'guru'],
    ])->assertSessionHasErrors('roles');

    $errors = session('errors');
    expect($errors->get('roles')[0])->toBe('Role Guru harus dibuat melalui Admin → Guru agar profil Guru dibuat dan tertaut dengan benar.');

    $fresh = $staff->fresh();
    expect($fresh->hasRole('guru_bk'))->toBeTrue();
    expect($fresh->hasRole('guru'))->toBeFalse();
});
```

- [ ] **Step 2: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter="rejects adding the guru role to an existing user" --compact`
Expected: FAIL — saat ini `update()` akan berhasil menambahkan `guru` (belum ada guard).

- [ ] **Step 3: Tulis failing test kedua — preservasi `guru` + carrier saat update TIDAK menyertakan `guru` (spec §4.5b, regression guard kritis)**

Tambahkan:
```php
it('preserves the guru role and its pegawai_lembaga carrier when updating with unrelated roles, since the guru checkbox no longer exists', function () {
    $manager = actingAsUserManager();
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    Role::firstOrCreate(['name' => 'pegawai_lembaga', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    Role::firstOrCreate(['name' => 'wakasek_kurikulum', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);
    $guru = User::factory()->create(['name' => 'Guru Rangkap', 'email' => 'gururangkap@example.test', 'lembaga_id' => $lembaga->id]);
    $guru->assignRole(['guru', 'pegawai_lembaga']);

    $this->actingAs($manager)->put(route('admin.users.update', $guru), [
        'name' => 'Guru Rangkap',
        'email' => 'gururangkap@example.test',
        'roles' => ['wakasek_kurikulum'],
    ])->assertRedirect(route('admin.users.index'));

    $fresh = $guru->fresh();
    expect($fresh->hasRole('guru'))->toBeTrue();
    expect($fresh->hasRole('pegawai_lembaga'))->toBeTrue();
    expect($fresh->hasRole('wakasek_kurikulum'))->toBeTrue();
});
```

- [ ] **Step 4: Jalankan test Step 3, pastikan GAGAL**

Run: `php artisan test --filter="preserves the guru role and its pegawai_lembaga carrier" --compact`
Expected: FAIL — saat ini `syncRoles(['wakasek_kurikulum', 'pegawai_lembaga'])` (baseline dihitung ulang tanpa `guru`, tapi kebetulan `wakasek_kurikulum` juga scope_level=lembaga jadi carrier tetap muncul) TAPI `guru` akan HILANG (`hasRole('guru')` jadi `false`), jadi assertion pertama gagal.

- [ ] **Step 5: Edit `update()` — tambah guard + preservasi paksa**

Baris 247-277 saat ini:
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
Ganti menjadi:
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

        if (in_array('guru', $data['roles'], true)) {
            return back()->withErrors(['roles' => 'Role Guru harus dibuat melalui Admin → Guru agar profil Guru dibuat dan tertaut dengan benar.'])->withInput();
        }

        // Form ini tidak pernah menampilkan checkbox 'guru' (lihat assignableRoles()),
        // jadi request TIDAK BISA merepresentasikan niat "cabut role guru". Kalau user
        // sudah punya 'guru', paksa tetap ikut disertakan -- baik ke perhitungan
        // carrier maupun ke syncRoles() -- supaya form fungsional ini tidak pernah
        // diam-diam mencabut identitas guru. Lifecycle 'guru' HANYA dikelola lewat
        // Admin -> Guru (GuruController), tidak pernah lewat sini, baik untuk
        // menambah maupun mencabut.
        $rolesToPersist = $data['roles'];
        if ($user->hasRole('guru')) {
            $rolesToPersist[] = 'guru';
        }

        $selectedRoles = Role::whereIn('name', $rolesToPersist)->get();
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
        $user->syncRoles(array_filter([...$rolesToPersist, $baselineRole]));

        return redirect()->route('admin.users.index')->with('status', 'Akun staff berhasil diperbarui.');
    }
```
Perhatikan: guard penolakan memakai `$data['roles']` (ASLI), sedangkan `$selectedRoles`/`syncRoles()` memakai `$rolesToPersist` (SETELAH preservasi). Kalau tertukar, update untuk guru existing akan selalu ditolak.

- [ ] **Step 6: Jalankan `php -l`**

Run: `php -l app/Http/Controllers/Admin/UserController.php`
Expected: `No syntax errors detected`

- [ ] **Step 7: Jalankan kedua test Step 1 dan Step 3, HARUS PASS**

Run: `php artisan test --filter="rejects adding the guru role to an existing user|preserves the guru role and its pegawai_lembaga carrier" --compact`
Expected: 2 passed.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/UserController.php tests/Feature/Admin/UserManagementTest.php
git commit -m "$(cat <<'EOF'
fix(rbac): cegah update() menambah ATAU diam-diam mencabut role guru

Guard menolak permintaan menambahkan 'guru' via form generik (sama seperti
store()). Fix kritis terpisah: user yang SUDAH py 'guru' sekarang dipaksa
tetap disertakan ($rolesToPersist) ke perhitungan baselineCarrierRole() dan
syncRoles() -- karena checkbox 'guru' sudah tidak ada di form, submission
manapun yang tidak menyertakan 'guru' TIDAK BOLEH ditafsirkan sebagai niat
mencabutnya. Tanpa fix ini, admin yang sekadar menambah role fungsional
lain ke akun guru akan diam-diam kehilangan role guru dan carrier
pegawai_lembaga sekaligus.
EOF
)"
```

---

### Task 4: Rewrite 2 test lama yang menguji perilaku yang sekarang dilarang, + 3 test regresi/acceptance sisa dari spec

**Files:**
- Modify: `tests/Feature/Admin/UserManagementTest.php:361-405` (2 test existing, lihat Step 1-2)
- Modify: `tests/Feature/Admin/UserManagementTest.php` (tambah test baru sesuai spec §4.3, §4.4, §4.6, §4.7)

**Interfaces:**
- Consumes: seluruh perubahan Task 1-3.
- Produces: tidak ada interface baru — task terakhir, murni menyelesaikan coverage test dan membereskan 2 test yang jadi usang.

- [ ] **Step 1: Ganti test lama "does not strip the pegawai_lembaga baseline role..." (baris 361-381) — sekarang menguji self-healing TANPA submit `guru`**

Baris 361-381 saat ini (SUDAH GAGAL sejak Task 3, karena submit `roles: ['guru']` sekarang ditolak):
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
```
Ganti PERSIS menjadi (nama test diperbarui supaya mencerminkan skenario baru — bukan lagi "submit guru", tapi "self-heal baseline yang hilang saat menambah role lain, tanpa admin pernah submit guru sama sekali"):
```php
it('self-heals a missing pegawai_lembaga baseline for a guru-only account when adding an unrelated role, without ever submitting guru', function () {
    $manager = actingAsUserManager();
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    Role::firstOrCreate(['name' => 'pegawai_lembaga', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    Role::firstOrCreate(['name' => 'guru_bk', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);
    $guru = User::factory()->create(['name' => 'Guru Lama', 'email' => 'gurulama@example.test', 'lembaga_id' => $lembaga->id]);
    $guru->assignRole('guru');

    expect($guru->hasRole('pegawai_lembaga'))->toBeFalse();

    $this->actingAs($manager)->put(route('admin.users.update', $guru), [
        'name' => 'Guru Baru',
        'email' => 'gurulama@example.test',
        'roles' => ['guru_bk'],
    ])->assertRedirect(route('admin.users.index'));

    $updated = $guru->fresh();
    expect($updated->hasRole('guru'))->toBeTrue();
    expect($updated->hasRole('pegawai_lembaga'))->toBeTrue();
    expect($updated->hasRole('guru_bk'))->toBeTrue();
});
```

- [ ] **Step 2: Ganti test lama "assigns multiple functional roles at once..." (baris 383-405) — pakai 2 role fungsional yang bukan `guru`**

Baris 383-405 saat ini (SUDAH GAGAL sejak Task 2, karena `roles: ['wakasek_kurikulum', 'guru']` sekarang ditolak total):
```php
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
```
Ganti PERSIS menjadi (dua role fungsional lembaga-scope yang BUKAN `guru`, membuktikan invariant "banyak role fungsional berbagi satu carrier" tanpa lagi memakai `guru`):
```php
it('assigns multiple functional roles at once plus a single shared pegawai_lembaga baseline', function () {
    $manager = actingAsUserManager();
    Role::firstOrCreate(['name' => 'wakasek_kurikulum', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    Role::firstOrCreate(['name' => 'pegawai_lembaga', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Wakasek Merangkap Admin SDM',
        'email' => 'wakasekadminsdm@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'lembaga_id' => $lembaga->id,
        'roles' => ['wakasek_kurikulum', 'admin_sdm'],
    ])->assertRedirect(route('admin.users.index'));

    $created = User::withoutGlobalScopes()->where('email', 'wakasekadminsdm@example.test')->first();
    expect($created->hasRole('wakasek_kurikulum'))->toBeTrue();
    expect($created->hasRole('admin_sdm'))->toBeTrue();
    expect($created->hasRole('pegawai_lembaga'))->toBeTrue();
    expect($created->roles()->count())->toBe(3);
});
```

- [ ] **Step 3: Jalankan kedua test yang baru diganti, HARUS PASS**

Run: `php artisan test --filter="self-heals a missing pegawai_lembaga baseline|assigns multiple functional roles at once" --compact`
Expected: 2 passed.

- [ ] **Step 4: Tulis test baru — `guru_bk` sendirian tetap boleh (spec §4.3)**

Tambahkan:
```php
it('still allows creating a user with only the guru_bk role via the generic form', function () {
    $manager = actingAsUserManager();
    Role::firstOrCreate(['name' => 'guru_bk', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Konselor BK Baru',
        'email' => 'konselorbkbaru@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'lembaga_id' => $lembaga->id,
        'roles' => ['guru_bk'],
    ])->assertRedirect(route('admin.users.index'));

    $created = User::withoutGlobalScopes()->where('email', 'konselorbkbaru@example.test')->first();
    expect($created)->not->toBeNull();
    expect($created->hasRole('guru_bk'))->toBeTrue();
});
```

- [ ] **Step 5: Tulis test baru — `wali_kelas` sendirian tetap boleh (spec §4.4)**

Tambahkan:
```php
it('still allows creating a user with only the wali_kelas role via the generic form', function () {
    $manager = actingAsUserManager();
    Role::firstOrCreate(['name' => 'wali_kelas', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'Wali Kelas Baru',
        'email' => 'walikelasbaru@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'lembaga_id' => $lembaga->id,
        'roles' => ['wali_kelas'],
    ])->assertRedirect(route('admin.users.index'));

    $created = User::withoutGlobalScopes()->where('email', 'walikelasbaru@example.test')->first();
    expect($created)->not->toBeNull();
    expect($created->hasRole('wali_kelas'))->toBeTrue();
});
```

- [ ] **Step 6: Jalankan kedua test baru, HARUS PASS**

Run: `php artisan test --filter="still allows creating a user with only the guru_bk role|still allows creating a user with only the wali_kelas role" --compact`
Expected: 2 passed.

- [ ] **Step 7: Jalankan SELURUH `UserManagementTest.php` — checkpoint penutup untuk file ini**

Run: `php artisan test tests/Feature/Admin/UserManagementTest.php --compact`
Expected: SEMUA test PASS (test lama yang tidak menyentuh `guru` + 2 test yang diganti Step 1-2 + test baru Task 1-4). Catat angka pasti.

- [ ] **Step 8: Cari file test lain yang mungkin submit `roles` mengandung `guru` ke endpoint `admin.users.*`**

Run:
```bash
grep -rln "admin.users.store\|admin.users.update" tests/ | xargs grep -l "'guru'"
```
Kalau ada file LAIN selain `UserManagementTest.php` yang muncul di hasil ini, buka dan periksa apakah submission-nya akan kena guard baru — kalau ya, STOP dan laporkan ke user (jangan diam-diam mengubah file test yang tidak disebut plan ini).

- [ ] **Step 9: Commit**

```bash
git add tests/Feature/Admin/UserManagementTest.php
git commit -m "$(cat <<'EOF'
test(rbac): rewrite 2 test lama yang memakai submission guru terlarang, +4 test acceptance

- 'does not strip pegawai_lembaga...' dan 'assigns multiple functional
  roles...' diganti supaya tidak lagi submit 'guru' secara eksplisit --
  invariant yang sama (self-healing baseline, multi-role shared carrier)
  tetap dibuktikan lewat cara submit yang valid di desain baru.
- +test guru_bk sendirian tetap bisa dibuat (spec 4.3)
- +test wali_kelas sendirian tetap bisa dibuat (spec 4.4)
EOF
)"
```

---

### Task 5: Regression check (checkpoint penutup)

**Files:**
- Tidak ada file yang diedit — task ini murni verifikasi.

**Interfaces:**
- Consumes: seluruh perubahan Task 1-4.
- Produces: konfirmasi akhir tidak ada regresi di luar `UserManagementTest.php`.

- [ ] **Step 1: Jalankan test file terkait lain yang menyentuh `UserController`/role `guru` di luar `UserManagementTest.php`**

Berdasarkan Task 4 Step 8, kalau tidak ada file lain yang ditemukan, cukup jalankan file-file yang sudah diketahui bersinggungan dengan role/permission secara umum:
```bash
php artisan test tests/Feature/Admin/UserManagementTest.php tests/Unit/RoleSeederTest.php --compact
```
Expected: 0 failed.

- [ ] **Step 2: Jalankan `vendor/bin/pint --dirty --format agent`**

Run: `vendor/bin/pint --dirty --format agent`
Expected: tidak ada error. Kalau ada file yang diformat ulang:
```bash
git add -u
git commit -m "style: pint formatting untuk perubahan guard guru rbac-v2"
```

- [ ] **Step 3: Laporkan hasil akhir ke user**

Ringkasan yang WAJIB disampaikan:
- Angka pasti test di `UserManagementTest.php` (dan `RoleSeederTest.php`).
- Daftar commit hash Task 1-4 (dan Task 5 kalau ada commit pint).
- Konfirmasi eksplisit: `guru` tidak bisa lagi dibuat/ditambahkan lewat `Admin → Pengguna` (sendirian maupun dikombinasikan), `guru_bk`/`wali_kelas` tetap bisa, dan role `guru` + carrier `pegawai_lembaga` pada akun guru existing tidak pernah hilang saat admin mengedit role lain lewat form ini.

---

## Self-Review (dilakukan penulis plan)

**Spec coverage**: §2.2(a) → Task 1. §2.2(b) → Task 2. §2.2(c) (termasuk fix kritis preservasi) → Task 3. §2.2(d) (formRoleGroups tidak diubah) → tidak ada task, sesuai spec eksplisit "TIDAK PERLU DIUBAH". §4.1 → Task 1 Step 2. §4.2 → Task 2 Step 1 (test pertama). §4.2b → Task 2 Step 1 (test kedua). §4.3 → Task 4 Step 4. §4.4 → Task 4 Step 5. §4.5 → Task 3 Step 1. §4.5b → Task 3 Step 3. §4.6 → dicek implisit lewat Task 4 Step 7 (full file run, test kombinasi role administratif existing tetap ada di file dan tidak disentuh). §4.7 → tidak ada task terpisah (spec eksplisit: `GuruController` tidak disentuh sama sekali, tidak ada test baru dibutuhkan di sana — cukup dicek lewat `grep` Task 4 Step 8 bahwa tidak ada test `GuruController` yang bersinggungan).

**Temuan tambahan di luar spec (ditemukan saat menyusun plan, sudah dikonfirmasi user)**: 2 test existing (`UserManagementTest.php:361-405`) menguji submission `roles` mengandung `guru` yang sekarang eksplisit dilarang — ditangani Task 4 Step 1-2 dengan rewrite yang mempertahankan invariant asli test tersebut lewat cara submit yang valid.

**Placeholder scan**: tidak ada "TBD"/"implement later" — semua step berisi kode lengkap atau command lengkap dengan expected output.

**Type consistency**: `$rolesToPersist` dipakai konsisten di Task 3 (didefinisikan sebagai `array`, dipakai untuk `Role::whereIn('name', $rolesToPersist)` dan `syncRoles(array_filter([...$rolesToPersist, $baselineRole]))`). Pesan error `'Role Guru harus dibuat melalui Admin → Guru agar profil Guru dibuat dan tertaut dengan benar.'` PERSIS sama di Task 2 dan Task 3 (disalin verbatim, bukan diketik ulang, untuk mencegah typo yang membuat 2 guard punya pesan berbeda).
