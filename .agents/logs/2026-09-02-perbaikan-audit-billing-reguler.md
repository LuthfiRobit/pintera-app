# Handoff Log — Perbaikan Audit Billing Reguler (Non-PPDB)

**Tanggal**: 2026-09-02
**Branch**: `keuangan-v2`
**Base Commit**: `571546ad` (setelah spec ditambahkan)
**Head Commit**: `290ec637`
**Dokumen Terkait**:
- Spec: `.agents/specs/2026-09-02-perbaikan-audit-billing-reguler.md`
- Plan: `.agents/plans/2026-09-02-perbaikan-audit-billing-reguler.md`
- Progress Ledger: `.superpowers/sdd/progress.md`
- Log audit temuan asal (B.1-B.10): `.agents/logs/2026-09-01-jenis-tagihan-konsolidasi-sasaran-tarif-keringanan.md` §11-14

---

## 1. Apa yang Dikerjakan

Diimplementasikan secara menyeluruh 9 task (Subagent-Driven Development, TDD ketat) menutup 9 temuan dari audit alur bisnis billing reguler (non-PPDB, non-siswa-portal) sebelumnya, plus 1 temuan Critical tambahan yang ditemukan sendiri lewat review akhir whole-branch (tidak ada di 9 temuan audit asli):

1. **Task 1 (B.3, Critical)**: `AutoAllocationEngine` & `SkipAlertResolver` sekarang exclude tagihan `perlu_ditinjau_ulang=true` dari kandidat auto-debit/skip-alert.
2. **Task 2 (B.2, Important)**: `PaymentService::guardAgainstInvalidTagihan()` (dipakai wallet/QRIS/manual/cash) dan re-fetch query di `createWalletPayment()` menolak tagihan yang di-flag — menutup celah waktu (TOCTOU) antara checkout dimuat dan submit.
3. **Task 3 (B.9, Minor)**: `BatalkanTagihanAction` menolak (422) membatalkan tagihan yang sedang ditinjau.
4. **Task 4 (B.10, Critical)**: Aksi baru `KoreksiNominalTagihanAction` + permission baru `tagihan.edit` + form "Koreksi Nominal" di halaman Perlu Ditinjau — jalan keluar resmi dari antrian tinjau (sebelumnya cuma bisa hapus flag tanpa koreksi nominal apapun).
5. **Task 5 (B.1, Critical)**: `priority_score` (dasar urutan auto-debit) sekarang bisa diisi lewat form Jenis Tagihan — sebelumnya kolom ini ada & dipakai penuh oleh engine tapi tidak pernah bisa dikonfigurasi.
6. **Task 6 (B.8, Important)**: Badge "Sedang Ditinjau" di halaman Jenis Tagihan Monitoring.
7. **Task 7 (B.6, Minor)**: `AutoAllocationEngine` di-refactor pakai `TagihanStatusResolver` (bukan logic inline duplikat).
8. **Task 8 (B.5, Minor)**: Default `auto_debit_enabled` disamakan jadi `true` di 2 controller portal orang tua, konsisten dengan `Wallet::topup()` yang sudah `true` — perbaikan kejujuran tampilan banner, TIDAK mengubah perilaku uang.
9. **Task 9 (B.4, Important — mitigasi)**: Cron `finance:reconcile-payments` dipercepat dari `hourly()` ke `everyTwoMinutes()`.

