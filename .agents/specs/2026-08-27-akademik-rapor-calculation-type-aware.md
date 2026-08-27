# RaporCalculationService Type-Aware + Fix Key-Mismatch (Priority 2) — Design Spec

**Tanggal**: 2026-08-27
**Branch**: `akademik-v2`
**Konteks roadmap**: `PETA_PENGEMBANGAN.md` §"🔵 Roadmap Kurikulum Dinamis", Prioritas #2 — blocker operasional independen dari Prioritas #1 (`KurikulumFramework`, sudah SELESAI).

---

## 1. Latar Belakang & Masalah

Roadmap mencatat: "lembaga PAUD/kelas naratif-predikat melihat Rekap Rapor & Persetujuan Rapor KOSONG TOTAL" karena `RaporCalculationService::hitungRekapKelas()` hanya mengagregasi komponen dengan `assessment_type=numeric`. Audit lebih dalam (27 Agustus 2026) menemukan masalah ini LEBIH LUAS dari yang tercatat:

**Bug independen yang ditemukan saat brainstorming — key-mismatch di kedua view**: `resources/views/portals/lembaga/akademik/rapor/_hasil.blade.php:100` dan `resources/views/portals/lembaga/rapor/persetujuan/show.blade.php:41` melakukan lookup `$rekapNilai[$siswa->id][$mapel->id]` dengan `$mapel->id` (integer polos), padahal key array sebenarnya dari service adalah string majemuk `"{morphClass}:{id}"` (dari `SubjekPenilaianKey::dari()`, contoh: `"mata_pelajaran:5"`). Akibatnya **kolom per-mapel di matriks SELALU kosong ("—") untuk SEMUA jenjang** — SD/SMP/SMA/SMK yang sudah dipakai produksi pun kena, bukan cuma PAUD. Bug ini lolos dari 2 test existing (`RaporControllerTest.php` baris 35, 288) karena keduanya cuma `assertSee('88')`/`assertSee('90')`, yang kebetulan match ke kolom "Rata-Rata Umum" (dihitung dari SELURUH value baris tanpa peduli key) — bukan ke kolom per-mapel yang sebenarnya rusak. Halaman Persetujuan Rapor tidak punya kolom penyelamat itu — matriksnya benar-benar kosong total, jenjang apa pun.

Keputusan user: gabungkan fix key-mismatch dan pembangunan agregasi type-aware jadi SATU spec+plan, karena keduanya di file/area yang sama dan memisahkannya jadi overhead tanpa manfaat.

## 2. Model Domain Saat Ini (Fakta, Tidak Berubah)

```text
KomponenPenilaian.assessment_type: AssessmentType (backed enum)
    = Numeric | Narrative | Predicate

NilaiSiswa (per siswa per asesmen per komponen):
    nilai_angka: ?int      -- diisi kalau assessment_type=Numeric
    predikat: ?PredikatPaud -- diisi kalau assessment_type=Predicate (BB/MB/BSH/BSB)
    catatan: ?text          -- diisi kalau assessment_type=Narrative
```

Kolom-kolom ini SUDAH ADA (Sprint 2), sudah terpisah per tipe — spec ini TIDAK mengubah skema database sama sekali, murni logic agregasi + view.

## 3. Perbaikan #1 — Fix Key-Mismatch (Root Cause)

`RaporCalculationService::hitungRekapKelas()` line 28-32, ganti `->values()` menjadi `->keyBy(fn ($s) => SubjekPenilaianKey::dari($s))` saat membangun `$subjekList` (yang jadi `mapelList` di return array) — sehingga collection-nya sendiri sudah ter-key dengan string composite key yang benar, konsisten dengan key `$rekapNilai[$siswa_id][...]`.

Kedua view diubah loop-nya:

```blade
{{-- SEBELUM (bug) --}}
@forelse ($mapelList as $mapel)
    @php $skor = $rekapNilai[$siswa->id][$mapel->id] ?? null; @endphp

{{-- SESUDAH (fix) --}}
@forelse ($mapelList as $subjekKey => $mapel)
    @php $sel = $rekapNilai[$siswa->id][$subjekKey] ?? null; @endphp
```

