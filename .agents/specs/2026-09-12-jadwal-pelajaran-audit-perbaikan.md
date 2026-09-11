# Spec: Audit & Perbaikan Modul Jadwal Pelajaran

**Tanggal**: 2026-09-12
**Branch**: `rbac-v2`
**Cakupan file**:
- `resources/views/portals/lembaga/akademik/jadwal-pelajaran/{index,_daftar,_matrix-roster,_modal-form,_modal-duplicate,create,edit}.blade.php`
- `resources/js/jadwal-pelajaran-filter.js`
- `resources/views/components/icon.blade.php` (tambah 1 case baru, TIDAK mengubah case lain)

## 1. Latar Belakang

Modul Jadwal Pelajaran punya fondasi arsitektur yang baik: filter tersinkron ke parameter URL, dual-view (Matriks Roster/Daftar), modal duplikasi antar kelas dengan mekanisme anti-bentrok. Backend (`JadwalPelajaranController`, `DuplicateJadwalAction`, FormRequest) sudah diverifikasi solid — TIDAK ada perubahan domain layer di spec ini. Ditemukan **1 bug fungsional nyata** (bukan preferensi desain: tombol "+ Tambah Slot Jadwal" mengarah ke URL rusak), plus 4 icon rusak, plus beberapa gap UX yang konsisten dengan pola perbaikan yang sudah diterapkan di modul Pola Jam.

**Koreksi terhadap laporan audit awal** (diverifikasi langsung ke kode, bukan diikuti mentah-mentah):
- Laporan menyebut 3 icon rusak (`grid_on`, `format_list_bulleted`, `class`) — setelah cross-check MENYELURUH ke SEMUA file modul ini (bukan cuma yang disebut laporan), ditemukan **4 icon rusak**, ada 1 yang terlewat laporan: **`event_busy`** (dipakai di `_daftar.blade.php` untuk empty state "Belum Ada Jadwal Pelajaran").
- Laporan meminta "tambahkan feedback rincian jumlah slot berhasil vs bentrok" pada modal duplikasi — **SUDAH ADA, ini klaim yang salah**. Dikonfirmasi langsung ke `JadwalPelajaranController::duplicate()` (baris 497-510): backend SUDAH mengembalikan pesan lengkap `"Duplikasi jadwal selesai: {N} sesi berhasil disalin, {M} sesi dilewati (bentrok slot/ruangan/guru)."` lewat JSON `message`, dan `jadwal-pelajaran-filter.js::submitDuplicate()` SUDAH menampilkannya lewat toast (`Alpine.store('toast').push('success', data.message)`). Tidak ada yang perlu dikerjakan untuk poin ini — dikeluarkan dari scope.

## 2. Temuan & Perbaikan

### §2.1 [KRITIS] Tombol "+ Tambah Slot Jadwal" Mengarah ke URL Rusak (`/undefined?...`)

**Diverifikasi langsung**: `index.blade.php:30` MENGIRIM `createUrlBase: @js(route('admin.jadwal-pelajaran.create'))` ke `jadwalPelajaranFilter(config)`, TAPI `jadwal-pelajaran-filter.js:5-10` (objek state yang di-return) **tidak pernah menyimpan `config.createUrlBase` ke `this.createUrlBase`** — hanya `opsiUrl`, `indexUrlBase`, `storeUrlBase` yang diinisialisasi. Method `tambahSlotUrl()` (baris 390-395) memakai `this.createUrlBase` yang bernilai `undefined`, sehingga `new URL(undefined, window.location.origin)` menghasilkan URL literal `.../undefined?kelas_id=...&semester_id=...`.

**Current** (`resources/js/jadwal-pelajaran-filter.js:5-10`):
```js
export function jadwalPelajaranFilter(config) {
    return {
        tahunAjaranId: config.tahunAjaranId ?? '',
        kelasId: config.kelasId ?? '',
        semesterId: config.semesterId ?? '',
        opsiUrl: config.opsiUrl,
        indexUrlBase: config.indexUrlBase,
        storeUrlBase: config.storeUrlBase ?? '',
```

**Fix**:
```js
export function jadwalPelajaranFilter(config) {
    return {
        tahunAjaranId: config.tahunAjaranId ?? '',
        kelasId: config.kelasId ?? '',
        semesterId: config.semesterId ?? '',
        opsiUrl: config.opsiUrl,
        indexUrlBase: config.indexUrlBase,
        createUrlBase: config.createUrlBase ?? '',
        storeUrlBase: config.storeUrlBase ?? '',
```
(hanya 1 baris ditambahkan, tidak ada baris lain di block ini yang berubah)

