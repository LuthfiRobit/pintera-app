# Audit & Perbaikan Scope Lembaga di Menu TP (Komponen Penilaian)

**Tanggal**: 2026-09-09
**Branch**: `akademik-v2`
**Status**: Draft — menunggu review user sebelum plan+kickoff

## Ringkasan

Audit ulang menyeluruh menu TP (routes, seluruh method controller, FormRequest, Action, DTO, semua view, semua JS) atas permintaan eksplisit user ("audit semua hal di halaman tp, audit ulang"), dipicu oleh pertanyaan user: *"tambah dan edit belum terkunci switch lembaga?"*.

Jawabannya: **benar, ada gap nyata** — tapi HANYA di sisi **Tambah (Create)**, bukan Edit. Root cause tunggal yang mendasari beberapa gejala berbeda: query `MataPelajaran::orderBy('nama')->get()` dan default `TahunAjaran::where('status_aktif', true)->value('id')` di beberapa method controller **tidak pernah mempertimbangkan** kondisi aktor yayasan yang sedang mode "Semua Lembaga" (belum switch ke 1 lembaga spesifik via pengalih topbar) — akibatnya data dari BANYAK lembaga tercampur flat tanpa label, atau default yang dipilih sistem jadi ambigu/menyesatkan.

**Keputusan desain kunci** (ditetapkan di spec ini, method sudah punya preseden identik di codebase — lihat Item A): alih-alih "melabeli" data yang tercampur (opsi lebih rumit, lebih banyak kode, tetap berisiko), **Tambah TP di-guard supaya HANYA bisa diakses saat aktor yayasan sudah switch ke 1 lembaga spesifik** — persis pola yang sudah diterapkan untuk menu **Scan QR Kehadiran SDM** (`AttendanceQrScanController`, `abort_if($lembagaId === null, 422, 'Pilih lembaga aktif melalui pengalih lembaga...')` + tombol/menu disembunyikan saat aggregate). Begitu guard ini ada, SELURUH query di dalam `create()`/`store()` otomatis benar tanpa perlu difilter manual satu-satu — karena `TenantScope` (global scope bawaan `BelongsToTenant`) sudah otomatis membatasi ke 1 lembaga begitu `session('active_lembaga_id')` tervalidasi terisi.

Edit TIDAK butuh guard yang sama — TP yang diedit sudah pasti terikat ke 1 lembaga tertentu (lewat route-model-binding + `TenantScope`), dan sejak perbaikan Task 1 sebelumnya, Subjek/Semester (sumber `lembaga_id`) memang sudah tidak bisa diubah lagi lewat form Edit sama sekali. Edit hanya perlu 1 perbaikan kecil (Item F, tampilkan nama lembaga sebagai konteks) plus pembersihan data mati peninggalan Task 1 (Item E).

---

## Item A — 🔴 Tinggi: Guard "Tambah TP" — Wajib Sudah Switch ke 1 Lembaga (Aktor Yayasan)

### Masalah

**Dibuktikan lewat pembacaan kode langsung** (bukan cuma dugaan): tombol **"+ Tambah TP Baru"**/"+ Tambah TP Pertama" (`_daftar.blade.php` baris 88-90, 145-147) tampil **tanpa syarat apa pun** — termasuk saat aktor yayasan sedang mode "Semua Lembaga" (badge ungu, belum pilih 1 lembaga lewat pengalih topbar).

Begitu diklik, `KomponenPenilaianController::create()` (baris 100-117) memanggil:
- `MataPelajaran::orderBy('nama')->get()` — **TIDAK ADA** `where('lembaga_id', ...)` eksplisit apa pun. `MataPelajaran` punya `BelongsToTenant` (`TenantScope`), tapi `TenantScope` untuk aktor yayasan **tanpa** `active_lembaga_id` tervalidasi di session sengaja fallback ke **"semua lembaga di bawah yayasan yang sama"** (lihat `app/Models/Scopes/TenantScope.php` baris 60-88, komentar eksplisit "No specific lembaga picked ... scope down to every lembaga under the actor's OWN yayasan"). Efeknya: dropdown "Mata Pelajaran" di form Tambah TP mencampur SEMUA mata pelajaran dari SEMUA lembaga yayasan itu jadi 1 list flat, TANPA label lembaga pembeda apa pun.
- Default `$tahunAjaranId` (baris 104-107): `old('tahun_ajaran_id', $request->query('tahun_ajaran_id'))`, fallback ke `TahunAjaran::where('status_aktif', true)->value('id')` — mengambil baris PERTAMA yang cocok di DB. Kalau yayasan itu punya beberapa lembaga yang masing-masing punya tahun ajaran `status_aktif=true` sendiri-sendiri (lazim — tiap lembaga kelola tahun ajarannya sendiri), ini **memilih SATU lembaga secara acak** (tergantung urutan baris DB) sebagai default, bukan benar-benar representasi "semua".

