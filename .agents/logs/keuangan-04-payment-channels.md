# Handoff Log: Payment Channels

**Terkait:**
- Spec: `.agents/specs/keuangan-04-payment-channels.md`
- Plan: `.agents/plans/keuangan-04-payment-channels.md`

## Apa yang dikerjakan
1. **Migrations**: Membuat tabel `bri_virtual_accounts` dan `bri_qris_payments` (Task 1). Menambahkan kolom `siswa_id`, merubah `status` enum (`menunggu_pembayaran`, `menunggu_verifikasi`, `lunas`, `ditolak`), dan menambahkan `topup_status` (`none`, `pending`, `completed`, `failed`) beserta `amount` pada tabel `pembayaran` (Task 2 & 6 revisi).
2. **Gateway Integration**: Mengimplementasikan `PaymentGatewayInterface` dan `MockPaymentGateway` untuk simulasi API bank (Task 3).
3. **Payment Service & Allocation**: Membuat `PaymentService` untuk menangani pembuatan `Pembayaran` dan data metode pembayaran yang sesuai (VA/QRIS). Membuat `PaymentAllocationService` untuk mengatur alokasi pembayaran setelah status menjadi `PAID` (apakah langsung melunasi `Tagihan`/`Cicilan` atau masuk ke `Wallet::topup`) (Task 4).
4. **Webhook Controller & Idempotency Lock**: 
   - Mengimplementasikan `BriWebhookController` (POST `/webhook/bri/payment-notification`).
   - Menerapkan `lockForUpdate()` di dalam `DB::transaction()` **untuk VA maupun QRIS** secara eksplisit guna mencegah *race condition* idempotency dari duplikat webhook.
   - Mengikuti konvensi Sub-project 03, eksekusi `$wallet->topup()` dijalankan **di luar** *outer transaction* DB agar `Wallet` dapat menjalankan `transaction`-nya sendiri tanpa menjadi *nested savepoint* yang rentan isu lock di MySQL.
5. **Reconciliation Command**: Membuat command `finance:reconcile-payments` untuk memproses VA/QRIS dengan status `WAITING` (polling) dan me-retry proses topup (dengan `topup_status = 'failed'`) tanpa membungkus ulang `topup()` di dalam transaksi. (Task 6).

## Keputusan penting yang diambil
1. **Penambahan Kolom `amount` di `pembayaran`**: 
   - **Isu Spesifik:** Untuk kasus *Permanent VA* yang digunakan top-up Wallet, nilai `amount` pada tabel `bri_virtual_accounts` adalah `null` (karena dinamis per transaksi bank). Saat *webhook* berhasil namun proses lokal `Wallet::topup()` gagal (misal DB timeout), kita mencatat `topup_status = 'failed'` untuk di-retry. Namun, saat di-retry oleh command `finance:reconcile-payments`, sistem perlu tahu *berapa* nilai yang harus di-topup ulang. Jika nilai ini tidak disave, data transaksinya akan hilang.
   - **Solusi:** Oleh karena itu, saya menambahkan kolom `amount` di tabel `pembayaran`. Nominal aktual yang didapat dari webhook disimpan ke `pembayaran.amount`, sehingga reconciliation command dapat membacanya kapan saja (melalui `$pembayaran->amount`) untuk di-*retry* dengan akurat.
2. **Fix Hasil Whole-Plan Code Review**: Selama *cross-task review* independen, saya mengidentifikasi dan membereskan 2 temuan arsitektural meskipun test suite berstatus *hijau*:
   - Menambahkan lookup & `lockForUpdate` spesifik QRIS ke dalam `BriWebhookController` karena test awal tidak menjangkaunya.
   - Memindahkan pemanggilan `Wallet::topup()` agar dieksekusi benar-benar setelah closure `DB::transaction()` selesai, baik pada `BriWebhookController` maupun `ReconcilePayments` command, guna mencegah *nested savepoint transaction bug*.