---

### §2.2 [KRITIS] Aksi Hapus Slot Masih Reload Seluruh Halaman

**Diverifikasi langsung**: `JadwalPelajaranController::destroy()` (baris 444-461) **SUDAH mendukung JSON response** (`if ($request->ajax() || $request->wantsJson()) { return response()->json(...); }`) — backend TIDAK PERLU diubah. Yang kurang murni di frontend: `_daftar.blade.php:118` dan `_matrix-roster.blade.php:100` masih pakai `<form>` submit reguler (`$el.submit()`), sehingga full-page reload walau backend sudah siap AJAX.

**Current** (`_daftar.blade.php:118-122`, pola identik juga di `_matrix-roster.blade.php:100-107`):
```blade
<form method="POST" action="{{ route('admin.jadwal-pelajaran.destroy', $jadwal) }}" x-data @submit.prevent="confirmDialog('Hapus Jadwal?', @js('Apakah Anda yakin ingin menghapus jadwal ' . ($jadwal->mataPelajaran?->nama ?? 'ini') . ' oleh ' . $jadwal->guru->nama . '?'), { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })">
    @csrf
    @method('DELETE')
    <button type="submit" class="text-xs font-semibold text-error-500 hover:text-error-700 transition-colors">Hapus</button>
</form>
```

**Fix**: tambah method `hapusJadwal(url, label)` di `jadwal-pelajaran-filter.js` (sisipkan setelah method `submitDuplicate()`, sebelum `initTahunAjaranSelect()`):
```js
async hapusJadwal(url, label) {
    const konfirmasi = await confirmDialog('Hapus Jadwal?', `Apakah Anda yakin ingin menghapus jadwal ${label}?`, { confirmLabel: 'Ya, Hapus' });
    if (!konfirmasi) return;

    try {
        window.dispatchEvent(new CustomEvent('ajax-start'));
        const response = await fetch(url, {
            method: 'POST',
            body: (() => { const fd = new FormData(); fd.append('_method', 'DELETE'); fd.append('_token', document.querySelector('meta[name="csrf-token"]')?.content ?? ''); return fd; })(),
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const data = await response.json().catch(() => ({}));
        if (response.ok) {
            Alpine.store('toast').push('success', data.message || 'Jadwal berhasil dihapus.');
            await this.muatUlangDaftar();
        } else {
            Alpine.store('toast').push('error', data.message || 'Gagal menghapus jadwal.');
        }
    } catch (e) {
        Alpine.store('toast').push('error', 'Terjadi kesalahan jaringan saat menghapus jadwal.');
    } finally {
        window.dispatchEvent(new CustomEvent('ajax-end'));
    }
},
```
**Catatan CSRF**: fetch dengan `method: 'POST'` + `_method=DELETE` di body (method spoofing Laravel) TIDAK otomatis membawa CSRF token seperti `<form>` asli (yang punya `@csrf` sebagai hidden input) — makanya token diambil manual dari `<meta name="csrf-token">` yang SUDAH ada di layout utama app (`layouts.app`/`x-app-layout` — konvensi standar Laravel starter kit, dipakai project ini). JANGAN skip baris `fd.append('_token', ...)` ini, tanpanya request akan ditolak 419.

Ganti pemakaian di `_daftar.blade.php:118-122`:
```blade
<button type="button" @click="hapusJadwal('{{ route('admin.jadwal-pelajaran.destroy', $jadwal) }}', @js(($jadwal->mataPelajaran?->nama ?? 'ini') . ' oleh ' . $jadwal->guru->nama)); " class="text-xs font-semibold text-error-500 hover:text-error-700 transition-colors">Hapus</button>
```
(hapus `<form>...</form>` pembungkusnya sepenuhnya — cukup `<button>` mandiri, TIDAK perlu `@csrf`/`@method` lagi karena sudah ditangani di JS.)

Ganti pemakaian identik di `_matrix-roster.blade.php:100-107` (tombol Hapus di kartu terisi):
```blade
<button type="button" @click="hapusJadwal('{{ route('admin.jadwal-pelajaran.destroy', $jadwal) }}', @js(($jadwal->mataPelajaran?->nama ?? 'ini') . ' oleh ' . $jadwal->guru->nama))" class="inline-flex items-center gap-1 text-[11px] font-bold text-error-500 hover:text-error-700 transition">
    <x-icon name="delete" class="h-3.5 w-3.5" />
    <span>Hapus</span>
</button>
```

---

### §2.3 4 Icon Rusak (Termasuk 1 yang Terlewat Laporan Awal)

