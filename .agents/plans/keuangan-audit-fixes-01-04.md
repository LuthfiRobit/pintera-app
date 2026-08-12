# Keuangan Cross-Sub-Project Audit Fixes (Sub-project 1-4) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix 3 confirmed bugs found during a cross-sub-project (1-4) audit of the Modul Keuangan Sekolah Dinamis, before starting Sub-project 5 (Notifikasi).

**Diagnosis source:** This plan has no separate spec document — the diagnosis was done directly in conversation (2026-08-11 audit session) by reading the actual shipped code against the specs/logs for Sub-project 2b-3, 3, and 4, and is fully documented per-task below. A 4th finding from the same audit (Sub-project 3's Auto-Allocation Engine policy — "Partial Top-Down" vs the original spec's "skip penuh") was reviewed and confirmed as the CORRECT, intentional, final behavior by the user — no code change needed there. See `.agents/specs/keuangan-03-wallet-auto-allocation.md`'s "Ambiguitas Terselesaikan" section for that resolution note.

**Architecture:** 3 independent bugs in 2 files — no shared code between tasks, safe to execute in any order.

**Tech Stack:** Laravel 12, Pest.

## Global Constraints

- This project had a real incident where multiple concurrent `php artisan test` processes corrupted the shared MySQL test database via racing `migrate:fresh` calls. Only ever run `php artisan test` in the foreground, one command at a time, never in the background, and only run the specific test files named — never the unfiltered full suite mid-task.
- Do not touch `AutoAllocationEngine.php`'s allocation policy (Partial Top-Down) — that was reviewed and confirmed correct in this same audit, not a bug.

---

### Task 1: `JenisTagihanMonitoringController::batalTagihan()` — ownership check must run before the status check

**Files:**
- Modify: `app/Http/Controllers/Admin/JenisTagihanMonitoringController.php`
- Test: `tests/Feature/Admin/JenisTagihanMonitoringTest.php`

