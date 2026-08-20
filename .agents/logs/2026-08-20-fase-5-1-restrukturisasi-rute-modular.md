# Handoff Log: FASE 5.1 Restrukturisasi Rute Modular

**Tanggal:** 2026-08-20  
**Branch:** `rbac-v2`  
**Spec:** [`.agents/specs/2026-08-20-fase-5-1-restrukturisasi-rute-modular-design.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-20-fase-5-1-restrukturisasi-rute-modular-design.md)  
**Plan:** [`.agents/plans/2026-08-20-fase-5-1-restrukturisasi-rute-modular.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-20-fase-5-1-restrukturisasi-rute-modular.md)  
**Baseline Test Suite:** 1861 passed (commit `a7ce013` / `2f285cf`)  
**Final Test Suite:** 1861 passed (5721 assertions, 0 failed)

---

## 1. Apa yang Dikerjakan

Berhasil melakukan modularisasi rute monolitik `routes/admin.php` (sebelumnya 368 baris mencakup ~15 domain dan grup non-admin) menjadi file-file kecil per modul tanpa mengubah satu pun URI, nama route, method, middleware, ataupun urutan relatif route.

### File Baru yang Dibuat (15 file):
1. [`routes/admin/roles.php`](file:///d:/laragon/www/pintera-app/routes/admin/roles.php) — Manajemen role & permissions catalog, CRUD user, toggle-active.
2. [`routes/admin/whatsapp-template.php`](file:///d:/laragon/www/pintera-app/routes/admin/whatsapp-template.php) — Template pesan WA sistem.
3. [`routes/admin/lembaga.php`](file:///d:/laragon/www/pintera-app/routes/admin/lembaga.php) — Profil lembaga, data periodik, ekstrakurikuler, layanan khusus, program inklusi, pengaturan yayasan.
4. [`routes/admin/guru-data.php`](file:///d:/laragon/www/pintera-app/routes/admin/guru-data.php) — CRUD guru, riwayat pendidikan, sertifikasi, jabatan tambahan, master jenis karyawan & jabatan.
5. [`routes/admin/akademik-master.php`](file:///d:/laragon/www/pintera-app/routes/admin/akademik-master.php) — Mata pelajaran, kelas, kalender akademik, hari aktif, tahun ajaran, semester, pola jam, jam pelajaran, jadwal pelajaran.
6. [`routes/admin/siswa.php`](file:///d:/laragon/www/pintera-app/routes/admin/siswa.php) — CRUD siswa, orang tua, karyawan, reset password, import siswa, SPMB daftar siswa.
7. [`routes/admin/spmb.php`](file:///d:/laragon/www/pintera-app/routes/admin/spmb.php) — Gelombang PPDB, jalur PPDB, formulir field, dokumen syarat, seleksi, konfigurasi duplikasi, verifikasi pendaftaran, nilai, keputusan, tagihan susulan, SK PPDB.
8. [`routes/admin/keuangan.php`](file:///d:/laragon/www/pintera-app/routes/admin/keuangan.php) — Jenis tagihan, monitoring tagihan, kategori keringanan, skema cicilan, pembayaran manual/verifikasi, virtual account.
9. [`routes/admin/rpp.php`](file:///d:/laragon/www/pintera-app/routes/admin/rpp.php) — Perangkat mengajar/RPP modul ajar, download, submit, verify.
10. [`routes/admin/penilaian-rapor.php`](file:///d:/laragon/www/pintera-app/routes/admin/penilaian-rapor.php) — Komponen penilaian admin, rapor cetak/opsi/persetujuan/decision kurikulum-kepsek, kenaikan kelas.
11. [`routes/admin/kasus-admin.php`](file:///d:/laragon/www/pintera-app/routes/admin/kasus-admin.php) — Manajemen kasus oleh admin/triase/assign konselor/restore/log akses/terhapus.
12. [`routes/admin/sarpras.php`](file:///d:/laragon/www/pintera-app/routes/admin/sarpras.php) — Master gedung, ruangan, kategori aset, aset barang, mutasi aset, KIR, rekap global sarpras yayasan.
13. [`routes/admin/pengadaan.php`](file:///d:/laragon/www/pintera-app/routes/admin/pengadaan.php) — Proposal pengadaan, LPJ staging & inventory conversion, inbox approval yayasan, disbursement, audit LPJ.
14. [`routes/kasus.php`](file:///d:/laragon/www/pintera-app/routes/kasus.php) — Explicit model binding `kasus` (bypass `TenantScope` untuk orang tua) + grup `kasus.*` (pengajuan kasus, consent, sesi, tugas, evaluasi).
15. [`routes/guru.php`](file:///d:/laragon/www/pintera-app/routes/guru.php) — Portal guru (jurnal KBM, rekap kehadiran, asesmen nilai, komponen penilaian guru, catatan rapor, ajukan rapor).

### File yang Dimodifikasi:
1. [`routes/admin.php`](file:///d:/laragon/www/pintera-app/routes/admin.php) — Berubah dari file monolitik 368 baris menjadi loader bersih 23 baris yang membungkus 13 file `routes/admin/*.php`.
2. [`routes/web.php`](file:///d:/laragon/www/pintera-app/routes/web.php) — Mendaftarkan `require __DIR__.'/kasus.php';` dan `require __DIR__.'/guru.php';`.
3. [`.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md) — Checklist FASE 5.1 ditandai selesai.

---

## 2. Keputusan Penting yang Diambil

1. **Pemecahan Per Modul Fungsional (Bukan Per Scope Aktor):**
   Sesuai spec design, perpecahan didasarkan pada domain fitur (`roles`, `lembaga`, `guru-data`, `akademik-master`, `siswa`, `spmb`, `keuangan`, `rpp`, `penilaian-rapor`, `kasus-admin`, `sarpras`, `pengadaan`) agar tidak perlu me-rename route URI/name dan tidak memerlukan route alias yang rapuh.
2. **Isolasi Rute Non-Admin (`kasus.*` dan `guru.*`):**
   Rute `kasus.*` (termasuk explicit `Route::bind('kasus')` untuk bypass `TenantScope` akun Orang Tua) dan portal guru `guru.*` yang sebelumnya berada di luar grup admin tetapi menumpang di file `admin.php` dipindahkan ke root `routes/kasus.php` dan `routes/guru.php` lalu direquire langsung dari `routes/web.php`.
3. **Verifikasi Komparasi JSON Route:**
   Verifikasi otomatis dilakukan di setiap akhir task membandingkan seluruh parameter route (URI, method, name, action, middleware) menggunakan output `php artisan route:list --json` terurut terhadap baseline awal, memastikan zero regression.

---

## 3. Hasil Pengujian

- **Route List Verification:** 14/14 task passed — `ROUTES IDENTICAL` terhadap snapshot baseline awal.
- **Full Test Suite (`php artisan test`):**
  - **1861 passed** (5721 assertions, 0 failed, duration 600s).

---

## 4. Status Git & Hal yang Perlu Direview

- **Branch:** `rbac-v2`
- **Commits:**
  - `e8de2a7`: refactor(routes): ekstrak modul roles & whatsapp-template ke routes/admin/
  - `849e6b5`: refactor(routes): ekstrak modul lembaga ke routes/admin/
  - `1815f50`: refactor(routes): ekstrak modul guru-data ke routes/admin/
  - `15d0b5d`: refactor(routes): ekstrak modul akademik-master ke routes/admin/
  - `aa15eb9`: refactor(routes): ekstrak modul siswa ke routes/admin/
  - `dcf6844`: refactor(routes): ekstrak modul spmb ke routes/admin/
  - `520dd5a`: refactor(routes): ekstrak modul keuangan ke routes/admin/
  - `9713b50`: refactor(routes): ekstrak modul rpp ke routes/admin/
  - `549ded6`: refactor(routes): ekstrak modul penilaian-rapor ke routes/admin/
  - `54910d9`: refactor(routes): ekstrak modul kasus-admin ke routes/admin/
  - `0904b5b`: refactor(routes): ekstrak modul sarpras ke routes/admin/
  - `97810d3`: refactor(routes): ekstrak modul pengadaan ke routes/admin/, admin.php kini murni loader
  - `ac681c5`: refactor(routes): pisahkan grup kasus.* ke routes/kasus.php (bukan bagian admin)
  - `5359992`: refactor(routes): pisahkan portal guru ke routes/guru.php (bukan bagian admin)
- **Tindak Lanjut Berikutnya:** FASE 5.2 (Dynamic Permission Sync) & FASE 5.3 pada master plan akademik jika diperlukan.
