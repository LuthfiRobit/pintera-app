# TD-AKADEMIK-001 — Hapus `TipeMataPelajaran::AspekPerkembangan` Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Hapus total `TipeMataPelajaran::AspekPerkembangan` — CRUD yang berfungsi tapi tidak terintegrasi ke defaulting `assessment_type` (bug laten) dan tidak pernah dipakai data nyata — sampai level `ENUM` database, menjadikan `ElemenCp` satu-satunya jalur resmi penilaian PAUD non-formal-subject.

**Architecture:** 1 migration baru (sempitkan `ENUM` DB dgn guard defense-in-depth) + hapus 1 case enum PHP + sesuaikan 1 controller + 2 view + 3 file test. Tidak ada migrasi/backfill data (dikonfirmasi nol row terpakai).

**Tech Stack:** Laravel 12.63.0, Pest, MySQL 8.0.30.

**Bergantung pada:** Sprint 1-5 + `TD-AKADEMIK-002` (SELESAI semua). `ElemenCp` TIDAK disentuh sama sekali.

**Spec:** `.agents/specs/2026-08-27-td-akademik-001-hapus-aspek-perkembangan.md`

## Global Constraints

- **Migration WAJIB guard eksplisit** — cek nol row `tipe='aspek_perkembangan'` SEBELUM `ALTER TABLE`. Kalau ternyata ada row (baseline environment beda dari asumsi spec), migration GAGAL KERAS dgn `RuntimeException` pesan jelas — JANGAN diam-diam menghapus/mengubah row itu.
- **`ElemenCp` (model, migration, seeder, test) TIDAK DISENTUH SAMA SEKALI.**
- Test `'allows nullable kelompok for aspek perkembangan paud'` DIADAPTASI (ganti ke `TipeMataPelajaran::Mapel`), BUKAN dihapus — konsep "kelompok boleh null" berlaku umum, bukan spesifik `aspek_perkembangan`.
- TIDAK menambah kolom/field baru, TIDAK mengerjakan STPPA 6-aspek, TIDAK menyentuh `KelompokMataPelajaran` — semua di luar scope (lihat spec §Non-Goals).
- Jalankan test scoped di tiap task; full suite HANYA di task terakhir.

---

### Task 1: Migration — Sempitkan `ENUM` `mata_pelajaran.tipe`

**Files:**
- Create: `database/migrations/2026_08_27_100000_remove_aspek_perkembangan_from_mata_pelajaran_tipe.php`
- Test: `tests/Unit/Migrations/RemoveAspekPerkembanganFromMataPelajaranTipeTest.php`

**Interfaces:**
- Produces: kolom `mata_pelajaran.tipe` jadi `ENUM('mapel')` — dipakai implisit oleh seluruh Task 2 (validasi `in:mapel` di controller sekarang selaras dgn skema DB).

- [ ] **Step 1: Test migration (RED dulu — migration belum ada, tabel masih permisif 2 nilai)**

```php
<?php
// tests/Unit/Migrations/RemoveAspekPerkembanganFromMataPelajaranTipeTest.php

use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Lembaga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('rejects inserting aspek_perkembangan into mata_pelajaran.tipe after the migration', function () {
    expect(fn () => DB::table('mata_pelajaran')->insert([
        'lembaga_id' => Lembaga::factory()->create()->id,
        'kode' => 'TEST-01',
        'nama' => 'Uji Enum',
        'no_urut' => 1,
        'tipe' => 'aspek_perkembangan',
        'status' => 'aktif',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

it('still allows mapel as a valid tipe value', function () {
    $lembaga = Lembaga::factory()->create();

    $mapel = MataPelajaran::create([
        'lembaga_id' => $lembaga->id,
        'kode' => 'TEST-02',
        'nama' => 'Uji Enum Valid',
        'no_urut' => 1,
        'tipe' => 'mapel',
        'status' => 'aktif',
    ]);

    expect($mapel->fresh()->tipe)->toBe(\App\Enums\TipeMataPelajaran::Mapel);
});
```

Run: `php artisan test --filter=RemoveAspekPerkembanganFromMataPelajaranTipeTest`
Expected: test pertama FAIL (insert `aspek_perkembangan` masih berhasil krn ENUM DB belum disempitkan), test kedua PASS (tidak terpengaruh).

- [ ] **Step 2: Implementasi migration**

