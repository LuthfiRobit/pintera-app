# Handoff Log: Rebranding Data Demo Pintera

**Tanggal:** 2026-08-21  
**Branch:** `rbac-v2`  
**Spec Referensi:** `.agents/specs/2026-08-21-rebranding-data-demo-pintera.md`  
**Plan Referensi:** `.agents/plans/2026-08-21-rebranding-data-demo-pintera.md`  

---

## 1. Apa yang Dikerjakan

Telah selesai dilakukan rebranding menyeluruh dan penyederhanaan data demo/seeders di Pintera:
1. **Rebranding Yayasan & Lembaga ke "Pintera" (Task 1, Commit `72a9ce8`):**
   - Mengubah `Yayasan Permata Kraksaan` menjadi `Yayasan Pintera` (`info@pintera.sch.id`, `https://pintera.sch.id`).
   - Mengubah 4 jenjang lembaga menjadi `KBIT PINTERA`, `TKIT PINTERA`, `SDIT PINTERA`, `SMPIT PINTERA` dengan kode lembaga `KBITPTR`, `TKITPTR`, `SDITPTR`, `SMPITPTR`.
   - Menyelaraskan domain institusional ke `@pintera.sch.id` dan nama rekening bank institusi.
   - Memperbaiki helper `cariAdminKeuangan` di `KeuanganDemoSeeder` agar me-match berdasarkan `bentuk_pendidikan` (`kb`, `tk`, `sd`, `smp`) ke domain `@demo.test`.

2. **Pola Email Pendek Staff & RBAC (Task 2, Commit `fea0a5f`):**
   - Menstandarkan seluruh email akun demo staf/RBAC ke pola `{peran}.{kode-lembaga}@demo.test` (misal `kepsek.kb@demo.test`, `adm.kb@demo.test`, `keuangan.kb@demo.test`, `kurikulum.kb@demo.test`, `guru.kb1@demo.test`, `siswa.kb@demo.test`, `ortu.kb@demo.test`, `psikolog.pool@demo.test`).
   - Memperbarui 7 seeder: `EssentialUserSeeder.php`, `UserSeeder.php`, `GuruSeeder.php`, `SiswaSeeder.php`, `OrangTuaKaryawanSeeder.php`, `SarprasPengadaanDemoSeeder.php`, `PendampinganSeeder.php`.

3. **Sinkronisasi Demo Parents Keuangan (Task 3, Commit `5f637ac`):**
   - Menyelaraskan array `$demoParents` di `KeuanganDemoSeeder.php` dengan NIK map demo parents dari `OrangTuaKaryawanSeeder.php` ke domain `@demo.test`.

4. **Standarisasi Email PPDB & Pendaftar (Task 4, Commit `da2799e`):**
   - Mengubah email search key demo pendaftaran dari `@example.test` ke `@demo.test` (`wali.menunggu@demo.test`, `wali.diterima@demo.test`, `wali.ditolak@demo.test`, `wali.cicilan@demo.test`) secara konsisten di seluruh rantai 9 seeder PPDB: `AkunPendaftarSeeder.php`, `PendaftaranSeeder.php`, `PembayaranSeeder.php`, `SkPpdbSeeder.php`, `TagihanSeeder.php`, `SkemaCicilanSeeder.php`, `CicilanSeeder.php`, `DokumenPendaftaranSeeder.php`, `HasilSeleksiSeeder.php`.

5. **Nama Manusia Netral (Task 5, Commit `5933207`):**
   - Menghapus seluruh honorifik keagamaan ("Ustadz" / "Ustadzah") dari data demo staff, kepala sekolah, guru, dan bendahara di `UserSeeder.php`, `GuruSeeder.php`, `LembagaSeeder.php`, `SarprasPengadaanDemoSeeder.php`, dan `EssentialUserSeeder.php`.

6. **Verifikasi Penuh (Task 6):**
   - Verifikasi login akun representatif via Hash check: 100% OK.
   - Grep menyeluruh direktori `database/seeders/`: 100% bersih dari residu pola lama.
   - Full test suite: **1895 passed (5789 assertions), 0 failures**.

---

## 2. Keputusan Penting yang Diambil

1. **Sinkronisasi `EssentialUserSeeder` dengan Lembaga KB:**
   - `EssentialUserSeeder` yang membuat akun representatif `kepsek.kb@demo.test`, `adm.kb@demo.test`, dll., disinkronkan ke default lembaga `20223311` (KBIT) dan nama-nama yang konsisten dengan `UserSeeder` (`Aisyah, S.Psi.`, dll.), sehingga `nama_kepala_sekolah` di `LembagaSeeder` dan `name` di `UserSeeder` identik dan sinkron.
2. **Preservasi Alamat Fisik / Wilayah Administrasi:**
   - Alamat jalan, RT/RW, kelurahan, dan kecamatan (`Kraksaan`, `Kabupaten Probolinggo`, `Jawa Timur`) tetap dipertahankan sesuai data riil geografis yayasan, sementara nama yayasan dan sekolah menjadi `Pintera`.
3. **Preservasi Role Names Internal:**
   - Role names di database/RBAC (`admin_keuangan`, `admin_administrasi`, `admin_akademik`, dll.) tetap utuh tanpa modifikasi, hanya string kosmetik data demo yang diubah.

---

## 3. Hal yang Perlu Direview Manusia / Claude

1. **State Git Saat Ini:**
   - Branch: `rbac-v2`
   - Daftar Commit:
     - `72a9ce8` — `feat(seeder): rebranding Yayasan & Lembaga ke Pintera, perbaiki mapping kode lembaga di KeuanganDemoSeeder`
     - `fea0a5f` — `feat(seeder): pendekkan email staff/RBAC ke pola role.kode@demo.test`
     - `5f637ac` — `chore(seeder): sinkronkan email demo orang tua di KeuanganDemoSeeder dengan pola @demo.test`
     - `da2799e` — `feat(seeder): standarisasi email PPDB ke pola wali.*@demo.test dan pendaftar.*@demo.test`
     - `5933207` — `feat(seeder): ganti nama staf dari honorifik keagamaan ke pola netral`
2. **Kesiapan:**
   - Seluruh 6 task dari plan `.agents/plans/2026-08-21-rebranding-data-demo-pintera.md` sudah 100% tuntas dan lulus pengujian full test suite tanpa regresi.
