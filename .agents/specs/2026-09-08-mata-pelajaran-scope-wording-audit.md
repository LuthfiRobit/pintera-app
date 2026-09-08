# Spec: Validasi Scope Backend & Kejujuran Wording — Menu Mata Pelajaran

> **Branch**: `rbac-v2`
> **Tanggal**: 8 September 2026
> **Latar belakang**: Audit menu "Mata Pelajaran" lanjutan dari list `.agents/logs/2026-09-07-audit-scope-yayasan-lembaga-sidebar.md` (setelah Tahun Ajaran & Kelas selesai). Bentuknya beda dari Kelas — TIDAK ada dropdown lintas-model (Guru/TahunAjaran/PolaJam) yang perlu di-scope — tapi ditemukan celah backend yang LEBIH SERIUS: `store()` tidak pernah memvalidasi ulang `session('active_lembaga_id')` terhadap yayasan aktor, beda dari SEMUA controller lain yang sudah diperbaiki sesi ini (Kelas, TahunAjaran, Karyawan, Guru — semua pakai `ResolveLembagaScopeTrait`). `MataPelajaranController` TIDAK memakai trait ini sama sekali.

## Ringkasan Temuan (5 item, 2 kelompok)

| # | Kelompok | Severity | Ringkasan |
|---|---|---|---|
| A.1 | Backend | 🔴 Kritis | `store()` baca `session('active_lembaga_id')` mentah tanpa validasi ulang kepemilikan yayasan — celah cross-tenant data pollution kalau session stale |
| A.2 | Backend | 🔴 Tinggi | `create()` (GET) tidak divalidasi lembaga aktif sebelum render form — pola sama seperti bug Kelas yang sudah diperbaiki |
| A.3 | Backend | 🟡 Sedang | `$isPaud` di `index()` diambil dari `auth()->user()->lembaga` (lembaga MILIK AKTOR), bukan lembaga AKTIF — banner PAUD tidak pernah muncul untuk aktor yayasan-scope |
| B.1 | Frontend | 🔴 Tinggi | Index/create/edit tidak punya badge scope yayasan/lembaga |
| B.2 | Frontend | 🔴 Tinggi | Tabel daftar (`_daftar.blade.php`) tidak punya kolom "Lembaga" — ambigu di mode "Semua Lembaga", terutama karena kode mapel standar (mis. "MTK-01") sangat mungkin duplikat identik lintas lembaga dalam 1 yayasan |

## Keputusan yang Diambil

1. **A.1 adalah prioritas TERTINGGI di spec ini** — beda dari Kelas/TahunAjaran (yang backend-nya sudah aman, cuma UX kurang informatif), celah ini BENAR-BENAR bisa menghasilkan data mata pelajaran tersimpan di lembaga LUAR yayasan aktor kalau `session('active_lembaga_id')` sempat stale. `CreateMataPelajaranAction` mempercayai `lembaga_id` mentah-mentah tanpa guard tambahan apa pun (dikonfirmasi lewat pembacaan langsung) — TIDAK ADA lapisan pertahanan kedua seperti `CreateKelasAction`/`UpdateKelasAction` yang 404 kalau kombinasi lembaga salah.
2. **`MataPelajaranController` diubah untuk memakai `ResolveLembagaScopeTrait`** (belum pernah dipakai sebelumnya di controller ini) — method `resolveActiveLembagaId(User $actor)` SUDAH melakukan validasi ulang kepemilikan yayasan secara built-in (`return $milikYayasan ? $lembagaId : null;`), jadi fix A.1 SEKALIGUS otomatis benar tanpa logic tambahan.
3. **A.3 (`$isPaud`) diperbaiki dengan sumber yang SAMA seperti A.1/A.2** (`resolveActiveLembagaId()`) — bukan `auth()->user()->lembaga`. Ini KONSISTEN dengan filosofi seluruh audit sesi ini: "lembaga yang relevan untuk suatu aksi/tampilan adalah lembaga AKTIF, bukan lembaga statis milik akun aktor".
4. **`UpdateMataPelajaranAction`/`update()` TIDAK diubah** — keduanya sudah aman (`lembaga_id` immutable setelah dibuat, TIDAK pernah diubah lewat form update; route-model-binding `MataPelajaran $mataPelajaran` sudah otomatis terlindungi `TenantScope`).
5. **Tidak ada A.4/A.5 untuk scoping dropdown seperti Kelas** — dikonfirmasi lewat pembacaan `_form.blade.php`: form Mata Pelajaran TIDAK punya field pilihan lintas-model (semua field-nya `kode`/`nama`/`no_urut`/enum `tipe`/`kelompok`/`status`), jadi tidak ada analog Kelompok A.2/A.3 Kelas yang relevan di sini.

