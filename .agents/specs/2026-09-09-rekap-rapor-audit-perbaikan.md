# Audit & Perbaikan Menu Rekap Rapor

**Tanggal**: 2026-09-09
**Branch**: `rbac-v2` (di sesi ini juga disebut `akademik-v2` — sama branch)
**Status**: Draft — menunggu review user sebelum plan+kickoff

## Ringkasan

Audit mendalam backend+frontend menu **Rekap Rapor** (`admin.rapor.index`, `RaporController`, `RaporCalculationService`, view `portals/lembaga/akademik/rapor/*.blade.php`, PDF `pdf/rekap-rapor.blade.php`) menemukan **1 bug kritis** (ambiguitas default filter untuk aktor yayasan mode agregat — kelas bug yang SAMA dengan yang baru diperbaiki di menu TP), **1 kesenjangan besar** (halaman ini TIDAK PERNAH ikut gelombang standarisasi badge-scope yang sudah diterapkan di ~15 menu lain sesi ini), dan beberapa item wording/kelengkapan dokumen minor.

**Koreksi penting terhadap catatan lama**: `.agents/logs/2026-09-07-audit-scope-yayasan-lembaga-sidebar.md` baris 89 SEBELUMNYA mencatat Rekap Rapor sebagai `✅ ✅` ("Diperbaiki Task 7"). Audit ulang kali ini (baca kode langsung, bukan percaya catatan lama) MEMBUKTIKAN catatan itu SUDAH TIDAK AKURAT — "perbaikan Task 7" yang dimaksud (suffix nama lembaga di dropdown Tahun Ajaran) justru dibangun di atas pola yang salah (baca `session()` mentah tanpa validasi), dan tidak menyentuh akar masalah (default filter yang ambigu). Spec ini akan memperbarui baris itu setelah perbaikan selesai.

**Backend inti (`RaporCalculationService::hitungRekapKelas()`, agregasi numeric/predicate/narrative, guard `opsi()`/`cetak()`) SUDAH BENAR** — tidak ada bug hitung, tidak ada N+1 query, tidak ada kebocoran lintas-yayasan. Fokus spec ini murni pada bug ambiguitas filter + kesenjangan scope-awareness UI, konsisten dengan pola menu TP sebelumnya.

**Ketergantungan urutan implementasi (WAJIB dibaca sebelum eksekusi)**: Item A dan Item B SAMA-SAMA mengubah `RaporController.php` dan SALING BERGANTUNG — kode baru Item A (`index()`) memanggil `$this->scopeHeaderData($request)` yang baru DIDEFINISIKAN di Item B, dan `$isYayasanAggregate` di Item A memakai `resolveActiveLembagaId()` dari trait yang di-`use` di Item A tapi baru benar-benar DIPAKAI juga oleh Item B. **Item A dan Item B WAJIB diimplementasikan BERSAMAAN dalam 1 task/1 commit**, TIDAK BISA dipisah jadi 2 task berurutan seperti item lain — kalau dipisah, kode akan gagal (method belum ada) di tengah jalan.

---

## Item A — 🔴 Kritis: Ambiguitas Default Filter untuk Aktor Yayasan Mode Agregat

### Masalah

**Dibuktikan lewat pembacaan kode langsung**: `RaporController::index()` baris 39-41:

```php
if (! $tahunAjaranId) {
    $tahunAjaranId = TahunAjaran::where('status_aktif', true)->value('id');
}
```

TIDAK ADA pengecekan mode agregat sama sekali. Untuk aktor yayasan (dikonfirmasi: role `yayasan_super_admin`, yayasan-scope, punya `rapor.view` lewat `Permission::all()`) yang belum switch ke 1 lembaga via pengalih topbar, baris ini memilih tahun ajaran SATU lembaga secara acak (baris pertama yang cocok di DB). Efeknya BERANTAI (bukan cuma 1 dropdown): `$kelasList`/`$semesterList` (baris 43-44) otomatis cuma berisi kelas & semester lembaga itu, LALU `$kelasId`/`$semesterId` (baris 46-53) otomatis memilih kelas & semester PERTAMA dari daftar itu. **Hasil akhirnya**: kunjungan pertama ke halaman ini oleh aktor yayasan mode agregat langsung menampilkan REKAP NILAI SATU KELAS SATU LEMBAGA SECARA ACAK, tanpa satu pun indikasi ke user bahwa ini bukan "kelas yang wajar/relevan" — user bisa mengira rekap yang tampil itu representatif, padahal sepenuhnya acak tergantung urutan baris database.

