# Spec: Validasi Scope Backend & Kejujuran Wording — Menu Kelas

> **Branch**: `rbac-v2`
> **Tanggal**: 8 September 2026
> **Latar belakang**: Audit menu "Kelas" mengikuti pola yang sama seperti Tahun Ajaran, TAPI user mengoreksi 3 poin krusial yang awalnya salah saya labeli "murni frontend" — ternyata ada 2 gap BACKEND nyata di menu ini (beda dari Tahun Ajaran yang backend-nya benar-benar sudah sempurna). `CreateKelasAction`/`UpdateKelasAction` TETAP aman dari korupsi data lintas-lembaga (keduanya sudah 404 kalau kombinasi lembaga tidak cocok) — TAPI `KelasController::create()` (GET, tampilkan form) dan opsi dropdown di `create()`/`edit()` TIDAK divalidasi/di-scope dengan benar, menyebabkan UX yang membingungkan dan berpotensi submit yang PASTI gagal tanpa peringatan di muka.

## Ringkasan Temuan (6 item, 2 kelompok)

| # | Kelompok | Severity | Ringkasan |
|---|---|---|---|
| A.1 | Backend | 🔴 Tinggi | `KelasController::create()` (GET) TIDAK memvalidasi lembaga aktif sebelum menampilkan form — beda dari `store()` yang sudah benar |
| A.2 | Backend | 🔴 Tinggi | Dropdown Tahun Ajaran/Wali Kelas/Pola Jam di `create()` mengambil data lewat `TenantScope` AMBIEN (ikut scope aktor login), BUKAN di-scope eksplisit ke lembaga TARGET yang akan dipakai |
| A.3 | Backend | 🔴 Tinggi | Sama seperti A.2 tapi di `edit()` — dropdown seharusnya di-scope ke `$kelas->lembaga_id` (lembaga PEMILIK kelas yang diedit), bukan ikut scope aktor |
| B.1 | Frontend | 🔴 Tinggi | Index/create/edit tidak punya badge scope yayasan/lembaga |
| B.2 | Frontend | 🔴 Tinggi | Tabel daftar kelas (`_daftar.blade.php`) tidak punya kolom "Lembaga" — ambigu total di mode "Semua Lembaga" |
| B.3 | Frontend | 🟡 Sedang | Filter dropdown "Tahun Ajaran" di index tidak diberi label lembaga saat mode "Semua Lembaga" |

## Keputusan yang Diambil

1. **A.2/A.3 menghilangkan kebutuhan label lembaga di dropdown FORM create/edit** (beda dari rencana awal saya) — karena begitu dropdown di-scope eksplisit ke SATU lembaga target, tidak ada lagi opsi campur-lembaga yang perlu dibedakan di situ. Label lembaga HANYA tetap dibutuhkan di **filter index** (B.3) karena filter itu sengaja tetap merentang semua lembaga saat mode agregat (browsing lintas-lembaga adalah tujuannya, beda dari form create/edit yang selalu menyasar 1 lembaga spesifik).
2. **`resolveActiveLembagaId(User $actor)` dipakai untuk SEMUA resolusi** (guard A.1, scoping A.2, dan `scopeHeaderData()` B.1) — method ini SUDAH menangani baik aktor yayasan-scope (baca session) MAUPUN lembaga-scope (`$actor->lembaga_id` langsung) dalam 1 pemanggilan, tidak perlu percabangan manual seperti pola lama di `store()`.
3. **A.3 pakai `$kelas->lembaga_id` (properti record), BUKAN `resolveActiveLembagaId()`** — beda dari A.2. Alasan: saat edit, target lembaga sudah TETAP (milik kelas itu sendiri), tidak tergantung lembaga mana yang sedang aktif di sesi aktor. Aktor yayasan-scope dalam mode "Semua Lembaga" harus tetap bisa membuka edit kelas manapun di yayasannya dan melihat dropdown yang benar untuk lembaga KELAS ITU, bukan untuk lembaga switcher-nya (yang mungkin kosong/beda).
4. **`CreateKelasAction`/`UpdateKelasAction` TIDAK diubah** — keduanya sudah correct (guard 404 lintas-lembaga sudah ada). Spec ini murni mencegah admin SAMPAI DI TITIK bisa memilih opsi yang pasti ditolak itu, bukan menambal Action yang sudah aman.

