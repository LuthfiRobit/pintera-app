# Spec: Keuangan 04 — Payment Channels

**Tanggal:** 2026-08-11
**Status:** Final (post-grill-me review)
**Referensi:** Sub-project 01 (skema dasar), Sub-project 03 (Wallet::topup/debit, konvensi transaction)

## Konteks dan Dependensi

- **Sub-project 1**: skema dasar pembayaran, tagihan, siswa.
- **Sub-project 2 (2a, 2b-1/2/3)**: tagihan PPDB dan non-PPDB; status dibatalkan pada tagihan.
- **Sub-project 3**: model Wallet, wallet_mutasi, Wallet::topup() / debit() / debitWithinTransaction().

WAJIB DIBACA sebelum menyentuh Wallet: Lihat .agents/logs/keuangan-03-wallet-auto-allocation.md
bagian Konvensi Wallet::debit() vs Wallet::debitWithinTransaction() — WAJIB DIBACA Agent Sub-project 04.

## Tujuan Sub-project 04

Membangun backend service layer (tidak ada UI baru) untuk:
1. Virtual Account BRI (VA) — dua tipe: BILL_DIRECT (tagihan) dan WALLET_PERMANENT (top-up wallet).
2. QRIS Dinamis — hanya DIRECT (bayar tagihan). QRIS untuk top-up wallet TIDAK dalam scope ini.
3. Transfer Manual — backend only: create record + alokasi saat diapprove admin.
4. Tunai (Cash di Loket) — backend only: create pembayaran langsung lunas + alokasi.

Tidak termasuk scope: UI Parent Portal (Sub-project 06), UI Admin Kasir.

## Keputusan Arsitektural (hasil grill-me 2026-08-11)

- VA Permanen lifecycle: Satu per siswa, seumur hidup. expired_at=NULL, status=PERMANENT selamanya.
- QRIS scope: Hanya DIRECT. Top-up wallet via VA Permanen atau Transfer Manual.
- Webhook atomicity: Step 1-3 dalam outer DB::transaction(). topup() dipanggil SETELAH commit.
- Webhook retry: Tidak ada retry internal. Return 401 jika signature gagal.
- Idempotency: Cek pembayaran.status == lunas atau bri_record.status == PAID sebelum alokasi.
- Double payment guard: Boleh buat VA/QRIS baru selama tagihan belum lunas.
- BriSnapGateway: Hanya stub (throw NotImplementedException).
- MockPaymentGateway: Full implementation + /dev/simulate-payment/{reference}.
- Service layer: PaymentService (orchestrate) + PaymentAllocationService (alokasi).
- Gateway reference: Pakai pembayaran.channel_reference yang sudah ada.
- Tambah kolom siswa_id di pembayaran (nullable FK).
- Extend enum pembayaran.status: tambah menunggu_pembayaran (pertahankan nilai lama).

## Abstraksi Payment Gateway

Interface: app/Contracts/PaymentGatewayInterface.php

Methods:
- createVirtualAccount(array params): VirtualAccountResult
- createQris(array params): QrisResult
- checkStatus(string reference): PaymentStatusResult
- verifyCallbackSignature(Request request): bool

Value Objects (DTO):
- VirtualAccountResult: va_number, expired_at, reference, gateway_raw_response
- QrisResult: qr_code, expired_at, reference, gateway_raw_response
- PaymentStatusResult: status (WAITING|PAID|EXPIRED), paid_at, amount

Gateway implementations:
- MockPaymentGateway: VA format MOCK-VA-{siswa_id}-{dechex(time())}, verifyCallbackSignature() always true.
- BriSnapGateway: stub, throw RuntimeException('BriSnapGateway not implemented: awaiting credentials').
- Binding: config('services.bri.gateway') = mock (default) | bri_snap. Daftarkan di AppServiceProvider.

## Perubahan Skema Database

