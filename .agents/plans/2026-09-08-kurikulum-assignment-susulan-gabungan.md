# Susulan Gabungan (3 Spec) — Menu Kurikulum Assignment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Gabungkan 3 spec susulan Kurikulum Assignment jadi 1 plan berurutan: (A) relasi `tahunAjaran` yang ke-scope diam-diam oleh `TenantScope` di 3 titik, (B) kunci field "Bentuk Pendidikan" ke `bentuk_pendidikan` lembaga untuk non-platform, (C) restyle tabel index sesuai konvensi modern (Mata Pelajaran).

**Architecture:** SEMUA perubahan di `app/Http/Controllers/Admin/KurikulumAssignmentController.php` (4 method: `index()`, `edit()`, `tahunAjaranListForScope()`, `store()`, `update()`) dan 2 view file (`_form.blade.php`, `index.blade.php`). `TenantScope` itu sendiri, `StoreKurikulumAssignmentRequest`/`UpdateKurikulumAssignmentRequest`, model, `KurikulumAssignmentResolver`, `CreateKelasAction` TIDAK disentuh sama sekali.

**Tech Stack:** Laravel 12, Blade, Pest (function-style test).

## Global Constraints

- `TenantScope` itu sendiri TIDAK diubah — semua fix pakai pola `withoutGlobalScope(TenantScope::class)` di titik query/relasi spesifik, pola yang SUDAH established di codebase ini (`KasusAksesLogController`, `KasusTerhapusController`, `DashboardController`).
- `Lembaga` relation (`$a->lembaga`, `$assignment->lembaga`) TIDAK PERNAH butuh bypass — model `Lembaga` TIDAK memakai `BelongsToTenant`/`TenantScope` sama sekali. HANYA relasi/query ke `TahunAjaran` yang butuh bypass.
- **Untuk aktor PLATFORM-scope, field "Bentuk Pendidikan" TETAP dropdown bebas** — di CREATE maupun EDIT. HANYA non-platform (yayasan/lembaga-scope) yang dikunci.
- Nilai `bentuk_pendidikan` yang BENAR-BENAR TERSIMPAN untuk non-platform WAJIB dihitung ulang SERVER-SIDE dari `bentuk_pendidikan` lembaga terkait — TIDAK BOLEH mempercayai nilai dari request/hidden-input begitu saja (defense-in-depth).
- `StoreKurikulumAssignmentRequest`/`UpdateKurikulumAssignmentRequest` TIDAK diubah — hidden input di view memenuhi validasi `required` yang sudah ada, TIDAK perlu mengubah rule jadi `nullable`.
- Restyle tabel (Task 5) MURNI markup — mekanisme form Hapus (`POST` + `onsubmit="return confirm(...)"`) TIDAK diubah, TIDAK ada JS/Alpine baru, TIDAK ada pagination/search ditambahkan.
- `canManageAssignment()`, `authorizeExistingAssignmentScope()` (dari spec sebelumnya, SUDAH live di kode) TIDAK disentuh oleh plan ini.

---

## Konteks File yang Sudah Ada (baca sebelum mulai)

- `app/Http/Controllers/Admin/KurikulumAssignmentController.php` — state SAAT INI (setelah spec 6-item sebelumnya) SUDAH punya `canManageAssignment()`, guard `create()`/`store()` untuk yayasan tanpa lembaga aktif, dan `$activeLembaga` dikirim ke view `create`. `Lembaga`, `TenantScope`, `TahunAjaran` SUDAH di-import.
- `resources/views/admin/kurikulum-assignment/_form.blade.php` — SUDAH direstrukturisasi (spec sebelumnya) supaya blok "Berlaku Untuk" mengecek `$assignment` SEBELUM `$isPlatform` (menutup crash edit). Field "Bentuk Pendidikan" (baris ±60-68) BELUM disentuh sama sekali sampai plan ini.
- `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php` — 3 helper existing: `actingAsKurikulumAssignmentManager(Lembaga $lembaga)`, `actingAsYayasanKurikulumManager()`, `actingAsPlatformScopeKurikulumManager()`.
- 3 spec sumber (baca urutan ini kalau perlu detail lebih dalam):
  1. `.agents/specs/2026-09-08-kurikulum-assignment-tahun-ajaran-scope-leak.md` (Task 1, 2 di plan ini)
  2. `.agents/specs/2026-09-08-kurikulum-assignment-bentuk-pendidikan-lembaga.md` (Task 3, 4)
  3. `.agents/specs/2026-09-08-kurikulum-assignment-restyle-tabel.md` (Task 5)

