# Audit Sistematis Akademik Tahap 2 — Kelompok B (Kenaikan Kelas UX) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambah 2 safety-net UX non-blocking pada fitur Kenaikan Kelas: saran otomatis "Lulus" untuk kelas di tingkat akhir jenjangnya, dan peringatan live kalau kurikulum kelas tujuan berbeda dari kelas asal.

**Architecture:** `isTingkatAkhir()` diekstrak dari method private `RaporPdfDataBuilder` ke method publik baru di enum `BentukPendidikan` (single source of truth semantik "tingkat akhir"), dipakai ulang oleh `KenaikanKelasController` untuk pre-select dropdown. Peringatan kurikulum murni Blade + Alpine.js inline di view, tanpa perubahan server-side logic sama sekali.

**Tech Stack:** Laravel 12.68, Pest v4, Blade + Alpine.js inline `x-data` (tanpa modul JS baru — `symfony/dom-crawler` TIDAK terinstal di proyek ini, jadi test HTML-scoping pakai substring/regex manual, bukan `$response->getCrawler()`).

## Global Constraints

- `validTingkatValues()` (validitas nilai tingkat) dan `isTingkatAkhir()` (semantik tingkat akhir/kelulusan) adalah 2 konsep BERBEDA yang TIDAK BOLEH disatukan. `isTingkatAkhir()` HARUS tetap `match` eksplisit per-case — JANGAN PERNAH direfactor jadi `end($this->validTingkatValues())`, itu akan membuat KB/TPA/SPS tingkat "B" salah dianggap tingkat akhir (regresi Priority #3).
- Tidak ada validasi server-side baru yang memblokir submit. `ProsesKenaikanKelasAction::execute()` TIDAK DIUBAH sama sekali — guard existing (cross-tahun-ajaran, cross-lembaga) tetap utuh.
- Tidak ada guard `bentuk_pendidikan` baru — sudah tercover guard lintas-`lembaga_id` existing (lembaga = enum tunggal per record).
- Peringatan kurikulum: HANYA muncul kalau KEDUA sisi (asal & tujuan) punya nilai kurikulum non-null dan berbeda. Kalau kurikulum asal `null`, peringatan TIDAK PERNAH muncul (interpretasi: "tidak diketahui", bukan "mismatch otomatis").
- Tidak menambah modul Alpine terpisah di `resources/js/` — state per-baris trivial, `x-data` inline sesuai konvensi proyek.
- Test yang membuktikan markup Blade/data attribute WAJIB scoped ke baris kelas yang benar (bukan `assertSee()` global) — proyek ini tidak punya `symfony/dom-crawler` terinstal, jadi gunakan pemotongan substring HTML manual (helper function di test) sebagai gantinya.
- Jalankan `vendor/bin/pint --dirty --format agent` di akhir setiap task sebelum commit.
- Test scoped per task; full suite TIDAK WAJIB di plan ini (Kelompok B murni menambah file/method baru + 1 method delegasi yang sudah punya regression test existing) — cukup jalankan gabungan test scoped di Task 3 sbg checkpoint akhir Kelompok B. Full suite akan dijalankan sekali di akhir Kelompok C (checkpoint gabungan B+C), bukan di sini.

---

### Task 1: `BentukPendidikan::isTingkatAkhir()` + delegasi `RaporPdfDataBuilder`

**Files:**
- Modify: `app/Domains/Akademik/Enums/BentukPendidikan.php`
- Modify: `app/Domains/Akademik/Services/RaporPdfDataBuilder.php:145-157`
- Test: `tests/Unit/Domains/Akademik/Enums/BentukPendidikanTest.php` (file SUDAH ADA, tambah test baru di dalamnya — bukan file baru, karena satu enum sebaiknya satu file test, konsisten dengan test `validTingkatValues()` yang sudah ada di file ini)

**Interfaces:**
- Produces: `BentukPendidikan::isTingkatAkhir(?string $tingkat): bool` — dipakai Task 2.

- [x] **Step 1: Baca file existing untuk konfirmasi baseline**

Baca `app/Domains/Akademik/Enums/BentukPendidikan.php` dan `app/Domains/Akademik/Services/RaporPdfDataBuilder.php` baris 145-157 — pastikan isinya persis seperti kutipan di Step 3 & 4 di bawah. Kalau berbeda, STOP dan laporkan ke user.

- [x] **Step 2: Tulis test data-driven yang gagal**

Tambahkan di akhir `tests/Unit/Domains/Akademik/Enums/BentukPendidikanTest.php`:

```php
it('treats KB tingkat B as NOT tingkat akhir (permanent exclusion, Priority #3)', function () {
    expect(BentukPendidikan::Kb->isTingkatAkhir('B'))->toBeFalse();
});

it('treats TPA tingkat B as NOT tingkat akhir (permanent exclusion, Priority #3)', function () {
    expect(BentukPendidikan::Tpa->isTingkatAkhir('B'))->toBeFalse();
});

it('treats SPS tingkat B as NOT tingkat akhir (permanent exclusion, Priority #3)', function () {
    expect(BentukPendidikan::Sps->isTingkatAkhir('B'))->toBeFalse();
});

it('treats TK tingkat B as tingkat akhir', function () {
    expect(BentukPendidikan::Tk->isTingkatAkhir('B'))->toBeTrue();
});

it('treats TK tingkat A as NOT tingkat akhir', function () {
    expect(BentukPendidikan::Tk->isTingkatAkhir('A'))->toBeFalse();
});

it('treats SD tingkat 6 as tingkat akhir, tingkat 5 as not', function () {
    expect(BentukPendidikan::Sd->isTingkatAkhir('6'))->toBeTrue();
    expect(BentukPendidikan::Sd->isTingkatAkhir('5'))->toBeFalse();
});

it('treats SLB tingkat 6 as tingkat akhir', function () {
    expect(BentukPendidikan::Slb->isTingkatAkhir('6'))->toBeTrue();
});

it('treats SMP tingkat 9 as tingkat akhir, tingkat 8 as not', function () {
    expect(BentukPendidikan::Smp->isTingkatAkhir('9'))->toBeTrue();
    expect(BentukPendidikan::Smp->isTingkatAkhir('8'))->toBeFalse();
});

it('treats SMA tingkat 12 as tingkat akhir, tingkat 11 as not', function () {
    expect(BentukPendidikan::Sma->isTingkatAkhir('12'))->toBeTrue();
    expect(BentukPendidikan::Sma->isTingkatAkhir('11'))->toBeFalse();
});

it('treats SMK tingkat 12 as tingkat akhir', function () {
    expect(BentukPendidikan::Smk->isTingkatAkhir('12'))->toBeTrue();
});

it('treats null tingkat as NOT tingkat akhir for every case', function () {
    foreach (BentukPendidikan::cases() as $case) {
        expect($case->isTingkatAkhir(null))->toBeFalse();
    }
});
```

- [x] **Step 3: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Unit/Domains/Akademik/Enums/BentukPendidikanTest.php --compact`
Expected: FAIL — method `isTingkatAkhir` belum ada di enum (`Call to undefined method`).

- [x] **Step 4: Tambah method `isTingkatAkhir()` ke enum**

Edit `app/Domains/Akademik/Enums/BentukPendidikan.php`, tambah method baru setelah `validTingkatValues()` (sebelum penutup `}` enum):

```php
    /**
     * Semantik bisnis "tingkat akhir/kelulusan" -- BERBEDA dari validTingkatValues().
     * validTingkatValues() adalah source of truth validitas nilai tingkat (tingkat
     * apa saja yang boleh diinput). isTingkatAkhir() adalah source of truth semantik
     * kelulusan, yang SENGAJA tidak selalu sama dengan elemen terakhir
     * validTingkatValues() -- KB/TPA/SPS berbagi nilai valid A/B dengan TK tapi TIDAK
     * berbagi aturan kelulusan TK (keputusan terkunci Priority #3, Kelulusan PAUD/SLB).
     * JANGAN PERNAH direfactor jadi `end($this->validTingkatValues())` -- itu akan
     * membuat KB/TPA/SPS tingkat B salah dianggap tingkat akhir.
     */
    public function isTingkatAkhir(?string $tingkat): bool
    {
        if ($tingkat === null) {
            return false;
        }

        return match ($this) {
            self::Kb, self::Tpa, self::Sps => false,
            self::Tk => $tingkat === 'B',
            self::Sd, self::Slb => $tingkat === '6',
            self::Smp => $tingkat === '9',
            self::Sma, self::Smk => $tingkat === '12',
        };
    }