**Konsekuensi nyata**: aktor yayasan di mode agregat bisa, tanpa sadar, memilih Mata Pelajaran dari **lembaga X** dipasangkan dengan Tahun Ajaran/Semester yang defaultnya kebetulan lembaga **Y** — submit akan GAGAL dengan cara membingungkan (lihat Item C), atau kalaupun kombinasinya kebetulan konsisten (mapel dan semester sama-sama lembaga X), user tidak pernah benar-benar diberi tahu/diminta menegaskan itu untuk lembaga mana.

**Bukti pola yang sudah pernah diakui sebagai bug & diperbaiki di menu lain** (dari `PETA_PENGEMBANGAN.md`): *"menu & endpoint Scan QR disembunyikan + di-guard 422 saat aktor yayasan berada di mode Semua Lembaga (belum memilih lembaga aktif via switcher topbar)"* — kode aktualnya (`AttendanceQrScanController::index()` baris 24-26, `store()` baris 43-47) SUDAH pakai persis pola guard yang diusulkan di sini.

### Keputusan Desain

**Tambah TP HANYA bisa diakses (baik buka halaman `create()` maupun submit `store()`) saat**:
- Aktor BUKAN yayasan-scope (`widestScopeLevel() !== 'yayasan'`, otomatis selalu terikat 1 lembaga) — TIDAK terpengaruh sama sekali oleh guard ini, ATAU
- Aktor yayasan-scope YANG SUDAH switch ke 1 lembaga spesifik (`resolveActiveLembagaId()` — varian TERVALIDASI dari `ResolveLembagaScopeTrait`, BUKAN raw `session('active_lembaga_id')`, supaya konsisten dengan pola `scopeHeaderData()` yang sudah dipakai di controller ini — mengembalikan `null` kalau lembaga di session ternyata bukan milik yayasan aktor, bukan `abort()`).

Kalau tidak memenuhi syarat: tombol Tambah TP disembunyikan dari `_daftar.blade.php`, DAN `create()`/`store()` di-guard `abort_if(..., 422, 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah TP.')` — pesan senada (BUKAN identik kata-per-kata, disesuaikan konteks TP) dengan pesan Scan QR, defense-in-depth kalau ada yang akses URL langsung.

**Kenapa TIDAK memilih opsi "biarkan tetap bisa Tambah TP di mode agregat, tapi label semua dropdown per-lembaga"**: opsi itu jauh lebih banyak kode (perlu eager-load + `<optgroup>` di 3 dropdown berbeda: Tahun Ajaran, Semester, Mata Pelajaran, ditambah re-fetch dinamis tiap ganti Tahun Ajaran lewat JS), TETAP menyisakan risiko salah-pilih (cuma mengurangi, bukan menghilangkan), dan bertentangan dengan preseden yang SUDAH ditetapkan & diterima di menu lain (Scan QR) untuk kelas masalah yang PERSIS SAMA ("aksi yang MEMBUAT data baru butuh 1 lembaga definitif, aksi yang cuma MELIHAT/FILTER data boleh tetap agregat"). Index (melihat & memfilter, lihat Item C/D di bawah) TETAP boleh diakses di mode agregat — hanya Tambah (yang MEMBUAT data baru) yang di-guard.

### Perbaikan

**1. `app/Http/Controllers/Admin/KomponenPenilaianController.php`** — tambah guard di `create()` dan `store()`. Di `create()`, sisipkan blok guard TEPAT SETELAH baris `$this->authorize('komponen-penilaian.kelola');` DAN SEBELUM baris `$tahunAjaranId = old('tahun_ajaran_id', ...)` (baris lain di method ini TIDAK berubah sama sekali):

```php
public function create(Request $request): View
{
    $this->authorize('komponen-penilaian.kelola');

    if ($request->user()->widestScopeLevel() === 'yayasan') {
        abort_if($this->resolveActiveLembagaId($request->user()) === null, 422, 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah TP.');
    }

    $tahunAjaranId = old('tahun_ajaran_id', $request->query('tahun_ajaran_id'));
    // ... (baris 105-116 setelah ini TIDAK berubah)
}
```

Di `store()`, sisipkan blok guard yang SAMA (pesan identik) sebagai baris PALING PERTAMA di dalam method, SEBELUM baris `$data = $request->validated();` (seluruh baris method setelah itu TIDAK berubah sama sekali):