**Diverifikasi via grep menyeluruh ke SEMUA file modul** (`index.blade.php`, `_daftar.blade.php`, `_matrix-roster.blade.php`, `_modal-form.blade.php`, `_modal-duplicate.blade.php`), dicocokkan ke `@case` yang ada di `icon.blade.php`:

| Dipakai di | Nama icon rusak | Baris |
|---|---|---|
| `_daftar.blade.php`, toggle "Matriks Roster" | `grid_on` | 21 |
| `_daftar.blade.php`, toggle "Tampilan Daftar" | `format_list_bulleted` | 30 |
| `_daftar.blade.php`, empty state "Belum Ada Jadwal Pelajaran" | `event_busy` | 53 |
| `_modal-form.blade.php`, badge konteks kelas | `class` | 30 |

(`content_copy` yang dipakai di `index.blade.php:48` dan `_modal-duplicate.blade.php:17` **SUDAH VALID** — case-nya ditambahkan sebagai efek samping siklus perbaikan Pola Jam sebelumnya di sesi ini, TIDAK perlu dikerjakan ulang.)

**Fix**:
- `grid_on` → `data_table` (sudah ada, cocok representasi tabel/matriks — konsisten dengan penggantian `grid_view`→`data_table` di siklus Pola Jam)
- `format_list_bulleted` → `list` (sudah ada — ditambahkan sebagai case baru di siklus Pola Jam sebelumnya untuk kebutuhan serupa)
- `event_busy` → **TIDAK ADA padanan semantik yang cocok** di daftar icon yang ada (icon lain yang mendekati, `event`, punya makna berbeda — "kalender/acara", bukan "kosong/tidak ada acara"). Tambah 1 case baru ke `resources/views/components/icon.blade.php`, ikuti gaya SVG yang sama (viewBox 24x24, stroke-width 1.8):
```blade
    @case('event_busy')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/><path d="m9.5 13.5 5 5m0-5-5 5"/></svg>
        @break

```
- `class` → `school` (sudah ada, konsisten dengan penggantian yang sama di siklus Pola Jam)

---

### §2.4 Redundansi Teks "Jadwal Pelajaran Kelas Kelas 1-A"

**Diverifikasi**: nama kelas di database SUDAH diawali kata "Kelas" (konvensi penamaan seed data, cth. "Kelas 1-A"), sehingga judul `"Jadwal Pelajaran Kelas {{ $kelas->nama }}"` menghasilkan duplikasi kata.

**Current** (`_daftar.blade.php:6`):
```blade
<h2 class="font-display text-base font-bold text-gray-900">Jadwal Pelajaran Kelas {{ $kelas->nama ?? '' }}</h2>
```

**Fix**:
```blade
<h2 class="font-display text-base font-bold text-gray-900">Jadwal Pelajaran — {{ $kelas->nama ?? '' }}</h2>
```

---

### §2.5 Filter: Semester ke TomSelect, Tombol Reset Filter, Badge Scope Lembaga, Breadcrumb Konsisten

**Current** (`index.blade.php:11-20, 71-79`): filter Semester pakai `<select>` HTML polos (Tahun Ajaran & Kelas sudah TomSelect), tidak ada tombol reset, tidak ada badge lembaga untuk aktor yayasan, breadcrumb "Beranda" (Pola Jam pakai "Akademik").

**Diverifikasi**: `JadwalPelajaranController.php:51-60` SUDAH punya method `scopeHeaderData(Request $request): array` (persis pola yang sama dengan `PolaJamController`/`KurikulumAssignmentController`), mengembalikan `['isYayasan' => bool, 'activeLembaga' => ?Lembaga]`, dan SUDAH di-spread (`...$this->scopeHeaderData($request)`) ke kedua array data view `index()` (baris 127 untuk cabang AJAX, baris 144 untuk cabang non-AJAX). Variabel `$isYayasan`/`$activeLembaga` SUDAH TERSEDIA di `index.blade.php` — TIDAK ADA perubahan controller yang dibutuhkan untuk badge ini, murni tambahan di Blade.

