# Audit & Perbaikan Menu Jadwal Pelajaran Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Perbaiki 8 celah UI/UX/kejelasan di menu Jadwal Pelajaran (label status aktif, suffix lembaga, konfirmasi konteks sebelum aksi, typo, parity modal, dan fitur salin jadwal lintas tahun ajaran). Backend/keamanan TIDAK diubah sama sekali.

**Architecture:** Task 1 membangun fondasi (`scopeHeaderData()` di kedua cabang `index()`) yang dipakai Item A dan Item H. Task 2-7 independen satu sama lain. Task 8 (fitur salin lintas tahun ajaran) bergantung pada fondasi Task 1. Task 9 penutup.

**Tech Stack:** Laravel 12, Blade, Alpine.js, Tom Select, Pest (function-style test).

## Global Constraints

- `JadwalPelajaranController.php` SAAT INI **belum** meng-`use App\Models\Lembaga;` — WAJIB ditambahkan di Task 1, TANPA import ini `Lembaga::withoutGlobalScopes()->find()` akan fatal error.
- `scopeHeaderData()` (Task 1) HARUS transkripsi PERSIS dari kode yang sudah dipakai `MataPelajaranController`/`KelasController`/`GuruController`/`TahunAjaranController` — `resolveActiveLembagaId()` dipanggil TANPA gate `$isYayasan` di depan, `Lembaga::find()` WAJIB pakai `withoutGlobalScopes()`.
- `scopeHeaderData()` WAJIB dipanggil di **KEDUA cabang** `index()` (baik `$request->ajax()` maupun halaman penuh) — BUKAN cuma satu, karena Item H (Task 8) menambahkan dropdown baru di dalam partial yang direfresh via AJAX dan butuh data yang sama.
- **Backend/query/keamanan TIDAK diubah sama sekali** — semua `abort_if` cross-lembaga existing tetap apa adanya. Tidak ada task di plan ini yang menyentuh `store()`, `update()`, `destroy()`, atau Action/Request classes.
- Konten yang dirender di dalam `_daftar.blade.php` (termasuk semua partial yang di-`@include` di dalamnya: `_modal-form.blade.php`, `_modal-duplicate.blade.php`) ter-refresh ulang setiap filter berubah (lewat `muatUlangDaftar()` yang meng-`innerHTML` elemen `x-ref="daftarJadwal"`). Konten di LUAR itu (area "Card Filter" atas termasuk dropdown Tahun Ajaran/Semester/Kelas dan tombol aksi) **HANYA dirender sekali saat page load pertama** — JANGAN taruh info yang perlu selalu akurat (nama kelas/semester terpilih) di area itu tanpa memastikannya reaktif; kalau perlu info begitu, taruh di dalam `_daftar.blade.php` atau partial di bawahnya.
- `_matrix-roster.blade.php`, `JadwalPelajaranSiswaController`, `JadwalAnakController` TIDAK disentuh — di luar cakupan.

---

## Konteks File yang Sudah Ada (baca sebelum mulai)

- `app/Http/Controllers/Admin/JadwalPelajaranController.php` — `index(Request $request): View|string` sudah menerima `$request`. Cabang `$request->ajax()` return `_daftar` partial; cabang lain return `index` full page. `opsi()` dipakai ulang oleh filter utama DAN (setelah Task 8) oleh modal Duplikat.
- `resources/views/portals/lembaga/akademik/jadwal-pelajaran/index.blade.php` — dropdown Tahun Ajaran baris ±61-66, Semester baris ±69-77, Kelas baris ±79-87. Tombol aksi ("Salin dari Kelas Lain", "+ Tambah Slot Jadwal") baris ±41-55, di dalam `<template x-if="kelasId && semesterId">`.
- `resources/views/portals/lembaga/akademik/jadwal-pelajaran/_daftar.blade.php` — header "Jadwal Pelajaran Kelas" baris ±4-11 (SELALU fresh di-refresh, target Task 5). `@include('..._modal-form')` baris ±136, `@include('..._modal-duplicate')` baris ±137.
- `resources/views/portals/lembaga/akademik/jadwal-pelajaran/_modal-form.blade.php` — header baris ±14-25, form mulai baris ±40. Field Ruangan baris ±108-116. Tombol submit dengan typo "Menyeduh..." baris ±123.
- `resources/views/portals/lembaga/akademik/jadwal-pelajaran/_modal-duplicate.blade.php` — header baris ±14-25, form field Semester/Kelas Sumber baris ±46-68.
- `resources/js/jadwal-pelajaran-filter.js` — `formModal`/`duplicateForm` state, `initTahunAjaranSelect`/`initKelasSelect`/`initModal*Select` (TomSelect init), `gantiTahunAjaran()` (AJAX populate Semester+Kelas saat TA berubah), `muatUlangDaftar()` (refresh `_daftar` partial), `openCreateModal()`/`openEditModal()`/`openDuplicateModal()`.
- `tests/Feature/Admin/JadwalPelajaranCrudTest.php` — helper `actingAsJadwalManager(Lembaga $lembaga): User` (lembaga-scope, role `operator_akademik`). TIDAK ADA helper yayasan-scope — ikuti pola bikin role+user inline (lihat test "duplicates jadwal pelajaran..." baris 953 utk contoh setup lengkap kelas/semester/pola-jam/jam-pelajaran, dan test lain di file yang sama utk pola yayasan-scope inline kalau dibutuhkan).

---

### Task 1: `scopeHeaderData()` (Kedua Cabang) + Badge Tahun Ajaran

**Files:**
- Modify: `app/Http/Controllers/Admin/JadwalPelajaranController.php`
- Modify: `resources/views/portals/lembaga/akademik/jadwal-pelajaran/index.blade.php`
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php`

**Interfaces:**
- Produces: kedua view (`index` dan `_daftar`) menerima `isYayasan` (`bool`), `activeLembaga` (`?Lembaga`), dan `tahunAjaranList` sekarang eager-load `lembaga`. Task 8 memakai ulang `isYayasan`/`activeLembaga`/`tahunAjaranList` yang SAMA di `_modal-duplicate.blade.php`.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/Admin/JadwalPelajaranCrudTest.php` (akhir file):

