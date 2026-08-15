# Spec: Perbaikan UI Halaman Admin Virtual Account

- **Slug**: `2026-08-15-perbaikan-ui-admin-virtual-account`
- **Tanggal**: 2026-08-15
- **Status**: Approved

## 1. Tujuan
Memperbaiki dan menyempurnakan tampilan antarmuka (UI/UX) pada halaman Admin Virtual Account agar sejalan dengan pola standar aplikasi Pintera (mengadopsi komponen dari halaman Jenis Tagihan dan Jadwal Pelajaran).

## 2. Ruang Lingkup Perubahan (In Scope)

### A. Halaman Index Virtual Account (`resources/views/admin/virtual-account/`)
1. **KPI Metric Cards**: Menambahkan 3 card ringkasan informasi di atas card filter:
   - Total Siswa ber-VA (Count siswa dengan permanent VA)
   - Total Saldo Terkumpul (Sum saldo wallet siswa ber-VA)
   - Belum Ada VA (Count siswa aktif tanpa VA)
2. **Tombol Export & Generate**: Menambahkan icon (`x-icon name="description"` / `x-icon name="add"`) dan tooltip (`title="..."`).
3. **Filter Kelas**: Mengelompokkan option kelas per Tahun Ajaran (`<optgroup label="Tahun Ajaran...">`).
4. **Tabel & Dropdown Aksi (Kiri)**:
   - Kolom aksi diletakkan di sisi kiri (`sticky left-0 bg-white`) menggunakan `<x-table-actions>` dan `<x-dropdown-link>`.
   - Dropdown aksi berisi:
     - "Lihat Riwayat" -> membuka modal riwayat pembayaran VA.
     - "Top-up Saldo" -> membuka modal topup manual (UI only).
5. **Kolom Nama Siswa**: Menampilkan nama siswa (`font-bold text-gray-900`) dan NIS (`text-xs text-gray-400 font-mono`) di bawahnya.

### B. Modal-modal di Halaman Index
1. **Style Modal**: Menyesuaikan seluruh modal (`_riwayat-modal`, `_generate-modal`, `_topup-modal`) dengan style modal `jadwal-pelajaran` (backdrop blur/opacity transition, rounded-2xl, shadow-elevated, cancel icon header, action buttons).
2. **Modal Top-up Saldo (Baru)**:
   - File: `_topup-modal.blade.php`.
   - Form input: Nama Siswa (disabled/readonly), Nomor VA (disabled/readonly), Nominal Top-up (Rp), Catatan/Keterangan.
   - Sifat: Tampilan UI saja (tidak ada backend submit / disimulasikan alert/toast sukses/batal).
3. **Modal Generate VA (Perbaikan)**:
   - Filter kelas di dalam modal dikelompokkan per Tahun Ajaran (style sama dengan filter index).
   - Seluruh baris list calon siswa dapat diklik (`@click="toggleSiswa(siswa.id)"`) untuk mencentang checkbox.
   - Ditambahkan checkbox **"Pilih Semua"** di header table modal untuk toggle seluruh calon siswa yang sedang tampil.

## 3. Out of Scope
- Tidak mengubah logika endpoint otentikasi BRI / webhook payment inbound.
- Tidak membuat backend controller untuk topup manual (hanya tampilan modal).

## 4. Acceptance Criteria
- Halaman index `/admin/virtual-account` menampilkan 3 card KPI di atas filter.
- Filter kelas di index dan modal generate terkelompok per Tahun Ajaran.
- Dropdown aksi berada di kolom kiri table dengan 2 opsi (Lihat Riwayat & Top-up Saldo).
- Modal Top-up Saldo muncul saat diklik dari aksi table dan menampilkan nama + nomor VA siswa.
- Modal Riwayat dan Generate VA memiliki style yang selaras dengan modal Jadwal Pelajaran.
- Pada modal Generate VA (mode manual), baris siswa dapat diklik langsung dan checkbox "Pilih Semua" berfungsi.
- Semua automated test berjalan lancar (100% pass, tanpa regresi).
