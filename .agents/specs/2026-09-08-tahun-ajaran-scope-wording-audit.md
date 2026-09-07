# Spec: Badge Scope & Kejujuran Wording — Menu Tahun Ajaran

> **Branch**: `rbac-v2`
> **Tanggal**: 8 September 2026
> **Latar belakang**: Audit menu "Tahun Ajaran" atas permintaan user, mengikuti urutan yang disepakati (backend dulu, lalu frontend/wording dicocokkan ke backend). Backend (`TenantScope`, `TahunAjaranController`, `TahunAjaran::activate()`) TERKONFIRMASI BENAR untuk "Semua Lembaga" maupun "Switch Lembaga" — spec ini TIDAK mengubah satu baris backend query/scope pun. Temuan murni di lapisan frontend: informasi yang ditampilkan ke user tidak konkret/tidak selalu jujur terhadap apa yang backend-nya benar-benar lakukan, dan halaman ini belum mengikuti pola badge scope yayasan/lembaga yang sudah dipasang di Siswa/Karyawan/Guru pada sesi-sesi sebelumnya.

## Ringkasan Temuan (5 item)

| # | Severity | Ringkasan |
|---|---|---|
| 1 | 🔴 Tinggi | Index tidak punya badge scope yayasan/lembaga (beda dari Siswa/Karyawan/Guru) |
| 2 | 🔴 Tinggi | Kartu Tahun Ajaran tidak menampilkan nama lembaga pemiliknya — ambigu total saat mode "Semua Lembaga" kalau ≥2 lembaga punya TA bernama sama |
| 3 | 🟡 Sedang | Wording dialog konfirmasi "Aktifkan" tidak menyebut lingkup "di lembaga yang sama" — berpotensi menyesatkan padahal backend-nya sudah benar |
| 4 | 🔴 Tinggi | Modal Tambah/Edit tidak menunjukkan konteks lembaga tujuan sebelum submit |
| 5 | 🟡 Sedang | Halaman `admin.tahun-ajaran.create` (route+controller+view) adalah dead code, tidak pernah diakses dari UI manapun, dan pakai design system lama |

## Keputusan yang Diambil

1. **Item 5 (halaman mati) — DIHAPUS**, bukan dibiarkan. Dikonfirmasi lewat grep menyeluruh ke `resources/views` bahwa `route('admin.tahun-ajaran.create')` TIDAK direferensikan di manapun (UI asli sepenuhnya modal SPA di `index.blade.php`). View-nya juga memakai kelas desain lama (`x-panel`, `text-brass`, `text-ink`, `text-slate`) yang tidak konsisten dengan konvensi terkini (`font-display`, `text-gray-900`, dst.) — mempertahankannya cuma menambah kebingungan untuk maintainer berikutnya. **Jika user berubah pikiran saat review spec ini, cukup pindahkan Item 5 ke "Di Luar Scope" sebelum lanjut ke plan.**
2. **Pola badge scope (Item 1, 4) mengikuti PERSIS pola yang sudah established** di `admin/siswa/index.blade.php` dan yang baru saja diterapkan ke Karyawan/Guru (lihat `.agents/logs/2026-09-07-karyawan-guru-lintas-yayasan-audit.md` bagian 5) — TIDAK menciptakan pola baru, murni replikasi: badge nama lembaga aktif (warna brand) atau "Semua Lembaga" (warna ungu), hanya untuk aktor `widestScopeLevel() === 'yayasan'`.
3. **Item 2 (label lembaga di kartu) HANYA tampil saat mode "Semua Lembaga"** (yayasan-scope + belum pilih lembaga aktif) — untuk lembaga-scope actor atau yayasan-scope yang sudah switch ke 1 lembaga, label ini disembunyikan (tidak berguna, semua kartu pasti 1 lembaga yang sama).

---

## Item 1 — Badge Scope Yayasan/Lembaga di Header Index

**File**: `resources/views/admin/tahun-ajaran/index.blade.php` (baris 88-92)

Kode saat ini:
```blade
<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">Data Induk</p>
        <h1 class="mt-0.5 font-display text-xl font-bold tracking-tight text-gray-900">Tahun Ajaran &amp; Semester</h1>
    </div>
```