**Diagnosis:** `Tagihan` has no `BelongsToTenant`/tenant scoping (confirmed: no such trait on the model), so route-model-binding `Tagihan $tagihan` resolves ANY tagihan row system-wide regardless of lembaga. The current code checks `$tagihan->status !== 'belum_bayar'` (business rule, aborts 422) BEFORE checking `$tagihan->jenis_tagihan_id !== $jenisTagihan->id` (ownership, aborts 403). The actual cancellation itself stays safely blocked either way (the ownership check does still run and block the `->update()` call), but the ORDER lets any authenticated `admin_keuangan` (any lembaga) probe an arbitrary `tagihan` ID's payment status cross-tenant: pairing it with any `jenisTagihan` they legitimately own, the 422-vs-403 response difference reveals whether that arbitrary tagihan's status is `belum_bayar` or not, before ownership is ever verified. Fix: swap the two checks so ownership is verified first, unconditionally, before any business-rule check runs — the same principle already established and validated in this codebase (Sub-project 2b-1's `x-show`-is-not-enough lesson: never let a business-logic branch reveal information before an authorization/ownership check has cleared).

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing new consumed elsewhere.

- [ ] **Step 1: Write the failing test** — append to `tests/Feature/Admin/JenisTagihanMonitoringTest.php`:

```php
it('checks tagihan ownership before the status business rule, preventing a cross-jenis_tagihan status leak', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $jenisTagihanOwned = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihanOther = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    Tagihan::query()->delete();

    // Tagihan belongs to a DIFFERENT jenis_tagihan than the one in the URL, AND its status
    // is NOT belum_bayar. Before the fix, the status check runs first and returns 422
    // (leaking that this arbitrary tagihan is not belum_bayar) instead of 403 (ownership).
    $tagihanFromOtherJenisTagihan = Tagihan::factory()->create([
        'jenis_tagihan_id' => $jenisTagihanOther->id,
        'tagihable_type' => Siswa::class,
        'tagihable_id' => $siswa->id,
        'status' => 'lunas',
    ]);

    $response = $this->actingAs($user)
        ->post(route('admin.jenis-tagihan.monitoring.batal', [$jenisTagihanOwned, $tagihanFromOtherJenisTagihan]), [
            'cancel_reason' => 'Probe percobaan',
        ]);

    $response->assertStatus(403);

    $tagihanFromOtherJenisTagihan->refresh();
    expect($tagihanFromOtherJenisTagihan->status)->toBe('lunas');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/JenisTagihanMonitoringTest.php --filter="ownership before"`
Expected: FAIL — current code returns 422 (status check runs first), not 403.

- [ ] **Step 3: Reorder the checks in `app/Http/Controllers/Admin/JenisTagihanMonitoringController.php`**

Replace:
```php
        if ($tagihan->status !== 'belum_bayar') {
            abort(422, 'Hanya tagihan dengan status belum bayar yang dapat dibatalkan.');
        }

        // Tenant scope: verify tagihan belongs to this jenisTagihan
        if ($tagihan->jenis_tagihan_id !== $jenisTagihan->id) {
            abort(403, 'Tagihan tidak ditemukan untuk jenis tagihan ini.');
        }
```
with:
```php
        // Ownership check MUST run before any business-rule check — otherwise a status-based
        // 422/403 response difference leaks whether an arbitrary cross-tenant tagihan is
        // belum_bayar, before we've verified it even belongs to this jenisTagihan.
        if ($tagihan->jenis_tagihan_id !== $jenisTagihan->id) {
            abort(403, 'Tagihan tidak ditemukan untuk jenis tagihan ini.');
        }

        if ($tagihan->status !== 'belum_bayar') {
            abort(422, 'Hanya tagihan dengan status belum bayar yang dapat dibatalkan.');
        }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/JenisTagihanMonitoringTest.php`
Expected: PASS (9/9 — 8 existing + 1 new)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/JenisTagihanMonitoringController.php tests/Feature/Admin/JenisTagihanMonitoringTest.php
git commit -m "fix(keuangan): check tagihan ownership before status in batalTagihan to prevent cross-tenant status leak"
```

---

### Task 2: `BriWebhookController` must return a non-2xx status when processing genuinely fails

**Files:**
- Modify: `app/Http/Controllers/Api/BriWebhookController.php`
- Test: `tests/Feature/Keuangan/WebhookControllerTest.php`

**Diagnosis:** The outer `catch (\Exception $e) { Log::error(...); }` block (covering the main `DB::transaction()` closure — including the `throw new \Exception("Payment reference not found")` path when neither a matching VA nor QRIS record exists) only logs the error. The unconditional `return response()->json(['status' => 'success']);` after the try/catch runs regardless of whether the catch block fired. Real payment gateways retry webhook delivery on a non-2xx response; this guarantees BRI would never retry a genuinely failed webhook (e.g., a race where the VA record hasn't been created yet, or a transient DB error), and the polling-fallback reconciliation command only queries EXISTING `WAITING` records — it can't discover a payment whose reference was never found in the first place. This does NOT apply to the inner try/catch around `$wallet->topup()` (lines ~116-127) — that failure mode is intentionally handled separately (marks `topup_status = 'failed'` for the reconciliation cron to retry, since the main transaction already committed successfully at that point) and must keep returning 200. Only the OUTER catch needs to change.

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing new consumed elsewhere.

- [ ] **Step 1: Write the failing test** — append to `tests/Feature/Keuangan/WebhookControllerTest.php`:

```php
    public function test_webhook_returns_error_status_when_payment_reference_not_found()
    {
        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('verifyCallbackSignature')->once()->andReturn(true);
        $this->app->instance(PaymentGatewayInterface::class, $mockGateway);

        // No BriVirtualAccount or BriQrisPayment record matches this payload at all.
        $payload = [
            'BrivaNo' => '0000',
            'CustCode' => '000000',
            'Amount' => '10000.00',
            'Status' => 'PAID',
        ];

        $response = $this->postJson('/webhook/bri/payment-notification', $payload, [
            'BRI-Signature' => 'valid'
        ]);

        // Must NOT be a 2xx — BRI needs a failure status to retry delivery.
        $response->assertStatus(500);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/WebhookControllerTest.php --filter="reference_not_found"`
Expected: FAIL — current code returns 200 `{"status": "success"}` even though no record was found.

- [ ] **Step 3: Fix `app/Http/Controllers/Api/BriWebhookController.php`**

Replace the outer catch block:
```php
        } catch (\Exception $e) {
            Log::error("Webhook error: " . $e->getMessage());
        }

        return response()->json(['status' => 'success']);
```
with:
```php
        } catch (\Exception $e) {
            Log::error("Webhook error: " . $e->getMessage());

            return response()->json(['status' => 'error', 'message' => 'Failed to process payment notification'], 500);
        }

        return response()->json(['status' => 'success']);