---

## Kelompok A — Backend

### A.1 — `store()`: Ganti Baca Session Mentah dengan `resolveActiveLembagaId()`

**File**: `app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php`

Kode saat ini:
```php
public function store(Request $request, CreateMataPelajaranAction $action): RedirectResponse
{
    $this->authorize('mata-pelajaran.create');

    $lembagaId = $request->user()->widestScopeLevel() === 'yayasan' ? session('active_lembaga_id') : $request->user()->lembaga_id;
    if ($lembagaId === null) {
        return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif terlebih dahulu.'])->withInput();
    }
    // ...sisa method tidak berubah
```

Fix:
```php
public function store(Request $request, CreateMataPelajaranAction $action): RedirectResponse
{
    $this->authorize('mata-pelajaran.create');

    $lembagaId = $this->resolveActiveLembagaId($request->user());
    if ($lembagaId === null) {
        return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif terlebih dahulu.'])->withInput();
    }
    // ...sisa method tidak berubah
```

**Kelas WAJIB ditambah**: `use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;` (import) dan `use ResolveLembagaScopeTrait;` (di dalam badan class, sejajar `use AuthorizesRequests;`) — trait ini SUDAH ADA di codebase (dipakai `KelasController`/`TahunAjaranController`), TIDAK dibuat baru.

**Kenapa ini menutup celah**: `resolveActiveLembagaId()` untuk aktor yayasan-scope melakukan `Lembaga::where('id', $lembagaId)->where('yayasan_id', $actor->yayasan_id)->exists()` sebelum mengembalikan nilai — kalau lembaga di session TERNYATA bukan milik yayasan aktor (skenario stale, PERSIS yang sudah dites eksplisit di `KelasCrudTest.php` untuk Kelas), method ini mengembalikan `null`, yang otomatis memicu baris `if ($lembagaId === null) { return back()->withErrors(...) }` yang SUDAH ADA — TIDAK perlu logic percabangan baru sama sekali, cukup ganti sumber `$lembagaId`.

### A.2 — Guard `create()` Sebelum Render Form

**File**: `app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php`

Kode saat ini:
```php
public function create(): View
{
    $this->authorize('mata-pelajaran.create');

    return view('portals.lembaga.akademik.mata-pelajaran.create', [
        'tipeList' => TipeMataPelajaran::cases(),
        'kelompokList' => KelompokMataPelajaran::cases(),
        'statusList' => StatusMataPelajaran::cases(),
    ]);
}
```

Fix (digabung dengan B.1 di bawah — 1 method, 1 edit):
```php
public function create(Request $request): View|RedirectResponse
{
    $this->authorize('mata-pelajaran.create');

    $lembagaId = $this->resolveActiveLembagaId($request->user());
    if ($lembagaId === null) {
        return redirect()->route('admin.mata-pelajaran.index')
            ->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah mata pelajaran.']);
    }

    return view('portals.lembaga.akademik.mata-pelajaran.create', [
        'tipeList' => TipeMataPelajaran::cases(),
        'kelompokList' => KelompokMataPelajaran::cases(),
        'statusList' => StatusMataPelajaran::cases(),
        ...$this->scopeHeaderData($request),
    ]);
}
```