**Beda dengan Item C spec TP sebelumnya**: di TP, resolusi yang benar adalah "default ke `null` = tampilkan SEMUA data lintas lembaga" (karena halaman index TP memang bisa menampilkan banyak TP dari lembaga berbeda sekaligus). **Rekap Rapor TIDAK BISA begitu** — 1 rekap HANYA bisa untuk 1 kelas pada satu waktu (tabelnya matriks siswa x mapel untuk 1 kelas spesifik, tidak ada konsep "rekap gabungan semua kelas semua lembaga" yang masuk akal). Jadi resolusi yang benar di sini BUKAN "tampilkan semua", tapi **"jangan auto-pilih apa pun — wajib user pilih Tahun Ajaran (dan otomatis itu berarti memilih 1 lembaga) secara sadar dulu"**, mirip pola `➖ (wajib pilih 1)` yang sudah dipakai untuk menu lain di `.agents/logs/2026-09-07-audit-scope-yayasan-lembaga-sidebar.md` (Pengaturan Akademik, Jadwal Piket Guru).

**Kenapa auto-pick Kelas/Semester (baris 46-53) TIDAK PERLU guard terpisah**: begitu Tahun Ajaran TIDAK di-auto-pick (tetap `null`), `$kelasList`/`$semesterList` otomatis jadi `collect()` kosong (baris 43-44, sudah pakai ternary `$tahunAjaranId ? ... : collect()`), sehingga `$kelasId`/`$semesterId` juga otomatis tetap `null` (list kosong = `->first()?->id` = `null`). Efek berantai yang tadinya jadi SUMBER masalah kini otomatis jadi SOLUSI — cukup 1 guard di titik akar (pemilihan Tahun Ajaran), tidak perlu 3 guard terpisah.

**Kenapa deep-link (`kelas_id` eksplisit di URL) TETAP harus berfungsi apa adanya, bahkan di mode agregat**: baris 33-38 SUDAH punya logic "derive tahun_ajaran_id dari kelas_id kalau ada" — ini BUKAN kasus "ambigu", ini permintaan EKSPLISIT (bookmark/link yang dibagikan). Guard baru HANYA berlaku pada langkah fallback TERAKHIR (baris 39-41), SETELAH upaya derive dari `kelas_id` sudah dicoba dan tetap gagal.

### Perbaikan

**1. `app/Http/Controllers/Admin/RaporController.php`** — tambahkan import trait DAN `use` di dalam class (dipakai juga oleh Item B — digabung sekali supaya tidak 2x ubah bagian yang sama). Ganti (baris 1-20):

```php
use App\Domains\Akademik\Services\RaporCalculationService;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\TahunAjaran;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RaporController extends BaseController
{
    use AuthorizesRequests;
```

menjadi:

```php
use App\Domains\Akademik\Services\RaporCalculationService;
use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\TahunAjaran;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RaporController extends BaseController
{
    use AuthorizesRequests;
    use ResolveLembagaScopeTrait;
```

(`App\Models\Lembaga` dipakai oleh `scopeHeaderData()` di Item B — diimpor sekaligus di sini supaya tidak 2x mengubah blok `use` yang sama.)

Lalu ganti method `index()` (baris 27-77):

