# Handoff Log: BRI SNAP VA Inbound Integration
**Date:** 2026-08-15
**Task:** 2026-08-15-bri-snap-va-inbound
**Related Spec:** `.agents/specs/2026-08-15-bri-snap-va-inbound.md` (or `docs/superpowers/specs/2026-08-15-bri-snap-va-inbound-design.md`)
**Related Plan:** `.agents/plans/2026-08-15-bri-snap-va-inbound.md` (or `docs/superpowers/plans/2026-08-15-bri-snap-va-inbound.md`)

## Apa yang dikerjakan
Sistem integrasi BRI Virtual Account lama (webhook based) telah berhasil dimigrasikan menjadi sistem standar BRI SNAP (Inbound VA) yang mendukung idempotency dan sinkronisasi top-up wallet permanen secara *real-time*.
1. **Migration & Model:** Pembuatan tabel `bri_inbound_payment_logs` dengan constraint `unique(payment_request_id)` untuk memastikan idempotency request pembayaran dari BRI.
2. **Authenticator Interface:** Pembuatan `BriInboundAuthenticatorInterface` & `SimpleBriInboundAuthenticator` agar modul otentikasi token untuk BRI B2B bisa dipisah dan di-mock dalam testing.
3. **Gateway Generation:** Pembuatan `BriSnapGateway::createVirtualAccount()` untuk meng-generate nomor VA lokal menggunakan format `<PartnerServiceId><CustomerNo(siswa_id di-padding 20 digit)>`.
4. **Refactoring PaymentService & Checkout:** 
   - Penghapusan fitur bundling VA dinamis lama (hanya menyisakan VA permanen ke wallet).
   - Pembuatan endpoint `CheckoutController::va()` dan `vaInfo()` untuk secara otomatis redirect ortu ke laman informasi VA (karena VA sekarang statis).
5. **Inbound Endpoints (`/snap/v1.0/*`):**
   - **B2B Token:** Menerbitkan access token lokal yang akan digunakan BRI memanggil endpoint di bawahnya.
   - **VA Inquiry:** Mengecek status akun VA beserta jumlah tagihannya (read-only).
   - **VA Payment:** Menerima push notification dari BRI. Memasukkan riwayat ke `bri_inbound_payment_logs` dan memicu `Wallet::topup()` di luar `DB::transaction()` agar deadlock/timeout bisa dihindari saat event listener berjalan berat.
6. **Clearing Dead-Code:** Menghapus `BriWebhookController` lama, file test yang relevan dengan legacy webhook, serta mematikan logika polling sinkronisasi/reconciliation VA dari command `finance:reconcile-payments`.
7. **Simulasi Command:** Pembuatan command `php artisan bri:test-va-inbound <va_number> <amount>` untuk men-trigger endpoint Inbound VA lokal dan mendemonstrasikan end-to-end webhook injection test di development.

## Keputusan penting yang diambil
1. **Urutan Ledger-Dulu-Baru-Topup, Dua `try/catch` Terpisah, Selalu Return Sukses ke BRI Setelah Ledger Tercatat:** Endpoint payment memvalidasi field wajib dan cek idempotency (baris `bri_inbound_payment_logs` dengan `payment_request_id` yang sama) SEBELUM menyentuh apa pun yang lain — kalau log sudah ada, langsung return sukses tanpa proses ulang. Kalau belum, sebuah `Pembayaran` (`topup_status: pending`) dibuat, lalu baris `BriInboundPaymentLog` dicoba ditulis dalam `try/catch` PERTAMA yang menangkap `\Throwable` apa pun (termasuk race unique-constraint dari request concurrent) — kalau gagal, `Pembayaran` yang baru dibuat itu dihapus lagi dan kita anggap ini replay idempotent dari request lain yang sudah lebih dulu berhasil mencatat, lalu return sukses.

   Begitu baris ledger berhasil tercatat, `$wallet->topup($amount, $pembayaran, ...)` dipanggil di dalam `try/catch` KEDUA yang terpisah, juga menangkap `\Throwable` apa pun. Ini penting karena `Wallet::topup()` punya transaksi database sendiri yang HANYA membungkus increment saldo + insert `wallet_mutasi` — begitu itu commit, `AutoAllocationEngine::run()` dipanggil DI LUAR transaksi itu (lihat `app/Models/Wallet.php`). Artinya kalau `AutoAllocationEngine::run()` throw (atau kegagalan apa pun terjadi setelah commit saldo), saldo wallet SUDAH bertambah secara permanen di database sebelum exception itu muncul — rollback tidak lagi mungkin menghapus penambahan saldo tersebut.

   Karena itu, satu-satunya pilihan yang aman adalah: `Log::error(...)` exception-nya untuk investigasi manual, set `$pembayaran->topup_status = 'failed'`, dan TETAP return response sukses (`paymentFlagStatus: "00"`) ke BRI — bukan error. Kalau kita balas error di titik ini, BRI akan meretry `paymentRequestId` yang sama, tapi replay itu akan langsung kena idempotency check di langkah pertama dan berhenti tanpa memproses ulang topup — sehingga uang yang sudah masuk ke saldo tidak akan pernah "diperbaiki" otomatis oleh retry BRI. Kegagalan di titik ini harus ditangani manual oleh operator berdasarkan log error di atas (mis. lewat `finance:reconcile-payments`'s `retryFailedTopups()` untuk memicu ulang alokasi/notifikasi, saldo itu sendiri sudah aman).
2. **Checkout VA Redirection:** Karena logic VA telah disederhanakan murni menjadi *Wallet Top-up* via VA permanen, menekan "Bayar menggunakan VA" kini tidak lagi membuat record `Pembayaran` dengan status 'waiting'. Melainkan langsung meng-generate VA permanen di database dan mem-forward *parent* ke laman `va-info.blade.php`.
3. **Internal App Request untuk Command Simulasi:** Command `bri:test-va-inbound` dibuat memanggil route internal Laravel (`app()->handle(Request::create(...))`) alih-alih `Http::post(url(...))` agar fungsional simulasi ini bisa jalan dengan stabil walau di ranah CLI local environment (misal ketika webserver tidak sedang dijalankan via `php artisan serve`).

## Hal yang masih perlu direview manusia/Claude
1. **Frontend Views (va-info):** Tampilan statis `va-info.blade.php` masih bersifat MVP (menggunakan Tailwind standard UI dari template Blade). Mungkin perlu direview agar warnanya atau estetika panduannya sejalan dengan tema brand Pintera.
2. **KenaikanKelasControllerTest:** Task ini secara sengaja mendefer / tidak mengerjakan perbaikan error pada `KenaikanKelasControllerTest` yang ditemukan di awal karena berfokus murni pada pipeline BRI SNAP VA Inbound. Harus ada follow-up task berikutnya untuk mengeksplorasi error tersebut secara terpisah.
3. **Environment Variables:** Jangan lupa memastikan `services.bri.inbound.client_id` dan `services.bri.inbound.client_secret` sudah tertanam di `.env` staging/production.