**Fix — Header & Breadcrumb** (ganti `index.blade.php:11-20`, TANPA menyentuh controller sama sekali — `isYayasan`/`activeLembaga` sudah tersedia):
```blade
{{-- Header & Breadcrumb --}}
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <div class="flex flex-wrap items-center gap-2.5">
            <h1 class="font-display text-lg font-bold text-gray-900">Jadwal Pelajaran</h1>
            @if ($isYayasan ?? false)
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
                    <x-icon name="apartment" class="h-3.5 w-3.5" />
                    {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
                </span>
            @endif
        </div>
        <p class="text-xs text-gray-500 mt-0.5">Kelola penomoran slot belajar, mata pelajaran, dan pengampu untuk tiap kelas.</p>
    </div>
    <p class="text-sm text-gray-500">
        Akademik <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Jadwal Pelajaran</b>
    </p>
</div>
```
**Fix — Filter Semester jadi TomSelect + Tombol Reset** (ganti `index.blade.php:58-90`):
```blade
<div class="flex items-center justify-between border-b border-gray-100 pb-2">
    <div></div>
    <template x-if="tahunAjaranId || kelasId || semesterId">
        <button type="button" @click="resetFilter()" class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-600 hover:text-brand-800 transition">
            <x-icon name="close" class="h-3.5 w-3.5" />
            Reset Filter
        </button>
    </template>
</div>
<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div>
        <x-input-label value="Tahun Ajaran" />
        <select x-ref="tahunAjaranSelect" x-init="initTahunAjaranSelect($refs.tahunAjaranSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm">
            <option value="">— Pilih Tahun Ajaran —</option>
            @foreach ($tahunAjaranList as $tahunAjaran)
                <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>
                    {{ $tahunAjaran->nama }}{{ $tahunAjaran->status_aktif ? ' (Aktif)' : '' }}{{ ($isYayasan ?? false) && ! ($activeLembaga ?? null) ? ' — '.($tahunAjaran->lembaga->nama ?? '-') : '' }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-input-label value="Semester" />
        <select x-ref="semesterSelect" x-init="initSemesterSelect($refs.semesterSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm">
            <option value="">— Pilih Semester —</option>
            @foreach ($semesterList as $semester)
                <option value="{{ $semester->id }}" @selected($semesterId == $semester->id)>{{ $semester->nama }}{{ $semester->status_aktif ? ' (Aktif)' : '' }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <x-input-label value="Kelas" />
        <select x-ref="kelasSelect" x-init="initKelasSelect($refs.kelasSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm">
            <option value="">— Pilih Kelas —</option>
            @foreach ($kelasList as $kelas)
                <option value="{{ $kelas->id }}" @selected($kelasId == $kelas->id)>{{ $kelas->nama }}</option>
            @endforeach
        </select>
    </div>
</div>
```

**Fix `jadwal-pelajaran-filter.js`** — tambah method `initSemesterSelect()` dan `resetFilter()`, DAN simpan instance TomSelect Tahun Ajaran ke `this` (dibutuhkan `resetFilter()` untuk clear tampilan visualnya, saat ini `initTahunAjaranSelect()` TIDAK menyimpan instance-nya ke `this`, hanya lokal):

Ganti `initTahunAjaranSelect()` (baris 176-186):
```js
initTahunAjaranSelect(el) {
    this.tahunAjaranTomSelect = new TomSelect(el, {
        maxItems: 1,
        create: false,
        placeholder: 'Cari tahun ajaran...',
        onChange: (value) => {
            this.tahunAjaranId = value;
            this.gantiTahunAjaran(value);
        },
    });
},

initSemesterSelect(el) {
    this.semesterTomSelect = new TomSelect(el, {
        maxItems: 1,
        create: false,
        placeholder: 'Cari semester...',
        onChange: (value) => {
            this.semesterId = value;
            this.muatUlangDaftar();
        },
    });
},

resetFilter() {
    this.tahunAjaranTomSelect?.clear(true);
    this.tahunAjaranId = '';
    this.gantiTahunAjaran('');
},
```
Tambah state awal `tahunAjaranTomSelect: null, semesterTomSelect: null,` di objek `return { ... }` (sejajar dengan `kelasTomSelect: null,` yang sudah ada di baris 11).

**Catatan penting soal `semesterTomSelect` dan `gantiTahunAjaran()`**: method `gantiTahunAjaran()` yang SUDAH ADA (baris 311+) memanipulasi `this.$refs.semesterSelect.innerHTML` langsung (menambah `<option>` via `appendChild`) SETIAP KALI tahun ajaran berganti — ini valid untuk `<select>` native, TAPI SETELAH `semesterSelect` dikelola TomSelect, manipulasi `innerHTML`/`appendChild` langsung pada elemen `<select>` yang sudah di-hijack TomSelect TIDAK akan tersinkron ke tampilan TomSelect (TomSelect membungkus elemen asli dan tidak "melihat" perubahan DOM manual). **Task/plan WAJIB menyesuaikan bagian `gantiTahunAjaran()` yang mengisi opsi semester** — ganti `appendChild` manual jadi `this.semesterTomSelect.addOption(...)` + `this.semesterTomSelect.refreshOptions(false)` + `this.semesterTomSelect.clear(true)` (pola PERSIS yang sudah dipakai `initKelasSelect`/`gantiTahunAjaran` untuk `kelasTomSelect` di baris 330-333, TINGGAL DITIRU untuk semester). Ini BUKAN detail sepele — kalau terlewat, filter Semester akan terlihat kosong walau opsi sebenarnya sudah ter-inject ke DOM, karena TomSelect tidak tahu opsi barunya.