---

## Kelompok A — Backend: Guard & Scoping Eksplisit

### A.1 — Guard `create()` Sebelum Render Form

**File**: `app/Http/Controllers/Admin/KelasController.php`

Kode saat ini:
```php
public function create(): View
{
    $this->authorize('kelas.create');

    return view('admin.kelas.create', [
        'tahunAjaranList' => TahunAjaran::orderByDesc('tanggal_mulai')->get(),
        'guruList' => Guru::with('person')->orderByNama()->get(),
        'polaJamList' => PolaJam::orderBy('nama')->get(),
        'faseList' => Fase::orderBy('urutan')->get(),
    ]);
}
```

Fix (digabung dengan A.2 di bawah — 1 method, 1 edit):
```php
public function create(Request $request): View|RedirectResponse
{
    $this->authorize('kelas.create');

    $lembagaId = $this->resolveActiveLembagaId($request->user());
    if ($lembagaId === null) {
        return redirect()->route('admin.kelas.index')
            ->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah kelas.']);
    }

    return view('admin.kelas.create', [
        'tahunAjaranList' => TahunAjaran::withoutGlobalScope(TenantScope::class)->where('lembaga_id', $lembagaId)->orderByDesc('tanggal_mulai')->get(),
        'guruList' => Guru::withoutGlobalScope(TenantScope::class)->where('lembaga_id', $lembagaId)->with('person')->orderByNama()->get(),
        'polaJamList' => PolaJam::withoutGlobalScope(TenantScope::class)->where('lembaga_id', $lembagaId)->orderBy('nama')->get(),
        'faseList' => Fase::orderBy('urutan')->get(),
    ]);
}
```

Wording error PERSIS sama dengan yang sudah dipakai `store()` (baris 106 kode existing) — konsisten, bukan pesan baru.

`Fase` SENGAJA TIDAK di-scope (dikonfirmasi lewat pembacaan model: `Fase` tidak pakai `BelongsToTenant`, murni referensi kurikulum nasional, sama untuk semua lembaga).

## A.2 — Scoping Eksplisit Dropdown di `create()`

Sudah termasuk dalam kode fix A.1 di atas (`->where('lembaga_id', $lembagaId)` pada `tahunAjaranList`/`guruList`/`polaJamList`, dengan `withoutGlobalScope(TenantScope::class)` supaya query TIDAK bergantung pada scope AMBIEN aktor — filter eksplisit berdasarkan `$lembagaId` yang sudah divalidasi non-null di A.1).

**Kenapa `withoutGlobalScope` diperlukan (bukan cuma `where()` biasa)**: `TenantScope` untuk aktor yayasan-scope dalam mode "Semua Lembaga" (session `active_lembaga_id` kosong) akan otomatis meng-AGREGAT ke semua lembaga di yayasan — TAPI titik ini TIDAK PERNAH tercapai karena guard A.1 sudah memblokir request sebelum sampai sini kalau `$lembagaId` null. Jadi secara teknis `where('lembaga_id', $lembagaId)` SENDIRIAN sudah cukup benar (TenantScope tinggal menambah filter yang SAMA persis atau lebih sempit). `withoutGlobalScope` ditambahkan di sini murni untuk KEJELASAN NIAT KODE (query ini scoped secara eksplisit berdasarkan variabel lokal yang sudah divalidasi, bukan "kebetulan benar karena TenantScope ambien juga menyaring hal yang sama") — pola yang SAMA seperti dipakai `scopeHeaderData()` di controller Karyawan/Guru/TahunAjaran sesi ini.

## A.3 — Scoping Eksplisit Dropdown di `edit()` (ke Lembaga PEMILIK Kelas)

**File**: `app/Http/Controllers/Admin/KelasController.php`

Kode saat ini:
```php
public function edit(Kelas $kelas): View
{
    $this->authorize('kelas.edit');

    return view('admin.kelas.edit', [
        'kelas' => $kelas,
        'tahunAjaranList' => TahunAjaran::orderByDesc('tanggal_mulai')->get(),
        'guruList' => Guru::with('person')->orderByNama()->get(),
        'polaJamList' => PolaJam::orderBy('nama')->get(),
        'faseList' => Fase::orderBy('urutan')->get(),
    ]);
}
```

