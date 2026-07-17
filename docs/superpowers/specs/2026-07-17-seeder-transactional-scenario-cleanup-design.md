# Pembersihan Seeder — Sub-project 3: Data Skenario/Transaksional — Design Spec

**Tanggal:** 2026-07-17
**Status:** Disetujui, siap masuk writing-plans

## 1. Latar Belakang & Tujuan

Sub-project 3 dari 3 (terakhir) inisiatif "pembersihan arsitektur seeder" — sub-project 1 (RBAC) dan sub-project 2 (data master/referensi) sudah selesai dan sudah di-merge ke main. Sub-project ini memecah dua file demo seeder terakhir yang masih monolitik:

- `M3DemoDataSeeder.php` — data skenario verifikasi & keputusan pendaftaran (CalonMurid, Pendaftaran, DokumenPendaftaran, HasilSeleksi, SkPpdb), sudah terdaftar di `DatabaseSeeder`.
- `PembayaranDemoSeeder.php` — data tagihan/pembayaran/cicilan, **sengaja tidak terdaftar** di `DatabaseSeeder` (dijalankan manual), dan hanya mencakup SMP.

Menjadi 11 seeder baru (satu tabel satu seeder), plus satu seeder baru untuk `AkunPendaftar` (belum pernah punya data demo sama sekali). Tujuan akhir user: data relasional yang real dan lengkap di setiap fitur, supaya pengecekan manual bisa langsung dilakukan tanpa setup tambahan setelah `migrate:fresh --seed` — prinsip yang sama yang sudah melandasi `M3DemoDataSeeder` sejak awal.

## 2. Keputusan Desain (dari sesi brainstorming)

1. **Semua didaftarkan ke `DatabaseSeeder`.** Termasuk seeder pengganti `PembayaranDemoSeeder`, yang sebelumnya sengaja dibuat manual-only. Konsekuensi: setiap `migrate:fresh --seed` sekarang otomatis menghasilkan tagihan/pembayaran pending di antrian verifikasi keuangan — perubahan perilaku default yang disengaja.
2. **Tidak ada duplikasi `JenisTagihan`/`NominalTagihanJalur`.** `PembayaranDemoSeeder` yang lama punya `firstOrCreate` sendiri untuk kedua tabel ini dengan nominal daftar-ulang yang berbeda (Rp900.000) dari yang sudah di-seed sub-project 2 (Rp3.000.000) — karena `firstOrCreate` menemukan baris yang sudah ada duluan, nilai Rp900.000 itu sudah jadi dead code. Seeder baru (`TagihanSeeder`) hanya melakukan lookup ke baris `JenisTagihan` yang sudah ada, tidak membuat baris baru.
3. **`SkemaCicilan`+`Cicilan` tetap lewat `PembayaranService::buatSkemaCicilan()`.** Service ini menulis ke kedua tabel sekaligus dalam satu transaksi (menghitung nominal per termin, jatuh tempo). `SkemaCicilanSeeder` memanggil service ini; `CicilanSeeder` tetap ada sebagai file terpisah (satu tabel satu tanggung jawab) tapi isinya memverifikasi baris `Cicilan` yang dihasilkan service sudah sesuai ekspektasi — bukan insert manual, supaya logic bisnis perhitungan termin yang sudah dipakai kode produksi tidak direplikasi/menyimpang.
4. **`AkunPendaftar` masuk scope, `VerifikasiEmailOtp` tidak.** `AkunPendaftar` adalah akun login terpisah untuk pendaftar (relasi `HasMany` ke `Pendaftaran`) yang belum pernah punya data demo — tanpa ini, portal akun pendaftar sama sekali tidak bisa dicek manual. `VerifikasiEmailOtp` by design adalah data berumur pendek (kode OTP + kedaluwarsa) yang nilainya justru ada di digenerate live saat signup sungguhan, bukan di-seed statis — dilewati.
5. **Cakupan diperluas ke kedua lembaga (SMP+SMA).** `PembayaranDemoSeeder` yang lama hanya SMP. Karena `M3DemoDataSeeder` sudah mencakup kedua lembaga dan tujuan user adalah data relasional real di setiap fitur (termasuk pengujian isolasi multi-tenant di fitur keuangan/portal), seluruh data tagihan/pembayaran/cicilan/akun-pendaftar sekarang dibuat untuk SMP **dan** SMA.