---

### §2.6 KPI Cards Ringkas

**Fix** — tambah 2 KPI di `_daftar.blade.php`, dihitung dari `$jadwalList` yang SUDAH di-fetch (TANPA query tambahan — perlu verifikasi controller SUDAH eager-load relasi yang cukup untuk `pluck` ini, kalau belum, tambahkan eager-load di titik yang SAMA seperti data lain yang sudah di-fetch, JANGAN N+1 query per baris):
```blade
{{-- sisipkan SETELAH blok header card (index.blade.php:3-10 versi _daftar.blade.php), SEBELUM blok view-toggle --}}
@php
    $totalMapel = $jadwalList->pluck('mata_pelajaran_id')->filter()->unique()->count();
    $totalGuru = $jadwalList->pluck('guru_id')->unique()->count();
@endphp
<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
    <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Mata Pelajaran Aktif</p>
        <p class="font-display text-lg font-bold text-gray-900">{{ $totalMapel }}</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Guru Pengampu Terlibat</p>
        <p class="font-display text-lg font-bold text-gray-900">{{ $totalGuru }}</p>
    </div>
</div>
```
**Dikeluarkan dari usulan laporan audit**: KPI "Total Sesi Belajar (X/Y slot terisi)" dan "Pola Jam Kelas" — lihat §4 alasannya.

---

### §2.7 Matriks: Kunci Slot Non-Pelajaran, Keseragaman Tinggi Kartu

**Current** (`_matrix-roster.blade.php:112-129`): SEMUA slot kosong (termasuk yang `is_pelajaran = false`, mis. Istirahat/Upacara dari Pola Jam) mendapat dropzone `+ Isi Jadwal` yang bisa diklik, berisiko admin salah menjadwalkan mata pelajaran di jam istirahat.

**Fix** — tambahkan pengecekan `$slot->is_pelajaran` SEBELUM merender dropzone kosong:
```blade
{{-- ganti baris 111-130, tambahkan 1 cabang baru sebelum baris "Empty Slot Dropzone" --}}
@else
    @if (! $slot->is_pelajaran)
        {{-- Slot non-pelajaran (Istirahat/Upacara dari Pola Jam) -- tidak bisa diisi mata pelajaran --}}
        <div class="flex flex-col items-center justify-center text-center rounded-2xl border border-dashed border-amber-200 bg-amber-50/40 p-4 h-full min-h-[140px] text-amber-600">
            <span class="text-xs font-mono font-semibold">{{ $waktuMulai }}–{{ $waktuSelesai }}</span>
            <span class="text-xs font-bold mt-1">{{ $slot->label }}</span>
        </div>
    @else
        {{-- Empty Slot Dropzone --}}
        @can('jadwal-pelajaran.kelola')
            <div @click="openCreateModal({ jam_ids: [{{ $slot->id }}] })" class="group flex flex-col items-center justify-center text-center rounded-2xl border-2 border-dashed border-gray-200 hover:border-brand-400 bg-gray-50/40 hover:bg-brand-50/30 p-4 transition-all duration-200 cursor-pointer h-full min-h-[140px]">
                <span class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200/60 bg-white/80 px-2.5 py-1 text-xs font-mono font-bold text-gray-500 group-hover:text-brand-600 group-hover:border-brand-200 mb-2">
                    <x-icon name="schedule" class="h-3.5 w-3.5 opacity-60" />
                    <span>{{ $waktuMulai }}–{{ $waktuSelesai }}</span>
                </span>
                <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-white shadow-xs border border-gray-200 group-hover:border-brand-300 group-hover:bg-brand-50 text-gray-400 group-hover:text-brand-600 transition-all mb-1 group-hover:scale-105">
                    <x-icon name="add" class="h-4 w-4" />
                </span>
                <span class="text-xs font-bold text-gray-400 group-hover:text-brand-700 transition-colors">+ Isi Jadwal</span>
            </div>
        @else
            <div class="flex flex-col items-center justify-center text-center rounded-2xl border border-dashed border-gray-200 bg-gray-50/20 p-4 h-full min-h-[140px] text-gray-400">
                <span class="text-xs font-mono font-semibold text-gray-500">{{ $waktuMulai }}–{{ $waktuSelesai }}</span>
                <span class="text-xs font-medium text-gray-400 mt-1">Kosong</span>
            </div>
        @endcan
    @endif
@endif
```
**Keseragaman tinggi kartu**: SUDAH DITERAPKAN di kode saat ini (`min-h-[140px]` konsisten dipakai di kartu terisi baris 62 DAN semua varian dropzone kosong baris 114/125/132) — klaim laporan audit soal ini TIDAK akurat, tidak ada perubahan tambahan diperlukan.

