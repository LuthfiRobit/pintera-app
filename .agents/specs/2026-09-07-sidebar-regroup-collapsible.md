# Spec: Sidebar — Regroup Menu & Grup Collapsible

> **Branch**: `refactor-view-v2` (baru dibuat dari `rbac-v2`, belum ada commit sendiri)
> **Tanggal**: 7 September 2026
> **Latar belakang**: Client minta gaya sidebar diganti (belum spesifik). Analisis menyeluruh terhadap `resources/views/layouts/sidebar.blade.php` (dibaca penuh, 239 baris) menemukan masalah STRUKTUR, bukan cuma visual: 1 grup 12-item campur aduk ("Data Induk"), 1 item ("Kasus Pendampingan") ditempel manual 4x di 4 grup berbeda dengan kondisi role yang hampir sama, dan 1 grup ("Kehadiran Saya") yang namanya tidak lagi mencerminkan isinya. 13 grup SEMUA selalu terbuka sekaligus (tidak ada collapse) — makin banyak modul ditambah, makin panjang.

## Ruang Lingkup — Ini Perbaikan STRUKTUR, Bukan Restyle Visual

**Spec ini TIDAK mengubah palet warna/tipografi/token desain** (`brand-500` indigo, font Outfit, `rounded-2xl`/`shadow-card` dst. — TailAdmin-style yang sudah ditetapkan sebelumnya, lihat memory `reference_design_direction_artifact`). Client belum kasih detail spesifik soal gaya visual yang diinginkan — restyle visual (kalau memang itu yang dimaksud) adalah spec TERPISAH menyusul, butuh input lebih konkret dari client dulu. Spec ini murni: (1) susun ulang isi/pengelompokan menu, (2) grup jadi collapsible.

## Keputusan Desain

### 1. Satukan "Kasus Pendampingan" jadi 1 kondisi, bukan 4 duplikasi

**Kode saat ini** — 4 baris HAMPIR identik tersebar di 4 grup berbeda:
```php
// Ruang Guru (baris 22):
Auth::user()->hasRole('guru') && Auth::user()->can('viewAny', \App\Domains\Kasus\Models\Kasus::class) ? [...] : null,
// Ruang Siswa (baris 33):
Auth::user()->hasRole('siswa') && Auth::user()->can('viewAny', \App\Domains\Kasus\Models\Kasus::class) ? [...] : null,
// Ruang Orang Tua (baris 46):
Auth::user()->orangTua !== null && Auth::user()->can('viewAny', \App\Domains\Kasus\Models\Kasus::class) ? [...] : null,
// Kehadiran Saya (baris 55):
! Auth::user()->hasRole('guru') && ! Auth::user()->hasRole('siswa') && Auth::user()->orangTua === null && Auth::user()->can('viewAny', \App\Domains\Kasus\Models\Kasus::class) ? [...] : null,
```
4 kondisi ini SECARA LOGIKA saling eksklusif (menjamin cuma 1 grup yang menampilkannya per user) — TAPI intinya cuma 1 syarat sebenarnya: `can('viewAny', Kasus::class)`. Kondisi persona di atas HANYA menentukan "taruh di grup MANA", bukan "boleh akses atau tidak". Ini pemborosan & rawan lupa update kalau ada persona baru (mis. karyawan pool nanti).

**Fix**: hapus SEMUA 4 kemunculan itu, buat 1 grup baru mandiri dengan 1 kondisi:
```php
[
    'label' => 'Pendampingan Saya',
    'group_icon' => 'stethoscope',
    'items' => array_filter([
        Auth::user()->can('viewAny', \App\Domains\Kasus\Models\Kasus::class) ? ['route' => 'kasus.index', 'pattern' => 'kasus.*', 'label' => 'Kasus Pendampingan', 'icon' => 'stethoscope'] : null,
    ]),
],
```

### 2. Rename "Kehadiran Saya" → "Ruang Karyawan"

Setelah Kasus Pendampingan ditarik keluar (poin 1), grup ini isinya cuma QR Kehadiran Saya + Izin/Cuti Saya (baris 53-54) — SECARA ISI sudah "murni kehadiran" lagi. TAPI nama lama tetap diganti untuk KONSISTENSI pola penamaan dengan "Ruang Guru"/"Ruang Siswa"/"Ruang Orang Tua" yang sudah ada — supaya kalau nanti ditambah item personal LAIN untuk staff non-guru (bukan cuma kehadiran), grup ini sudah punya nama yang menampung tanpa perlu direname lagi. Icon TETAP `clock` (masih representatif, isi sekarang murni kehadiran).