Fix (badge disisipkan di sebelah `<h1>`, pola identik Siswa/Karyawan/Guru):
```blade
<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">Data Induk</p>
        <div class="mt-0.5 flex flex-wrap items-center gap-2.5">
            <h1 class="font-display text-xl font-bold tracking-tight text-gray-900">Tahun Ajaran &amp; Semester</h1>
            @if ($isYayasan ?? (auth()->user()?->widestScopeLevel() === 'yayasan'))
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
                    <x-icon name="apartment" class="h-3.5 w-3.5" />
                    {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
                </span>
            @endif
        </div>
    </div>
```

**Controller**: `app/Http/Controllers/Admin/TahunAjaranController.php` — tambah helper privat `scopeHeaderData()` (pola identik `KaryawanController`/`GuruController`, TIDAK menyentuh query `index()` yang sudah benar):

```php
public function index(Request $request): View
{
    $this->authorize('tahun-ajaran.view');

    return view('admin.tahun-ajaran.index', [
        'tahunAjaranList' => TahunAjaran::with(['semester', 'lembaga'])->get(),
        ...$this->scopeHeaderData($request),
    ]);
}

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

Catatan: `index()` yang lama `public function index(): View` (tanpa `Request $request`) harus diubah menerima `Request $request` untuk bisa memanggil `scopeHeaderData()`. `resolveActiveLembagaId()` sudah tersedia dari `ResolveLembagaScopeTrait` yang SUDAH di-`use` di controller ini (dipakai dengan cara yang SAMA seperti fix `GuruController` sebelumnya — dipilih ketimbang `resolveLembagaId()` trait yang sama karena varian itu `abort()` saat yayasan-scope belum pilih lembaga aktif, tidak cocok untuk badge yang harus tetap tampil sebagai "Semua Lembaga"). Tambah `use App\Models\Lembaga;` ke import (dikonfirmasi belum ada di file ini saat ini).

`with(['semester', 'lembaga'])` — relasi `lembaga` ditambahkan ke eager load di sini karena Item 2 di bawah membutuhkannya (`$ta->lembaga->nama`), menghindari N+1 query.

---

## Item 2 — Label Lembaga per Kartu Saat Mode "Semua Lembaga"

**File**: `resources/views/admin/tahun-ajaran/index.blade.php` (baris 113-126, bagian "Top Bar Card")

Kode saat ini:
```blade
<div>
    <div class="flex items-center gap-2">
        <h3 class="font-display text-lg font-bold text-gray-900">{{ $ta->nama }}</h3>
        @if ($isTaActive)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 border border-emerald-200 shadow-2xs">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                Aktif
            </span>
        @else
            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-medium text-gray-600">
                Non-aktif
            </span>
        @endif
    </div>
    <p class="mt-1.5 flex items-center gap-1.5 text-xs font-medium text-gray-500">
        <x-icon name="event" class="h-3.5 w-3.5 text-gray-400" />
        <span>{{ \Carbon\Carbon::parse($ta->tanggal_mulai)->translatedFormat('d M Y') }} - {{ \Carbon\Carbon::parse($ta->tanggal_selesai)->translatedFormat('d M Y') }}</span>
    </p>
</div>
```

Fix — sisipkan label lembaga (badge kecil abu-abu netral, BUKAN warna brand/purple supaya tidak tertukar visual dengan badge scope header di Item 1) setelah baris tanggal, HANYA saat mode "Semua Lembaga" (`$isYayasan` true DAN `$activeLembaga` null — variabel ini sudah tersedia di scope view dari Item 1):
```blade
<div>
    <div class="flex items-center gap-2">
        <h3 class="font-display text-lg font-bold text-gray-900">{{ $ta->nama }}</h3>
        @if ($isTaActive)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 border border-emerald-200 shadow-2xs">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                Aktif
            </span>
        @else
            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-medium text-gray-600">
                Non-aktif
            </span>
        @endif
    </div>
    <p class="mt-1.5 flex items-center gap-1.5 text-xs font-medium text-gray-500">
        <x-icon name="event" class="h-3.5 w-3.5 text-gray-400" />
        <span>{{ \Carbon\Carbon::parse($ta->tanggal_mulai)->translatedFormat('d M Y') }} - {{ \Carbon\Carbon::parse($ta->tanggal_selesai)->translatedFormat('d M Y') }}</span>
    </p>
    @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
        <p class="mt-1 flex items-center gap-1.5 text-[11px] font-medium text-gray-400">
            <x-icon name="apartment" class="h-3 w-3" />
            <span>{{ $ta->lembaga->nama ?? '-' }}</span>
        </p>
    @endif