```php
public function index(Request $request): View|string
{
    $this->authorize('rapor.view');

    $tahunAjaranId = is_scalar($request->query('tahun_ajaran_id')) ? $request->query('tahun_ajaran_id') : null;
    $kelasIdParam = is_scalar($request->query('kelas_id')) ? $request->query('kelas_id') : null;
    if (! $tahunAjaranId && $kelasIdParam) {
        // Deep link with kelas_id but no tahun_ajaran_id (e.g. a bookmarked/shared URL):
        // derive it from the kelas itself instead of falling back to the active tahun
        // ajaran, which may not be the one the kelas actually belongs to.
        $tahunAjaranId = Kelas::find($kelasIdParam)?->tahun_ajaran_id;
    }
    if (! $tahunAjaranId) {
        $tahunAjaranId = TahunAjaran::where('status_aktif', true)->value('id');
    }

    $kelasList = $tahunAjaranId ? Kelas::where('tahun_ajaran_id', $tahunAjaranId)->orderBy('nama')->get() : collect();
    $semesterList = $tahunAjaranId ? Semester::where('tahun_ajaran_id', $tahunAjaranId)->orderByDesc('id')->get() : collect();

    $kelasId = $kelasIdParam;
    if (! $kelasId || ! $kelasList->contains('id', (int) $kelasId)) {
        $kelasId = $kelasList->first()?->id;
    }
    $semesterId = is_scalar($request->query('semester_id')) ? $request->query('semester_id') : null;
    if (! $semesterId || ! $semesterList->contains('id', (int) $semesterId)) {
        $semesterId = $semesterList->first()?->id;
    }

    $selectedKelas = $kelasId ? Kelas::find($kelasId) : null;
    $selectedSemester = $semesterId ? Semester::find($semesterId) : null;

    $rekap = ($selectedKelas && $selectedSemester)
        ? $this->raporCalculationService->hitungRekapKelas($selectedKelas, $selectedSemester)
        : $this->rekapKosong();

    if ($request->ajax()) {
        return view('portals.lembaga.akademik.rapor._hasil', array_merge([
            'selectedKelas' => $selectedKelas,
            'selectedSemester' => $selectedSemester,
        ], $rekap))->render();
    }

    return view('portals.lembaga.akademik.rapor.index', array_merge([
        'tahunAjaranList' => TahunAjaran::with('lembaga')->orderByDesc('id')->get(),
        'tahunAjaranId' => $tahunAjaranId,
        'kelasList' => $kelasList,
        'semesterList' => $semesterList,
        'selectedKelas' => $selectedKelas,
        'selectedSemester' => $selectedSemester,
    ], $rekap));
}
```

menjadi:

```php
public function index(Request $request): View|string
{
    $this->authorize('rapor.view');

    $isYayasanAggregate = $request->user()->widestScopeLevel() === 'yayasan' && $this->resolveActiveLembagaId($request->user()) === null;

    $tahunAjaranId = is_scalar($request->query('tahun_ajaran_id')) ? $request->query('tahun_ajaran_id') : null;
    $kelasIdParam = is_scalar($request->query('kelas_id')) ? $request->query('kelas_id') : null;
    if (! $tahunAjaranId && $kelasIdParam) {
        // Deep link with kelas_id but no tahun_ajaran_id (e.g. a bookmarked/shared URL):
        // derive it from the kelas itself instead of falling back to the active tahun
        // ajaran, which may not be the one the kelas actually belongs to.
        $tahunAjaranId = Kelas::find($kelasIdParam)?->tahun_ajaran_id;
    }
    if (! $tahunAjaranId && ! $isYayasanAggregate) {
        $tahunAjaranId = TahunAjaran::where('status_aktif', true)->value('id');
    }

    $kelasList = $tahunAjaranId ? Kelas::with('lembaga')->where('tahun_ajaran_id', $tahunAjaranId)->orderBy('nama')->get() : collect();
    $semesterList = $tahunAjaranId ? Semester::where('tahun_ajaran_id', $tahunAjaranId)->orderByDesc('id')->get() : collect();

    $kelasId = $kelasIdParam;
    if (! $kelasId || ! $kelasList->contains('id', (int) $kelasId)) {
        $kelasId = $kelasList->first()?->id;
    }
    $semesterId = is_scalar($request->query('semester_id')) ? $request->query('semester_id') : null;
    if (! $semesterId || ! $semesterList->contains('id', (int) $semesterId)) {
        $semesterId = $semesterList->first()?->id;
    }

    $selectedKelas = $kelasId ? $kelasList->firstWhere('id', (int) $kelasId) : null;
    $selectedSemester = $semesterId ? $semesterList->firstWhere('id', (int) $semesterId) : null;

    $rekap = ($selectedKelas && $selectedSemester)
        ? $this->raporCalculationService->hitungRekapKelas($selectedKelas, $selectedSemester)
        : $this->rekapKosong();

    if ($request->ajax()) {
        return view('portals.lembaga.akademik.rapor._hasil', array_merge([
            'selectedKelas' => $selectedKelas,
            'selectedSemester' => $selectedSemester,
        ], $rekap, $this->scopeHeaderData($request)))->render();
    }

    return view('portals.lembaga.akademik.rapor.index', array_merge([
        'tahunAjaranList' => TahunAjaran::with('lembaga')->orderByDesc('id')->get(),
        'tahunAjaranId' => $tahunAjaranId,
        'kelasList' => $kelasList,
        'semesterList' => $semesterList,
        'selectedKelas' => $selectedKelas,
        'selectedSemester' => $selectedSemester,
    ], $rekap, $this->scopeHeaderData($request)));
}
```