```php
[
    'label' => 'Ruang Karyawan',
    'group_icon' => 'clock',
    'items' => array_filter([
        ! Auth::user()->hasRole('guru') && Auth::user()->can('kehadiran-sdm.lihat-qr-sendiri') ? ['route' => 'sdm.qr-saya', 'pattern' => 'sdm.qr-saya', 'label' => 'QR Kehadiran Saya', 'icon' => 'qr-code'] : null,
        ! Auth::user()->hasRole('guru') && Auth::user()->can('kehadiran-sdm.izin.lihat-sendiri') ? ['route' => 'sdm.izin-cuti.index', 'pattern' => 'sdm.izin-cuti.*', 'label' => 'Izin/Cuti Saya', 'icon' => 'calendar-days'] : null,
    ]),
],
```
(Kondisi `! Auth::user()->hasRole('guru')` TIDAK berubah — tetap eksklusif dari Ruang Guru yang sudah punya QR/Izin-Cuti sendiri, baris 20-21.)

### 3. Pecah "Data Induk" (12 item) jadi 3 grup bertema

**Yayasan & Lembaga** (struktur organisasi):
```php
[
    'label' => 'Yayasan & Lembaga',
    'group_icon' => 'building-2',
    'items' => array_filter([
        Auth::user()->can('yayasan.kelola') ? ['route' => 'admin.yayasan.edit', 'pattern' => 'admin.yayasan.*', 'label' => 'Pengaturan Yayasan', 'icon' => 'landmark'] : null,
        Auth::user()->can('lembaga.view') ? ['route' => 'admin.lembaga.index', 'pattern' => 'admin.lembaga.*', 'label' => 'Lembaga', 'icon' => 'building-2'] : null,
        Auth::user()->can('tahun-ajaran.view') ? ['route' => 'admin.tahun-ajaran.index', 'pattern' => 'admin.tahun-ajaran.*', 'label' => 'Tahun Ajaran', 'icon' => 'calendar-days'] : null,
    ]),
],
```

**Data Guru & Karyawan** (SDM):
```php
[
    'label' => 'Data Guru & Karyawan',
    'group_icon' => 'briefcase',
    'items' => array_filter([
        Auth::user()->can('guru.view') ? ['route' => 'admin.guru.index', 'pattern' => 'admin.guru.*', 'label' => 'Guru', 'icon' => 'graduation-cap'] : null,
        Auth::user()->can('karyawan.view') ? ['route' => 'admin.karyawan.index', 'pattern' => 'admin.karyawan.*', 'label' => 'Karyawan', 'icon' => 'briefcase'] : null,
        Auth::user()->can('jenis-karyawan-master.view') ? ['route' => 'admin.jenis-karyawan-master.index', 'pattern' => 'admin.jenis-karyawan-master.*', 'label' => 'Jenis Karyawan', 'icon' => 'tags'] : null,
        Auth::user()->can('jabatan-tambahan-master.view') ? ['route' => 'admin.jabatan-tambahan-master.index', 'pattern' => 'admin.jabatan-tambahan-master.*', 'label' => 'Jabatan Tambahan', 'icon' => 'medal'] : null,
    ]),
],
```

**Data Siswa & Orang Tua**:
```php
[
    'label' => 'Data Siswa & Orang Tua',
    'group_icon' => 'contact',
    'items' => array_filter([
        Auth::user()->can('siswa.view') ? ['route' => 'admin.siswa.index', 'pattern' => 'admin.siswa.*', 'label' => 'Siswa', 'icon' => 'users'] : null,
        Auth::user()->can('orang-tua.view') ? ['route' => 'admin.orang-tua.index', 'pattern' => 'admin.orang-tua.*', 'label' => 'Orang Tua', 'icon' => 'users'] : null,
    ]),
],
```

### 4. Kelas & Mata Pelajaran pindah ke grup "Akademik"

