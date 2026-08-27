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

### 2.1 — Ekstrak `isTingkatAkhir()` ke enum `BentukPendidikan` sebagai source of truth untuk semantik tingkat akhir

`app/Domains/Akademik/Enums/BentukPendidikan.php` sudah punya method `validTingkatValues(): array`, tapi method itu MENGELOMPOKKAN `Kb, Tpa, Sps, Tk` jadi satu arm yang sama-sama mengembalikan `['A', 'B']` (untuk keperluan validasi input tingkat). Menggunakan `end($this->validTingkatValues())` secara generik untuk `isTingkatAkhir()` akan SALAH: itu membuat KB/TPA/SPS di tingkat "B" ikut dianggap tingkat akhir, padahal keputusan bisnis yang sudah dikunci di Priority #3 (Kelulusan PAUD & SLB) menyatakan **hanya TK tingkat B** yang dianggap tingkat akhir untuk kelulusan — KB/TPA/SPS dikecualikan permanen berapa pun tingkatnya.

**Dua method ini sengaja punya tanggung jawab berbeda dan TIDAK BOLEH disamakan**:
- `validTingkatValues()` = source of truth untuk **validitas nilai tingkat** (tingkat apa saja yang boleh diinput untuk jenjang ini).
- `isTingkatAkhir()` = source of truth untuk **semantik bisnis "tingkat akhir/kelulusan"** — bisa berbeda dari "elemen terakhir daftar tingkat valid", persis kasus KB/TPA/SPS yang berbagi nilai valid A/B dengan TK tapi tidak berbagi aturan kelulusan TK.

Karena itu, method baru TETAP eksplisit per-case (tidak diderivasi generik dari `validTingkatValues()`, dan TIDAK BOLEH direfactor jadi `end($this->validTingkatValues())` di masa depan — itu akan menghidupkan kembali bug KB/TPA/SPS), supaya perilaku identik persis dengan peta hardcoded lama:

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

**Keputusan eksplisit — kurikulum asal `null`**: kondisi `kurikulumAsal !== null` di expression `x-show` berarti kalau kelas asal TIDAK punya nilai `kurikulum` tersimpan (data legacy/`null`), peringatan TIDAK PERNAH muncul apa pun kurikulum kelas tujuannya — bahkan kalau kelas tujuan punya kurikulum yang jelas. Ini keputusan sadar, bukan celah: `null` di sini berarti "kurikulum kelas asal tidak diketahui/tidak informatif untuk dibandingkan", BUKAN "mismatch otomatis". Peringatan hanya bermakna kalau KEDUA sisi punya nilai kurikulum yang jelas dan berbeda satu sama lain.

**Invariant — state awal render**: form Kenaikan Kelas saat ini TIDAK melakukan preselect `kelas_baru_id` dari `old()` input setelah validasi gagal (dikonfirmasi dari `index.blade.php` existing — dropdown kelas tujuan selalu mulai dari `<option value="">—</option>` tanpa `@selected(old(...))`). Karena itu `kurikulumTujuan: null` sebagai state Alpine awal selalu benar untuk initial render dalam kondisi form saat ini. Kalau di masa depan ada perubahan yang menambahkan preselect dari `old()`, state Alpine WAJIB diinisialisasi dari opsi yang ter-preselect itu (bukan `null`) — dicatat di sini supaya perubahan itu tidak diam-diam membuat peringatan menghilang pada render pertama setelah validasi gagal.

**Tingkat ditampilkan berdampingan sbg info saja** (tingkat asal di kolom "Kelas Lama", tingkat tujuan di bawah dropdown) — TIDAK ADA logika pembanding otomatis "tingkat seharusnya = asal+1", karena `tingkat` adalah kolom teks bebas tanpa urutan baku yang bisa diasumsikan aman untuk semua kasus.

## 3. Non-Goals (eksplisit di luar scope)

