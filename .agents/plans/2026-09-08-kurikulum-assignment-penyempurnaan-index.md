# Penyempurnaan Halaman Index — Menu Kurikulum Assignment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Perbaiki 5 hal di halaman index Kurikulum Assignment: index sekarang menyempit saat switch lembaga (melengkapi peniruan `TenantScope` yang sebelumnya cuma separuh), badge scope, header responsif + hierarki tombol, `$errors` akhirnya ditampilkan, dan dialog konfirmasi Hapus standar aplikasi.

**Architecture:** 1 perubahan controller (`index()`) + 1 view file (`index.blade.php`, 4 titik perubahan: header, `$errors` block, badge, form Hapus). Tidak ada file lain yang disentuh.

**Tech Stack:** Laravel 12, Blade, Alpine.js (`confirmDialog()` global helper — SUDAH ada, tidak perlu registrasi baru), Pest (function-style test).

## Global Constraints

- `resolveActiveLembagaId()` (dari `ResolveLembagaScopeTrait`, SUDAH dipakai luas di controller ini) dipakai untuk E.1 — method ini otomatis menangani validasi ulang "lembaga aktif di session masih milik yayasan aktor" (kasus stale session), TIDAK PERLU logic tambahan.
- Cabang `elseif ($scope !== 'platform')` (lembaga-scope) dan cabang platform (`$scope === 'platform'`, tanpa filter tambahan) di `index()` TIDAK BOLEH diubah — HANYA cabang `yayasan` yang mendapat percabangan baru (menyempit vs agregat).
- Catatan statis lama ("Daftar ini selalu menampilkan SEMUA lembaga...") WAJIB DIHAPUS — kalau dibiarkan, akan jadi MENYESATKAN setelah E.1 diterapkan (index BENAR-BENAR menyempit sekarang, catatan lama akan berbohong).
- `<x-link-button>` dipakai APA ADANYA (komponen SUDAH ada, 2 varian: `primary` default, `ghost` untuk aksi sekunder) — JANGAN membuat komponen/varian baru.
- `confirmDialog()` adalah helper GLOBAL (`window.confirmDialog`, `<x-confirm-dialog />` SUDAH ter-render lewat `app.blade.php`) — JANGAN menambahkan registrasi/import apa pun, cukup dipanggil langsung di `@submit.prevent`.
- Pengaman "masih dipakai Kelas" TIDAK diperluas ke assignment global di plan ini (keputusan eksplisit di spec) — HANYA teks peringatan di dialog konfirmasi yang diperkuat.

---

## Konteks File yang Sudah Ada (baca sebelum mulai)

- `app/Http/Controllers/Admin/KurikulumAssignmentController.php` — `index()` method SAAT INI (setelah 3 spec susulan sebelumnya) sudah eager-load `tahunAjaran` dengan bypass `TenantScope`, sudah punya `canManageAssignment()` per-baris, kirim `isYayasan` ke view. BELUM kirim `activeLembaga`, BELUM ada percabangan menyempit/agregat.
- `resources/views/admin/kurikulum-assignment/index.blade.php` — SUDAH direstyle (spec sebelumnya, tabel modern dengan `<x-table-actions>`). Header (baris ±11-32) dan blok pesan error (baris ±1-9) BELUM disentuh sejak awal modul ini dibuat.
- `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php` — 3 helper existing: `actingAsKurikulumAssignmentManager(Lembaga $lembaga)`, `actingAsYayasanKurikulumManager()`, `actingAsPlatformScopeKurikulumManager()`. 2 test existing PENTING untuk regresi E.1: "yayasan cuma lihat assignment global + milik yayasannya sendiri di index" dan "platform TETAP lihat SEMUA assignment lintas yayasan di index" — KEDUANYA TIDAK set `session('active_lembaga_id')`, jadi tetap masuk cabang "agregat" yang SUDAH ADA, TIDAK terpengaruh perubahan E.1.
- Pola `confirmDialog()` sudah dipakai 40+ tempat lain (cth. `resources/views/admin/tahun-ajaran/index.blade.php`) — cukup disalin, bukan pola baru.

---

### Task 1: `index()` — Menyempit Saat Switch + Kirim `activeLembaga`

**Files:**
- Modify: `app/Http/Controllers/Admin/KurikulumAssignmentController.php`
- Test: `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`

**Interfaces:**
- Produces: view `admin.kurikulum-assignment.index` menerima `activeLembaga` (`?Lembaga`) — `null` untuk platform/lembaga-scope, atau untuk yayasan-scope mode "Semua Lembaga"; berisi `Lembaga` untuk yayasan-scope yang sudah switch.