**Catatan perubahan tambahan yang IKUT masuk di diff yang sama** (murni efisiensi, BUKAN perbaikan bug terpisah — supaya tidak 2x mengubah baris yang sama): `Kelas::find($kelasId)`/`Semester::find($semesterId)` diganti `$kelasList->firstWhere(...)`/`$semesterList->firstWhere(...)` — data itu SUDAH ada di memori dari query 2 baris sebelumnya, query ulang ke DB tidak perlu. Perilaku observable TIDAK BERUBAH (`$kelasList`/`$semesterList` sudah pasti berisi baris yang sama persis dengan yang `::find()` akan kembalikan, karena `$kelasId` divalidasi HARUS ada di `$kelasList` beberapa baris sebelumnya).

**Catatan tambahan KEDUA yang IKUT masuk di diff yang sama**: query `$kelasList` di atas SUDAH langsung ditulis dengan `Kelas::with('lembaga')->where(...)` (bukan `Kelas::where(...)` polos) — eager-load `lembaga` ini prasyarat Item D (badge lembaga di judul konteks hasil rekap), digabung di sini supaya baris yang sama tidak diubah 2x oleh 2 item berbeda. Item D TIDAK PERLU mengubah controller lagi — cukup baca `$selectedKelas->lembaga` yang sudah tersedia tanpa N+1.

`array_merge(..., $rekap)` diganti `array_merge(..., $rekap, $this->scopeHeaderData($request))` — dipakai Item B, digabung di sini.

### Keputusan Desain

Setelah Item A ini, kondisi berikut BERUBAH secara alami (efek berantai yang sudah dijelaskan di atas), TIDAK PERLU perubahan kode tambahan:
- Aktor yayasan mode agregat, kunjungan pertama (tanpa query string): `$tahunAjaranId` tetap `null` → `$kelasList`/`$semesterList` kosong → halaman menampilkan state "Silakan Pilih..." (lihat Item C untuk penyesuaian wording state ini).
- Aktor yayasan mode agregat, deep link dengan `kelas_id` eksplisit: TETAP berfungsi (logic derive di baris 33-38 tidak disentuh).
- Aktor lembaga-scope ATAU yayasan-yang-sudah-switch: TIDAK ADA PERUBAHAN PERILAKU SAMA SEKALI (guard `! $isYayasanAggregate` di baris kondisi memastikan default-active-TA tetap jalan seperti sebelumnya).

---

## Item B — 🔴 Tinggi: Tidak Ada Badge Scope Sama Sekali (Kesenjangan dari Standar yang Sudah Ada)

### Masalah

`RaporController` TIDAK `use ResolveLembagaScopeTrait` sama sekali, dan `index.blade.php` (header, baris 11-17) TIDAK PUNYA badge scope (isYayasan/activeLembaga) — beda dari ~15 menu lain yang SUDAH konsisten pakai pola ini sepanjang rangkaian audit sesi ini (RPP, TP, Tahun Ajaran, Karyawan, Guru, dst). Halaman ini kelihatannya terlewat dari gelombang standarisasi itu.

