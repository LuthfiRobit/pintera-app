# Handoff Log: Keuangan Sub-project 06c2 (Top-Up Bundling & Verifikasi Admin)

## Apa yang dikerjakan
Sub-project ini menyelesaikan implementasi top-up bundling dan verifikasi admin manual payment (Tahap 06c2), mengikuti spec di `.agents/specs/keuangan-06c2-topup-bundling-verifikasi-admin.md` dan plan di `.agents/plans/keuangan-06c2-topup-bundling-verifikasi-admin.md`.
- **Top-up Bundling**: Menambahkan field opsional `topup_amount` pada form checkout VA dan QRIS. Implementasi `PaymentService::createVaPaymentWithTopup` dan `createQrisPaymentWithTopup` yang mengatur `pembayaran.amount = sum(tagihan) + topup_amount`.
- **Pecahan Sisa (Remainder)**: Implementasi fungsi helper `topupSisaJikaAda()` yang dipanggil saat payment gateway atau manual payment success. Sisa uang dari `pembayaran.amount - sum(pembayaran_tagihan.amount_allocated)` langsung dimasukkan ke wallet via `Wallet::topup()`.
- **Admin Verifikasi (Transfer Manual)**: Controller dan UI untuk admin (`ManualPaymentController`) agar bisa me-review, approve, dan reject request transfer manual. 
- **Security & Authorization**: Scope strict tenant (lembaga) di `ManualPaymentController`, test cross-tenant yang ketat (admin A tidak bisa lihat/ubah data lembaga B).
- **Notifikasi**: Mengirim email dan WhatsApp ke kontak_utama saat admin approve atau reject manual payment.
- **Kwitansi & Riwayat**: Update UI riwayat pembayaran dan PDF kwitansi untuk memisahkan nominal yang dialokasikan ke tagihan dengan nominal yang masuk ke wallet (Top-Up Wallet).

## Keputusan Penting yang Diambil
- **Idempotency Guard**: Pada webhook dan manual payment approval, status awal yang diperbolehkan hanya `menunggu_pembayaran` / `PENDING`. Request akan ditolak jika statusnya bukan ini, menghindari double-allocation dan double-topup.
- **Cross-Task Code Review (Sesuai Aturan)**: Review kode secara menyeluruh memastikan tidak ada tumpang tindih antara `AutoAllocationEngine` dan top-up bundling. Pada webhook/manual payment, kita melakukan alokasi ke tagihan *di dalam* DB transaction, tetapi mengeksekusi `Wallet::topup()` (yang men-trigger auto-allocation) *di luar* transaction tersebut untuk menghindari masalah savepoint bersarang (nested transaction pada Wallet), sesuai dengan kesepakatan pada log `keuangan-03-wallet-auto-allocation.md`.
- **Perbaikan Playwright Script**: Field `topup_amount` pada frontend Alpine JS tadinya merupakan *hidden input* namun di-fill langsung oleh Playwright `fill`, menyebabkan timeout. Selector playwright telah diperbaiki untuk menarget `input[type="number"]` secara eksplisit, dan DB disiapkan dengan data dummy via seed+tinker. Test playwright E2E berhasil (hijau).
- **Testing Regression Gate**: Test suite keseluruhan berjalan dengan hasil (236 passed, 627 assertions). Bebas dari test pollution.

## Hal yang Masih Perlu Direview Manusia/Claude
- **Status Notifikasi**: Notifikasi manual payment berjalan *best-effort*. Jika WhatsApp gateway sedang mati, proses approve/reject tetap berlanjut sukses. Harap dicatat jika ini kelak membutuhkan antrian (queue) yang di-retry.
- **Git State**: Semua kode telah dikerjakan di branch saat ini, belum di push/merge. (Terserah pada workflow branching yang diatur selanjutnya).