```

Do NOT change the inner `try`/`catch` around `$wallet->topup(...)` — that block's `catch` correctly marks `topup_status = 'failed'` and does not rethrow, and the function must still return 200 in that case (the webhook itself WAS processed successfully; only the async wallet credit failed and is queued for reconciliation).

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Keuangan/WebhookControllerTest.php`
Expected: PASS (6/6 — 5 existing + 1 new)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/BriWebhookController.php tests/Feature/Keuangan/WebhookControllerTest.php
git commit -m "fix(keuangan): return 500 instead of 200 when the bri webhook fails to find a matching payment reference"
```

---

### Task 3: `BriWebhookController` must validate the webhook-reported amount for `BILL_DIRECT` payments

**Files:**
- Modify: `app/Http/Controllers/Api/BriWebhookController.php`
- Test: `tests/Feature/Keuangan/WebhookControllerTest.php`

**Diagnosis:** `$amountPaid` (parsed from the webhook payload's `Amount` field) is only used in the `WALLET_PERMANENT` branch. For `BILL_DIRECT`, it's parsed but never checked against anything — `PaymentAllocationService::allocate()` unconditionally trusts the pre-existing `pembayaran_tagihan.amount_allocated` pivot value (set at VA-creation time), regardless of what BRI actually reports as paid. `BriVirtualAccount.amount` (confirmed via the existing test fixture and model cast `'amount' => 'decimal:2'`) stores the expected amount for `BILL_DIRECT` VAs (NULLABLE only for `WALLET_PERMANENT`, where the top-up amount is intentionally dynamic). Add a check: for `BILL_DIRECT`, if `$va->amount` is set and doesn't match `$amountPaid`, log a warning and do NOT mark the payment as lunas/allocate — leave the VA in its current state so the mismatch surfaces for manual review rather than silently accepting a wrong amount as full payment. This is a defensive check (some payment rails may enforce exact-amount settlement on their end already) — its value is in never trusting an unvalidated webhook amount at all, consistent with the "never trust client/external input" principle already established across this module (Sub-project 2b-1's server-side PPDB-kategori guard, the PPDB billing-engine guard from earlier in this session).

**Interfaces:**
- Consumes: `BriVirtualAccount::$amount` (existing column).
- Produces: nothing new consumed elsewhere.

- [ ] **Step 1: Write the failing test** — append to `tests/Feature/Keuangan/WebhookControllerTest.php`:

```php
    public function test_webhook_rejects_bill_direct_payment_when_amount_does_not_match()
    {
        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('verifyCallbackSignature')->once()->andReturn(true);
        $this->app->instance(PaymentGatewayInterface::class, $mockGateway);

        $siswa = Siswa::factory()->create();
        $tagihan = Tagihan::factory()->create([
            'total_tagihan' => 100000,
            'net_amount' => 100000,
            'paid_amount' => 0,
            'status' => 'belum_bayar'
        ]);

        $pembayaran = Pembayaran::factory()->create([
            'siswa_id' => $siswa->id,
            'metode' => 'va_bri',
            'status' => 'menunggu_pembayaran'
        ]);

        PembayaranTagihan::create([
            'pembayaran_id' => $pembayaran->id,
            'tagihan_id' => $tagihan->id,
            'amount_allocated' => 100000
        ]);

        $va = BriVirtualAccount::factory()->create([
            'pembayaran_id' => $pembayaran->id,
            'va_type' => 'BILL_DIRECT',
            'va_number' => '1234567890',
            'amount' => 100000,
            'status' => 'WAITING'
        ]);

        // BRI reports a DIFFERENT amount than the VA was created for.
        $payload = [
            'BrivaNo' => '1234',
            'CustCode' => '567890',
            'Amount' => '75000.00',
            'Status' => 'PAID',
        ];

        $response = $this->postJson('/webhook/bri/payment-notification', $payload, [
            'BRI-Signature' => 'valid'
        ]);

        $response->assertStatus(500);

        // Nothing should have been marked paid/lunas — the mismatch blocks allocation.
        $va->refresh();
        expect($va->status)->toBe('WAITING');

        $pembayaran->refresh();
        expect($pembayaran->status)->toBe('menunggu_pembayaran');

        $tagihan->refresh();
        expect($tagihan->paid_amount)->toEqual(0);
        expect($tagihan->status)->toBe('belum_bayar');
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Keuangan/WebhookControllerTest.php --filter="amount_does_not_match"`
Expected: FAIL — current code marks the VA/pembayaran/tagihan as paid regardless of the amount mismatch.

- [ ] **Step 3: Add the amount check to `app/Http/Controllers/Api/BriWebhookController.php`**

Inside the `if ($va->va_type === 'BILL_DIRECT') { ... }` block, before marking `$va->status = 'PAID'`:

```php
                        if ($va->va_type === 'BILL_DIRECT') {
                            if ($va->amount !== null && bccomp((string) $va->amount, (string) $amountPaid, 2) !== 0) {
                                throw new \Exception("Amount mismatch for VA {$vaNumber}: expected {$va->amount}, got {$amountPaid}");
                            }

                            $va->status = 'PAID';
                            $va->save();
```

(Using `bccomp` rather than `==`/`!=` for decimal comparison avoids float-precision false mismatches — matches the `decimal:2` cast already declared on the model.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Keuangan/WebhookControllerTest.php`
Expected: PASS (7/7 — 6 existing/Task-2 + 1 new)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/BriWebhookController.php tests/Feature/Keuangan/WebhookControllerTest.php
git commit -m "fix(keuangan): validate webhook-reported amount against the va's expected amount for bill_direct payments"
```

---

### Task 4: Full regression verification + handoff log

**Files:** none (verification-only task)

- [ ] **Step 1: Run every test file touched by this plan**

```bash
php artisan test tests/Feature/Admin/JenisTagihanMonitoringTest.php tests/Feature/Keuangan/WebhookControllerTest.php
```
Expected: all PASS.

- [ ] **Step 2: Run the full Keuangan suite**

```bash
php artisan test tests/Feature/Keuangan/
```
Expected: all PASS, no new failures beyond whatever pre-existing baseline is currently established.

- [ ] **Step 3: Run the full project suite** (single foreground run, never background, never concurrent with any other `php artisan test` process)

```bash
php artisan test
```
Expected: no NEW failures beyond the established pre-existing baseline (`LembagaCrudTest`, `RoleBuilderTest` x4, `RoleFormAuditBannerTest`).

- [ ] **Step 4: Write the handoff log**

Write `.agents/logs/keuangan-audit-fixes-01-04.md` covering: the cross-sub-project audit that found these issues, the 3 fixes, the Sub-project 3 policy question that was resolved WITHOUT a code change (Partial Top-Down confirmed correct), and current git state.