### Tabel bri_virtual_accounts (BARU)
- id
- pembayaran_id: FK -> pembayaran nullable cascade delete
- wallet_id: FK -> wallets nullable (untuk WALLET_PERMANENT)
- va_type: ENUM('WALLET_PERMANENT', 'BILL_DIRECT')
- va_number: string unique
- amount: decimal(15,2) nullable (NULL untuk WALLET_PERMANENT)
- expired_at: timestamp nullable (NULL untuk WALLET_PERMANENT)
- status: ENUM('PERMANENT', 'WAITING', 'PAID', 'EXPIRED')
- callback_payload: json nullable
- timestamps
Index: va_number unique, (wallet_id, va_type)

### Tabel bri_qris_payments (BARU)
- id
- pembayaran_id: FK -> pembayaran cascade delete
- qris_type: ENUM('DIRECT') [TOPUP dihapus dari scope]
- amount: decimal(15,2)
- qr_code: text
- expired_at: timestamp
- status: ENUM('WAITING', 'PAID', 'EXPIRED')
- callback_payload: json nullable
- timestamps
Index: (pembayaran_id, status)

### Tabel manual_payment_requests (BARU)
- id
- pembayaran_id: FK -> pembayaran cascade delete
- requested_by: FK -> users
- amount: decimal(15,2)
- transfer_proof_path: string
- bank_origin: string nullable
- transfer_date: date
- status: ENUM('PENDING', 'APPROVED', 'REJECTED')
- reviewed_by: FK -> users nullable
- reviewed_at: timestamp nullable
- rejection_reason: text nullable
- timestamps

### Migrasi pembayaran (MODIFIKASI)
a) Tambah kolom siswa_id (nullable FK ke siswa, SET NULL on delete) setelah wallet_id.
b) Extend enum status: tambah 'menunggu_pembayaran' tanpa hapus nilai existing.
   Nilai existing: menunggu_verifikasi, lunas, ditolak.
c) Tambah kolom topup_status ENUM('none', 'pending', 'completed', 'failed') NOT NULL DEFAULT 'none' setelah status.

## Service Layer

### PaymentService (orchestrator)
- createVaPayment(tagihanIds[], siswaId): Pembayaran
- createQrisPayment(tagihanIds[], siswaId): Pembayaran
- getOrCreatePermanentVa(siswaId): BriVirtualAccount (idempotent)
- createManualPayment(data[], siswaId): Pembayaran
- createCashPayment(tagihanIds[], amount, siswaId): Pembayaran (langsung lunas + alokasi)

Guard: tagihan dengan status dibatalkan ditolak dari semua alur.
expired_at: now() + min(va_expire_hours dari semua jenis_tagihan yang dipilih).

### PaymentAllocationService (alokasi)
- allocate(Pembayaran): void
  Membaca pembayaran_tagihan, update tagihan.paid_amount dan tagihan.status.
  Tagihan dibatalkan dilewati.

*Catatan untuk Transfer Manual:* Alur approval Manual Payment oleh admin mematuhi konvensi transaction yang persis sama dengan webhook (lihat Alur Webhook di bawah): update status pembayaran dan alokasi berjalan di dalam `DB::transaction()`, sementara eksekusi `topup()` (serta status tracking `completed`/`failed`) dijalankan **di luar** (setelah commit).

## Alur Webhook

POST /webhook/bri/payment-notification:
1. verifyCallbackSignature() gagal -> return 401 (BRI retry dari sisinya)
2. Lookup bri_virtual_accounts atau bri_qris_payments by reference
3. Idempotency: jika pembayaran.status == lunas atau bri_record.status == PAID -> return 200, stop
4. DB::transaction() {
     bri_record.status = PAID, simpan callback_payload
     pembayaran.status = lunas, diverifikasi_pada = now()
     PaymentAllocationService::allocate(pembayaran)
   } // commit
