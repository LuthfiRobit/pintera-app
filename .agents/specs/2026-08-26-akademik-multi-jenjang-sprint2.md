# Spec: Fondasi Akademik Multi-Jenjang — Sprint 2 (Assessment Type)

**Status:** Draft untuk review — belum masuk plan eksekusi.
**Branch:** `akademik-v2`
**Bergantung pada:** Sprint 1 (SELESAI — `subjek_type`/`subjek_id` polymorphic sudah live, di-drop kolom lama, full suite hijau).

## Latar Belakang

Sprint 1 menghilangkan asumsi "penilaian pasti punya mata pelajaran". Sprint 2 menghilangkan asumsi kedua yang masih tersisa: **"penilaian pasti berupa angka 0-100"**. Skema `nilai_siswa` sudah punya `nilai_angka` (nullable), `predikat`, `catatan` sejak awal — tapi `predikat` tidak pernah ditulis di mana pun, dan form input guru hardcode `<input type="number">`. PAUD butuh menilai secara naratif atau lewat skala capaian (BB/MB/BSH/BSB), bukan angka.

## Keputusan Desain (hasil 2 putaran review)

1. **`assessment_type` adalah fakta di DB, default dihitung di domain layer** — bukan database default yang "memahami" `subjek_type`. Kolom `komponen_penilaian.assessment_type` defaultnya `'numeric'` murni sebagai nilai kolom; logika "kalau `subjek_type=elemen_cp` maka defaultnya `narrative`" hidup di `CreateKomponenPenilaianAction` (application/domain layer), bukan di migration/trigger DB. Admin tetap bisa override manual.

2. **`AssessmentType` dan `PredikatPaud` adalah DUA konsep berbeda, tidak boleh dicampur.**
   - `AssessmentType` = **bagaimana nilai disimpan** (numeric/narrative/predicate) — generik, tidak spesifik jenjang.
   - `PredikatPaud` = **vocabulary nilai yang tersedia** untuk konteks PAUD saat `AssessmentType::Predicate` dipilih (BB/MB/BSH/BSB).
   - TIDAK ADA `AssessmentType::Paud` atau `AssessmentType::BB` — itu mencampur dua level konsep.
   - Nama `PredikatPaud` sengaja tetap scoped ke PAUD untuk sekarang (bukan `PredikatUniversal` atau semacamnya) — kalau nanti predicate dipakai di konteks lain dengan vocabulary berbeda, itu jadi enum baru terpisah, BUKAN memperluas `PredikatPaud` jadi generik prematur.

3. **Audit consumer `NilaiSiswa` WAJIB dilakukan sebelum mengklaim calculation layer aman** — hasil audit menentukan mana yang WAJIB diperbaiki Sprint 2 (Bucket C) vs mana yang boleh ditunda ke Sprint 5/Report Engine (Bucket B). Lihat §3.

4. **`UpdateNilaiSiswaRequest` mengambil `assessment_type` dari `KomponenPenilaian` di server, TIDAK PERNAH mempercayai nilai yang dikirim browser.**

5. **`SimpanNilaiSiswaAction` menjaga konsistensi data per tipe** (bukan cuma menambah `predikat` ke `updateOrCreate`) — kalau tipe `numeric`, paksa `predikat=null`; kalau `narrative`/`predicate`, paksa `nilai_angka=null`. Business rule tidak boleh hanya hidup di validasi request.

## §1. Skema Database

```php
Schema::table('komponen_penilaian', function (Blueprint $table) {
    $table->string('assessment_type')->default('numeric')->after('subjek_id');
});
```
Non-breaking: semua baris existing otomatis `'numeric'`, behavior lama tidak berubah tanpa aksi apa pun.

## §2. Enum Baru

`app/Domains/Akademik/Enums/AssessmentType.php`:
```php
enum AssessmentType: string
{
    case Numeric = 'numeric';
    case Narrative = 'narrative';
    case Predicate = 'predicate';

    public function label(): string
    {
        return match ($this) {
            self::Numeric => 'Nilai Angka',
            self::Narrative => 'Naratif/Deskriptif',
            self::Predicate => 'Predikat Capaian',
        };
    }
}
```

