# Plan: Fitur Edit & Pengajuan Ulang (Resubmit) Usulan Pengadaan Pasca Revisi

## Checklist Implementasi
- [ ] **Step 1: Domain Action & Request**
  - Buat `app/Http/Requests/Pengadaan/UpdatePengajuanRequest.php`
  - Buat `app/Domains/Pengadaan/Actions/UpdatePengajuanAction.php`
  - Perbarui `app/Domains/Pengadaan/Actions/SubmitPengajuanAction.php` untuk menangani resubmit proposal revisi (reset step workflow ke step 1).
- [ ] **Step 2: Controller & View Edit**
  - Tambahkan method `edit()` dan `update()` pada `app/Http/Controllers/Lembaga/Pengadaan/PengajuanPengadaanController.php`.
  - Buat template `resources/views/portals/lembaga/pengadaan/proposal/edit.blade.php`.
- [ ] **Step 3: UI Enhancement (Show & Daftar)**
  - Perbarui `resources/views/portals/lembaga/pengadaan/proposal/show.blade.php` untuk menampilkan banner revisi, tombol "Edit Usulan", dan tombol "Ajukan Ulang".
  - Perbarui `resources/views/portals/lembaga/pengadaan/proposal/_daftar.blade.php` untuk menampilkan aksi "Edit Usulan" saat status `Draft` atau `RevisionRequired`.
- [ ] **Step 4: Testing & Verifikasi**
  - Buat `tests/Feature/Pengadaan/ProposalEditAndResubmitTest.php` untuk memvalidasi flow: Proposal -> Disubmit -> Kepsek Minta Revisi -> Pengusul Buka Edit -> Ubah Item -> Resubmit -> Masuk lagi ke Step 1 Kepsek.
  - Jalankan seluruh unit & feature test suite.
