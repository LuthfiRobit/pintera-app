# Plan: Perbaikan Alur Konversi Aset Sarpras dari LPJ Terverifikasi

## Checklist Eksekusi
- [x] **Step 1: Permission & Role Hardening**
  - Berikan permission `pengadaan.proposal.*` & `pengadaan.lpj.submit` ke role `admin_administrasi` di `PengadaanPermissionSeeder.php`.
- [x] **Step 2: UI Staging Inventory State Handling**
  - Buat conditional view di `staging-inventory.blade.php` untuk menampilkan form saat item pending, atau banner sukses + list barang terdaftar saat item sudah `converted`.
- [x] **Step 3: Action Buttons & Links**
  - Update `proposal/show.blade.php` dan `proposal/_daftar.blade.php` untuk menampilkan "Lihat Aset di Sarpras" jika seluruh barang sudah dikonversi.
- [x] **Step 4: Flash Notification & Scoped Counters**
  - Pasang handler `session('success')` di `sarpras/aset/index.blade.php`.
  - Rapikan query KPI counter di `AsetBarangController::index` agar mendukung scope lembaga dan yayasan.
- [x] **Step 5: Automated Testing**
  - Buat feature test `tests/Feature/Pengadaan/InventoryConversionFlowTest.php` dan jalankan test suite untuk memastikan semua skenario lulus.