```

- [x] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Unit/Domains/Akademik/Enums/BentukPendidikanTest.php --compact`
Expected: PASS, semua test di file (existing + baru) lulus.

- [x] **Step 6: Refactor `RaporPdfDataBuilder::isTingkatAkhir()` jadi delegasi**

Edit `app/Domains/Akademik/Services/RaporPdfDataBuilder.php`. Tambah import di bagian atas file (cek dulu apakah `App\Domains\Akademik\Enums\BentukPendidikan` sudah di-import — kalau belum, tambahkan):

```php
use App\Domains\Akademik\Enums\BentukPendidikan;
```

Ubah method private (baris 145-157) dari:

```php
    private function isTingkatAkhir(?string $bentukPendidikan, ?string $tingkat): bool
    {
        $tingkatAkhirPerJenjang = [
            'TK' => 'B',
            'SD' => '6',
            'SLB' => '6',
            'SMP' => '9',
            'SMA' => '12',
            'SMK' => '12',
        ];

        return isset($tingkatAkhirPerJenjang[$bentukPendidikan]) && $tingkatAkhirPerJenjang[$bentukPendidikan] === $tingkat;
    }
```

menjadi:

```php
    private function isTingkatAkhir(?string $bentukPendidikan, ?string $tingkat): bool
    {
        if ($bentukPendidikan === null) {
            return false;
        }

        return BentukPendidikan::from($bentukPendidikan)->isTingkatAkhir($tingkat);
    }
```

