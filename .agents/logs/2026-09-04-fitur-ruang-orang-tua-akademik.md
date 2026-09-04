# Handoff Log: Fitur Ruang Orang Tua Akademik (Nilai & Rapor, Jadwal, Riwayat Izin/Sakit)

- **Tanggal**: 2026-09-04
- **Branch**: `akademik-v2`
- **Spec**: `.agents/specs/2026-09-04-fitur-ruang-orang-tua-akademik.md`
- **Plan**: `.agents/plans/2026-09-04-fitur-ruang-orang-tua-akademik.md`
- **Kickoff**: `.agents/kickoff/2026-09-04-fitur-ruang-orang-tua-akademik-kickoff.md`
- **Base Commit**: `22cb77f5` (`docs(akademik): kickoff file untuk fitur Ruang Orang Tua`)
- **Head Commit**: `d47294de` (`feat(akademik): buka kembali menu Ruang Orang Tua (Nilai, Jadwal, Riwayat Izin/Sakit Anak)`)

---

## 1. Apa yang Dikerjakan

Fitur baru Ruang Orang Tua Akademik berhasil dibangun secara menyeluruh menggantikan tautan stub `/dalam-pengembangan` yang disembunyikan sejak 2026-09-03. Seluruh 5 task diselesaikan berurutan dan terverifikasi 100% lulus uji:

1. **Task 1 (`ResolveAnakOrangTuaTrait`)**: `app/Domains/Akademik/Support/ResolveAnakOrangTuaTrait.php`
   - Membuat trait reusable `ResolveAnakOrangTuaTrait` berisi method `resolveAnakList(User $actor): Collection` dan `resolveAnakTerpilih(Collection $anakList, ?int $siswaIdDiminta): ?Siswa`.
   - Mengambil seluruh siswa yang berelasi dengan orang tua actor tanpa hambatan `TenantScope` (`withoutGlobalScope(TenantScope::class)` pada relasi `siswa`, `kelas`, `tahunAjaran`, dan `lembaga`).
   - Menerapkan pola "derive, don't validate": bila `siswa_id` tidak ada di request atau bukan milik actor (IDOR), resolver diam-diam fallback ke anak pertama (atau `null` bila belum memiliki anak).
   - Test unit: `tests/Unit/Support/ResolveAnakOrangTuaTraitTest.php` (5 tests passing).
   - Commit: `a98e54bb` `feat(akademik): trait ResolveAnakOrangTuaTrait untuk resolve anak milik orang tua + anak terpilih`.

2. **Task 2 (`NilaiAnakController` & View Nilai & Rapor Anak)**:
   - Membuat `app/Http/Controllers/Admin/NilaiAnakController.php` dengan method `index()` (daftar nilai formatif/sumatif semester terpilih + status pengajuan rapor) dan `unduhRapor()` (unduh cetak rapor PDF).
   - Membuat view `resources/views/admin/orang-tua/nilai-anak.blade.php` dengan token desain `text-ink`, `text-slate`, `bg-paper`, `font-display`, komponen `<x-panel>`, `<x-badge tone="...">`, dan Material Symbols `<x-icon name="...">` (`print`, `assessment`, `receipt`).
   - Mendaftarkan route di `routes/admin/orang-tua-akademik.php` dan diikutsertakan ke dalam `routes/admin.php`.
   - Test feature: `tests/Feature/Admin/NilaiAnakControllerTest.php` (6 tests passing, termasuk pengujian IDOR dan proteksi 403 tegas pada unduh rapor).
   - Commit: `367113eb` `feat(akademik): halaman Nilai & Rapor Anak untuk Ruang Orang Tua`.

3. **Task 3 (`JadwalAnakController` & View Jadwal Anak)**:
   - Membuat `app/Http/Controllers/Admin/JadwalAnakController.php` dengan method `index()`.
   - Mengambil jadwal pelajaran semester aktif untuk kelas anak terpilih, mengelompokkan berdasarkan hari dan mengurutkan berdasarkan jam mulai pelajaran secara berurutan.
   - Membuat view `resources/views/admin/orang-tua/jadwal-anak.blade.php` dengan layout mingguan, filter pemilihan anak, dan Material Symbols `<x-icon name="event">`.
   - Menambahkan route `admin.jadwal-anak.index` di `routes/admin/orang-tua-akademik.php`.
   - Test feature: `tests/Feature/Admin/JadwalAnakControllerTest.php` (3 tests passing).
   - Commit: `c224f959` `feat(akademik): halaman Jadwal Anak untuk Ruang Orang Tua`.

4. **Task 4 (`RiwayatIzinSakitAnakController` & View Riwayat Izin/Sakit Anak)**:
   - Membuat `app/Http/Controllers/Admin/RiwayatIzinSakitAnakController.php` dengan method `index()` dan validasi rentang tanggal (`dari_tanggal`, `sampai_tanggal`).
   - Query memfilter `Presensi` dengan status `izin` dan `sakit` pada rentang tanggal terpilih (default: bulan berjalan) dengan bypass `TenantScope` pada relasi `sesiPembelajaran` dan `mataPelajaran`.
   - Membuat view `resources/views/admin/orang-tua/riwayat-izin-sakit-anak.blade.php` dengan form filter tanggal + dropdown anak serta Material Symbols `<x-icon name="history">`.
   - Menambahkan route `admin.riwayat-izin-sakit-anak.index` di `routes/admin/orang-tua-akademik.php`.
   - Test feature: `tests/Feature/Admin/RiwayatIzinSakitAnakControllerTest.php` (4 tests passing).
   - Commit: `a8cca54b` `feat(akademik): halaman Riwayat Izin/Sakit Anak untuk Ruang Orang Tua`.