### Perbaikan

`app/Http/Controllers/Admin/RaporController.php` — import `Lembaga` dan trait SUDAH ditambahkan di Item A (digabung supaya tidak 2x mengubah blok `use` yang sama). Di sini tinggal tambahkan method `scopeHeaderData()` (pola PERSIS sama seperti menu lain), sebelum method `index()`:

```php
private function scopeHeaderData(Request $request): array
{
    $isYayasan = $request->user()->widestScopeLevel() === 'yayasan';
    $lembagaId = $this->resolveActiveLembagaId($request->user());

    return [
        'isYayasan' => $isYayasan,
        'activeLembaga' => ($isYayasan && $lembagaId) ? Lembaga::withoutGlobalScopes()->find($lembagaId) : null,
    ];
}
```

`resources/views/portals/lembaga/akademik/rapor/index.blade.php` — ganti header (baris 11-17):

```blade
        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Rekapitulasi Nilai Rapor</h1>
            <p class="text-sm text-gray-500">
                Akademik <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Rekap Rapor</b>
            </p>
        </div>
```

menjadi:

```blade
        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="font-display text-lg font-bold text-gray-900">Rekapitulasi Nilai Rapor</h1>
                @if ($isYayasan ?? false)
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
                        <x-icon name="apartment" class="h-3.5 w-3.5" />
                        {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
                    </span>
                @endif
            </div>
            <p class="text-sm text-gray-500">
                Akademik <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Rekap Rapor</b>
            </p>
        </div>
```

---

## Item C — 🟡 Sedang: Dropdown Tahun Ajaran Baca `session()` Mentah + Wording Empty-State Perlu Disesuaikan

### Masalah

`index.blade.php` baris 36 baca `session('active_lembaga_id')` LANGSUNG di view (bukan lewat `resolveActiveLembagaId()` yang tervalidasi):

```blade
<option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}@if (Auth::user()->widestScopeLevel() === 'yayasan' && ! session('active_lembaga_id')) — {{ $tahunAjaran->lembaga->nama }}@endif</option>
```

Kalau `session('active_lembaga_id')` berisi ID lembaga BASI/tidak valid (bukan milik yayasan aktor — skenario yang SUDAH terbukti nyata terjadi dan diperbaiki di RPP/TP sebelumnya), suffix nama lembaga TIDAK MUNCUL padahal seharusnya muncul (karena raw check ini menganggap "session ada isi = sudah pilih 1 lembaga", padahal isinya invalid).

**Sekalian di item ini**: setelah Item A, `$tahunAjaranId` BISA `null` untuk aktor yayasan mode agregat — perlu (1) tambahkan opsi placeholder kosong di dropdown Tahun Ajaran (SEBELUMNYA tidak ada opsi kosong sama sekali — kalau dibiarkan, browser otomatis menganggap opsi PERTAMA "terpilih" secara visual meski `$tahunAjaranId` sebenarnya `null`, membuat tampilan menyesatkan), dan (2) perbarui wording pesan "Silakan Pilih..." di `_hasil.blade.php` supaya tetap akurat baik saat HANYA Kelas/Semester yang belum dipilih MAUPUN saat Tahun Ajaran juga belum dipilih.

### Perbaikan

`index.blade.php`, dropdown Tahun Ajaran (baris 34-38) — ganti:

```blade
                        <select x-ref="tahunAjaranSelect" x-init="initTahunAjaranSelect($refs.tahunAjaranSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-bold text-gray-900 transition focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($tahunAjaranList as $tahunAjaran)
                                <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}@if (Auth::user()->widestScopeLevel() === 'yayasan' && ! session('active_lembaga_id')) — {{ $tahunAjaran->lembaga->nama }}@endif</option>
                            @endforeach
                        </select>
```

menjadi:

```blade
                        <select x-ref="tahunAjaranSelect" x-init="initTahunAjaranSelect($refs.tahunAjaranSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-bold text-gray-900 transition focus:border-brand-500 focus:ring-brand-500">
                            <option value="">— Pilih Tahun Ajaran —</option>
                            @foreach ($tahunAjaranList as $tahunAjaran)
                                <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}{{ ($isYayasan ?? false) && ! ($activeLembaga ?? null) ? ' — '.($tahunAjaran->lembaga->nama ?? '-') : '' }}</option>
                            @endforeach
                        </select>
```