- [x] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php` (di akhir file):

```php
it('narrows the index to the active lembaga plus global assignments when a yayasan-scoped actor has switched into a lembaga', function () {
    $managerA = actingAsYayasanKurikulumManager();
    $lembagaAktif = Lembaga::factory()->create(['yayasan_id' => $managerA->yayasan_id, 'bentuk_pendidikan' => 'SD']);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $managerA->yayasan_id, 'bentuk_pendidikan' => 'TK']);
    $taAktif = TahunAjaran::factory()->create(['lembaga_id' => $lembagaAktif->id]);
    $taLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $assignmentAktif = KurikulumAssignment::create(['lembaga_id' => $lembagaAktif->id, 'tahun_ajaran_id' => $taAktif->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);
    $assignmentLain = KurikulumAssignment::create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $taLain->id, 'bentuk_pendidikan' => 'TK', 'tingkat' => null, 'kurikulum' => 'merdeka']);
    session(['active_lembaga_id' => $lembagaAktif->id]);

    $response = $this->actingAs($managerA)->get(route('admin.kurikulum-assignment.index'))->assertOk();

    $response->assertViewHas('assignmentList', function ($list) use ($assignmentAktif, $assignmentLain) {
        return $list->contains('id', $assignmentAktif->id) && ! $list->contains('id', $assignmentLain->id);
    });
});

it('still shows all own-yayasan assignments in aggregate mode (no active lembaga)', function () {
    $managerA = actingAsYayasanKurikulumManager();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $managerA->yayasan_id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $managerA->yayasan_id]);
    $taA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $taB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $assignmentA = KurikulumAssignment::create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $taA->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);
    $assignmentB = KurikulumAssignment::create(['lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $taB->id, 'bentuk_pendidikan' => 'TK', 'tingkat' => null, 'kurikulum' => 'merdeka']);

    $response = $this->actingAs($managerA)->get(route('admin.kurikulum-assignment.index'))->assertOk();

    $response->assertViewHas('assignmentList', function ($list) use ($assignmentA, $assignmentB) {
        return $list->contains('id', $assignmentA->id) && $list->contains('id', $assignmentB->id);
    });
});
```

- [x] **Step 2: Jalankan test, pastikan test pertama gagal, test kedua sudah lulus**

Run: `php artisan test --filter="narrows the index to the active lembaga|still shows all own-yayasan assignments" --compact`
Expected: test PERTAMA FAIL (index saat ini masih menampilkan `$assignmentLain` walau sudah switch — belum ada penyempitan); test KEDUA sudah PASS (perilaku agregat memang belum berubah, ini baseline regresi).

- [x] **Step 3: Implementasi minimal**

Di `app/Http/Controllers/Admin/KurikulumAssignmentController.php`, ganti method `index()`:

```php
public function index(Request $request): View
{
    $this->authorize('kurikulum-assignment.view');

    $actor = $request->user();
    $scope = $actor->widestScopeLevel();
    $query = KurikulumAssignment::with(['lembaga', 'tahunAjaran' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)]);

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
```

menjadi:

```php
public function index(Request $request): View
{
    $this->authorize('kurikulum-assignment.view');

    $actor = $request->user();
    $scope = $actor->widestScopeLevel();
    $activeLembagaId = $scope === 'yayasan' ? $this->resolveActiveLembagaId($actor) : null;
    $query = KurikulumAssignment::with(['lembaga', 'tahunAjaran' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)]);

    if ($scope === 'yayasan') {
        if ($activeLembagaId !== null) {
            $query->where(function ($q) use ($activeLembagaId) {
                $q->whereNull('lembaga_id')->orWhere('lembaga_id', $activeLembagaId);
            });
        } else {
            $lembagaIds = Lembaga::where('yayasan_id', $actor->yayasan_id)->pluck('id');
            $query->where(function ($q) use ($lembagaIds) {
                $q->whereNull('lembaga_id')->orWhereIn('lembaga_id', $lembagaIds);
            });
        }
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
        'activeLembaga' => $activeLembagaId ? Lembaga::find($activeLembagaId) : null,
    ]);
}
```

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="narrows the index to the active lembaga|still shows all own-yayasan assignments" --compact`
Expected: PASS kedua test.