Fix:
```php
public function edit(Kelas $kelas): View
{
    $this->authorize('kelas.edit');

    return view('admin.kelas.edit', [
        'kelas' => $kelas,
        'tahunAjaranList' => TahunAjaran::withoutGlobalScope(TenantScope::class)->where('lembaga_id', $kelas->lembaga_id)->orderByDesc('tanggal_mulai')->get(),
        'guruList' => Guru::withoutGlobalScope(TenantScope::class)->where('lembaga_id', $kelas->lembaga_id)->with('person')->orderByNama()->get(),
        'polaJamList' => PolaJam::withoutGlobalScope(TenantScope::class)->where('lembaga_id', $kelas->lembaga_id)->orderBy('nama')->get(),
        'faseList' => Fase::orderBy('urutan')->get(),
    ]);
}
```

**Catatan penting — beda dari A.2**: di sini TIDAK ada guard "kalau null tolak", karena `$kelas` sudah pasti punya `lembaga_id` terisi (kolom `NOT NULL` di skema, dikonfirmasi lewat `SHOW COLUMNS FROM kelas`). Route-model-binding `Kelas $kelas` sendiri sudah lolos `TenantScope` sebelum sampai method ini (aktor tidak akan bisa membuka kelas di luar yayasannya sama sekali — 404 duluan) — jadi `$kelas->lembaga_id` di titik ini SELALU valid dan aman dipakai langsung.

**Import baru wajib ditambahkan** ke `app/Http/Controllers/Admin/KelasController.php`:
```php
use App\Models\Lembaga;
use App\Models\Scopes\TenantScope;
use Illuminate\Http\RedirectResponse;
```
(`Lembaga` dipakai di Kelompok B `scopeHeaderData()` di bawah; `RedirectResponse` untuk return type baru `create()`; `TenantScope` untuk A.1-A.3. Semua dikonfirmasi BELUM ada di import file ini saat ini.)

---

## Kelompok B — Frontend: Badge Scope & Kolom Lembaga

### B.1 — Badge Scope di Header Index/Create/Edit

**Controller**: tambah helper privat `scopeHeaderData()` (pola identik Karyawan/Guru/TahunAjaran), dipanggil dari SEMUA method yang render halaman penuh (`index()` — KEDUA cabang, ajax maupun bukan, karena partial `_daftar.blade.php` butuh datanya juga untuk B.2; `create()`; `edit()`):

```php
/**
 * Info scope yayasan/lembaga yang sedang aktif, ditampilkan sebagai badge di header
 * halaman (pola sama seperti admin/siswa/index.blade.php) -- HANYA relevan untuk aktor
 * berscope yayasan (punya switcher lembaga).
 *
 * @return array{isYayasan: bool, activeLembaga: ?Lembaga}
 */
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

**`index()` LENGKAP setelah semua perubahan Kelompok B** (menggabungkan B.1 + B.2 + B.3 di 1 method, karena saling terkait):
```php
public function index(Request $request): View
{
    $this->authorize('kelas.view');

    $perPage = in_array((int) $request->input('per_page'), [10, 25, 50]) ? (int) $request->input('per_page') : 20;

    $query = Kelas::with(['tahunAjaran', 'waliKelas', 'lembaga'])->orderBy('nama');

    if ($search = $request->input('search')) {
        $query->where('nama', 'like', '%'.$search.'%');
    }

    if ($tahunAjaranId = $request->input('tahun_ajaran_id')) {
        $query->where('tahun_ajaran_id', $tahunAjaranId);
    }

    $kelasList = $query->paginate($perPage)->withQueryString();

    if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
        return view('admin.kelas._daftar', [
            'kelasList' => $kelasList,
            'perPage' => $perPage,
            ...$this->scopeHeaderData($request),
        ]);
    }

    return view('admin.kelas.index', [
        'kelasList' => $kelasList,
        'tahunAjaranList' => TahunAjaran::with('lembaga')->orderByDesc('tanggal_mulai')->get(),
        'perPage' => $perPage,
        'totalKelas' => Kelas::count(),
        'totalTaAktif' => Kelas::whereHas('tahunAjaran', fn ($q) => $q->where('status_aktif', true))->count(),
        ...$this->scopeHeaderData($request),
    ]);
}
```

Perubahan dari kode asli: (1) `with(['tahunAjaran', 'waliKelas'])` → tambah `'lembaga'` untuk B.2; (2) cabang AJAX SEKARANG ikut `...$this->scopeHeaderData($request)` (sebelumnya cuma `kelasList`+`perPage`) — WAJIB, karena `_daftar.blade.php` di-reload lewat AJAX setiap kali filter berubah, jadi kolom Lembaga (B.2) harus tahu status scope di SETIAP response, bukan cuma saat load halaman penuh pertama kali; (3) `tahunAjaranList` untuk filter dropdown SEKARANG `with('lembaga')` untuk B.3.

**View — Header Index** (`resources/views/admin/kelas/index.blade.php`, baris ±12-19):

Kode saat ini:
```blade
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="font-display text-lg font-bold text-gray-900">Kelas</h1>
        <p class="text-xs text-gray-500 mt-0.5">Kelola daftar kelas, penugasan wali kelas, dan ikatan tahun ajaran lembaga.</p>
    </div>
