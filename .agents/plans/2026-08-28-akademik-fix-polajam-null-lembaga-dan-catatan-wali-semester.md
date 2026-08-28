# Fix: PolaJam lembaga_id NULL & Catatan Wali Kelas Semester Mismatch — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** (1) `PolaJamController::store()` harus mengisi `lembaga_id` dari akun aktor untuk aktor lembaga biasa (bukan hanya untuk aktor yayasan). (2) `Guru\RaporController::update()` harus menolak `semester_id` yang bukan milik tahun ajaran kelas siswa, sama seperti 4 method lain di file yang sama.

**Architecture:** 2 task independen (beda file, beda area) dalam 1 plan karena digabung dalam satu siklus fix atas persetujuan user. Task 1: ganti derivasi `$lembagaId` di `PolaJamController::store()` supaya mirror `GuruController::resolveLembagaId()`. Task 2: tambah 1 `abort_if` di `Guru\RaporController::update()`, mirror pola yang sudah ada di `edit()`/`generateNarasi()`/`cetak()`.

**Tech Stack:** Laravel 12, PHP 8.3, Pest v4.

## Global Constraints

- Task 1 HANYA mengubah `app/Http/Controllers/Admin/PolaJamController.php` (ganti logika derivasi `$lembagaId` di `store()`, tidak ada import baru).
- Task 2 HANYA mengubah `app/Http/Controllers/Guru/RaporController.php` (+1 baris `abort_if` di `update()`, tidak ada import baru — `Semester` sudah di-import).
- Guard existing yang TIDAK BOLEH diubah: pesan error `'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat pola jam.'` (Task 1, tetap dipakai apa adanya), guard `wali_kelas_guru_id` di `update()` (Task 2, tetap dipertahankan sebelum guard baru).
- Semua test existing di kedua file test yang disebut di bawah WAJIB tetap PASS tanpa modifikasi assertion apa pun, KECUALI test `creates a pola jam` di `PolaJamCrudTest.php` yang BOLEH diperkuat (tambah assertion `lembaga_id`) tanpa mengubah assertion existing-nya (`assertRedirect`, `exists()` tetap dipertahankan, hanya menambah baris baru).
- Hanya jalankan test scoped per task: `php artisan test tests/Feature/Admin/PolaJamCrudTest.php --compact` untuk Task 1, `php artisan test tests/Feature/Guru/RaporControllerTest.php --compact` untuk Task 2. TIDAK PERLU full suite untuk fix sekecil ini.

---

### Task 1: Isi `lembaga_id` untuk Aktor Lembaga Biasa di `PolaJamController::store()`

**Files:**
- Modify: `app/Http/Controllers/Admin/PolaJamController.php`
- Modify: `tests/Feature/Admin/PolaJamCrudTest.php`

**Interfaces:**
- Consumes: `$request->user()->widestScopeLevel(): string`, `$request->user()->lembaga_id: ?int`, `session('active_lembaga_id')` — semuanya sudah dipakai identik di `GuruController::resolveLembagaId()` (`app/Http/Controllers/Admin/GuruController.php:181-188`), pola referensi yang di-mirror.
- Produces: `store()` tetap `(Request $request, CreatePolaJamAction $action): RedirectResponse` — signature tidak berubah, hanya logika derivasi `$lembagaId` di dalamnya.

- [x] **Step 1: Baca baseline `store()` untuk memastikan tidak ada drift**

Baseline (baris 42-62 di `app/Http/Controllers/Admin/PolaJamController.php`):

```php
    public function store(Request $request, CreatePolaJamAction $action): RedirectResponse
    {
        $this->authorize('pola-jam.create');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
        ]);

        $lembagaId = null;
        if ($request->user()->widestScopeLevel() === 'yayasan') {
            $lembagaId = session('active_lembaga_id');

            if ($lembagaId === null) {
                return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat pola jam.'])->withInput();
            }
        }

        $action->execute(new PolaJamData(nama: $data['nama'], lembagaId: $lembagaId));

        return redirect()->route('admin.pola-jam.index')->with('status', 'Pola jam berhasil dibuat.');
    }
```

Jika file di repo berbeda dari baseline ini, STOP dan laporkan sebelum melanjutkan.

- [x] **Step 2: Tulis test yang gagal (reproduksi bug + perkuat test existing + regresi negatif yayasan)**

Di `tests/Feature/Admin/PolaJamCrudTest.php`, GANTI test existing `creates a pola jam` (baris 34-44) — tambahkan assertion `lembaga_id` TANPA menghapus assertion yang sudah ada:

```php
it('creates a pola jam', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);

    $this->actingAs($manager)->post(route('admin.pola-jam.store'), [
        'nama' => 'Kelas Tinggi 4-6',
    ])->assertRedirect(route('admin.pola-jam.index'));

    $polaJam = PolaJam::where('nama', 'Kelas Tinggi 4-6')->first();
    expect($polaJam)->not->toBeNull();
    expect($polaJam->lembaga_id)->toBe($lembaga->id);
});
```

Tambahkan 2 test baru setelah test tersebut:

```php
it('lets the lembaga-scoped manager see the pola jam they just created in the index', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);

    $this->actingAs($manager)->post(route('admin.pola-jam.store'), [
        'nama' => 'Kelas Rendah 1-3',
    ]);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));

    $response->assertSee('Kelas Rendah 1-3');
});

it('creates a pola jam with the active lembaga for a yayasan-scoped manager', function () {
    Permission::firstOrCreate(['name' => 'pola-jam.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_pola_jam_create_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['pola-jam.create']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)
        ->withSession(['active_lembaga_id' => $lembaga->id])
        ->post(route('admin.pola-jam.store'), ['nama' => 'Pola Yayasan'])
        ->assertRedirect(route('admin.pola-jam.index'));

    $polaJam = PolaJam::where('nama', 'Pola Yayasan')->first();
    expect($polaJam)->not->toBeNull();
    expect($polaJam->lembaga_id)->toBe($lembaga->id);
});

it('rejects creating a pola jam for a yayasan-scoped manager with no active lembaga', function () {
    Permission::firstOrCreate(['name' => 'pola-jam.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_pola_jam_no_active_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['pola-jam.create']);

    $yayasan = Yayasan::factory()->create();
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)
        ->post(route('admin.pola-jam.store'), ['nama' => 'Pola Tanpa Lembaga'])
        ->assertSessionHasErrors('lembaga_id');

    expect(PolaJam::where('nama', 'Pola Tanpa Lembaga')->exists())->toBeFalse();
});
```

- [x] **Step 3: Jalankan test untuk memastikan reproduksi bug GAGAL (bug masih ada)**

Run: `php artisan test tests/Feature/Admin/PolaJamCrudTest.php --filter="creates a pola jam" --compact`
Expected: FAIL pada assertion `expect($polaJam->lembaga_id)->toBe($lembaga->id)` — `lembaga_id` aktual `null`.

Run: `php artisan test tests/Feature/Admin/PolaJamCrudTest.php --filter="lets the lembaga-scoped manager see the pola jam" --compact`
Expected: FAIL — `assertSee('Kelas Rendah 1-3')` gagal karena pola jam dengan `lembaga_id` null tidak muncul di index yang di-scope ke lembaga manager.

Run 2 test lain (`creates a pola jam with the active lembaga for a yayasan-scoped manager`, `rejects creating a pola jam for a yayasan-scoped manager with no active lembaga`) dan pastikan itu PASS dari awal (baseline aman untuk jalur yayasan, tidak berubah).

- [x] **Step 4: Implementasi minimal fix**

Edit `app/Http/Controllers/Admin/PolaJamController.php`:

```php
    public function store(Request $request, CreatePolaJamAction $action): RedirectResponse
    {
        $this->authorize('pola-jam.create');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
        ]);

        $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $request->user()->lembaga_id;

        if ($lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat pola jam.'])->withInput();
        }

        $action->execute(new PolaJamData(nama: $data['nama'], lembagaId: $lembagaId));

        return redirect()->route('admin.pola-jam.index')->with('status', 'Pola jam berhasil dibuat.');
    }
```

- [x] **Step 5: Jalankan seluruh file test dan pastikan semua PASS**

Run: `php artisan test tests/Feature/Admin/PolaJamCrudTest.php --compact`
Expected: PASS untuk seluruh test di file ini (baseline + 3 test baru + test existing yang diperkuat).

Jika ada test lain yang FAIL, laporkan sebagai temuan BLOCKED — jangan diam-diam mengubah assertion existing lain.

