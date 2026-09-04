# Handoff Log: Fitur Ruang Siswa Akademik (Nilai & Rapor, Jadwal Pelajaran, Presensi Saya)

- **Tanggal**: 2026-09-04
- **Branch**: `akademik-v2`
- **Spec**: `.agents/specs/2026-09-04-fitur-ruang-siswa-akademik.md`
- **Plan**: `.agents/plans/2026-09-04-fitur-ruang-siswa-akademik.md`
- **Kickoff**: `.agents/kickoff/2026-09-04-fitur-ruang-siswa-akademik-kickoff.md`
- **Base Commit**: `c304f538` (`docs(akademik): kickoff file untuk fitur Ruang Siswa`)
- **Head Commit**: `e8a744fc` (`feat(akademik): buka kembali menu Ruang Siswa (Nilai & Rapor, Jadwal Pelajaran, Presensi Saya)`)

---

## 1. Apa yang Dikerjakan

Fitur self-service Ruang Siswa Akademik berhasil dibangun menggantikan placeholder `/dalam-pengembangan` yang dikomentari sejak 2026-09-03. Seluruh 4 task pada plan diselesaikan berurutan dan terverifikasi 100% lulus uji:

1. **Task 1 (`NilaiRaporSiswaController` & View Nilai & Rapor)**:
   - Membuat file route `routes/admin/siswa-akademik.php` dan mengikutsertakannya ke dalam `routes/admin.php`.
   - Membuat `app/Http/Controllers/Admin/NilaiRaporSiswaController.php` dengan method `index()` (daftar nilai formatif/sumatif semester aktif/terpilih + status pengajuan rapor) dan `unduhRapor()` (unduh cetak PDF rapor via `RaporPdfDataBuilder`).
   - Membuat minimal view `resources/views/admin/siswa-akademik/nilai-rapor.blade.php`.
   - Menambahkan test feature `tests/Feature/Admin/NilaiRaporSiswaControllerTest.php` (4 tests passing, mencakup nilai semester, regresi identitas siswa A vs B, serta otorisasi unduh rapor disetujui vs belum disetujui).
   - Commit: `9c9b3e46` `feat(akademik): halaman Nilai & Rapor untuk Ruang Siswa`.

2. **Task 2 (`JadwalPelajaranSiswaController` & View Jadwal Pelajaran)**:
   - Membuat `app/Http/Controllers/Admin/JadwalPelajaranSiswaController.php` dengan method `index()`.
   - Mengambil jadwal pelajaran kelas siswa pada semester aktif/terpilih dengan bypass `TenantScope` pada `JadwalPelajaran` dan eager-loading relasi `mataPelajaran`, `guru`, `ruang`, mengelompokkan berdasarkan `hari` dengan konstanta `HARI_ORDER`, lalu mengurutkan berdasar jam mulai.
   - Menambahkan route `admin.jadwal-pelajaran-saya.index` ke `routes/admin/siswa-akademik.php`.
   - Membuat minimal view `resources/views/admin/siswa-akademik/jadwal-pelajaran.blade.php`.
   - Menambahkan test feature `tests/Feature/Admin/JadwalPelajaranSiswaControllerTest.php` (2 tests passing).
   - Commit: `9cc6212d` `feat(akademik): halaman Jadwal Pelajaran untuk Ruang Siswa`.

3. **Task 3 (`PresensiSayaController` & View Presensi Saya)**:
   - Membuat `app/Http/Controllers/Admin/PresensiSayaController.php` dengan method `index()` dan filter rentang tanggal (default: bulan ini). Menampilkan SEMUA status presensi (hadir, sakit, izin, alpa, terlambat).
   - Menambahkan route `admin.presensi-saya.index` ke `routes/admin/siswa-akademik.php`.
   - Membuat minimal view `resources/views/admin/siswa-akademik/presensi-saya.blade.php`.
   - Menambahkan test feature `tests/Feature/Admin/PresensiSayaControllerTest.php` (3 tests passing).
   - Commit: `52a29bc2` `feat(akademik): halaman Presensi Saya untuk Ruang Siswa`.

4. **Task 4 (Buka Kembali Menu Sidebar & Verifikasi Full Suite)**:
   - Membuka komentar 3 menu Ruang Siswa di `resources/views/layouts/sidebar.blade.php` mengarah ke route resmi: `admin.nilai-rapor-saya.index`, `admin.jadwal-pelajaran-saya.index`, dan `admin.presensi-saya.index` dengan mempertahankan guard `Auth::user()->hasRole('siswa')`.
   - Menyesuaikan `tests/Feature/SidebarStubMenuHiddenTest.php` dan `tests/Feature/SidebarPengelompokanTest.php` untuk memastikan menu Ruang Siswa aktif dan tautan placeholder `/dalam-pengembangan` tidak ada lagi di sidebar.
   - Menjalankan Pint dan full test suite sendirian: **2,822 passed (7,671 assertions)**, 0 failures, durasi 531.04s.
   - Commit: `e8a744fc` `feat(akademik): buka kembali menu Ruang Siswa (Nilai & Rapor, Jadwal Pelajaran, Presensi Saya)`.

---