```php
public function store(StoreKomponenPenilaianRequest $request): RedirectResponse|JsonResponse
{
    if ($request->user()->widestScopeLevel() === 'yayasan') {
        abort_if($this->resolveActiveLembagaId($request->user()) === null, 422, 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah TP.');
    }

    $data = $request->validated();
    // ... (baris 121-148 setelah ini TIDAK berubah)
}
```

**PENTING — kenapa guard di atas AMAN sekalipun `create()`/`store()` TIDAK diubah lebih jauh**: setelah guard lolos, `session('active_lembaga_id')` PASTI tervalidasi terisi (dicek lewat `resolveActiveLembagaId()`), sehingga `TenantScope` (dipakai otomatis oleh `MataPelajaran::orderBy('nama')->get()`, `TahunAjaran::orderByDesc('id')->get()`, `Semester::where(...)->get()` — SEMUANYA pakai `BelongsToTenant`) otomatis mengembalikan HANYA baris milik 1 lembaga itu. **TIDAK PERLU** menambah `where('lembaga_id', ...)` manual di query mana pun — behavior yang benar didapat gratis dari scope global yang sudah ada. Untuk aktor lembaga-scope (`widestScopeLevel() !== 'yayasan'`), tidak ada perubahan sama sekali (guard di-skip, `TenantScope` sudah otomatis 1-lembaga sejak awal seperti sebelumnya).

**Kenapa `create()` TIDAK butuh perbaikan default-Tahun-Ajaran serupa Item C**: baris `$tahunAjaranId = TahunAjaran::where('status_aktif', true)->value('id');` di `create()` TETAP APA ADANYA (TIDAK diubah) — karena SETELAH guard di atas lolos, kondisinya SELALU salah satu dari 2 kemungkinan yang SAMA-SAMA sudah otomatis 1-lembaga lewat `TenantScope`: aktor lembaga-scope (selalu begitu), atau aktor yayasan-scope yang PASTI sudah punya `active_lembaga_id` tervalidasi (guard barusan memastikannya). Ambiguitas "banyak lembaga sama-sama `status_aktif=true`" yang jadi masalah di Item C (`index()`, yang SENGAJA masih mengizinkan mode agregat) TIDAK PERNAH bisa terjadi lagi di `create()` sejak guard Item A ada. Ini kenapa Item A dan Item C punya fix yang beda bentuk untuk gejala yang mirip — bukan inkonsistensi, tapi konsekuensi dari `create()` di-guard sedangkan `index()` sengaja tetap dibiarkan agregat.

**2. `resources/views/portals/lembaga/akademik/komponen-penilaian/_daftar.blade.php`** — sembunyikan tombol Tambah TP saat aktor yayasan belum switch lembaga. View partial ini di-render dari `index()` yang SUDAH mengirim `isYayasan`/`activeLembaga` (lewat `scopeHeaderData()`) untuk cabang halaman-penuh — TAPI belum untuk cabang ajax (lihat Item D, keduanya WAJIB dikirim `scopeHeaderData()` mulai spec ini). Ganti KEDUA lokasi tombol:

```blade
<x-link-button href="{{ route('admin.komponen-penilaian.create') }}">
    <span class="text-base leading-none mr-1.5">+</span> Tambah TP Baru
</x-link-button>
```

menjadi:

```blade
@if (! ($isYayasan ?? false) || ($activeLembaga ?? null))
    <x-link-button href="{{ route('admin.komponen-penilaian.create') }}">
        <span class="text-base leading-none mr-1.5">+</span> Tambah TP Baru
    </x-link-button>
@endif
```

Dan (di blok `@empty`):

```blade
<x-link-button href="{{ route('admin.komponen-penilaian.create') }}" class="inline-flex justify-center">
    <span class="text-base leading-none mr-1.5">+</span> Tambah TP Pertama
</x-link-button>
```

menjadi:

```blade
@if (! ($isYayasan ?? false) || ($activeLembaga ?? null))
    <x-link-button href="{{ route('admin.komponen-penilaian.create') }}" class="inline-flex justify-center">
        <span class="text-base leading-none mr-1.5">+</span> Tambah TP Pertama
    </x-link-button>
@else
    <p class="text-xs text-gray-400 max-w-sm mx-auto mt-2">Pilih 1 lembaga lewat pengalih di topbar untuk mulai menambah TP.</p>
@endif
```

(Blok `@empty` SEBELUMNYA tidak punya penjelasan apa pun kalau tombol disembunyikan — versi baru menambahkan 1 baris teks penjelas supaya user tidak bingung kenapa tombol hilang, konsisten dengan semangat "alur bisnisnya harus jelas" dari Item A audit TP sebelumnya.)

---