`_hasil.blade.php`, blok `@else` (baris 141-150) — ganti:

```blade
    @else
        <div class="rounded-2xl border border-dashed border-gray-300 p-12 text-center text-gray-400 space-y-3 bg-white">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                <x-icon name="assessment" class="h-7 w-7" />
            </div>
            <div>
                <p class="text-base font-semibold text-gray-700">Silakan Pilih Kelas dan Semester</p>
                <p class="text-xs text-gray-400 max-w-sm mx-auto mt-0.5">Pilih parameter kelas di bagian atas untuk menampilkan rekapitulasi nilai rapor peserta didik.</p>
            </div>
        </div>
    @endif
```

menjadi:

```blade
    @else
        <div class="rounded-2xl border border-dashed border-gray-300 p-12 text-center text-gray-400 space-y-3 bg-white">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                <x-icon name="assessment" class="h-7 w-7" />
            </div>
            <div>
                <p class="text-base font-semibold text-gray-700">Silakan Pilih Tahun Ajaran, Kelas, dan Semester</p>
                <p class="text-xs text-gray-400 max-w-sm mx-auto mt-0.5">Pilih parameter di bagian atas untuk menampilkan rekapitulasi nilai rapor peserta didik.</p>
            </div>
        </div>
    @endif
```

(Wording baru TETAP AKURAT untuk kedua skenario — "belum pilih apa-apa sama sekali" MAUPUN "sudah pilih Tahun Ajaran tapi Kelas/Semester entah kenapa masih kosong" — karena kalimatnya generik menyebut ketiganya sekaligus, tidak mengklaim kondisi spesifik mana yang sedang terjadi.)

---

## Item D — 🟡 Sedang: Hasil Rekap Tidak Punya Konteks Kelas/Lembaga (Kartu Statistik & Tabel Matriks)

### Masalah

`_hasil.blade.php` (saat `$selectedKelas && $selectedSemester` true) langsung menampilkan kartu statistik dan tabel matriks TANPA satu pun judul yang menyebutkan SEDANG melihat rekap KELAS MANA. Konteks kelas/semester HANYA terlihat dari dropdown filter di atas (yang bisa saja sudah scroll keluar layar di mobile). Ditambah, setelah Item A, aktor yayasan mode agregat AKAN benar-benar melihat rekap lintas-lembaga bergantian (pindah-pindah Tahun Ajaran = pindah-pindah lembaga) — tanpa penegasan ulang "ini kelas X, lembaga Y", mudah salah kira sedang lihat kelas yang berbeda.

### Perbaikan

`_hasil.blade.php` — tambahkan judul konteks SEBELUM baris kartu statistik (baris 1-4), ganti:

```blade
<div class="space-y-4">
    @if ($selectedKelas && $selectedSemester)
        <!-- Class Stat Summary -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
```

menjadi:

```blade
<div class="space-y-4">
    @if ($selectedKelas && $selectedSemester)
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="font-display font-bold text-gray-900">{{ $selectedKelas->nama }}</span>
            <span class="text-gray-300">&bull;</span>
            <span class="text-gray-600">{{ $selectedSemester->nama }} — {{ $selectedSemester->tahunAjaran->nama }}</span>
            @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
                <span class="inline-flex items-center gap-1 rounded-full bg-purple-50 px-2.5 py-0.5 text-xs font-medium text-purple-700">
                    <x-icon name="apartment" class="h-3 w-3" />
                    {{ $selectedKelas->lembaga->nama ?? '-' }}
                </span>
            @endif
        </div>

        <!-- Class Stat Summary -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
```

**Tidak perlu perubahan controller tambahan di item ini**: `$selectedKelas->lembaga` di atas SUDAH aman dipakai tanpa N+1 — eager-load `with('lembaga')` pada `$kelasList` sudah digabung ke dalam kode Item A (lihat "Catatan tambahan KEDUA" di Item A). Item D murni perubahan view.