## 2. Keputusan Penting yang Diambil

1. **Akses Data Siswa Tanpa Trait Khusus**:
   - Berbeda dari modul Ruang Orang Tua yang memerlukan `ResolveAnakOrangTuaTrait` (karena satu akun orang tua dapat memiliki banyak anak), akun siswa berelasi 1:1 langsung melalui `$request->user()->siswa`. Relasi `User::siswa()` (`HasOneThrough`) sudah membungkus `withoutGlobalScope(TenantScope::class)` di level model sehingga aman dan bebas dari isu bypass berulang.

2. **Tanpa Parameter Route pada Unduh Rapor (Bebas IDOR)**:
   - `NilaiRaporSiswaController::unduhRapor()` tidak menerima parameter ID siswa (`/admin/nilai-rapor-saya/unduh-rapor`). Data siswa selalu diambil langsung dari `$request->user()->siswa`, sehingga tidak ada permukaan celah IDOR.

3. **Perilaku `TenantScope` pada Presensi Siswa**:
   - Tidak seperti akun orang tua yang memiliki `lembaga_id = null`, akun siswa memiliki `lembaga_id` riil yang terhubung dengan lembaganya.
   - Terbukti secara empiris melalui pengujian bahwa query `Presensi::query()->whereHas('sesiPembelajaran', ...)` dapat memuat data presensi tanpa perlu bypass `withoutGlobalScope(TenantScope::class)` pada `sesiPembelajaran`.

4. **Penanganan Enum `StatusPresensi` pada View Blade**:
   - Model `Presensi` meng-cast kolom `status` ke BackedEnum `StatusPresensi`. Pemanggilan fungsi bawaan PHP string seperti `ucfirst($item->status)` memicu `TypeError`. Pada view `presensi-saya.blade.php`, penanganan disesuaikan menggunakan `$item->status?->label() ?? ucfirst((string) $item->status)`.

5. **Kondisi Guard Sidebar Konsisten**:
   - Guard menu Ruang Siswa pada sidebar tetap menggunakan `Auth::user()->hasRole('siswa')` persis seperti baris aslinya sebelum dikomentari.

6. **Polesan UI/UX Menyeluruh Sesuai Desain Sistem Pintera (TailAdmin)**:
   - Ketiga view (`nilai-rapor.blade.php`, `jadwal-pelajaran.blade.php`, `presensi-saya.blade.php`) telah dipoles secara menyeluruh menggunakan komponen standar Pintera: `<x-panel>`, `<x-badge>`, `<x-icon>`, tipografi `font-display` (Outfit), warna token `text-ink`, `text-slate`, `bg-paper`, serta kartu dan empty state yang interaktif dan serasi dengan Ruang Orang Tua.

7. **Standardisasi Tampilan Jadwal: List Horizontal + Time Pill Mono + Toggle Matriks Mingguan**:
   - Menyamakan format tampilan jadwal antara Ruang Siswa dan Ruang Orang Tua.
   - **Tampilan Daftar (List Mode)**: Menggunakan baris horizontal memanjang (`space-y-3`) per hari dengan time pill presisi mono (`<span class="... font-mono ..."><x-icon name="schedule" /> 07:30 - 08:05</span>`).
   - **Tampilan Matriks (Matrix Mode)**: Menyediakan segmented control toggle via Alpine.js (`x-data="{ viewMode: 'list' }"`) untuk beralih instan ke Tampilan Matriks Mingguan (kolom multi-hari sejajar yang menampilkan seluruh sesi belajar mingguan secara terorganisir).

8. **Highlight "Hari Ini" & Fitur Auto-Scroll (List & Matriks)**:
   - Mendeteksi hari saat ini secara dinamis melalui `\App\Enums\Hari::fromCarbonDayOfWeek(now()->dayOfWeek)->value`.
   - **Pembeda Visual**: Panel/kolom hari ini ditandai dengan badge solid `HARI INI`, border aktif `border-2 border-brand-500`, shadow aksen, dan ring fokus `ring-4 ring-brand-500/10`.
   - **Auto-Scroll Halus**: Alpine.js secara otomatis menggulirkan viewport ke jadwal hari ini (`scrollIntoView({ behavior: 'smooth', block: 'center' })` pada List Mode, dan `inline: 'center'` pada Matrix Mode) setelah jeda render awal atau saat beralih mode.
   - **Quick Action Shortcut**: Menyediakan tombol shortcut interaktif "Fokus ke Jadwal Hari Ini" pada filter card serta pesan informatif bila hari ini akhir pekan/tidak ada KBM.

---

## 3. Hal yang Masih Perlu Direview Manusia / Claude

1. **Item di Luar Scope (Sengaja Tidak Disentuh)**:
   - **DashboardController & Widget**: Widget ringkasan dashboard siswa existing tetap berjalan normal tanpa perubahan.
   - **Bottom Navigation Mobile**: Tidak diubah (di luar cakupan tugas ini).

2. **Git State**:
   - **Branch**: `akademik-v2` (tetap di branch kerja tanpa perpindahan).
   - **Hasil Uji**: Full test suite bersih 100% (**2,822 passed**, 0 failures).
