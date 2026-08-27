# Fix Konsistensi Ownership Tahun Ajaran vs Lembaga (Kurikulum Assignment) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menutup celah defense-in-depth: `KurikulumAssignmentController::store()` bisa menyimpan baris dengan `lembaga_id` terisi tapi `tahun_ajaran_id` milik lembaga lain, karena `StoreKurikulumAssignmentRequest` hanya validasi `exists:tahun_ajaran,id` tanpa cek ownership.

**Architecture:** Satu query tambahan di `KurikulumAssignmentController::store()`, konsisten dengan gaya inline-validation yang sudah ada di method yang sama (cek duplikat assignment). Tidak ada file baru.

**Tech Stack:** Laravel 12.68, Pest v4, MySQL.

## Global Constraints

- Invariant berbasis NILAI `lembaga_id` efektif yang akan tersimpan, BUKAN jenis aktor: `lembaga_id` terisi (dari mana pun asalnya — dipaksa controller untuk admin lembaga, atau dipilih eksplisit oleh platform/yayasan) → `tahun_ajaran_id` WAJIB milik lembaga yang sama. `lembaga_id` NULL (default nasional) → TIDAK ADA validasi ownership tambahan sama sekali.
- Ini validasi KONSISTENSI OWNERSHIP DATA, bukan perubahan otorisasi aktor — `authorize()`/`authorizeAssignmentScope()` yang sudah ada TIDAK disentuh.
- Gunakan `TahunAjaran::whereKey($id)->where('lembaga_id', $lembagaId)->exists()` (satu query gabungan) — JANGAN `TahunAjaran::find($id)` lalu cek null terpisah.
- Tidak mengubah `KurikulumAssignmentResolver`, `UpdateKurikulumAssignmentRequest`, `KurikulumAssignmentController::update()`, skema, atau migration.
- Test scoped (`tests/Feature/Akademik/KurikulumAssignmentControllerTest.php` penuh) — TIDAK PERLU full suite, fix ini sempit (1 method, 1 file produksi).
- Jalankan `vendor/bin/pint --dirty --format agent` sebelum commit.

---

### Task 1: Tambah validasi ownership `tahun_ajaran_id` vs `lembaga_id` di `store()`