Signature method TIDAK BERUBAH (masih private, masih dipanggil internal saja) — perubahan murni implementasi.

- [x] **Step 7: Jalankan test regresi existing — WAJIB tetap pass tanpa modifikasi assertion**

Run: `php artisan test tests/Feature/Akademik/RaporPdfDataBuilderIsTingkatAkhirTest.php --compact`
Expected: PASS, semua 10 test (termasuk yang eksplisit menguji KB/TPA/SPS tetap `false` di tingkat B) lulus TANPA perlu mengubah satu assertion pun. File ini SUDAH ADA sebelum plan ini dan sudah menguji skenario regresi yang persis sama — kalau ada yang gagal, itu tanda refactor Step 6 salah, JANGAN ubah test-nya, perbaiki kode Step 6.

Run juga: `php artisan test tests/Feature/Akademik/RaporPdfDataBuilderTest.php --compact`
Expected: PASS, tidak ada regresi ke test builder utama.

- [x] **Step 8: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/Akademik/Enums/BentukPendidikan.php app/Domains/Akademik/Services/RaporPdfDataBuilder.php tests/Unit/Domains/Akademik/Enums/BentukPendidikanTest.php
git commit -m "refactor(akademik): ekstrak isTingkatAkhir() ke enum BentukPendidikan"
```

---

### Task 2: Saran otomatis "Lulus" di dropdown tindakan Kenaikan Kelas

**Files:**
- Modify: `app/Http/Controllers/Admin/KenaikanKelasController.php:29-31`
- Modify: `resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php:72-81`
- Test: `tests/Feature/Akademik/KenaikanKelasControllerUxTest.php` (BARU — terpisah dari `tests/Feature/Admin/KenaikanKelasControllerTest.php` yang sudah ada, supaya test business-logic `execute()` tetap terpisah dari test rendering/UX)

**Interfaces:**
- Consumes: `BentukPendidikan::isTingkatAkhir(?string $tingkat): bool` (Task 1).

- [x] **Step 1: Baca file existing untuk konfirmasi baseline**

Baca `app/Http/Controllers/Admin/KenaikanKelasController.php` baris 20-41 dan `resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php` baris 72-81 — pastikan cocok dengan kutipan di Step 3/4. Kalau beda, STOP dan laporkan.

- [x] **Step 2: Tulis test yang gagal — pre-select scoped per baris kelas**

Buat `tests/Feature/Akademik/KenaikanKelasControllerUxTest.php`:

```php
<?php

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function siapkanKenaikanKelasUxUser(): array
{
    Permission::firstOrCreate(['name' => 'kenaikan-kelas.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'operator_kenaikan_ux', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kenaikan-kelas.kelola']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return [$manager, $lembaga];
}

/**
 * Ambil substring HTML utk satu <select> spesifik lewat atribut `name`-nya.
 * Proyek ini tidak punya symfony/dom-crawler terinstal, jadi scoping HTML
 * dilakukan manual via regex non-greedy, bukan lewat $response->getCrawler().
 */
function htmlSelectByName(string $html, string $selectName): string
{
    $pattern = '/<select[^>]*name="' . preg_quote($selectName, '/') . '"[^>]*>.*?<\/select>/s';
    preg_match($pattern, $html, $matches);

    return $matches[0] ?? '';
}

function selectedOptionValue(string $selectHtml): ?string
{
    preg_match('/<option[^>]*value="([^"]*)"[^>]*selected/s', $selectHtml, $matches);

    return $matches[1] ?? null;
}

it('pre-selects Lulus for a kelas at the terminal tingkat of its jenjang', function () {
    [$manager, $lembaga] = siapkanKenaikanKelasUxUser();
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasTingkatAkhir = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id, 'nama' => 'Kelas 6 Akhir', 'tingkat' => '6']);
    $kelasBukanTingkatAkhir = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id, 'nama' => 'Kelas 3 Biasa', 'tingkat' => '3']);

    $response = $this->actingAs($manager)->get(route('admin.kenaikan-kelas.index', ['tahun_ajaran_id' => $tahunLalu->id]));
    $response->assertOk();
    $html = $response->getContent();

    $selectTingkatAkhir = htmlSelectByName($html, "mapping[{$kelasTingkatAkhir->id}][tindakan]");
    $selectBukanTingkatAkhir = htmlSelectByName($html, "mapping[{$kelasBukanTingkatAkhir->id}][tindakan]");

    expect($selectTingkatAkhir)->not->toBe('');
    expect($selectBukanTingkatAkhir)->not->toBe('');
    expect(selectedOptionValue($selectTingkatAkhir))->toBe('lulus');
    expect(selectedOptionValue($selectBukanTingkatAkhir))->toBe('naik');
});
```

- [x] **Step 3: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/Akademik/KenaikanKelasControllerUxTest.php --compact`
Expected: FAIL — `selectedOptionValue($selectTingkatAkhir)` masih `null`/`'naik'` karena belum ada logic pre-select.