Wording error SENGAJA DIBUAT MIRIP (bukan identik — beda dari pesan `store()` "Pilih lembaga aktif terlebih dahulu." yang lebih pendek) dengan pola `KelasController::create()`'s guard message ("Pilih lembaga aktif melalui pengalih lembaga sebelum menambah kelas.") — konsisten format kalimat "Pilih lembaga aktif melalui pengalih lembaga sebelum [aksi]." lintas modul.

### A.3 — `$isPaud` Diambil dari Lembaga AKTIF, Bukan Lembaga Milik Aktor

**File**: `app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php`

Kode saat ini (`index()`, bagian akhir):
```php
        return view('portals.lembaga.akademik.mata-pelajaran.index', [
            'mataPelajaranList' => $paginated,
            'tipeList' => TipeMataPelajaran::cases(),
            'kelompokList' => KelompokMataPelajaran::cases(),
            'statusList' => StatusMataPelajaran::cases(),
            'perPage' => $perPage,
            'totalMapel' => MataPelajaran::count(),
            'countKurikulum' => MataPelajaran::where('tipe', TipeMataPelajaran::Mapel->value)->count(),
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
        ]);
```

Fix (digabung dengan B.1 di bawah — bagian dari `index()` yang sama):
```php
        $lembagaAktifId = $this->resolveActiveLembagaId($request->user());

        return view('portals.lembaga.akademik.mata-pelajaran.index', [
            'mataPelajaranList' => $paginated,
            'tipeList' => TipeMataPelajaran::cases(),
            'kelompokList' => KelompokMataPelajaran::cases(),
            'statusList' => StatusMataPelajaran::cases(),
            'perPage' => $perPage,
            'totalMapel' => MataPelajaran::count(),
            'countKurikulum' => MataPelajaran::where('tipe', TipeMataPelajaran::Mapel->value)->count(),
            'isPaud' => in_array(
                Lembaga::find($lembagaAktifId)?->bentuk_pendidikan,
                [
                    BentukPendidikan::Kb->value,
                    BentukPendidikan::Tpa->value,
                    BentukPendidikan::Sps->value,
                    BentukPendidikan::Tk->value,
                ],
                true
            ),
            ...$this->scopeHeaderData($request),
        ]);
```

**Catatan**: `$lembagaAktifId` DIHITUNG TERPISAH dari `scopeHeaderData()` (bukan reuse `activeLembaga`-nya) — `scopeHeaderData()` HANYA mengisi `activeLembaga` untuk aktor yayasan-scope (`null` untuk lembaga-scope, sesuai desain badge yang memang tidak relevan untuk lembaga-scope). `$isPaud` HARUS benar untuk KEDUA jenis aktor (lembaga-scope: bentuk_pendidikan lembaganya sendiri; yayasan-scope: bentuk_pendidikan lembaga yang sedang di-switch) — makanya pakai `resolveActiveLembagaId()` langsung + `Lembaga::find()`, bukan bergantung pada output `scopeHeaderData()` yang gated `$isYayasan`.

**Import baru wajib**: `use App\Models\Lembaga;` (dikonfirmasi belum ada di file ini).

---

## Kelompok B — Frontend

### B.1 — Badge Scope di Header Index/Create/Edit

**Controller**: tambah helper privat `scopeHeaderData()` (pola identik Karyawan/Guru/TahunAjaran/Kelas), dipanggil dari `index()` (KEDUA cabang — ajax dan halaman penuh), `create()` (sudah termasuk kode A.2 di atas), `edit()`:

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

