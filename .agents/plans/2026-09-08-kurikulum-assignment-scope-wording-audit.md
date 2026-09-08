# Perbaikan Kritis & Kejujuran Wording — Menu Kurikulum Assignment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tutup 1 crash (500) kritis untuk aktor platform-scope, 1 bug view/backend yang tidak sinkron soal siapa boleh kelola assignment global, 1 raw-abort yang tidak ramah, dan 3 gap wording — SEMUANYA di lapisan Controller + Blade + test file `KurikulumAssignmentController` sendiri, TANPA menyentuh `KurikulumAssignmentResolver`/`CreateKelasAction`/model/skema.

**Architecture:** Perubahan di `KurikulumAssignmentController` (1 helper baru `canManageAssignment()`, guard tambahan di `create()`/`store()`) dan 3 view file (`_form.blade.php`, `create.blade.php`, `index.blade.php`). `edit.blade.php`, `KurikulumAssignmentResolver`, `AssignKurikulumAction`, `UpdateKurikulumAssignmentAction`, model, dan skema TIDAK disentuh sama sekali.

**Tech Stack:** Laravel 12, Blade, Pest (function-style test).

## Global Constraints

- `KurikulumAssignmentResolver`, `CreateKelasAction`, `AssignKurikulumAction`, `UpdateKurikulumAssignmentAction`, `KurikulumAssignmentData`, model `KurikulumAssignment`, dan skema tabel TIDAK BOLEH diubah — blast radius dikonfirmasi terkurung di Controller+View+Test.
- **Kepemilikan assignment global (`lembaga_id = null`) TETAP eksklusif Platform Admin** — `authorizeExistingAssignmentScope()` (dipakai `edit()`/`update()`/`destroy()`) TIDAK diubah logikanya sama sekali, TETAP dipanggil apa adanya.
- `canManageAssignment()` (helper baru, dipakai `index()` untuk visibilitas tombol) HARUS mirror PERSIS kondisi `authorizeExistingAssignmentScope()` (return bool, bukan abort) — JANGAN memanggil `authorizeExistingAssignmentScope()` langsung (method itu melempar exception, bukan return value).
- Mode EDIT `_form.blade.php` SELALU read-only untuk field "Berlaku Untuk", untuk SEMUA scope aktor termasuk platform — TIDAK ADA cara untuk mengubah `lembaga_id` lewat form edit (backend memang sudah immutable, `edit.blade.php` TIDAK PERLU dan TIDAK BOLEH diubah untuk mengirim `lembagaList`).
- Label "Read-only (Platform)" di index TIDAK PERLU diubah — setelah `canManageAssignment()` benar, label ini otomatis selalu akurat (satu-satunya kondisi `canManage = false` untuk non-platform adalah baris global, dijamin filter query `index()`).
- TIDAK ADA badge "Semua Lembaga vs 1 Lembaga" di index (beda dari Kelas/TahunAjaran/MataPelajaran) — index Kurikulum Assignment SENGAJA selalu agregat, tidak pernah menyempit walau lembaga aktif di-switch. Badge di CREATE saja (halaman itu genuinely selalu 1 lembaga).
- Guard baru di `create()`/`store()` HANYA berlaku untuk yayasan-scope (`resolveActiveLembagaId()` untuk platform selalu lewat `$lembagaIdDiminta` terpisah; untuk lembaga-scope selalu mengembalikan `$actor->lembaga_id`, tidak pernah null) — JANGAN menambahkan guard untuk platform.

---

## Konteks File yang Sudah Ada (baca sebelum mulai)

- `app/Http/Controllers/Admin/KurikulumAssignmentController.php` — sudah `use ResolveLembagaScopeTrait;`. `resolveActiveLembagaId()` DAN `resolveLembagaId()` DUA-DUANYA dipakai di file ini untuk keperluan BEDA — `resolveLembagaId(actor, lembagaIdDiminta)` dipakai `store()` (mendukung platform memilih lembaga eksplisit), `resolveActiveLembagaId(actor)` yang BARU ditambahkan plan ini untuk guard (tidak pernah abort).
- `resources/views/admin/kurikulum-assignment/_form.blade.php` — dipakai BERSAMA oleh `create.blade.php` dan `edit.blade.php` lewat `@include`. Baris 17-38 adalah blok "Berlaku Untuk" yang jadi sumber SEMUA masalah A.1 — urutan `@if`/`@elseif`/`@else` SAAT INI mengecek `$isPlatform` lebih dulu sebelum `$assignment` (mode edit vs create), itulah akar crash.
- `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php` — Pest function-style, sudah punya 3 helper: `actingAsKurikulumAssignmentManager(Lembaga $lembaga)` (lembaga-scope), `actingAsYayasanKurikulumManager()` (yayasan-scope, TIDAK ADA parameter lembaga), `actingAsPlatformScopeKurikulumManager()` (platform-scope). 1 test existing (baris ±195, "yayasan tanpa active_lembaga_id di sesi ditolak...") WAJIB DIUBAH di Task 3, bukan ditambah baru.
- Variabel `isPlatformOrYayasan` dipakai ULANG (nama sama, KONTEKS BEDA) di `FaseDefaultMappingController`/`ResyncKurikulumFaseController` — TIDAK TERKAIT, TIDAK disentuh plan ini.

