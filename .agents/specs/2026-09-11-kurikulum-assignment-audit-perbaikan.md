# Spec: Audit & Perbaikan Modul Assignment Kurikulum

**Tanggal**: 2026-09-11
**Branch**: `rbac-v2`
**Cakupan file**:
- `resources/views/admin/kurikulum-assignment/{index,create,edit,_form,resync}.blade.php`
- `app/Http/Controllers/Admin/{KurikulumAssignmentController,ResyncKurikulumFaseController}.php`
- `app/Domains/Akademik/Actions/Kelas/ResyncKurikulumFaseKelasAction.php`
- Baru: `resources/views/admin/kurikulum-assignment/_daftar.blade.php`

## 1. Latar Belakang

Modul Assignment Kurikulum adalah rule engine yang dipakai `CreateKelasAction`/`KurikulumAssignmentResolver` untuk menentukan kurikulum+fase default kelas baru, dengan hirarki fallback 3 tingkat (spesifik tingkat → catch-all lembaga → platform default). Backend/domain layer (resolver, action, form-request validation) sudah solid dan sudah diverifikasi lewat pembacaan kode langsung — TIDAK ada bug keamanan/data-integrity yang ditemukan. Masalah yang ada murni di lapisan UI/UX dan satu celah UX-yang-berisiko-jadi-bug (lihat §2.1).

Spec ini merangkum temuan dari audit UI/UX menyeluruh dan memutuskan cakupan implementasi final (kritis + polish digabung dalam satu siklus, sesuai keputusan user).

## 2. Temuan & Perbaikan

### §2.1 [KRITIS] Input `tingkat` rawan salah ketik → jadi pill selector