**Dikeluarkan dari scope**: color-coding/aksen warna per mata pelajaran — lihat §4 alasannya.

---

### §2.8 Tooltip Tambahan & Konteks

**Fix** — tambah `<x-tooltip>` (komponen sudah ada, dipakai persis dengan pola dari siklus Pola Jam/Kurikulum Assignment) di 2 titik:

`index.blade.php:43-50`, bungkus tombol "Salin dari Kelas Lain":
```blade
<x-tooltip text="Salin susunan mata pelajaran, guru, dan ruangan dari kelas lain yang jadwalnya sudah diatur.">
    <button
        type="button"
        @click="openDuplicateModal()"
        class="inline-flex items-center gap-1.5 rounded-xl bg-white px-3.5 py-2.5 text-xs font-semibold text-gray-700 shadow-sm border border-gray-200 hover:bg-gray-50 transition-colors"
    >
        <x-icon name="content_copy" class="h-4 w-4 text-gray-500" />
        <span>Salin dari Kelas Lain</span>
    </button>
</x-tooltip>
```

`_modal-duplicate.blade.php:45-49`, bungkus badge "Mekanisme Anti-Bentrok Proaktif" (badge span teks, bukan tombol — pakai versi `<x-tooltip>` non-interaktif default, TANPA prop `as-button`):
```blade
<x-tooltip text="Slot yang sudah terisi di kelas tujuan, atau guru yang sudah mengajar di jam yang sama di kelas lain, otomatis dilewati tanpa menimpa data yang ada.">
    <div class="rounded-xl bg-brand-50/70 p-3.5 border border-brand-100 text-xs space-y-1 text-brand-900 cursor-help">
        <div class="flex items-center gap-1.5 font-semibold text-brand-700">
            <x-icon name="info" class="h-4 w-4 shrink-0 text-brand-500" />
            <span>Mekanisme Anti-Bentrok Proaktif</span>
        </div>
        <p class="text-brand-800/80 leading-relaxed">
            Sistem akan secara otomatis melepaskan slot yang bertentangan (jika slot sudah diisi di kelas tujuan, atau guru sudah mengajar di kelas lain pada jam tersebut).
        </p>
    </div>
</x-tooltip>
```

---

### §2.9 `create.blade.php` & `edit.blade.php` — Breadcrumb Konsisten

**Current** (`create.blade.php:13-17`, pola serupa harus dicek juga di `edit.blade.php` — implementer WAJIB baca `edit.blade.php` langsung untuk memastikan strukturnya sama sebelum menerapkan fix yang sama, JANGAN diasumsikan identik tanpa verifikasi):
```blade
<p class="text-sm text-gray-500">
    Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
    <a href="{{ route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $semesterId]) }}" class="font-semibold text-gray-700 hover:text-brand-600">Jadwal Pelajaran</a>
    <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Tambah</b>
</p>
```

**Fix** (ganti kata "Beranda" jadi "Akademik", SATU kata saja, tidak ada perubahan lain di blok ini):
```blade
<p class="text-sm text-gray-500">
    Akademik <span class="mx-1 text-gray-300">&rsaquo;</span>
    <a href="{{ route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $semesterId]) }}" class="font-semibold text-gray-700 hover:text-brand-600">Jadwal Pelajaran</a>
    <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Tambah</b>
</p>
```
Terapkan perubahan kata "Beranda"→"Akademik" yang SAMA di breadcrumb `edit.blade.php` (label terakhir "Edit" bukan "Tambah", sisanya sama).

---

## 3. Ringkasan Perubahan File