- Tidak ada guard `bentuk_pendidikan` baru — sudah tercover oleh guard lintas-`lembaga_id` existing (lihat §1 poin 3).
- Tidak ada validasi server-side yang memblokir submit berdasarkan mismatch kurikulum/tingkat — ini tetap workflow manual, peringatan murni UI.
- Tidak mengubah `ProsesKenaikanKelasAction::execute()` sama sekali — semua perubahan di Kelompok B ada di layer Controller (query) dan View (Blade + Alpine inline) + 1 method baru di enum `BentukPendidikan`. Ini BUKAN berarti server tidak melakukan validasi apa pun — guard/business rule yang SUDAH ADA di `execute()` (penolakan same-tahun-ajaran, penolakan cross-lembaga, dll., lihat Kelompok A) tetap dipertahankan utuh dan tidak disentuh; Kelompok B murni menambah UX guidance di atas guard yang sudah ada.
- Tidak menambah modul Alpine terpisah di `resources/js/` — state per-baris cukup trivial untuk `x-data` inline sesuai konvensi proyek.
- Tidak mengubah `RaporPdfDataBuilder`'s label rapor/perilaku PDF — hasil `isTingkatAkhir()` harus identik sebelum/sesudah refactor (dibuktikan lewat test regresi existing `RaporPdfDataBuilderTest.php` yang harus tetap pass).

## 4. Testing (acceptance criteria wajib)

**4.1 — `BentukPendidikan::isTingkatAkhir()` (test baru, data-driven — 1 dataset per baris tabel berikut, WAJIB semua baris ada)**:

| Case | Tingkat | Ekspektasi | Catatan |
| --- | --- | --- | --- |
| `Kb` | `'B'` | `false` | pengecualian permanen Priority #3 — assertion PALING KRITIS, buktikan regresi generik-dari-`validTingkatValues()` tidak terjadi |
| `Tpa` | `'B'` | `false` | idem |
| `Sps` | `'B'` | `false` | idem |
| `Tk` | `'B'` | `true` | tingkat akhir TK |
| `Tk` | `'A'` | `false` | bukan tingkat akhir |
| `Sd` | `'6'` | `true` | tingkat akhir SD |
| `Sd` | `'5'` | `false` | tingkat SEBELUM akhir — menangkap bug implementasi longgar (mis. `>=`) |
| `Slb` | `'6'` | `true` | tingkat akhir SLB |
| `Smp` | `'9'` | `true` | tingkat akhir SMP |
| `Smp` | `'8'` | `false` | tingkat sebelum akhir |
| `Sma` | `'12'` | `true` | tingkat akhir SMA |
| `Sma` | `'11'` | `false` | tingkat sebelum akhir |
| `Smk` | `'12'` | `true` | tingkat akhir SMK |
| semua 9 case | `null` | `false` | tingkat null selalu bukan tingkat akhir |

**4.2 — Regresi `RaporPdfDataBuilder` (test existing, WAJIB tetap pass tanpa modifikasi assertion)**:
- Jalankan `RaporPdfDataBuilderTest.php` penuh — label "Keterangan Kelulusan" vs "Keterangan Kenaikan Kelas" harus identik hasilnya sebelum/sesudah refactor delegasi.

