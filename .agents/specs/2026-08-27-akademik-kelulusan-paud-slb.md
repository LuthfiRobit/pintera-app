# Kelulusan/Rapor Akhir PAUD + Keputusan SLB (Priority 3) — Design Spec

**Tanggal**: 2026-08-27
**Branch**: `akademik-v2`
**Konteks roadmap**: `PETA_PENGEMBANGAN.md` §"🔵 Roadmap Kurikulum Dinamis", Prioritas #3 — independen dari Prioritas #1/#2 (sudah SELESAI), effort Kecil.

---

## 1. Latar Belakang & Masalah

`RaporPdfDataBuilder::isTingkatAkhir()` (`app/Domains/Akademik/Services/RaporPdfDataBuilder.php`) memakai map `$tingkatAkhirPerJenjang = ['SD'=>'6','SLB'=>'6','SMP'=>'9','SMA'=>'12','SMK'=>'12']` — `KB`/`TPA`/`SPS`/`TK` tidak pernah masuk, jadi label "Keterangan Kelulusan" tidak pernah muncul di rapor PAUD. "Surat Keterangan Lulus TK" adalah dokumen administratif nyata yang dibutuhkan untuk pendaftaran SD.

Audit lebih dalam saat brainstorming menemukan gap KEDUA yang lebih mendasar: `resources/views/pdf/rapor/paud.blade.php` **tidak punya section "Keterangan Kelulusan"/"Keterangan Kenaikan Kelas" sama sekali** — beda dari `sd.blade.php`/`smp-sma.blade.php`/`smk.blade.php` yang sudah punya `@if ($isGenap) <h2>{{ $labelKenaikan }}</h2>...`. Jadi memperbaiki `isTingkatAkhir()` saja TIDAK CUKUP — section-nya sendiri belum pernah ada di template PAUD.

SLB saat ini fallback ke template `sd` sbg "regression compatibility, bukan keputusan domain final" (komentar eksplisit di `AcademicProfile.php`). Keputusan user: formalkan ini jadi keputusan final yang disengaja, TIDAK membuat template SLB terpisah.

## 2. Keputusan Bisnis (dari diskusi user, WAJIB diikuti persis)

1. **Hanya `TK` pada tingkat `B`** yang dianggap tingkat akhir PAUD utk kebutuhan "Keterangan Kelulusan". `KB`/`TPA`/`SPS` TIDAK PERNAH dianggap tingkat akhir, berapa pun tingkatnya — jenjang ini adalah pengasuhan sebelum TK, bukan titik transisi formal ke SD.
2. **SLB tetap pakai template `sd`**, formal sbg keputusan final (bukan fallback diam-diam) — tingkat akhir SLB tetap `6` (ikut pola SD). Tidak ada template SLB baru di Prioritas #3 ini. Keputusan ini revisable kalau nanti ada pelanggan SLB nyata dengan kebutuhan berbeda.
3. **Wording label kelulusan TK SAMA PERSIS** dengan SD/SMP/SMA/SMK ("Keterangan Kelulusan") — TIDAK ADA wording khusus TK, TIDAK ADA cabang logic baru. Isi kontennya tetap dari `CatatanWaliKelas::keterangan_kenaikan` yang diisi guru — guru bisa menulis kalimat spesifik TK di situ kalau perlu.

## 3. Perubahan #1 — Fix `isTingkatAkhir()`

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

Satu baris ditambah (`'TK' => 'B'`). `KB`/`TPA`/`SPS` tetap TIDAK ADA di map — `isset(...)` mengembalikan `false` utk ketiganya, jadi `isTingkatAkhir()` selalu `false` tidak peduli tingkatnya, persis perilaku sekarang (tidak berubah).

## 4. Perubahan #2 — Tambah Section "Keterangan Kelulusan" ke `paud.blade.php`

Tambahkan tepat sebelum `@include('pdf.rapor._tanda-tangan')` (baris terakhir sebelum `</body>`):

```blade
    @if ($isGenap)
        <h2 style="font-size: 13px; margin-top: 14px;">{{ $labelKenaikan }}</h2>
        <p>{{ $catatan?->keterangan_kenaikan ?: '-' }}</p>
    @endif

    @include('pdf.rapor._tanda-tangan')
```

Pola identik `sd.blade.php` baris 93-96 — `$labelKenaikan` dan `$catatan` SUDAH tersedia di data yang di-passing `RaporPdfDataBuilder::build()` (tidak perlu perubahan builder utk section ini, cuma `isTingkatAkhir()` yang perlu fix di §3). Section ini tampil kalau semester genap, ISI JUDULNYA berbeda tergantung `$isTingkatAkhir` (dihitung builder): `"Keterangan Kelulusan"` kalau TK-B, `"Keterangan Kenaikan Kelas"` kalau TK-A atau PAUD lain.

## 5. Perubahan #3 — Formalkan Keputusan SLB (Komentar Saja, Tanpa Logic Baru)

`app/Domains/Akademik/Services/AcademicProfile.php` baris 30-34, ganti komentar dari:

```php
// SLB tidak punya cabang eksplisit di RaporPdfDataBuilder::templateUntukJenjang()
// production saat ini -- jatuh ke default yang sama dgn SD. Baris ini SENGAJA
// meniru compatibility behavior itu, BUKAN klaim kebutuhan pelaporan SLB identik
// dgn SD. Re-evaluasi/pemisahan template SLB didorong ke desain Sprint 5.
$bentukPendidikan === 'SLB' => 'sd',
```

menjadi:

```php
// SLB memakai template SD sbg KEPUTUSAN FINAL yang disengaja (diformalkan
// Prioritas #3 Roadmap Kurikulum Dinamis, 27 Agustus 2026) -- bukan fallback
// diam-diam. Tidak ada pelanggan SLB nyata dgn kebutuhan struktur rapor
// berbeda saat ini; keputusan ini revisable kalau itu berubah.
$bentukPendidikan === 'SLB' => 'sd',
```

`tests/Feature/Akademik/RaporPdfDataBuilderTest.php` baris 18-19, ganti komentar dataset test dari:

```php
// SLB -> sd adalah regression compatibility (hasil lama krn fallback default),
// BUKAN keputusan domain baru bahwa SLB "memang seharusnya" sama dgn SD.
['SLB', 'pdf.rapor.sd'],
```

menjadi:

```php
// SLB -> sd adalah keputusan final yang disengaja (Prioritas #3, 27 Agustus 2026),
// bukan lagi fallback compatibility -- lihat AcademicProfile.php.
['SLB', 'pdf.rapor.sd'],
```

TIDAK ADA perubahan logic/behavior — murni dokumentasi yang menutup keputusan yang sebelumnya eksplisit ditandai "belum final".

## 6. Non-Goals (eksplisit di luar scope)

- Tidak membuat template SLB terpisah.
- Tidak mengubah `isTingkatAkhir()` untuk `KB`/`TPA`/`SPS` — tetap selalu `false`.
- Tidak menyentuh `sd.blade.php`/`smp-sma.blade.php`/`smk.blade.php` (sudah benar).
- Tidak mengubah skema database — `CatatanWaliKelas::keterangan_kenaikan` sudah ada.
- Tidak menambah wording/logic khusus TK — label sama persis dgn jenjang lain.

## 7. Testing (acceptance criteria wajib)

**Unit test `isTingkatAkhir()`** — HARUS memisahkan bentuk_pendidikan dan tingkat sbg kasus independen (bukan cuma 1-2 contoh happy-path), minimal:

| bentuk_pendidikan | tingkat | Expected |
|---|---|---|
| TK | B | `true` |
| TK | A | `false` |
| KB | B | `false` |
| TPA | B | `false` |
| SPS | B | `false` |
| SD | 6 | `true` |
| SLB | 6 | `true` |
| SMP | 9 | `true` |
| SMA | 12 | `true` |
| SMK | 12 | `true` |

(Tabel ini persis permintaan user — regresi SD/SLB/SMP/SMA/SMK WAJIB dibuktikan tetap `true`, bukan diasumsikan "tidak berubah jadi tidak perlu dites".)

**Feature test render `paud.blade.php`** — HARUS menguji KONDISI genap, bukan sekadar keberadaan teks di halaman manapun:
1. `isGenap=true` + kelas TK tingkat B (→ `isTingkatAkhir=true` dari builder) → render mengandung teks `"Keterangan Kelulusan"`, DAN mengandung isi `keterangan_kenaikan` yang diisi di fixture test (mis. `"Siap melanjutkan ke SD"`) — buktikan section membaca sumber data yang benar, bukan cuma judul yang muncul kebetulan.
2. `isGenap=false` (semester ganjil) → section (baik judul maupun isi `keterangan_kenaikan`) TIDAK muncul sama sekali di render.
3. (Opsional tapi disarankan) `isGenap=true` + kelas TK tingkat A (→ `isTingkatAkhir=false`) → render mengandung `"Keterangan Kenaikan Kelas"`, BUKAN `"Keterangan Kelulusan"`.

## 8. Ringkasan Alur

```text
AcademicProfile / template selection
        │
        ├── TK → paud
        └── SLB → sd (keputusan final eksplisit)

RaporPdfDataBuilder::isTingkatAkhir()
        │
        ├── TK-B → true
        ├── SD-6 → true
        ├── SLB-6 → true
        ├── SMP-9 → true
        ├── SMA/SMK-12 → true
        └── KB/TPA/SPS → false (selalu, tidak peduli tingkat)

paud.blade.php
        │
        └── semester genap
              ↓
       {{ $labelKenaikan }}   ("Keterangan Kelulusan" utk TK-B, "Keterangan Kenaikan Kelas" lainnya)
              ↓
       {{ $catatan?->keterangan_kenaikan }}
```

Tidak ada perubahan database, tidak ada template SLB baru, tidak ada logic kelulusan baru — Priority 3 kecil dan terisolasi sesuai estimasi roadmap.