```php
it('shows "(Aktif)" and the lembaga name suffix on the tahun ajaran dropdown in aggregate mode for a yayasan-scoped actor', function () {
    Permission::firstOrCreate(['name' => 'jadwal-pelajaran.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_jadwal_ta_badge_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['jadwal-pelajaran.kelola']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Cempaka Raya']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2025/2026', 'status_aktif' => true]);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index'))
        ->assertSee('2025/2026 (Aktif) — SD Cempaka Raya', false);
});

it('does not show the lembaga suffix on the tahun ajaran dropdown for a lembaga-scoped actor', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2025/2026', 'status_aktif' => true]);

    $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index'))
        ->assertSee('2025/2026 (Aktif)', false)
        ->assertDontSee('2025/2026 (Aktif) —', false);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="shows .\(Aktif\). and the lembaga name suffix|does not show the lembaga suffix on the tahun ajaran dropdown" --compact`
Expected: FAIL — dropdown saat ini polos tanpa "(Aktif)" atau suffix lembaga.

- [ ] **Step 3: Implementasi minimal**

Di `app/Http/Controllers/Admin/JadwalPelajaranController.php`, tambahkan import setelah `use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;`:

```php
use App\Models\Lembaga;
```

Tambahkan method private baru sebelum `index()`:

```php
/**
 * Info scope yayasan/lembaga yang sedang aktif, dipakai utk suffix nama lembaga
 * di dropdown Tahun Ajaran saat mode agregat -- HANYA relevan utk aktor
 * berscope yayasan (punya switcher lembaga).
 *
 * @return array{isYayasan: bool, activeLembaga: ?Lembaga}
 */
private function scopeHeaderData(Request $request): array
{
    $isYayasan = $request->user()->widestScopeLevel() === 'yayasan';
    $lembagaId = $this->resolveActiveLembagaId($request->user());

    return [
        'isYayasan' => $isYayasan,
        'activeLembaga' => ($isYayasan && $lembagaId) ? Lembaga::withoutGlobalScopes()->find($lembagaId) : null,
    ];
}
```

Di `index()`, ganti KEDUA return `view(...)`:

```php
if ($request->ajax()) {
    return view('portals.lembaga.akademik.jadwal-pelajaran._daftar', [
        'jadwalList' => $jadwalList,
        'hariAktif' => $hariAktif,
        'kelasId' => $kelasId,
        'semesterId' => $semesterId,
        'kelasList' => $kelasList,
        'semesterList' => $semesterList,
        'jamPelajaranPerHari' => $jamPelajaranPerHari,
        'mataPelajaranList' => $mataPelajaranList,
        'guruList' => $guruList,
        'ruanganList' => $ruanganList,
        'kelas' => $kelas,
    ])->render();
}

return view('portals.lembaga.akademik.jadwal-pelajaran.index', [
    'tahunAjaranList' => TahunAjaran::orderByDesc('id')->get(),
    'tahunAjaranId' => $tahunAjaranId,
    'kelasList' => $kelasList,
    'semesterList' => $semesterList,
    'jadwalList' => $jadwalList,
    'hariAktif' => $hariAktif,
    'kelasId' => $kelasId,
    'semesterId' => $semesterId,
    'jamPelajaranPerHari' => $jamPelajaranPerHari,
    'mataPelajaranList' => $mataPelajaranList,
    'guruList' => $guruList,
    'ruanganList' => $ruanganList,
    'kelas' => $kelas,
]);
```

menjadi:

```php
if ($request->ajax()) {
    return view('portals.lembaga.akademik.jadwal-pelajaran._daftar', [
        'jadwalList' => $jadwalList,
        'hariAktif' => $hariAktif,
        'kelasId' => $kelasId,
        'semesterId' => $semesterId,
        'kelasList' => $kelasList,
        'semesterList' => $semesterList,
        'jamPelajaranPerHari' => $jamPelajaranPerHari,
        'mataPelajaranList' => $mataPelajaranList,
        'guruList' => $guruList,
        'ruanganList' => $ruanganList,
        'kelas' => $kelas,
        'tahunAjaranList' => TahunAjaran::with('lembaga')->orderByDesc('id')->get(),
        ...$this->scopeHeaderData($request),
    ])->render();
}

return view('portals.lembaga.akademik.jadwal-pelajaran.index', [
    'tahunAjaranList' => TahunAjaran::with('lembaga')->orderByDesc('id')->get(),
    'tahunAjaranId' => $tahunAjaranId,
    'kelasList' => $kelasList,
    'semesterList' => $semesterList,
    'jadwalList' => $jadwalList,
    'hariAktif' => $hariAktif,
    'kelasId' => $kelasId,
    'semesterId' => $semesterId,
    'jamPelajaranPerHari' => $jamPelajaranPerHari,
    'mataPelajaranList' => $mataPelajaranList,
    'guruList' => $guruList,
    'ruanganList' => $ruanganList,
    'kelas' => $kelas,
    ...$this->scopeHeaderData($request),
]);
```

Di `index.blade.php`, ganti (baris ±63-65):

```blade
@foreach ($tahunAjaranList as $tahunAjaran)
    <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
@endforeach
```

menjadi:

```blade
@foreach ($tahunAjaranList as $tahunAjaran)
    <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>
        {{ $tahunAjaran->nama }}{{ $tahunAjaran->status_aktif ? ' (Aktif)' : '' }}{{ ($isYayasan ?? false) && ! ($activeLembaga ?? null) ? ' — '.($tahunAjaran->lembaga->nama ?? '-') : '' }}
    </option>
@endforeach
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="shows .\(Aktif\). and the lembaga name suffix|does not show the lembaga suffix on the tahun ajaran dropdown" --compact`
Expected: PASS kedua test.