**Fakta penting yang mengoreksi asumsi awal audit**: backend SUDAH memvalidasi `tingkat` terhadap `BentukPendidikan::validTingkatValues()` di `StoreKurikulumAssignmentRequest`/`UpdateKurikulumAssignmentRequest::withValidator()` — jadi input yang salah ketik TIDAK diam-diam lolos ke database, melainkan menghasilkan error validasi. Tidak ada resiko *data corruption*/*silent failure* seperti dugaan awal. Yang ada murni **UX buruk**: admin harus coba-coba mengetik lalu bertemu error, alih-alih dipandu memilih dari opsi valid sejak awal.

`app/Domains/Akademik/Enums/BentukPendidikan.php:35-43` (TIDAK diubah, hanya dipakai sebagai source of truth):
```php
public function validTingkatValues(): array
{
    return match ($this) {
        self::Kb, self::Tpa, self::Sps, self::Tk => ['A', 'B'],
        self::Sd, self::Slb => ['1', '2', '3', '4', '5', '6'],
        self::Smp => ['7', '8', '9'],
        self::Sma, self::Smk => ['10', '11', '12'],
    };
}
```

**Current** (`_form.blade.php:81-85`):
```blade
<div class="sm:col-span-6">
    <x-input-label value="Tingkat (kosongkan = berlaku semua tingkat)" />
    <x-text-input type="text" name="tingkat" value="{{ $val('tingkat') }}" placeholder="Contoh: 1, 10, A (kosongkan utk catch-all)" class="mt-1.5 w-full" />
    <x-input-error :messages="$errors->get('tingkat')" class="mt-1.5" />
</div>
```

**Fix**: ganti jadi toggle "Semua Tingkat" vs "Tingkat Tertentu" + pill button per nilai valid. Nilai valid HARUS reaktif terhadap `bentuk_pendidikan` yang sedang dipilih (mode platform: dropdown bebas; mode non-platform: fixed dari lembaga aktif).

Controller (`KurikulumAssignmentController@create` dan `@edit`) kirim map lengkap sekali saja (murah, cuma 9 bentuk pendidikan):
```php
// tambahkan ke data view create() dan edit():
'tingkatOptionsByBentuk' => collect(BentukPendidikan::cases())
    ->mapWithKeys(fn ($bp) => [$bp->value => $bp->validTingkatValues()]),
```

`_form.blade.php` — tambahkan `x-data` di root form partial (bungkus seluruh isi `<div class="overflow-hidden ...">` yang sudah ada, TIDAK mengubah struktur card):
```blade
@php
    $bentukPendidikanAwal = $isPlatform ?? false
        ? $val('bentuk_pendidikan', $bentukPendidikanList[0]->value)
        : ($lembagaBentukPendidikanUntukPill ?? null);
@endphp
<div
    x-data="{
        bentukPendidikan: @js($bentukPendidikanAwal),
        tingkatOptions: @js($tingkatOptionsByBentuk),
        modeTingkat: @js($val('tingkat') ? 'spesifik' : 'semua'),
        tingkat: @js($val('tingkat')),
        get pillOptions() { return this.tingkatOptions[this.bentukPendidikan] ?? []; },
    }"
    class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
>
```

Ganti blok Bentuk Pendidikan (platform mode, `_form.blade.php:60-69`) supaya set `bentukPendidikan` Alpine saat berubah:
```blade
<select name="bentuk_pendidikan" x-model="bentukPendidikan" @change="modeTingkat = 'semua'; tingkat = ''" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
```
(alasan reset `modeTingkat`/`tingkat` saat ganti jenjang: pilihan pill lama --misal "10"-- tidak valid lagi kalau jenjang berubah ke SD.)

Ganti blok Tingkat (`_form.blade.php:81-85`) jadi:
```blade
<div class="sm:col-span-12">
    <x-input-label value="Tingkat" />
    <div class="mt-1.5 flex flex-wrap gap-2">
        <button type="button" @click="modeTingkat = 'semua'; tingkat = ''"
            :class="modeTingkat === 'semua' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
            class="rounded-full border px-3.5 py-1.5 text-xs font-semibold transition">
            Semua Tingkat (Default Jenjang)
        </button>
        <button type="button" @click="modeTingkat = 'spesifik'"
            :class="modeTingkat === 'spesifik' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
            class="rounded-full border px-3.5 py-1.5 text-xs font-semibold transition">
            Tingkat Tertentu
        </button>
    </div>
    <div x-show="modeTingkat === 'spesifik'" x-cloak class="mt-2.5 flex flex-wrap gap-2">
        <template x-for="opsi in pillOptions" :key="opsi">
            <button type="button" @click="tingkat = opsi"
                :class="tingkat === opsi ? 'border-brand-600 bg-brand-600 text-white' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                class="rounded-lg border px-3.5 py-1.5 text-sm font-semibold transition"
                x-text="opsi"
            ></button>
        </template>
    </div>
    <input type="hidden" name="tingkat" :value="tingkat">
    <x-input-error :messages="$errors->get('tingkat')" class="mt-1.5" />
</div>
```

Untuk mode non-platform (bentuk pendidikan tetap), `_form.blade.php:70-79` sudah punya `$lembagaBentukPendidikan` — assign juga sebagai `$lembagaBentukPendidikanUntukPill` dipakai di `$bentukPendidikanAwal` di atas (tidak perlu blok baru, `bentukPendidikan` Alpine tetap statis karena tidak ada `<select>` yang mengubahnya untuk mode ini).

**Global constraint**: nilai `validTingkatValues()` HARUS diambil dari enum `BentukPendidikan` (satu-satunya source of truth), JANGAN di-hardcode ulang di Blade/JS.

---

### §2.2 [KRITIS] Sinkronisasi massal di halaman resync tanpa konfirmasi

**Current** (`resync.blade.php:36-69`): form `POST admin.kurikulum-assignment.resync.apply` langsung submit tanpa dialog konfirmasi apa pun saat tombol "Sinkronkan yang Dicentang" diklik. Aksi ini mengubah `kurikulum`+`fase_id` kelas terpilih secara permanen (lihat `ResyncKurikulumFaseKelasAction::terapkan()`).

**Fix**: bungkus submit dengan `confirmDialog()` (pola identik dengan tombol hapus assignment di `index.blade.php:78-83`, dan dengan puluhan pemakaian `confirmDialog` lain di app ini). Perlu hitung jumlah kelas tercentang untuk pesan dialog — tambahkan Alpine state kecil di form:

```blade
<form
    method="POST"
    action="{{ route('admin.kurikulum-assignment.resync.apply') }}"
    class="rounded-2xl border border-gray-200 bg-white shadow-sm"
    x-data="{ terpilih: [] }"
    @submit.prevent="confirmDialog(
        'Sinkronkan Kurikulum/Fase Kelas Terpilih?',
        `Kurikulum dan fase pada ${terpilih.length} kelas terpilih akan diperbarui ke aturan terbaru. Pastikan guru dan wali kelas sudah mengetahui perubahan ini sebelum melanjutkan.`,
        { confirmLabel: 'Ya, Sinkronkan Sekarang', isDanger: false }
    ).then(confirmed => { if (confirmed) $el.submit() })"
>
```
Checkbox per baris ganti jadi `x-model="terpilih"` (array of kelas id, value string) alih-alih `class="resync-row"` + `onclick` manual:
```blade
<input type="checkbox" name="kelas_ids[]" value="{{ $row['kelas']->id }}" x-model="terpilih">
```
Checkbox "select all" di header tabel:
```blade
<input type="checkbox" @click="terpilih = $event.target.checked ? @js($diff ? collect($diff)->pluck('kelas.id')->map(fn($v)=>(string)$v)->all() : []) : []">
```
Tombol submit diberi `:disabled="terpilih.length === 0"` supaya tidak submit kosong, dan sticky bottom bar muncul saat `terpilih.length > 0` menampilkan jumlah terpilih (lihat §2.6 untuk detail visual bar ini, satu Alpine state yang sama dipakai untuk keduanya).

---

### §2.3 Index: kontainer, KPI ringkas, filter AJAX, badge hierarki fallback

**Kontainer** (`index.blade.php:2`): ganti `<div class="space-y-4">` → `<div class="mx-auto max-w-6xl space-y-4">` (standar semua halaman admin lain).

**KPI Cards**: TIDAK menambahkan KPI "status drift/keselarasan global" (dikeluarkan dari scope, lihat §4 — mahal untuk dihitung karena butuh loop semua kombinasi lembaga×tahun-ajaran×kelas di index(), padahal index() saat ini murah). Cukup 2 KPI dari data yang SUDAH di-fetch (`$assignmentList`, tanpa query tambahan):
```blade
<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
    <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Aturan</p>
        <p class="font-display text-lg font-bold text-gray-900">{{ $assignmentList->count() }}</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Cakupan</p>
        <p class="font-display text-lg font-bold text-gray-900">
            {{ $assignmentList->contains(fn ($a) => $a->lembaga_id !== null) ? 'Ada Aturan Mandiri' : 'Standar Platform' }}
        </p>
    </div>
</div>
```
(hanya tampil kalau bukan mode "Semua Lembaga" tanpa filter, konsisten dengan pola halaman lain -- boleh selalu tampil, karena datanya tetap valid/bermakna di kedua mode.)

**Filter AJAX** (`dataTableFilter`, pola identik `piket-guru`/`admin/kelas`): pecah tabel jadi partial `_daftar.blade.php`, controller `index()` tambah cabang ajax:
```php
// KurikulumAssignmentController@index, setelah $assignmentList di-build:
if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
    return view('admin.kurikulum-assignment._daftar', ['assignmentList' => $assignmentList]);
}
```
Filter query ditambahkan SEBELUM `$query->orderByDesc(...)->get()`:
```php
if ($tahunAjaranId = $request->query('tahun_ajaran_id')) {
    $query->where('tahun_ajaran_id', $tahunAjaranId);
}
if ($bentukPendidikan = $request->query('bentuk_pendidikan')) {
    $query->where('bentuk_pendidikan', $bentukPendidikan);
}
```
`index.blade.php` bungkus tabel dengan `x-data="dataTableFilter({...})"` dan `x-ref="tableContainer"`, tambah 2 filter select (Tahun Ajaran, Bentuk Pendidikan) — copy pola persis dari `piket-guru/index.blade.php:100-190` (sudah dipakai di sesi ini, tidak perlu didesain ulang).

**Badge hierarki fallback** (`index.blade.php:107`): baris "Semua Tingkat" adalah catch-all, beri label lebih eksplisit:
```blade
{{-- current: {{ $a->tingkat ?? 'Semua Tingkat' }} --}}
@if ($a->tingkat)
    <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">Tingkat {{ $a->tingkat }}</span>
@else
    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">
        <x-icon name="bolt" class="h-3 w-3" />
        Semua Tingkat (Default Jenjang)
    </span>
@endif
```

**Read-only Platform row** (`index.blade.php:94-96`): bungkus teks polos dengan tooltip penjelasan, pakai komponen `<x-tooltip>` yang sudah ada:
```blade
@else
    <x-tooltip text="Dikelola oleh Platform Admin. Buat aturan baru untuk menimpa ini khusus lembaga Anda.">
        <span class="inline-flex items-center gap-1 text-xs text-gray-400">
            <x-icon name="lock" class="h-3.5 w-3.5" />
            Read-only
        </span>
    </x-tooltip>
@endif
```

---

### §2.4 Form Edit: metadata card + callout dampak resync

**Current** (`_form.blade.php:21-24, 54-58, 74-78`): field immutable ditampilkan sebagai `<p>` abu-abu terpisah-pisah di grid, terlihat seperti input disabled yang gagal render, bukan ringkasan metadata.

**Fix** (mode edit saja, `@if ($assignment)`): satu card ringkas berisi 3 badge sejajar, ganti section "Berlaku Untuk" + "Tahun Ajaran" + "Bentuk Pendidikan" (yang immutable) jadi satu blok di awal `_form.blade.php`, SEBELUM grid form yang bisa diedit:
```blade
@if ($assignment)
    <div class="sm:col-span-12 rounded-xl border border-gray-100 bg-gray-50 p-4">
        <p class="text-xs font-semibold text-gray-500 mb-2">Identitas Aturan (terkunci, tidak bisa diubah)</p>
        <div class="flex flex-wrap gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-purple-200 bg-purple-50 px-2.5 py-1 text-xs font-medium text-purple-700">
                <x-icon name="apartment" class="h-3.5 w-3.5" />
                {{ $assignment->lembaga?->nama ?? 'Global (Platform Default)' }}
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
                <x-icon name="calendar_month" class="h-3.5 w-3.5" />
                {{ $assignment->tahunAjaran?->nama ?? '-' }}
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-2.5 py-1 text-xs font-medium text-gray-700">
                <x-icon name="school" class="h-3.5 w-3.5" />
                {{ $assignment->bentuk_pendidikan }}
            </span>
        </div>
    </div>
    {{-- field Tingkat & Kurikulum tetap bisa diedit seperti biasa di bawah blok ini --}}
@endif
```
Hilangkan section per-field lama yang digantikan (`Berlaku Untuk`, `Tahun Ajaran`, `Bentuk Pendidikan` versi `<p>` abu-abu untuk mode edit) — bentuk_pendidikan tetap dikirim via `<input type="hidden" name="bentuk_pendidikan" value="{{ $assignment->bentuk_pendidikan }}">` (server toh selalu re-derive dari lembaga, hidden input hanya syarat form, sesuai kode controller `update()` baris 187-190 yang sudah ada).

**Callout dampak** (tambah di bawah field Kurikulum, hanya mode edit):
```blade
@if ($assignment)
    <div class="sm:col-span-12 flex items-start gap-3 rounded-xl border border-blue-200 bg-blue-50 p-4 text-xs text-blue-800">
        <x-icon name="info" class="mt-0.5 h-4 w-4 shrink-0 text-blue-500" />
        <p>Perubahan ini hanya berlaku otomatis untuk <strong>kelas baru</strong> yang dibuat setelah ini. Kelas yang sudah ada TIDAK berubah otomatis — gunakan menu <strong>Sinkronisasi Kurikulum Kelas</strong> untuk menyelaraskannya secara sadar.</p>
    </div>
@endif
```

---

### §2.5 Breadcrumb create/edit

`create.blade.php` dan `edit.blade.php` tidak punya breadcrumb (pola standar `piket-guru`/`jadwal-pelajaran` semua punya). Tambahkan baris breadcrumb di bawah `<h1>`, pola identik `piket-guru/index.blade.php:39-41`:
```blade
<p class="text-sm text-gray-500">
    Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
    <a href="{{ route('admin.kurikulum-assignment.index') }}" class="hover:text-gray-700">Pengaturan Kurikulum</a>
    <span class="mx-1 text-gray-300">&rsaquo;</span>
    <b class="font-semibold text-gray-700">{{ $assignment ?? false ? 'Edit' : 'Tambah' }} Aturan</b>
</p>
```

---

### §2.6 Resync: empty state awal, visual diff, floating bulk bar, nama fase lama

**Empty state sebelum filter dipilih** (`resync.blade.php`, setelah form filter GET, sebelum blok `@if ($lembagaId !== null && $tahunAjaranId !== null)`):
```blade
@else
    <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-16 text-center">
        <x-icon name="sync_alt" class="h-10 w-10 text-gray-300" />
        <p class="mt-3 font-display text-sm font-bold text-gray-900">Pilih Lembaga & Tahun Ajaran untuk Memindai</p>
        <p class="mt-1 max-w-sm text-xs text-gray-500">Sistem akan memeriksa apakah ada kelas yang kurikulum atau fasenya berbeda dari aturan kurikulum terbaru.</p>
    </div>
@endif
```

**Fase lama tampilkan nama, bukan ID mentah**: `ResyncKurikulumFaseKelasAction.php:58-65`, tambah `faseLamaNama` (Kelas model sudah punya relasi `fase()`):
```php
$diff[] = [
    'kelas' => $kelas,
    'kurikulumLama' => $kurikulumLamaValue,
    'kurikulumBaru' => $kurikulumBaruValue,
    'faseLamaId' => $faseLamaId,
    'faseLamaNama' => $kelas->fase?->nama,
    'faseBaruId' => $faseBaruId,
    'faseBaruNama' => $faseBaru?->nama,
];
```
Update docblock `@return` di atas method (baris 22) menambahkan `faseLamaNama: ?string`.

**Visual diff chip** (ganti teks panah polos `resync.blade.php:59-60`):
```blade
<td class="px-4 py-3 text-sm">
    <span class="inline-flex items-center gap-1.5">
        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $row['kurikulumLama'] ?? '—' }}</span>
        <x-icon name="arrow_forward" class="h-3.5 w-3.5 text-gray-300" />
        <span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-700">{{ $row['kurikulumBaru'] ?? '—' }}</span>
    </span>
</td>
<td class="px-4 py-3 text-sm">
    <span class="inline-flex items-center gap-1.5">
        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $row['faseLamaNama'] ?? 'Tanpa Fase' }}</span>
        <x-icon name="arrow_forward" class="h-3.5 w-3.5 text-gray-300" />
        <span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-700">{{ $row['faseBaruNama'] ?? 'Tanpa Fase' }}</span>
    </span>
</td>
```

**Zero-drift success state** (ganti `resync.blade.php:43`):
```blade
@if (empty($diff))
    <div class="flex flex-col items-center justify-center gap-2 p-10 text-center">
        <x-icon name="check_circle" class="h-8 w-8 text-success-500" />
        <p class="font-display text-sm font-bold text-gray-900">Semua Kelas Sudah Selaras</p>
        <p class="text-xs text-gray-500">Tidak ada kelas di kombinasi ini yang perlu disinkronkan.</p>
    </div>
@else
```

**Floating bulk bar** (state `terpilih` dari §2.2, tambah di akhir form sebelum `</form>`):
```blade
<div x-show="terpilih.length > 0" x-cloak x-transition class="sticky bottom-0 flex items-center justify-between gap-3 border-t border-gray-200 bg-white/95 px-5 py-3.5 backdrop-blur">
    <p class="text-xs font-medium text-gray-600"><span x-text="terpilih.length"></span> kelas dipilih untuk disinkronkan</p>
    <button type="submit" :disabled="terpilih.length === 0" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">Terapkan Sinkronisasi</button>
</div>
```
(tombol submit lama di baris 66 dihapus, digantikan tombol di bar ini.)

**Select filter distandarkan** (pakai `<x-input-label>`/`initFilterSelect` seperti halaman lain) — opsional-kosmetik, TETAP GET-submit biasa (bukan full AJAX SPA, disproporsional untuk halaman diagnostik low-traffic ini): cukup ganti class `<select>` mengikuti styling standar (`rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500`), tanpa mengubah mekanisme submit.

---

### §2.7 Standardisasi wording

| Sebelum | Sesudah | Lokasi |
|---|---|---|
| "Cek & Perbaiki Kurikulum/Fase" (tombol index) | "Sinkronisasi Kurikulum Kelas" | `index.blade.php:31` |
| "Cek & Perbaiki Kurikulum/Fase Kelas" (judul halaman resync) | "Sinkronisasi Kurikulum Kelas" | `resync.blade.php:8` |
| "Cek & Perbaiki Kurikulum/Fase Kelas" (link di edit) | "Sinkronisasi Kurikulum Kelas" | `edit.blade.php:10` |
| "Tambah Assignment" | "Tambah Aturan Kurikulum" | `index.blade.php:37` |
| "Tambah Assignment Kurikulum" (H1 create) | "Tambah Aturan Kurikulum" | `create.blade.php:8` |
| "Edit Assignment Kurikulum" (H1 edit) | "Edit Aturan Kurikulum" | `edit.blade.php:8` |
| "Daftar Assignment Kurikulum" | "Daftar Aturan Kurikulum" | `index.blade.php:45` |
| "Edit Assignment" / "Hapus Assignment" (dropdown) | "Edit Aturan" / "Hapus Aturan" | `index.blade.php:70,89` |
| "Cek Drift" (tombol form resync) | "Pindai Keselarasan" | `resync.blade.php:33` |
| "Sinkronkan yang Dicentang" | "Terapkan Sinkronisasi" | `resync.blade.php:66` (sudah pindah ke floating bar §2.6) |
| "Platform Default" (badge) | "Standar Platform" | `index.blade.php:100` |

`route()` names, nama kolom database, dan nama class/method TIDAK ikut berubah — murni label yang tampil ke pengguna.

---

## 3. Ringkasan Perubahan File

| File | Jenis Perubahan |
|---|---|
| `app/Domains/Akademik/Enums/BentukPendidikan.php` | Tidak diubah (dibaca sebagai source of truth) |
| `app/Http/Controllers/Admin/KurikulumAssignmentController.php` | `index()`: tambah cabang ajax + filter query. `create()`/`edit()`: kirim `tingkatOptionsByBentuk`. |
| `app/Domains/Akademik/Actions/Kelas/ResyncKurikulumFaseKelasAction.php` | `hitungDiff()`: tambah key `faseLamaNama` |
| `resources/views/admin/kurikulum-assignment/index.blade.php` | Kontainer, KPI, filter AJAX (`x-data`, `x-ref`), badge hierarki, tooltip read-only, wording |
| `resources/views/admin/kurikulum-assignment/_daftar.blade.php` | BARU — partial tabel hasil ekstraksi dari index.blade.php |
| `resources/views/admin/kurikulum-assignment/create.blade.php` | Breadcrumb, wording |
| `resources/views/admin/kurikulum-assignment/edit.blade.php` | Breadcrumb, wording |
| `resources/views/admin/kurikulum-assignment/_form.blade.php` | Pill selector tingkat, metadata card edit, callout dampak |
| `resources/views/admin/kurikulum-assignment/resync.blade.php` | confirmDialog, empty state, visual diff chip, floating bulk bar, wording, styling select |

## 4. Di Luar Cakupan (Sengaja Tidak Dikerjakan)

- **KPI "status keselarasan/drift" global di halaman index** — butuh menjalankan `hitungDiff()` untuk SETIAP kombinasi lembaga×tahun-ajaran saat index() dipanggil, terlalu mahal untuk halaman yang saat ini ringan. Kalau dibutuhkan nanti, harus jadi fitur async/cached terpisah, bukan bagian dari perbaikan UI ini.
- **Konversi halaman resync jadi full AJAX-SPA** (pola `jadwal-pelajaran`/`piket-guru`) — disproporsional untuk halaman diagnostik low-traffic yang dipakai sesekali, bukan halaman CRUD harian. GET-submit + confirmDialog sudah cukup aman dan sederhana.
- **Perubahan validasi/skema database `tingkat`** (misal ubah jadi kolom enum di DB) — di luar scope, validasi aplikasi via `validTingkatValues()` sudah cukup, dan ini murni perbaikan UI/UX bukan restrukturisasi data.
- **Opsi tingkat "13" untuk SMK 4 tahun** — TIDAK ada di `BentukPendidikan::validTingkatValues()` saat ini (SMK hanya 10/11/12). Laporan audit awal salah berasumsi ini ada; spec ini mengikuti kode aktual, bukan asumsi.
- **Perubahan `KurikulumAssignmentResolver`/`AssignKurikulumAction`/`UpdateKurikulumAssignmentAction`** — domain layer sudah diverifikasi solid, tidak disentuh.

## 5. Self-Review (5 Putaran)

**Putaran 1 — Cakupan vs temuan awal**: Semua 3 kategori temuan (kritis: §2.1, §2.2; index: §2.3; form: §2.4-§2.5; resync: §2.6; wording: §2.7) sudah masuk. Ditemukan 1 klaim laporan awal yang keliru (opsi tingkat "13" SMK) — dikoreksi di §4, tidak diimplementasikan karena tidak ada di enum.

**Putaran 2 — Konsistensi kode vs current state**: Re-cek ulang setiap "Current" code block terhadap file asli (dibaca langsung, bukan diasumsikan dari laporan audit) — semua baris/nomor baris di §2.1-§2.7 cocok dengan isi file per 2026-09-11. `faseLamaId` di resync memang cuma ID mentah (bukan nama) — dikonfirmasi ini real gap, ditambahkan §2.6 fix (bukan sekadar polish CSS).

**Putaran 3 — Keamanan/regresi**: Semua perubahan §2.1-§2.7 murni presentational + 1 penambahan field (`faseLamaNama`) + 1 cabang ajax read-only di `index()`. Tidak ada perubahan pada `store()`/`update()`/`destroy()`/`apply()` (logika bisnis, validasi, authorization tetap identik). Filter AJAX index() hanya menambah `where()` pada query yang SUDAH di-scope oleh blok `if ($scope === 'yayasan')`/`elseif ($scope !== 'platform')` di atasnya — filter baru ditempatkan SETELAH scoping itu, jadi tidak bisa dipakai untuk bypass tenant scope.

**Putaran 4 — Konsistensi pola dengan modul lain di sesi ini**: `dataTableFilter` dipakai persis seperti `piket-guru`/`admin/kelas` (bukan re-desain pola baru). `confirmDialog` dipakai persis seperti pola index.blade.php sendiri (tombol hapus assignment) dan puluhan halaman lain. `<x-tooltip>` dipakai sesuai kontrak default (`asButton=false`, trigger `<span>` non-interaktif) — TIDAK perlu prop `as-button` karena isinya teks statis, bukan elemen interaktif bersarang, jadi tidak kena regresi tooltip yang pernah ditemukan sesi ini.

**Putaran 5 — Placeholder & kelengkapan kode**: Scan ulang §2.1-§2.7 untuk instruksi tanpa kode konkret — tidak ditemukan (`TODO`, "seperti biasa", dsb). Satu area yang sengaja ditulis sebagai instruksi umum bukan kode-siap-pakai: bagian "Select filter distandarkan" di §2.6 (styling class only, tidak butuh kode Alpine baru). Baris `$bentukPendidikanAwal`/`$lembagaBentukPendidikanUntukPill` di §2.1 diverifikasi konsisten dengan variabel `$lembagaBentukPendidikan` yang SUDAH ADA di `_form.blade.php:72` (mode non-platform) — plan nanti harus eksplisit menyambungkan variabel ini, dicatat sebagai catatan untuk task plan.
