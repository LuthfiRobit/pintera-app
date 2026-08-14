# Handoff Log: Integrasi BRI SNAP API (QRIS Real)

**Referensi Plan:** `docs/superpowers/plans/2026-08-14-bri-snap-qris-integration.md`
**Referensi Spec:** `docs/superpowers/specs/2026-08-14-bri-snap-qris-integration-design.md`

## Apa yang dikerjakan

1. `BriApiException` untuk membungkus error response BRI SNAP.
2. `BriSnapClient` — token retrieval + caching (TTL 850s), signature asimetris (RSA-SHA256, untuk Get Token) dan simetris (HMAC-SHA512, untuk call lain), HTTP POST generik. Diverifikasi protokolnya persis sesuai `bri-api.md` termasuk body/signature byte-identity (body yang di-hash sama persis dengan body yang dikirim).
3. `PaymentGatewayInterface::checkStatus()` — tambah parameter `$type` (`'va'|'qris'`), diterapkan ke semua implementer (`MockPaymentGateway`, `BriSnapGateway`) dan semua call site (termasuk 2 call site di `ReconcilePayments`).
4. Migration `reference_no` di `bri_qris_payments` + `PaymentService` menyimpannya dari response BRI.
5. `BriSnapGateway::createQris()` dan `checkStatus(..., 'qris')` — implementasi asli lewat `BriSnapClient`. `createVirtualAccount()`, `checkStatus(..., 'va')`, dan `verifyCallbackSignature()` tetap `throw` (VA sengaja di luar scope, menunggu jawaban BRI soal skema autentikasi callback inbound).
6. `HybridPaymentGateway` — route QRIS ke `BriSnapGateway` (real), VA ke `MockPaymentGateway`. `AppServiceProvider` dapat opsi binding `'hybrid'` baru, di samping `'snap'` dan `'mock'` yang sudah ada.
7. `bri:test-qris` — command manual untuk verifikasi berulang ke sandbox BRI asli (di luar suite otomatis, karena sengaja menghubungi network asli).
8. Fix drive-by: `LembagaFactory` (`unique()->city()`) untuk mengatasi `UniqueConstraintViolationException` yang muncul saat menjalankan suite Keuangan penuh.

## Siklus review yang dijalani

- **Whole-branch review** (commit `1346eea`) menemukan 2 Critical + 6 Important + 4 Minor — termasuk `ReconcilePayments` yang belum dialihkan dari `qr_code` ke `reference_no` (akan membuat reconciliation QRIS gagal diam-diam begitu gateway di-flip ke `hybrid`), command `bri:test-qris` yang cuma bisa dipakai sekali (partnerReferenceNo konstan), dan `verifyCallbackSignature()` yang diam-diam berubah dari `throw` jadi `return false` (melanggar batasan scope plan).
- **Fix wave** (commit `520eaf2`) menutup seluruh 12 temuan.
- **Re-review independen** memverifikasi ulang seluruh 12 temuan dari diff-nya sendiri (bukan percaya laporan fixer) — semua **CLOSED**, termasuk validasi bahwa guard null di `ReconcilePayments` benar-benar diletakkan sebelum `checkStatus()` dipanggil, dan `$pembayaran->id` di `bri:test-qris` benar-benar bisa di-assign langsung tanpa terhalang mutator/cast. Ditemukan 5 item Minor baru dari fix diff itu sendiri (webhook bisa 500 alih-alih 401 kalau `gateway=snap` dipakai langsung bukan lewat `hybrid`; skip baris `reference_no` null tanpa logging; satu assertion placeholder di test baru; padding tidak berefek di `bri:test-qris`; gaya FQCN inline) — tidak ada yang blocking.
- **Hasil akhir**: 258 test passed (666 assertions) untuk scope `tests/Feature/Keuangan/ tests/Unit/Services/Finance/Gateway/ tests/Unit/Exceptions/`.

## Keputusan penting yang diambil

- `BriSnapClient` didaftarkan sebagai singleton (`fromConfig()`) di `AppServiceProvider::register()` supaya Laravel bisa autowire `BriSnapGateway`/`HybridPaymentGateway`.
- `partnerReferenceNo` untuk QRIS memakai `pembayaran->id` (bukan `channel_reference` yang UUID 36 karakter) — deviasi disengaja dari plan, didokumentasikan langsung sebagai komentar kode karena dokumentasi BRI sendiri kontradiktif soal panjang field ini.
- Status `checkStatus()` QRIS di-map ke `WAITING`/`PAID`/`FAILED` (mengikuti vocabulary kolom `bri_qris_payments.status`), bukan `PENDING`/`PAID` biner mentah dari plan.

## Yang masih terbuka (di luar scope sub-project ini)

- **VA (Virtual Account) real** — `createVirtualAccount()`, `checkStatus($ref, 'va')` versi BRI, dan `verifyCallbackSignature()` versi BRI masih throw. Diblokir pertanyaan yang masih menunggu jawaban BRI: siapa yang jadi token issuer untuk 3 endpoint inbound VA (Inquiry, Payment, Notify).
- **`BriWebhookController`** tidak disentuh — bentuknya masih placeholder API BRI generasi lama (Non-SNAP), bukan SNAP BI yang kita pakai sekarang. Perlu ditulis ulang total saat VA dikerjakan.
- **Action wajib untuk user**: isi `BRI_SNAP_PARTNER_ID`, `BRI_SNAP_CHANNEL_ID`, `BRI_SNAP_MERCHANT_ID`, `BRI_SNAP_TERMINAL_ID` di `.env` (masih kosong), lalu jalankan `php artisan bri:test-qris <amount>` untuk verifikasi manual ke sandbox asli sebelum memutuskan mengubah `BRI_PAYMENT_GATEWAY` dari `mock` ke `hybrid`.
- 5 item Minor dari re-review (lihat "Siklus review yang dijalani" di atas) belum ditindaklanjuti — tidak blocking, bisa dikerjakan kapan saja.

Status Branch: `demo`, working tree bersih setelah commit ini, semua test scoped hijau.