- [ ] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --compact`
Expected: PASS semua — termasuk test "returns only the schedule fragment for an AJAX request, not the full page" (harus tetap tidak mengandung markup halaman penuh meski payload ajax sekarang lebih besar).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/JadwalPelajaranController.php resources/views/portals/lembaga/akademik/jadwal-pelajaran/index.blade.php tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "feat(jadwal-pelajaran): scopeHeaderData() di kedua cabang index() + badge (Aktif)/suffix lembaga di dropdown Tahun Ajaran"
```

---

### Task 2: Dropdown Semester — Label "(Aktif)"

**Files:**
- Modify: `app/Http/Controllers/Admin/JadwalPelajaranController.php`
- Modify: `resources/views/portals/lembaga/akademik/jadwal-pelajaran/index.blade.php`
- Modify: `resources/js/jadwal-pelajaran-filter.js`
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php`

**Interfaces:**
- Tidak ada interface baru.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('shows "(Aktif)" on the semester dropdown for the active semester', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil', 'status_aktif' => true]);
    Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Genap', 'status_aktif' => false]);

    $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index', ['tahun_ajaran_id' => $tahunAjaran->id]))
        ->assertSee('Ganjil (Aktif)', false)
        ->assertDontSee('Genap (Aktif)', false);
});

it('includes status_aktif in the opsi() endpoint semester payload', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil', 'status_aktif' => true]);

    $response = $this->actingAs($manager)->getJson(route('admin.jadwal-pelajaran.opsi', ['tahun_ajaran_id' => $tahunAjaran->id]));

    $response->assertOk()->assertJsonFragment(['status_aktif' => true]);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="shows .\(Aktif\). on the semester dropdown|includes status_aktif in the opsi" --compact`
Expected: FAIL — dropdown Semester dan payload `opsi()` belum menyertakan `status_aktif`.

- [ ] **Step 3: Implementasi minimal**

Di `index.blade.php`, ganti (baris ±73-75):

```blade
@foreach ($semesterList as $semester)
    <option value="{{ $semester->id }}" @selected($semesterId == $semester->id)>{{ $semester->nama }}</option>
@endforeach
```

menjadi:

```blade
@foreach ($semesterList as $semester)
    <option value="{{ $semester->id }}" @selected($semesterId == $semester->id)>{{ $semester->nama }}{{ $semester->status_aktif ? ' (Aktif)' : '' }}</option>
@endforeach
```

Di `app/Http/Controllers/Admin/JadwalPelajaranController.php`, method `opsi()`, ganti:

```php
'semesterList' => Semester::where('tahun_ajaran_id', $tahunAjaran->id)->orderByDesc('id')->get(['id', 'nama']),
```

menjadi:

```php
'semesterList' => Semester::where('tahun_ajaran_id', $tahunAjaran->id)->orderByDesc('id')->get(['id', 'nama', 'status_aktif']),
```

Di `resources/js/jadwal-pelajaran-filter.js`, method `gantiTahunAjaran()`, ganti:

```js
json.semesterList.forEach((semester) => {
    const option = document.createElement('option');
    option.value = semester.id;
    option.textContent = semester.nama;
    this.$refs.semesterSelect.appendChild(option);
});
```

menjadi:

```js
json.semesterList.forEach((semester) => {
    const option = document.createElement('option');
    option.value = semester.id;
    option.textContent = semester.nama + (semester.status_aktif ? ' (Aktif)' : '');
    this.$refs.semesterSelect.appendChild(option);
});
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="shows .\(Aktif\). on the semester dropdown|includes status_aktif in the opsi" --compact`
Expected: PASS kedua test.

- [ ] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --compact`
Expected: PASS semua — termasuk "returns kelas and semester options scoped to the given tahun ajaran via the opsi endpoint" (payload sekarang punya field tambahan, TIDAK boleh menghilangkan field lama).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/JadwalPelajaranController.php resources/views/portals/lembaga/akademik/jadwal-pelajaran/index.blade.php resources/js/jadwal-pelajaran-filter.js tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "feat(jadwal-pelajaran): badge (Aktif) di dropdown Semester (render awal + hasil AJAX)"
```

---

### Task 3: Konfirmasi Konteks di Modal "Tambah Slot Jadwal" / "Edit Sesi"

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/jadwal-pelajaran/_modal-form.blade.php`
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php`

**Interfaces:**
- Consumes: `$kelas`, `$semesterList`, `$semesterId` (sudah tersedia di scope, tidak perlu perubahan controller).

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('shows a read-only kelas and semester context banner inside the add/edit slot modal', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil 2025/2026']);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '4A']);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index', [
        'tahun_ajaran_id' => $tahunAjaran->id, 'kelas_id' => $kelas->id, 'semester_id' => $semester->id,
    ]));

    $response->assertSee('4A')->assertSee('Ganjil 2025/2026');
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="read-only kelas and semester context banner" --compact`
Expected: kemungkinan sudah PASS SEBAGIAN (nama kelas/semester mungkin sudah muncul di tempat lain di halaman, mis. dropdown) — kalau begitu, tambahkan assertion `assertSeeInOrder` atau cek jumlah kemunculan spesifik markup modal. Kalau test sudah lulus tanpa perubahan, lanjut ke Step 3 tetap (test ini akan makin kuat setelah modal-nya benar-benar diberi banner), TIDAK PERLU dipaksa gagal dulu -- fokus pastikan Step 4 sungguh menguji markup BARU, bukan cuma teks yang kebetulan sudah ada.

- [ ] **Step 3: Implementasi minimal**

Di `_modal-form.blade.php`, setelah header (baris ±25, sebelum `@if (isset($jamPelajaranPerHari) ...)`), tambahkan:

```blade
@if (isset($kelas) && $kelas)
    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg bg-gray-50 px-3.5 py-2.5 text-xs text-gray-600 border border-gray-200">
        <span class="flex items-center gap-1.5">
            <x-icon name="class" class="h-3.5 w-3.5 text-gray-400" />
            Kelas <strong class="font-semibold text-gray-800">{{ $kelas->nama }}</strong>
        </span>
        <span class="flex items-center gap-1.5">
            <x-icon name="event" class="h-3.5 w-3.5 text-gray-400" />
            Semester <strong class="font-semibold text-gray-800">{{ $semesterList->firstWhere('id', $semesterId)?->nama ?? '—' }}</strong>
        </span>
    </div>