---

### Task 1: Fix Crash Halaman Edit untuk Platform-Scope (A.1 — KRITIS)

**Files:**
- Modify: `resources/views/admin/kurikulum-assignment/_form.blade.php`
- Test: `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`

**Interfaces:**
- Tidak ada interface baru — task ini murni memperbaiki urutan kondisi Blade, tidak menyentuh signature controller.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php` (di akhir file):

```php
it('renders the edit page without crashing for a platform-scoped actor viewing a global assignment', function () {
    $manager = actingAsPlatformScopeKurikulumManager();
    $ta = TahunAjaran::factory()->create();
    $assignmentGlobal = KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $this->actingAs($manager)->get(route('admin.kurikulum-assignment.edit', $assignmentGlobal))->assertOk();
});

it('renders the edit page without crashing for a platform-scoped actor viewing a lembaga-specific assignment', function () {
    $manager = actingAsPlatformScopeKurikulumManager();
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $assignment = KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $this->actingAs($manager)->get(route('admin.kurikulum-assignment.edit', $assignment))->assertOk();
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="renders the edit page without crashing" --compact`
Expected: FAIL — kedua test gagal dengan response BUKAN 200 (Laravel mengonversi `TypeError` dari `foreach()` pada `$lembagaList` yang undefined jadi HTTP 500).

- [ ] **Step 3: Implementasi minimal**

Di `resources/views/admin/kurikulum-assignment/_form.blade.php`, ganti (baris 17-38):

```blade
@if ($isPlatform ?? false)
    <div class="sm:col-span-6">
        <x-input-label value="Berlaku Untuk" />
        <select name="lembaga_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="" @selected($val('lembaga_id') === '')>— Platform (semua lembaga) —</option>
            @foreach ($lembagaList as $lembaga)
                <option value="{{ $lembaga->id }}" @selected($val('lembaga_id') == $lembaga->id)>{{ $lembaga->nama }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('lembaga_id')" class="mt-1.5" />
    </div>
@elseif (! $assignment)
    <div class="sm:col-span-6">
        <x-input-label value="Berlaku Untuk" />
        <p class="mt-1.5 rounded-lg border border-gray-100 bg-gray-50 px-3.5 py-2.5 text-sm text-gray-600">Assignment ini akan dibuat untuk lembaga yang sedang aktif di sesi Anda.</p>
    </div>
@else
    <div class="sm:col-span-6">
        <x-input-label value="Berlaku Untuk" />
        <p class="mt-1.5 rounded-lg border border-gray-100 bg-gray-50 px-3.5 py-2.5 text-sm text-gray-600">{{ $assignment->lembaga?->nama ?? '— Global (semua lembaga) —' }}</p>
    </div>
@endif
```

menjadi:

```blade
@if ($assignment)
    {{-- Mode edit: lembaga_id immutable setelah dibuat (UpdateKurikulumAssignmentAction selalu
         pakai nilai lama), jadi SELALU read-only untuk SEMUA scope aktor termasuk platform --
         tidak ada gunanya (dan menyesatkan) menampilkan dropdown yang bisa diklik tapi diabaikan. --}}
    <div class="sm:col-span-6">
        <x-input-label value="Berlaku Untuk" />
        <p class="mt-1.5 rounded-lg border border-gray-100 bg-gray-50 px-3.5 py-2.5 text-sm text-gray-600">{{ $assignment->lembaga?->nama ?? '— Global (Platform Default) —' }} <span class="text-gray-400">(tidak bisa diubah setelah dibuat)</span></p>
    </div>
@elseif ($isPlatform ?? false)
    <div class="sm:col-span-6">
        <x-input-label value="Berlaku Untuk" />
        <select name="lembaga_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="" @selected($val('lembaga_id') === '')>— Platform (semua lembaga) —</option>
            @foreach ($lembagaList as $lembaga)
                <option value="{{ $lembaga->id }}" @selected($val('lembaga_id') == $lembaga->id)>{{ $lembaga->nama }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('lembaga_id')" class="mt-1.5" />
    </div>
@else
    <div class="sm:col-span-6">
        <x-input-label value="Berlaku Untuk" />
        <p class="mt-1.5 rounded-lg border border-gray-100 bg-gray-50 px-3.5 py-2.5 text-sm text-gray-600">Assignment ini akan dibuat untuk lembaga yang sedang aktif di sesi Anda.</p>
    </div>
@endif
```

**Catatan**: teks di cabang `@else` terakhir (mode create, non-platform) SENGAJA BELUM diubah di task ini (tetap "lembaga yang sedang aktif di sesi Anda") — akan disempurnakan jadi menyebut nama lembaga eksplisit di Task 4, SETELAH controller `create()` mengirim variabel `$activeLembaga`. Task ini FOKUS TUNGGAL menutup crash secepat mungkin.

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="renders the edit page without crashing" --compact`
Expected: PASS kedua test.

- [ ] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Akademik/KurikulumAssignmentControllerTest.php --compact`
Expected: PASS semua — termasuk test-test `create()`/`store()` existing untuk platform (baris ±209, ±224) yang TIDAK terpengaruh perubahan ini (mode create untuk platform TIDAK diubah).

- [ ] **Step 6: Commit**

```bash
git add resources/views/admin/kurikulum-assignment/_form.blade.php tests/Feature/Akademik/KurikulumAssignmentControllerTest.php
git commit -m "fix(kurikulum-assignment): tutup crash 500 halaman edit untuk aktor platform-scope"
```

---

### Task 2: Sinkronkan `$canManage` dengan Backend (A.2)

**Files:**
- Modify: `app/Http/Controllers/Admin/KurikulumAssignmentController.php`
- Modify: `resources/views/admin/kurikulum-assignment/index.blade.php`
- Test: `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`

**Interfaces:**
- Produces: `KurikulumAssignmentController::canManageAssignment(User $actor, ?int $existingLembagaId): bool` — private, dipakai `index()`.
- Produces: setiap item `$assignmentList` (view `index`) punya properti dinamis `->canManage` (bool).
- Produces: view `index` menerima `isYayasan` (bool) — MENGGANTI `isPlatformOrYayasan` yang dihapus.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('computes canManage correctly per assignment for a yayasan-scoped actor (false for global, true for own-yayasan lembaga)', function () {
    $managerA = actingAsYayasanKurikulumManager();
    $lembagaMilikSendiri = Lembaga::factory()->create(['yayasan_id' => $managerA->yayasan_id, 'bentuk_pendidikan' => 'SD']);
    $taSendiri = TahunAjaran::factory()->create(['lembaga_id' => $lembagaMilikSendiri->id]);
    $taGlobal = TahunAjaran::factory()->create();
    $assignmentSendiri = KurikulumAssignment::create(['lembaga_id' => $lembagaMilikSendiri->id, 'tahun_ajaran_id' => $taSendiri->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);
    $assignmentGlobal = KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $taGlobal->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $response = $this->actingAs($managerA)->get(route('admin.kurikulum-assignment.index'))->assertOk();

    $response->assertViewHas('assignmentList', function ($list) use ($assignmentSendiri, $assignmentGlobal) {
        $sendiri = $list->firstWhere('id', $assignmentSendiri->id);
        $global = $list->firstWhere('id', $assignmentGlobal->id);

        return $sendiri->canManage === true && $global->canManage === false;
    });
});

it('does not render an Edit link for a global assignment row to a yayasan-scoped actor', function () {
    $managerA = actingAsYayasanKurikulumManager();
    $ta = TahunAjaran::factory()->create();
    $assignmentGlobal = KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $response = $this->actingAs($managerA)->get(route('admin.kurikulum-assignment.index'))->assertOk();

    $response->assertDontSee(route('admin.kurikulum-assignment.edit', $assignmentGlobal), false);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="computes canManage correctly|does not render an Edit link for a global assignment" --compact`
Expected: FAIL — test pertama gagal (`canManage` belum jadi properti assignment, `firstWhere` mengembalikan objek tanpa `canManage`, perbandingan `=== true`/`=== false` gagal); test kedua gagal (link Edit saat ini MUNCUL untuk yayasan-scope di baris global, sesuai bug yang ditemukan).

- [ ] **Step 3: Implementasi minimal — controller**

Di `app/Http/Controllers/Admin/KurikulumAssignmentController.php`, ganti method `index()`:

```php
public function index(Request $request): View
{
    $this->authorize('kurikulum-assignment.view');

    $scope = $request->user()->widestScopeLevel();
    $query = KurikulumAssignment::with(['lembaga', 'tahunAjaran']);

    if ($scope === 'yayasan') {
        $lembagaIds = Lembaga::where('yayasan_id', $request->user()->yayasan_id)->pluck('id');
        $query->where(function ($q) use ($lembagaIds) {
            $q->whereNull('lembaga_id')->orWhereIn('lembaga_id', $lembagaIds);
        });
    } elseif ($scope !== 'platform') {
        $query->where(function ($q) use ($request) {
            $q->whereNull('lembaga_id')->orWhere('lembaga_id', $request->user()->lembaga_id);
        });
    }

    return view('admin.kurikulum-assignment.index', [
        'assignmentList' => $query->orderByDesc('tahun_ajaran_id')->orderBy('bentuk_pendidikan')->orderByRaw('tingkat IS NULL')->orderBy('tingkat')->get(),
        'isPlatformOrYayasan' => in_array($scope, ['platform', 'yayasan'], true),
    ]);
}
```

menjadi:

```php
public function index(Request $request): View
{
    $this->authorize('kurikulum-assignment.view');

    $actor = $request->user();
    $scope = $actor->widestScopeLevel();
    $query = KurikulumAssignment::with(['lembaga', 'tahunAjaran']);

    if ($scope === 'yayasan') {
        $lembagaIds = Lembaga::where('yayasan_id', $actor->yayasan_id)->pluck('id');
        $query->where(function ($q) use ($lembagaIds) {
            $q->whereNull('lembaga_id')->orWhereIn('lembaga_id', $lembagaIds);
        });
    } elseif ($scope !== 'platform') {
        $query->where(function ($q) use ($actor) {
            $q->whereNull('lembaga_id')->orWhere('lembaga_id', $actor->lembaga_id);
        });
    }

    $assignmentList = $query->orderByDesc('tahun_ajaran_id')->orderBy('bentuk_pendidikan')->orderByRaw('tingkat IS NULL')->orderBy('tingkat')->get()
        ->each(function (KurikulumAssignment $assignment) use ($actor) {
            $assignment->canManage = $this->canManageAssignment($actor, $assignment->lembaga_id);
        });

    return view('admin.kurikulum-assignment.index', [
        'assignmentList' => $assignmentList,
        'isYayasan' => $scope === 'yayasan',
    ]);
}

/**
 * Mirror PERSIS kondisi authorizeExistingAssignmentScope() TAPI return bool alih-alih abort --
 * dipakai index() untuk visibilitas tombol Edit/Hapus, BUKAN pengganti authorizeExistingAssignmentScope()
 * yang tetap dipanggil apa adanya oleh edit()/update()/destroy().
 */
private function canManageAssignment(User $actor, ?int $existingLembagaId): bool
{
    if ($actor->widestScopeLevel() === 'platform') {
        return true;
    }

    if ($existingLembagaId === null) {
        return false;
    }

    if ($actor->widestScopeLevel() === 'yayasan') {
        return Lembaga::where('id', $existingLembagaId)->where('yayasan_id', $actor->yayasan_id)->exists();
    }

    return $existingLembagaId === $actor->lembaga_id;
}
```

- [ ] **Step 4: Implementasi minimal — view**

Di `resources/views/admin/kurikulum-assignment/index.blade.php`, ganti (baris 57-61):

```blade
<td class="whitespace-nowrap px-6 py-3.5 text-right text-sm">
    @php
        $canManage = $isPlatformOrYayasan || ($a->lembaga_id !== null && $a->lembaga_id === auth()->user()->lembaga_id);
    @endphp
    @if ($canManage)
```

menjadi:

```blade
<td class="whitespace-nowrap px-6 py-3.5 text-right text-sm">
    @if ($a->canManage)
```

(Sisa isi `@if`/`@else` DI BAWAH baris ini TIDAK BERUBAH — termasuk teks "Read-only (Platform)", TIDAK PERLU disentuh.)

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="computes canManage correctly|does not render an Edit link for a global assignment" --compact`
Expected: PASS kedua test.

- [ ] **Step 6: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Akademik/KurikulumAssignmentControllerTest.php --compact`
Expected: PASS semua — termasuk test baris ±267 ("platform TETAP lihat SEMUA assignment lintas yayasan") dan ±285 ("yayasan cuma lihat assignment global + milik yayasannya sendiri") yang memakai `assertViewHas('assignmentList', fn ($list) => $list->contains('id', ...))` — method `contains()` TETAP berfungsi normal pada collection yang sudah dimutasi lewat `->each()`.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/KurikulumAssignmentController.php resources/views/admin/kurikulum-assignment/index.blade.php tests/Feature/Akademik/KurikulumAssignmentControllerTest.php
git commit -m "fix(kurikulum-assignment): sinkronkan canManage index dengan authorizeExistingAssignmentScope()"
```

---

### Task 3: `store()` Guard Ramah, Bukan `abort(422)` Mentah (A.3)

**Files:**
- Modify: `app/Http/Controllers/Admin/KurikulumAssignmentController.php`
- Modify: `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`

**Interfaces:**
- Tidak ada interface baru.

- [ ] **Step 1: Ubah test existing supaya gagal terhadap kode lama**

Di `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`, cari test (baris ±195):

```php
it('yayasan tanpa active_lembaga_id di sesi ditolak dengan pesan jelas saat membuat assignment', function () {
    $manager = actingAsYayasanKurikulumManager();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id, 'bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    session()->forget('active_lembaga_id');

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.store'), [
        'tahun_ajaran_id' => $ta->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ])->assertStatus(422);
});
```

Ganti SELURUHNYA jadi:

```php
it('yayasan tanpa active_lembaga_id di sesi ditolak dengan pesan jelas saat membuat assignment', function () {
    $manager = actingAsYayasanKurikulumManager();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id, 'bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    session()->forget('active_lembaga_id');

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.store'), [
        'tahun_ajaran_id' => $ta->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ])->assertRedirect()->assertSessionHasErrors('lembaga_id');

    expect(KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->exists())->toBeFalse();
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="yayasan tanpa active_lembaga_id di sesi ditolak" --compact`
Expected: FAIL — `store()` saat ini melempar `abort(422)` mentah (bukan redirect dengan session errors), `assertRedirect()` gagal.

- [ ] **Step 3: Implementasi minimal**

Di `app/Http/Controllers/Admin/KurikulumAssignmentController.php`, ganti (di method `store()`):

```php
$validated = $request->validated();
$tingkat = ($validated['tingkat'] ?? '') !== '' ? $validated['tingkat'] : null;
$lembagaIdDiminta = $request->user()->widestScopeLevel() === 'platform' ? ($validated['lembaga_id'] ?? null) : null;
$lembagaId = $this->resolveLembagaId($request->user(), $lembagaIdDiminta);
```

menjadi:

```php
$validated = $request->validated();
$tingkat = ($validated['tingkat'] ?? '') !== '' ? $validated['tingkat'] : null;
$lembagaIdDiminta = $request->user()->widestScopeLevel() === 'platform' ? ($validated['lembaga_id'] ?? null) : null;

if ($request->user()->widestScopeLevel() === 'yayasan' && $this->resolveActiveLembagaId($request->user()) === null) {
    return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah assignment kurikulum.'])->withInput();
}

$lembagaId = $this->resolveLembagaId($request->user(), $lembagaIdDiminta);
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="yayasan tanpa active_lembaga_id di sesi ditolak" --compact`
Expected: PASS.

- [ ] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Akademik/KurikulumAssignmentControllerTest.php --compact`
Expected: PASS semua — termasuk test baris ±158 ("memakai session active_lembaga_id...") dan ±177 ("menolak yayasan membuat assignment global...") yang TIDAK terpengaruh (keduanya punya `active_lembaga_id` valid di session, guard baru TIDAK PERNAH ter-trigger untuk kasus itu).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/KurikulumAssignmentController.php tests/Feature/Akademik/KurikulumAssignmentControllerTest.php
git commit -m "fix(kurikulum-assignment): store() redirect ramah, bukan abort(422) mentah, saat lembaga aktif kosong"
```

---

### Task 4: Guard `create()` + Badge & Nama Lembaga (B.1 & B.2)

**Files:**
- Modify: `app/Http/Controllers/Admin/KurikulumAssignmentController.php`
- Modify: `resources/views/admin/kurikulum-assignment/create.blade.php`
- Modify: `resources/views/admin/kurikulum-assignment/_form.blade.php`
- Test: `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`

**Interfaces:**
- Produces: view `create` menerima `activeLembaga` (`?Lembaga`) — `null` untuk platform, WAJIB non-null untuk non-platform (dijamin guard baru).

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('redirects back to index when a yayasan-scoped actor opens create without an active lembaga', function () {
    $manager = actingAsYayasanKurikulumManager();

    $this->actingAs($manager)->get(route('admin.kurikulum-assignment.create'))
        ->assertRedirect(route('admin.kurikulum-assignment.index'))
        ->assertSessionHasErrors('lembaga_id');
});

it('shows the active lembaga name on the create page for a yayasan-scoped actor', function () {
    $manager = actingAsYayasanKurikulumManager();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id, 'nama' => 'SMA Bintang Persada']);
    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->get(route('admin.kurikulum-assignment.create'))->assertSee('SMA Bintang Persada');
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="opens create without an active lembaga|active lembaga name on the create page" --compact`
Expected: FAIL — test pertama gagal (`create()` saat ini SELALU `assertOk()`, tidak pernah redirect); test kedua gagal (belum ada teks nama lembaga di halaman create).

- [ ] **Step 3: Implementasi minimal — controller**

Ganti method `create()`:

```php
public function create(Request $request): View
{
    $this->authorize('kurikulum-assignment.create');

    $isPlatform = $request->user()->widestScopeLevel() === 'platform';

    return view('admin.kurikulum-assignment.create', [
        'kurikulumList' => KurikulumFramework::cases(),
        'bentukPendidikanList' => BentukPendidikan::cases(),
        'tahunAjaranList' => $this->tahunAjaranListForScope($request),
        'lembagaList' => $isPlatform ? Lembaga::orderBy('nama')->get() : collect(),
        'isPlatform' => $isPlatform,
    ]);
}
```

menjadi:

```php
public function create(Request $request): View|RedirectResponse
{
    $this->authorize('kurikulum-assignment.create');

    $isPlatform = $request->user()->widestScopeLevel() === 'platform';

    if (! $isPlatform && $this->resolveActiveLembagaId($request->user()) === null) {
        return redirect()->route('admin.kurikulum-assignment.index')
            ->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah assignment kurikulum.']);
    }

    $activeLembagaId = $isPlatform ? null : $this->resolveActiveLembagaId($request->user());

    return view('admin.kurikulum-assignment.create', [
        'kurikulumList' => KurikulumFramework::cases(),
        'bentukPendidikanList' => BentukPendidikan::cases(),
        'tahunAjaranList' => $this->tahunAjaranListForScope($request),
        'lembagaList' => $isPlatform ? Lembaga::orderBy('nama')->get() : collect(),
        'isPlatform' => $isPlatform,
        'activeLembaga' => $activeLembagaId ? Lembaga::find($activeLembagaId) : null,
    ]);
}
```

- [ ] **Step 4: Implementasi minimal — badge di header create**

Di `resources/views/admin/kurikulum-assignment/create.blade.php`, ganti (baris 7):

```blade
<h1 class="font-display text-lg font-bold text-gray-900">Tambah Assignment Kurikulum</h1>
```

menjadi:

```blade
<div class="flex flex-wrap items-center gap-2.5">
    <h1 class="font-display text-lg font-bold text-gray-900">Tambah Assignment Kurikulum</h1>
    @if (! ($isPlatform ?? false))
        <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
            <x-icon name="apartment" class="h-3.5 w-3.5" />
            {{ $activeLembaga->nama }}
        </span>
    @endif
</div>
```

- [ ] **Step 5: Implementasi minimal — sebut nama lembaga di teks _form.blade.php**

Di `resources/views/admin/kurikulum-assignment/_form.blade.php` (struktur SUDAH direstrukturisasi Task 1), ganti cabang `@else` terakhir:

```blade
@else
    <div class="sm:col-span-6">
        <x-input-label value="Berlaku Untuk" />
        <p class="mt-1.5 rounded-lg border border-gray-100 bg-gray-50 px-3.5 py-2.5 text-sm text-gray-600">Assignment ini akan dibuat untuk lembaga yang sedang aktif di sesi Anda.</p>
    </div>
@endif
```

menjadi:

```blade
@else
    <div class="sm:col-span-6">
        <x-input-label value="Berlaku Untuk" />
        <p class="mt-1.5 rounded-lg border border-gray-100 bg-gray-50 px-3.5 py-2.5 text-sm text-gray-600">Assignment ini akan dibuat untuk lembaga aktif Anda saat ini: <strong class="font-semibold text-gray-900">{{ $activeLembaga->nama }}</strong>.</p>
    </div>
@endif
```

(`$activeLembaga` otomatis tersedia di `_form.blade.php` lewat `@include` tanpa perlu diteruskan eksplisit di `create.blade.php` — Blade `@include` mewarisi SELURUH variabel view parent.)

- [ ] **Step 6: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="opens create without an active lembaga|active lembaga name on the create page" --compact`
Expected: PASS kedua test.

- [ ] **Step 7: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Akademik/KurikulumAssignmentControllerTest.php --compact`
Expected: PASS semua — termasuk test `create()`/`store()` platform (TIDAK terpengaruh, guard hanya untuk yayasan-scope) dan test lembaga-scope (`resolveActiveLembagaId()` untuk mereka SELALU `$actor->lembaga_id`, tidak pernah null, guard tidak pernah ter-trigger).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/KurikulumAssignmentController.php resources/views/admin/kurikulum-assignment/create.blade.php resources/views/admin/kurikulum-assignment/_form.blade.php tests/Feature/Akademik/KurikulumAssignmentControllerTest.php
git commit -m "feat(kurikulum-assignment): guard create() + badge dan nama lembaga eksplisit"
```

---

### Task 5: Catatan Statis — Index Selalu Agregat (B.3)

**Files:**
- Modify: `resources/views/admin/kurikulum-assignment/index.blade.php`
- Test: `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`

**Interfaces:**
- Consumes: `$isYayasan` dari Task 2.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('shows a static note explaining the index never narrows by active lembaga for a yayasan-scoped actor', function () {
    $manager = actingAsYayasanKurikulumManager();

    $this->actingAs($manager)->get(route('admin.kurikulum-assignment.index'))
        ->assertSee('tidak menyempit walau Anda mengganti lembaga aktif', false);
});

it('does not show the aggregate note for a lembaga-scoped actor', function () {
    $lembaga = Lembaga::factory()->create();
    $manager = actingAsKurikulumAssignmentManager($lembaga);

    $this->actingAs($manager)->get(route('admin.kurikulum-assignment.index'))
        ->assertDontSee('tidak menyempit walau Anda mengganti lembaga aktif', false);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="static note explaining the index|does not show the aggregate note" --compact`
Expected: FAIL — test pertama gagal (catatan belum ada), test kedua kemungkinan sudah PASS kebetulan (catatan memang belum ada sama sekali) — normal, fokus ke test pertama.

- [ ] **Step 3: Implementasi minimal**

Di `resources/views/admin/kurikulum-assignment/index.blade.php`, ganti (baris 11-15):

```blade
<div>
    <h1 class="font-display text-lg font-bold text-gray-900">Pengaturan Kurikulum</h1>
    <p class="text-xs text-gray-500">Kurikulum yang berlaku per jenjang, tingkat, dan tahun ajaran. Kelas baru mengikuti ini otomatis saat dibuat.</p>
</div>
```

menjadi:

```blade
<div>
    <h1 class="font-display text-lg font-bold text-gray-900">Pengaturan Kurikulum</h1>
    <p class="text-xs text-gray-500">Kurikulum yang berlaku per jenjang, tingkat, dan tahun ajaran. Kelas baru mengikuti ini otomatis saat dibuat.</p>
    @if ($isYayasan ?? false)
        <p class="mt-1 text-xs text-gray-400">Daftar ini selalu menampilkan SEMUA lembaga di yayasan Anda beserta assignment global — tidak menyempit walau Anda mengganti lembaga aktif lewat pengalih lembaga di pojok kanan atas.</p>
    @endif
</div>
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="static note explaining the index|does not show the aggregate note" --compact`
Expected: PASS kedua test.

- [ ] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Akademik/KurikulumAssignmentControllerTest.php --compact`
Expected: PASS semua.

- [ ] **Step 6: Commit**

```bash
git add resources/views/admin/kurikulum-assignment/index.blade.php tests/Feature/Akademik/KurikulumAssignmentControllerTest.php
git commit -m "feat(kurikulum-assignment): catatan statis index selalu agregat untuk yayasan-scope"
```

---

### Task 6: Penutup — Regresi Penuh & Pint

**Files:**
- Tidak ada file baru — task verifikasi murni.

- [ ] **Step 1: Jalankan seluruh test domain Kurikulum Assignment**

Run: `php artisan test --compact --filter="KurikulumAssignmentControllerTest|KurikulumAssignmentDestroyGuardTest|KurikulumAssignmentTest|KurikulumAssignmentResolverTest"`
Expected: PASS semua, 0 gagal. **Perhatikan KHUSUS**: `KurikulumAssignmentResolverTest` (Unit) HARUS tetap hijau TANPA satu pun perubahan perilaku — ini bukti langsung blast-radius benar-benar terkurung di Controller+View, tidak merembet ke Resolver.

- [ ] **Step 2: Jalankan regresi modul Kelas (konsumen `KurikulumAssignmentResolver`)**

Run: `php artisan test --compact --filter="KelasCrudTest|CreateKelasActionTest"`
Expected: PASS semua, 0 gagal — bukti tambahan bahwa perubahan di Kurikulum Assignment TIDAK berdampak ke pembuatan Kelas.

- [ ] **Step 3: Jalankan Pint pada file yang diubah**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}`.

- [ ] **Step 4: Verifikasi manual cepat via browser (WAJIB, bukan opsional — Task 1 menutup crash nyata)**

Login sebagai PLATFORM-scope: buka index Kurikulum Assignment, klik Edit pada baris "Platform Default" (global) — HARUS terbuka normal (sebelumnya 500). Klik Edit pada baris lembaga-spesifik manapun — HARUS terbuka normal juga, field "Berlaku Untuk" tampil sebagai teks read-only (bukan dropdown). Login sebagai YAYASAN-scope: buka index — baris "Platform Default" TIDAK ADA tombol Edit/Hapus (cuma "Read-only (Platform)"), lihat catatan statis "Daftar ini selalu menampilkan SEMUA lembaga...". Klik "Tambah Assignment" TANPA switch lembaga dulu → redirect balik ke index dengan error. Switch ke 1 lembaga, klik "Tambah Assignment" lagi → badge nama lembaga muncul di header, teks "Berlaku Untuk" menyebut nama lembaga eksplisit.

- [ ] **Step 5: Laporkan hasil**

TIDAK perlu menulis file handoff log baru di task ini — kalau user menghendaki log terpisah, itu permintaan tambahan setelah plan ini selesai.

---

## Self-Review

**1. Spec coverage** — semua 6 item spec `.agents/specs/2026-09-08-kurikulum-assignment-scope-wording-audit.md` tercakup: A.1 Task 1, A.2 Task 2, A.3 Task 3, B.1+B.2 Task 4, B.3 Task 5. "Di Luar Scope" spec (3-state badge, index ikut menyempit, resync.blade.php, konsistensi nama variabel `isPlatformOrYayasan` di controller lain) sengaja tidak ada task-nya.

**2. Placeholder scan** — tidak ada "TBD"/dst. Semua step berisi kode lengkap.

**3. Type consistency** — `canManageAssignment(User $actor, ?int $existingLembagaId): bool` (Task 2) mirror PERSIS parameter `authorizeExistingAssignmentScope(User $actor, ?int $existingLembagaId): void` yang SUDAH ADA — nama parameter sengaja disamakan untuk memudahkan perbandingan side-by-side saat review. `$activeLembaga` (Task 4) dipakai konsisten di `create.blade.php` dan `_form.blade.php` (lewat pewarisan `@include`).

**Catatan tambahan hasil self-review**:
- Task 1 SENGAJA TIDAK menyentuh teks "lembaga yang sedang aktif di sesi Anda" (baru diperbaiki Task 4) — supaya Task 1 tetap task PALING KECIL DAN PALING CEPAT mungkin untuk menutup crash produksi, tidak tercampur dengan perbaikan wording yang levelnya beda urgensi.
- Task 5 Step 2 test kedua ("does not show the aggregate note...") berpotensi PASS lebih awal dari yang diharapkan (dijelaskan eksplisit di step itu) — pola yang sama seperti ditemukan di plan-plan audit sebelumnya, bukan indikasi kesalahan.
- Task 6 Step 2 (regresi modul Kelas) DITAMBAHKAN SECARA KHUSUS di plan ini (tidak ada di plan-plan audit sebelumnya) — mengingat besarnya kekhawatiran user soal blast-radius di awal audit ini, verifikasi eksplisit ke konsumen `KurikulumAssignmentResolver` dianggap perlu sebagai bukti konkret, bukan cuma klaim di spec.