**`index()` LENGKAP setelah A.3 + B.1 digabung**:
```php
public function index(Request $request): View
{
    $this->authorize('mata-pelajaran.view');

    $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

    $query = MataPelajaran::with('lembaga')->orderBy('no_urut')->orderBy('nama');

    if ($search = $request->input('search')) {
        $query->where(function ($q) use ($search) {
            $q->where('nama', 'like', '%'.$search.'%')
                ->orWhere('kode', 'like', '%'.$search.'%');
        });
    }

    if ($tipe = $request->input('tipe')) {
        $query->where('tipe', $tipe);
    }

    if ($kelompok = $request->input('kelompok')) {
        $query->where('kelompok', $kelompok);
    }

    if ($status = $request->input('status')) {
        $query->where('status', $status);
    }

    $paginated = $query->paginate($perPage)->withQueryString();

    if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
        return view('portals.lembaga.akademik.mata-pelajaran._daftar', [
            'mataPelajaranList' => $paginated,
            'perPage' => $perPage,
            ...$this->scopeHeaderData($request),
        ]);
    }

    $lembagaAktifId = $this->resolveActiveLembagaId($request->user());

    return view('portals.lembaga.akademik.mata-pelajaran.index', [
        'mataPelajaranList' => $paginated,
        'tipeList' => TipeMataPelajaran::cases(),
        'kelompokList' => KelompokMataPelajaran::cases(),
        'statusList' => StatusMataPelajaran::cases(),
        'perPage' => $perPage,
        'totalMapel' => MataPelajaran::count(),
        'countKurikulum' => MataPelajaran::where('tipe', TipeMataPelajaran::Mapel->value)->count(),
        'isPaud' => in_array(
            Lembaga::find($lembagaAktifId)?->bentuk_pendidikan,
            [
                BentukPendidikan::Kb->value,
                BentukPendidikan::Tpa->value,
                BentukPendidikan::Sps->value,
                BentukPendidikan::Tk->value,
            ],
            true
        ),
        ...$this->scopeHeaderData($request),
    ]);
}
```

Perubahan dari kode asli: (1) `MataPelajaran::orderBy(...)` → tambah `with('lembaga')` untuk B.2; (2) cabang AJAX SEKARANG ikut `...$this->scopeHeaderData($request)`; (3) `isPaud` dipindah sumbernya (A.3); (4) seluruh return array halaman penuh ikut `...$this->scopeHeaderData($request)`.

**`edit()` LENGKAP setelah B.1**:
```php
public function edit(Request $request, MataPelajaran $mataPelajaran): View
{
    $this->authorize('mata-pelajaran.edit');

    return view('portals.lembaga.akademik.mata-pelajaran.edit', [
        'mataPelajaran' => $mataPelajaran,
        'tipeList' => TipeMataPelajaran::cases(),
        'kelompokList' => KelompokMataPelajaran::cases(),
        'statusList' => StatusMataPelajaran::cases(),
        ...$this->scopeHeaderData($request),
    ]);
}
```

(`Request $request` ditambahkan ke signature, sebelum route-model-binding `MataPelajaran $mataPelajaran` — konsisten pola `KelasController::edit()`.)

**View — Header Index** (`resources/views/portals/lembaga/akademik/mata-pelajaran/index.blade.php`, baris ±12-19):

Kode saat ini:
```blade
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="font-display text-lg font-bold text-gray-900">Mata Pelajaran</h1>
        <p class="mt-0.5 text-xs text-gray-500">Kelola daftar mata pelajaran untuk kurikulum lembaga.</p>
    </div>
```

Fix:
```blade
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <div class="flex flex-wrap items-center gap-2.5">
            <h1 class="font-display text-lg font-bold text-gray-900">Mata Pelajaran</h1>
            @if ($isYayasan ?? (auth()->user()?->widestScopeLevel() === 'yayasan'))
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
                    <x-icon name="apartment" class="h-3.5 w-3.5" />
                    {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
                </span>
            @endif
        </div>
        <p class="mt-0.5 text-xs text-gray-500">Kelola daftar mata pelajaran untuk kurikulum lembaga.</p>
    </div>
```

**View — Header Create** (`resources/views/portals/lembaga/akademik/mata-pelajaran/create.blade.php`, baris ±12-13):

Kode saat ini:
```blade
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-display text-lg font-bold text-gray-900">Tambah Mata Pelajaran</h1>
```

Fix:
```blade
<div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap items-center gap-2.5">
        <h1 class="font-display text-lg font-bold text-gray-900">Tambah Mata Pelajaran</h1>
        @if ($isYayasan ?? false)
            <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
                <x-icon name="apartment" class="h-3.5 w-3.5" />
                {{ $activeLembaga->nama }}
            </span>
        @endif
    </div>
```