@endif
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="read-only kelas and semester context banner" --compact`
Expected: PASS. Perkuat assertion di Step 1 kalau ternyata belum benar-benar menguji markup baru (mis. tambah `assertSee('class', false)` untuk cek ikon spesifik banner, atau `substr_count` kalau perlu presisi lebih).

- [ ] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --compact`
Expected: PASS semua.

- [ ] **Step 6: Commit**

```bash
git add resources/views/portals/lembaga/akademik/jadwal-pelajaran/_modal-form.blade.php tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "feat(jadwal-pelajaran): banner konteks kelas & semester read-only di modal Tambah/Edit Slot"
```

---

### Task 4: Konfirmasi Konteks di Modal "Salin dari Kelas Lain"

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/jadwal-pelajaran/_modal-duplicate.blade.php`
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php`

**Interfaces:**
- Consumes: `$kelas`, `$semesterList`, `$semesterId`.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('shows "Menyalin KE" context with the target kelas and semester name inside the duplicate modal', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Genap 2025/2026']);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '5B']);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index', [
        'tahun_ajaran_id' => $tahunAjaran->id, 'kelas_id' => $kelas->id, 'semester_id' => $semester->id,
    ]));

    $response->assertSee('Menyalin KE', false)->assertSee('5B')->assertSee('Genap 2025/2026');
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="shows .Menyalin KE. context" --compact`
Expected: FAIL — teks "Menyalin KE" belum ada di manapun saat ini.

- [ ] **Step 3: Implementasi minimal**

Di `_modal-duplicate.blade.php`, setelah header (baris ±25, sebelum `<form>`), tambahkan:

```blade
@if (isset($kelas) && $kelas)
    <div class="mt-3 rounded-lg bg-brand-50 px-3.5 py-2.5 text-xs text-brand-800 border border-brand-200">
        <span class="flex items-center gap-1.5">
            <x-icon name="arrow_forward" class="h-3.5 w-3.5 text-brand-500" />
            Menyalin KE: Kelas <strong class="font-semibold">{{ $kelas->nama }}</strong> · Semester <strong class="font-semibold">{{ $semesterList->firstWhere('id', $semesterId)?->nama ?? '—' }}</strong>
        </span>
    </div>
@endif
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="shows .Menyalin KE. context" --compact`
Expected: PASS.

- [ ] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --compact`
Expected: PASS semua.

- [ ] **Step 6: Commit**

```bash
git add resources/views/portals/lembaga/akademik/jadwal-pelajaran/_modal-duplicate.blade.php tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "feat(jadwal-pelajaran): tegaskan kelas & semester TUJUAN di modal Salin dari Kelas Lain"
```

---

### Task 5: Header `_daftar.blade.php` — Nama Kelas & Semester Eksplisit

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/jadwal-pelajaran/_daftar.blade.php`
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php`

**Interfaces:**
- Consumes: `$kelas`, `$semesterList`, `$semesterId`, `$jadwalList`.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('shows the actual kelas and semester name in the daftar header, staying correct after an ajax filter refresh', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil 2025/2026']);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '6C']);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index', [
        'tahun_ajaran_id' => $tahunAjaran->id, 'kelas_id' => $kelas->id, 'semester_id' => $semester->id,
    ]), ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertSee('Jadwal Pelajaran Kelas 6C', false)->assertSee('Semester Ganjil 2025/2026', false);
});
```

Catatan: header `X-Requested-With: XMLHttpRequest` SENGAJA dipakai supaya test ini menghantam cabang `$request->ajax()` — persis skenario yang dulu jadi bug di draf pertama spec (badge basi kalau taruh di tempat yang tidak ikut refresh).

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="actual kelas and semester name in the daftar header" --compact`
Expected: FAIL — header saat ini generik "Jadwal Pelajaran Kelas" tanpa nama.

- [ ] **Step 3: Implementasi minimal**

Di `_daftar.blade.php`, ganti (baris ±4-11):

```blade
<div>
    <div class="flex items-center gap-2">
        <h2 class="font-display text-base font-bold text-gray-900">Jadwal Pelajaran Kelas</h2>
        <span class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-0.5 text-[11px] font-bold text-brand-700 border border-brand-200/60">
            Total {{ $jadwalList->count() }} Sesi
        </span>
    </div>
    <p class="text-xs text-gray-500 mt-0.5">Jadwal kegiatan belajar mengajar mingguan untuk kelas dan semester yang terpilih.</p>
</div>
```

menjadi:

```blade
<div>
    <div class="flex items-center gap-2">
        <h2 class="font-display text-base font-bold text-gray-900">Jadwal Pelajaran Kelas {{ $kelas->nama ?? '' }}</h2>
        <span class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-0.5 text-[11px] font-bold text-brand-700 border border-brand-200/60">
            Total {{ $jadwalList->count() }} Sesi
        </span>
    </div>
    <p class="text-xs text-gray-500 mt-0.5">Semester {{ $semesterList->firstWhere('id', $semesterId)?->nama ?? '—' }} · Jadwal kegiatan belajar mengajar mingguan untuk kelas dan semester yang terpilih.</p>
</div>
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="actual kelas and semester name in the daftar header" --compact`
Expected: PASS.

