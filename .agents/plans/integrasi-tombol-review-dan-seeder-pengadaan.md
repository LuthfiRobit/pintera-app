# Plan: Integrasi Tombol Review Approval & Seeder Demo Pengadaan

- **Spec:** [`.agents/specs/integrasi-tombol-review-dan-seeder-pengadaan.md`](file:///d:/laragon/www/pintera-app/.agents/specs/integrasi-tombol-review-dan-seeder-pengadaan.md)
- **Status:** Completed

---

## Checklist Implementasi

- [x] **Task 1: Update Approver Resolver & Controller Authorization**
  - [x] Tambahkan dukungan Super Admin bypass pada `App\Domains\Workflow\Services\ApproverResolverService`.
  - [x] Perbarui otorisasi di `App\Http\Controllers\Yayasan\Pengadaan\ApprovalPengadaanController` untuk method `review` dan `decision` agar menerima `pengadaan.approval.internal` (Kepsek), `pengadaan.approval.yayasan` (Bendahara Yayasan), atau role Super Admin.
  - [x] Tambahkan helper method di model `PengajuanPengadaan` atau user helper untuk mendeteksi apakah user saat ini dapat mereview langkah aktif: `canBeApprovedBy(User $user)`.

- [x] **Task 2: Update View Detail Proposal (`proposal/show.blade.php`)**
  - [x] Tambahkan tombol **"Review & Putuskan Usulan"** pada header jika `$proposal->canBeApprovedBy(auth()->user())`.
  - [x] Tampilkan info status/approver aktif pada kartu informasi stepper jika user yang login bukan approver pada langkah saat ini.

- [x] **Task 3: Refactor Seeder Demo Pengadaan (`SarprasPengadaanDemoSeeder.php`)**
  - [x] Pastikan akun `kepsek@sistem.test` memiliki `lembaga_id` yang sesuai, role `kepala_sekolah`, dan permission `pengadaan.approval.internal`.
  - [x] Pastikan akun `bendahara.yayasan@sistem.test` memiliki role `bendahara_yayasan` dan permission `pengadaan.approval.yayasan`.
  - [x] Siapkan tepat **1 proposal pengadaan aktif** dengan **2 item belanja** (Unit: Laptop ASUS & Batch: Kursi Belajar Siswa) berstatus `Submitted` pada Step 1 (Kepala Sekolah).
  - [x] Jalankan seeder via `php artisan db:seed --class=SarprasPengadaanDemoSeeder`.

- [x] **Task 4: Automated Testing & Verifikasi**
  - [x] Tulis/perbarui feature test untuk skenario:
    1. Kepala Sekolah membuka detail proposal dan melakukan review/approval Step 1.
    2. Bendahara Yayasan melanjutkan review/approval Step 2 hingga proposal `Approved`.
  - [x] Jalankan seluruh test suite Pengadaan, Workflow, dan Sarpras.

- [x] **Task 5: Serah Terima & Handoff Log**
  - [x] Tulis `.agents/logs/integrasi-tombol-review-dan-seeder-pengadaan.md`.