**Catatan**: sama seperti Kelas, badge create SELALU brand color (tidak pernah varian ungu) karena guard A.2 menjamin `$activeLembaga` tidak null di titik ini.

**View — Header Edit** (`resources/views/portals/lembaga/akademik/mata-pelajaran/edit.blade.php`, baris ±12-13):

Kode saat ini:
```blade
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-display text-lg font-bold text-gray-900">Edit Mata Pelajaran: {{ $mataPelajaran->nama }}</h1>
```

Fix:
```blade
<div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap items-center gap-2.5">
        <h1 class="font-display text-lg font-bold text-gray-900">Edit Mata Pelajaran: {{ $mataPelajaran->nama }}</h1>
        @if ($isYayasan ?? false)
            <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
                <x-icon name="apartment" class="h-3.5 w-3.5" />
                {{ $mataPelajaran->lembaga->nama }}
            </span>
        @endif
    </div>
```

Badge edit pakai `$mataPelajaran->lembaga->nama` (lembaga PEMILIK record), BUKAN `$activeLembaga` — konsisten pola Kelas A.3/B.1.

### B.2 — Kolom "Lembaga" di Tabel Daftar

**File**: `resources/views/portals/lembaga/akademik/mata-pelajaran/_daftar.blade.php`

Kode saat ini (baris 22-31, header tabel):
```blade
<thead>
    <tr class="border-b border-gray-200 bg-gray-50/75 font-display text-xs font-bold uppercase tracking-wider text-gray-500">
        <th class="sticky left-0 z-10 bg-gray-50/75 px-5 py-3 w-32">Aksi</th>
        <th class="px-4 py-3 text-center w-20">No. Rapor</th>
        <th class="px-4 py-3 w-32">Kode</th>
        <th class="px-4 py-3">Nama Mata Pelajaran</th>
        <th class="px-4 py-3">Tipe</th>
        <th class="px-4 py-3">Kelompok</th>
        <th class="px-5 py-3 text-center w-28">Status</th>
    </tr>
</thead>
```

Fix:
```blade
<thead>
    <tr class="border-b border-gray-200 bg-gray-50/75 font-display text-xs font-bold uppercase tracking-wider text-gray-500">
        <th class="sticky left-0 z-10 bg-gray-50/75 px-5 py-3 w-32">Aksi</th>
        <th class="px-4 py-3 text-center w-20">No. Rapor</th>
        <th class="px-4 py-3 w-32">Kode</th>
        <th class="px-4 py-3">Nama Mata Pelajaran</th>
        @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
            <th class="px-4 py-3">Lembaga</th>
        @endif
        <th class="px-4 py-3">Tipe</th>
        <th class="px-4 py-3">Kelompok</th>
        <th class="px-5 py-3 text-center w-28">Status</th>
    </tr>
</thead>
```

Body tabel (baris 34-68), tambahkan `<td>` baru setelah "Nama Mata Pelajaran" dan sesuaikan `colSpan` empty-state (baris 71, saat ini `colSpan="7"` hardcode):
```blade
<tbody class="divide-y divide-gray-100 font-normal">
    @forelse ($mataPelajaranList as $mapel)
        <tr class="transition-colors hover:bg-gray-50/60">
            <td class="sticky left-0 z-10 bg-white px-5 py-3">
                <x-table-actions>
                    @can('mata-pelajaran.edit')
                    <a href="{{ route('admin.mata-pelajaran.edit', $mapel) }}" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                        <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                        Edit Mata Pelajaran
                    </a>
                    @endcan
                </x-table-actions>
            </td>
            <td class="px-4 py-3.5 text-center font-mono text-xs font-bold text-gray-600">
                {{ $mapel->no_urut }}
            </td>
            <td class="px-4 py-3.5 font-mono text-xs font-semibold text-brand-600">
                {{ $mapel->kode }}
            </td>
            <td class="px-4 py-3.5 font-medium text-gray-900">
                {{ $mapel->nama }}
            </td>
            @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
                <td class="px-4 py-3.5 text-xs text-gray-500">{{ $mapel->lembaga->nama ?? '-' }}</td>
            @endif
            <td class="px-4 py-3.5 text-xs text-gray-600">
                {{ $mapel->tipe->label() }}
            </td>
            <td class="px-4 py-3.5 text-xs text-gray-600">
                {{ $mapel->kelompok?->label() ?? '—' }}
            </td>
            <td class="px-5 py-3.5 text-center">
                @if ($mapel->status === \App\Enums\StatusMataPelajaran::Aktif)
                    <span class="inline-flex items-center rounded-full bg-success-50 px-2.5 py-0.5 text-xs font-medium text-success-700">Aktif</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">Nonaktif</span>
                @endif
            </td>
        </tr>
    @empty
        <tr>
            <td colSpan="{{ ($isYayasan ?? false) && ! ($activeLembaga ?? null) ? 8 : 7 }}" class="px-5 py-12 text-center text-gray-500">
                <p class="text-sm">Belum ada mata pelajaran yang didaftarkan.</p>
            </td>
        </tr>
    @endforelse
</tbody>
```