---

## Item E — 🟡 Sedang: PDF Cetak Tidak Menyebut Nama Lembaga

### Masalah

`resources/views/pdf/rekap-rapor.blade.php` baris 20-21 — header PDF menyebut nama kelas + semester + tahun ajaran, TAPI TIDAK PERNAH menyebut nama LEMBAGA. Untuk dokumen resmi yang bisa dicetak/diarsip/dibagikan ke pihak luar (orang tua, dinas pendidikan, dll), ketiadaan nama lembaga adalah kelengkapan dokumen yang nyata kurang, bukan cuma soal UI.

### Perbaikan

`resources/views/pdf/rekap-rapor.blade.php` — ganti (baris 20-21):

```blade
    <h1>Rekap Nilai Rapor — {{ $selectedKelas->nama }}</h1>
    <p class="subtitle">{{ $selectedSemester->nama }} — {{ $selectedSemester->tahunAjaran->nama }} &middot; Dicetak {{ now()->translatedFormat('d F Y H:i') }}</p>
```

menjadi:

```blade
    <h1>Rekap Nilai Rapor — {{ $selectedKelas->nama }}</h1>
    <p class="subtitle">{{ $selectedKelas->lembaga->nama ?? '-' }} &middot; {{ $selectedSemester->nama }} — {{ $selectedSemester->tahunAjaran->nama }} &middot; Dicetak {{ now()->translatedFormat('d F Y H:i') }}</p>
```

**Catatan**: `RaporController::cetak()` memanggil `Kelas::find($data['kelas_id'])` (bukan lewat `$kelasList`) — TIDAK perlu eager-load tambahan di situ karena `Kelas::find()` mengembalikan 1 model utuh (bukan collection besar), akses `->lembaga->nama` di PDF cukup 1x lazy-load, dampaknya dapat diabaikan (beda dengan Item D yang berulang per-baris di dalam list).

---

## Item F — 🟢 Kecil: "Rata-Rata Kelas" Ambigu Secara Metodologi

### Masalah

`_hasil.blade.php` (kartu statistik, baris 17-27) menampilkan label "Rata-Rata Kelas" begitu saja. `RaporCalculationService::hitungRekapKelas()` baris 87 menghitungnya sebagai rata-rata dari **SEMUA sel nilai numerik individual** (siswa × mapel, digabung rata), BUKAN rata-rata dari nilai-rata-rata tiap siswa. Kedua metode ini menghasilkan angka BERBEDA kalau ada siswa dengan jumlah mapel ternilai yang tidak sama rata (mis. siswa pindahan yang cuma punya nilai di 2 mapel dari 8 mapel kelas) — bukan bug hitung, tapi angka yang ditampilkan tanpa penjelasan metodologi.

### Perbaikan

`_hasil.blade.php` — tambahkan `<x-tooltip>` pada label "Rata-Rata Kelas" (baris 20), ganti:

```blade
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Rata-Rata Kelas</p>
```

menjadi:

```blade
                        <div class="flex items-center gap-1">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Rata-Rata Kelas</p>
                            <x-tooltip text="Dihitung dari rata-rata SELURUH nilai numerik individual (siswa x mapel), bukan rata-rata dari nilai rata-rata tiap siswa.">
                                <x-icon name="info" class="h-3 w-3 cursor-help text-gray-400" />
                            </x-tooltip>
                        </div>
```

---

## Item G — Perbarui Catatan Lama yang Sudah Tidak Akurat

### Masalah

`.agents/logs/2026-09-07-audit-scope-yayasan-lembaga-sidebar.md` baris 89 mengklaim Rekap Rapor `✅ ✅` — TERBUKTI TIDAK AKURAT oleh Item A-C di atas (lihat "Koreksi penting" di Ringkasan).

### Perbaikan

Setelah Item A-F selesai diimplementasi DAN diverifikasi, update baris 89 file itu jadi:

```
| Rekap Rapor | ✅ | ✅ | Diperbaiki 2026-09-09 (spec `2026-09-09-rekap-rapor-audit-perbaikan.md`) — default filter tidak lagi ambigu di mode agregat, badge scope ditambahkan, konteks kelas/lembaga ditampilkan di hasil & PDF |
```