## Hal yang masih perlu direview manusia/Claude
1. **Manual Payment Integrasi:** Saat ini *Manual Payment* (Persetujuan Admin) belum terintegrasi untuk kasus Topup Wallet secara utuh di UI. Jika UI nanti memanggil *approve* untuk bukti transfer manual yang *ditujukan untuk topup*, pastikan `topup_status` di-update dan eksekusi `topup()` berada di luar transaksi persetujuan, sesuai konvensi.
2. **Polling Schedule:** `ReconcilePayments` sudah di-schedule `hourly()` pada `routes/console.php`. Mohon review apakah frekuensinya (setiap jam) sudah cukup atau butuh lebih sering (misal `everyFifteenMinutes()`).
3. ~~Semua code sudah aman (120 tests passed ~ 46s execution) di current branch (`demo`). Belum di-push ke remote. Evaluasi akhir dapat dilakukan sebelum proses *merge/push*.~~ — **Lihat "Update: Audit Lintas Sub-Project 1-4" di bawah: 2 bug nyata DITEMUKAN setelah klaim ini ditulis.** Test hijau tidak berarti bug tidak ada — kedua bug di bawah lolos test suite yang ada sampai diaudit ulang dengan skenario yang lebih spesifik (webhook gagal / amount mismatch).

## Update: Audit Lintas Sub-Project 1-4 (2026-08-11, sesi terpisah)

Setelah sub-project 04 dinyatakan selesai (120 tests passed), dilakukan audit independen lintas sub-project 1-4 (kode dibaca ulang langsung, bukan sekadar percaya laporan/test hijau) sebelum lanjut ke sub-project 5. Ditemukan **2 bug nyata di `BriWebhookController.php`** yang lolos dari review/test sebelumnya, keduanya sudah diperbaiki. Detail lengkap ada di `.agents/logs/keuangan-audit-fixes-01-04.md` (commit range `440fcbb..ad18d46` di `demo`); ringkasan untuk konteks sub-project 04 ini:

1. **Webhook selalu return HTTP 200, bahkan saat gagal** (commit fix `4bbcdf7`). Blok `catch` terluar (mencakup exception `"Payment reference not found"` saat payload webhook tidak match VA/QRIS manapun) cuma log error, lalu kode tetap jatuh ke `return response()->json(['status' => 'success'])` yang tidak bersyarat. Payment gateway sungguhan (termasuk BRI nantinya) mengandalkan respons non-2xx untuk tahu harus retry — bug ini berarti webhook yang gagal diproses **tidak akan pernah di-retry**, dan `finance:reconcile-payments` cuma polling record `WAITING` yang SUDAH ADA (tidak bisa menemukan payment yang reference-nya sama sekali tidak match sejak awal). **Diperbaiki:** outer catch sekarang return HTTP 500. Blok try/catch INNER di sekitar `$wallet->topup()` (untuk kasus WALLET_PERMANENT yang gagal topup setelah transaction utama commit) SENGAJA tidak disentuh — itu memang harus tetap return 200 karena webhook-nya sendiri sukses diproses, cuma kredit wallet-nya yang di-retry lewat reconciliation cron (pakai kolom `pembayaran.amount` yang sudah dijelaskan di atas).
2. **Amount webhook untuk `BILL_DIRECT` tidak pernah divalidasi** (commit fix `e2e6137`). `$amountPaid` di-parse dari payload tapi cuma dipakai di cabang `WALLET_PERMANENT`; untuk `BILL_DIRECT`, sistem percaya penuh ke `pembayaran_tagihan.amount_allocated` yang tersimpan sejak VA dibuat, apa pun yang BRI benar-benar laporkan sebagai amount masuk. **Diperbaiki:** guard `bccomp($va->amount, $amountPaid)` di cabang `BILL_DIRECT` — throw (ditangkap fix #1 di atas, jadi 500) kalau tidak cocok, transaction rollback penuh (VA/pembayaran/tagihan semua tidak tersentuh saat mismatch). Cabang `WALLET_PERMANENT` SENGAJA tidak disentuh — top-up wallet memang dirancang dengan amount dinamis, tidak ada "expected amount" untuk dibandingkan (beda kasus dari kolom `pembayaran.amount` di atas, yang soal retry bukan soal validasi — lihat log audit untuk penjelasan lengkap perbedaannya).

**Yang DIKONFIRMASI BENAR (bukan bug) saat audit ini:** `lockForUpdate()` untuk `bri_virtual_accounts` DAN `bri_qris_payments` sudah diverifikasi ulang ada dan diposisikan benar (di dalam `DB::transaction()`, sebelum mutasi apa pun) — klaim di "Apa yang dikerjakan" poin 4 di atas terbukti akurat.

**Untuk sesi/agent berikutnya:** modul Payment Channels (sub-project 04) dinyatakan bersih setelah audit + fix ini. Item terbuka yang TERSISA hanya poin 1 dan 2 di atas (Manual Payment UI integration, Polling Schedule review) — keduanya soal desain/keputusan produk, bukan bug.
