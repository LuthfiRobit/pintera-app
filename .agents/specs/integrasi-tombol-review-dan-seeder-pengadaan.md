# Spesifikasi: Integrasi Tombol Review Approval & Seeder Demo Pengadaan

- **Slug:** `integrasi-tombol-review-dan-seeder-pengadaan`
- **Tanggal:** 16 Agustus 2026
- **Status:** Draft / Ready for Implementation

---

## 1. Tujuan
Mempermudah proses verifikasi dan persetujuan pengadaan sarana & prasarana dengan:
1. Menyediakan tombol **"Review & Putuskan Usulan"** pada halaman Detail Pengajuan (`proposal/show.blade.php`) yang hanya aktif dan muncul jika pengguna yang login memenuhi syarat sebagai approver pada langkah persetujuan aktif (`currentStep`).
2. Mengharmonisasi otorisasi controller review persetujuan (`ApprovalPengadaanController`) agar dapat diakses oleh approver internal sekolah (Kepala Sekolah) maupun yayasan (Bendahara / Super Admin).
3. Menyederhanakan `SarprasPengadaanDemoSeeder.php` menjadi **1 data pengadaan aktif dengan 2 item belanja** yang siap diuji coba persetujuannya secara sekuensial (Step 1 Kepsek $\rightarrow$ Step 2 Bendahara Yayasan).

---

## 2. Scope

### In Scope:
1. **Frontend Detail Proposal (`resources/views/portals/lembaga/pengadaan/proposal/show.blade.php`):**
   - Menambahkan helper/pengecekan otorisasi approver langkah aktif.
   - Menampilkan tombol aksi "Review & Putuskan Usulan" jika user memenuhi syarat approver langkah aktif.
   - Menampilkan status informatif jika proposal sedang menunggu approver lain.
2. **Backend Controller & Resolver (`ApprovalPengadaanController.php` & `ApproverResolverService.php`):**
   - Memperluas otorisasi method `review` dan `decision` agar mendukung `pengadaan.approval.internal` (Kepala Sekolah) dan `pengadaan.approval.yayasan` (Bendahara Yayasan).
   - Menambahkan dukungan bypass/override untuk peran `yayasan_super_admin` / `super_admin`.
3. **Database Seeder (`database/seeders/SarprasPengadaanDemoSeeder.php`):**
   - Menyediakan 1 data usulan pengadaan aktif dengan 2 item barang:
     - Item 1: *Laptop ASUS ExpertBook* (Unit - Rp 9.500.000)
     - Item 2: *Kursi Belajar Siswa* (Batch - 10 unit x Rp 250.000 = Rp 2.500.000)
   - Memastikan akun dummy `kepsek@sistem.test` (role `kepala_sekolah`, lembaga terhubung) dan `bendahara.yayasan@sistem.test` (role `bendahara_yayasan`) terkonfigurasi dengan tepat.

### Out of Scope:
- Modifikasi skema tabel database (tidak ada migrasi baru yang diperlukan).
- Modifikasi kalkulasi formula LPJ atau rekonsiliasi kas.

---

## 3. Acceptance Criteria
1. **Pemeriksaan Otorisasi Step 1 (Kepala Sekolah):**
   - Saat login sebagai `kepsek@sistem.test` dan membuka detail usulan pengadaan (`/admin/pengadaan/proposal/{id}`), muncul tombol **"Review & Putuskan Usulan"**.
   - Mengklik tombol tersebut membuka halaman review per-item `/admin/pengadaan/inbox/{id}/review`.
   - Kepala Sekolah dapat menyetujui kedua item dan mengirim keputusan tanpa error *"Anda tidak memiliki hak akses"*.
   - Proposal otomatis berpindah ke **Step 2 (Persetujuan & Pencairan Yayasan)**.
2. **Pemeriksaan Otorisasi Step 2 (Bendahara Yayasan):**
   - Sebelum Step 1 disetujui, jika Bendahara Yayasan membuka detail proposal, sistem memberi info *"Menunggu Persetujuan: Kepala Sekolah (Langkah 1)"*.
   - Setelah Step 1 disetujui, saat login sebagai `bendahara.yayasan@sistem.test`, muncul tombol **"Review & Putuskan Usulan"**.
   - Bendahara Yayasan dapat menyetujui proposal hingga berstatus `Approved`.
3. **Pengujian Otomatis:**
   - Seluruh test suite pengadaan dan sarpras (`Pengadaan`, `Workflow`, `Sarpras`) lulus 100%.