```php
<?php
// database/migrations/2026_08_27_100000_remove_aspek_perkembangan_from_mata_pelajaran_tipe.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $jumlahTerpakai = DB::table('mata_pelajaran')->where('tipe', 'aspek_perkembangan')->count();

        if ($jumlahTerpakai > 0) {
            throw new \RuntimeException(
                "Migration dibatalkan: ditemukan {$jumlahTerpakai} baris mata_pelajaran dengan tipe='aspek_perkembangan'. " .
                "Migration ini mengasumsikan nol baris terpakai (dikonfirmasi kosong di seluruh seeder demo saat spec ditulis). " .
                "Selesaikan migrasi data baris tersebut secara manual (pilih konsolidasi ke ElemenCp atau tipe lain) sebelum menjalankan migration ini."
            );
        }

        DB::statement("ALTER TABLE mata_pelajaran MODIFY COLUMN tipe ENUM('mapel') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE mata_pelajaran MODIFY COLUMN tipe ENUM('mapel', 'aspek_perkembangan') NOT NULL");
    }
};
```

- [ ] **Step 3: Jalankan test lagi**

Run: `php artisan test --filter=RemoveAspekPerkembanganFromMataPelajaranTipeTest`
Expected: PASS (2/2).

- [ ] **Step 4: `php -l` dan commit**

```bash
php -l database/migrations/2026_08_27_100000_remove_aspek_perkembangan_from_mata_pelajaran_tipe.php
git add database/migrations/2026_08_27_100000_remove_aspek_perkembangan_from_mata_pelajaran_tipe.php tests/Unit/Migrations/RemoveAspekPerkembanganFromMataPelajaranTipeTest.php
git commit -m "refactor(akademik): sempitkan ENUM mata_pelajaran.tipe jadi cuma 'mapel', dgn guard defense-in-depth"
```

---

### Task 2: Hapus dari Kode Aplikasi (Enum, Controller, View, Test)

**Files:**
- Modify: `app/Enums/TipeMataPelajaran.php`
- Modify: `app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php`
- Modify: `resources/views/portals/lembaga/akademik/mata-pelajaran/_form.blade.php`
- Modify: `resources/views/portals/lembaga/akademik/mata-pelajaran/index.blade.php`
- Modify: `tests/Unit/Enums/AcademicEnumsTest.php`
- Modify: `tests/Feature/Admin/MataPelajaranCrudTest.php`
- Modify: `tests/Unit/Models/MataPelajaranTest.php`

**Interfaces:**
- Consumes: hasil Task 1 (skema DB sudah `ENUM('mapel')`).
- Produces: `TipeMataPelajaran` cuma punya 1 case (`Mapel`) — konsisten dgn skema DB dari Task 1.

**Catatan urutan**: HARUS 1 task/commit (bukan dipecah lagi) — menghapus case enum SEBELUM mengupdate controller/view yang mereferensikannya akan membuat kode gagal compile/runtime error di antara langkah. Semua sub-langkah di bawah dikerjakan berurutan tapi di-commit SEKALI di akhir.

- [ ] **Step 1: Baca ulang seluruh file yang akan diubah (WAJIB — verifikasi baseline)**

Baca `app/Enums/TipeMataPelajaran.php`, `app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php`, `resources/views/portals/lembaga/akademik/mata-pelajaran/_form.blade.php`, `resources/views/portals/lembaga/akademik/mata-pelajaran/index.blade.php` — bandingkan dgn kutipan di spec §2-§4. Kalau berbeda, STOP dan laporkan ke user.

- [ ] **Step 2: Ubah test dulu (RED — sesuai TDD, tapi di sini "RED" berarti test yang lama akan FAIL setelah Step 3 mengubah enum, jadi test diubah lebih dulu supaya jelas targetnya)**

`tests/Unit/Enums/AcademicEnumsTest.php` — ganti:
```php
it('defines the expected TipeMataPelajaran cases', function () {
    expect(array_column(TipeMataPelajaran::cases(), 'value'))
        ->toBe(['mapel', 'aspek_perkembangan']);
});
```
menjadi:
```php
it('defines the expected TipeMataPelajaran cases', function () {
    expect(array_column(TipeMataPelajaran::cases(), 'value'))
        ->toBe(['mapel']);
});
```