## Item B — 🟡 Sedang: `store()` Gagal dengan 404 Kosong, Bukan Pesan Validasi (Defense-in-Depth)

### Masalah

`store()` (baris 123-131) punya cross-check keamanan yang SUDAH BENAR secara logika (mencegah `mata_pelajaran` dan `semester_id` dari lembaga berbeda tersimpan bersamaan) — TAPI kegagalannya `abort_if($subjek === null || $semester === null, 404)` dan `abort_if($subjek->lembaga_id !== $semester->lembaga_id, 404)` memicu **halaman 404 kosong bawaan Laravel**, BUKAN `back()->withInput()->withErrors()` seperti pola `ValidationException` yang SUDAH dipakai beberapa baris di bawahnya (baris 135-141) di method yang SAMA. Efeknya: deskripsi/KKTP/kode yang sudah diketik user **hilang seketika** tanpa penjelasan.

**Setelah Item A**: skenario ini jadi jauh lebih jarang terjadi (guard sudah mencegah SEBAGIAN BESAR sumbernya — pencampuran lembaga di dropdown Mata Pelajaran mode agregat). TAPI TIDAK sepenuhnya mustahil — payload HTTP mentah (di luar UI form manapun) tetap bisa mengirim `subjek_id`/`semester_id` yang sengaja tidak cocok. Perbaikan ini murni **defense-in-depth**, bukan lagi perbaikan jalur UI utama.

### Perbaikan

`app/Http/Controllers/Admin/KomponenPenilaianController.php`, method `store()` — ganti:

```php
$subjek = match ($data['subjek_type']) {
    'mata_pelajaran' => MataPelajaran::withoutGlobalScopes()->find($data['subjek_id']),
    'elemen_cp' => ElemenCp::find($data['subjek_id']),
};
$semester = Semester::find($data['semester_id']);
abort_if($subjek === null || $semester === null, 404);
if ($data['subjek_type'] === 'mata_pelajaran') {
    abort_if($subjek->lembaga_id !== $semester->lembaga_id, 404);
}
```

menjadi:

```php
$subjek = match ($data['subjek_type']) {
    'mata_pelajaran' => MataPelajaran::withoutGlobalScopes()->find($data['subjek_id']),
    'elemen_cp' => ElemenCp::find($data['subjek_id']),
};
$semester = Semester::find($data['semester_id']);
if ($subjek === null || $semester === null) {
    return back()->withInput()->withErrors(['subjek_id' => 'Subjek Penilaian atau Semester yang dipilih tidak valid.']);
}
if ($data['subjek_type'] === 'mata_pelajaran' && $subjek->lembaga_id !== $semester->lembaga_id) {
    return back()->withInput()->withErrors(['subjek_id' => 'Mata Pelajaran dan Semester yang dipilih berasal dari lembaga yang berbeda.']);
}
```

**Catatan**: cabang `ajax()`/`wantsJson()` TIDAK disentuh oleh perubahan ini (`store()` saat ini HANYA dipanggil lewat submit form biasa/full-page, bukan AJAX — dicek lewat `create.blade.php`, form-nya method POST biasa tanpa `fetch`/AJAX apa pun). Kalau di kemudian hari ada jalur AJAX ke `store()`, WAJIB ditambahkan percabangan `if ($request->ajax() || $request->wantsJson())` yang sama seperti di blok `catch (ValidationException $e)` beberapa baris di bawahnya.

---

## Item C — 🟡 Sedang: Default Tahun Ajaran di `index()` Ambigu Saat Mode Agregat (Kontradiksi dengan Badge "Semua Lembaga")

### Masalah

`index()` (baris 52-55): kalau TIDAK ADA `tahun_ajaran_id` di query string sama sekali (kunjungan pertama ke halaman), default `$tahunAjaranId` diambil dari `TahunAjaran::where('status_aktif', true)->value('id')` — baris PERTAMA yang cocok di DB. Untuk aktor yayasan di mode agregat (badge "Semua Lembaga"), ini memilih tahun ajaran SATU lembaga secara acak (tergantung urutan baris DB, bisa lembaga mana saja) — **bukan** benar-benar "semua tahun ajaran semua lembaga" seperti yang diklaim badge di sampingnya.

**Efek nyata**: aktor yayasan buka halaman TP pertama kali, badge bilang "Semua Lembaga", tapi daftar TP yang muncul sebenarnya SUDAH terfilter diam-diam ke 1 lembaga (tahun ajaran default itu). Kalau lembaga lain di yayasan yang sama punya TP dengan tahun ajaran BERBEDA, TP itu TIDAK akan pernah terlihat kecuali user secara eksplisit ubah filter Tahun Ajaran — padahal tidak ada indikasi apa pun bahwa ini sedang terjadi.

