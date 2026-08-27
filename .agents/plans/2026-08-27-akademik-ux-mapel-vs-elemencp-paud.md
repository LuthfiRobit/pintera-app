# UX Mata Pelajaran vs ElemenCp PAUD Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Tambah banner informatif di halaman Mata Pelajaran, tampil HANYA untuk lembaga PAUD, menjelaskan bahwa aspek perkembangan dikelola lewat Elemen CP di Komponen Penilaian.

**Architecture:** `MataPelajaranController::index()` menghitung boolean `isPaud` dari `bentuk_pendidikan` lembaga user, dikirim ke view. `index.blade.php` render banner kondisional berdasar boolean itu, dengan link ke halaman Komponen Penilaian.

**Tech Stack:** Laravel 12.63.0, Pest v4, MySQL 8.0.30. Tidak ada migration, tidak ada perubahan skema.

## Global Constraints

- Banner tampil HANYA kalau `bentuk_pendidikan` ∈ `KB`, `TPA`, `SPS`, `TK` — TIDAK PERNAH utk SD/SMP/SMA/SMK/SLB.
- Wording banner PERSIS: judul `"Catatan untuk PAUD"`, isi menyebut `"Elemen CP"` secara eksplisit, teks `"Kelola Komponen Penilaian"` sbg link.
- Link mengarah ke `route('admin.komponen-penilaian.index')` (sudah diverifikasi ada di `routes/admin/penilaian-rapor.php:8`).
- Controller mengirim boolean `isPaud`, BUKAN raw `bentuk_pendidikan` — view tidak melakukan daftar enum sendiri.
- `bentuk_pendidikan` di model `Lembaga` TIDAK di-cast enum (sudah diverifikasi di `Lembaga::casts()`) — tetap `string` polos, jadi perbandingan pakai `BentukPendidikan::Kb->value` dkk (string), BUKAN instance enum.
- Tidak ada migration baru, tidak ada perubahan skema, tidak ada helper baru di enum `BentukPendidikan` (tidak perlu method `isPaud()` baru di enum itu sendiri).

---

## Task 1: Banner PAUD di Halaman Mata Pelajaran

**Files:**
- Modify: `app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php`
- Modify: `resources/views/portals/lembaga/akademik/mata-pelajaran/index.blade.php`
- Test: `tests/Feature/Admin/MataPelajaranCrudTest.php`

**Interfaces:**
- Produces: view `portals.lembaga.akademik.mata-pelajaran.index` menerima key baru `isPaud: bool` — tidak ada interface lain yang berubah.

- [x] **Step 1: Tulis test (akan gagal — banner belum ada)**

Tambahkan ke akhir `tests/Feature/Admin/MataPelajaranCrudTest.php`:

```php
it('shows the PAUD note banner with a link to Komponen Penilaian for KB/TPA/SPS/TK', function (string $bentukPendidikan) {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => $bentukPendidikan]);
    $manager = actingAsMataPelajaranManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.mata-pelajaran.index'));

    $response->assertOk();
    $response->assertSee('Catatan untuk PAUD');
    $response->assertSee('Elemen CP');
    $response->assertSee(e(route('admin.komponen-penilaian.index')), false);
})->with(['KB', 'TPA', 'SPS', 'TK']);

it('does not show the PAUD note banner for a non-PAUD bentuk_pendidikan', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $manager = actingAsMataPelajaranManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.mata-pelajaran.index'));

    $response->assertOk();
    $response->assertDontSee('Catatan untuk PAUD');
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/Admin/MataPelajaranCrudTest.php --filter="PAUD"`
Expected: FAIL — `assertSee('Catatan untuk PAUD')` gagal (banner belum ada di view). Test kedua (`'does not show...'`) kemungkinan PASS kebetulan (krn banner memang belum ada sama sekali) — itu wajar, akan tetap PASS setelah fix krn memang tidak boleh muncul utk SD.

- [x] **Step 3: Tambah `isPaud` ke controller**

Baca dulu `app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php` baris 1-68 penuh sebelum edit, konfirmasi struktur masih sama seperti kutipan di bawah. Tambahkan import setelah `use App\Domains\Akademik\DataTransferObjects\MataPelajaranData;`:

```php
use App\Domains\Akademik\Enums\BentukPendidikan;
```