---

### Task 1: Bypass `TenantScope` pada Relasi `tahunAjaran` — `index()` & `edit()`

**Files:**
- Modify: `app/Http/Controllers/Admin/KurikulumAssignmentController.php`
- Test: `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`

**Interfaces:**
- Tidak ada interface baru.

- [x] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php` (di akhir file):

```php
it('shows the correct tahun ajaran name in the index for an assignment belonging to a different lembaga than the one currently active', function () {
    $managerA = actingAsYayasanKurikulumManager();
    $lembagaAktif = Lembaga::factory()->create(['yayasan_id' => $managerA->yayasan_id, 'bentuk_pendidikan' => 'TK']);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $managerA->yayasan_id, 'bentuk_pendidikan' => 'SD']);
    $taLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id, 'nama' => '2030/2031']);
    KurikulumAssignment::create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $taLain->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);
    session(['active_lembaga_id' => $lembagaAktif->id]);

    $this->actingAs($managerA)->get(route('admin.kurikulum-assignment.index'))->assertSee('2030/2031');
});

it('shows the correct tahun ajaran name on the edit page for an assignment belonging to a different lembaga than the one currently active', function () {
    $managerA = actingAsYayasanKurikulumManager();
    $lembagaAktif = Lembaga::factory()->create(['yayasan_id' => $managerA->yayasan_id, 'bentuk_pendidikan' => 'TK']);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $managerA->yayasan_id, 'bentuk_pendidikan' => 'SD']);
    $taLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id, 'nama' => '2031/2032']);
    $assignment = KurikulumAssignment::create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $taLain->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);
    session(['active_lembaga_id' => $lembagaAktif->id]);

    $this->actingAs($managerA)->get(route('admin.kurikulum-assignment.edit', $assignment))->assertSee('2031/2032');
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="different lembaga than the one currently active" --compact`
Expected: FAIL — kedua test gagal, `assertSee` tidak ketemu teks nama Tahun Ajaran (relasi `tahunAjaran` ke-scope ke `$lembagaAktif`, bukan `$lembagaLain` pemilik data sebenarnya).

- [x] **Step 3: Implementasi minimal**

Di `app/Http/Controllers/Admin/KurikulumAssignmentController.php`, ganti (method `index()`, baris ±38):

```php
$query = KurikulumAssignment::with(['lembaga', 'tahunAjaran']);
```

menjadi:

```php
$query = KurikulumAssignment::with(['lembaga', 'tahunAjaran' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)]);
```

Ganti (method `edit()`, baris ±149):

```php
'assignment' => $kurikulumAssignment->loadMissing('tahunAjaran', 'lembaga'),
```

menjadi:

```php
'assignment' => $kurikulumAssignment->loadMissing([
    'tahunAjaran' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
    'lembaga',
]),
```

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="different lembaga than the one currently active" --compact`
Expected: PASS kedua test.

- [x] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Akademik/KurikulumAssignmentControllerTest.php --compact`
Expected: PASS semua.

- [x] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/KurikulumAssignmentController.php tests/Feature/Akademik/KurikulumAssignmentControllerTest.php
git commit -m "fix(kurikulum-assignment): bypass TenantScope pada relasi tahunAjaran di index() dan edit()"
```

---

### Task 2: Bypass `TenantScope` pada `tahunAjaranListForScope()` Cabang Platform

**Files:**
- Modify: `app/Http/Controllers/Admin/KurikulumAssignmentController.php`
- Test: `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`

**Interfaces:**
- Tidak ada interface baru.