**Pola yang sama sudah pernah dicatat berulang di codebase ini** ("year-ambiguity filter bug", tercatat berulang di beberapa menu lain termasuk Kenaikan Kelas) — ini kemunculan yang sama di menu TP, belum pernah diperbaiki di sini.

### Perbaikan

`app/Http/Controllers/Admin/KomponenPenilaianController.php`, method `index()` — HANYA berlaku default-picking ini untuk aktor yang TIDAK di mode agregat (lembaga-scope, ATAU yayasan-scope yang SUDAH switch ke 1 lembaga — untuk kedua kasus itu, "tahun ajaran aktif" memang tidak ambigu, cuma ada 1 kandidat). Ganti:

```php
$tahunAjaranId = $request->query('tahun_ajaran_id');
if ($tahunAjaranId === null && ! $request->query->has('tahun_ajaran_id')) {
    $tahunAjaranId = TahunAjaran::where('status_aktif', true)->value('id');
}
```

menjadi:

```php
$isYayasanAggregate = $request->user()->widestScopeLevel() === 'yayasan' && $this->resolveActiveLembagaId($request->user()) === null;

$tahunAjaranId = $request->query('tahun_ajaran_id');
if ($tahunAjaranId === null && ! $request->query->has('tahun_ajaran_id') && ! $isYayasanAggregate) {
    $tahunAjaranId = TahunAjaran::where('status_aktif', true)->value('id');
}
```

Perilaku baru: aktor yayasan mode agregat, kunjungan pertama (tanpa query string), `$tahunAjaranId` tetap `null` → filter "Semua Tahun Ajaran" benar-benar aktif, badge dan data yang ditampilkan jadi KONSISTEN. Aktor lembaga-scope atau yayasan-yang-sudah-switch TIDAK terpengaruh sama sekali (perilaku default-ke-tahun-aktif TETAP SAMA seperti sebelumnya).

---

## Item D — 🟡 Sedang: Baris Daftar & Kartu Kalkulator Bobot Tidak Berlabel Lembaga di Mode Agregat

### Masalah

`_daftar.blade.php` — baik kartu "Live Calculator Bobot" (baris 44-81) maupun tiap baris TP di daftar (baris 95-149) — TIDAK PERNAH menampilkan nama lembaga, dalam kondisi APA PUN. Untuk aktor yayasan mode agregat (yang setelah Item C, SEKARANG BENAR-BENAR bisa melihat TP dari berbagai lembaga sekaligus di 1 layar), 2 lembaga berbeda yang kebetulan punya mata pelajaran bernama sama (mis. "Matematika") akan tampil sebagai 2 kartu/baris yang TERLIHAT IDENTIK — tidak ada cara membedakan dari tampilan saja.

Pola ini SUDAH diterapkan konsisten di menu lain sepanjang rangkaian audit sesi ini (Tahun Ajaran: label lembaga per kartu; TP sendiri: sudah dapat badge scope di HEADER lewat Item B di spec `2026-09-09-tp-komponen-penilaian-perbaikan.md` sebelumnya — SPEC LAMA, bukan Item B spec INI yang soal `store()` 404 — tapi belum sampai ke level BARIS/KARTU individual).

**Prasyarat**: `_daftar.blade.php` di-render dari `index()` di 2 tempat — cabang ajax (baris 69-71) dan cabang halaman-penuh (baris 73-83). SAAT INI `scopeHeaderData()` HANYA dikirim ke cabang halaman-penuh (ditetapkan sengaja di spec `2026-09-09-tp-komponen-penilaian-perbaikan.md` Item B sebelumnya, KARENA saat itu belum ada kebutuhan di partial). **Kebutuhan itu SEKARANG ADA** (Item D ini + Item A spec INI butuh `isYayasan`/`activeLembaga` di partial) — jadi keputusan lama itu perlu DIBALIK, bukan salah waktu itu, situasinya yang berubah.

### Perbaikan

`app/Http/Controllers/Admin/KomponenPenilaianController.php`, method `index()` — kirim `scopeHeaderData()` ke KEDUA cabang. Ganti:

```php
if ($request->ajax()) {
    return view('portals.lembaga.akademik.komponen-penilaian._daftar', ['komponenList' => $komponenList])->render();
}
```

menjadi:

```php
if ($request->ajax()) {
    return view('portals.lembaga.akademik.komponen-penilaian._daftar', [
        'komponenList' => $komponenList,
        ...$this->scopeHeaderData($request),
    ])->render();
}
```

`_daftar.blade.php` — tambahkan label lembaga, HANYA saat `$isYayasan && !$activeLembaga` (mode agregat). Di kartu Live Calculator (baris 57-66), ganti:

```blade
<div class="truncate">
    <h4 class="font-bold text-gray-900 text-sm truncate">{{ $first->subjek->nama }}</h4>
    <p class="text-[11px] text-gray-500">{{ $first->semester->nama }} ({{ $first->semester->tahunAjaran->nama }})</p>
</div>
```

menjadi:

```blade
<div class="truncate">
    <h4 class="font-bold text-gray-900 text-sm truncate">{{ $first->subjek->nama }}</h4>
    <p class="text-[11px] text-gray-500">
        {{ $first->semester->nama }} ({{ $first->semester->tahunAjaran->nama }})
        @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
            &bull; {{ $first->lembaga->nama ?? '-' }}
        @endif
    </p>
</div>
```

Di baris daftar TP (baris 109-110), ganti:

```blade
<div class="flex items-center gap-3">
    <x-badge tone="slate" class="text-xs font-medium">{{ $komponen->semester->nama }} — {{ $komponen->semester->tahunAjaran->nama }}</x-badge>
```

menjadi:

```blade
<div class="flex items-center gap-3">
    @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
        <x-badge tone="slate" class="text-xs font-medium">{{ $komponen->lembaga->nama ?? '-' }}</x-badge>
    @endif
    <x-badge tone="slate" class="text-xs font-medium">{{ $komponen->semester->nama }} — {{ $komponen->semester->tahunAjaran->nama }}</x-badge>
```

**Perlu eager-load `lembaga`** supaya tidak N+1: `index()`, query `$komponenList` (baris 60-67) — tambahkan `lembaga` ke `with([...])`. Ganti:

```php
$komponenList = KomponenPenilaian::whereNotNull('subjek_id')
    ->with(['subjek', 'semester.tahunAjaran'])
```

menjadi:

```php
$komponenList = KomponenPenilaian::whereNotNull('subjek_id')
    ->with(['subjek', 'semester.tahunAjaran', 'lembaga'])
```

---

## Item E — 🟡 Sedang: `edit()` Query 3 Dataset yang Sudah Mati Total (Utang dari Task 1)

### Masalah

**Dikonfirmasi lewat pencarian langsung** (`grep` nama variabel di `edit.blade.php` — 0 hasil untuk keempatnya): `KomponenPenilaianController::edit()` (baris 151-170) mengirim `mataPelajaranList`, `elemenCpList`, `semesterList` (dengan eager-load `tahunAjaran`), dan `bentukPendidikan` ke view — TAPI **TIDAK SATU PUN** dipakai di `edit.blade.php`. Sejak perbaikan Task 1 sebelumnya (Subjek/Semester dikunci permanen, tidak lagi jadi `<select>` di form Edit, cuma `<p>` read-only), 3+1 dataset ini jadi query yang benar-benar terbuang setiap kali halaman Edit TP dibuka — Task 1 tidak menyentuh method `edit()` sama sekali (di luar daftar file yang diubah saat itu), jadi kelewat.

### Perbaikan

`app/Http/Controllers/Admin/KomponenPenilaianController.php`, method `edit()` — ganti:

```php
public function edit(KomponenPenilaian $komponenPenilaian): View
{
    $this->authorize('komponen-penilaian.kelola');

    $subjek = $komponenPenilaian->subjek;
    if (! $subjek) {
        abort(404);
    }

    $dipakai = $komponenPenilaian->asesmen()->exists() || $komponenPenilaian->nilaiSiswa()->exists();

    return view('portals.lembaga.akademik.komponen-penilaian.edit', [
        'komponenPenilaian' => $komponenPenilaian->load(['subjek', 'semester.tahunAjaran']),
        'dipakai' => $dipakai,
        'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
        'elemenCpList' => ElemenCp::orderBy('no_urut')->get(),
        'semesterList' => Semester::with('tahunAjaran')->orderByDesc('id')->get(),
        'bentukPendidikan' => auth()->user()->lembaga?->bentuk_pendidikan,
    ]);
}
```

menjadi:

```php
public function edit(Request $request, KomponenPenilaian $komponenPenilaian): View
{
    $this->authorize('komponen-penilaian.kelola');

    $subjek = $komponenPenilaian->subjek;
    if (! $subjek) {
        abort(404);
    }

    $dipakai = $komponenPenilaian->asesmen()->exists() || $komponenPenilaian->nilaiSiswa()->exists();

    return view('portals.lembaga.akademik.komponen-penilaian.edit', [
        'komponenPenilaian' => $komponenPenilaian->load(['subjek', 'semester.tahunAjaran', 'lembaga']),
        'dipakai' => $dipakai,
        ...$this->scopeHeaderData($request),
    ]);
}
```