Ubah `index()` — tambahkan key `isPaud` ke array yang dioper ke `view('portals.lembaga.akademik.mata-pelajaran.index', [...])`, setelah `'countKurikulum' => MataPelajaran::where('tipe', TipeMataPelajaran::Mapel->value)->count(),`:

```php
            'isPaud' => in_array(
                auth()->user()->lembaga?->bentuk_pendidikan,
                [
                    BentukPendidikan::Kb->value,
                    BentukPendidikan::Tpa->value,
                    BentukPendidikan::Sps->value,
                    BentukPendidikan::Tk->value,
                ],
                true
            ),
```

Method `create()`/`store()`/`edit()`/`update()` TIDAK DISENTUH.

- [x] **Step 4: Tambah banner ke view**

Baca dulu `resources/views/portals/lembaga/akademik/mata-pelajaran/index.blade.php` baris 1-22 penuh sebelum edit. Tambahkan blok berikut TEPAT SETELAH baris `</div>` penutup blok "Header & Breadcrumb" (baris 20), SEBELUM komentar `{{-- KPI Compact Horizontal Statistic Cards ... --}}` (baris 22):

```blade

        {{-- Catatan PAUD: aspek perkembangan dikelola lewat Elemen CP, bukan Mata Pelajaran --}}
        @if ($isPaud)
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-800">
                <p class="font-semibold">Catatan untuk PAUD</p>
                <p class="mt-1">Untuk PAUD, aspek perkembangan/Capaian Pembelajaran dikelola melalui <strong>Elemen CP</strong> pada Komponen Penilaian, bukan melalui menu Mata Pelajaran.</p>
                <a href="{{ route('admin.komponen-penilaian.index') }}" class="mt-2 inline-block font-semibold text-indigo-700 hover:text-indigo-900">
                    Kelola Komponen Penilaian &rarr;
                </a>
            </div>
        @endif
```

Tidak ada baris lain di file yang berubah.

- [x] **Step 5: Jalankan test, pastikan semua lulus**

Run: `php artisan test tests/Feature/Admin/MataPelajaranCrudTest.php`
Expected: PASS (semua test di file, termasuk 4 dataset PAUD + 1 test non-PAUD + semua test existing lain di file yang sama tidak terpengaruh)

- [x] **Step 6: Lint & commit**

Run: `php -l app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php`
Expected: `No syntax errors detected`

```bash
git add app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php resources/views/portals/lembaga/akademik/mata-pelajaran/index.blade.php tests/Feature/Admin/MataPelajaranCrudTest.php
git commit -m "feat(akademik): tambah banner PAUD di halaman Mata Pelajaran, arahkan ke Komponen Penilaian"
```

---

## Task 2: Regresi Penuh & Update Roadmap

**Files:**
- Modify: `PETA_PENGEMBANGAN.md`

- [x] **Step 1: Jalankan full test suite tanpa filter**

Run: `php artisan test --compact`
Expected: 0 failed. Catat angka pasti (passed/skipped/assertions).

- [x] **Step 2: Update `PETA_PENGEMBANGAN.md`**

Di bagian `## 🔵 Roadmap Kurikulum Dinamis`, ubah baris tabel Prioritas #7 kolom "Status" dari `Belum Ada` menjadi:

```
✅ SELESAI (27 Agustus 2026) — lihat `.agents/specs/2026-08-27-akademik-ux-mapel-vs-elemencp-paud.md`
```

Tambahkan paragraf baru setelah tabel prioritas:

```markdown
**Prioritas #7 SELESAI (27 Agustus 2026)**: Halaman Mata Pelajaran sekarang menampilkan banner "Catatan untuk PAUD" (hanya utk lembaga `KB`/`TPA`/`SPS`/`TK`) yang menjelaskan aspek perkembangan dikelola lewat Elemen CP di Komponen Penilaian, dengan link langsung ke halaman itu. Seluruh 7 prioritas Roadmap Kurikulum Dinamis kini tuntas ditangani (1/2/3/6/7 SELESAI, 4/5 sengaja ditunda menunggu pelanggan nyata). Dieksekusi lewat `.agents/plans/2026-08-27-akademik-ux-mapel-vs-elemencp-paud.md`.
```

- [x] **Step 3: Commit**

```bash
git add PETA_PENGEMBANGAN.md
git commit -m "docs: tandai Prioritas 7 Roadmap Kurikulum Dinamis SELESAI"
```
