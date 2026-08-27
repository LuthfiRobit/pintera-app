# Audit Sistematis Akademik Tahap 2 — Kelompok B (Kenaikan Kelas UX Safety-Net) — Design Spec

**Tanggal**: 2026-08-27
**Branch**: `akademik-v2`
**Konteks**: Lanjutan dari Kelompok A. Menutup 2 gap UX safety-net pada fitur Kenaikan Kelas — workflow ini murni manual/admin-driven (bukan bug data), sehingga perbaikan berupa saran/peringatan non-blocking, bukan validasi keras yang memblokir submit.

---

## 1. Latar Belakang & Masalah

Audit tahap 2 menemukan 3 gap pada `ProsesKenaikanKelasAction`/`KenaikanKelasController`:

1. Tidak ada peringatan kalau kurikulum kelas tujuan yang dipilih admin berbeda dari kurikulum kelas asal.
2. `isTingkatAkhir()` (sudah ada, private di `RaporPdfDataBuilder.php`, dipakai untuk label rapor "Kenaikan Kelas" vs "Kelulusan") tidak dipakai untuk menyarankan otomatis pilihan "Lulus" di dropdown tindakan saat kelas asal berada di tingkat akhir jenjangnya.
3. **[Sudah diverifikasi BUKAN gap nyata]** — kekhawatiran awal "1 lembaga bisa mencakup 2 jenjang sekaligus (TK+SD)" tidak berlaku di skema ini: `lembaga.bentuk_pendidikan` adalah kolom ENUM TUNGGAL per record (`KB/TPA/SPS/TK/SD/SMP/SMA/SMK/SLB`), dikonfirmasi via query `SELECT bentuk_pendidikan, COUNT(*) FROM lembaga GROUP BY bentuk_pendidikan`. Satu `lembaga_id` secara struktural tidak mungkin punya 2 jenjang. `ProsesKenaikanKelasAction.php:47` (`abort_if($kelasBaru === null || $kelasBaru->lembaga_id !== $kelasLama->lembaga_id, 404)`) sudah menolak kelas tujuan dari `lembaga_id` berbeda — ini otomatis juga menjadi guard lintas-jenjang. **Tidak ada fix yang diperlukan untuk poin ini.**

## 2. Keputusan Desain

### 2.1 — Ekstrak `isTingkatAkhir()` ke enum `BentukPendidikan` (single source of truth)

`app/Domains/Akademik/Enums/BentukPendidikan.php` sudah punya method `validTingkatValues(): array`, tapi method itu MENGELOMPOKKAN `Kb, Tpa, Sps, Tk` jadi satu arm yang sama-sama mengembalikan `['A', 'B']` (untuk keperluan validasi input tingkat). Menggunakan `end($this->validTingkatValues())` secara generik untuk `isTingkatAkhir()` akan SALAH: itu membuat KB/TPA/SPS di tingkat "B" ikut dianggap tingkat akhir, padahal keputusan bisnis yang sudah dikunci di Priority #3 (Kelulusan PAUD & SLB) menyatakan **hanya TK tingkat B** yang dianggap tingkat akhir untuk kelulusan — KB/TPA/SPS dikecualikan permanen berapa pun tingkatnya. Karena itu, method baru TETAP eksplisit per-case (tidak diderivasi generik dari `validTingkatValues()`), supaya perilaku identik persis dengan peta hardcoded lama:

```php
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

Ini menghasilkan perilaku **identik** dengan peta hardcoded lama di `RaporPdfDataBuilder` (TK→B, SD/SLB→6, SMP→9, SMA/SMK→12, KB/TPA/SPS selalu `false`). `RaporPdfDataBuilder::isTingkatAkhir()` (private method, baris 145-157) diubah jadi delegasi:

```php
    private function isTingkatAkhir(?string $bentukPendidikan, ?string $tingkat): bool
    {
        if ($bentukPendidikan === null) {
            return false;
        }

        return BentukPendidikan::from($bentukPendidikan)->isTingkatAkhir($tingkat);
    }