`app/Domains/Akademik/Enums/PredikatPaud.php`:
```php
enum PredikatPaud: string
{
    case BB = 'BB';
    case MB = 'MB';
    case BSH = 'BSH';
    case BSB = 'BSB';

    public function label(): string
    {
        return match ($this) {
            self::BB => 'Belum Berkembang',
            self::MB => 'Mulai Berkembang',
            self::BSH => 'Berkembang Sesuai Harapan',
            self::BSB => 'Berkembang Sangat Baik',
        };
    }
}
```

`KomponenPenilaian` model: tambah `'assessment_type'` ke `$fillable`, tambah cast `'assessment_type' => AssessmentType::class`.
`NilaiSiswa` model: tambah cast `'predikat' => PredikatPaud::class` (nullable enum cast — Laravel native support, aman untuk baris `numeric`/`narrative` yang `predikat`-nya tetap `null`).

## §3. Audit Consumer `NilaiSiswa` (WAJIB, hasil menentukan scope Sprint 2)

Audit menyeluruh dilakukan (bukan asumsi) terhadap setiap tempat yang membaca `NilaiSiswa`/`nilai_angka`/`KomponenPenilaian` dengan asumsi "semua komponen pasti numeric". Hasil:

### Bucket C — WAJIB diperbaiki Sprint 2 (akan menghasilkan angka SALAH, bukan cuma tidak lengkap)

| # | File | Kenapa Bucket C |
|---|---|---|
| C1 | `app/Domains/Akademik/Services/RaporCalculationService.php` (`hitungRekapKelas`) | Rata-rata tertimbang per subjek pakai `whereNotNull('nilai_angka')` sbg proxy "sudah dinilai". Begitu ada komponen `narrative`/`predicate` dengan bobot, komponen itu hilang diam-diam dari numerator DAN denominator (total bobot) — base pembagi weighted-average bergeser tanpa disengaja setiap kali guru menambah komponen non-numeric. Ini bug angka, bukan cuma UI tidak lengkap. |
| C2 | `app/Domains/Akademik/Services/CapaianKompetensiGenerator.php` (`generateNarasi`) | Sama pola: `whereNotNull('nilai_angka')->avg(...)` untuk cari skor tertinggi/terendah per komponen guna narasi otomatis. Komponen non-numeric ikut dilewati tanpa sinyal — narasi jadi berbasis subset komponen yang keliru diam-diam begitu campuran tipe makin banyak. |
| C3 | `app/Services/DashboardStatsService.php` (`statistikProgressRaporKelas`) | `$totalKomponen` (denominator) menghitung SEMUA komponen (numeric+narrative+predicate), tapi `$totalTerisi` (numerator) cuma hitung baris ber-`nilai_angka` non-null. Begitu ada komponen non-numeric, persentase progress **tidak akan pernah mencapai 100%** walau semua sel sudah benar-benar diisi sesuai tipenya masing-masing — mismatch numerator/denominator, bukan cuma tampilan kurang lengkap. |

**Fix Sprint 2 untuk C1-C3**: filter eksplisit ke `assessment_type === AssessmentType::Numeric`, bukan lagi mengandalkan `whereNotNull('nilai_angka')` sbg proxy semantik. Progress/rekap Sprint 2 SENGAJA hanya mengukur komponen numeric — komponen narrative/predicate belum ikut terhitung "complete" di widget manapun (itu pekerjaan Report Engine Sprint 5), TAPI angka yang ditampilkan untuk komponen numeric sendiri harus tetap benar (numerator/denominator konsisten), bukan bias oleh keberadaan komponen tipe lain.

### Bucket B — ditandai untuk Sprint 5 (Report Engine), TIDAK diubah Sprint 2

| # | File | Kenapa Bucket B (bukan C) |
|---|---|---|
| B1 | `resources/views/portals/guru/akademik/asesmen/show.blade.php` (`$filledCount`/`$progressPct`) | Progress bar input guru — begitu ada komponen narrative/predicate yang sudah diisi `catatan`/`predikat`, sel itu tetap terlihat "belum terisi" (karena masih cek `nilai_angka !== null`) walau sebenarnya sudah lengkap. Menyesatkan secara UX, tapi tidak menghasilkan angka SALAH yang dipakai untuk keputusan (rapor/rekap) — murni indikator progres pengisian form. |
| B2 | `app/Http/Controllers/Admin/DashboardController.php` (`$nilaiTerbaru` utk dashboard siswa & orang tua) | Widget "nilai terbaru" filter `whereNotNull('nilai_angka')` — begitu siswa PAUD punya nilai narrative/predicate terbaru, itu tidak muncul, yang muncul cuma nilai numeric lama (kalau ada). Tampilan jadi kurang relevan/stale untuk PAUD, tapi bukan angka yang salah dihitung. |