Disisipkan di AWAL array `items` grup Akademik (sebelum "Pengaturan Akademik"), karena keduanya struktur dasar yang dipakai semua item lain di grup itu (Kurikulum Assignment, Jadwal Pelajaran, dst. — semua butuh Kelas/Mapel sudah ada duluan):
```php
[
    'label' => 'Akademik',
    'group_icon' => 'book-open',
    'items' => array_filter([
        Auth::user()->can('kelas.view') ? ['route' => 'admin.kelas.index', 'pattern' => 'admin.kelas.*', 'label' => 'Kelas', 'icon' => 'door-open'] : null,
        Auth::user()->can('mata-pelajaran.view') ? ['route' => 'admin.mata-pelajaran.index', 'pattern' => 'admin.mata-pelajaran.*', 'label' => 'Mata Pelajaran', 'icon' => 'book'] : null,
        Auth::user()->can('kalender-akademik.view') ? ['route' => 'admin.pengaturan.akademik.index', 'pattern' => 'admin.pengaturan.akademik.*', 'label' => 'Pengaturan Akademik', 'icon' => 'calendar-clock'] : null,
        // ...9 item existing (Kurikulum Assignment s.d. Jadwal Piket Guru) TIDAK berubah urutan/isinya
    ]),
],
```

### 5. Template WhatsApp pindah ke "Akses & Peran", di-rename "Pengaturan Sistem"

```php
[
    'label' => 'Pengaturan Sistem',
    'group_icon' => 'shield-check',
    'items' => array_filter([
        Auth::user()->can('users.view') ? ['route' => 'admin.users.index', 'pattern' => 'admin.users.*', 'label' => 'Pengguna', 'icon' => 'users-round'] : null,
        Auth::user()->can('roles.view') ? ['route' => 'admin.roles.index', 'pattern' => 'admin.roles.*', 'label' => 'Peran', 'icon' => 'user-cog'] : null,
        Auth::user()->can('whatsapp-template.edit') ? ['route' => 'admin.whatsapp-template.index', 'pattern' => 'admin.whatsapp-template.*', 'label' => 'Template WhatsApp', 'icon' => 'message-square'] : null,
    ]),
],
```

### 6. Grup jadi Collapsible (Accordion)

**Kondisi saat ini**: `sidebarOpen`/`sidebarCollapsed` adalah state Alpine di LAYOUT PARENT (bukan file ini) — mengatur BUKA/TUTUP SELURUH sidebar (mobile drawer + desktop collapse-ke-w-0). Tidak ada state per-grup sama sekali; `sidebar.blade.php` sendiri TIDAK punya `x-data` (murni `@foreach` Blade polos di baris 206-232).

**Fix**: bungkus tiap grup dengan `x-data` LOKAL (Alpine mendukung nested scope tanpa konflik dengan `sidebarOpen`/`sidebarCollapsed` di parent). State awal `open` = `true` HANYA kalau grup itu mengandung item yang sedang aktif (`request()->routeIs(...)`), supaya user langsung lihat lokasinya saat pindah halaman — grup lain default tertutup.

Ganti blok `@foreach ($navGroups as $group)` (baris 206-232) jadi:
```blade
@foreach ($navGroups as $group)
    @if (count($group['items']))
        @php
            $groupHasActiveItem = collect($group['items'])->contains(fn ($item) => request()->routeIs($item['pattern']));
        @endphp
        <div class="mb-3" x-data="{ open: {{ $groupHasActiveItem ? 'true' : 'false' }} }">
            <button
                type="button"
                @click="open = !open"
                class="mb-2 flex w-full items-center justify-between gap-1.5 rounded-lg px-2 py-1.5 text-left transition hover:bg-gray-50"
            >
                <span class="flex items-center gap-1.5 font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-400">
                    @if (isset($group['group_icon']))
                        <x-dynamic-component :component="'lucide-' . $group['group_icon']" class="h-[14px] w-[14px] opacity-70" />
                    @endif
                    {{ $group['label'] }}
                </span>
                <x-dynamic-component :component="'lucide-chevron-down'" class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform duration-200" ::class="{ '-rotate-90': !open }" />
            </button>
            <ul
                class="space-y-0.5"
                x-show="open"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
            >
                @foreach ($group['items'] as $item)
                    @php $active = request()->routeIs($item['pattern']); @endphp
                    <li>
                        <a
                            href="{{ route($item['route'], $item['params'] ?? []) }}"
                            class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition
                                {{ $active ? 'bg-brand-50 font-semibold text-brand-600' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
                        >
                            <x-dynamic-component :component="'lucide-' . $item['icon']" class="h-[18px] w-[18px] shrink-0 {{ $active ? 'text-brand-500' : 'text-gray-400 group-hover:text-gray-500' }}" />
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endforeach
```

