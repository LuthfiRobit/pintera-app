# Keuangan 04 — Payment Channels Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun backend service layer untuk Payment Channels (VA, QRIS, Manual, Cash) dengan abstraksi gateway, webhook, dan mekanisme rekonsiliasi.

**Architecture:** Menggunakan `PaymentGatewayInterface` untuk abstraksi. Flow webhook menggunakan outer transaction untuk pembayaran+alokasi, dan memanggil `Wallet::topup()` di luar transaction dengan status tracking `topup_status`. Tidak ada UI yang dibangun di sub-project ini.

**Tech Stack:** Laravel 11, SQLite (testing) / MySQL (production), PHP 8.2

## Global Constraints

- Test menggunakan TDD: tulis test yang spesifik (Feature/Unit dalam namespace Keuangan), pastikan gagal, implementasi, pastikan pass.
- Setiap task harus di-commit (granular per task ke local `demo` branch).
- Hindari re-locking (nested transaction) saat topup. Gunakan `Wallet::topup()` di luar transaction webhook.
- Jangan menebak business logic, batasi implementasi murni sesuai spec.
- Tidak push ke remote repository.

---

### Task 1: Migrations & Models

**Files:**
- Create: `database/migrations/*_add_siswa_id_and_status_to_pembayaran_table.php`
- Create: `database/migrations/*_create_bri_virtual_accounts_table.php`
- Create: `database/migrations/*_create_bri_qris_payments_table.php`
- Create: `database/migrations/*_create_manual_payment_requests_table.php`
- Create: `app/Models/BriVirtualAccount.php`
- Create: `app/Models/BriQrisPayment.php`
- Create: `app/Models/ManualPaymentRequest.php`
- Modify: `app/Models/Pembayaran.php`
- Test: `tests/Feature/Keuangan/PaymentChannelModelsTest.php`

**Interfaces:**
- Consumes: Skema dasar pembayaran, tagihan, siswa, wallet.
- Produces: Skema database baru siap digunakan oleh Service Layer.

- [ ] **Step 1: Write the failing test**
  Buat `tests/Feature/Keuangan/PaymentChannelModelsTest.php` yang menguji:
  - Pembayaran punya `siswa_id` (belongsTo Siswa).
  - Pembayaran bisa punya `topup_status` enum.
  - Relasi dari Pembayaran ke `BriVirtualAccount`, `BriQrisPayment`, `ManualPaymentRequest`.

- [ ] **Step 2: Run test to verify it fails**
  Run: `php artisan test --filter PaymentChannelModelsTest`
  Expected: FAIL

- [ ] **Step 3: Write minimal implementation**
  Buat 4 file migration sesuai spec:
  1. Modifikasi `pembayaran` (tambah nullable `siswa_id`, extend enum `status` dengan `menunggu_pembayaran`, tambah `topup_status` ENUM default 'none').
  2. Create `bri_virtual_accounts` (termasuk `va_type`, enum status, foreign keys).
  3. Create `bri_qris_payments` (termasuk `qris_type`, enum status).
  4. Create `manual_payment_requests`.
  Buat model untuk ketiga tabel baru beserta casts untuk `callback_payload`. Update `Pembayaran.php` menambah relasi.

- [ ] **Step 4: Run test to verify it passes**
  Run: `php artisan test --filter PaymentChannelModelsTest`
  Expected: PASS

- [ ] **Step 5: Commit**
  `git add database/migrations/ app/Models/ tests/Feature/Keuangan/PaymentChannelModelsTest.php`
  `git commit -m "feat(keuangan): create migrations and models for payment channels"`

---

### Task 2: Gateway Abstraction & Mock Implementation

**Files:**
- Create: `app/Contracts/PaymentGatewayInterface.php`
- Create: `app/DTO/VirtualAccountResult.php`
- Create: `app/DTO/QrisResult.php`
- Create: `app/DTO/PaymentStatusResult.php`
- Create: `app/Exceptions/PaymentException.php`
- Create: `app/Services/Finance/Gateway/MockPaymentGateway.php`
- Create: `app/Services/Finance/Gateway/BriSnapGateway.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Keuangan/GatewayImplementationTest.php`

**Interfaces:**
- Consumes: Models.
- Produces: `PaymentGatewayInterface` yang ter-bind di container Laravel.

- [ ] **Step 1: Write the failing test**
  Uji `MockPaymentGateway` memproduksi deterministic `MOCK-VA-` dan `MOCK-QR-`.
  Uji `BriSnapGateway` throws exception.
  Uji binding via `app()->make(PaymentGatewayInterface::class)`.

- [ ] **Step 2: Run test to verify it fails**
  Run: `php artisan test --filter GatewayImplementationTest`
  Expected: FAIL

- [ ] **Step 3: Write minimal implementation**
  Buat Interface dan DTOs.
  Implementasikan `MockPaymentGateway` sesuai spec (va_number deterministic, verifyCallback selalu true, dll).
  Implementasikan `BriSnapGateway` sebagai stub melempar `\RuntimeException('BriSnapGateway not implemented: awaiting credentials')`.
  Bind di `AppServiceProvider` membaca `config('services.bri.gateway')` fallback ke 'mock'.

- [ ] **Step 4: Run test to verify it passes**
  Run: `php artisan test --filter GatewayImplementationTest`
  Expected: PASS

- [ ] **Step 5: Commit**
  `git add app/Contracts/ app/DTO/ app/Exceptions/ app/Services/Finance/Gateway/ app/Providers/ tests/`
  `git commit -m "feat(keuangan): implement payment gateway abstraction and mock"`

---

### Task 3: PaymentAllocationService

**Files:**
- Create: `app/Services/Finance/PaymentAllocationService.php`
- Test: `tests/Feature/Keuangan/PaymentAllocationServiceTest.php`

