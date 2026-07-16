# Pembersihan Seeder — Sub-project 2: Data Master/Referensi — Design Spec

**Tanggal:** 2026-07-17
**Status:** Disetujui, siap masuk writing-plans

## 1. Latar Belakang & Tujuan

Sub-project 2 dari 3 inisiatif "pembersihan arsitektur seeder" (sub-project 1, RBAC, sudah selesai — `PermissionSeeder`/`RoleSeeder`/`EssentialUserSeeder`). Sub-project ini memecah `DemoDataSeeder.php` (satu file besar yang sekarang menyentuh ~18 tabel sekaligus: Lembaga, staf, Guru+profil, TahunAjaran, konfigurasi PPDB, dsb) jadi satu seeder per tabel, mengikuti konvensi standar Laravel — plus menambahkan seeder baru untuk `jenis_tagihan`/`nominal_tagihan_jalur` (sebelumnya sengaja dibiarkan kosong saat sub-project Keuangan dibangun, sekarang diisi juga sesuai keputusan terbaru).

## 2. Lingkup

**Termasuk — 20 seeder baru** (satu file per tabel), dikelompokkan jadi 8 task implementasi berdasarkan kohesi data:

1. `LembagaSeeder` — 2 lembaga (SMP, SMA), berdiri sendiri (cuma butuh Yayasan dari sub-project 1).
2. `UserSeeder` — akun staf per-lembaga yang sudah ada (`kepsek.smp@alhikmah.sch.id`, `adm.smp@...`, `keuangan.smp@...`, plus padanan SMA, plus `admin.yayasan@alhikmah.sch.id`) — **tidak** menyentuh 5 akun `EssentialUserSeeder` dari sub-project 1.
3. `TahunAjaranSeeder` + `SemesterSeeder` — tahun ajaran lama+baru per lembaga, ganjil+genap per tahun ajaran.
4. `GuruSeeder` + `RiwayatPendidikanGuruSeeder` + `SertifikasiGuruSeeder` + `GuruJabatanTambahanSeeder` — profil guru lengkap (3 guru per lembaga, dengan riwayat pendidikan, sebagian punya sertifikasi, sebagian punya jabatan tambahan).
5. `LembagaDataPeriodikSeeder` + `LayananKhususLembagaSeeder` + `ProgramInklusiLembagaSeeder` + `EkstrakurikulerLembagaSeeder` — data profil/fasilitas per lembaga.
6. `JenisTesMasterSeeder` + `GelombangPpdbSeeder` + `JalurPpdbSeeder` + `FormulirFieldSeeder` + `DokumenSyaratPpdbSeeder` + `SeleksiPpdbSeeder` — satu paket konfigurasi PPDB lengkap.
7. `JenisTagihanSeeder` + `NominalTagihanJalurSeeder` — **baru**, jenis tagihan pendaftaran (wajib lunas) dan daftar ulang (bisa dicicil), dengan nominal per jalur.
8. Integrasi: hapus `DemoDataSeeder.php`, wiring `DatabaseSeeder`, update panduan manual testing M4 Keuangan.

**Isi data dipertahankan persis** dari `DemoDataSeeder.php` yang ada sekarang — ini murni reorganisasi struktur file, bukan perubahan konten. Termasuk detail yang harus dipertahankan: konfigurasi PPDB untuk SMP sengaja dibuat **dua kali** (tahun ajaran lama, untuk menguji fitur duplikasi; tahun ajaran aktif, supaya wizard SPMB publik langsung bisa diuji tanpa perlu duplikasi dulu) — SMA cuma sekali (langsung di tahun ajaran aktif).

**Data baru (bukan reorganisasi, tapi tambahan yang disetujui):**
- `JenisTagihanSeeder`: "Biaya Pendaftaran" (kategori pendaftaran, tidak bisa dicicil) dan "Uang Pangkal" (kategori daftar ulang, bisa dicicil maks 3x) — per lembaga (SMP & SMA masing-masing).
- `NominalTagihanJalurSeeder`: nominal untuk tiap jenis tagihan × jalur PPDB — termasuk contoh nominal Rp0 (gratis) untuk jalur Afirmasi, mengikuti prinsip "nominal dinamis, termasuk gratis" dari PRD, dan sengaja MENYISAKAN satu kombinasi jenis-tagihan×jalur tanpa nominal (jalur Prestasi) supaya perilaku "lewati saja, jangan buat tagihan palsu" (dari `TagihanGenerator`) masih bisa didemonstrasikan meski datanya sekarang tidak lagi kosong total.