## 3. Lingkup — 11 Seeder Baru

**Kelompok A — Skenario M3 (reorganisasi dari `M3DemoDataSeeder`):**

1. `CalonMuridSeeder` — 8 baris: 3 skenario (Menunggu Verifikasi, Diterima, Ditolak) × 2 lembaga, + 1 kandidat cicilan × 2 lembaga. Idempotensi via `nama_lengkap` (deterministik, menyertakan nama lembaga — `calon_murid` tidak punya kolom `lembaga_id`, hanya `yayasan_id`, jadi ini satu-satunya kunci alami yang membedakan skenario per lembaga di tabel ini).
2. `PendaftaranSeeder` — 8 baris terkait (lookup `CalonMurid` via `nama_lengkap`). Idempotensi via `email_pendaftaran`+`lembaga_id` (bukan `kode_pendaftaran`, yang tetap punya suffix acak persis seperti kode asli, cuma tidak dipakai sebagai kunci pencarian). Field keputusan final (`status`, `catatan_keputusan`, `ditetapkan_oleh_user_id`, `ditetapkan_pada`) diisi langsung saat create untuk skenario Diterima/Ditolak — nilainya tidak dihitung dari `HasilSeleksi`, jadi tidak ada dependensi melingkar dengan Task 4.
3. `DokumenPendaftaranSeeder` — dokumen campuran (sebagian terverifikasi/ditolak/belum) untuk "Menunggu Verifikasi", dokumen lengkap-terverifikasi untuk "Diterima". 2 lembaga × 2 skenario.
4. `HasilSeleksiSeeder` — nilai untuk "Diterima" (75-95) dan "Ditolak" (30-55), per seleksi milik jalur+gelombang aktif lembaga itu. 2 lembaga × 2 skenario.
5. `SkPpdbSeeder` — 1 SK per lembaga mencakup pendaftaran Diterima+Ditolak lembaga itu, meng-update `pendaftaran.sk_ppdb_id` (satu-satunya cross-table write di luar kelompoknya sendiri — konsisten dengan pola child-seeder yang sudah mapan di sub-project 1/2). Dilewati (tidak membuat SK) jika lembaga belum punya staf, mengikuti guard yang sudah ada di kode asli.

**Kelompok B — Keuangan (reorganisasi dari `PembayaranDemoSeeder`, diperluas ke SMA):**

6. `TagihanSeeder` — per lembaga: 2 tagihan untuk "Diterima" (kategori `pendaftaran` + `daftar_ulang`) + 1 tagihan `daftar_ulang` untuk kandidat cicilan. Lookup `JenisTagihan` milik lembaga dari sub-project 2 (tidak membuat baru).
7. `TagihanItemSeeder` — 1 item per tagihan, mengacu ke `JenisTagihan` yang sama.
8. `SkemaCicilanSeeder` — 1 skema per lembaga (3 termin) untuk tagihan `daftar_ulang` kandidat cicilan, via `PembayaranService::buatSkemaCicilan()`.
9. `CicilanSeeder` — verifikasi 3 baris `Cicilan` per lembaga (6 total) sudah sesuai ekspektasi (urutan, nominal, jatuh tempo, status `belum_bayar`) — bukan insert manual, ditulis sebagai efek samping Task 8.
10. `PembayaranSeeder` — per lembaga: 2 pembayaran `menunggu_verifikasi` untuk 2 tagihan "Diterima" (via `tagihan_id`) + 1 pembayaran `menunggu_verifikasi` untuk termin-1 cicilan kandidat cicilan (via `cicilan_id`).

**Kelompok C — Portal Akun Pendaftar (baru, belum pernah ada seeder-nya):**

11. `AkunPendaftarSeeder` — 1 akun per lembaga (`pendaftar.smp@example.test`/`pendaftar.sma@example.test`), password+`email_verified_at` terisi, di-attach ke `Pendaftaran` "Diterima" lembaga itu via `akun_pendaftar_id`.

