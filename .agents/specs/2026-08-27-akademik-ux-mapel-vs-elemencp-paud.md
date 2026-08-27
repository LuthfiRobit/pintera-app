# Kejelasan UI Mata Pelajaran vs ElemenCp untuk PAUD (Priority 7) — Design Spec

**Tanggal**: 2026-08-27
**Branch**: `akademik-v2`
**Konteks roadmap**: `PETA_PENGEMBANGAN.md` §"🔵 Roadmap Kurikulum Dinamis", Prioritas #7 — independen, effort Kecil (UX polish), tidak mengganggu prioritas lain.

---

## 1. Latar Belakang & Masalah

Sejak `TipeMataPelajaran::AspekPerkembangan` dihapus total (`TD-AKADEMIK-001`), admin lembaga PAUD yang buka menu "Mata Pelajaran" tidak mendapat indikasi apa pun bahwa aspek perkembangan/Capaian Pembelajaran (CP) untuk PAUD dikelola lewat `ElemenCp` via menu Komponen Penilaian — bukan lewat Mata Pelajaran. Ini murni UX polish, tidak ada bug fungsional.

## 2. Keputusan Desain (dari diskusi user)

1. **Banner tampil HANYA untuk lembaga PAUD** (`bentuk_pendidikan` ∈ `KB`, `TPA`, `SPS`, `TK`) — TIDAK tampil sama sekali utk SD/SMP/SMA/SMK/SLB.
2. **Wording informatif, bukan menyalahkan** — menjelaskan APA yang harus dilakukan (kelola lewat Elemen CP di Komponen Penilaian), bukan menyatakan menu Mata Pelajaran "tidak berlaku"/"salah" utk PAUD. Teks final (WAJIB dipakai persis):

   > **Catatan untuk PAUD**
   > Untuk PAUD, aspek perkembangan/Capaian Pembelajaran dikelola melalui **Elemen CP** pada Komponen Penilaian, bukan melalui menu Mata Pelajaran.
   > **Kelola Komponen Penilaian →**

3. **Link langsung** ke `route('admin.komponen-penilaian.index')` (dikonfirmasi persis: `routes/admin/penilaian-rapor.php:8`, di dalam group `prefix('admin')->name('admin.')` — `routes/admin.php:5,16`).
4. **Controller mengirim boolean `isPaud`, bukan raw `bentuk_pendidikan`** — View tidak melakukan daftar enum sendiri, logic "apa saja yang dianggap PAUD" hidup satu tempat di controller.

**Verifikasi tipe data (bukan asumsi)**: `Lembaga::casts()` (`app/Models/Lembaga.php:32-46`) TIDAK meng-cast `bentuk_pendidikan` ke enum apa pun — kolom ini tetap `string` polos. Jadi `auth()->user()->lembaga?->bentuk_pendidikan` mengembalikan `string`, dan perbandingan `in_array(..., [BentukPendidikan::Kb->value, ...], true)` di §3 di bawah SUDAH BENAR apa adanya — tidak perlu diubah jadi perbandingan enum instance.

## 3. Perubahan #1 — Controller

`app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php` — tambahkan import:

```php
use App\Domains\Akademik\Enums\BentukPendidikan;
```

Ubah `index()` — tambah 1 key ke array data view (setelah `'countKurikulum' => ...`):

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

`$paginated`, `$tipeList`, `$kelompokList`, `$statusList`, `$perPage`, `$totalMapel`, `$countKurikulum` TIDAK BERUBAH. Method `create()`/`store()`/`edit()`/`update()` TIDAK DISENTUH.

## 4. Perubahan #2 — View

`resources/views/portals/lembaga/akademik/mata-pelajaran/index.blade.php` — tambahkan banner TEPAT SETELAH blok "Header & Breadcrumb" (baris 20), SEBELUM "KPI Compact Horizontal Statistic Cards" (baris 22):

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

## 5. Non-Goals (eksplisit di luar scope)

- Tidak menyentuh halaman/controller Komponen Penilaian itu sendiri.
- Tidak mengubah behavior/validasi Mata Pelajaran apa pun (murni tambahan UI informatif).
- Tidak ada migration/perubahan skema.
- Tidak menambah helper baru di enum `BentukPendidikan` (`validTingkatValues()` sudah ada, cukup; grouping "PAUD" cukup inline `in_array` di controller, bukan method baru — 4 nilai literal ini cukup jelas tanpa abstraksi tambahan).

## 6. Testing (acceptance criteria wajib)

1. **Lembaga PAUD (mis. `bentuk_pendidikan=TK`) melihat banner** — GET `admin.mata-pelajaran.index` → response mengandung teks `"Catatan untuk PAUD"`, `"Elemen CP"` (assertion terpisah, bukan cuma keberadaan div — memastikan tujuan UX-nya, bukan cuma markup kosong), dan link ke `route('admin.komponen-penilaian.index')`.
2. **Lembaga non-PAUD (mis. `bentuk_pendidikan=SD`) TIDAK melihat banner** — response TIDAK mengandung teks `"Catatan untuk PAUD"`.
3. Ulangi test #1 utk KETIGA `bentuk_pendidikan` PAUD lain (`KB`, `TPA`, `SPS`) — via Pest dataset (`->with([...])`), bukan cuma `TK`, supaya `in_array` di controller benar-benar teruji utk semua 4 nilai, bukan cuma 1 representative case.

## 7. Ringkasan Alur

```text
MataPelajaranController::index()
    │
    ├── isPaud = bentuk_pendidikan lembaga ∈ {KB, TPA, SPS, TK}
    │
    ▼
index.blade.php
    │
    └── @if ($isPaud)
           Banner: "Catatan untuk PAUD" + link ke Komponen Penilaian
        @endif
```