`colSpan` dihitung dinamis (7 atau 8) — pola sama seperti fix Kelas B.2.

---

## Di Luar Scope / Backlog Terpisah

1. **Filter dropdown (Tipe/Kelompok/Status) di index TIDAK butuh label lembaga** — beda dari Kelas, filter-filter ini adalah enum tetap (bukan entitas per-lembaga seperti Tahun Ajaran), jadi tidak ada ambiguitas lintas-lembaga di situ sama sekali.
2. **Empty-state tabel tidak dibedakan "belum ada data" vs "tidak ada hasil filter"** (selalu "Belum ada mata pelajaran yang didaftarkan.", padahal `_daftar.blade.php` menerima parameter `search`/`tipe`/`kelompok`/`status`) — ditemukan saat audit, TAPI ini murni wording UX generik (sama sekali tidak terkait scope yayasan/lembaga), di luar tema spec ini. Backlog terpisah kalau user memang menghendaki.
3. **`UpdateMataPelajaranAction`/`update()` tetap tidak diubah** — sudah aman by design (`lembaga_id` immutable, TenantScope melindungi route-model-binding).

---

## Ringkasan Test yang Wajib Ditambahkan/Diperbarui

| Item | Test |
|---|---|
| A.1 | Feature test — yayasan-scope dengan `active_lembaga_id` session STALE (lembaga milik yayasan LAIN, pola persis test Kelas "menolak actor yayasan dengan active_lembaga_id stale"), POST `admin.mata-pelajaran.store`, assert `assertSessionHasErrors('lembaga_id')` DAN mata pelajaran TIDAK tersimpan sama sekali (termasuk cek `MataPelajaran::withoutGlobalScopes()` untuk memastikan tidak nyelip ke lembaga manapun) |
| A.2 | Feature test — yayasan-scope tanpa lembaga aktif GET `admin.mata-pelajaran.create`, assert redirect ke index + `assertSessionHasErrors('lembaga_id')`; DENGAN lembaga aktif, assert `assertOk()` |
| A.3 | Feature test — yayasan-scope switch ke lembaga `bentuk_pendidikan=TK`, assert banner "Catatan untuk PAUD" MUNCUL (test lama di `MataPelajaranCrudTest.php` cuma menguji lembaga-scope, test baru INI KHUSUS menguji yayasan-scope — kasus yang sebelumnya gagal total) |
| B.1 | Feature test — assert badge muncul di index/create/edit sesuai pola Kelas (3 test terpisah) |
| B.2 | Feature test — 2 mapel kode SAMA di 2 lembaga berbeda (skenario realistis: "MTK-01" di kedua lembaga), mode "Semua Lembaga": assert kedua nama lembaga muncul di tabel; mode switch: assert kolom "Lembaga" TIDAK muncul |

Regresi wajib: seluruh test `tests/Feature/Admin/MataPelajaranCrudTest.php` (existing, TERMASUK 2 test PAUD yang sudah ada — harus tetap hijau untuk kasus lembaga-scope), `tests/Unit/Models/MataPelajaranTest.php`, `tests/Unit/MataPelajaranSeederTest.php`.