**Tidak ditemukan** consumer lain yang butuh perbaikan — sudah dicek gate kelengkapan rapor (`PengajuanRapor`/approval actions), tidak ada gate "semua nilai harus terisi" yang bergantung pada `nilai_angka` saat ini.

## §4. UI Form Komponen Penilaian (Lembaga & Guru portal) — Field Baru "Tipe Penilaian"

Ditambah `<select name="assessment_type">` di form create/edit `komponen-penilaian` (kedua portal), berdampingan dengan toggle "Jenis Subjek Penilaian" dari Sprint 1:
```blade
<div>
    <x-input-label value="Tipe Penilaian *" />
    <select name="assessment_type" x-model="assessmentType" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
        <option value="numeric">Nilai Angka</option>
        <option value="narrative">Naratif/Deskriptif</option>
        <option value="predicate">Predikat Capaian (BB/MB/BSH/BSB)</option>
    </select>
</div>
```
Alpine: saat radio `subjekType` diganti ke `elemen_cp`, `assessmentType` auto-di-set ke `'narrative'` (default domain, BUKAN dipaksa/disabled — admin tetap bebas ganti ke `predicate` atau bahkan `numeric` kalau memang mau). Saat `subjekType` ke `mata_pelajaran`, `assessmentType` auto-set `'numeric'`. Ini murni UX convenience (pre-fill), bukan constraint — field tetap `<select>` biasa yang bisa diubah bebas.

## §5. `KomponenPenilaianData` DTO, `CreateKomponenPenilaianAction`, `UpdateKomponenPenilaianAction`

DTO tambah `public ?string $assessmentType` (**nullable**, bukan required-tapi-boleh-kosong). Prinsip tegas 3 kondisi:
```text
null       → tidak diberikan sama sekali → domain layer hitung default
valid enum → override eksplisit dihormati apa adanya
invalid    → DITOLAK di FormRequest/DTO boundary (bukan diperlakukan sbg "kosong")
```
`KomponenPenilaianData::fromArray()`:
```php
assessmentType: isset($data['assessment_type']) && $data['assessment_type'] !== ''
    ? (string) $data['assessment_type']
    : null,
```
Validasi enum-validity (`Rule::enum(AssessmentType::class)`) tetap di `StoreKomponenPenilaianRequest`/`UpdateKomponenPenilaianRequest` sbg `nullable` — jadi input invalid (mis. `'foobar'`) sudah ditolak SEBELUM sampai ke DTO/Action sama sekali; DTO/Action hanya pernah menerima `null` (tidak diisi) atau nilai enum valid, tidak pernah nilai invalid.

`CreateKomponenPenilaianAction::execute()`: hitung default HANYA saat `null`:
```php
$assessmentType = $data->assessmentType ?? match ($data->subjekType) {
    'elemen_cp' => AssessmentType::Narrative->value,
    'mata_pelajaran' => AssessmentType::Numeric->value,
};
```

`UpdateKomponenPenilaianAction`: tambah `$komponen->assessment_type = $data->assessmentType ?? $komponen->assessment_type;` (pertahankan nilai lama kalau tidak dikirim).

## §6. `UpdateNilaiSiswaRequest` — Validasi Kondisional Server-Side

**WAJIB ambil `assessment_type` dari `KomponenPenilaian` di database, BUKAN dari input request.** Request tidak boleh membawa field `assessment_type` sama sekali di payload nilai — dia HANYA relevan sebagai properti `KomponenPenilaian` yang sudah tersimpan.

