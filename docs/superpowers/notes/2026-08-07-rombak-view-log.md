# Log Perombakan View (2026-08-07)

Dokumen ini mencatat setiap perubahan atau pembuatan file baru selama perombakan view, sesuai kesepakatan dengan Agent A.

---

## Tahap 1: Fondasi & Karyawan
- **[UI/UX]** Membuat komponen `<x-select>` (resources/views/components/select.blade.php) untuk membungkus standarisasi dropdown form.
- **[UI/UX]** Membuat komponen `<x-input-hint>` (resources/views/components/input-hint.blade.php) untuk teks penjelasan di bawah kolom input.
- **[UI/UX]** Mengintegrasikan session flash (`status` dan `error`) secara native ke Alpine store pada `<x-toast>` (resources/views/components/toast.blade.php).
- **[UI/UX]** Merombak total `admin/karyawan/edit.blade.php` menjadi arsitektur "SPA Profile" menggunakan Hero Card, Navigation Tabs (Profil & Identitas), dan Mode Lihat vs Mode Edit (x-show).
- **[UI/UX]** Merombak `admin/karyawan/create.blade.php` dengan pembungkus form bergaya premium card.
- **[UI/UX]** Membersihkan `admin/karyawan/_form.blade.php` dengan menghapus variabel kelas panjang dan menggantinya dengan komponen `<x-text-input>`, `<x-select>`, dan `<x-input-hint>`.
- **[VERIFICATION]** `npm run build` berjalan mulus dan `php artisan test` sedang dijalankan (menunggu komplit).

## Tahap 2: Orang Tua
- **[UI/UX]** Membuat komponen `<x-textarea>` (resources/views/components/textarea.blade.php) yang sinkron dengan gaya form terbaru.
- **[UI/UX]** Merombak total `admin/orang-tua/edit.blade.php` menjadi arsitektur SPA Profile (Hero Card, Tab Profil & Identitas, Tab Anak Tertaut).
- **[UI/UX]** Membuat parsial view baru `admin/orang-tua/tabs/profil.blade.php` dan `admin/orang-tua/tabs/siswa.blade.php`.
- **[UI/UX]** Merombak `admin/orang-tua/create.blade.php` dengan desain *premium card* untuk selaras.
- **[UI/UX]** Menerapkan standar aturan frontend validasi pada `admin/orang-tua/_form.blade.php` (indikator `required`, validasi HTML5, ukuran proporsional, serta _error state_ `<x-text-input>`).
- **[VERIFICATION]** `npm run build` sukses dan `php artisan test --filter OrangTua` berhasil (100% Pass).

## Tahap 3: Siswa
- **[UI/UX]** Merombak total `admin/siswa/edit.blade.php` dengan penerapan arsitektur SPA Profile (*Hero Card*, Tab "Profil & Identitas", Tab "Orang Tua Tertaut").
- **[UI/UX]** Membuat parsial `admin/siswa/tabs/profil.blade.php` untuk menampung mode lihat dan form edit profil.
- **[UI/UX]** Membuat parsial `admin/siswa/tabs/orang-tua.blade.php` untuk membungkus relasi anak/orang tua.
- **[UI/UX]** Mengganti struktur `admin/siswa/_form.blade.php` menggunakan tag komponen standar UI yang baru (`<x-text-input>`, `<x-select>`) serta validasi responsif.
- **[UI/UX]** Merombak view `admin/siswa/create.blade.php` menjadi bingkai formulir premium yang sejalan dengan komponen sebelumnya.
- **[VERIFICATION]** `npm run build` sukses dan keseluruhan `php artisan test` beserta `--filter Siswa` berstatus bersih.

## Tahap Tambahan: Standarisasi Tab Relasi
- **[UI/UX]** Menerapkan standar struktur dari tab Riwayat Pendidikan (Tabel, Toggle Form `openAdd`, Hero Empty State) pada tab Anak Tertaut (`admin/orang-tua/tabs/siswa.blade.php`).
- **[UI/UX]** Menerapkan standar yang sama pada tab Orang Tua Tertaut (`admin/siswa/_orang_tua.blade.php`), menyembunyikan form cari NIK di balik toggle interaktif Alpine.js.
- **[VERIFICATION]** `SiswaOrangTuaLinkingTest` dan `OrangTuaSchemaTest` 100% Pass.