**Ditemukan TAMBAHAN lewat review akhir whole-branch** (bukan bagian dari 9 temuan audit asli — celah baru yang lolos dari cakupan plan):
10. **`PaymentAllocationService::allocate()`** — titik pusat penerapan pembayaran untuk rekonsiliasi QRIS/VA (cron) dan approval transfer manual — TIDAK PERNAH mengecek `perlu_ditinjau_ulang` sama sekali. Task 2 cuma menutup jalur SINKRON (`PaymentService`'s creation methods); jalur ASINKRON (konfirmasi/approval yang bisa terjadi menit-jam setelah pembayaran dibuat) tetap terbuka. Ditutup di commit `290ec637`, mengikuti pola `dibatalkan`-skip yang sudah ada di method yang sama (cek pre-lock DAN post-lock).

## 2. Keputusan Desain Penting

1. **Tidak ada sistem refund dibangun sama sekali.** Task 4 (koreksi nominal manual) — kalau hasil koreksi bikin `net_amount < paid_amount` (overpayment), status otomatis jadi `lunas` lewat `TagihanStatusResolver::resolve()` (karena `paid_amount >= net_amount`), TANPA field/tabel refund baru. Kelebihan bayar cuma terlihat implisit dari selisih `paid_amount - net_amount` di manapun kedua angka itu ditampilkan. Ini keputusan sadar (codebase ini memang tidak punya mekanisme refund di manapun).
2. **`TagihanStatusResolver::resolve()` tetap satu-satunya sumber kebenaran transisi status** — dipakai konsisten oleh `PaymentAllocationService`, `RecalculateTagihanNominalAction` (dari paket sebelumnya), `KoreksiNominalTagihanAction` (Task 4), dan sekarang `AutoAllocationEngine` (Task 7, sebelumnya duplikasi inline).
3. **Permission baru `tagihan.edit`** ditambahkan (belum pernah ada sebelumnya) khusus untuk Task 4 — dipisah dari `tagihan.view` (dipakai `tandaiSelesaiDitinjau()` yang cuma hapus flag) karena `koreksiNominal()` mengubah nominal uang langsung, butuh level akses berbeda. Diberikan ke role `bendahara_lembaga`.
4. **B.4 (cron dipercepat) adalah MITIGASI SEMENTARA, bukan solusi permanen.** Perbaikan permanen butuh webhook QRIS asli dari BRI SNAP (kalau memang didukung) — **belum diriset, apalagi dikerjakan**. Ini item terbuka yang perlu ditindaklanjuti terpisah kalau volume pembayaran QRIS cukup tinggi untuk membuat delay 2 menit masih terasa mengganggu.

## 3. Temuan Review yang Diperbaiki Selama Eksekusi

Selain temuan Critical `PaymentAllocationService` di atas, 1 temuan lain muncul di review per-task:

- **Task 4, review putaran 1**: `koreksiNominal()` awalnya TIDAK punya pengecekan kepemilikan lembaga sama sekali — admin `bendahara_lembaga` dari Lembaga A bisa mengoreksi nominal tagihan milik Lembaga B manapun (cross-tenant IDOR) hanya dengan menebak/increment id tagihan. Diperbaiki dengan helper baru `resolveTagihanLembagaId()` yang menangani baik tagihan PPDB (`pendaftaran`) maupun siswa reguler (`tagihable`), dipasang sebagai guard `abort_unless(..., 404)` sebelum mutasi apapun. Re-review putaran 2: bersih.
- **Deferred (Minor, disengaja tidak diperbaiki)**: `tandaiSelesaiDitinjau()` (aksi lama, TIDAK disentuh paket ini) punya celah kepemilikan yang sama — tapi dia cuma menghapus flag, tidak menyentuh kolom finansial apapun, jadi risikonya jauh lebih rendah. Dicatat untuk follow-up terpisah kalau memang ingin ditutup juga.

## 4. Efek Samping yang Ditemukan & Diperbaiki

Full-suite run pertama setelah 9 task selesai menemukan **8 test gagal** — semuanya karena 10 assertion hardcoded (`Permission::count() === 150`, `bendahara_lembaga permissions count === 13`) di 3 file test (`PermissionSeederTest.php`, `RoleSeederTest.php`, `RolePermissionSeederTest.php`) yang tidak mengantisipasi penambahan permission `tagihan.edit` dari Task 4. Diperbaiki di commit `3f8b7261` (murni update angka literal, tidak ada perubahan logic/skenario test).

## 5. Hasil Verifikasi Akhir

- **Full Test Suite** (dijalankan sendirian, tanpa proses lain bersamaan — insiden sebelumnya di sesi ini pernah menyebabkan 869 false failure gara-gara 2 proses `php artisan test` bentrok di database yang sama via `SQLSTATE[HY000]: 1412 Table definition has changed`): **2697 passed, 7359 assertions, 0 failed**.
- **Code Style (Pint)**: bersih (`vendor/bin/pint --dirty --format agent`).
- **Frontend Build**: `npm run build` sukses (Task 4 menambah markup Alpine baru di `perlu-ditinjau.blade.php`).
- **Review whole-branch**: 2 putaran. Putaran 1 menemukan 1 Critical (`PaymentAllocationService` di atas). Putaran 2 (setelah fix): **bersih, "Ready to merge: Yes"**, termasuk full sweep manual semua titik mutasi `paid_amount` di seluruh aplikasi (cuma 2: `AutoAllocationEngine` dan `PaymentAllocationService`, keduanya sekarang ter-guard).

## 6. Item Terbuka untuk Tindak Lanjut (Tidak Dikerjakan di Paket Ini)

1. **Webhook QRIS permanen** — B.4 cuma mitigasi (cron 2 menit). Perbaikan sungguhan butuh riset dokumentasi BRI SNAP untuk konfirmasi apakah mereka mendukung webhook QRIS asli, lalu implementasi endpoint inbound baru (mirip pola `BriVaInboundController` yang sudah ada untuk VA).
2. **`tandaiSelesaiDitinjau()` tidak punya guard kepemilikan lembaga** — dicatat sebagai temuan Minor saat memperbaiki Task 4, sengaja tidak ditutup di paket ini karena aksi itu tidak menyentuh kolom finansial (cuma hapus flag).
3. **Audit trail eksplisit untuk koreksi manual (Task 4)** — disarankan reviewer sebagai peningkatan (bukan blocker): `Tagihan` sudah pakai Spatie Activitylog, jadi perubahan `total_tagihan`/`discount_amount`/`net_amount` lewat `KoreksiNominalTagihanAction` sudah otomatis tercatat lewat mekanisme itu, tapi belum ada catatan eksplisit "alasan tinjauan yang diselesaikan lewat koreksi manual X".

## 7. Status Git

- Branch: `keuangan-v2`
- 13 commit atomik (9 task + 1 fix IDOR Task 4 + 1 fix test-suite alignment + 1 fix Critical `PaymentAllocationService`).
- Belum di-push ke remote (siap direview dan di-merge/push sesuai keputusan user).