- [x] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Akademik/KurikulumAssignmentControllerTest.php --compact`
Expected: PASS semua — termasuk test "platform TETAP lihat SEMUA assignment lintas yayasan" dan "yayasan cuma lihat assignment global + milik yayasannya sendiri" (KEDUANYA tidak set `active_lembaga_id`, jatuh ke cabang agregat yang tidak berubah).

- [x] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/KurikulumAssignmentController.php tests/Feature/Akademik/KurikulumAssignmentControllerTest.php
git commit -m "fix(kurikulum-assignment): index() menyempit ke lembaga aktif, melengkapi peniruan TenantScope"
```

---

### Task 2: View — Badge Scope + Header Responsif + Hierarki Tombol

**Files:**
- Modify: `resources/views/admin/kurikulum-assignment/index.blade.php`
- Test: `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`

**Interfaces:**
- Consumes: `$isYayasan`, `$activeLembaga` dari Task 1.

- [x] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('shows the active lembaga name badge in the index header when switched into a lembaga', function () {
    $managerA = actingAsYayasanKurikulumManager();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $managerA->yayasan_id, 'nama' => 'SD Cempaka Raya']);
    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($managerA)->get(route('admin.kurikulum-assignment.index'))->assertSee('SD Cempaka Raya');
});

it('shows the "Semua Lembaga" badge in aggregate mode', function () {
    $managerA = actingAsYayasanKurikulumManager();

    $this->actingAs($managerA)->get(route('admin.kurikulum-assignment.index'))->assertSee('Semua Lembaga');
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="active lembaga name badge in the index header|Semua Lembaga.*badge in aggregate mode" --compact`
Expected: FAIL — badge belum ada di header saat ini.

- [x] **Step 3: Implementasi minimal**

Di `resources/views/admin/kurikulum-assignment/index.blade.php`, ganti (baris ±11-32):

```blade
<div class="flex items-center justify-between">
    <div>
        <h1 class="font-display text-lg font-bold text-gray-900">Pengaturan Kurikulum</h1>
        <p class="text-xs text-gray-500">Kurikulum yang berlaku per jenjang, tingkat, dan tahun ajaran. Kelas baru mengikuti ini otomatis saat dibuat.</p>
        @if ($isYayasan ?? false)
            <p class="mt-1 text-xs text-gray-400">Daftar ini selalu menampilkan SEMUA lembaga di yayasan Anda beserta assignment global — tidak menyempit walau Anda mengganti lembaga aktif lewat pengalih lembaga di pojok kanan atas.</p>
        @endif
    </div>
    <div class="flex items-center gap-2">
        @can('kurikulum-assignment.view')
            <a href="{{ route('admin.kurikulum-assignment.resync') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                Cek & Perbaiki Kurikulum/Fase
            </a>
        @endcan
        @can('kurikulum-assignment.create')
            <a href="{{ route('admin.kurikulum-assignment.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                <x-icon name="plus" class="h-4 w-4" />
                Tambah Assignment
            </a>
        @endcan
    </div>
</div>
```

menjadi:

```blade
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <div class="flex flex-wrap items-center gap-2.5">
            <h1 class="font-display text-lg font-bold text-gray-900">Pengaturan Kurikulum</h1>
            @if ($isYayasan ?? false)
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
                    <x-icon name="apartment" class="h-3.5 w-3.5" />
                    {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
                </span>
            @endif
        </div>
        <p class="text-xs text-gray-500">Kurikulum yang berlaku per jenjang, tingkat, dan tahun ajaran. Kelas baru mengikuti ini otomatis saat dibuat.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        @can('kurikulum-assignment.view')
            <x-link-button href="{{ route('admin.kurikulum-assignment.resync') }}" variant="ghost">
                Cek & Perbaiki Kurikulum/Fase
            </x-link-button>
        @endcan
        @can('kurikulum-assignment.create')
            <x-link-button href="{{ route('admin.kurikulum-assignment.create') }}">
                <x-icon name="plus" class="h-4 w-4" />
                Tambah Assignment
            </x-link-button>
        @endcan
    </div>
</div>
```

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="active lembaga name badge in the index header|Semua Lembaga.*badge in aggregate mode" --compact`
Expected: PASS kedua test.

- [x] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Akademik/KurikulumAssignmentControllerTest.php --compact`
Expected: PASS semua.

- [x] **Step 6: Commit**

```bash
git add resources/views/admin/kurikulum-assignment/index.blade.php tests/Feature/Akademik/KurikulumAssignmentControllerTest.php
git commit -m "feat(kurikulum-assignment): badge scope + header responsif + hierarki tombol via x-link-button"
```

---

### Task 3: View — Tampilkan `$errors`

**Files:**
- Modify: `resources/views/admin/kurikulum-assignment/index.blade.php`
- Test: `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`

**Interfaces:**
- Tidak ada interface baru.

- [x] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('shows the validation error message when redirected to index after failing to open create without an active lembaga', function () {
    $managerA = actingAsYayasanKurikulumManager();

    $this->actingAs($managerA)->get(route('admin.kurikulum-assignment.create'));

    $this->followingRedirects()->get(route('admin.kurikulum-assignment.create'))
        ->assertSee('Pilih lembaga aktif melalui pengalih lembaga sebelum menambah assignment kurikulum.');
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="validation error message when redirected to index" --compact`
Expected: FAIL — `index.blade.php` belum menampilkan `$errors` sama sekali.

- [x] **Step 3: Implementasi minimal**

Di `resources/views/admin/kurikulum-assignment/index.blade.php`, ganti (baris ±1-9):

```blade
<x-app-layout>
    <div class="space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700">{{ session('error') }}</div>
        @endif
```

menjadi:

```blade
<x-app-layout>
    <div class="space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif
```

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="validation error message when redirected to index" --compact`
Expected: PASS.

- [x] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Akademik/KurikulumAssignmentControllerTest.php --compact`
Expected: PASS semua.

- [x] **Step 6: Commit**

```bash
git add resources/views/admin/kurikulum-assignment/index.blade.php tests/Feature/Akademik/KurikulumAssignmentControllerTest.php
git commit -m "fix(kurikulum-assignment): tampilkan pesan validasi \$errors di index (sebelumnya redirect diam-diam)"
```

---

### Task 4: View — Dialog Konfirmasi Hapus Standar

**Files:**
- Modify: `resources/views/admin/kurikulum-assignment/index.blade.php`
- Test: `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`

**Interfaces:**
- Tidak ada interface baru — `confirmDialog()` helper global SUDAH tersedia.

- [x] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('uses the standard confirmDialog() instead of native browser confirm() for the delete button', function () {
    $lembaga = Lembaga::factory()->create();
    $manager = actingAsKurikulumAssignmentManager($lembaga);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.index'))->assertOk();

    $response->assertSee('confirmDialog(', false);
    $response->assertDontSee('onsubmit="return confirm(', false);
});

it('warns explicitly about the lack of a usage guard when deleting a global assignment', function () {
    $ta = TahunAjaran::factory()->create();
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);
    $manager = actingAsPlatformScopeKurikulumManager();

    $this->actingAs($manager)->get(route('admin.kurikulum-assignment.index'))->assertOk()
        ->assertSee('PERINGATAN', false);
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="uses the standard confirmDialog|warns explicitly about the lack of a usage guard" --compact`
Expected: FAIL — form Hapus SAAT INI masih pakai `onsubmit="return confirm(...)"`, tidak ada teks "PERINGATAN" untuk baris global.

- [x] **Step 3: Implementasi minimal**

Di `resources/views/admin/kurikulum-assignment/index.blade.php`, ganti (di dalam `<x-table-actions>`, blok form Hapus):

```blade
<form method="POST" action="{{ route('admin.kurikulum-assignment.destroy', $a) }}" onsubmit="return confirm('Hapus assignment ini?')">
    @csrf
    @method('DELETE')
    <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-error-600 transition duration-150 ease-in-out hover:bg-error-50 focus:bg-error-50 focus:outline-none">
        <x-icon name="delete" class="h-4 w-4" />
        Hapus Assignment
    </button>
</form>
```

menjadi:

```blade
<form
    method="POST"
    action="{{ route('admin.kurikulum-assignment.destroy', $a) }}"
    x-data
    @submit.prevent="confirmDialog(
        'Hapus Assignment Kurikulum?',
        @js('Hapus assignment '.$a->bentuk_pendidikan.($a->tingkat ? ' tingkat '.$a->tingkat : ' (semua tingkat)').' untuk '.($a->tahunAjaran->nama ?? 'tahun ajaran ini').'?'.($a->lembaga_id === null ? ' PERINGATAN: ini assignment GLOBAL (Platform Default) — dipakai sebagai cadangan oleh lembaga mana pun yang belum punya assignment sendiri untuk kombinasi ini, dan TIDAK ADA pengecekan otomatis sebelum dihapus.' : '')),
        { confirmLabel: 'Ya, Hapus', isDanger: true }
    ).then(confirmed => { if (confirmed) $el.submit() })"
>
    @csrf
    @method('DELETE')
    <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-error-600 transition duration-150 ease-in-out hover:bg-error-50 focus:bg-error-50 focus:outline-none">
        <x-icon name="delete" class="h-4 w-4" />
        Hapus Assignment
    </button>
</form>
```

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="uses the standard confirmDialog|warns explicitly about the lack of a usage guard" --compact`
Expected: PASS kedua test.

- [x] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Akademik/KurikulumAssignmentControllerTest.php --compact`
Expected: PASS semua.

- [x] **Step 6: Commit**

```bash
git add resources/views/admin/kurikulum-assignment/index.blade.php tests/Feature/Akademik/KurikulumAssignmentControllerTest.php
git commit -m "feat(kurikulum-assignment): ganti confirm() native jadi confirmDialog() standar + peringatan assignment global"
```

---

### Task 5: Penutup — Regresi Penuh, Pint, Verifikasi Manual

**Files:**
- Tidak ada file baru — task verifikasi murni.

- [x] **Step 1: Jalankan seluruh test domain Kurikulum Assignment**

Run: `php artisan test --compact --filter="KurikulumAssignmentControllerTest|KurikulumAssignmentDestroyGuardTest|KurikulumAssignmentTest|KurikulumAssignmentResolverTest"`
Expected: PASS semua, 0 gagal.

- [x] **Step 2: Jalankan regresi modul Kelas**

Run: `php artisan test --compact --filter="KelasCrudTest|CreateKelasActionTest"`
Expected: PASS semua, 0 gagal.

- [x] **Step 3: Jalankan Pint pada file yang diubah**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}`.

- [x] **Step 4: Verifikasi manual via browser (WAJIB)**

Login sebagai YAYASAN-scope, mode "Semua Lembaga": lihat badge ungu "Semua Lembaga", header terlihat rapi (2 tombol dengan bobot visual beda — "Tambah Assignment" solid biru, "Cek & Perbaiki" outline). Switch ke 1 lembaga: badge berubah jadi nama lembaga (brand color), tabel HANYA menampilkan assignment lembaga itu + global. Klik "Tambah Assignment" TANPA lembaga aktif (switch ke "Semua Lembaga" dulu): redirect ke index DENGAN pesan error terlihat jelas (bukan diam-diam). Klik "Hapus" pada 1 baris: dialog modal standar muncul (bukan popup browser), pesan menyebutkan detail assignment; kalau baris "Platform Default", pesan ADA peringatan tambahan. Coba perkecil lebar browser (mode HP): header tidak pecah/overflow.

- [x] **Step 5: Laporkan hasil**

TIDAK perlu menulis file handoff log baru di task ini — kalau user menghendaki log terpisah, itu permintaan tambahan setelah plan ini selesai.

---

## Self-Review

**1. Spec coverage** — SEMUA 5 item spec `.agents/specs/2026-09-08-kurikulum-assignment-penyempurnaan-index.md` tercakup: E.1 → Task 1, E.2 → Task 1 (data) + Task 2 (view), E.3 → Task 2, E.4 → Task 3, E.5 → Task 4. "Di Luar Scope" spec (perluasan pengaman assignment global, `resync.blade.php`, filter tambahan) sengaja tidak ada task-nya.

**2. Placeholder scan** — tidak ada "TBD"/dst. Semua step berisi kode lengkap.

**3. Type consistency** — `activeLembaga` (`?Lembaga`) dipakai konsisten Task 1 (controller) dan Task 2 (view). Nama variabel `$isYayasan`/`$activeLembaga` SAMA PERSIS dengan pola badge yang sudah dipakai Kelas/TahunAjaran/MataPelajaran sesi-sesi sebelumnya.

**Catatan tambahan hasil self-review**:
- Task 1 Step 2 SENGAJA mencatat test KEDUA ("still shows all own-yayasan assignments...") sudah PASS SEBELUM implementasi — ini baseline regresi yang disengaja ditulis lebih dulu (bukan TDD murni untuk fitur baru), supaya kalau nanti tiba-tiba gagal setelah Step 3, pelaksana tahu itu regresi nyata bukan salah baca.
- Urutan task (index-query dulu → view/badge → error display → dialog konfirmasi) mengikuti urutan KETERGANTUNGAN data: Task 2 butuh `activeLembaga` dari Task 1; Task 3 dan 4 independen dari Task 1/2 (bisa ditukar urutannya) tapi tetap diletakkan belakangan karena levelnya lebih kosmetik/independen dibanding perubahan query di Task 1.