- [x] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/PolaJamController.php tests/Feature/Admin/PolaJamCrudTest.php
git commit -m "fix(akademik): isi lembaga_id untuk aktor lembaga biasa pada PolaJamController::store()"
```

---

### Task 2: Cross-Check Semester vs Tahun Ajaran Kelas di `Guru\RaporController::update()`

**Files:**
- Modify: `app/Http/Controllers/Guru/RaporController.php`
- Modify: `tests/Feature/Guru/RaporControllerTest.php`

**Interfaces:**
- Consumes: `App\Models\Semester::find(int $id): ?Semester` (sudah dipakai identik di `edit()`/`generateNarasi()`/`cetak()` di file yang sama), `$request->validated('semester_id')` dari `StoreCatatanWaliKelasRequest` (sudah divalidasi `required|integer|exists:semester,id`).
- Produces: `update()` tetap `(Siswa $siswa, StoreCatatanWaliKelasRequest $request): RedirectResponse` — signature tidak berubah, hanya menambah 1 jalur `abort_if` baru (404).

- [x] **Step 1: Baca baseline `update()` untuk memastikan tidak ada drift**

Baseline (baris 156-176 di `app/Http/Controllers/Guru/RaporController.php`):

```php
    public function update(Siswa $siswa, StoreCatatanWaliKelasRequest $request): RedirectResponse
    {
        $guru = $request->user()->guru;
        abort_if($guru === null, 403);
        abort_unless($siswa->kelas && $siswa->kelas->wali_kelas_guru_id === $guru->id, 403);

        $this->simpanCatatanWaliKelasAction->execute(
            CatatanWaliKelasData::fromArray([...$request->validated(), 'siswa_id' => $siswa->id])
        );

        $nextSiswaId = $request->input('next_siswa_id');
        if ($nextSiswaId) {
            return redirect()
                ->route('guru.rapor.catatan.edit', ['siswa' => $nextSiswaId, 'semester_id' => $request->input('semester_id')])
                ->with('success', 'Catatan wali kelas berhasil disimpan.');
        }

        return redirect()
            ->route('guru.rapor.catatan.index', ['kelas_id' => $siswa->kelas_id, 'semester_id' => $request->input('semester_id')])
            ->with('success', 'Catatan wali kelas berhasil disimpan.');
    }
```

Jika file di repo berbeda dari baseline ini, STOP dan laporkan sebelum melanjutkan.

- [x] **Step 2: Tulis test yang gagal (reproduksi bug)**

Tambahkan di akhir `tests/Feature/Guru/RaporControllerTest.php` (setelah test terakhir di file):

```php
it('rejects saving catatan wali kelas when semester_id belongs to a different tahun ajaran than the siswa kelas', function () {
    ['guruUser' => $guruUser, 'siswa' => $siswa, 'lembaga' => $lembaga] = siapkanWaliKelasUntukRapor();
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLain->id]);

    $this->actingAs($guruUser)
        ->put(route('guru.rapor.catatan.update', $siswa), [
            'semester_id' => $semesterLain->id,
            'catatan_sikap' => 'Percobaan mismatch semester.',
        ])
        ->assertNotFound();

    $this->assertDatabaseMissing('catatan_wali_kelas', [
        'siswa_id' => $siswa->id,
        'semester_id' => $semesterLain->id,
    ]);
});
```

`siapkanWaliKelasUntukRapor()` sudah mengembalikan `lembaga` di array-nya, tidak perlu setup tambahan.

- [x] **Step 3: Jalankan test untuk memastikan reproduksi bug GAGAL (bug masih ada)**

Run: `php artisan test tests/Feature/Guru/RaporControllerTest.php --filter="rejects saving catatan wali kelas when semester_id belongs to a different tahun ajaran" --compact`
Expected: FAIL — `assertNotFound()` gagal karena response sukses (redirect) alih-alih 404, dan baris `catatan_wali_kelas` justru tersimpan.

- [x] **Step 4: Implementasi minimal fix**

Edit `app/Http/Controllers/Guru/RaporController.php`:

```php
    public function update(Siswa $siswa, StoreCatatanWaliKelasRequest $request): RedirectResponse
    {
        $guru = $request->user()->guru;
        abort_if($guru === null, 403);
        abort_unless($siswa->kelas && $siswa->kelas->wali_kelas_guru_id === $guru->id, 403);

        $semester = Semester::find($request->validated('semester_id'));
        abort_if($semester === null || $semester->tahun_ajaran_id !== $siswa->kelas->tahun_ajaran_id, 404);

        $this->simpanCatatanWaliKelasAction->execute(
            CatatanWaliKelasData::fromArray([...$request->validated(), 'siswa_id' => $siswa->id])
        );

        $nextSiswaId = $request->input('next_siswa_id');
        if ($nextSiswaId) {
            return redirect()
                ->route('guru.rapor.catatan.edit', ['siswa' => $nextSiswaId, 'semester_id' => $request->input('semester_id')])
                ->with('success', 'Catatan wali kelas berhasil disimpan.');
        }

        return redirect()
            ->route('guru.rapor.catatan.index', ['kelas_id' => $siswa->kelas_id, 'semester_id' => $request->input('semester_id')])
            ->with('success', 'Catatan wali kelas berhasil disimpan.');
    }