`tests/Feature/Admin/MataPelajaranCrudTest.php` — cari test `'calculates executive KPI statistics accurately in index view'`, ganti isinya dari:
```php
    MataPelajaran::create([
        'lembaga_id' => $lembaga->id,
        'kode' => 'SD-01',
        'nama' => 'Matematika SD',
        'no_urut' => 1,
        'tipe' => TipeMataPelajaran::Mapel->value,
        'kelompok' => KelompokMataPelajaran::Umum->value,
        'status' => StatusMataPelajaran::Aktif->value,
    ]);
    MataPelajaran::create([
        'lembaga_id' => $lembaga->id,
        'kode' => 'PAUD-01',
        'nama' => 'Motorik Halus',
        'no_urut' => 2,
        'tipe' => TipeMataPelajaran::AspekPerkembangan->value,
        'kelompok' => null,
        'status' => StatusMataPelajaran::Aktif->value,
    ]);

    $response = $this->actingAs($manager)->get(route('admin.mata-pelajaran.index'));
    $response->assertOk();
    $response->assertViewHas('totalMapel', 2);
    $response->assertViewHas('countKurikulum', 1);
    $response->assertViewHas('countAspek', 1);
});
```
menjadi:
```php
    MataPelajaran::create([
        'lembaga_id' => $lembaga->id,
        'kode' => 'SD-01',
        'nama' => 'Matematika SD',
        'no_urut' => 1,
        'tipe' => TipeMataPelajaran::Mapel->value,
        'kelompok' => KelompokMataPelajaran::Umum->value,
        'status' => StatusMataPelajaran::Aktif->value,
    ]);

    $response = $this->actingAs($manager)->get(route('admin.mata-pelajaran.index'));
    $response->assertOk();
    $response->assertViewHas('totalMapel', 1);
    $response->assertViewHas('countKurikulum', 1);
});
```

`tests/Unit/Models/MataPelajaranTest.php` — ganti:
```php
it('allows nullable kelompok for aspek perkembangan paud', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $mapel = MataPelajaran::create([
        'lembaga_id' => $lembaga->id,
        'kode' => 'PAUD-01',
        'nama' => 'Nilai Agama dan Moral',
        'no_urut' => 1,
        'tipe' => TipeMataPelajaran::AspekPerkembangan->value,
        'kelompok' => null,
        'status' => StatusMataPelajaran::Aktif->value,
    ]);

    expect($mapel->fresh()->kelompok)->toBeNull();
});
```
menjadi:
```php
it('allows nullable kelompok for a mata pelajaran without a formal kelompok', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $mapel = MataPelajaran::create([
        'lembaga_id' => $lembaga->id,
        'kode' => 'MULOK-01',
        'nama' => 'Muatan Lokal',
        'no_urut' => 1,
        'tipe' => TipeMataPelajaran::Mapel->value,
        'kelompok' => null,
        'status' => StatusMataPelajaran::Aktif->value,
    ]);

    expect($mapel->fresh()->kelompok)->toBeNull();
});
```

- [ ] **Step 3: Ubah enum PHP**

`app/Enums/TipeMataPelajaran.php` — ganti seluruh isi jadi:
```php
<?php

namespace App\Enums;

enum TipeMataPelajaran: string
{
    case Mapel = 'mapel';

    public function label(): string
    {
        return match ($this) {
            self::Mapel => 'Mata Pelajaran',
        };
    }
}
```

- [ ] **Step 4: Ubah controller**

`app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php`:

Hapus baris (di `index()`):
```php
            'countAspek'        => MataPelajaran::where('tipe', TipeMataPelajaran::AspekPerkembangan->value)->count(),
```

Ganti (di `store()` DAN `update()`, 2 tempat):
```php
            'tipe' => ['required', 'in:mapel,aspek_perkembangan'],
```
menjadi:
```php
            'tipe' => ['required', 'in:mapel'],
```

- [ ] **Step 5: Ubah view `_form.blade.php`**

Hapus baris:
```blade
                    <option value="aspek_perkembangan" @selected($val('tipe') === 'aspek_perkembangan')>Aspek Perkembangan (PAUD / TK)</option>
```
(Baris `<option value="mapel" ...>` di atasnya TETAP ADA.)

- [ ] **Step 6: Ubah view `index.blade.php`**

Hapus card ke-3 (blok lengkap di bawah ini, termasuk komentar `{{-- KPI Compact... --}}` di atasnya TETAP ADA — cuma card ke-3 yang dihapus):
```blade
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <x-icon name="extension" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-amber-600">Aspek Perkembangan</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $countAspek ?? 0 }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">PAUD / TK</span>
            </div>
```