- [x] **Step 4: Eager-load `lembaga` di controller**

Edit `app/Http/Controllers/Admin/KenaikanKelasController.php`, ubah `kelasLamaList` (baris 29-31) dari:

```php
            'kelasLamaList' => $tahunAjaranId
                ? Kelas::where('tahun_ajaran_id', $tahunAjaranId)->withCount('siswa')->orderBy('nama')->get()
                : collect(),
```

menjadi:

```php
            'kelasLamaList' => $tahunAjaranId
                ? Kelas::where('tahun_ajaran_id', $tahunAjaranId)->with('lembaga')->withCount('siswa')->orderBy('nama')->get()
                : collect(),
```

- [x] **Step 5: Update view — pre-select "Lulus" untuk tingkat akhir**

Edit `resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php`. Tambah `use` statement Blade di baris paling atas file (baris 1, sebelum `<x-app-layout>`):

```blade
@php use App\Domains\Akademik\Enums\BentukPendidikan; @endphp
```

Ubah blok `<td>` kolom "Tindakan" (baris 76-81) dari:

```blade
                                        <td class="px-4 py-4">
                                            <select name="mapping[{{ $kelasLama->id }}][tindakan]" class="rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                                <option value="naik">Naik Kelas</option>
                                                <option value="lulus">Lulus</option>
                                            </select>
                                        </td>
```