(Ditulis sebagai task TERAKHIR di plan, setelah semua item lain terverifikasi lulus test — supaya catatan yang diperbarui benar-benar mencerminkan kondisi kode yang sudah teruji, bukan janji.)

---

## Di Luar Scope

- **Backend inti (`RaporCalculationService`, agregasi numeric/predicate/narrative, guard `opsi()`/`cetak()`) TIDAK diubah** — sudah dikonfirmasi benar lewat audit, tidak ada bug hitung/N+1/kebocoran lintas-yayasan.
- **`<x-input-label>` tidak pernah pakai atribut `for=`/`id=` untuk menghubungkan label ke input (aksesibilitas screen reader)** — DITEMUKAN saat audit ini, TAPI ini pola yang dipakai IDENTIK di HAMPIR SEMUA form di seluruh aplikasi (bukan spesifik Rekap Rapor). Memperbaikinya di sini saja tidak menyelesaikan masalahnya secara sistemik, dan memperbaikinya di seluruh app jauh di luar scope 1 halaman. TIDAK dikerjakan di spec ini — dicatat di sini secara eksplisit sebagai permintaan user ("catat saja semua dalam spec"), keputusan tindak lanjut (mis. jadi `.ai/rules` terpisah) diserahkan ke user.
- **Adopsi `<x-select>` untuk 3 dropdown filter (Tahun Ajaran/Kelas/Semester)** — SUDAH tercatat sebagai backlog lintas-app di `.ai/rules/components.md` (temuan sesi TP sebelumnya, 63+ file terpengaruh termasuk Rekap Rapor). TIDAK perlu item terpisah di spec ini, cukup jadi 1 data poin tambahan yang mengonfirmasi cakupan backlog itu.
- **Tidak ada perubahan skema database, tidak ada migrasi baru.**
- **Tidak pakai worktree, tidak pindah branch.**

## Tabel Panduan Test

| Item | Test yang dibutuhkan |
|---|---|
| A | Aktor yayasan mode agregat, kunjungan pertama TANPA query string: `$tahunAjaranId`/`$kelasId`/`$semesterId` internal semuanya `null`, halaman menampilkan state "Silakan Pilih...", TIDAK ADA kelas/rekap yang otomatis tampil. Aktor yayasan mode agregat dengan deep-link `?kelas_id=X`: tetap resolve dengan benar (regresi). Aktor lembaga-scope ATAU yayasan-yang-sudah-switch: TIDAK ADA perubahan — Tahun Ajaran aktif, kelas pertama, semester pertama tetap otomatis terpilih seperti sebelumnya (regresi). |
| B | Badge scope (isYayasan/activeLembaga) tampil konsisten dengan pola menu lain — brand color saat 1 lembaga aktif, ungu "Semua Lembaga" saat agregat, TIDAK tampil untuk aktor lembaga-scope. |
| C | Dropdown Tahun Ajaran: aktor yayasan mode agregat melihat suffix `— Nama Lembaga` di tiap opsi (regresi terhadap raw session check yang lama — pastikan test-nya set `session(['active_lembaga_id' => ...])` untuk skenario "sudah switch" DAN skenario session basi/tidak divalidasi ke yayasan lain). Wording empty-state baru muncul benar di kedua kondisi (belum pilih apa-apa vs sudah pilih TA tapi belum kelas/semester). |
| D | Aktor yayasan mode agregat yang sudah memilih kelas: badge lembaga muncul di judul konteks hasil rekap. Aktor lembaga-scope/yayasan-yang-sudah-switch: badge TIDAK muncul (regresi), tapi nama kelas & semester tetap tampil sebagai judul konteks (item baru, bukan cuma regresi). |
| E | PDF cetak menampilkan nama lembaga di subtitle header. |
| F | Tooltip "Rata-Rata Kelas" menampilkan teks penjelasan metodologi saat hover/fokus pada ikon info. |
| G | Tidak perlu test otomatis — verifikasi manual (baca ulang file log) sebagai langkah terakhir plan. |