**Dikonfirmasi saat spec ditulis (bukan asumsi)**:
1. **`@alpinejs/collapse` TIDAK terpasang di project ini** (dicek langsung: `grep -rn "collapse" resources/js/*.js package.json` — 0 hasil). Kode di atas SUDAH disesuaikan untuk TIDAK memakai `x-collapse` — pakai `x-show` + `x-transition` biasa (transisi opacity/translate, bukan animasi height yang mulus, tapi tidak butuh dependency baru). JANGAN tambah `@alpinejs/collapse` untuk spec ini kecuali user secara eksplisit minta animasi height yang lebih halus — itu penambahan scope terpisah.
2. **`<x-icon>` BUKAN komponen yang dipakai di file ini** — `<x-icon>` (`resources/views/components/icon.blade.php`) pakai skema nama Material-Symbols-style (`expand_more`, `chevron_right`, dst., TIDAK ADA `chevron-down`/`chevron_down` — kalau dipaksa dipakai, jatuh ke `@default` yang render ICON PLACEHOLDER SALAH, bukan chevron). Seluruh `sidebar.blade.php` (item DAN group icon) konsisten pakai `<x-dynamic-component :component="'lucide-' . $nama">` (paket ikon Lucide terpisah) — kode di atas SUDAH diperbaiki memakai pola yang SAMA (`lucide-chevron-down`), BUKAN `<x-icon>`. JANGAN campur 2 sistem ikon berbeda di file yang sama.
3. **`x-init` di `<nav>` (baris 199-204, scroll ke item aktif)** — dengan grup jadi collapsible, item aktif HANYA visible di DOM (untuk discroll-ke) kalau grup induknya `open`. Karena `open` awal SUDAH dihitung `true` untuk grup berisi item aktif (per desain di atas), ini SEHARUSNYA tetap bekerja tanpa perubahan — TAPI **VERIFIKASI dengan browser sungguhan** (bukan cuma dibaca), karena `x-show`/`x-collapse` punya timing render yang bisa beda dari elemen yang selalu ada di DOM.
4. **Mobile vs desktop collapsed (`sidebarCollapsed` = whole sidebar jadi w-0)** — pastikan state accordion per-grup TIDAK bentrok/reset aneh saat sidebar utama di-collapse lalu dibuka lagi (kemungkinan besar aman karena Alpine `x-data` grup nested tidak di-destroy saat parent cuma berubah `width`/`translate-x`, tapi VERIFIKASI visual).

## Di Luar Scope

1. **Restyle visual (warna/tipografi/radius/shadow)** — TIDAK disentuh, tetap TailAdmin-style existing. Kalau client memang mau ganti nuansa visual total, itu spec terpisah menyusul setelah ada detail lebih jelas dari client.
2. **Reorder POSISI grup** (mis. Ringkasan tetap paling atas, Pengaturan Sistem tetap paling bawah) — TIDAK diubah urutannya kecuali disebutkan eksplisit di atas (Data Induk lama ganti jadi 3 grup baru di posisi yang sama).
3. **4 grup "Ruang" existing (Guru/Siswa/Orang Tua) selain penarikan Kasus Pendampingan** — isinya TIDAK diubah lebih lanjut.

## Test / Verifikasi

Sidebar ini TIDAK punya test Pest dedicated yang ditemukan sejauh investigasi (murni Blade view, dirender lewat layout) — cek dulu saat implementasi apakah ada test snapshot/render existing untuk file ini (grep `sidebar` di `tests/`). Kalau ada, jalankan & pastikan tetap hijau (terutama test yang mengasersi label/route menu tertentu ada/tidak ada berdasarkan permission — beberapa test sepanjang sesi ini sempat menyinggung sidebar, mis. Kategori B "Ruang Guru" test).

**Verifikasi manual WAJIB di browser** (bukan cuma baca kode):
1. Login sebagai `yayasan_super_admin` (semua permission) — cek SEMUA grup baru muncul dengan isi benar, collapse/expand tiap grup berfungsi, grup berisi halaman aktif otomatis terbuka saat load.
2. Login sebagai guru — cek Ruang Guru TETAP tampil normal, "Pendampingan Saya" muncul kalau guru itu py `kasus.viewAny`, TIDAK muncul kalau tidak.
3. Login sebagai orang tua — sama, cek "Pendampingan Saya" & "Ruang Karyawan" TIDAK muncul untuk persona ini (dia harusnya masuk kategori beda).
4. Resize ke mobile width — cek drawer sidebar + accordion tetap jalan bareng tanpa konflik.