| File | Jenis Perubahan |
|---|---|
| `resources/js/jadwal-pelajaran-filter.js` | Fix `createUrlBase`, tambah `hapusJadwal()`, `initSemesterSelect()`, `resetFilter()`, ubah `gantiTahunAjaran()` bagian pengisian opsi semester supaya kompatibel TomSelect |
| `resources/views/components/icon.blade.php` | Tambah 1 `@case('event_busy')` baru |
| `resources/views/portals/lembaga/akademik/jadwal-pelajaran/index.blade.php` | Badge scope lembaga, breadcrumb "Akademik", filter Semester jadi TomSelect, tombol Reset Filter, tooltip tombol Salin |
| `resources/views/portals/lembaga/akademik/jadwal-pelajaran/_daftar.blade.php` | Icon fix (`grid_on`→`data_table`, `format_list_bulleted`→`list`, `event_busy` valid), teks "Kelas Kelas" fix, AJAX hapus, KPI cards |
| `resources/views/portals/lembaga/akademik/jadwal-pelajaran/_matrix-roster.blade.php` | AJAX hapus, kunci slot non-pelajaran |
| `resources/views/portals/lembaga/akademik/jadwal-pelajaran/_modal-form.blade.php` | Icon fix (`class`→`school`) |
| `resources/views/portals/lembaga/akademik/jadwal-pelajaran/_modal-duplicate.blade.php` | Tooltip badge anti-bentrok |
| `resources/views/portals/lembaga/akademik/jadwal-pelajaran/create.blade.php` | Breadcrumb "Akademik" |
| `resources/views/portals/lembaga/akademik/jadwal-pelajaran/edit.blade.php` | Breadcrumb "Akademik" |
| `app/Http/Controllers/Admin/JadwalPelajaranController.php` | **TIDAK diubah sama sekali** — `scopeHeaderData()` sudah ada dan sudah dikirim ke `index()`, dikonfirmasi langsung |

## 4. Di Luar Cakupan (Sengaja Tidak Dikerjakan)

- **"Feedback hasil duplikasi yang rinci"** (usul laporan audit) — SUDAH ADA, backend dan frontend sudah lengkap mengembalikan+menampilkan jumlah berhasil/dilewati. Klaim laporan audit awal soal ini SALAH, dikoreksi di §1.
- **Preview jumlah sesi kelas sumber saat dipilih di modal duplikasi** ("Kelas Sumber memiliki X sesi yang siap disalin") — butuh endpoint/query tambahan (hitung jadwal per kelas-semester secara live saat dropdown berubah), disproporsional untuk siklus UI ini. Kalau dibutuhkan, sebaiknya jadi endpoint kecil terpisah yang dipikirkan sendiri (payload apa yang dikembalikan, caching, dsb), bukan ditempel sebagai quick-win.
- **KPI "Total Sesi Belajar (X/Y slot terisi)"** — butuh menghitung total slot TERSEDIA dari Pola Jam (bukan cuma yang terisi), yang berarti join/query tambahan ke `JamPelajaran` per kelas — di luar "tanpa query tambahan" yang jadi prinsip KPI ringan di spec ini (§2.6 hanya pakai `$jadwalList` yang sudah ada).
- **KPI "Pola Jam Kelas"** (nama pola jam yang tertaut) — data ini TIDAK ada di `$jadwalList` (list JadwalPelajaran), perlu query terpisah ke relasi `Kelas -> PolaJam`. Di luar prinsip yang sama seperti di atas.
- **Color-coding/aksen warna chip per mata pelajaran di Matriks** — definisi bisnis "kategori mapel" untuk pewarnaan tidak jelas (berdasarkan apa: nama mapel di-hash? kelompok mapel? kurikulum?), dan berisiko jadi keputusan desain sepihak yang subjektif tanpa masukan lebih lanjut. Kalau memang diinginkan, perlu didiskusikan dulu skema pewarnaannya secara eksplisit sebelum diimplementasikan, bukan diasumsikan di sini.
- **Badge "Ruang Bersama" eksplisit di dropdown Ruangan Sarpras** — **koreksi**: kolom `is_shared` TERNYATA SUDAH ADA di model `Ruangan` (dipakai `JadwalPelajaranController::index()` baris ~103, query `->orWhere('is_shared', true)` untuk mengumpulkan ruangan lintas-lembaga yang statusnya "bersama"). Jadi data-nya SUDAH TERSEDIA, bukan tidak ada seperti dugaan awal — TAPI tetap dikeluarkan dari scope spec ini karena murni polish kosmetik minor (info "Kapasitas: X" sudah cukup membantu, badge tambahan tidak signifikan dibanding item lain), bukan karena keterbatasan data. Kalau user memang menginginkan badge ini secara eksplisit di siklus berikutnya, tinggal tambah `@if ($ruangan->is_shared) <span class="...">Ruang Bersama</span> @endif` di opsi dropdown `_modal-form.blade.php`/`create.blade.php` — kode 1 baris, sengaja tidak dimasukkan sekarang murni soal prioritas bukan soal teknis.
- **Perubahan `JadwalPelajaranController.php`** — TIDAK ADA perubahan yang direncanakan, KECUALI temuan prasyarat di §2.5 (kalau ternyata `isYayasan`/`activeLembaga` belum dikirim sama sekali, itu satu-satunya izin sentuh controller).
- **Perubahan `DuplicateJadwalAction`/domain layer manapun** — sudah diverifikasi solid, di luar scope.