(Tambahan `'lembaga'` di `load()` dan parameter `Request $request` + `scopeHeaderData()` dipakai oleh Item F, digabung di sini supaya tidak 2x mengubah signature method yang sama.)

Route `admin.komponen-penilaian.edit` (`routes/admin/penilaian-rapor.php` baris 12) memanggil `[KomponenPenilaianController::class, 'edit']` tanpa parameter route eksplisit selain model binding — Laravel route-model-binding otomatis tetap inject `Request` sebagai parameter tambahan tanpa perlu ubah route, konsisten dengan method lain di controller yang sama (`create(Request $request)`, `store(StoreKomponenPenilaianRequest $request)`).

---

## Item F — 🟢 Kecil: Form Edit Tidak Menampilkan Nama Lembaga TP

### Masalah

`edit.blade.php` menampilkan Subjek Penilaian dan "Semester — Tahun Ajaran" (baris 42-53) sebagai konteks read-only, TAPI tidak pernah menyebut lembaga. Untuk aktor yayasan mode agregat yang membuka Edit dari daftar TP campuran (Item D), tidak ada penegasan ulang "TP ini milik lembaga mana" di halaman Edit itu sendiri. **Bukan risiko keamanan** (field yang bisa diedit — kode/deskripsi/bobot/kktp/assessment_type — tidak menyentuh `lembaga_id` sama sekali sejak Task 1), murni kejelasan konteks.

### Perbaikan

`resources/views/portals/lembaga/akademik/komponen-penilaian/edit.blade.php` — tampilkan badge lembaga di header, HANYA saat mode agregat (konsisten dengan konvensi yang sudah dipakai di `index.blade.php`). Ganti (baris 11-19, blok LENGKAP termasuk breadcrumb):

```blade
{{-- Header & Breadcrumb --}}
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-display text-lg font-bold text-gray-900">Edit Komponen Penilaian (TP)</h1>
    <p class="text-sm text-gray-500">
        Akademik <span class="mx-1 text-gray-300">&rsaquo;</span>
        <a href="{{ route('admin.komponen-penilaian.index') }}" class="font-semibold text-gray-700 hover:text-brand-600 transition-colors">Komponen Penilaian</a>
        <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Edit</b>
    </p>
</div>
```

menjadi (HANYA `<h1>` yang dibungkus `<div>` baru berisi badge — breadcrumb `<p>` dan wrapper terluar TIDAK berubah sama sekali):

```blade
{{-- Header & Breadcrumb --}}
<div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap items-center gap-2.5">
        <h1 class="font-display text-lg font-bold text-gray-900">Edit Komponen Penilaian (TP)</h1>
        @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
            <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
                <x-icon name="apartment" class="h-3.5 w-3.5" />
                {{ $komponenPenilaian->lembaga->nama ?? '-' }}
            </span>
        @endif
    </div>
    <p class="text-sm text-gray-500">
        Akademik <span class="mx-1 text-gray-300">&rsaquo;</span>
        <a href="{{ route('admin.komponen-penilaian.index') }}" class="font-semibold text-gray-700 hover:text-brand-600 transition-colors">Komponen Penilaian</a>
        <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Edit</b>
    </p>
</div>
```

(Badge di sini SELALU pakai warna brand — BEDA dari `index.blade.php` yang punya 2 warna beda untuk "1 lembaga aktif" vs "Semua Lembaga" — karena di halaman Edit, konteksnya SELALU 1 TP = SELALU 1 lembaga definitif, tidak pernah ada makna "agregat" di sini. Badge ini hanya perlu tampil kalau si AKTOR sedang mode agregat, tapi badge-nya sendiri selalu merujuk 1 lembaga spesifik milik TP tersebut. Pola persis sama seperti yang sudah diterapkan di `index.blade.php` untuk header serupa.)

---

## Item G — 🟢 Kecil: Dropdown Mata Pelajaran di Filter `index()` Tetap Flat (Tidak Perlu Diubah, Didokumentasikan Sengaja)

### Kenapa TIDAK diperbaiki di spec ini

Filter "Mata Pelajaran" di `index.blade.php` (baris 64-69) SECARA TEKNIS masih mengalami gejala yang sama seperti Item A (list tercampur lintas-lembaga tanpa label, saat mode agregat) — TAPI ini SENGAJA TIDAK diperbaiki di spec ini karena:
1. Konteksnya MELIHAT/MEMFILTER (bukan MEMBUAT data baru) — konsisten dengan prinsip Item A ("aksi lihat boleh tetap agregat").
2. Risikonya jauh lebih rendah dari Create: salah pilih filter Mata Pelajaran paling buruk menghasilkan **hasil filter kosong/salah** (bisa langsung terlihat & dikoreksi user, tidak ada apa pun yang tersimpan salah ke database).
3. Setelah Item C (default Tahun Ajaran tidak lagi otomatis 1 lembaga acak) dan Item D (label lembaga per baris/kartu), user MELIHAT dengan jelas kalau daftar hasil filter berisi banyak lembaga sekaligus — cukup sebagai sinyal, tidak butuh perbaikan tambahan di dropdown filter itu sendiri.