- [x] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('shows tahun ajaran options in the create dropdown for a platform-scoped actor', function () {
    $manager = actingAsPlatformScopeKurikulumManager();
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2032/2033']);

    $this->actingAs($manager)->get(route('admin.kurikulum-assignment.create'))->assertSee('2032/2033');
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="tahun ajaran options in the create dropdown for a platform" --compact`
Expected: FAIL — `tahunAjaranListForScope()`'s cabang platform mengembalikan collection kosong (ter-scope ke `WHERE lembaga_id IS NULL`, TA yang dibuat test tidak match).

- [x] **Step 3: Implementasi minimal**

Di `app/Http/Controllers/Admin/KurikulumAssignmentController.php`, ganti (method `tahunAjaranListForScope()`):

```php
if ($scope === 'platform') {
    return TahunAjaran::orderByDesc('tanggal_mulai')->get();
}
```

menjadi:

```php
if ($scope === 'platform') {
    return TahunAjaran::withoutGlobalScope(TenantScope::class)->orderByDesc('tanggal_mulai')->get();
}
```

(Cabang `yayasan` dan cabang terakhir/lembaga-scope TIDAK berubah — SUDAH benar, lihat spec sumber untuk penjelasan kenapa.)

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="tahun ajaran options in the create dropdown for a platform" --compact`
Expected: PASS.

- [x] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Akademik/KurikulumAssignmentControllerTest.php --compact`
Expected: PASS semua.

- [x] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/KurikulumAssignmentController.php tests/Feature/Akademik/KurikulumAssignmentControllerTest.php
git commit -m "fix(kurikulum-assignment): bypass TenantScope pada dropdown Tahun Ajaran untuk platform di create()"
```

---

### Task 3: Kunci Field "Bentuk Pendidikan" untuk Non-Platform (View)

**Files:**
- Modify: `resources/views/admin/kurikulum-assignment/_form.blade.php`
- Test: `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`

**Interfaces:**
- Consumes: `$activeLembaga` (mode create, SUDAH dikirim `create()`), `$assignment->lembaga` (mode edit, SUDAH ter-load).

- [x] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('shows a locked (read-only) bentuk pendidikan on the create page for a non-platform actor, not a free dropdown', function () {
    $manager = actingAsYayasanKurikulumManager();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id, 'bentuk_pendidikan' => 'SD']);
    session(['active_lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.create'))->assertOk();
    $response->assertSee('SD');
    $response->assertDontSee('<select name="bentuk_pendidikan"', false);
});

it('shows a locked (read-only) bentuk pendidikan on the edit page for a non-platform actor, not a free dropdown', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'TK']);
    $manager = actingAsKurikulumAssignmentManager($lembaga);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $assignment = KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'TK', 'tingkat' => null, 'kurikulum' => 'k13']);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.edit', $assignment))->assertOk();
    $response->assertDontSee('<select name="bentuk_pendidikan"', false);
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="locked \(read-only\) bentuk pendidikan" --compact`
Expected: FAIL — kedua test gagal, dropdown `<select name="bentuk_pendidikan"` MASIH ada untuk non-platform saat ini.

- [x] **Step 3: Implementasi minimal**

Di `resources/views/admin/kurikulum-assignment/_form.blade.php`, ganti (baris ±60-68):

```blade
<div class="sm:col-span-6">
    <x-input-label value="Bentuk Pendidikan" />
    <select name="bentuk_pendidikan" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
        @foreach ($bentukPendidikanList as $bp)
            <option value="{{ $bp->value }}" @selected($val('bentuk_pendidikan') === $bp->value)>{{ $bp->value }}</option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('bentuk_pendidikan')" class="mt-1.5" />
