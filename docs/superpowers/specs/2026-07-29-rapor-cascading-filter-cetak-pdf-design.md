# Halaman Rekap Rapor — Cascading Filter, Cetak PDF, Rapikan Ambang Nilai

## Goal

Halaman Rekap Rapor (`admin/rapor/index.blade.php`) punya 3 masalah:

1. Filter Kelas & Semester berdiri sendiri-sendiri — Semester tidak di-scope ke Tahun Ajaran milik Kelas yang dipilih, sehingga user bisa memilih kombinasi yang secara logis tidak masuk akal (Kelas TA 2025/2026 + Semester dari TA 2024/2025) tanpa dicegah sistem.
2. Tombol "Cetak Rekap Nilai" cuma `window.print()` tanpa print-stylesheet — hasil cetak menyertakan seluruh chrome aplikasi (sidebar, topbar, filter card), bukan cuma tabel rekap.
3. Ambang "Tuntas (≥75)" hardcoded di Blade sebagai angka mentah.

Paket ini memperbaiki ketiganya: filter jadi cascading Tahun Ajaran → Kelas & Semester (reuse pola `opsi()` dari Komponen Penilaian), ekspor PDF asli via `barryvdh/laravel-dompdf` (sudah terpasang & dipakai di 3 tempat lain), dan ambang nilai dipindah ke satu config value.

## Requirements

1. Tambah dropdown **Tahun Ajaran** di filter, default ke Tahun Ajaran aktif. Field ini wajib (tidak ada opsi "Semua Tahun Ajaran") karena Kelas & Semester sama-sama bergantung padanya.
2. Dropdown **Kelas** dan **Semester** sama-sama ter-filter ke Tahun Ajaran terpilih (fan-out satu level dari TA — Kelas dan Semester tidak saling bergantung satu sama lain). Ganti Tahun Ajaran → keduanya di-refresh dan otomatis diisi ulang ke opsi pertama masing-masing (supaya tetap langsung menampilkan rekap tanpa 2 klik tambahan), lalu daftar hasil ikut dimuat ulang.
3. Ganti Kelas atau Semester (tanpa ganti TA) → hanya muat ulang hasil (stat + matriks) via AJAX fragment, tanpa reload halaman penuh — pola sama seperti `komponen-penilaian-filter.js`.
4. Semua 3 dropdown jadi Tom Select.
5. **Cetak Rekap Nilai** memakai `barryvdh/laravel-dompdf`: `Pdf::loadView('pdf.rekap-rapor', [...])->stream(...)`, dibuka di tab baru. View PDF terpisah dari `app-layout` (tanpa sidebar/topbar), isinya cuma header info Kelas/Semester + tabel matriks, dengan style yang sama (badge Tuntas/Perlu Bimbingan).
6. Ambang nilai (`75`) dipindah ke `config('akademik.ambang_tuntas')`, dipakai baik oleh view web maupun view PDF — tidak ada perubahan skema database, `kktp` tetap teks bebas.
7. Endpoint baru (`opsi`, `cetak`) dan `index()` tetap tenant-safe: `Kelas`/`Semester`/`TahunAjaran` sudah pakai `BelongsToTenant`, jadi `::find()` pada ID milik tenant lain mengembalikan `null` → `abort_if(..., 404)`, mengikuti pola yang sudah ada di `KomponenPenilaianController::opsi()`.
8. Kalau `kelas_id`/`semester_id` di query string ternyata bukan milik Tahun Ajaran yang dipilih (mis. user mengetik URL manual atau state lama), abaikan dan jatuhkan ke default (Kelas & Semester pertama pada TA itu) — ini yang menutup bug ambiguitas di poin 1.

## Arsitektur

### Backend — `RaporController`

Data rata-rata per siswa/mapel + ringkasan kelas (classAvg, highestScore) saat ini dihitung lewat `@php` block di dalam Blade `index.blade.php`, dan akan dibutuhkan LAGI oleh view PDF. Pindahkan perhitungan itu ke controller (satu fungsi privat `hitungRekap()`) supaya tidak duplikasi logic antara `_hasil.blade.php` dan `pdf/rekap-rapor.blade.php`.