**Files:**
- Modify: `app/Http/Controllers/Admin/KurikulumAssignmentController.php:58-83`
- Test: `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php` (tambah 4 test baru — baris matrix #1 sudah punya test existing)

**Interfaces:**
- Tidak ada — perubahan murni internal ke satu method controller.

- [ ] **Step 1: Baca file existing untuk konfirmasi baseline**

Baca `app/Http/Controllers/Admin/KurikulumAssignmentController.php` baris 1-84 penuh dan `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php` baris 1-111 penuh — pastikan method `store()` dan helper `actingAsKurikulumAssignmentManager()` persis seperti kutipan di plan ini. Kalau beda, STOP dan laporkan ke user.

- [ ] **Step 2: Tulis test yang gagal — matrix admin lembaga + platform/yayasan**

Tambahkan di akhir `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`. Tambah import di bagian atas file kalau belum ada: `use App\Models\Yayasan;`.

```php
function actingAsPlatformKurikulumManager(): User
{
    foreach (['kurikulum-assignment.view', 'kurikulum-assignment.create', 'kurikulum-assignment.edit', 'kurikulum-assignment.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $yayasan = Yayasan::factory()->create();
    $role = Role::firstOrCreate(['name' => 'yayasan_admin_kurikulum', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['kurikulum-assignment.view', 'kurikulum-assignment.create', 'kurikulum-assignment.edit', 'kurikulum-assignment.delete']);

    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    return $manager;
}

it('rejects a store where lembaga_id is forced to the admin lembaga but tahun_ajaran belongs to another lembaga', function () {
    $lembagaSaya = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $lembagaLain = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $taLembagaLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $manager = actingAsKurikulumAssignmentManager($lembagaSaya);

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.store'), [
        'tahun_ajaran_id' => $taLembagaLain->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ])->assertSessionHasErrors('tahun_ajaran_id');

    expect(KurikulumAssignment::where('tahun_ajaran_id', $taLembagaLain->id)->exists())->toBeFalse();
});

it('allows platform/yayasan to store with an explicit lembaga_id when tahun_ajaran matches that lembaga', function () {
    $lembagaTarget = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $taLembagaTarget = TahunAjaran::factory()->create(['lembaga_id' => $lembagaTarget->id]);
    $manager = actingAsPlatformKurikulumManager();

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.store'), [
        'lembaga_id' => $lembagaTarget->id,
        'tahun_ajaran_id' => $taLembagaTarget->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ])->assertRedirect(route('admin.kurikulum-assignment.index'));

    expect(KurikulumAssignment::where('tahun_ajaran_id', $taLembagaTarget->id)->where('lembaga_id', $lembagaTarget->id)->exists())->toBeTrue();
});

it('rejects platform/yayasan store when explicit lembaga_id does not match the chosen tahun_ajaran ownership', function () {
    $lembagaTarget = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $lembagaLain = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $taLembagaLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $manager = actingAsPlatformKurikulumManager();

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.store'), [
        'lembaga_id' => $lembagaTarget->id,
        'tahun_ajaran_id' => $taLembagaLain->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ])->assertSessionHasErrors('tahun_ajaran_id');

    expect(KurikulumAssignment::where('tahun_ajaran_id', $taLembagaLain->id)->where('lembaga_id', $lembagaTarget->id)->exists())->toBeFalse();
});

it('does not reject a platform/yayasan store for ownership when lembaga_id is left null (default nasional)', function () {
    $lembagaManaPun = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $taLembagaManaPun = TahunAjaran::factory()->create(['lembaga_id' => $lembagaManaPun->id]);
    $manager = actingAsPlatformKurikulumManager();

    $response = $this->actingAs($manager)->post(route('admin.kurikulum-assignment.store'), [
        'tahun_ajaran_id' => $taLembagaManaPun->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ]);

    $response->assertSessionDoesntHaveErrors(['tahun_ajaran_id']);
});
```

- [ ] **Step 3: Jalankan test, pastikan gagal sesuai ekspektasi**

Run: `php artisan test --filter="tahun_ajaran belongs to another lembaga|explicit lembaga_id does not match" --compact`
Expected: KEDUA test FAIL — saat ini `store()` belum menolak kombinasi ownership yang salah (bug yang sedang diperbaiki).

Run: `php artisan test --filter="matches that lembaga|left null" --compact`
Expected: KEDUA test ini sudah PASS bahkan SEBELUM fix (baseline: kombinasi valid & kasus null memang belum pernah ditolak) — konfirmasi ini regresi-negatif yang sudah hijau dari awal, bukan hasil fix.

- [ ] **Step 4: Tambah validasi ownership di `store()`**

Edit `app/Http/Controllers/Admin/KurikulumAssignmentController.php`, ubah `store()` dari:

```php
    public function store(StoreKurikulumAssignmentRequest $request, CreateKurikulumAssignmentAction $action): RedirectResponse
    {
        $this->authorize('kurikulum-assignment.create');

        $validated = $request->validated();
        $tingkat = ($validated['tingkat'] ?? '') !== '' ? $validated['tingkat'] : null;

        $isPlatformOrYayasan = $this->isPlatformOrYayasan($request);
        $lembagaId = $isPlatformOrYayasan ? ($validated['lembaga_id'] ?? null) : $request->user()->lembaga_id;

        $this->authorizeAssignmentScope($request, $lembagaId);

        if (KurikulumAssignment::where('lembaga_id', $lembagaId)->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])->where('bentuk_pendidikan', $validated['bentuk_pendidikan'])->where('tingkat', $tingkat)->exists()) {
            return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada assignment kurikulum untuk kombinasi tahun ajaran, jenjang, dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
        }
```

menjadi:

```php
    public function store(StoreKurikulumAssignmentRequest $request, CreateKurikulumAssignmentAction $action): RedirectResponse
    {
        $this->authorize('kurikulum-assignment.create');

        $validated = $request->validated();
        $tingkat = ($validated['tingkat'] ?? '') !== '' ? $validated['tingkat'] : null;

        $isPlatformOrYayasan = $this->isPlatformOrYayasan($request);
        $lembagaId = $isPlatformOrYayasan ? ($validated['lembaga_id'] ?? null) : $request->user()->lembaga_id;

        $this->authorizeAssignmentScope($request, $lembagaId);

        if ($lembagaId !== null) {
            $tahunAjaranValid = TahunAjaran::whereKey($validated['tahun_ajaran_id'])
                ->where('lembaga_id', $lembagaId)
                ->exists();

            if (! $tahunAjaranValid) {
                return back()->withErrors(['tahun_ajaran_id' => 'Tahun ajaran yang dipilih bukan milik lembaga ini.'])->withInput();
            }
        }

        if (KurikulumAssignment::where('lembaga_id', $lembagaId)->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])->where('bentuk_pendidikan', $validated['bentuk_pendidikan'])->where('tingkat', $tingkat)->exists()) {
            return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada assignment kurikulum untuk kombinasi tahun ajaran, jenjang, dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
        }
```

(`App\Models\TahunAjaran` sudah di-import di file ini — dipakai `tahunAjaranListForScope()` — tidak perlu import baru.)

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="tahun_ajaran belongs to another lembaga|explicit lembaga_id does not match|matches that lembaga|left null" --compact`
Expected: PASS, 4/4 test.

- [ ] **Step 6: Jalankan seluruh `KurikulumAssignmentControllerTest.php` supaya tidak regresi**

Run: `php artisan test tests/Feature/Akademik/KurikulumAssignmentControllerTest.php --compact`
Expected: PASS semua (7 test existing + 4 baru = 11 test), 0 failed. Test existing baris 28-41 (`'creates a kurikulum assignment'`) sudah menjadi bukti hidup untuk baris #1 matrix spec (admin lembaga + tahun ajaran lembaga sendiri → sukses) — WAJIB tetap lulus tanpa modifikasi.

- [ ] **Step 7: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/KurikulumAssignmentController.php tests/Feature/Akademik/KurikulumAssignmentControllerTest.php
git commit -m "fix(akademik): validasi konsistensi ownership tahun_ajaran vs lembaga pada kurikulum assignment"
```

- [ ] **Step 8: Catat di `PETA_PENGEMBANGAN.md`**

Tambahkan entri baru singkat (bukan sub-item Kelompok A/B/C yang sudah ditutup, dan bukan bagian dari entri Fix IDOR RPP — ini temuan Data Master terpisah dari audit yang sama): judul "Fix: Konsistensi Ownership Tahun Ajaran vs Lembaga pada Kurikulum Assignment (27 Agustus 2026)", 1-2 kalimat ringkasan, link ke spec/plan.

```bash
git add PETA_PENGEMBANGAN.md
git commit -m "docs: catat fix konsistensi ownership tahun ajaran kurikulum assignment"
```