</div>
```

menjadi:

```blade
@if ($isPlatform ?? false)
    <div class="sm:col-span-6">
        <x-input-label value="Bentuk Pendidikan" />
        <select name="bentuk_pendidikan" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
            @foreach ($bentukPendidikanList as $bp)
                <option value="{{ $bp->value }}" @selected($val('bentuk_pendidikan') === $bp->value)>{{ $bp->value }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('bentuk_pendidikan')" class="mt-1.5" />
    </div>
@else
    @php
        $lembagaBentukPendidikan = $assignment ? $assignment->lembaga?->bentuk_pendidikan : ($activeLembaga->bentuk_pendidikan ?? null);
    @endphp
    <div class="sm:col-span-6">
        <x-input-label value="Bentuk Pendidikan" />
        <p class="mt-1.5 rounded-lg border border-gray-100 bg-gray-50 px-3.5 py-2.5 text-sm text-gray-600">{{ $lembagaBentukPendidikan }} <span class="text-gray-400">(mengikuti bentuk pendidikan lembaga, tidak bisa diubah)</span></p>
        <input type="hidden" name="bentuk_pendidikan" value="{{ $lembagaBentukPendidikan }}">
    </div>
@endif
```

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="locked \(read-only\) bentuk pendidikan" --compact`
Expected: PASS kedua test.

- [x] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Akademik/KurikulumAssignmentControllerTest.php --compact`
Expected: PASS semua — termasuk test platform create/edit (TIDAK terpengaruh, cabang `@if ($isPlatform ?? false)` TIDAK berubah).

- [x] **Step 6: Commit**

```bash
git add resources/views/admin/kurikulum-assignment/_form.blade.php tests/Feature/Akademik/KurikulumAssignmentControllerTest.php
git commit -m "feat(kurikulum-assignment): kunci field Bentuk Pendidikan ke lembaga untuk non-platform"
```

---

### Task 4: Hitung Ulang `bentuk_pendidikan` Server-Side — `store()` & `update()`

**Files:**
- Modify: `app/Http/Controllers/Admin/KurikulumAssignmentController.php`
- Test: `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`

**Interfaces:**
- Tidak ada interface baru.

- [x] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('ignores a tampered bentuk_pendidikan value on store() for a non-platform actor, forcing the lembaga\'s own value', function () {
    $managerA = actingAsYayasanKurikulumManager();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $managerA->yayasan_id, 'bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($managerA)->post(route('admin.kurikulum-assignment.store'), [
        'tahun_ajaran_id' => $ta->id,
        'bentuk_pendidikan' => 'TK',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ])->assertRedirect(route('admin.kurikulum-assignment.index'));

    $assignment = KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->first();
    expect($assignment->bentuk_pendidikan)->toBe('SD');
});

it('ignores a tampered bentuk_pendidikan value on update() for a non-platform actor, forcing the lembaga\'s own value', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $manager = actingAsKurikulumAssignmentManager($lembaga);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $assignment = KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $this->actingAs($manager)->put(route('admin.kurikulum-assignment.update', $assignment), [
        'bentuk_pendidikan' => 'TK',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ])->assertRedirect(route('admin.kurikulum-assignment.index'));

    expect($assignment->fresh()->bentuk_pendidikan)->toBe('SD');
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="ignores a tampered bentuk_pendidikan" --compact`
Expected: FAIL — kedua test gagal, `bentuk_pendidikan` yang tersimpan SAAT INI ikut nilai yang dikirim ('TK'), bukan dipaksa 'SD'.

- [x] **Step 3: Implementasi minimal — `store()`**

Di `app/Http/Controllers/Admin/KurikulumAssignmentController.php`, ganti (method `store()`):

```php
$lembagaId = $this->resolveLembagaId($request->user(), $lembagaIdDiminta);

if ($lembagaId !== null) {
    $tahunAjaranValid = TahunAjaran::withoutGlobalScope(TenantScope::class)
        ->whereKey($validated['tahun_ajaran_id'])
        ->where('lembaga_id', $lembagaId)
        ->exists();

    if (! $tahunAjaranValid) {
        return back()->withErrors(['tahun_ajaran_id' => 'Tahun ajaran yang dipilih bukan milik lembaga ini.'])->withInput();
    }
}

if (KurikulumAssignment::where('lembaga_id', $lembagaId)->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])->where('bentuk_pendidikan', $validated['bentuk_pendidikan'])->where('tingkat', $tingkat)->exists()) {
    return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada assignment kurikulum untuk kombinasi tahun ajaran, jenjang, dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
}

$action->executeCreate($request->user(), $validated['bentuk_pendidikan'], $tingkat, $validated['kurikulum'], $lembagaIdDiminta, (int) $validated['tahun_ajaran_id']);
```

menjadi:

```php
$lembagaId = $this->resolveLembagaId($request->user(), $lembagaIdDiminta);

// Non-platform: bentuk_pendidikan SELALU mengikuti bentuk_pendidikan milik lembaga tujuan --
// nilai dari hidden input form TIDAK dipercaya begitu saja, dihitung ulang di server supaya
// tidak bisa dimanipulasi lewat devtools untuk membuat kombinasi yang mustahil terpakai
// CreateKelasAction (yang selalu memakai $lembaga->bentuk_pendidikan, bukan pilihan bebas).
$bentukPendidikan = $validated['bentuk_pendidikan'];
if ($request->user()->widestScopeLevel() !== 'platform') {
    $bentukPendidikan = Lembaga::find($lembagaId)?->bentuk_pendidikan ?? $bentukPendidikan;
}

if ($lembagaId !== null) {
    $tahunAjaranValid = TahunAjaran::withoutGlobalScope(TenantScope::class)
        ->whereKey($validated['tahun_ajaran_id'])
        ->where('lembaga_id', $lembagaId)
        ->exists();

    if (! $tahunAjaranValid) {
        return back()->withErrors(['tahun_ajaran_id' => 'Tahun ajaran yang dipilih bukan milik lembaga ini.'])->withInput();
    }
}

if (KurikulumAssignment::where('lembaga_id', $lembagaId)->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])->where('bentuk_pendidikan', $bentukPendidikan)->where('tingkat', $tingkat)->exists()) {
    return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada assignment kurikulum untuk kombinasi tahun ajaran, jenjang, dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
}

$action->executeCreate($request->user(), $bentukPendidikan, $tingkat, $validated['kurikulum'], $lembagaIdDiminta, (int) $validated['tahun_ajaran_id']);
```

- [x] **Step 4: Implementasi minimal — `update()`**

Ganti (method `update()`):

```php
$validated = $request->validated();
$tingkat = ($validated['tingkat'] ?? '') !== '' ? $validated['tingkat'] : null;

if (KurikulumAssignment::where('id', '!=', $kurikulumAssignment->id)->where('lembaga_id', $kurikulumAssignment->lembaga_id)->where('tahun_ajaran_id', $kurikulumAssignment->tahun_ajaran_id)->where('bentuk_pendidikan', $validated['bentuk_pendidikan'])->where('tingkat', $tingkat)->exists()) {
    return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada assignment kurikulum untuk kombinasi tahun ajaran, jenjang, dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
}

$action->execute($kurikulumAssignment, new KurikulumAssignmentData(
    bentukPendidikan: $validated['bentuk_pendidikan'],
    tingkat: $tingkat,
    kurikulum: $validated['kurikulum'],
    lembagaId: $kurikulumAssignment->lembaga_id,
    tahunAjaranId: $kurikulumAssignment->tahun_ajaran_id,
));
```

menjadi:

```php
$validated = $request->validated();
$tingkat = ($validated['tingkat'] ?? '') !== '' ? $validated['tingkat'] : null;

// Sama seperti store() -- non-platform TIDAK BISA mengubah bentuk_pendidikan menjauh dari
// bentuk_pendidikan lembaga pemilik assignment ini (lembaga_id sendiri immutable, dijamin
// authorizeExistingAssignmentScope() di atas method ini).
$bentukPendidikan = $validated['bentuk_pendidikan'];
if ($request->user()->widestScopeLevel() !== 'platform') {
    $bentukPendidikan = Lembaga::find($kurikulumAssignment->lembaga_id)?->bentuk_pendidikan ?? $bentukPendidikan;
}

if (KurikulumAssignment::where('id', '!=', $kurikulumAssignment->id)->where('lembaga_id', $kurikulumAssignment->lembaga_id)->where('tahun_ajaran_id', $kurikulumAssignment->tahun_ajaran_id)->where('bentuk_pendidikan', $bentukPendidikan)->where('tingkat', $tingkat)->exists()) {
    return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada assignment kurikulum untuk kombinasi tahun ajaran, jenjang, dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
}

$action->execute($kurikulumAssignment, new KurikulumAssignmentData(
    bentukPendidikan: $bentukPendidikan,
    tingkat: $tingkat,
    kurikulum: $validated['kurikulum'],
    lembagaId: $kurikulumAssignment->lembaga_id,
    tahunAjaranId: $kurikulumAssignment->tahun_ajaran_id,
));
```

- [x] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="ignores a tampered bentuk_pendidikan" --compact`
Expected: PASS kedua test.

- [x] **Step 6: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Akademik/KurikulumAssignmentControllerTest.php --compact`
Expected: PASS semua — termasuk test platform "platform BISA membuat assignment global"/"platform BISA membuat assignment untuk lembaga manapun lintas yayasan" (TIDAK terpengaruh, cabang override HANYA untuk non-platform).

- [x] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/KurikulumAssignmentController.php tests/Feature/Akademik/KurikulumAssignmentControllerTest.php
git commit -m "fix(kurikulum-assignment): hitung ulang bentuk_pendidikan server-side untuk non-platform di store() dan update()"
```

---

### Task 5: Restyle Tabel Index Sesuai Konvensi Modern

**Files:**
- Modify: `resources/views/admin/kurikulum-assignment/index.blade.php`
- Test: `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`

**Interfaces:**
- Tidak ada interface baru — murni markup, variabel yang dipakai (`$assignmentList`, `$a->canManage`, `$a->lembaga_id`, dst.) TIDAK berubah.

- [x] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('renders the restyled Aksi dropdown with explicit action labels for a manageable row', function () {
    $lembaga = Lembaga::factory()->create();
    $manager = actingAsKurikulumAssignmentManager($lembaga);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $this->actingAs($manager)->get(route('admin.kurikulum-assignment.index'))->assertOk()
        ->assertSee('Edit Assignment')
        ->assertSee('Hapus Assignment');
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="restyled Aksi dropdown" --compact`
Expected: FAIL — teks label saat ini masih "Edit"/"Hapus" polos, bukan "Edit Assignment"/"Hapus Assignment".

- [x] **Step 3: Implementasi minimal**

Di `resources/views/admin/kurikulum-assignment/index.blade.php`, ganti SELURUH blok tabel (baris ±31-86):

```blade
<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Scope</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Tahun Ajaran</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Bentuk Pendidikan</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Tingkat</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600">Kurikulum</th>
                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 bg-white">
            @forelse ($assignmentList as $a)
                <tr class="hover:bg-gray-50">
                    <td class="whitespace-nowrap px-6 py-3.5 text-sm">
                        @if ($a->lembaga_id === null)
                            <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">Platform Default</span>
                        @else
                            <span class="inline-flex rounded-full bg-purple-50 px-2.5 py-0.5 text-xs font-medium text-purple-700">{{ $a->lembaga->nama ?? 'Lembaga #' . $a->lembaga_id }}</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-6 py-3.5 text-sm text-gray-600">{{ $a->tahunAjaran->nama ?? '-' }}</td>
                    <td class="whitespace-nowrap px-6 py-3.5 text-sm font-semibold text-gray-900">{{ $a->bentuk_pendidikan }}</td>
                    <td class="whitespace-nowrap px-6 py-3.5 text-sm text-gray-600">{{ $a->tingkat ?? 'Semua Tingkat' }}</td>
                    <td class="whitespace-nowrap px-6 py-3.5 text-sm text-gray-900">{{ $a->kurikulum->label() }}</td>
                    <td class="whitespace-nowrap px-6 py-3.5 text-right text-sm">
                        @if ($a->canManage)
                            <div class="inline-flex items-center gap-2">
                                @can('kurikulum-assignment.edit')
                                    <a href="{{ route('admin.kurikulum-assignment.edit', $a) }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Edit</a>
                                @endcan
                                @can('kurikulum-assignment.delete')
                                    <form method="POST" action="{{ route('admin.kurikulum-assignment.destroy', $a) }}" onsubmit="return confirm('Hapus assignment ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-error-600 hover:text-error-700">Hapus</button>
                                    </form>
                                @endcan
                            </div>
                        @else
                            <span class="text-xs text-gray-400">Read-only (Platform)</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">Belum ada assignment kurikulum yang dikonfigurasi.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
```

menjadi:

```blade
<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Daftar Assignment Kurikulum</p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50/75 font-display text-xs font-bold uppercase tracking-wider text-gray-500">
                    <th class="sticky left-0 z-10 bg-gray-50/75 px-5 py-3 w-32">Aksi</th>
                    <th class="px-4 py-3">Scope</th>
                    <th class="px-4 py-3">Tahun Ajaran</th>
                    <th class="px-4 py-3">Bentuk Pendidikan</th>
                    <th class="px-4 py-3">Tingkat</th>
                    <th class="px-5 py-3">Kurikulum</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 font-normal">
                @forelse ($assignmentList as $a)
                    <tr class="transition-colors hover:bg-gray-50/60">
                        <td class="sticky left-0 z-10 bg-white px-5 py-3">
                            @if ($a->canManage)
                                <x-table-actions>
                                    @can('kurikulum-assignment.edit')
                                        <x-dropdown-link href="{{ route('admin.kurikulum-assignment.edit', $a) }}">
                                            <span class="inline-flex items-center gap-2.5">
                                                <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                                Edit Assignment
                                            </span>
                                        </x-dropdown-link>
                                    @endcan
                                    @can('kurikulum-assignment.delete')
                                        <form method="POST" action="{{ route('admin.kurikulum-assignment.destroy', $a) }}" onsubmit="return confirm('Hapus assignment ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-error-600 transition duration-150 ease-in-out hover:bg-error-50 focus:bg-error-50 focus:outline-none">
                                                <x-icon name="delete" class="h-4 w-4" />
                                                Hapus Assignment
                                            </button>
                                        </form>
                                    @endcan
                                </x-table-actions>
                            @else
                                <span class="text-xs text-gray-400">Read-only (Platform)</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3.5">
                            @if ($a->lembaga_id === null)
                                <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">Platform Default</span>
                            @else
                                <span class="inline-flex rounded-full bg-purple-50 px-2.5 py-0.5 text-xs font-medium text-purple-700">{{ $a->lembaga->nama ?? 'Lembaga #'.$a->lembaga_id }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3.5 text-gray-600">{{ $a->tahunAjaran->nama ?? '-' }}</td>
                        <td class="whitespace-nowrap px-4 py-3.5 font-semibold text-gray-900">{{ $a->bentuk_pendidikan }}</td>
                        <td class="whitespace-nowrap px-4 py-3.5 text-gray-600">{{ $a->tingkat ?? 'Semua Tingkat' }}</td>
                        <td class="whitespace-nowrap px-5 py-3.5 text-gray-900">{{ $a->kurikulum->label() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-gray-500">
                            <p class="text-sm">Belum ada assignment kurikulum yang dikonfigurasi.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
```

**Verifikasi penting**: `<x-table-actions>` dan `<x-dropdown-link>` SELF-CONTAINED (masing-masing punya `x-data` sendiri) — TIDAK butuh `x-data` tambahan di parent element manapun, dikonfirmasi lewat pembacaan `resources/views/components/table-actions.blade.php` dan `dropdown-link.blade.php` saat spec ditulis.

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="restyled Aksi dropdown" --compact`
Expected: PASS.

- [x] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Akademik/KurikulumAssignmentControllerTest.php --compact`
Expected: PASS semua — termasuk SEMUA test dari Task 1-4 di atas dan spec-spec sebelumnya (test yang mengecek `assertSee`/`assertDontSee` konten data, `assertViewHas`, URL route — TIDAK ADA yang berubah karena isinya, cuma pembungkus visual).

- [x] **Step 6: Commit**

```bash
git add resources/views/admin/kurikulum-assignment/index.blade.php tests/Feature/Akademik/KurikulumAssignmentControllerTest.php
git commit -m "style(kurikulum-assignment): restyle tabel index sesuai konvensi modern (pola Mata Pelajaran)"
```

---

### Task 6: Penutup — Regresi Penuh, Pint, Verifikasi Manual

**Files:**
- Tidak ada file baru — task verifikasi murni.

- [x] **Step 1: Jalankan seluruh test domain Kurikulum Assignment**

Run: `php artisan test --compact --filter="KurikulumAssignmentControllerTest|KurikulumAssignmentDestroyGuardTest|KurikulumAssignmentTest|KurikulumAssignmentResolverTest"`
Expected: PASS semua, 0 gagal.

- [x] **Step 2: Jalankan regresi modul Kelas (konsumen `KurikulumAssignmentResolver`)**

Run: `php artisan test --compact --filter="KelasCrudTest|CreateKelasActionTest"`
Expected: PASS semua, 0 gagal — bukti perubahan di 3 spec susulan ini TIDAK berdampak ke pembuatan Kelas (Task 1/2 menyentuh cara `tahunAjaran` di-eager-load, TIDAK menyentuh `KurikulumAssignmentResolver`/`CreateKelasAction` itu sendiri).

- [x] **Step 3: Jalankan Pint pada file yang diubah**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}`.

- [x] **Step 4: Verifikasi manual via browser (WAJIB)**

Login sebagai YAYASAN-scope, switch ke Lembaga A: buka index Kurikulum Assignment — baris milik Lembaga B (lembaga lain di yayasan yang sama) HARUS menampilkan nama Tahun Ajaran-nya dengan benar (bukan "-"). Klik Edit pada baris milik Lembaga B — field Tahun Ajaran di form HARUS terisi benar, field "Bentuk Pendidikan" HARUS tampil sebagai teks terkunci (bukan dropdown), sesuai `bentuk_pendidikan` Lembaga B. Klik "Tambah Assignment" — field "Bentuk Pendidikan" terkunci ke `bentuk_pendidikan` Lembaga A (lembaga aktif). Tabel index terlihat modern (kolom Aksi di kiri dengan dropdown, header uppercase).

- [x] **Step 5: Laporkan hasil**

TIDAK perlu menulis file handoff log baru di task ini — kalau user menghendaki log terpisah, itu permintaan tambahan setelah plan ini selesai.

---

## Self-Review

**1. Spec coverage** — ketiga spec sumber tercakup penuh: `tahun-ajaran-scope-leak.md` (C.1, C.2 → Task 1; C.3 → Task 2), `bentuk-pendidikan-lembaga.md` (D.1 → Task 3; D.2, D.3 → Task 4), `restyle-tabel.md` (→ Task 5).

**2. Placeholder scan** — tidak ada "TBD"/dst. Semua step berisi kode lengkap.

**3. Type consistency** — pola `withoutGlobalScope(TenantScope::class)` dipakai identik di Task 1 (2 titik) dan Task 2 (1 titik). Variabel `$bentukPendidikan` (Task 4) dipakai konsisten menggantikan `$validated['bentuk_pendidikan']` di SEMUA titik pemakaian dalam method yang sama (query duplicate-check DAN pemanggilan Action).

**Catatan tambahan hasil self-review**:
- Urutan task SENGAJA: Task 1-2 (bug data-scoping) → Task 3-4 (gap validasi bisnis) → Task 5 (restyle visual) — dari yang paling mendasar/berisiko ke yang paling kosmetik, supaya kalau ada masalah di tengah jalan, task yang paling penting (perbaikan data) sudah lebih dulu selesai & ter-commit terpisah.
- Task 5 (restyle) SENGAJA diletakkan PALING AKHIR sebelum closeout — perubahan visual murni paling aman dilakukan setelah semua perubahan LOGIC (Task 1-4) selesai dan stabil, supaya diff Task 5 di git history murni CSS/markup tanpa tercampur logic, memudahkan review terpisah kalau diperlukan.
- Task 6 Step 2 (regresi modul Kelas) tetap dipertahankan dari kickoff sebelumnya sebagai standar untuk SEMUA perubahan di controller ini — bukan cuma untuk 1 spec tertentu, karena `KurikulumAssignmentResolver`/`CreateKelasAction` adalah konsumen nyata yang harus selalu diverifikasi tidak terpengaruh.