```php
public function index(Request $request): View|string
{
    $this->authorize('rapor.view');

    $tahunAjaranId = $request->query('tahun_ajaran_id') ?: TahunAjaran::where('status_aktif', true)->value('id');

    $kelasList = $tahunAjaranId ? Kelas::where('tahun_ajaran_id', $tahunAjaranId)->orderBy('nama')->get() : collect();
    $semesterList = $tahunAjaranId ? Semester::where('tahun_ajaran_id', $tahunAjaranId)->orderByDesc('id')->get() : collect();

    $kelasId = $request->query('kelas_id');
    if (! $kelasId || ! $kelasList->contains('id', (int) $kelasId)) {
        $kelasId = $kelasList->first()?->id;
    }
    $semesterId = $request->query('semester_id');
    if (! $semesterId || ! $semesterList->contains('id', (int) $semesterId)) {
        $semesterId = $semesterList->first()?->id;
    }

    $selectedKelas = $kelasId ? Kelas::find($kelasId) : null;
    $selectedSemester = $semesterId ? Semester::find($semesterId) : null;

    $data = $this->hitungRekap($selectedKelas, $selectedSemester);

    if ($request->ajax()) {
        return view('admin.rapor._hasil', [
            'selectedKelas' => $selectedKelas,
            'selectedSemester' => $selectedSemester,
            ...$data,
        ])->render();
    }

    return view('admin.rapor.index', [
        'tahunAjaranList' => TahunAjaran::orderByDesc('id')->get(),
        'tahunAjaranId' => $tahunAjaranId,
        'kelasList' => $kelasList,
        'semesterList' => $semesterList,
        'selectedKelas' => $selectedKelas,
        'selectedSemester' => $selectedSemester,
        ...$data,
    ]);
}

public function opsi(Request $request): JsonResponse
{
    $this->authorize('rapor.view');

    $data = $request->validate(['tahun_ajaran_id' => ['required', 'integer']]);

    $tahunAjaran = TahunAjaran::find($data['tahun_ajaran_id']);
    abort_if($tahunAjaran === null, 404);

    return response()->json([
        'kelasList' => Kelas::where('tahun_ajaran_id', $tahunAjaran->id)->orderBy('nama')->get(['id', 'nama']),
        'semesterList' => Semester::where('tahun_ajaran_id', $tahunAjaran->id)->orderByDesc('id')->get(['id', 'nama']),
    ]);
}

public function cetak(Request $request): Response
{
    $this->authorize('rapor.view');

    $data = $request->validate([
        'kelas_id' => ['required', 'integer'],
        'semester_id' => ['required', 'integer'],
    ]);

    $selectedKelas = Kelas::find($data['kelas_id']);
    abort_if($selectedKelas === null, 404);
    $selectedSemester = Semester::find($data['semester_id']);
    abort_if($selectedSemester === null, 404);

    $pdf = Pdf::loadView('pdf.rekap-rapor', [
        'selectedKelas' => $selectedKelas,
        'selectedSemester' => $selectedSemester,
        ...$this->hitungRekap($selectedKelas, $selectedSemester),
    ]);

    return $pdf->stream('rekap-rapor-'.$selectedKelas->nama.'.pdf');
}

private function hitungRekap(?Kelas $kelas, ?Semester $semester): array
{
    if (! $kelas || ! $semester) {
        return ['siswaList' => collect(), 'mapelList' => collect(), 'rekapNilai' => [], 'classAvg' => null, 'highestScore' => null];
    }

    $siswaList = Siswa::where('kelas_id', $kelas->id)->orderBy('nama_lengkap')->get();

    $asesmenList = Asesmen::where('kelas_id', $kelas->id)->where('semester_id', $semester->id)->with('mataPelajaran')->get();
    $mapelList = $asesmenList->pluck('mataPelajaran')->unique('id')->sortBy('nama');
    $allNilai = NilaiSiswa::whereIn('asesmen_id', $asesmenList->pluck('id'))->get();

    $rekapNilai = [];
    foreach ($siswaList as $siswa) {
        $rekapNilai[$siswa->id] = [];
        foreach ($mapelList as $mapel) {
            $mapelAsesmenIds = $asesmenList->where('mata_pelajaran_id', $mapel->id)->pluck('id');
            $scores = $allNilai->whereIn('asesmen_id', $mapelAsesmenIds)->where('siswa_id', $siswa->id)->whereNotNull('nilai_angka')->pluck('nilai_angka');
            $rekapNilai[$siswa->id][$mapel->id] = $scores->count() > 0 ? round($scores->avg(), 1) : null;
        }
    }

    $allScores = collect($rekapNilai)->flatMap(fn ($m) => collect($m)->filter(fn ($v) => $v !== null));

    return [
        'siswaList' => $siswaList,
        'mapelList' => $mapelList,
        'rekapNilai' => $rekapNilai,
        'classAvg' => $allScores->count() > 0 ? round($allScores->avg(), 1) : null,
        'highestScore' => $allScores->count() > 0 ? $allScores->max() : null,
    ];
}
```