Dicatat di sini secara eksplisit (bukan ditemukan lalu diabaikan diam-diam) supaya keputusan ini terlihat, bukan celah yang tidak disadari.

---

## Di Luar Scope

- Pola default `TahunAjaran::where('status_aktif', true)->value('id')` yang SAMA (berpotensi ambigu di mode agregat yayasan) kemungkinan ADA juga di controller lain (RPP, Guru\KomponenPenilaianController — dicek sekilas, guru-side TIDAK terpengaruh karena guru SELALU lembaga-scope, tidak pernah yayasan-scope). RPP MEMUNGKINKAN kena pola sama — TIDAK diaudit ulang di spec ini (di luar scope permintaan user, yang spesifik ke halaman TP). Dicatat sebagai potensi backlog terpisah, BUKAN bagian dari perbaikan ini.
- Backend inti yang sudah diperbaiki di spec Item A sebelumnya (Subjek/Semester terkunci permanen, guard bobot 100%, `lockForUpdate()`) TIDAK disentuh lagi di spec ini.
- Jalur Guru (`Guru\KomponenPenilaianController`) TIDAK disentuh — guru selalu lembaga-scope tunggal (tidak pernah yayasan-scope), sehingga TIDAK PERNAH mengalami kondisi "mode agregat" yang jadi akar seluruh Item A-D di atas. Tidak relevan untuk spec ini.
- Tidak ada perubahan skema database, tidak ada migrasi baru.

## Tabel Panduan Test

| Item | Test yang dibutuhkan |
|---|---|
| A | Aktor yayasan TANPA lembaga aktif: tombol Tambah TP TIDAK muncul di `_daftar.blade.php` (baik kondisi ada data maupun kosong); GET `create()` dan POST `store()` mengembalikan 422 dengan pesan yang sesuai. Aktor yayasan YANG SUDAH switch ke 1 lembaga: tombol muncul, `create()`/`store()` berhasil seperti biasa, dan `mataPelajaranList`/`tahunAjaranList`/`semesterList` yang dikirim ke view HANYA berisi data lembaga aktif itu (regresi: pastikan tidak ada data lembaga lain yang bocor). Aktor lembaga-scope: TIDAK ADA perubahan perilaku sama sekali (regresi). |
| B | Payload `store()` dengan `subjek_id`/`semester_id` yang sengaja beda lembaga (lewat request mentah, bypass UI) menghasilkan redirect `back()` dengan `session('errors')` berisi pesan yang jelas, BUKAN response 404, dan input lain (deskripsi/kode/dst) tetap ter-preserve lewat `withInput()`. |
| C | Aktor yayasan mode agregat, buka `index()` TANPA query string sama sekali: `tahunAjaranId` yang dipakai internal adalah `null` (bukan salah satu tahun ajaran lembaga tertentu) — dibuktikan lewat daftar TP yang tampil mencakup SEMUA lembaga (kalau ada TP dengan tahun ajaran berbeda-beda di lembaga berbeda, semuanya muncul, bukan cuma 1 lembaga). Aktor lembaga-scope atau yayasan-yang-sudah-switch: default tahun ajaran aktif TETAP terpilih otomatis seperti sebelumnya (regresi). |
| D | Aktor yayasan mode agregat: baris TP dan kartu Live Calculator Bobot menampilkan nama lembaga masing-masing. Aktor lembaga-scope atau yayasan-yang-sudah-switch: label lembaga TIDAK muncul (regresi, konsisten dengan pola menu lain). Cabang ajax (`_daftar` di-render ulang lewat filter) JUGA menampilkan label yang sama (bukan cuma di full-page load pertama). |
| E | Regresi murni — halaman Edit TP tetap render sukses & seluruh field tetap bisa disimpan seperti sebelumnya (test existing Task 1 harus tetap lulus tanpa perubahan assertion). Tidak perlu test baru khusus untuk penghapusan query mati ini (tidak ada perilaku yang berubah, cuma efisiensi). |
| F | Aktor yayasan mode agregat, buka Edit sebuah TP: badge nama lembaga TP itu muncul di header. Aktor lembaga-scope atau yayasan-yang-sudah-switch: badge TIDAK muncul (regresi). |