</div>
```

`$ta->lembaga` sudah eager-loaded lewat perubahan `with()` di Item 1 — tidak menambah query per-kartu.

---

## Item 3 — Wording Dialog Konfirmasi "Aktifkan" Diperjelas

**File**: `resources/views/admin/tahun-ajaran/index.blade.php`, 2 lokasi identik (baris 141 untuk tombol Aktifkan Tahun Ajaran; wording semester "Aktifkan" di baris 198/234 TIDAK termasuk temuan ini — sudah tidak ada dialog konfirmasi sama sekali di situ, di luar scope)

Kode saat ini:
```blade
<form action="{{ route('admin.tahun-ajaran.activate', $ta) }}" method="POST" class="inline" onsubmit="return confirm('Aktifkan Tahun Ajaran ini? Tahun Ajaran lain akan dinonaktifkan.')">
```

Fix — tambahkan lingkup eksplisit "di lembaga [nama lembaga]" supaya wording BENAR-BENAR mencerminkan perilaku `TahunAjaran::activate()` (`where('lembaga_id', $this->lembaga_id)`, TIDAK lintas lembaga):
```blade
<form action="{{ route('admin.tahun-ajaran.activate', $ta) }}" method="POST" class="inline" onsubmit="return confirm('Aktifkan {{ $ta->nama }}? Tahun Ajaran lain di lembaga {{ $ta->lembaga->nama ?? 'ini' }} akan dinonaktifkan (tidak memengaruhi lembaga lain).')">
```

`$ta->lembaga` sudah eager-loaded (Item 1). Fallback `?? 'ini'` untuk kasus data legacy yang relasi lembaganya sudah terhapus (row `lembaga_id` yatim) — sengaja tidak dianggap error, cukup fallback kata ganti netral.

---

## Item 4 — Konteks Lembaga Tujuan di Modal Tambah/Edit

**File**: `resources/views/admin/tahun-ajaran/_modal-tahun-ajaran.blade.php`

Kode saat ini (baris 14-22, header modal):
```blade
<div class="flex items-center justify-between pb-3.5 border-b border-gray-200">
    <h3 class="font-display text-base font-bold text-gray-900 flex items-center gap-2">
        <x-icon name="date_range" class="h-5 w-5 text-brand-500" />
        <span x-text="modalTahunAjaranMode === 'create' ? 'Tambah Tahun Ajaran' : 'Edit Tahun Ajaran'"></span>
    </h3>
    <button @click="showModalTahunAjaran = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
        <x-icon name="cancel" class="h-5 w-5" />
    </button>
</div>
```

Fix — tambahkan baris info lembaga tujuan di bawah judul modal, 3 kondisi berbeda (yayasan-scope tanpa lembaga aktif = akan gagal submit; yayasan-scope dengan lembaga aktif = tampil nama lembaganya; lembaga-scope = tidak perlu ditampilkan sama sekali, sudah jelas dari konteks):
```blade
<div class="flex items-center justify-between pb-3.5 border-b border-gray-200">
    <div>
        <h3 class="font-display text-base font-bold text-gray-900 flex items-center gap-2">
            <x-icon name="date_range" class="h-5 w-5 text-brand-500" />
            <span x-text="modalTahunAjaranMode === 'create' ? 'Tambah Tahun Ajaran' : 'Edit Tahun Ajaran'"></span>
        </h3>
        @if ($isYayasan ?? false)
            <p class="mt-1 pl-7 text-xs {{ ($activeLembaga ?? null) ? 'text-gray-500' : 'text-error-600 font-semibold' }}">
                @if ($activeLembaga ?? null)
                    Untuk lembaga: {{ $activeLembaga->nama }}
                @else
                    Pilih lembaga aktif dulu melalui pengalih lembaga di atas — tidak bisa menambah Tahun Ajaran saat mode "Semua Lembaga".
                @endif
            </p>
        @endif
    </div>
    <button @click="showModalTahunAjaran = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
        <x-icon name="cancel" class="h-5 w-5" />
    </button>