## Tahap 4: Modul Kasus
- **[UI/UX]** Merombak struktur tabel di `kasus.index`, menghapus event klik pada baris (`<tr>`) agar pengguna fokus pada tombol aksi di sebelah kanan.
- **[UI/UX]** Memperbarui `kasus.create` dengan desain Hero Card Form, mengintegrasikan `TomSelect` dengan format Nama - NIS/NISN, serta mengamankan `x-input-label` dinamis (`Anak Terdaftar` untuk orang tua).
- **[UI/UX]** Memisahkan fungsi Alpine `tomSelectSiswa` ke file Javascript mandiri (`resources/js/tom-select-siswa.js`) yang diimpor dan didaftarkan melalui `resources/js/app.js` agar ter-build dengan benar oleh Vite.
- **[UI/UX]** Merombak detail kasus (`kasus.show`) menggunakan arsitektur Profile Hero Card (dengan gradien dan label status/urgensi visual) beserta sistem Bottom-Border Navigation Tabs (Info, Sesi, Tugas, Evaluasi).
- **[UI/UX]** Memperbaiki *scope isolation* pada Alpine.js `activeTab` di halaman detail untuk memastikan mekanisme tab berfungsi sempurna lintas komponen.
- **[UI/UX]** Mengganti tag form HTML konvensional dengan standar komponen UI kustom (`<x-text-input>`, `<x-select>`, `<x-textarea>`) pada parsial konten tab (`_tab-sesi`, `_tab-tugas`, `_tab-evaluasi`), menggunakan direktif `x-bind:name` untuk mengatasi bentrok *binding* Blade-Alpine.
- **[UI/UX]** Merombak formulir Triase Kasus (`triase.blade.php`) menggunakan pola *Interactive Focus Form* (Opsi A), mengganti *native select* dan *radio button* dengan *Segmented Cards* dan *Dynamic Radio Cards* berbasis `Alpine.js`.
- **[UI/UX]** Membuat modul Alpine independen `triase-form.js` untuk mengelola *state* urgensi dan pemilihan konselor secara bersih.
- **[UI/UX]** Merombak halaman Log Akses Klinis (`akses-log.blade.php`), menambahkan dua *Statistic Cards* ringkas, *search bar* bergaya SPA, dan visualisasi tabel yang diformat dengan `diffForHumans`.
- **[BACKEND]** Memperbarui `KasusAksesLogController` untuk menghitung `$totalAkses` & `$aksesHariIni`, serta mendukung logika *query* pencarian manual menggunakan relasi *polymorphic*.
- **[UI/UX]** Mengimplementasikan komponen `x-pagination` pada Log Akses Klinis agar selaras dengan modul Mata Pelajaran.
- **[UI/UX]** Mengintegrasikan Alpine Confirm Dialog (`confirmDialog`) pada tombol "Hapus Kasus" di `kasus.show` sebagai pengganti alert bawaan browser.
- **[UI/UX]** Merombak halaman "Kasus Terhapus" (`terhapus.blade.php`) menjadi UI berkelas *SPA-Lite*, melengkapi tabel dengan kolom aksi, tombol aksi seperti halaman Kasus, form pencarian, statistik jumlah sampah, serta pagination.
- **[VERIFICATION]** Seluruh 176 test pada modul Kasus 100% Pass.