5. JIKA pembayaran.wallet_id:
     try {
         $wallet->topup($amount, $pembayaran)  // DI LUAR transaction (konvensi log 03)
         pembayaran.update(['topup_status' => 'completed'])
     } catch (\Exception $e) {
         pembayaran.update(['topup_status' => 'failed'])
         // log error
     }
     (tetap return 200, tagihan sudah aman)
6. return 200

## Polling Fallback

Command: billing:poll-bri-status
Jadwal: everyFifteenMinutes() via Laravel Task Scheduler

Algoritma:
1. Ambil bri_virtual_accounts dengan status=WAITING dan expired_at > now()
2. Ambil bri_qris_payments dengan status=WAITING dan expired_at > now()
3. Untuk setiap record: $gateway->checkStatus($reference)
4. Jika PAID: jalankan alur alokasi sama seperti webhook handler
5. Jika EXPIRED: update bri_record.status = EXPIRED, biarkan pembayaran.status = menunggu_pembayaran

VA Permanen (expired_at = NULL) tidak di-poll.

## Topup Reconciliation

Command: billing:retry-failed-topup
Jadwal: everyHour() via Laravel Task Scheduler

Algoritma:
1. Ambil pembayaran dengan status=lunas, wallet_id IS NOT NULL, dan topup_status IN ('pending', 'failed')
2. Untuk setiap pembayaran, coba jalankan `$wallet->topup($amount, $pembayaran)`
3. Jika berhasil, update `topup_status = 'completed'`
4. Jika gagal lagi, log error dan biarkan statusnya tetap `failed` untuk retry berikutnya.

## MockPaymentGateway Detail

- VA number: MOCK-VA-{siswa_id}-{dechex(time())}
- QRIS: MOCK-QR-{pembayaran_id}-{random(6)}
- verifyCallbackSignature(): selalu true
- checkStatus(): lookup di DB by channel_reference, return status aktual
- Endpoint dev: POST /dev/simulate-payment/{reference} (hanya jika app()->isLocal())
  Trigger alur webhook handler (method call, bukan HTTP roundtrip)

## Struktur File

app/Contracts/PaymentGatewayInterface.php
app/DTO/VirtualAccountResult.php
app/DTO/QrisResult.php
app/DTO/PaymentStatusResult.php
app/Exceptions/PaymentException.php
app/Services/Finance/PaymentService.php
app/Services/Finance/PaymentAllocationService.php
app/Services/Finance/Gateway/MockPaymentGateway.php
app/Services/Finance/Gateway/BriSnapGateway.php
app/Models/BriVirtualAccount.php
app/Models/BriQrisPayment.php
app/Models/ManualPaymentRequest.php
app/Http/Controllers/WebhookController.php
app/Console/Commands/PollBriStatusCommand.php
app/Console/Commands/RetryFailedTopupCommand.php
database/migrations/*_create_bri_virtual_accounts_table.php
database/migrations/*_create_bri_qris_payments_table.php
database/migrations/*_create_manual_payment_requests_table.php
database/migrations/*_add_siswa_id_and_status_to_pembayaran_table.php

## Acceptance Criteria

1. MockPaymentGateway::createVirtualAccount() menghasilkan VA number unik per panggilan.
2. getOrCreatePermanentVa() untuk siswa yang sama return record yang sama (idempotent).
3. Webhook dengan signature invalid return 401 tanpa mutasi database.
4. Webhook yang sama dipanggil dua kali: alokasi hanya dieksekusi sekali (idempotency).
5. Setelah webhook sukses: bri_va.status=PAID, pembayaran.status=lunas, tagihan.paid_amount terupdate.
6. Setelah webhook top-up wallet: wallet.balance bertambah, wallet_mutasi tercipta.
7. Tagihan dibatalkan tidak bisa masuk alur pembayaran (PaymentService return error).
8. billing:poll-bri-status memanggil checkStatus() hanya untuk WAITING + expired_at > now().
9. /dev/simulate-payment hanya aktif di environment local.
10. Semua tabel baru cascade delete saat pembayaran dihapus.