Catatan: per-siswa `generalAvg` (rata-rata umum tiap baris) tetap dihitung inline di view (`_hasil.blade.php` dan `pdf/rekap-rapor.blade.php`) dari `$rekapNilai[$siswa->id]` — nilainya trivial (`array_filter` + rata-rata) dan sudah dipakai identik di kedua tempat lewat `@php` kecil, tidak perlu naik ke controller.

### Routes (`routes/admin.php`)

```php
Route::get('rapor', [RaporController::class, 'index'])->name('rapor.index');
Route::get('rapor/opsi', [RaporController::class, 'opsi'])->name('rapor.opsi');
Route::get('rapor/cetak', [RaporController::class, 'cetak'])->name('rapor.cetak');
```

### Frontend

- `index.blade.php`: filter card jadi 3 select (Tahun Ajaran, Kelas, Semester) dengan `x-data="raporFilter({ tahunAjaranId, kelasId, semesterId, opsiUrl, indexUrlBase })"`; area hasil (`x-ref="hasilRapor"`) meng-`@include('admin.rapor._hasil')` untuk render awal.
- `_hasil.blade.php` (baru): isi dari stat summary cards + matrix table + kedua empty state (belum pilih / belum ada siswa) yang sekarang ada di `index.blade.php` — dipindah apa adanya, dipakai baik oleh render awal maupun response AJAX. Tombol "Cetak Rekap Nilai" di sini jadi `<a href="{{ route('admin.rapor.cetak', ['kelas_id' => $selectedKelas->id, 'semester_id' => $selectedSemester->id]) }}" target="_blank">` — dihitung server-side tiap partial di-render, tidak perlu Alpine binding.
- `resources/js/rapor-filter.js` (baru, pola sama seperti `komponen-penilaian-filter.js`): `initTahunAjaranSelect` → `gantiTahunAjaran()` fetch `opsiUrl`, replace opsi Kelas & Semester, auto-pilih opsi pertama masing-masing, lalu `muatUlangDaftar()`; `initKelasSelect`/`initSemesterSelect` → langsung `muatUlangDaftar()` on change; `muatUlangDaftar()` fetch `indexUrlBase` dengan query `tahun_ajaran_id`/`kelas_id`/`semester_id`, swap `innerHTML` ke `$refs.hasilRapor`, update URL via `history.pushState`.
- Registrasi di `resources/js/app.js`: `import { raporFilter } from './rapor-filter'; Alpine.data('raporFilter', raporFilter);`

### PDF — `resources/views/pdf/rekap-rapor.blade.php`

HTML polos (tanpa Tailwind/app-layout) mengikuti pola `pdf/bukti-pendaftaran.blade.php`: `<style>` inline sederhana, header "Rekap Nilai Rapor — {Kelas} — {Semester} ({TahunAjaran})", tabel matriks identik dengan versi web (kolom No, Nama, per-mapel, Rata-Rata Umum), badge warna Tuntas/Perlu Bimbingan pakai warna solid (dompdf tidak selalu render Tailwind opacity/gradient dengan baik, dipakai warna hex langsung).

### Config — `config/akademik.php` (baru)

```php
return [
    'ambang_tuntas' => 75,
];
```

Dipakai di `_hasil.blade.php`, `pdf/rekap-rapor.blade.php` sebagai `config('akademik.ambang_tuntas')`, menggantikan angka `75` yang sebelumnya hardcoded di 2 tempat (badge warna per skor + legend).

## Self-Review

- **Tidak ada perubahan skema database** — `kktp` tetap teks bebas, ambang jadi config bukan kolom.
- **Tenant-safety**: `opsi()` dan `cetak()` keduanya pakai `Model::find()` yang sudah di-scope `BelongsToTenant`, plus `abort_if(... === null, 404)` — pola yang sama dengan `KomponenPenilaianController::opsi()`, mencegah IDOR lintas tenant (pola bug yang berulang di proyek Presensi/Asesmen).
- **Requirement #8 tertutup**: `index()` selalu memvalidasi `kelas_id`/`semester_id` dari query string terhadap `$kelasList`/`$semesterList` yang sudah di-scope ke `$tahunAjaranId` — kombinasi yang tidak match otomatis diganti default, bukan ditolak (404) atau dibiarkan tampil salah.
- **Scope**: hanya menyentuh `RaporController`, view `admin/rapor/*`, 1 JS module baru, 1 config baru, dan `routes/admin.php`. Tidak menyentuh modul lain (Komponen Penilaian, Asesmen, dsb).