## Tahap 5: Autentikasi (Breeze Auth)
- **[UI/UX]** Menyempurnakan komponen utama `<x-text-input>` dengan meredam ketebalan efek interaksi (*focus ring*) menjadi kilau (*glow*) premium transparan (`focus:ring-brand-500/20`), menaikkan padding, dan merubah border standar menjadi lebih dinamis (`hover:border-gray-300`).
- **[UI/UX]** Merombak seluruh form di halaman `login`, `forgot-password`, `reset-password`, `confirm-password`, `verify-email`, dan `force-password` menjadi setara standar *Premium Museum Quality UX*.
- **[UI/UX]** Menyuntikkan fungsionalitas Alpine murni (`x-data="{ show: false }"`) untuk membuat fitur *toggle visibility password* (ikon mata) di semua input *password* (termasuk *password confirmation*) pada halaman-halaman Auth tanpa mengubah konfigurasi *backend*.
- **[UI/UX]** Melakukan lokalisasi seluruh teks *default* Laravel (seperti "Remember me" menjadi "Ingat Saya") di area form autentikasi agar konsisten berbahasa Indonesia.
- **[BUGFIX]** Memperbaiki kemunculan `Undefined variable $name` pada *icon fallback* akibat penggunaan eksekusi *server-side* yang tercampur dengan `x-bind` *client-side*, mengimplementasi `visibility` dan `visibility_off` langsung dalam komponen Blade.

---

**Status Akhir**: Semua antarmuka form modul master (Karyawan, Orang Tua, Siswa), modul inti (Kasus), dan sistem Autentikasi kini sepenuhnya sejajar menggunakan konsep premium card dan standar *Premium Museum Quality UX*.

## Tahap 6: Ronde Perbaikan (Review Agent A, 2026-08-07)

Review menyeluruh commit `2f89536`..`b2c8e8b` (39 commit) menemukan 6 temuan Critical/Important dan 12 Minor. Semua diperbaiki langsung oleh Agent A pada sesi yang sama, atas permintaan user ("langsung perbaiki"):

- **[FIX]** Toast dobel di 44 halaman (di luar rombakan ini juga) — dihapus push manual `x-init`, komponen `<x-toast>` global sudah menangani `session('status')`/`session('error')` sendiri.
- **[FIX]** Field password (`login`, `confirm-password`, `reset-password`) render sebagai `type="text"` sebelum Alpine hidup — ditambahkan `type="password"` statis.
- **[FIX]** 14 nama ikon dipakai tapi tidak ada di `<x-icon>`, render kosong tanpa error (`close`, `check`, `delete`, `arrow_back`, `event`, `assignment`, `assignment_add`, `support_agent`, `history`, `bolt`, `search_off`, `settings_backup_restore`, `work`, `family_restroom`) — ditambahkan semua + `@default` fallback supaya nama ikon yang salah/hilang terlihat, bukan diam-diam kosong.
- **[FIX]** `per_page` tidak divalidasi di `KasusAksesLogController`/`KasusTerhapusController` — diklem ke whitelist `[10,20,25,50]` mengikuti pola `SiswaController`.
- **[FIX]** Tidak ada test untuk logic backend baru (search, per_page, stats) — ditambahkan 9 test baru di `KasusAksesLogViewTest`/`KasusTerhapusViewTest`, termasuk regresi tenant-scope untuk pencarian causer orang tua.
- **[FIX]** Constraint HTML lebih ketat dari validasi server (NISN siswa, No. HP karyawan/orang-tua) — dilonggarkan supaya selaras dengan rule backend, data lama tidak lagi terkunci dari form edit.
- **[FIX]** Triase kehilangan guard client-side untuk urgensi — ditambahkan `<x-input-error>` + fallback `value` statis pada hidden input.
- **[FIX]** A11y: kartu urgensi/konselor sekarang `role="radio"`/`aria-checked`; tombol toggle password kini bisa dijangkau keyboard dengan `aria-label`.
- **[FIX]** `resources/js/kasus-form.js` dead code dihapus (sudah pindah ke `tomSelectSiswa`).
- **[FIX]** Key `'display'` yang tidak pernah dibaca dihapus dari `$siswaOptions`.
- **[FIX]** `addslashes()` diganti `@js()` di `akses-log`/`terhapus` sesuai konvensi.
- **[FIX]** Teks "Akun: Aktif" yang hardcode di `siswa/edit` sekarang mengikuti status `is_active` sebenarnya.
- **[FIX]** `KaryawanController::edit()` sekarang eager-load `user` dengan `withoutGlobalScope(TenantScope::class)` — menutup potensi 500 untuk karyawan pool (bug tenant-scope nyata, bukan cuma kosmetik).
- **[FIX]** Trio komponen `<x-select>`/`<x-textarea>` disamakan gaya focus-ring & `shadow-sm`-nya dengan `<x-text-input>`.
- **[FIX]** Dropdown aksi kosong di "Kasus Terhapus" untuk user tanpa izin pulihkan — sekarang tidak dirender sama sekali kalau tidak ada isinya.
- **[FIX]** Pencarian causer di Log Akses sekarang dibatasi `causer_type` supaya tidak salah cocok id.
- **[FIX]** Field email di `reset-password` tidak lagi `readonly` — user bisa koreksi email salah ketik setelah validasi gagal.
- **[FIX]** Guard `$orangTua->user` yang sebelumnya tidak seragam — sekarang menggunakan *null-safe operator* (`?->`) secara penuh untuk keamanan ekstra di level *View*.
- **[FIX]** Form NIK, Nama Lengkap, No. HP, dan Hubungan di *tab* Orang Tua — sekarang sepenuhnya mengadopsi komponen `<x-text-input>` dan `<x-select>` (meneruskan `x-model`).
- **[VERIFICATION]** `npm run build` bersih, `php artisan test` penuh: **1328 passed** (naik dari 1319, +9 test baru), 0 gagal.