```

Signature method private ini TIDAK BERUBAH (dipanggil secara internal saja) — perubahan murni implementasi, bukan API.

### 2.2 — Saran otomatis "Lulus" di dropdown tindakan

`KenaikanKelasController::index()` eager-load relasi `lembaga` pada `kelasLamaList`:

```php
'kelasLamaList' => $tahunAjaranId
    ? Kelas::where('tahun_ajaran_id', $tahunAjaranId)->with('lembaga')->withCount('siswa')->orderBy('nama')->get()
    : collect(),
```

View `kenaikan-kelas/index.blade.php` menghitung per baris apakah kelas lama tingkat akhir, lalu pre-select opsi "Lulus":

```blade
@php
    $isTingkatAkhir = $kelasLama->lembaga
        ? \App\Domains\Akademik\Enums\BentukPendidikan::from($kelasLama->lembaga->bentuk_pendidikan)->isTingkatAkhir($kelasLama->tingkat)
        : false;
@endphp
<select name="mapping[{{ $kelasLama->id }}][tindakan]" ...>
    <option value="naik" @selected(! $isTingkatAkhir)>Naik Kelas</option>
    <option value="lulus" @selected($isTingkatAkhir)>Lulus</option>
</select>
@if ($isTingkatAkhir)
    <p class="mt-1 text-xs text-amber-600">Disarankan: tingkat akhir jenjang</p>