- [ ] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --compact`
Expected: PASS semua — termasuk "shows an explanatory message instead of a silent empty state when the filter is incomplete" (kelas/semester belum terpilih, `$kelas`/`$semesterId` null -- pastikan tidak muncul error, `{{ $kelas->nama ?? '' }}` sudah aman untuk itu).

- [ ] **Step 6: Commit**

```bash
git add resources/views/portals/lembaga/akademik/jadwal-pelajaran/_daftar.blade.php tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "fix(jadwal-pelajaran): tampilkan nama kelas & semester eksplisit di header daftar (selalu fresh, bukan basi)"
```

---

### Task 6: Fix Typo "Menyeduh..." → "Menyimpan..."

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/jadwal-pelajaran/_modal-form.blade.php`
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php`

**Interfaces:**
- Tidak ada interface baru.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('does not show the "Menyeduh" typo as the loading state text in the add/edit slot modal', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index'));

    $response->assertDontSee('Menyeduh', false)->assertSee('Menyimpan...', false);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="does not show the .Menyeduh. typo" --compact`
Expected: FAIL — teks "Menyeduh..." masih ada.

- [ ] **Step 3: Implementasi minimal**

Di `_modal-form.blade.php` baris 123, ganti:

```blade
<span x-show="formModal.loading">Menyeduh...</span>
```

menjadi:

```blade
<span x-show="formModal.loading">Menyimpan...</span>
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="does not show the .Menyeduh. typo" --compact`
Expected: PASS.

- [ ] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --compact`
Expected: PASS semua.

- [ ] **Step 6: Commit**

```bash
git add resources/views/portals/lembaga/akademik/jadwal-pelajaran/_modal-form.blade.php tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "fix(jadwal-pelajaran): perbaiki typo 'Menyeduh...' jadi 'Menyimpan...' di loading state modal"
```

---

### Task 7: Samakan Kelengkapan Modal dengan Halaman Penuh (Ruangan + Error Per-Field)

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/jadwal-pelajaran/_modal-form.blade.php`
- Modify: `resources/js/jadwal-pelajaran-filter.js`
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php`

**Interfaces:**
- Produces: `formModal.errors` (object, keyed per field) selain `formModal.errorMessage` (string, tetap dipakai toast).

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('shows the default ruangan name and kapasitas in the modal ruangan dropdown', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $gedung = Gedung::factory()->create(['lembaga_id' => $lembaga->id]);
    $ruanganDefault = Ruangan::factory()->create(['lembaga_id' => $lembaga->id, 'gedung_id' => $gedung->id, 'nama_ruangan' => 'Ruang Utama', 'kapasitas' => 30]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'ruangan_id' => $ruanganDefault->id]);
    $ruanganOpsi = Ruangan::factory()->create(['lembaga_id' => $lembaga->id, 'gedung_id' => $gedung->id, 'nama_ruangan' => 'Lab Komputer', 'kapasitas' => 24, 'is_aktif' => true]);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index', [
        'tahun_ajaran_id' => $tahunAjaran->id, 'kelas_id' => $kelas->id,
    ]));

    $response->assertSee('— Default Ruang Kelas (Ruang Utama) —', false)
        ->assertSee('Lab Komputer (Kapasitas: 24)', false);
});

it('exposes formModal.errors state and per-field error rendering for guru_id in the modal', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index'));

    $response->assertSee('formModal.errors.guru_id', false);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="default ruangan name and kapasitas|formModal.errors state and per-field" --compact`
Expected: FAIL — dropdown Ruangan modal masih polos, `formModal.errors` belum ada.

- [ ] **Step 3: Implementasi minimal**

Di `_modal-form.blade.php` baris ±108-116, ganti:

```blade
<div>
    <x-input-label value="Ruangan Sarpras" />
    <select name="ruangan_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
        <option value="">— Default Ruang Kelas —</option>
        @foreach ($ruanganList ?? [] as $ruangan)
            <option value="{{ $ruangan->id }}">{{ $ruangan->nama_ruangan }}</option>
        @endforeach
    </select>
</div>
```

menjadi:

```blade
<div>
    <x-input-label value="Ruangan Sarpras" />
    <select name="ruangan_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
        <option value="">— Default Ruang Kelas ({{ $kelas?->ruangan?->nama_ruangan ?? 'Belum Diatur' }}) —</option>
        @foreach ($ruanganList ?? [] as $ruangan)
            <option value="{{ $ruangan->id }}">{{ $ruangan->nama_ruangan }} (Kapasitas: {{ $ruangan->kapasitas ?? '—' }})</option>
        @endforeach
    </select>
</div>
```

Di `_modal-form.blade.php`, tambahkan baris error di bawah select Guru Pengampu (baris ±98-106), Mata Pelajaran (baris ±87-96), dan Ruangan (blok di atas) — contoh untuk Guru:

```blade
<div>
    <x-input-label value="Guru Pengampu" />
    <select name="guru_id" required x-init="initModalGuruSelect($el)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
        <option value="" disabled>— Pilih Guru —</option>
        @foreach ($guruList ?? [] as $guru)
            <option value="{{ $guru->id }}">{{ $guru->nama }}</option>
        @endforeach
    </select>
    <p x-show="formModal.errors.guru_id" x-text="formModal.errors.guru_id?.[0]" class="mt-1 text-[11px] text-error-600"></p>
</div>
```

Field Mata Pelajaran dan Ruangan dapat baris `<p x-show="formModal.errors.mata_pelajaran_id" ...>`/`<p x-show="formModal.errors.ruangan_id" ...>` yang sama polanya, dan blok `jam_pelajaran_id` (baik cabang create maupun edit) dapat `<p x-show="formModal.errors.jam_pelajaran_id" x-text="formModal.errors.jam_pelajaran_id?.[0]" class="mt-1 text-[11px] text-error-600"></p>`.

Di `resources/js/jadwal-pelajaran-filter.js`, tambahkan `errors: {}` ke `formModal` state:

```js
formModal: {
    mode: 'create',
    actionUrl: '',
    jam_ids: [],
    jam_id: '',
    mapel_id: '',
    guru_id: '',
    loading: false,
    errorMessage: '',
},
```