Ganti baris pembungkus grid dari:
```blade
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
```
menjadi:
```blade
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
```
(Filter dropdown "Tipe Kurikulum" yang loop `@foreach ($tipeList as $item)` TIDAK diubah — otomatis menyesuaikan begitu `TipeMataPelajaran::cases()` cuma 1 item.)

- [ ] **Step 7: Jalankan test scoped**

```bash
php artisan test --filter=AcademicEnumsTest
php artisan test --filter=MataPelajaranCrudTest
php artisan test --filter=MataPelajaranTest
```
Expected: semua PASS. Kalau ada test LAIN (di luar 3 file ini) yang ternyata juga mereferensikan `AspekPerkembangan` dan gagal compile/fail, STOP — itu tandanya audit sebelumnya kurang lengkap, laporkan ke user (jangan asal hapus test tsb).

- [ ] **Step 8: `php -l` dan commit**

```bash
php -l app/Enums/TipeMataPelajaran.php
php -l app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php
git add app/Enums/TipeMataPelajaran.php app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php resources/views/portals/lembaga/akademik/mata-pelajaran/_form.blade.php resources/views/portals/lembaga/akademik/mata-pelajaran/index.blade.php tests/Unit/Enums/AcademicEnumsTest.php tests/Feature/Admin/MataPelajaranCrudTest.php tests/Unit/Models/MataPelajaranTest.php
git commit -m "refactor(akademik): hapus TipeMataPelajaran::AspekPerkembangan dari kode aplikasi (enum, controller, view, test)"
```

---

### Task 3: Regresi Penuh

**Files:** Tidak ada file baru — task verifikasi murni.

- [ ] **Step 1: Grep verifikasi akhir — nol referensi tersisa**

```bash
grep -rln "AspekPerkembangan\|aspek_perkembangan" --include=*.php --include=*.blade.php .
```
Expected: nol hasil di file `.php`/`.blade.php` aktif (kalau ada hasil di `.agents/`/`docs/` — itu arsip historis, BOLEH tetap ada, JANGAN diubah).

- [ ] **Step 2: Jalankan full test suite (TANPA filter), sekali, foreground, tidak ada proses lain berjalan bersamaan**

Run: `php artisan test`
Expected: 0 failed. Baseline sebelum task ini adalah **2238 passed, 4 skipped** (state akhir `TD-AKADEMIK-002`). Task 1 menambah 2 test baru, Task 2 mengubah 3 test existing tanpa menambah/mengurangi jumlah. Laporkan angka NYATA, jangan asumsikan.

- [ ] **Step 3: Migrasi database dev nyata (Laragon/MySQL, bukan cuma test database)**

```bash
php artisan migrate
```
(Bukan `migrate:fresh` — dev database sudah berisi data nyata, migration baru sudah dipastikan aman lewat guard di Task 1.)

- [ ] **Step 4: Laporkan hasil final ke user**

Ringkasan: jumlah test pass/fail (angka pasti), commit hash tiap task (2 commit), konfirmasi grep nol referensi, konfirmasi migrasi dev database sukses, konfirmasi `ElemenCp` tidak tersentuh sama sekali.

## Self-Review

- Cakupan spec: §1 (migration+guard) → Task 1; §2 (enum) → Task 2 Step 3; §3 (controller) → Task 2 Step 4; §4 (view) → Task 2 Step 5-6; §5 (test) → Task 2 Step 2; §Non-Goals → dipatuhi (tidak ada task yang menyentuh `ElemenCp`/STPPA/`KelompokMataPelajaran`).
- Placeholder scan: tidak ada. Semua kode lengkap, termasuk isi test before/after dikutip persis.
- Konsistensi: `TipeMataPelajaran::cases()` cuma 1 item setelah Task 2 Step 3 — filter dropdown di `index.blade.php` (Task 2 Step 6, tidak diubah kodenya) otomatis konsisten karena loop generik.
- Urutan task memperhitungkan dependency: Task 2 HARUS 1 commit (enum+controller+view+test bersamaan) krn menghapus case enum akan merusak compile kalau controller/view yang mereferensikannya belum diupdate di commit yang sama — ditandai eksplisit di "Catatan urutan" Task 2.