**4.3 — `KenaikanKelasController::index()` pre-select "Lulus"**:
- **Assertion HARUS di-scope ke baris kelas yang benar, bukan `assertSee()` global.** Kalau ada >1 baris kelas di tabel, `assertSee('selected')` global bisa false-positive (baris lain kebetulan punya opsi selected juga). Cara benar: parse response HTML (mis. `Symfony\Component\DomCrawler\Crawler` via `$response->getCrawler()` atau regex yang secara eksplisit menyasar `<select name="mapping[{$kelasLama->id}][tindakan]">...`), cari elemen `<select>` dengan `name="mapping[{ID kelas ini}][tindakan]"` spesifik, baru periksa `<option>` mana di DALAM select itu yang punya atribut `selected`.
- Kelas asal di tingkat akhir jenjangnya (mis. SD tingkat "6") → di dalam `<select mapping[{id}][tindakan]>` milik kelas itu, opsi `value="lulus"` yang ter-`selected` (bukan `value="naik"`).
- Kelas asal BUKAN di tingkat akhir (mis. SD tingkat "3") → sebaliknya, opsi `value="naik"` yang ter-`selected`.
- Test dengan MINIMAL 2 baris kelas berbeda (1 tingkat akhir, 1 bukan) dalam satu response, buktikan preselect-nya independen per baris — ini yang membuktikan scoping-nya benar, bukan kebetulan cocok karena cuma ada 1 baris di test.
- **Defensive rendering test — optional, bukan business state yang harus didukung.** Relasi `Kelas::lembaga()` secara skema selalu ada (foreign key wajib) — `lembaga === null` bukan skenario bisnis nyata, jadi tidak perlu dianggap sbg kondisi yang harus "didukung". Yang penting dijaga di level kode: `BentukPendidikan::from($kelasLama->lembaga->bentuk_pendidikan)` TIDAK BOLEH dipanggil kalau `$kelasLama->lembaga` null (lihat `@php` block §2.2 yang sudah pakai ternary null-check) — kalau implementer ingin menambah test untuk baris kode defensif ini, boleh, tapi tidak wajib dan tidak masuk definition-of-done.

**4.4 — Peringatan kurikulum — dipisah jadi 2 lapis pembuktian, TIDAK berpura-pura feature test membuktikan runtime JS:**

*Lapis 1 — Server/Blade contract (WAJIB dites via feature test, ini yang benar-benar dibuktikan PHP):*
- Opsi `<option>` kelas tujuan di dalam dropdown milik baris kelas tsb punya `data-kurikulum="..."` dengan NILAI YANG BENAR (samakan dengan `$kelasBaru->kurikulum->value` sungguhan dari database, bukan sekadar "attribute ada").
- Elemen `x-data` pada `<tr>` baris itu memuat nilai `kurikulumAsal` yang BENAR (samakan dengan `$kelasLama->kurikulum->value` sungguhan) — assert lewat mencari substring `kurikulumAsal:"{nilai kurikulum asal sesungguhnya}"` (atau bentuk serialisasi `Js::from()` yang sesuai) di dalam markup `<tr>` baris tersebut, bukan di sembarang tempat di halaman.
- Elemen peringatan (`<p x-show="...">`) memuat EXPRESSION perbandingan yang benar (`kurikulumTujuan !== null && kurikulumAsal !== null && kurikulumTujuan !== kurikulumAsal`) — assert string expression itu ada persis di markup, membuktikan logic perbandingannya bukan typo/salah operator.
- Assert existence dulu: kelas tujuan dengan kurikulum yang dimaksud benar-benar ada di `kelasTujuanList` dan muncul sbg `<option>` di dropdown — baru assert soal `data-kurikulum`-nya.

*Lapis 2 — Runtime behavior (TIDAK wajib dites via PHPUnit/Pest; proyek ini tidak punya browser/E2E test untuk Alpine):*
- Bahwa peringatan BENAR-BENAR muncul secara visual saat user memilih opsi di browser sungguhan adalah tanggung jawab manual QA / verifikasi visual saat review, BUKAN diklaim terbukti oleh test otomatis di plan ini. Jangan tulis assertion PHPUnit yang berpura-pura membuktikan ini (mis. assert teks peringatan "muncul" padahal itu di-hide via `x-show` dan text-nya tetap ada di DOM secara statis).

## 5. Ringkasan Perubahan File

```text
app/Domains/Akademik/Enums/BentukPendidikan.php          [+method isTingkatAkhir()]
app/Domains/Akademik/Services/RaporPdfDataBuilder.php    [isTingkatAkhir() delegasi ke enum]
app/Http/Controllers/Admin/KenaikanKelasController.php   [+with('lembaga') di kelasLamaList]
resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php  [+pre-select Lulus, +Alpine inline peringatan kurikulum]
tests/Unit/Domains/Akademik/Enums/BentukPendidikanTingkatAkhirTest.php   [BARU]
tests/Feature/Akademik/KenaikanKelasControllerUxTest.php                [BARU]
```