```

Fix:
```blade
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <div class="flex flex-wrap items-center gap-2.5">
            <h1 class="font-display text-lg font-bold text-gray-900">Kelas</h1>
            @if ($isYayasan ?? (auth()->user()?->widestScopeLevel() === 'yayasan'))
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
                    <x-icon name="apartment" class="h-3.5 w-3.5" />
                    {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
                </span>
            @endif
        </div>
        <p class="text-xs text-gray-500 mt-0.5">Kelola daftar kelas, penugasan wali kelas, dan ikatan tahun ajaran lembaga.</p>
    </div>
```

**View — Header Create** (`resources/views/admin/kelas/create.blade.php`, baris ±12-13):

Kode saat ini:
```blade
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-display text-lg font-bold text-gray-900">Tambah Kelas</h1>
```

Fix:
```blade
<div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap items-center gap-2.5">
        <h1 class="font-display text-lg font-bold text-gray-900">Tambah Kelas</h1>
        @if ($isYayasan ?? false)
            <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
                <x-icon name="apartment" class="h-3.5 w-3.5" />
                {{ $activeLembaga->nama }}
            </span>
        @endif
    </div>
```

**Catatan penting**: badge di halaman create BERBEDA dari pola index/edit — di sini `$activeLembaga` DIJAMIN tidak null berkat guard A.1 (form tidak akan pernah dirender kalau lembaga aktif kosong), jadi TIDAK perlu cabang warna ungu "Semua Lembaga" sama sekali — SELALU brand color, SELALU tampil nama lembaga. Ini konsisten dengan Keputusan #1 di atas (A.1-A.3 menghilangkan ambiguitas di titik create/edit).

**View — Header Edit** (`resources/views/admin/kelas/edit.blade.php`, baris ±12-13):

Kode saat ini:
```blade
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-display text-lg font-bold text-gray-900">Edit Kelas: {{ $kelas->nama }}</h1>
```

Fix:
```blade
<div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap items-center gap-2.5">
        <h1 class="font-display text-lg font-bold text-gray-900">Edit Kelas: {{ $kelas->nama }}</h1>
        @if ($isYayasan ?? false)
            <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
                <x-icon name="apartment" class="h-3.5 w-3.5" />
                {{ $kelas->lembaga->nama }}
            </span>
        @endif
    </div>
```

**Catatan**: badge edit pakai `$kelas->lembaga->nama` (lembaga PEMILIK kelas, konsisten dengan A.3), BUKAN `$activeLembaga` — sebab aktor yayasan-scope BISA membuka edit kelas ini walau sedang dalam mode "Semua Lembaga" (`$activeLembaga` null), tapi kelasnya sendiri tetap py 1 lembaga pasti. `$kelas->lembaga` sudah otomatis ter-load lewat relasi (tidak perlu eager-load tambahan khusus untuk 1 record, N+1 tidak relevan di halaman single-record).

### B.2 — Kolom "Lembaga" di Tabel Daftar Kelas

**File**: `resources/views/admin/kelas/_daftar.blade.php`

Kode saat ini (baris 24-32, header tabel):
```blade
<table class="w-full text-sm">
    <thead>
        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
            <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
            <th class="px-5 py-3">Nama Kelas</th>
            <th class="px-5 py-3">Tahun Ajaran</th>
            <th class="px-5 py-3">Wali Kelas</th>
        </tr>
    </thead>
```

Fix:
```blade
<table class="w-full text-sm">
    <thead>
        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
            <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
            <th class="px-5 py-3">Nama Kelas</th>
            @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
                <th class="px-5 py-3">Lembaga</th>
            @endif
            <th class="px-5 py-3">Tahun Ajaran</th>
            <th class="px-5 py-3">Wali Kelas</th>
        </tr>
    </thead>
```

Baris `<td>` (baris ±46-64), tambahkan `<td>` baru setelah kolom "Nama Kelas" dan SESUAIKAN `colspan` empty-state:
```blade
<tbody class="divide-y divide-gray-100">
    @foreach ($kelasList as $kelas)
        <tr class="transition hover:bg-gray-50">
            <td class="sticky left-0 z-10 bg-white px-5 py-3">
                <x-table-actions>
                    <x-dropdown-link :href="route('admin.kelas.edit', $kelas)">
                        <span class="inline-flex items-center gap-2.5">
                            <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                            Edit Kelas
                        </span>
                    </x-dropdown-link>
                </x-table-actions>
            </td>
            <td class="px-5 py-3.5 font-semibold text-gray-900">
                {{ $kelas->nama }}
                @if ($kelas->tingkat)
                    <span class="ml-1 text-xs font-normal text-gray-400">(Tingkat {{ $kelas->tingkat }})</span>
                @endif
            </td>
            @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
                <td class="px-5 py-3.5 text-gray-500">{{ $kelas->lembaga->nama ?? '-' }}</td>
            @endif
            <td class="px-5 py-3.5 text-gray-600">
                {{ $kelas->tahunAjaran->nama }}
                @if ($kelas->tahunAjaran->status_aktif)
                    <x-badge tone="green">Aktif</x-badge>
                @endif
            </td>
            <td class="px-5 py-3.5 text-gray-600">
                @if ($kelas->waliKelas)
                    {{ $kelas->waliKelas->nama }}
                @else
                    <x-badge tone="slate">Belum ditentukan</x-badge>
                @endif
            </td>
        </tr>
    @endforeach

    @if ($kelasList->isEmpty())
        <tr>
            <td colspan="{{ ($isYayasan ?? false) && ! ($activeLembaga ?? null) ? 5 : 4 }}" class="px-5 py-10 text-center text-gray-500">
                @if (request()->anyFilled(['search', 'tahun_ajaran_id']))
                    Tidak ada kelas yang cocok dengan filter ini.
                @else
                    Belum ada kelas yang didaftarkan.
                @endif
            </td>
        </tr>
    @endif
</tbody>
```

`colspan` DIHITUNG DINAMIS (4 atau 5) supaya baris empty-state tetap merentang penuh lebar tabel di kedua mode — kalau di-hardcode ke 5 akan meninggalkan celah kosong saat kolom Lembaga disembunyikan (mode switch/lembaga-scope).

### B.3 — Label Lembaga di Filter Dropdown Tahun Ajaran

**File**: `resources/views/admin/kelas/index.blade.php`

Kode saat ini (baris ±90-97):
```blade
<select x-ref="taSelect" x-init="initFilterSelect($refs.taSelect, 'tahun_ajaran_id', true)" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
    <option value="">Semua Tahun Ajaran</option>
    @foreach ($tahunAjaranList as $ta)
        <option value="{{ $ta->id }}" @selected(request('tahun_ajaran_id') == $ta->id)>
            {{ $ta->nama }}{{ $ta->status_aktif ? ' (Aktif)' : '' }}
        </option>
    @endforeach
</select>
```

Fix:
```blade
<select x-ref="taSelect" x-init="initFilterSelect($refs.taSelect, 'tahun_ajaran_id', true)" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
    <option value="">Semua Tahun Ajaran</option>
    @foreach ($tahunAjaranList as $ta)
        <option value="{{ $ta->id }}" @selected(request('tahun_ajaran_id') == $ta->id)>
            {{ $ta->nama }}{{ $ta->status_aktif ? ' (Aktif)' : '' }}{{ ($isYayasan ?? false) && ! ($activeLembaga ?? null) ? ' — '.($ta->lembaga->nama ?? '-') : '' }}
        </option>
    @endforeach
</select>
```

Label lembaga HANYA muncul saat mode "Semua Lembaga" (konsisten dengan B.2) — di mode switch/lembaga-scope, semua TA di filter otomatis sudah 1 lembaga yang sama (TenantScope ambien di query `tahunAjaranList`, TIDAK diubah spec ini), jadi label akan redundan.

---

## Di Luar Scope / Backlog Terpisah

1. **`StoreKelasRequest`/`UpdateKelasRequest` tidak punya `exists:` rule untuk `tahun_ajaran_id`/`wali_kelas_guru_id`/`pola_jam_id`** (cuma `integer`) — arsitektur tidak konsisten (validasi referential integrity ada di Action, bukan Request), TAPI tetap aman karena Action sudah menjaga. Backlog kalau mau dirapikan ke pola FormRequest yang lebih standar, tidak mendesak.
2. **Konsistensi guru dropdown tidak difilter per `jenis_ptk`** (mis. wali kelas idealnya cuma `guru_kelas`, bukan semua jenis PTK) — bukan masalah scope yayasan/lembaga, di luar cakupan audit ini.
3. **Kemungkinan data legacy** di mana `$kelas->waliKelas`/`polaJam` punya `lembaga_id` berbeda dari `$kelas->lembaga_id` (seharusnya tidak mungkin terjadi berkat guard `UpdateKelasAction`, tapi kalau ada data lama sebelum guard itu ada) — dropdown edit (A.3) TIDAK AKAN menampilkan opsi tsb sebagai `@selected` (karena difilter keluar dari daftar), yang berarti submit tanpa mengubah dropdown itu akan MENGOSONGKAN field tsb secara diam-diam. Tidak ditangani di spec ini (asumsi data konsisten berkat guard existing) — backlog audit data kalau ternyata ada laporan kejadian nyata.

---

## Ringkasan Test yang Wajib Ditambahkan/Diperbarui

| Item | Test |
|---|---|
| A.1 | Feature test — yayasan-scope tanpa lembaga aktif GET `admin.kelas.create`, assert redirect ke index + `assertSessionHasErrors('lembaga_id')`; yayasan-scope DENGAN lembaga aktif, assert `assertOk()` |
| A.2 | Feature test — yayasan-scope switch ke Lembaga A, buat 1 TA di Lembaga B (yayasan sama); GET `admin.kelas.create`, assert response TIDAK mengandung nama TA milik Lembaga B (`assertDontSee`) |
| A.3 | Feature test — kelas milik Lembaga A, yayasan-scope dalam mode "Semua Lembaga" (tanpa switch); buat TA/guru di Lembaga B (yayasan sama); GET `admin.kelas.edit`, assert dropdown TIDAK mengandung opsi milik Lembaga B |
| B.1 | Feature test — assert badge "Semua Lembaga"/nama lembaga muncul di index; assert badge SELALU nama lembaga (bukan "Semua Lembaga") di create (karena guard A.1); assert badge nama lembaga KELAS (bukan lembaga switcher aktor) di edit saat mode "Semua Lembaga" |
| B.2 | Feature test — 2 kelas nama sama di 2 lembaga berbeda, mode "Semua Lembaga": assert kedua nama lembaga muncul di tabel; mode switch: assert kolom "Lembaga" TIDAK muncul (`assertDontSee('>Lembaga<', false)` pada header tabel, atau cek jumlah kolom via `<th>` count) |
| B.3 | Feature test — 2 TA nama sama di 2 lembaga berbeda, mode "Semua Lembaga": assert filter dropdown mengandung kedua nama lembaga; mode switch: assert TIDAK mengandung tanda "—" pemisah lembaga |

Regresi wajib: seluruh test yang menyentuh `KelasController`, `CreateKelasAction`, `UpdateKelasAction`, `StoreKelasRequest`/`UpdateKelasRequest` (nama file test dikonfirmasi ulang saat penulisan plan).