Ini SATU-SATUNYA perubahan yang bersifat "bug fix murni" — semua yang lain di spec ini adalah fitur baru (agregasi type-aware).

## 4. Perbaikan #2 — Agregasi Type-Aware

### 4.1 DTO baru `RekapNilaiSel`

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

use App\Domains\Akademik\Enums\AssessmentType;

final readonly class RekapNilaiSel
{
    public function __construct(
        public AssessmentType $assessmentType,
        public string $label,
        public ?bool $tuntas,
    ) {}
}
```

Sel matriks kosong (tidak ada komponen apa pun untuk siswa+subjek itu) tetap direpresentasikan sebagai `null` (bukan instance DTO) — konsisten dengan perilaku lama.

### 4.2 Precedence per sel: numeric > predicate > narrative

Kalau satu subjek (edge case, tidak dilarang skema DB) punya campuran tipe komponen untuk siswa yang sama, urutan prioritas tampilan: **numeric diutamakan** (paling informatif, backward-compatible dgn perilaku lama), lalu **predicate**, baru **narrative**. Precedence dicek per siswa per subjek — bukan precedence global per kelas.

**Ketegasan soal `mapelList`/`subjekList`**: collection `$subjekList` yang dikembalikan sebagai `mapelList` HARUS di-`keyBy(fn ($s) => SubjekPenilaianKey::dari($s))` sebagai langkah TERAKHIR pembentukannya (menggantikan `->values()` yang ada sekarang) — jangan ada `->values()` lagi setelah `keyBy()` di titik mana pun, karena itu akan membuang key composite yang justru jadi inti perbaikan §3. Key hasil `keyBy()` ini adalah SATU-SATUNYA identifier yang dipakai view untuk lookup sel (`$rekapNilai[$siswa->id][$subjekKey]`) — tidak ada mekanisme lookup lain.

### 4.3 Strategi per tipe

**Numeric** (TIDAK BERUBAH dari kode existing):
- Rata-rata berbobot (`bobot` komponen, default 1 kalau ≤ 0).
- `label` = string angka hasil `round(..., 1)`.
- `tuntas` = `$nilai >= config('akademik.ambang_tuntas')`.

**Predicate**:
- Ambil seluruh `NilaiSiswa` siswa itu untuk subjek itu (semester berjalan) yang `komponenPenilaian.assessment_type === Predicate` dan `predikat !== null`.
- **Kalau tidak ada satu pun `NilaiSiswa` yang lolos filter itu** (subjek punya komponen predicate tapi siswa belum punya satu pun `predikat` valid) → sel = `null`. JANGAN hasilkan `RekapNilaiSel` kosong/badge tanpa isi.
- Hitung frekuensi tiap `PredikatPaud` case.
- Predikat dgn frekuensi terbesar menang. **Tie-break eksplisit**: kalau ada 2+ predikat dgn frekuensi sama, menangkan yang rankingnya lebih tinggi — `BSB=4, BSH=3, MB=2, BB=1`.
- `label` = kode predikat pemenang (mis. `"BSH"`).
- `tuntas` = `null` (tidak ada konsep ambang tuntas utk predikat).

**Narrative**:
- `$total` = jumlah pasangan (asesmen, komponen) dgn `assessment_type === Narrative` yang **terdaftar/relevan untuk subjek dan semester tersebut secara spesifik** (via `Asesmen->komponenPenilaian()` pivot, difilter `kelas_id`+`semester_id`+`subjek` yang sama dgn konteks rekap ini) — BUKAN seluruh komponen narrative yang ada di database. `$total` SAMA utk semua siswa di kelas itu (tidak bergantung siswa).
- **Kalau `$total === 0`** (subjek itu tidak punya komponen narrative sama sekali utk semester ini) → sel = `null`. JANGAN hasilkan label `"0/0"` — itu sel benar-benar kosong, konsisten dgn aturan "tidak ada komponen apa pun = null".
- `$terisi` = dari `$total` itu, berapa yang siswa ybs punya `NilaiSiswa` dgn **`trim($catatan ?? '') !== ''`**. Definisi "terisi" ini eksplisit: `null`, `""`, dan string berisi whitespace semua dianggap BELUM terisi — mencegah inkonsistensi.
- `label` = `"{$terisi}/{$total}"` (mis. `"3/4"`), hanya dibentuk kalau `$total > 0`.
- `tuntas` = `null`.

### 4.4 `classAvg` dan `highestScore` — TIDAK BERUBAH SEMANTIK

`RekapNilaiSel` TIDAK punya field `value` — DTO cuma `assessmentType`, `label`, `tuntas` (lihat §4.1). Nilai numeric mentah (float hasil rata-rata berbobot, sebelum di-`round()`/diformat jadi `label` string) dipertahankan sbg variabel terpisah SELAMA proses agregasi di dalam service (mis. array bantu `$rekapNumericMentah[siswa_id][subjekKey] = float`, tidak diekspos ke luar service). `classAvg`/`highestScore` dihitung dari array bantu numeric mentah ini — PERSIS logic lama (`collect(...)->flatMap(...)->filter($v !== null)->avg()/max()`), tidak pernah membaca balik dari `RekapNilaiSel::$label` (yang sudah berupa string terformat, bukan angka). Kelas PAUD murni (semua sel predicate/narrative, nol numeric) akan tetap menampilkan `classAvg = null`/`"—"` — TIDAK diberi kartu ringkasan baru utk predicate/narrative (Non-Goal eksplisit, lihat §6).

## 5. View — Rendering per Tipe

Kedua view (`_hasil.blade.php`, `persetujuan/show.blade.php`) menerima `$sel` (instance `RekapNilaiSel` atau `null`) per sel, render:

```blade
@if ($sel === null)
    <span class="text-gray-300 font-normal text-xs">—</span>