```php
public function rules(): array
{
    $asesmen = $this->route('asesmen');
    $tipePerKomponen = $asesmen->komponenPenilaian()->pluck('assessment_type', 'komponen_penilaian.id');

    $rules = [
        'nilai' => ['required', 'array'],
    ];

    foreach ($tipePerKomponen as $komponenId => $tipe) {
        $prefix = "nilai.*.{$komponenId}";
        $rules["{$prefix}.nilai_angka"] = match ($tipe) {
            'numeric' => ['nullable', 'integer', 'min:0', 'max:100'],
            default => ['prohibited'],
        };
        $rules["{$prefix}.predikat"] = match ($tipe) {
            'predicate' => ['nullable', Rule::in(array_column(PredikatPaud::cases(), 'value'))],
            default => ['prohibited'],
        };
        $rules["{$prefix}.catatan"] = match ($tipe) {
            'narrative' => ['required', 'string'],
            default => ['nullable', 'string'],
        };
    }

    return $rules;
}
```
Catatan: `nilai.*.{$komponenId}` memakai wildcard `*` untuk segmen siswa (index array pertama tetap dinamis per siswa) dan `{$komponenId}` literal untuk segmen komponen (karena komponen per-asesmen jumlahnya tetap & diketahui di server) — detail keakuratan pola wildcard Laravel untuk struktur nested-per-key-dinamis ini WAJIB diverifikasi saat implementasi (lihat catatan implementer di plan).

`required`/`prohibited` bukan `nullable` polos untuk `narrative` — supaya guru tidak bisa submit sel narrative kosong lalu lolos begitu saja (beda dari `numeric` yang boleh dikosongkan dulu = belum dinilai).

**Acceptance criterion eksplisit (regression/security test)**: payload `nilai.*.*` TIDAK PERNAH punya field `assessment_type` di dalamnya — field itu murni properti `KomponenPenilaian` yang sudah tersimpan di DB, bukan bagian dari struktur payload nilai sama sekali. Kalau browser/client secara paksa mengirim `nilai[siswaId][komponenId][assessment_type]=numeric` (mis. lewat DevTools, mencoba menyamarkan komponen `predicate` seolah `numeric` supaya validasi `prohibited` tidak berlaku), field itu WAJIB diabaikan total — tidak pernah dibaca `UpdateNilaiSiswaRequest` maupun `SimpanNilaiSiswaAction`, dan tidak mempengaruhi validasi maupun penyimpanan. Rules di atas sudah otomatis memenuhi ini (rules dibangun dari `$tipePerKomponen` hasil query DB, bukan dari input request) — tapi test eksplisit WAJIB ada utk mengunci perilaku ini sbg kontrak, bukan kebetulan.

## §7. `SimpanNilaiSiswaAction` — Konsistensi Data per Tipe (Bukan Cuma Simpan Apa Adanya)

```php
foreach ($perKomponen as $komponenId => $values) {
    if (! $komponenIds->contains((int) $komponenId)) {
        continue;
    }

    $tipe = $tipePerKomponen->get((int) $komponenId); // AssessmentType value, di-load sekali di awal method dari $asesmen->komponenPenilaian()

    NilaiSiswa::updateOrCreate(
        ['asesmen_id' => $asesmen->id, 'siswa_id' => $siswaId, 'komponen_penilaian_id' => $komponenId],
        match ($tipe) {
            'numeric' => [
                'nilai_angka' => isset($values['nilai_angka']) && $values['nilai_angka'] !== '' ? (int) $values['nilai_angka'] : null,
                'predikat' => null,
                'catatan' => $values['catatan'] ?? null,
            ],
            'narrative' => [
                'nilai_angka' => null,
                'predikat' => null,
                'catatan' => $values['catatan'] ?? null,
            ],
            'predicate' => [
                'nilai_angka' => null,
                'predikat' => $values['predikat'] ?? null,
                'catatan' => $values['catatan'] ?? null,
            ],
        }
    );
}
```
Ini memaksa konsistensi DI ACTION juga (bukan cuma di Request) — kalau suatu saat ada jalur lain yang memanggil Action ini tanpa lewat `UpdateNilaiSiswaRequest` (mis. import/seed programatik), data tetap tidak bisa jadi campur aduk (`nilai_angka` DAN `predikat` sama-sama terisi utk baris yang sama).

## §8. UI Form Input Nilai Guru (`asesmen/show.blade.php`)

Render kondisional per kolom komponen berdasar `$komponen->assessment_type`:

```blade
@foreach ($komponenList as $komponen)
    <td class="px-4 py-4 space-y-1.5">
        @if ($komponen->assessment_type === App\Domains\Akademik\Enums\AssessmentType::Numeric)
            <input type="number" step="1" min="0" max="100"
                name="nilai[{{ $siswa->id }}][{{ $komponen->id }}][nilai_angka]"
                value="{{ old('nilai.'.$siswa->id.'.'.$komponen->id.'.nilai_angka', $nilai?->nilai_angka) }}"
                placeholder="0 - 100"
                class="w-24 text-center font-extrabold text-base rounded-lg border-gray-300 py-1.5 shadow-sm focus:border-brand-500 focus:ring-brand-500">
            <input type="text" name="nilai[{{ $siswa->id }}][{{ $komponen->id }}][catatan]"
                value="{{ old('nilai.'.$siswa->id.'.'.$komponen->id.'.catatan', $nilai?->catatan) }}"
                placeholder="Catatan..." class="w-full rounded-lg border-gray-200 text-xs text-gray-900 shadow-sm py-1.5 px-2.5">
        @elseif ($komponen->assessment_type === App\Domains\Akademik\Enums\AssessmentType::Narrative)
            <textarea name="nilai[{{ $siswa->id }}][{{ $komponen->id }}][catatan]" rows="2" required
                placeholder="Deskripsi perkembangan (wajib)..."
                class="w-full rounded-lg border-gray-200 text-xs text-gray-900 shadow-sm py-1.5 px-2.5">{{ old('nilai.'.$siswa->id.'.'.$komponen->id.'.catatan', $nilai?->catatan) }}</textarea>
        @elseif ($komponen->assessment_type === App\Domains\Akademik\Enums\AssessmentType::Predicate)
            <select name="nilai[{{ $siswa->id }}][{{ $komponen->id }}][predikat]" required
                class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">— Pilih —</option>
                @foreach (App\Domains\Akademik\Enums\PredikatPaud::cases() as $predikatOpsi)
                    <option value="{{ $predikatOpsi->value }}" @selected(old('nilai.'.$siswa->id.'.'.$komponen->id.'.predikat', $nilai?->predikat?->value) === $predikatOpsi->value)>{{ $predikatOpsi->value }} — {{ $predikatOpsi->label() }}</option>
                @endforeach
            </select>
            <input type="text" name="nilai[{{ $siswa->id }}][{{ $komponen->id }}][catatan]"
                value="{{ old('nilai.'.$siswa->id.'.'.$komponen->id.'.catatan', $nilai?->catatan) }}"
                placeholder="Catatan tambahan (opsional)..." class="w-full rounded-lg border-gray-200 text-xs text-gray-900 shadow-sm py-1.5 px-2.5">
        @endif
    </td>
@endforeach
```

**Non-goal eksplisit (ditegaskan ulang)**: `$filledCount`/`$progressPct` di file yang sama (Bucket B1) TIDAK diubah Sprint 2 — progress bar tetap mengacu `nilai_angka !== null` apa adanya, sengaja meleset utk komponen non-numeric, ditandai untuk Sprint 5.

## §9. Test Matrix (Acceptance Criteria WAJIB, bukan cuma happy path)

**Layer 1 — `UpdateNilaiSiswaRequest` (HTTP validation, via `$this->post(...)`):**

| Tipe | nilai_angka | predikat | catatan | Hasil |
|---|---|---|---|---|
| Numeric | 85 | — | opsional | ✅ lolos |
| Numeric | kosong | — | — | ✅ lolos (null, belum dinilai) |
| Numeric | 85 | BSH | — | ❌ ditolak — `predikat` `prohibited` untuk tipe numeric |
| Narrative | — | — | wajib diisi | ✅ lolos |
| Narrative | — | — | kosong | ❌ ditolak — `catatan` `required` |
| Narrative | 85 | — | diisi | ❌ ditolak — `nilai_angka` `prohibited` untuk tipe narrative |
| Predicate | — | BSH | opsional | ✅ lolos |
| Predicate | — | kosong | — | ❌ ditolak — `predikat` wajib |
| Predicate | — | `"invalid_value"` | — | ❌ ditolak — bukan salah satu BB/MB/BSH/BSB |

**Layer 2 — `SimpanNilaiSiswaAction` (unit test, panggil Action langsung dgn `NilaiSiswaBatchData` buatan tangan, MELEWATI validasi HTTP sepenuhnya):**

Menguji invariant §7 sbg defense-in-depth murni — payload sengaja "kotor" (field yang seharusnya `prohibited` tetap diisi) untuk membuktikan Action membersihkannya sendiri terlepas dari apakah Request sempat memvalidasi atau tidak:

| Tipe komponen | Payload dipaksa | Hasil tersimpan di `NilaiSiswa` |
|---|---|---|
| numeric | `nilai_angka=85, predikat='BSH', catatan='x'` | `nilai_angka=85, predikat=NULL, catatan='x'` |
| narrative | `nilai_angka=85, predikat='BSH', catatan='y'` | `nilai_angka=NULL, predikat=NULL, catatan='y'` |
| predicate | `nilai_angka=85, predikat='BSH', catatan='z'` | `nilai_angka=NULL, predikat='BSH', catatan='z'` |

Dua layer ini SENGAJA diuji terpisah (bukan cuma satu test HTTP end-to-end) — Layer 1 membuktikan "request menolak input salah", Layer 2 membuktikan "Action tidak pernah bisa menyimpan kombinasi tidak konsisten, bahkan kalau suatu saat dipanggil dari jalur lain yang melewati Request sama sekali (mis. import/seed programatik)".

Ditambah:
- Create `KomponenPenilaian` dengan `subjek_type=elemen_cp` tanpa `assessment_type` eksplisit → default `narrative`.
- Create `KomponenPenilaian` dengan `subjek_type=mata_pelajaran` tanpa `assessment_type` eksplisit → default `numeric`.
- Create dengan `assessment_type` eksplisit override (mis. `elemen_cp` + `predicate`) → override dihormati, TIDAK dipaksa ke default.
- **Regresi wajib**: `KomponenPenilaian` existing (dari data Sprint 1 / sebelum Sprint 2) otomatis `assessment_type='numeric'`, dan seluruh alur numeric existing (input nilai, rekap, rapor) tetap berjalan tanpa perubahan perilaku **yang tidak disengaja**. Catatan penting: C1/C2/C3 (§3) MEMANG sengaja diubah supaya hanya memperhitungkan komponen `assessment_type=numeric` secara eksplisit (bukan lagi via `whereNotNull('nilai_angka')`) — test regresi untuk 3 service itu HARUS memverifikasi hasil hitungnya identik dengan sebelumnya UNTUK KASUS yang seluruh komponennya numeric (tidak ada perubahan angka di skenario itu), BUKAN menguji bahwa kode/implementasinya tidak berubah sama sekali.
- C1/C2/C3 (§3): test yang membuktikan rekap/narasi/progress **angka-nya benar** saat ada campuran komponen numeric+narrative+predicate dalam satu kelas/semester (bukan cuma "tidak error").

## Non-Goals Sprint 2 (eksplisit)

- Rapor PDF (`RaporPdfDataBuilder` output visual) TIDAK dibuat mendukung tampilan narrative/predicate — itu Sprint 5 (Report Engine).
- Progress bar UI (B1) dan widget "nilai terbaru" dashboard (B2) TIDAK diperbaiki — ditandai Sprint 5.
- Tidak ada tabel/konfigurasi predikat per-lembaga — `PredikatPaud` tetap enum fixed sesuai keputusan review.
- Tidak ada gate "rapor tidak bisa diajukan kalau ada nilai narrative/predicate belum lengkap" — di luar cakupan (§3 hasil audit: gate itu tidak ada sama sekali saat ini, tetap tidak dibangun).

## Self-Review

- Semua 8 poin syarat dari review Sprint 2 masuk eksplisit: (1) default domain-layer bukan DB §5, (2) AssessmentType vs PredikatPaud dipisah §2, (3) audit lengkap §3 dgn 3 Bucket C + 2 Bucket B dijelaskan alasannya, (4) validasi server-side dari KomponenPenilaian bukan trust-request §6, (5) konsistensi data di Action §7, (6) test matrix eksplisit §9, (7) non-goal report PDF ditegaskan ulang.
- Placeholder scan: satu titik ditandai eksplisit sbg "perlu diverifikasi saat implementasi" (pola wildcard Laravel `nilai.*.{id}` di §6) — bukan placeholder, tapi ketidakpastian teknis yang jujur dan spesifik. Plan WAJIB memuat task RED eksplisit lebih dulu: minimal 2 siswa × 2 komponen (tipe berbeda) dikirim dalam satu request, buktikan tiap kombinasi baris/kolom tervalidasi sesuai `assessment_type` komponennya masing-masing (bukan tercampur/salah sasaran antar siswa atau antar komponen) — SEBELUM rule di §6 dianggap final/GREEN.