menjadi:

```js
formModal: {
    mode: 'create',
    actionUrl: '',
    jam_ids: [],
    jam_id: '',
    mapel_id: '',
    guru_id: '',
    loading: false,
    errorMessage: '',
    errors: {},
},
```

Di `submitForm()`, ganti:

```js
if (!response.ok || data.status === 'error') {
    const firstError = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Gagal menyimpan jadwal.');
    this.formModal.errorMessage = firstError;
    Alpine.store('toast').push('error', this.formModal.errorMessage);
}
```

menjadi:

```js
if (!response.ok || data.status === 'error') {
    this.formModal.errors = data.errors || {};
    const firstError = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Gagal menyimpan jadwal.');
    this.formModal.errorMessage = firstError;
    Alpine.store('toast').push('error', this.formModal.errorMessage);
}
```

Di `openCreateModal()`, ganti:

```js
openCreateModal(data = {}) {
    this.formModal.mode = 'create';
    this.formModal.actionUrl = this.storeUrlBase;
    this.formModal.jam_ids = data && data.jam_ids ? data.jam_ids.map(String) : [];
    this.formModal.mapel_id = '';
    this.formModal.guru_id = '';
    this.formModal.errorMessage = '';
    this.showModalForm = true;
```

menjadi:

```js
openCreateModal(data = {}) {
    this.formModal.mode = 'create';
    this.formModal.actionUrl = this.storeUrlBase;
    this.formModal.jam_ids = data && data.jam_ids ? data.jam_ids.map(String) : [];
    this.formModal.mapel_id = '';
    this.formModal.guru_id = '';
    this.formModal.errorMessage = '';
    this.formModal.errors = {};
    this.showModalForm = true;
```

Di `openEditModal()`, ganti:

```js
openEditModal(data) {
    this.formModal.mode = 'edit';
    this.formModal.actionUrl = data.url;
    this.formModal.jam_id = String(data.jam_id);
    this.formModal.mapel_id = data.mapel_id ? String(data.mapel_id) : '';
    this.formModal.guru_id = String(data.guru_id);
    this.formModal.errorMessage = '';
    this.showModalForm = true;
```

menjadi:

```js
openEditModal(data) {
    this.formModal.mode = 'edit';
    this.formModal.actionUrl = data.url;
    this.formModal.jam_id = String(data.jam_id);
    this.formModal.mapel_id = data.mapel_id ? String(data.mapel_id) : '';
    this.formModal.guru_id = String(data.guru_id);
    this.formModal.errorMessage = '';
    this.formModal.errors = {};
    this.showModalForm = true;
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="default ruangan name and kapasitas|formModal.errors state and per-field" --compact`
Expected: PASS kedua test.

- [ ] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --compact`
Expected: PASS semua.

- [ ] **Step 6: Commit**

```bash
git add resources/views/portals/lembaga/akademik/jadwal-pelajaran/_modal-form.blade.php resources/js/jadwal-pelajaran-filter.js tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "feat(jadwal-pelajaran): samakan modal dengan halaman penuh -- nama+kapasitas ruangan, error validasi per-field"
```

---

### Task 8: Fitur Baru — Salin Jadwal Lintas Tahun Ajaran

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/jadwal-pelajaran/_modal-duplicate.blade.php`
- Modify: `resources/js/jadwal-pelajaran-filter.js`
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php`

**Interfaces:**
- Consumes: `$tahunAjaranList`, `$isYayasan`, `$activeLembaga` dari Task 1 (SUDAH tersedia di cabang ajax sejak Task 1).
- Backend `duplicate()` TIDAK diubah — endpoint `opsi()` dipakai ulang apa adanya.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('shows a "Tahun Ajaran Sumber" dropdown in the duplicate modal, populated with all tahun ajaran including ones different from the currently filtered one', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);
    $tahunAjaranLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2024/2025']);
    $tahunAjaranBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2025/2026', 'status_aktif' => true]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaranBaru->id]);

    $response = $this->actingAs($manager)->get(route('admin.jadwal-pelajaran.index', [
        'tahun_ajaran_id' => $tahunAjaranBaru->id, 'kelas_id' => $kelas->id,
    ]));

    $response->assertSee('Tahun Ajaran Sumber', false)
        ->assertSee('2024/2025', false)
        ->assertSee('2025/2026 (Aktif)', false);
});

it('duplicates jadwal pelajaran from a source kelas belonging to a different tahun ajaran than the one currently filtered', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembaga);

    $tahunAjaranLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2024/2025']);
    $tahunAjaranBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2025/2026']);
    $semesterLama = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLama->id]);
    $semesterBaru = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranBaru->id]);

    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $slot = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Senin->value, 'urutan' => 1, 'is_pelajaran' => true]);

    $sourceKelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaranLama->id, 'pola_jam_id' => $pola->id, 'nama' => '4A (2024/2025)']);
    $targetKelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaranBaru->id, 'pola_jam_id' => $pola->id, 'nama' => '4A (2025/2026)']);

    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    JadwalPelajaran::factory()->create([
        'kelas_id' => $sourceKelas->id, 'semester_id' => $semesterLama->id, 'jam_pelajaran_id' => $slot->id,
        'mata_pelajaran_id' => $mapel->id, 'guru_id' => $guru->id,
    ]);

    $response = $this->actingAs($manager)->postJson(route('admin.jadwal-pelajaran.duplicate'), [
        'source_kelas_id' => $sourceKelas->id,
        'source_semester_id' => $semesterLama->id,
        'target_kelas_id' => $targetKelas->id,
        'target_semester_id' => $semesterBaru->id,
    ]);

    $response->assertOk()->assertJson(['status' => 'success', 'copied_count' => 1, 'skipped_count' => 0]);
    $this->assertDatabaseHas('jadwal_pelajaran', ['kelas_id' => $targetKelas->id, 'jam_pelajaran_id' => $slot->id]);
});
```