5. **Task 5 (Buka Kembali Sidebar & Verifikasi Full Suite)**:
   - Membuka komentar 3 menu di `resources/views/layouts/sidebar.blade.php` pada grup `Ruang Orang Tua` mengarah ke rute aktif: `admin.nilai-anak.index`, `admin.jadwal-anak.index`, `admin.riwayat-izin-sakit-anak.index` dengan mempertahankan ikon Lucide (`award`, `calendar-clock`, `clipboard-check`).
   - Membuat test baru `tests/Feature/SidebarOrangTuaAkademikTest.php` (memastikan menu muncul untuk role `orang_tua` dan tidak muncul untuk role lain).
   - Menyesuaikan `tests/Feature/SidebarPengelompokanTest.php` dan `tests/Feature/SidebarStubMenuHiddenTest.php` agar mencerminkan bahwa rute resmi sudah aktif dan tautan placeholder `/dalam-pengembangan` sudah tidak ada di sidebar.
   - Menjalankan Pint dan full test suite: **2,813 passed (7,646 assertions)**, 0 failures, durasi 609.45s.
   - Commit: `d47294de` `feat(akademik): buka kembali menu Ruang Orang Tua (Nilai, Jadwal, Riwayat Izin/Sakit Anak)`.

---

## 2. Keputusan Penting yang Diambil

1. **Perbedaan Filosofi Penanganan IDOR: Fallback Diam-diam vs Abort 403**:
   - Pada ketiga halaman indeks (`NilaiAnakController@index`, `JadwalAnakController@index`, `RiwayatIzinSakitAnakController@index`), manipulasi `siswa_id` pada query string diperlakukan dengan pola "derive, don't validate" (fallback diam-diam ke anak pertama yang valid). Ini menjaga UX orang tua tetap mulus tanpa menampilkan error 403 yang membingungkan saat ada tautan kedaluwarsa.
   - Sebaliknya, pada `NilaiAnakController::unduhRapor()`, akses WAJIB `abort_unless(..., 403)`. Karena ini adalah endpoint unduh dokumen privat (PDF), bila orang tua meminta rapor siswa yang bukan anaknya, sistem menolak tegas dengan 403 Forbidden untuk mencegah kebocoran dokumen.

2. **Penanganan `TenantScope` & Route Model Binding pada Pengguna Orang Tua**:
   - Akun orang tua memiliki `lembaga_id = null` karena orang tua bisa memiliki anak di lembaga yang berbeda di bawah yayasan.
   - Route Model Binding bawaan Laravel `{siswa}` akan menerapkan `TenantScope` (`where lembaga_id is null`), sehingga request orang tua otomatis menghasilkan HTTP 404 sebelum controller dieksekusi.
   - Solusi: Parameter pada method `unduhRapor(Request $request, Siswa|int|string $siswa)` dibaca sebagai ID numerik, lalu entitas `Siswa` diambil dari `$anakList->firstWhere('id', $siswaId)`.
   - Di `ResolveAnakOrangTuaTrait`, eager loading `kelas.tahunAjaran` dibungkus dengan `withoutGlobalScope(TenantScope::class)` agar pemanggilan `$kelas->tahunAjaran->nama` di dalam `RaporPdfDataBuilder` tidak menghasilkan null.

3. **Konsistensi Dua Sistem Ikon Berbeda**:
   - Sidebar navigasi aplikasi menggunakan paket Lucide icon (`<x-dynamic-component :component="'lucide-'.$icon">`), sehingga tetap menggunakan ikon `award`, `calendar-clock`, dan `clipboard-check`.
   - Konten di dalam halaman menggunakan Material Symbols SVG (`<x-icon name="...">`), menggunakan nama ikon yang terdaftar di `resources/views/components/icon.blade.php` (`receipt`, `print`, `assessment`, `event`, `history`).

4. **Penyesuaian Tes Sidebar Existing**:
   - `SidebarStubMenuHiddenTest` dan `SidebarPengelompokanTest` sebelumnya ditulis pada 2026-09-03 untuk menguji penonaktifan stub placeholder. Kedua test disesuaikan agar mengonfirmasi rute aktif telah menggantikan tautan stub `/dalam-pengembangan`.

---

## 3. Hal yang Masih Perlu Direview Manusia / Claude

1. **Item yang Sengaja Dikecualikan Sesuai Scope**:
   - **Sisi Siswa**: Halaman Nilai & Rapor Siswa, Jadwal Siswa, dan Presensi Siswa tetap ditunda untuk paket terpisah menyusul.
   - **Form Pengajuan Izin/Sakit**: Halaman Riwayat Izin/Sakit Anak murni berstatus read-only; form pengajuan izin/sakit baru dari orang tua tidak dibuat.
   - **Model Presensi**: Tidak ditambahkan `BelongsToTenant` karena tenancy dibatasi lewat relasi `sesiPembelajaran`.
   - **Bottom Navigation Mobile**: Tidak diubah pada paket ini (di luar cakupan).

2. **Git State**:
   - **Branch**: `akademik-v2` (tetap di branch yang sama, tidak berpindah branch).
   - **Status**: Seluruh implementasi selesai, terformat rapi dengan Pint, dan seluruh test suite lulus (2,813 passed, 0 failures).