menjadi:

```blade
                                        @php
                                            $isTingkatAkhir = $kelasLama->lembaga
                                                ? BentukPendidikan::from($kelasLama->lembaga->bentuk_pendidikan)->isTingkatAkhir($kelasLama->tingkat)
                                                : false;
                                        @endphp
                                        <td class="px-4 py-4">
                                            <select name="mapping[{{ $kelasLama->id }}][tindakan]" class="rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                                <option value="naik" @selected(! $isTingkatAkhir)>Naik Kelas</option>
                                                <option value="lulus" @selected($isTingkatAkhir)>Lulus</option>
                                            </select>
                                            @if ($isTingkatAkhir)
                                                <p class="mt-1 text-xs text-amber-600">Disarankan: tingkat akhir jenjang</p>
                                            @endif
                                        </td>
```

**PENTING**: `$kelasLama->lembaga->bentuk_pendidikan` mengembalikan STRING (bukan enum) karena `Lembaga` model tidak meng-cast kolom itu ke enum (dikonfirmasi Priority #7 sebelumnya) — panggilan `BentukPendidikan::from(...)` di atas benar menerima string.

- [x] **Step 6: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Feature/Akademik/KenaikanKelasControllerUxTest.php --compact`
Expected: PASS.

Run juga test existing supaya tidak regresi: `php artisan test tests/Feature/Admin/KenaikanKelasControllerTest.php --compact`
Expected: PASS, semua 9 test lama tetap lulus (perubahan Step 4/5 tidak mengubah struktur data yang mereka assert).

- [x] **Step 7: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/KenaikanKelasController.php resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php tests/Feature/Akademik/KenaikanKelasControllerUxTest.php
git commit -m "feat(akademik): saran otomatis Lulus di tingkat akhir pada Kenaikan Kelas"
```

---

### Task 3: Peringatan live kurikulum berbeda (Alpine.js inline)

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php`
- Test: `tests/Feature/Akademik/KenaikanKelasControllerUxTest.php` (tambah test, file sudah dibuat di Task 2)

**Interfaces:**
- Consumes: `$kelasLama->kurikulum` (enum `KurikulumFramework`, nullable), `$kelasBaru->kurikulum` (sama) — sudah ada di model `Kelas`, tidak ada perubahan skema.

- [x] **Step 1: Tulis test yang gagal — server/Blade contract untuk peringatan kurikulum**

Tambahkan di akhir `tests/Feature/Akademik/KenaikanKelasControllerUxTest.php`:

```php
it('renders the kurikulum-asal value and matching data-kurikulum options for the JS warning to compare', function () {
    [$manager, $lembaga] = siapkanKenaikanKelasUxUser();
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLama = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id,
        'nama' => 'Kelas Asal K13', 'tingkat' => '3', 'kurikulum' => 'k13',
    ]);
    $kelasTujuanBerbeda = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id,
        'nama' => 'Kelas Tujuan Merdeka', 'tingkat' => '4', 'kurikulum' => 'merdeka',
    ]);
    $kelasTujuanSama = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id,
        'nama' => 'Kelas Tujuan K13 Juga', 'tingkat' => '4', 'kurikulum' => 'k13',
    ]);

    $response = $this->actingAs($manager)->get(route('admin.kenaikan-kelas.index', [
        'tahun_ajaran_id' => $tahunLalu->id,
        'tahun_ajaran_tujuan_id' => $tahunBaru->id,
    ]));
    $response->assertOk();
    $html = $response->getContent();

    // Existence dulu: kedua kelas tujuan benar-benar muncul di dropdown sebelum assert data attribute-nya.
    expect($html)->toContain('Kelas Tujuan Merdeka');
    expect($html)->toContain('Kelas Tujuan K13 Juga');

    // Server/Blade contract: kurikulum asal ter-serialize benar di x-data baris kelas ini.
    // Cari posisi <tr sungguhan sebelum nama kelas, BUKAN offset karakter tebakan --
    // atribut x-data berisi beberapa baris JS + indentasi Blade, bisa >400 karakter.
    $namaPos = strpos($html, 'Kelas Asal K13');
    expect($namaPos)->not->toBeFalse();
    $trOpenPos = strrpos(substr($html, 0, $namaPos), '<tr');
    expect($trOpenPos)->not->toBeFalse();
    $trChunk = substr($html, $trOpenPos, ($namaPos - $trOpenPos) + 3000);
    expect($trChunk)->toContain('kurikulumAsal');
    expect($trChunk)->toContain('k13');

    // data-kurikulum pada option kelas tujuan sesuai nilai kurikulum sungguhan.
    expect($trChunk)->toContain('data-kurikulum="merdeka"');
    expect($trChunk)->toContain('data-kurikulum="k13"');

    // Expression perbandingan warning ada di markup (bukan typo/operator salah).
    expect($trChunk)->toContain('kurikulumTujuan !== null');
    expect($trChunk)->toContain('kurikulumAsal !== null');
    expect($trChunk)->toContain('kurikulumTujuan !== kurikulumAsal');
});