@elseif ($sel->tuntas !== null)
    {{-- numeric: badge existing hijau/amber berdasarkan ambang tuntas --}}
    <span class="inline-block rounded-lg px-2.5 py-1 {{ $sel->tuntas ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
        {{ $sel->label }}
    </span>
@else
    {{-- predicate/narrative: badge netral, tidak ada makna tuntas/belum-tuntas --}}
    <span class="inline-block rounded-lg px-2.5 py-1 bg-gray-100 text-gray-700 border border-gray-200">
        {{ $sel->label }}
    </span>
@endif
```

`persetujuan/show.blade.php` (yang sebelumnya cuma `{{ $rekapNilai[$siswa->id][$mapel->id] ?? '—' }}` inline tanpa badge) mendapat treatment badge yang sama supaya konsisten dgn Rekap Rapor.

## 6. Non-Goals (eksplisit di luar scope)

- Tidak menambah kartu ringkasan kelas baru utk distribusi predicate atau completion-rate narrative agregat (mis. "% BSH di kelas ini") — nice-to-have UI, di luar scope perbaikan bug operasional ini.
- Tidak mengubah skema database (`nilai_angka`/`predikat`/`catatan` sudah ada sejak Sprint 2).
- Tidak menyentuh `isTingkatAkhir()`/kelulusan PAUD (Prioritas #3 roadmap terpisah).
- Tidak menyentuh struktur KD vs CP+TP (Prioritas #4).
- Tidak mengubah `templateUntukJenjang`/`RaporPdfDataBuilder` (PDF rapor individual per siswa) — spec ini HANYA soal `RaporCalculationService` dan 2 halaman REKAP (Rekap Rapor admin, Persetujuan Rapor wali kelas), bukan rapor PDF final per siswa yang sudah py jalur render terpisah.

## 7. Testing (acceptance criteria wajib)

1. **Fix key-mismatch terbukti via feature test**: render `_hasil.blade.php` dgn 1 siswa + 1 mapel numeric bernilai 88 → assert badge SPESIFIK per-mapel muncul (bukan cuma "Rata-Rata Umum"), mis. via `assertSeeInOrder` atau query DOM-like string match yang membedakan kolom mapel dari kolom rata-rata umum. Test existing yang cuma `assertSee('88')` TIDAK cukup (itulah kenapa bug lolos) — test baru harus lebih presisi.
2. **Numeric behavior tidak berubah**: `RaporCalculationServiceTest.php` dan `RaporCalculationServiceAssessmentTypeTest.php` — SEMUA assertion yang memverifikasi HASIL numeric (angka rata-rata berbobot, exclusion narrative dari rata-rata, isolasi lembaga, dll) harus tetap menghasilkan nilai yang identik dgn sebelumnya. Assertion BOLEH disesuaikan HANYA untuk mengikuti kontrak DTO baru (mis. `expect($rekap['rekapNilai'][$id][$key])->toBe(80.0)` menjadi `expect($rekap['rekapNilai'][$id][$key]->label)->toBe('80')` atau serupa) — TIDAK BOLEH mengubah nilai numeric yang diharapkan itu sendiri.
3. **Predicate modus + tie-break**: test dgn 3 nilai predikat (`BSH, BSH, MB`) → menang `BSH` (frekuensi terbesar, 2 vs 1). Test tie-break terpisah: 4 nilai predikat `BSH, BSH, BSB, BSB` (frekuensi seri 2-2) → menang `BSB` (ranking lebih tinggi: `BSB=4 > BSH=3`).
4. **Predicate tanpa nilai valid → sel null**: subjek punya komponen `assessment_type=Predicate` tapi siswa ybs tidak punya satu pun `NilaiSiswa` dgn `predikat` terisi → sel = `null`, bukan `RekapNilaiSel` dgn label kosong.
5. **Narrative completion-rate + definisi "terisi"**: test dgn 4 slot narrative (spesifik utk subjek+semester itu), 3 punya `catatan` non-kosong, 1 `catatan=null` → label `"3/4"`. Test terpisah tegas: `catatan=""` dan `catatan="   "` (whitespace) dihitung SAMA sbg belum-terisi seperti `null`.
6. **Narrative tanpa slot sama sekali → sel null, bukan "0/0"**: subjek tidak punya komponen narrative terdaftar utk semester itu → sel = `null`.
7. **Precedence numeric > predicate**: test dgn satu subjek yang komponennya sengaja dicampur (1 numeric + 1 predicate utk siswa yg sama) → sel menampilkan hasil numeric, bukan predicate.
8. **Precedence predicate > narrative**: test dgn satu subjek yang komponennya dicampur (1 predicate `BSH` + 1 narrative terisi, TANPA komponen numeric) utk siswa yg sama → sel menampilkan hasil predicate (`"BSH"`), bukan narrative completion-rate. Ini membuktikan sisi kedua dari precedence penuh `numeric > predicate > narrative` (test #7 di atas cuma membuktikan sisi numeric>predicate).
9. **`classAvg`/`highestScore` tidak berubah utk kelas PAUD murni**: kelas dgn HANYA komponen predicate/narrative → `classAvg === null`, `highestScore === null` (sama seperti perilaku lama, bukan crash/error).
10. **Sel null tetap null**: siswa tanpa nilai apa pun utk subjek tertentu → `$rekapNilai[$siswa][$subjekKey]` tetap `null`, bukan DTO kosong.

## 8. Ringkasan Alur

```text
NilaiSiswa (nilai_angka | predikat | catatan)
    per siswa × subjek × komponen
              │
              ▼
   RaporCalculationService::hitungRekapKelas()
   precedence per sel: numeric > predicate > narrative
              │
              ▼
     RekapNilaiSel (assessmentType, label, tuntas) | null
              │
              ▼
   mapelList di-keyBy(SubjekPenilaianKey) -- FIX bug lama
              │
              ▼
   View: badge hijau/amber (numeric) | badge netral (predicate/narrative) | "—" (null)
```