</div>
```

Catatan: pesan peringatan ini HANYA tampil untuk mode `create` maupun `edit` (keduanya pakai modal yang sama) — untuk `edit`, "Untuk lembaga: X" tetap relevan sebagai konfirmasi konteks (TA yang diedit memang milik lembaga aktif, karena kalau bukan, `TahunAjaranController::update()` sudah menolak lewat `TenantScope` sebelum modal ini bisa terbuka — tombol edit hanya muncul untuk kartu yang memang sudah lolos filter tenant). Tombol "Simpan" TIDAK di-disable di spec ini (di luar scope — server-side sudah menolak dengan pesan error yang jelas lewat banner di atas halaman; menambah disable-state client-side murni penambahan UX, bukan perbaikan kejujuran informasi, boleh jadi backlog terpisah kalau user memintanya).

---

## Item 5 — Hapus Halaman Mati `admin.tahun-ajaran.create`

**File 1**: `routes/admin/akademik-master.php` (baris 47)

Hapus baris:
```php
Route::get('tahun-ajaran/create', [TahunAjaranController::class, 'create'])->name('tahun-ajaran.create');
```

**File 2**: `app/Http/Controllers/Admin/TahunAjaranController.php` — hapus method:
```php
public function create(): View
{
    $this->authorize('tahun-ajaran.create');

    return view('admin.tahun-ajaran.create');
}
```
(Permission `tahun-ajaran.create` TETAP DIPERTAHANKAN di seeder — dipakai untuk otorisasi `store()`/`update()`/`@can` di Blade, BUKAN eksklusif untuk method `create()` yang dihapus.)

**File 3**: Hapus file `resources/views/admin/tahun-ajaran/create.blade.php` seluruhnya.

**Verifikasi wajib sebelum hapus**: jalankan `php artisan route:list --name=tahun-ajaran` untuk konfirmasi ulang tidak ada route lain yang bergantung pada nama route ini, dan grep ulang `route('admin.tahun-ajaran.create'` di seluruh `resources/views` DAN `app/` (bukan cuma `resources/views` seperti audit awal) untuk memastikan tidak ada referensi terlewat (mis. dari notifikasi, PDF export, atau seed data).

---

## Di Luar Scope / Backlog Terpisah

1. **`Semester::activate()` scoping cuma `lembaga_id`, tidak ikut `tahun_ajaran_id`** — dicatat saat audit, TAPI bukan bug aktif (aman karena invariant "1 TA aktif per lembaga" dijaga `TahunAjaran::activate()`, dan UI hanya mengizinkan aktivasi semester dari TA yang sedang aktif). Backlog kalau invariant itu nanti berubah.
2. **Disable tombol "Simpan" di modal secara client-side saat mode "Semua Lembaga"** (Item 4) — server-side sudah menolak dengan pesan jelas, penambahan disable-state murni UX tambahan, bukan perbaikan kejujuran informasi.
3. **Rombak `SemesterController::store()`/`activate()` wording dialog** — tidak diaudit di spec ini (scope dibatasi ke menu Tahun Ajaran level TA, bukan Semester secara detail); backlog audit terpisah kalau diperlukan.

---

## Ringkasan Test yang Wajib Ditambahkan/Diperbarui

| Item | Test |
|---|---|
| 1 | Feature test `TahunAjaranControllerTest` (atau file baru kalau belum ada) — assert `assertSee('Semua Lembaga')` untuk yayasan-scope tanpa lembaga aktif; assert `assertSee($lembaga->nama)` untuk yayasan-scope dengan lembaga aktif; assert `assertDontSee` badge untuk lembaga-scope actor |
| 2 | Feature test — yayasan-scope mode "Semua Lembaga" dengan 2 TA nama sama di 2 lembaga berbeda, assert kedua nama lembaga muncul di response HTML; assert label TIDAK muncul saat lembaga sudah dipilih |
| 3 | Assertion sederhana (bukan test JS) — assert string `onsubmit` mengandung nama lembaga saat lembaga ter-eager-load; test existing yang cek redirect/status `activate()` tetap hijau tanpa perubahan |
| 4 | Feature test — assert modal (fragment HTML) mengandung teks lembaga aktif atau pesan peringatan sesuai mode |
| 5 | Assert `route('admin.tahun-ajaran.create')` melempar `RouteNotFoundException` (route benar-benar terhapus); regresi: seluruh test `TahunAjaranController`/`SemesterController` existing (`TahunAjaranControllerTest.php`/nama file yang sesuai — cek dulu nama pastinya saat plan ditulis) tetap hijau |

Regresi wajib dijalankan: seluruh test yang menyentuh `TahunAjaranController`, `SemesterController`, dan `admin/tahun-ajaran/*.blade.php` (nama file test dikonfirmasi ulang saat penulisan plan, belum diverifikasi persis di audit ini).