it('renders kurikulumAsal as null in x-data when the source kelas has no kurikulum value', function () {
    [$manager, $lembaga] = siapkanKenaikanKelasUxUser();
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLamaTanpaKurikulum = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id,
        'nama' => 'Kelas Legacy Tanpa Kurikulum', 'tingkat' => '2', 'kurikulum' => null,
    ]);

    $response = $this->actingAs($manager)->get(route('admin.kenaikan-kelas.index', ['tahun_ajaran_id' => $tahunLalu->id]));
    $response->assertOk();
    $html = $response->getContent();

    $namaPos = strpos($html, 'Kelas Legacy Tanpa Kurikulum');
    expect($namaPos)->not->toBeFalse();
    $trOpenPos = strrpos(substr($html, 0, $namaPos), '<tr');
    expect($trOpenPos)->not->toBeFalse();
    $trChunk = substr($html, $trOpenPos, ($namaPos - $trOpenPos) + 3000);
    expect($trChunk)->toContain('kurikulumAsal');
    expect($trChunk)->toContain('null');
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/Akademik/KenaikanKelasControllerUxTest.php --compact`
Expected: FAIL — `data-kurikulum`, `kurikulumAsal`, dan expression warning belum ada di markup.

- [x] **Step 3: Update view — tambah `x-data` per baris + data attribute + elemen peringatan**

Edit `resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php`. Ubah pembuka `<tr>` di dalam `@foreach ($kelasLamaList as $kelasLama)` (saat ini `<tr class="transition hover:bg-gray-50/60">`) menjadi:

```blade
                                    <tr class="transition hover:bg-gray-50/60" x-data="{
                                        kurikulumAsal: {{ Js::from($kelasLama->kurikulum?->value) }},
                                        kurikulumTujuan: null,
                                        tingkatTujuan: null,
                                        onKelasTujuanChange(event) {
                                            const opt = event.target.selectedOptions[0];
                                            this.kurikulumTujuan = opt?.dataset.kurikulum || null;
                                            this.tingkatTujuan = opt?.dataset.tingkat || null;
                                        },
                                    }">
