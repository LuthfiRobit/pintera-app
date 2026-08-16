# Spec: Perbaikan Alur Konversi Aset Sarpras dari LPJ Terverifikasi

## 1. Tujuan
Memastikan alur konversi aset pengadaan ke master sarpras berjalan dengan mulus tanpa kendala permission atau ambiguity status, serta memberikan feedback UI yang jelas (flash notification & status tracking barang yang sudah diterbitkan).

## 2. Scope
- **In-Scope**:
  - Penambahan hak akses `pengadaan.proposal.*` dan `pengadaan.lpj.submit` untuk role `admin_administrasi` pada `PengadaanPermissionSeeder`.
  - Penanganan status barang LPJ yang sudah dikonversi vs belum dikonversi pada `staging-inventory.blade.php`.
  - Update tombol aksi pada `proposal/show.blade.php` dan `proposal/_daftar.blade.php` sehingga berubah menjadi "Lihat Aset di Sarpras" setelah seluruh item berstatus `converted`.
  - Dukungan flash message `session('success')` pada `portals/lembaga/sarpras/aset/index.blade.php`.
  - Perbaikan query agregat KPI di `AsetBarangController::index` agar mendukung multi-tenant (Lembaga & Yayasan).
  - Feature test otomatis `InventoryConversionFlowTest`.
- **Out-of-Scope**:
  - Perubahan skema tabel database `lpj_pengadaan` atau `aset_barang`.

## 3. Acceptance Criteria
1. Role `admin_administrasi` dapat mengakses halaman staging konversi sarpras dan memproses `convert-inventory`.
2. Setelah konversi berhasil, user diarahkan ke `admin.sarpras.aset.index` dengan flash notification hijau yang muncul.
3. Halaman `staging-inventory` menampilkan banner "Inventarisasi Selesai Diterbitkan" dan badge "Terdaftar di Sarpras" jika seluruh barang sudah dikonversi.
4. Tombol pada detail proposal dan tabel usulan beralih menjadi "Lihat Aset di Sarpras" dan tidak lagi memicu form konversi ulang.
5. Seluruh feature test terkait pengadaan dan sarpras lulus 100%.