```

- [x] **Step 5: Jalankan seluruh file test dan pastikan semua PASS**

Run: `php artisan test tests/Feature/Guru/RaporControllerTest.php --compact`
Expected: PASS untuk seluruh test di file ini (baseline + 1 test baru), TERMASUK test existing `saves catatan wali kelas via update and redirects back to the index` (baris 127-143) dan `redirects to the next siswa edit page when next_siswa_id is submitted` (baris 145-155) yang HARUS tetap PASS tanpa modifikasi — keduanya memakai `$semester` dari `siapkanWaliKelasUntukRapor()` yang satu tahun ajaran dengan kelas, jadi tidak boleh terpengaruh guard baru.

Jika ada test lain yang FAIL, laporkan sebagai temuan BLOCKED.

- [x] **Step 6: Jalankan Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [x] **Step 7: Commit**

```bash
git add app/Http/Controllers/Guru/RaporController.php tests/Feature/Guru/RaporControllerTest.php
git commit -m "fix(akademik): cross-check semester vs tahun ajaran kelas pada update catatan wali kelas"
```

---

## Self-Review

**1. Spec coverage:**
- Temuan 1 (§2 Fix Temuan 1: mirror `resolveLembagaId()`) → Task 1 Step 4. ✅
- Temuan 2 (§2 Fix Temuan 2: 1 abort_if di update()) → Task 2 Step 4. ✅
- §3 Non-Goals (tidak ubah CreatePolaJamAction/AssignKelasToPolaJamAction/JamPelajaranController, tidak ubah StoreCatatanWaliKelasRequest rules, tidak ubah Approve/VerifyPengajuanRaporAction, tidak ada data backfill/migration) → tidak ada task yang menyentuh area-area itu. ✅
- §4.1 Regresi wajib → Task 1 Step 5, Task 2 Step 5. ✅
- §4.2/§4.3 (Temuan 1 reproduksi + regresi negatif yayasan) → Task 1 Step 2 (4 test: 1 diperkuat + 3 baru mencakup lembaga biasa sukses+terlihat, yayasan sukses, yayasan tanpa active_lembaga_id ditolak). ✅
- §4.4/§4.5 (Temuan 2 reproduksi + regresi negatif) → Task 2 Step 2 (1 test baru) + Task 2 Step 5 eksplisit menyebut 2 test existing yang harus tetap PASS sebagai bukti regresi negatif. ✅
- §5 Ringkasan file → cocok dengan Task 1 & 2 Files. ✅

**2. Placeholder scan:** Tidak ada TBD/TODO. Semua kode test dan implementasi lengkap.

**3. Type consistency:** `PolaJamController::store()` dan `Guru\RaporController::update()` signature tidak berubah di seluruh plan. `PolaJamData(nama: ..., lembagaId: ...)` dipakai identik dengan konstruktor existing.

---

## Konteks Tambahan untuk Kickoff

- Task 1 dan Task 2 independen sepenuhnya (file berbeda, tidak saling bergantung) — bisa dikerjakan dalam urutan apapun, plan ini menulis berurutan untuk kejelasan commit history.
- Referensi pola yang di-mirror di Task 1: `app/Http/Controllers/Admin/GuruController.php:181-188` (`resolveLembagaId()`).
- Referensi pola yang di-mirror di Task 2: `app/Http/Controllers/Guru/RaporController.php` method `edit()`/`generateNarasi()`/`cetak()` (baris-baris yang sudah punya `abort_if($semester->tahun_ajaran_id !== $siswa->kelas->tahun_ajaran_id, 404)` dari fix sebelumnya).
- Temuan #3 dari audit yang sama (waka kurikulum level yayasan tidak bisa approve/verify rapor, di `ApprovePengajuanRaporAction`/`VerifyPengajuanRaporAction`) SENGAJA TIDAK termasuk dalam plan ini — user memisahkannya sebagai item terpisah. JANGAN mengerjakannya sebagai bagian dari plan ini meski tergoda karena file-nya berdekatan.