## 5. Self-Review (5 Putaran)

**Putaran 1 — Verifikasi klaim laporan audit vs kode asli**: SEMUA klaim utama diverifikasi lewat pembacaan kode langsung (bukan percaya laporan): bug `createUrlBase` (akurat), 3 icon yang disebut laporan (akurat), teks "Kelas Kelas" (akurat). Ditemukan 1 icon TAMBAHAN yang terlewat laporan (`event_busy`, di §2.3) via grep menyeluruh ke SEMUA file, bukan cuma yang disebut laporan. Ditemukan 1 klaim SALAH TOTAL yang dikoreksi (§1, §4): "feedback duplikasi rinci" ternyata SUDAH ADA end-to-end (backend+frontend), diverifikasi baca `JadwalPelajaranController::duplicate()` baris 497-510 dan `submitDuplicate()` di JS.

**Putaran 2 — Cakupan temuan vs perbaikan**: Semua bagian laporan audit (bug URL, reload hapus, icon, redundansi teks, inkonsistensi filter, KPI, matriks, modal form, modal duplicate, create/edit) punya bagian §2.x yang eksplisit menjawabnya, termasuk yang DIKOREKSI (duplikasi feedback) atau DIPERSEMPIT (KPI jadi 2 bukan 4, alasan di §4).

**Putaran 3 — Konsistensi kode vs current state**: Re-cek nomor baris untuk `index.blade.php`, `_daftar.blade.php`, `_matrix-roster.blade.php`, `_modal-form.blade.php`, `_modal-duplicate.blade.php`, `jadwal-pelajaran-filter.js`, `create.blade.php` — semua dibaca langsung dari file per 2026-09-12, cocok. `edit.blade.php` SENGAJA TIDAK dikutip kode current-nya secara lengkap (hanya pola serupa `create.blade.php` diasumsikan) — dicatat eksplisit di §2.9 bahwa implementer WAJIB verifikasi sendiri sebelum menerapkan, BUKAN kelalaian.

**Putaran 4 — Keamanan/regresi**: Perubahan §2.1 murni 1 baris JS (assignment state). §2.2 (AJAX hapus) memakai backend yang SUDAH mendukung JSON — TIDAK ADA perubahan otorisasi/validasi, HANYA cara request dikirim (fetch vs form submit) — dicatat eksplisit soal kebutuhan CSRF token manual via meta tag karena fetch+FormData tanpa `@csrf` hidden input TIDAK otomatis membawa token seperti form asli (ini detail yang gampang terlewat, gagal CSRF akan muncul sebagai 419, bukan silent failure — cukup aman untuk dites langsung). §2.5 (TomSelect Semester) diberi CATATAN PENTING eksplisit soal `gantiTahunAjaran()` yang WAJIB disesuaikan (manipulasi DOM manual tidak sinkron ke TomSelect) — ini potensi bug REGRESI yang HARUS diantisipasi di plan, bukan ditemukan belakangan saat testing.

**Putaran 5 — Placeholder scan & verifikasi lanjutan pasca-draf**: Scan ulang §2.1-§2.9 untuk pola red-flag ("TODO", "seperti biasa") — tidak ditemukan. Draf awal §2.5 sempat menyisakan "prasyarat verifikasi" (`isYayasan`/`activeLembaga` belum dipastikan ada di controller) — SETELAH dicek langsung ke `JadwalPelajaranController.php` baris 51-60 & 127/144, ternyata `scopeHeaderData()` SUDAH ADA dan SUDAH dikirim ke kedua cabang view `index()` — prasyarat itu dihapus, diganti kepastian "TIDAK ADA perubahan controller dibutuhkan". Draf awal §4 juga sempat salah menyebut kolom `is_shared` "tidak ada di model Ruangan" — dicek ulang ke query `index()` yang SUDAH memakainya (`->orWhere('is_shared', true)`), dikoreksi jadi "data ada, dikeluarkan murni soal prioritas". §2.9 soal struktur `edit.blade.php` TETAP dibiarkan sebagai instruksi verifikasi eksplisit untuk implementer (bukan placeholder) karena file itu memang belum dibaca penuh saat spec ini ditulis — beda kasus dari 2 koreksi di atas yang SUDAH terverifikasi tuntas.