**Tidak termasuk:**
- `M3DemoDataSeeder`, `PembayaranDemoSeeder`, dan seluruh data skenario/transaksional lain — Sub-project 3.
- Perubahan isi/nilai data (nama, tanggal, dsb) — persis sama seperti sekarang, kecuali `JenisTagihanSeeder`/`NominalTagihanJalurSeeder` yang memang baru.

## 3. Urutan di `DatabaseSeeder`

```
PermissionSeeder, RoleSeeder, YayasanSeeder, JabatanTambahanMasterSeeder,
LembagaSeeder,
EssentialUserSeeder,        ← dipindah ke sini, sesuai catatan yang ditinggalkan di sub-project 1
UserSeeder,
TahunAjaranSeeder, SemesterSeeder,
GuruSeeder, RiwayatPendidikanGuruSeeder, SertifikasiGuruSeeder, GuruJabatanTambahanSeeder,
LembagaDataPeriodikSeeder, LayananKhususLembagaSeeder, ProgramInklusiLembagaSeeder, EkstrakurikulerLembagaSeeder,
JenisTesMasterSeeder, GelombangPpdbSeeder, JalurPpdbSeeder, FormulirFieldSeeder, DokumenSyaratPpdbSeeder, SeleksiPpdbSeeder,
JenisTagihanSeeder, NominalTagihanJalurSeeder,
M3DemoDataSeeder
```

`DemoDataSeeder.php` **dihapus** (bukan disimpan sebagai wrapper backward-compat) — dikonfirmasi tidak dipanggil test manapun secara langsung (cuma lewat `DatabaseSeeder`), berbeda dengan kasus `RolePermissionSeeder` di sub-project 1.

## 4. Update Panduan Manual Testing M4 Keuangan

`docs/pengujian-manual/2026-07-16-m4-keuangan-master-tagihan-manual-testing.md` diperbarui: bagian "Penting — tidak ada data jenis tagihan bawaan" (baris 7) dan Bagian 1 "Membuat Jenis Tagihan & Nominal per Jalur" (yang menyuruh tester membuat konfigurasi dari nol) diganti supaya mencerminkan bahwa `JenisTagihanSeeder`/`NominalTagihanJalurSeeder` sekarang sudah mengisinya — termasuk instruksi baru untuk memverifikasi nominal yang sudah ter-seed benar, dan tetap mempertahankan pengujian kombinasi jalur-tanpa-nominal (yang sekarang jadi kasus "Prestasi" yang sengaja disisakan kosong, bukan seluruh tabel kosong).

## 5. Rencana Pengujian

- Tiap seeder: jumlah baris yang benar setelah dijalankan, spot-check beberapa field kunci (bukan mengecek tiap field satu per satu — datanya besar), idempoten (dijalankan dua kali tidak dobel/error).
- `JenisTagihanSeeder`/`NominalTagihanJalurSeeder`: mengikuti aturan bisnis yang sudah ada (`bisa_dicicil`/`maks_cicilan` benar untuk Uang Pangkal, kategori pendaftaran tidak boleh `bisa_dicicil=true`), nominal Rp0 untuk Afirmasi benar-benar tersimpan sebagai 0 (bukan null), kombinasi Prestasi memang sengaja tidak punya baris `NominalTagihanJalur`.
- Regresi: `M3DemoDataSeederTest` dan test lain yang bergantung pada rantai `DatabaseSeeder` penuh tetap hijau tanpa modifikasi — khususnya karena `M3DemoDataSeeder` mengasumsikan `Lembaga` dengan NPSN tertentu sudah ada duluan.
- Detail konfigurasi PPDB SMP-dua-kali dipertahankan: test membuktikan tahun ajaran lama SMP punya `JalurPpdb`/`GelombangPpdb` sendiri (nonaktif, untuk uji duplikasi) terpisah dari tahun ajaran aktifnya.

## 6. Non-Tujuan / Catatan

- Sub-project 3 (data skenario/transaksional: CalonMurid, Pendaftaran, Tagihan, dst, menggantikan `M3DemoDataSeeder`/`PembayaranDemoSeeder`) adalah spec/plan terpisah, belum dimulai.
- Kalau nanti kombinasi Prestasi-tanpa-nominal dianggap perlu diisi juga, itu keputusan terpisah — sengaja dipertahankan kosong di sub-project ini untuk terus mendemonstrasikan perilaku "lewati, jangan buat tagihan palsu".