@endif
```

Admin tetap bebas mengubah pilihan — ini saran default, bukan validasi yang memblokir submit.

### 2.3 — Peringatan live kurikulum berbeda (Alpine.js inline per baris)

Tiap `<option>` di dropdown kelas tujuan diberi data attribute:

```blade
<option value="{{ $kelasBaru->id }}" data-kurikulum="{{ $kelasBaru->kurikulum?->value }}" data-tingkat="{{ $kelasBaru->tingkat }}">{{ $kelasBaru->nama }}</option>
```

Baris `<tr>` diberi `x-data` inline (state trivial — perbandingan 2 string, sesuai `.ai/rules/js.md` yang mensyaratkan modul Alpine terpisah HANYA untuk logic non-trivial seperti forms/tabel/filter kompleks):

```blade
<tr x-data="{
    kurikulumAsal: {{ Js::from($kelasLama->kurikulum?->value) }},
    kurikulumTujuan: null,
    tingkatTujuan: null,
    onKelasTujuanChange(event) {
        const opt = event.target.selectedOptions[0];
        this.kurikulumTujuan = opt?.dataset.kurikulum || null;
        this.tingkatTujuan = opt?.dataset.tingkat || null;
    },
}">
    <td>{{ $kelasLama->nama }} <span class="text-xs text-gray-400">(Tingkat {{ $kelasLama->tingkat ?? '-' }})</span></td>
    ...
    <td>
        <select name="mapping[{{ $kelasLama->id }}][kelas_baru_id]" x-on:change="onKelasTujuanChange($event)" ...>
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
</tr>
```

Peringatan ini **murni informatif** — tidak memblokir submit, tidak ada validasi server-side tambahan (server tetap menerima kombinasi kurikulum apa pun, sesuai keputusan bahwa ini workflow manual admin-driven). Kalau `kelas_baru_id` belum dipilih (`value=""`), tidak ada peringatan yang tampil (`kurikulumTujuan` tetap `null`).

**Tingkat ditampilkan berdampingan sbg info saja** (tingkat asal di kolom "Kelas Lama", tingkat tujuan di bawah dropdown) — TIDAK ADA logika pembanding otomatis "tingkat seharusnya = asal+1", karena `tingkat` adalah kolom teks bebas tanpa urutan baku yang bisa diasumsikan aman untuk semua kasus.

## 3. Non-Goals (eksplisit di luar scope)

- Tidak ada guard `bentuk_pendidikan` baru — sudah tercover oleh guard lintas-`lembaga_id` existing (lihat §1 poin 3).
- Tidak ada validasi server-side yang memblokir submit berdasarkan mismatch kurikulum/tingkat — ini tetap workflow manual, peringatan murni UI.
- Tidak mengubah `ProsesKenaikanKelasAction::execute()` sama sekali — semua perubahan di Kelompok B ada di layer Controller (query) dan View (Blade + Alpine inline) + 1 method baru di enum `BentukPendidikan`.
- Tidak menambah modul Alpine terpisah di `resources/js/` — state per-baris cukup trivial untuk `x-data` inline sesuai konvensi proyek.
- Tidak mengubah `RaporPdfDataBuilder`'s label rapor/perilaku PDF — hasil `isTingkatAkhir()` harus identik sebelum/sesudah refactor (dibuktikan lewat test regresi existing `RaporPdfDataBuilderTest.php` yang harus tetap pass).

## 4. Testing (acceptance criteria wajib)

**4.1 — `BentukPendidikan::isTingkatAkhir()` (test baru)**:
- `Kb::isTingkatAkhir('B')`, `Tpa::isTingkatAkhir('B')`, `Sps::isTingkatAkhir('B')` → SEMUA `false` (pengecualian permanen dari Priority #3 — ini assertion paling kritis, buktikan regresi generik-dari-`validTingkatValues()` tidak terjadi).
- `Tk::isTingkatAkhir('B')` → `true`; `Tk::isTingkatAkhir('A')` → `false`.
- `Sd::isTingkatAkhir('6')` dan `Slb::isTingkatAkhir('6')` → `true`; tingkat lain (mis. '3') → `false`.
- `Smp::isTingkatAkhir('9')` → `true`. `Sma::isTingkatAkhir('12')` dan `Smk::isTingkatAkhir('12')` → `true`.
- `isTingkatAkhir(null)` → `false` untuk semua case.

**4.2 — Regresi `RaporPdfDataBuilder` (test existing, WAJIB tetap pass tanpa modifikasi assertion)**:
- Jalankan `RaporPdfDataBuilderTest.php` penuh — label "Keterangan Kelulusan" vs "Keterangan Kenaikan Kelas" harus identik hasilnya sebelum/sesudah refactor delegasi.

**4.3 — `KenaikanKelasController::index()` pre-select "Lulus"**:
- Kelas asal di tingkat akhir jenjangnya (mis. SD tingkat "6") → response HTML mengandung opsi "lulus" ter-`selected` untuk baris kelas itu (assert via `assertSee`/parsing HTML, bukan asumsi urutan opsi).
- Kelas asal BUKAN di tingkat akhir (mis. SD tingkat "3") → opsi "naik" yang ter-`selected`.
- Kelas dengan `lembaga` null-safe (edge case tidak realistis tapi jangan sampai error) — pastikan tidak crash kalau relasi lembaga somehow tidak ter-load.

**4.4 — Peringatan kurikulum (feature test, assert HTML mengandung data attribute yang benar — bukan test JS runtime)**:
- Response index dengan kelas tujuan berkurikulum berbeda dari kelas asal → HTML mengandung `data-kurikulum="..."` yang berbeda nilainya antara opsi kelas asal (tersirat dari `x-data`) dan opsi kelas tujuan.
- Assert existence dulu (kelas dgn kurikulum tsb benar-benar ada & muncul di dropdown) sebelum assert soal data attribute-nya — pola existence-then-exclusion yang baku di proyek ini, diadaptasi jadi existence-then-assertion untuk kasus non-exclusion ini.

## 5. Ringkasan Perubahan File

```text
app/Domains/Akademik/Enums/BentukPendidikan.php          [+method isTingkatAkhir()]
app/Domains/Akademik/Services/RaporPdfDataBuilder.php    [isTingkatAkhir() delegasi ke enum]
app/Http/Controllers/Admin/KenaikanKelasController.php   [+with('lembaga') di kelasLamaList]
resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php  [+pre-select Lulus, +Alpine inline peringatan kurikulum]
tests/Unit/Domains/Akademik/Enums/BentukPendidikanTingkatAkhirTest.php   [BARU]
tests/Feature/Akademik/KenaikanKelasControllerUxTest.php                [BARU]
```