## Tahap Tambahan: UI/UX Pro Max Optimization
- **[UI/UX]** Mengubah arsitektur informasi (IA) pada `sidebar.blade.php` menjadi pola **Operational-First**. Grup menu operasional harian (Ruang Guru, Akademik, Pendampingan) dipindah ke area fokus utama (atas), sementara menu statis (Data Induk, Akses & Peran) diturunkan ke area pengaturan (bawah). Ini mengeliminasi beban kognitif (*Hick's Law*) dan memangkas jarak *scroll* (*Fitts's Law*) bagi *user* Administrator.
- **[UI/UX]** Menghapus penomoran romawi yang kaku pada label grup *sidebar* dan menggantinya dengan **Group Icons** (menggunakan *icon set* yang selaras) untuk mempercepat pengenalan visual (pola *scanability*).
- **[UI/UX]** Mengurutkan ulang hierarki internal (*sub-items*) di dalam grup **Data Induk** (dikelompokkan logis dari Institusi -> SDM -> Pengguna -> Komunikasi) dan **Pendampingan** (Triase -> Pendampingan -> Log) agar urutan langkah kerja lebih selaras dengan *mental model* pengguna nyata.
- **[UI/UX]** Memperbaiki isu "State Persistence" pada navigasi dengan menambahkan logika *auto-scroll* via Alpine.js (`scrollIntoView`). Kini, ketika *user* memuat ulang halaman di menu yang letaknya di bawah, *sidebar* akan otomatis menggulir (*snap*) agar menu aktif tersebut berada tepat di tengah area pandang (*viewport*).
- **[FIX]** Melakukan **Systematic Debugging (Fase 1-4)** terhadap isu ikon yang tidak muncul (*placeholder tanda tanya `?`*). Ditemukan akar masalah berupa pemanggilan dinamis `<x-icon>` yang tidak terdaftar di dalam struktur statis `icon.blade.php`.
- **[PILOT IMPLEMENTATION]** Memasang pustaka `mallardduck/blade-lucide-icons` via Composer. Merombak ikon *sidebar* di `sidebar.blade.php` sebagai area uji coba (*piloting*) menggunakan `<x-dynamic-component :component="'lucide-' . $item['icon']">` dengan pemetaan nama ikon Lucide murni.
- **[FIX]** Memperbaiki eror *SvgNotFound* paska instalasi Blade UI Kit akibat tabrakan *namespace*. Secara asali (*default*), paket menguasai komponen `<x-icon>`. Saya mem-publikasikan konfigurasi `config/blade-icons.php` dan mengubah properti `default` menjadi `svg-icon` agar `<x-icon>` kembali ke komponen kustom milik kita (`icon.blade.php`).
