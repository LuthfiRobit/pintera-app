# Follow-up Kecil: admin_akademik User, Kelas Test Coverage, Semester IDOR Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the 3 small follow-up items noted after the presensi/asesmen module and its gap-closure branch were merged: (1) seed an example `admin_akademik` account so the role that now holds 25 permissions actually has a holder, matching the existing `kepsek@sistem.test`/`adm@sistem.test`/etc. pattern; (2) extend `KelasCrudTest.php`'s cross-lembaga IDOR coverage to the `pola_jam_id`/`wali_kelas_guru_id` vectors and the `update()` path, which were fixed in code during the gap-closure branch but only got a `tahun_ajaran_id`/`store()` test; (3) fix `SemesterController::store()`'s `tahun_ajaran_id` validation, which still uses the same raw `exists:tahun_ajaran,id` pattern already fixed everywhere else in this module (`TahunAjaran` is tenant-scoped via `BelongsToTenant`) — currently dormant since no lembaga-scoped role holds `semester.create` yet, but per this project's own standing lesson, a permission being ungranted today doesn't make the underlying bug fixed.

**Architecture:** Three small, independent tasks — safe to execute in any order.

**Tech Stack:** Laravel 12, Pest 4.

## Global Constraints

- Same conventions as every prior tahap/addendum in this project (`AuthorizesRequests`, `casts()` method style).
- Any FK validated against a tenant-scoped model (`TahunAjaran` uses `BelongsToTenant`) must be resolved via `Model::find($id)` + `abort_if(null, 404)` — never a raw `exists:table,column` rule. This is now the project's most-repeated standing rule (10 confirmed occurrences of the same bug class across this project's history).
- Every task's change must keep `php artisan test` green end-to-end.

---

### Task 1: Seed an example `admin_akademik` account

**Files:**
- Modify: `database/seeders/EssentialUserSeeder.php`
- Modify: `tests/Unit/EssentialUserSeederTest.php`

**Interfaces:** Standalone.

**Context:** `EssentialUserSeeder` creates one example account per lembaga-scoped role (`kepsek@sistem.test`, `adm@sistem.test`, `keuangan@sistem.test`, `guru@sistem.test`) so a fresh install always has a login for every role. `admin_akademik` (added by the gap-closure branch, 25 academic-management permissions) has no example account, so after a fresh seed nobody but `yayasan_super_admin` can actually use the presensi/asesmen module's admin screens.

- [ ] **Step 1: Write the failing test**

Modify `tests/Unit/EssentialUserSeederTest.php`:

Change the second test's title from `'creates all 5 essential accounts when a lembaga exists, attaching the lembaga-scoped ones to it'` to `'creates all 6 essential accounts when a lembaga exists, attaching the lembaga-scoped ones to it'`, and after the existing `$guru = ...` block at the end of that test, add:

```php
    $akademik = User::where('email', 'akademik@sistem.test')->first();
    expect($akademik->hasRole('admin_akademik'))->toBeTrue();
    expect($akademik->lembaga_id)->toBe($lembaga->id);
```

Change the `it('is idempotent when run twice', ...)` test's `expect(User::count())->toBe(5);` to `expect(User::count())->toBe(6);`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/EssentialUserSeederTest.php`
Expected: FAIL — `akademik@sistem.test` doesn't exist yet, `User::count()` is 5 not 6.

- [ ] **Step 3: Update the seeder**

In `database/seeders/EssentialUserSeeder.php`, add a new entry to the `$akunLembagaScoped` array:

```php
        $akunLembagaScoped = [
            'kepsek@sistem.test' => ['name' => 'Kepala Sekolah (Contoh)', 'role' => 'kepala_sekolah'],
            'adm@sistem.test' => ['name' => 'Admin Administrasi (Contoh)', 'role' => 'admin_administrasi'],
            'keuangan@sistem.test' => ['name' => 'Admin Keuangan (Contoh)', 'role' => 'admin_keuangan'],
            'akademik@sistem.test' => ['name' => 'Admin Akademik (Contoh)', 'role' => 'admin_akademik'],
            'guru@sistem.test' => ['name' => 'Guru (Contoh)', 'role' => 'guru'],
        ];
```