```

Ubah blok `<td>` kolom "Kelas Tujuan" (baris 82-89 sebelum Task 2 menambah kolom Tindakan di atasnya — cari `<select name="mapping[{{ $kelasLama->id }}][kelas_baru_id]"`) dari:

```blade
                                        <td class="px-4 py-4">
                                            <select name="mapping[{{ $kelasLama->id }}][kelas_baru_id]" class="rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                                <option value="">—</option>
                                                @foreach ($kelasTujuanList as $kelasBaru)
                                                    <option value="{{ $kelasBaru->id }}">{{ $kelasBaru->nama }}</option>
                                                @endforeach
                                            </select>
                                        </td>
```

menjadi:

```blade
                                        <td class="px-4 py-4">
                                            <select name="mapping[{{ $kelasLama->id }}][kelas_baru_id]" x-on:change="onKelasTujuanChange($event)" class="rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                                <option value="">—</option>
                                                @foreach ($kelasTujuanList as $kelasBaru)
                                                    <option value="{{ $kelasBaru->id }}" data-kurikulum="{{ $kelasBaru->kurikulum?->value }}" data-tingkat="{{ $kelasBaru->tingkat }}">{{ $kelasBaru->nama }}</option>
                                                @endforeach
                                            </select>
                                            <p x-show="tingkatTujuan !== null" class="mt-1 text-xs text-gray-400" x-text="'Tingkat tujuan: ' + tingkatTujuan"></p>
                                            <p x-show="kurikulumTujuan !== null && kurikulumAsal !== null && kurikulumTujuan !== kurikulumAsal"
                                               class="mt-1 text-xs font-medium text-amber-600"
                                               x-text="'⚠ Kurikulum berbeda: kelas asal ' + kurikulumAsal + ', kelas tujuan ' + kurikulumTujuan"></p>
                                        </td>
```

Juga tambahkan tingkat kelas asal sbg info di kolom "Kelas Lama" (baris `<td class="px-6 py-4 font-bold text-gray-900">{{ $kelasLama->nama }}</td>`) — ubah jadi:

```blade
                                        <td class="px-6 py-4 font-bold text-gray-900">{{ $kelasLama->nama }} <span class="text-xs font-normal text-gray-400">(Tingkat {{ $kelasLama->tingkat ?? '-' }})</span></td>
```

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Feature/Akademik/KenaikanKelasControllerUxTest.php --compact`
Expected: PASS, semua 3 test (pre-select + 2 kurikulum) lulus.

- [x] **Step 5: Jalankan seluruh test scoped Kelompok B sbg checkpoint akhir**

Run: `php artisan test tests/Unit/Domains/Akademik/Enums/BentukPendidikanTest.php tests/Feature/Akademik/RaporPdfDataBuilderIsTingkatAkhirTest.php tests/Feature/Akademik/RaporPdfDataBuilderTest.php tests/Feature/Akademik/KenaikanKelasControllerUxTest.php tests/Feature/Admin/KenaikanKelasControllerTest.php --compact`
Expected: 0 failed. Catat angka pasti di laporan akhir. (Full suite TIDAK dijalankan di sini — sesuai Global Constraints, ditunda sampai checkpoint gabungan akhir Kelompok C.)

- [x] **Step 6: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php tests/Feature/Akademik/KenaikanKelasControllerUxTest.php
git commit -m "feat(akademik): peringatan live kurikulum berbeda saat kenaikan kelas"
```

- [x] **Step 7: Catat penyelesaian Kelompok B di PETA_PENGEMBANGAN.md**

Baca dulu bagian "Audit Sistematis Akademik Tahap 2" yang sudah ada (ditambahkan saat Kelompok A selesai), lalu update baris Kelompok B dari "🟡 Menunggu pengerjaan terpisah" jadi "✅ SELESAI (tanggal hari ini)" dengan ringkasan singkat (saran otomatis Lulus + peringatan kurikulum, keduanya non-blocking; temuan #3 dikonfirmasi bukan gap nyata).

```bash
git add PETA_PENGEMBANGAN.md
git commit -m "docs: catat penyelesaian Kelompok B audit sistematis tahap 2 akademik"
```