Catatan: test kedua memvalidasi PREMIS Item H bahwa backend `duplicate()` SUDAH MENDUKUNG lintas tahun ajaran TANPA perubahan apa pun (test ini murni membuktikan ulang fakta yang sudah ada, bukan menguji kode baru) — kalau test ini GAGAL, berarti asumsi spec SALAH dan harus lapor ke user SEBELUM lanjut, JANGAN otak-atik `duplicate()`/`DuplicateJadwalAction` untuk membuatnya lulus.

- [ ] **Step 2: Jalankan test, pastikan test pertama gagal, test kedua SUDAH lulus**

Run: `php artisan test --filter="Tahun Ajaran Sumber. dropdown in the duplicate modal|duplicates jadwal pelajaran from a source kelas belonging to a different tahun ajaran" --compact`
Expected: test pertama (dropdown) FAIL — belum ada dropdown baru. Test KEDUA (backend) HARUS SUDAH PASS tanpa perubahan kode apa pun (membuktikan premis "backend sudah mendukung").

- [ ] **Step 3: Implementasi minimal**

Di `_modal-duplicate.blade.php` baris ±46-68, ganti:

```blade
<div class="space-y-4">
    <div>
        <x-input-label value="Semester Sumber" />
        <select name="source_semester_id" x-model="duplicateForm.source_semester_id" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="">— Pilih Semester Sumber —</option>
            @foreach ($semesterList as $sem)
                <option value="{{ $sem->id }}">{{ $sem->nama }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-[11px] text-gray-400">Pilih semester asal data yang akan di-copy.</p>
    </div>

    <div>
        <x-input-label value="Kelas Sumber" />
        <select name="source_kelas_id" x-model="duplicateForm.source_kelas_id" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="">— Pilih Kelas Sumber —</option>
            @foreach ($kelasList as $kel)
                <option value="{{ $kel->id }}" x-show="String({{ $kel->id }}) !== String(duplicateForm.target_kelas_id)">{{ $kel->nama }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-[11px] text-gray-400">Pilih kelas yang memiliki konfigurasi jadwal yang ingin diterapkan.</p>
    </div>
</div>
```

menjadi:

```blade
<div class="space-y-4">
    <div>
        <x-input-label value="Tahun Ajaran Sumber" />
        <select x-ref="duplicateTahunAjaranSelect" x-init="initDuplicateTahunAjaranSelect($refs.duplicateTahunAjaranSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="">— Pilih Tahun Ajaran Sumber —</option>
            @foreach ($tahunAjaranList as $ta)
                <option value="{{ $ta->id }}">{{ $ta->nama }}{{ $ta->status_aktif ? ' (Aktif)' : '' }}{{ ($isYayasan ?? false) && ! ($activeLembaga ?? null) ? ' — '.($ta->lembaga->nama ?? '-') : '' }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-[11px] text-gray-400">Boleh dari Tahun Ajaran yang berbeda dari yang sedang dilihat.</p>
    </div>

    <div>
        <x-input-label value="Semester Sumber" />
        <select name="source_semester_id" x-ref="duplicateSemesterSelect" x-model="duplicateForm.source_semester_id" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="">— Pilih Tahun Ajaran Sumber Dulu —</option>
        </select>
    </div>

    <div>
        <x-input-label value="Kelas Sumber" />
        <select name="source_kelas_id" x-ref="duplicateKelasSelect" x-model="duplicateForm.source_kelas_id" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="">— Pilih Tahun Ajaran Sumber Dulu —</option>
        </select>
        <p class="mt-1 text-[11px] text-gray-400">Pilih kelas yang memiliki konfigurasi jadwal yang ingin diterapkan.</p>
    </div>
</div>
```

Di `resources/js/jadwal-pelajaran-filter.js`, tambahkan method baru `initDuplicateTahunAjaranSelect()` (sejajar dengan `initKelasSelect`/`initTahunAjaranSelect`):

```js
initDuplicateTahunAjaranSelect(el) {
    new TomSelect(el, {
        maxItems: 1,
        create: false,
        placeholder: 'Cari tahun ajaran sumber...',
        onChange: async (value) => {
            this.duplicateForm.source_semester_id = '';
            this.duplicateForm.source_kelas_id = '';
            this.$refs.duplicateSemesterSelect.innerHTML = '<option value="">— Pilih Semester Sumber —</option>';
            this.$refs.duplicateKelasSelect.innerHTML = '<option value="">— Pilih Kelas Sumber —</option>';

            if (!value) return;

            try {
                const url = new URL(this.opsiUrl, window.location.origin);
                url.searchParams.set('tahun_ajaran_id', value);
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', 'Gagal memuat opsi kelas dan semester sumber.');
                    return;
                }

                json.semesterList.forEach((semester) => {
                    const option = document.createElement('option');
                    option.value = semester.id;
                    option.textContent = semester.nama + (semester.status_aktif ? ' (Aktif)' : '');
                    this.$refs.duplicateSemesterSelect.appendChild(option);
                });

                json.kelasList.forEach((kelas) => {
                    if (String(kelas.id) === String(this.duplicateForm.target_kelas_id)) return;
                    const option = document.createElement('option');
                    option.value = kelas.id;
                    option.textContent = kelas.nama;
                    this.$refs.duplicateKelasSelect.appendChild(option);
                });
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memuat opsi kelas dan semester sumber.');
            }
        },
    });
},
```

Di `openDuplicateModal()`, ganti:

```js
openDuplicateModal() {
    this.duplicateForm.target_kelas_id = this.kelasId;
    this.duplicateForm.target_semester_id = this.semesterId;
    this.duplicateForm.source_semester_id = this.semesterId;
    this.duplicateForm.source_kelas_id = '';
    this.duplicateForm.errorMessage = '';
    this.showModalDuplicate = true;
},
```

menjadi:

```js
openDuplicateModal() {
    this.duplicateForm.target_kelas_id = this.kelasId;
    this.duplicateForm.target_semester_id = this.semesterId;
    this.duplicateForm.source_semester_id = '';
    this.duplicateForm.source_kelas_id = '';
    this.duplicateForm.errorMessage = '';
    this.showModalDuplicate = true;
},
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="Tahun Ajaran Sumber. dropdown in the duplicate modal|duplicates jadwal pelajaran from a source kelas belonging to a different tahun ajaran" --compact`
Expected: PASS kedua test.

- [ ] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --compact`
Expected: PASS semua — termasuk "duplicates jadwal pelajaran from source kelas and semester to target kelas and semester while skipping teacher collisions" dan "rejects schedule duplication when target class belongs to a different tenant" (backend `duplicate()` TIDAK diubah, regresi HARUS tetap lulus tanpa perubahan apa pun).

- [ ] **Step 6: Commit**

```bash
git add resources/views/portals/lembaga/akademik/jadwal-pelajaran/_modal-duplicate.blade.php resources/js/jadwal-pelajaran-filter.js tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "feat(jadwal-pelajaran): fitur salin jadwal lintas tahun ajaran (murni frontend, backend sudah mendukung)"
```

---

### Task 9: Penutup — Regresi Penuh, Pint, Verifikasi Manual

**Files:**
- Tidak ada file baru — task verifikasi murni.

- [ ] **Step 1: Jalankan seluruh test domain Jadwal Pelajaran**

Run: `php artisan test --compact --filter="JadwalPelajaranCrudTest|JadwalPelajaranBentrokWaktuTest|JadwalPelajaranTenantGuardTest|JadwalPelajaranSiswaControllerTest"`
Expected: PASS semua, 0 gagal.

- [ ] **Step 2: Jalankan Pint pada file yang diubah**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}`.

- [ ] **Step 3: Verifikasi manual via browser (WAJIB)**

Login sebagai LEMBAGA-scope: buka Jadwal Pelajaran, pastikan dropdown Tahun Ajaran & Semester menampilkan "(Aktif)" pada entri yang benar. Pilih Kelas — header daftar menampilkan nama kelas & semester eksplisit ("Jadwal Pelajaran Kelas 4A", "Semester Ganjil ..."). Ganti Semester lewat dropdown TANPA reload halaman — header ikut update (bukan basi).

Klik "+ Tambah Slot Jadwal" — modal terbuka dengan banner konteks kelas/semester read-only di atas, dropdown Ruangan menyebut nama+kapasitas, loading state saat submit bertuliskan "Menyimpan..." (bukan "Menyeduh"). Coba submit dengan data tidak valid — error muncul PER FIELD di bawah masing-masing input, bukan cuma 1 pesan umum.

Klik "Salin dari Kelas Lain" — modal menampilkan "Menyalin KE: ..." dengan nama kelas & semester TUJUAN. Pilih Tahun Ajaran Sumber yang BERBEDA dari yang sedang aktif — dropdown Semester & Kelas Sumber ter-populate ulang sesuai Tahun Ajaran itu. Selesaikan duplikasi, pastikan berhasil.

Login sebagai YAYASAN-scope mode "Semua Lembaga" (kalau ada 2+ lembaga dengan Tahun Ajaran senama di data uji): pastikan dropdown Tahun Ajaran (baik filter utama maupun Tahun Ajaran Sumber di modal Duplikat) menampilkan suffix nama lembaga.

- [ ] **Step 4: Laporkan hasil**

TIDAK perlu menulis file handoff log baru di task ini — kalau user menghendaki log terpisah, itu permintaan tambahan setelah plan ini selesai.

---

## Self-Review

**1. Spec coverage** — SEMUA 8 item spec `.agents/specs/2026-09-08-jadwal-pelajaran-audit-perbaikan.md` tercakup: Item A → Task 1, Item B → Task 2, Item C → Task 3, Item D → Task 4, Item E → Task 5, Item F → Task 6, Item G → Task 7, Item H → Task 8. Task 9 menutup dengan regresi + Pint + verifikasi manual.

**2. Placeholder scan** — tidak ada "TBD"/dst. Semua step berisi kode lengkap, ditranskripsi persis dari spec yang sudah 2x direview (termasuk 3 koreksi nyata: badge lokasi-basi Item E dipindah, `scopeHeaderData()` di kedua cabang, guard self-copy Kelas Sumber dipertahankan).

**3. Type consistency** — `isYayasan`/`activeLembaga`/`tahunAjaranList` (Task 1) dipakai konsisten di Task 8. `formModal.errors` (Task 7) nama field sama persis di JS dan Blade.

**Catatan tambahan hasil self-review**:
- Task 1 WAJIB paling awal — Task 8 bergantung langsung pada `scopeHeaderData()` dan `tahunAjaranList` yang ditambahkan Task 1 ke cabang ajax.
- Task 8 Step 1 test kedua SENGAJA ditulis sebagai bukti ulang bahwa backend TIDAK PERLU diubah (bukan test fitur baru) — instruksi eksplisit "kalau gagal, JANGAN otak-atik backend, lapor ke user dulu" ditambahkan karena kalau asumsi spec ternyata salah, itu sinyal masalah desain yang lebih besar, bukan sekadar bug implementasi.
- Task 3 Step 1-2 punya nuansa (test mungkin sebagian sudah lulus sebelum implementasi, karena nama kelas/semester kemungkinan sudah terlihat di tempat lain di halaman seperti dropdown) — instruksi eksplisit ditambahkan supaya pelaksana tidak bingung kalau test tidak 100% gagal di awal, dan tetap memastikan Step 4 menguji markup BARU yang sebenarnya.
- Urutan Task 2-7 TIDAK saling bergantung dan boleh dikerjakan dalam urutan berbeda kalau perlu, TAPI tetap disarankan berurutan seperti tertulis (ikuti urutan huruf item di spec) untuk kemudahan tracking.