(No other change needed — the existing `foreach` loop below already handles any entry in this array generically.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/EssentialUserSeederTest.php`
Expected: PASS (all tests)

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: all tests pass — check for any OTHER hardcoded `User::count()` assertion elsewhere in the suite that might also assume 5 essential accounts (grep for it) and bump if found.

- [ ] **Step 6: Commit**

```bash
git add database/seeders/EssentialUserSeeder.php tests/Unit/EssentialUserSeederTest.php
git commit -m "feat: seed an example admin_akademik account"
```

- [ ] **Step 7: Re-seed the real dev DB (post-merge step)**

Per this project's standing post-seeder-change step: run `php artisan db:seed --class=EssentialUserSeeder` against the real dev DB (`pintera_app`) after merging, so `akademik@sistem.test` actually exists there too.

---

### Task 2: Extend `KelasCrudTest.php`'s cross-lembaga IDOR coverage

**Files:**
- Modify: `tests/Feature/Admin/KelasCrudTest.php`

**Interfaces:** Standalone.

**Context:** The gap-closure branch fixed `KelasController::store()`/`update()` to resolve `tahun_ajaran_id`, `wali_kelas_guru_id`, and `pola_jam_id` via `Model::find()` + `abort_if(null-or-lembaga-mismatch, 404)` instead of raw `exists:` rules — but only added ONE regression test (`tahun_ajaran_id` on `store()`). The `wali_kelas_guru_id`/`pola_jam_id` vectors and the entire `update()` path are fixed in code but currently unverified by any test. Current `KelasController` code (for reference, already correct, do not change it):

```php
public function store(Request $request): RedirectResponse
{
    $this->authorize('kelas.create');

    $data = $request->validate([
        'tahun_ajaran_id' => ['required', 'integer'],
        'nama' => ['required', 'string', 'max:255'],
        'tingkat' => ['nullable', 'string', 'max:20'],
        'wali_kelas_guru_id' => ['nullable', 'integer'],
        'pola_jam_id' => ['nullable', 'integer'],
    ]);

    $tahunAjaran = TahunAjaran::find($data['tahun_ajaran_id']);
    abort_if($tahunAjaran === null, 404);
    $data['tahun_ajaran_id'] = $tahunAjaran->id;

    if (!empty($data['wali_kelas_guru_id'])) {
        $guru = Guru::find($data['wali_kelas_guru_id']);
        abort_if($guru === null || $guru->lembaga_id !== $tahunAjaran->lembaga_id, 404);
        $data['wali_kelas_guru_id'] = $guru->id;
    }

    if (!empty($data['pola_jam_id'])) {
        $polaJam = PolaJam::find($data['pola_jam_id']);
        abort_if($polaJam === null || $polaJam->lembaga_id !== $tahunAjaran->lembaga_id, 404);
        $data['pola_jam_id'] = $polaJam->id;
    }

    if ($request->user()->widestScopeLevel() === 'yayasan') {
        $lembagaId = session('active_lembaga_id');
        if ($lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat kelas.'])->withInput();
        }
        $data['lembaga_id'] = $lembagaId;
    }

    Kelas::create($data);

    return redirect()->route('admin.kelas.index')->with('status', 'Kelas berhasil disimpan.');
}

public function update(Request $request, Kelas $kelas): RedirectResponse
{
    $this->authorize('kelas.edit');

    $data = $request->validate([
        'tahun_ajaran_id' => ['required', 'integer'],
        'nama' => ['required', 'string', 'max:255'],
        'tingkat' => ['nullable', 'string', 'max:20'],
        'wali_kelas_guru_id' => ['nullable', 'integer'],
        'pola_jam_id' => ['nullable', 'integer'],
    ]);

    $tahunAjaran = TahunAjaran::find($data['tahun_ajaran_id']);
    abort_if($tahunAjaran === null || $tahunAjaran->lembaga_id !== $kelas->lembaga_id, 404);
    $data['tahun_ajaran_id'] = $tahunAjaran->id;

    if (!empty($data['wali_kelas_guru_id'])) {
        $guru = Guru::find($data['wali_kelas_guru_id']);
        abort_if($guru === null || $guru->lembaga_id !== $kelas->lembaga_id, 404);
        $data['wali_kelas_guru_id'] = $guru->id;
    }

    if (!empty($data['pola_jam_id'])) {
        $polaJam = PolaJam::find($data['pola_jam_id']);
        abort_if($polaJam === null || $polaJam->lembaga_id !== $kelas->lembaga_id, 404);
        $data['pola_jam_id'] = $polaJam->id;
    }

    $kelas->update($data);

    return redirect()->route('admin.kelas.index')->with('status', 'Kelas berhasil diperbarui.');
}
```

**Before writing the tests below, read the ACTUAL current `app/Http/Controllers/Admin/KelasController.php` on disk** — the code above is what the plan author observed; if the real file differs in any way (variable names, exact structure), write the tests against what's really there, not this snippet.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Admin/KelasCrudTest.php` (append after the existing `it('rejects creating a kelas with a tahun_ajaran belonging to a different lembaga', ...)` test):

```php
it('rejects creating a kelas with a wali_kelas_guru_id belonging to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $guruLain = Guru::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembagaLain->id])->id,
        'lembaga_id' => $lembagaLain->id,
        'nik' => '3201234567892222',
        'nama' => 'Guru Lain Lembaga',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);
    $manager = actingAsKelasManager($lembagaSaya);

    $this->actingAs($manager)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Kelas Wali Campur',
        'tingkat' => '6',
        'wali_kelas_guru_id' => $guruLain->id,
    ])->assertNotFound();

    expect(Kelas::where('nama', 'Kelas Wali Campur')->exists())->toBeFalse();
});

it('rejects creating a kelas with a pola_jam_id belonging to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $polaLain = PolaJam::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $manager = actingAsKelasManager($lembagaSaya);

    $this->actingAs($manager)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Kelas Pola Campur',
        'tingkat' => '6',
        'pola_jam_id' => $polaLain->id,
    ])->assertNotFound();

    expect(Kelas::where('nama', 'Kelas Pola Campur')->exists())->toBeFalse();
});

it('rejects updating a kelas to a tahun_ajaran belonging to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranSaya = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $manager = actingAsKelasManager($lembagaSaya);
    $kelas = Kelas::create(['lembaga_id' => $lembagaSaya->id, 'tahun_ajaran_id' => $tahunAjaranSaya->id, 'nama' => '6A']);

    $this->actingAs($manager)->put(route('admin.kelas.update', $kelas), [
        'tahun_ajaran_id' => $tahunAjaranLain->id,
        'nama' => '6A',
        'tingkat' => '6',
    ])->assertNotFound();

    expect($kelas->fresh()->tahun_ajaran_id)->toBe($tahunAjaranSaya->id);
});

it('rejects updating a kelas with a wali_kelas_guru_id belonging to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $manager = actingAsKelasManager($lembagaSaya);
    $kelas = Kelas::create(['lembaga_id' => $lembagaSaya->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '6A']);
    $guruLain = Guru::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembagaLain->id])->id,
        'lembaga_id' => $lembagaLain->id,
        'nik' => '3201234567893333',
        'nama' => 'Guru Lain Lembaga Dua',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    $this->actingAs($manager)->put(route('admin.kelas.update', $kelas), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => '6A',
        'tingkat' => '6',
        'wali_kelas_guru_id' => $guruLain->id,
    ])->assertNotFound();

    expect($kelas->fresh()->wali_kelas_guru_id)->toBeNull();
});
```

- [ ] **Step 2: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/KelasCrudTest.php`
Expected: PASS (all tests, including the 4 new ones) — these tests exercise already-correct behavior from the gap-closure branch, no implementation change is expected. If any new test fails, that reveals a real gap in the existing fix — stop and report it rather than weakening the test.

- [ ] **Step 3: Run the full suite**

Run: `php artisan test`
Expected: all tests pass.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Admin/KelasCrudTest.php
git commit -m "test: extend Kelas cross-lembaga IDOR coverage to pola_jam_id/wali_kelas_guru_id and update()"
```

---

### Task 3: Fix `SemesterController::store()`'s dormant cross-tenant IDOR

**Files:**
- Modify: `app/Http/Controllers/Admin/SemesterController.php`
- Modify: `tests/Feature/Admin/TahunAjaranSemesterPanelTest.php`

**Interfaces:** Standalone.

**Context:** `SemesterController::store()` validates `'tahun_ajaran_id' => ['required', 'exists:tahun_ajaran,id']` — the same raw `exists:` pattern against a tenant-scoped model (`TahunAjaran` uses `BelongsToTenant`) already fixed in `KelasController`/`SemesterController`'s siblings during the gap-closure branch. Currently dormant (no lembaga-scoped role holds `semester.create` yet — only `yayasan_super_admin`, who isn't tenant-restricted, and ad hoc test-only roles), but per this project's own standing lesson from the last review cycle, a permission being ungranted today doesn't make the underlying bug fixed; fix it now while doing this sweep rather than waiting for it to become reachable and rediscovering it later.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Admin/TahunAjaranSemesterPanelTest.php` (append after the existing `it('creates a semester under a tahun ajaran', ...)` test):

```php
it('rejects creating a semester under a tahun_ajaran belonging to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsTahunAjaranManager($lembagaSaya);
    $this->actingAs($manager);

    $tahunAjaranLain = TahunAjaran::create([
        'lembaga_id' => $lembagaLain->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30',
    ]);

    $this->post(route('admin.semester.store'), [
        'tahun_ajaran_id' => $tahunAjaranLain->id,
        'nama' => 'Ganjil',
        'urutan' => 1,
        'kode_dapodik' => '20261',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-01-15',
    ])->assertNotFound();

    expect(Semester::where('tahun_ajaran_id', $tahunAjaranLain->id)->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/TahunAjaranSemesterPanelTest.php`
Expected: FAIL — `exists:tahun_ajaran,id` doesn't check tenancy, so the cross-lembaga POST currently succeeds (302 redirect) instead of 404ing.

- [ ] **Step 3: Fix the controller**

Replace `app/Http/Controllers/Admin/SemesterController.php`'s `store()` method:

```php
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('semester.create');

        $data = $request->validate([
            'tahun_ajaran_id' => ['required', 'integer'],
            'nama' => ['required', 'in:Ganjil,Genap'],
            'urutan' => ['required', 'integer', 'in:1,2'],
            'kode_dapodik' => ['nullable', 'string', 'max:5'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after:tanggal_mulai'],
        ]);

        $tahunAjaran = TahunAjaran::find($data['tahun_ajaran_id']);
        abort_if($tahunAjaran === null, 404);
        $data['tahun_ajaran_id'] = $tahunAjaran->id;

        Semester::create($data);

        return redirect()->route('admin.tahun-ajaran.index')->with('status', 'Semester berhasil dibuat.');
    }
```

Add `use App\Models\TahunAjaran;` to the file's `use` block.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Admin/TahunAjaranSemesterPanelTest.php`
Expected: PASS (all tests, including the new one)

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/SemesterController.php tests/Feature/Admin/TahunAjaranSemesterPanelTest.php
git commit -m "fix: close dormant cross-lembaga IDOR in SemesterController::store()"
```

---

## Plan Self-Review Notes

- **Spec coverage**: all 3 items from the user's request have a dedicated task with complete, runnable code and no placeholders.
- **Scope discipline**: the broader `exists:` sweep was deliberately NOT extended to `GuruController::store()`'s `user_id`→`users` table, `LembagaController`'s `yayasan_id`→`yayasan` table, `RoleController`'s `permissions.*`→`permissions` table, or `UserController`'s `lembaga_id`→`lembaga` table — none of these point at a `BelongsToTenant` model (they point at the tenant root itself, or at globally-shared tables), so they are not instances of this bug class and are out of scope for "sweep remaining exists: FK issues" in the presensi/asesmen module's context.