**Interfaces:**
- Produces: Class service yang mengupdate status dan `paid_amount` tagihan berdasar `pembayaran_tagihan`.

- [ ] **Step 1: Write the failing test**
  Uji bahwa `allocate(Pembayaran)` akan menambah `paid_amount` pada tagihan terkait, mengubah status tagihan ('sebagian' / 'lunas'), dan melewati tagihan dengan status 'dibatalkan'.

- [ ] **Step 2: Run test to verify it fails**
  Run: `php artisan test --filter PaymentAllocationServiceTest`
  Expected: FAIL

- [ ] **Step 3: Write minimal implementation**
  Buat `PaymentAllocationService::allocate(Pembayaran $pembayaran)` yang membaca relasi `tagihan` (via `pembayaran_tagihan`), update `paid_amount`, dan update status.

- [ ] **Step 4: Run test to verify it passes**
  Run: `php artisan test --filter PaymentAllocationServiceTest`
  Expected: PASS

- [ ] **Step 5: Commit**
  `git add app/Services/Finance/PaymentAllocationService.php tests/`
  `git commit -m "feat(keuangan): implement payment allocation service"`

---

### Task 4: PaymentService (Orchestrator)

**Files:**
- Create: `app/Services/Finance/PaymentService.php`
- Test: `tests/Feature/Keuangan/PaymentServiceTest.php`

**Interfaces:**
- Consumes: `PaymentGatewayInterface`, `PaymentAllocationService`.
- Produces: Service sentral pembuat pembayaran.

- [ ] **Step 1: Write the failing test**
  Uji `createVaPayment`, `createQrisPayment` (menyimpan record VA/QRIS WAITING, return Pembayaran).
  Uji `getOrCreatePermanentVa` (idempotent permanent VA per siswa).
  Uji guard: tidak bisa buat pembayaran jika tagihan 'dibatalkan'.

- [ ] **Step 2: Run test to verify it fails**
  Run: `php artisan test --filter PaymentServiceTest`
  Expected: FAIL

- [ ] **Step 3: Write minimal implementation**
  Implementasi ke-5 method di `PaymentService` (createVaPayment, createQrisPayment, getOrCreatePermanentVa, createManualPayment, createCashPayment). Hitungan `expired_at` dari min(`va_expire_hours`). Hubungkan dengan `$gateway->createVirtualAccount()` / `createQris()`.

- [ ] **Step 4: Run test to verify it passes**
  Run: `php artisan test --filter PaymentServiceTest`
  Expected: PASS

- [ ] **Step 5: Commit**
  `git add app/Services/Finance/PaymentService.php tests/`
  `git commit -m "feat(keuangan): implement main payment service orchestrator"`

---

### Task 5: WebhookController & Mock Simulate

**Files:**
- Create: `app/Http/Controllers/WebhookController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Keuangan/WebhookControllerTest.php`

**Interfaces:**
- Consumes: `PaymentGatewayInterface`, `PaymentAllocationService`.

- [ ] **Step 1: Write the failing test**
  Uji POST webhook signature invalid return 401.
  Uji webhook sukses: transaksi commit (tagihan lunas), diakhiri dengan Wallet `topup()` di luar transaction (wallet ter-topup, `topup_status` = 'completed').
  Uji webhook topup exception: tetap return 200, `topup_status` = 'failed'.
  Uji idempotency dan concurrent race condition (webhook kedua/duplikat bersamaan di-ignore dengan lockForUpdate).

- [ ] **Step 2: Run test to verify it fails**
  Run: `php artisan test --filter WebhookControllerTest`
  Expected: FAIL

- [ ] **Step 3: Write minimal implementation**
  Buat `WebhookController@handle`.
  Routing: `POST /webhook/bri/payment-notification`.
  Implementasi transaction atomicity untuk step 1-3. Gunakan `lockForUpdate()` pada record VA/QRIS di dalam transaction dan re-check status setelah mendapatkan lock.
  Step 4 (topup) di luar transaction di-wrap dalam try-catch, set `topup_status`.
  Tambahkan endpoint dev `/dev/simulate-payment/{reference}` khusus local env di `routes/api.php`.

- [ ] **Step 4: Run test to verify it passes**
  Run: `php artisan test --filter WebhookControllerTest`
  Expected: PASS

- [ ] **Step 5: Commit**
  `git add app/Http/Controllers/ routes/ tests/`
  `git commit -m "feat(keuangan): implement payment webhook endpoint and flow"`

---

### Task 6: Polling & Reconciliation Commands

**Files:**
- Create: `app/Console/Commands/PollBriStatusCommand.php`
- Create: `app/Console/Commands/RetryFailedTopupCommand.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Keuangan/PaymentCommandsTest.php`

**Interfaces:**
- Consumes: DB state, Gateway.

- [ ] **Step 1: Write the failing test**
  Uji perintah `billing:poll-bri-status` mengambil VA WAITING, memanggil gateway checkStatus, dan alokasi jika PAID.
  Uji perintah `billing:retry-failed-topup` mencoba topup ulang pembayaran berstatus lunas yang `topup_status` = 'failed'.

- [ ] **Step 2: Run test to verify it fails**
  Run: `php artisan test --filter PaymentCommandsTest`
  Expected: FAIL

- [ ] **Step 3: Write minimal implementation**
  Implementasikan kedua artisan command.
  Jadwalkan di `routes/console.php` (setiap 15 menit dan setiap jam).

- [ ] **Step 4: Run test to verify it passes**
  Run: `php artisan test --filter PaymentCommandsTest`
  Expected: PASS

- [ ] **Step 5: Commit**
  `git add app/Console/ routes/console.php tests/`
  `git commit -m "feat(keuangan): add cron commands for polling and topup reconciliation"`