## 4. Urutan di `DatabaseSeeder`

Melanjutkan langsung setelah `NominalTagihanJalurSeeder` (posisi terakhir dari sub-project 2), menggantikan `M3DemoDataSeeder`:

```
... (seluruh urutan sub-project 1 & 2, tidak berubah) ...
JenisTagihanSeeder, NominalTagihanJalurSeeder,
CalonMuridSeeder,
PendaftaranSeeder,
DokumenPendaftaranSeeder,
HasilSeleksiSeeder,
SkPpdbSeeder,
TagihanSeeder,
TagihanItemSeeder,
SkemaCicilanSeeder,
CicilanSeeder,
PembayaranSeeder,
AkunPendaftarSeeder
```

`M3DemoDataSeeder.php` dan `PembayaranDemoSeeder.php` **dihapus** setelah dikonfirmasi tidak ada test yang memanggilnya langsung (pola yang sama dengan penghapusan `DemoDataSeeder.php` di sub-project 2 — dicek ulang di awal task integrasi, bukan diasumsikan).

## 5. Rencana Pengujian

- Tiap seeder: jumlah baris yang benar (memperhitungkan pengali ×2 lembaga di hampir semua tabel), spot-check field kunci, idempoten (dijalankan dua kali tidak dobel/error).
- **Konsistensi relasi lintas-seeder** — ini fokus utama dibanding sub-project 1/2 karena datanya benar-benar berantai (calon → pendaftaran → dokumen/hasil-seleksi → SK → tagihan → item → cicilan → pembayaran): test eksplisit yang menelusuri rantai penuh dari satu `Pendaftaran` "Diterima" sampai ke `Pembayaran`-nya, membuktikan semua FK antara terisi benar dan mengarah ke baris yang tepat (bukan tertukar antar lembaga).
- `TagihanSeeder`: `JenisTagihan` yang dipakai memang baris dari sub-project 2 (bukan baris baru), nominal sesuai (Uang Pangkal SMP = Rp3.000.000, bukan Rp900.000).
- `SkemaCicilanSeeder`/`CicilanSeeder`: 3 termin per skema, total nominal cicilan = `total_tagihan` tagihan induknya (pembulatan termin terakhir menyerap sisa, sesuai logic `PembayaranService`), `tagihan.status` berubah jadi `dicicil` setelah skema dibuat.
- `SkPpdbSeeder`: dua `Pendaftaran` (Diterima+Ditolak) lembaga yang sama mengarah ke `sk_ppdb_id` yang SAMA (satu SK mencakup keduanya, sesuai desain asli); lembaga lain punya SK terpisah.
- `AkunPendaftarSeeder`: password bisa dipakai login (ter-hash benar), `Pendaftaran.akun_pendaftar_id` terisi dan relasi `akunPendaftar->pendaftaran` mengembalikan baris yang benar.
- Regresi: test lain yang bergantung pada rantai `DatabaseSeeder` penuh tetap hijau tanpa modifikasi.
- Verifikasi akhir (task integrasi): `migrate:fresh --seed` nyata terhadap database asli, seluruh 11 seeder baru + seluruh chain sub-project 1/2 berjalan tanpa error.

## 6. Non-Tujuan / Catatan

- `VerifikasiEmailOtp` sengaja tidak di-seed (lihat §2.4) — bukan gap yang perlu ditutup di sub-project ini.
- Tidak ada perubahan nilai/isi data dari `M3DemoDataSeeder`/`PembayaranDemoSeeder` yang sudah ada, kecuali: (a) perluasan cakupan PembayaranDemoSeeder ke SMA (disetujui eksplisit di §2.5), (b) penghapusan logic `JenisTagihan`/`NominalTagihanJalur` yang duplikat (disetujui eksplisit di §2.2).
- Ini adalah sub-project **terakhir** dari inisiatif "pembersihan arsitektur seeder" — setelah ini selesai, tidak ada lagi file seeder monolitik (`*DemoDataSeeder.php`) yang tersisa di `database/seeders/`.
