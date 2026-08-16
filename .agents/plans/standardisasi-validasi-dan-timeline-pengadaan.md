# Standardisasi Validasi, Unified Audit Trail, dan Workflow Engine Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengimplementasikan validasi frontend & backend komprehensif di seluruh modul pengadaan, membangun agregator & timeline visual riwayat audit trail end-to-end (Draft s/d Inventarisasi Sarpras), dan menyempurnakan resolusi multi-tenant scope pada engine dynamic workflow.

**Architecture:** 
1. `ApproverResolverService` disempurnakan untuk memeriksa scope lembaga target dari `approvable->lembaga_id`.
2. Dedicated `FormRequest` classes dengan aturan ketat, sanitasi data, dan custom messages berbahasa Indonesia.
3. `PengajuanPengadaan::timelineEvents()` mengagregasikan event (Submit, Approval Logs, Pencairan Kas, Pengunggahan LPJ, Audit Yayasan, dan Konversi Sarpras) ke dalam collection event terstandar.
4. Blade templates mengintegrasikan `<x-input-error>`, feedback interaktif Alpine.js, dan komponen visual Timeline Audit Trail.

**Tech Stack:** Laravel 11, Blade, Tailwind CSS, Alpine.js, Spatie Permission, PHPUnit / Pest.

---

### Task 1: Penyempurnaan Dynamic Workflow Engine Resolver (Multi-Tenant Scope)

**Files:**
- Modify: `app/Domains/Workflow/Services/ApproverResolverService.php`
- Test: `tests/Feature/Pengadaan/SequentialApprovalTest.php`

**Interfaces:**
- Consumes: `WorkflowStep`, `User`, `ApprovalRequest`
- Produces: `ApproverResolverService::canUserApprove(WorkflowStep $step, User $user, ApprovalRequest $request): bool`

- [ ] **Step 1: Write failing test / update test expectation for approvable lembaga matching**
- [ ] **Step 2: Update `ApproverResolverService.php` to resolve target lembaga from approvable or requester**
- [ ] **Step 3: Run tests to verify all pass**
- [ ] **Step 4: Commit**

---

### Task 2: Backend FormRequest Hardening & Custom Validation Messages

**Files:**
- Modify: `app/Http/Requests/Pengadaan/StorePengajuanRequest.php`
- Modify: `app/Http/Requests/Pengadaan/StoreLpjRequest.php`
- Modify: `app/Http/Requests/Pengadaan/StoreDisbursementRequest.php`
- Modify: `app/Http/Requests/Pengadaan/ProcessApprovalRequest.php`
- Create: `tests/Feature/Pengadaan/PengadaanValidationTest.php`

**Interfaces:**
- Produces: Validated payload and custom localized error messages across all Pengadaan actions.

- [ ] **Step 1: Write failing validation test `PengadaanValidationTest.php`**
- [ ] **Step 2: Add validation rules & messages in `StorePengajuanRequest.php`**
- [ ] **Step 3: Add validation rules & messages in `StoreLpjRequest.php`**
- [ ] **Step 4: Add validation rules & messages in `StoreDisbursementRequest.php`**
- [ ] **Step 5: Add validation rules & messages in `ProcessApprovalRequest.php`**
- [ ] **Step 6: Run `php artisan test --filter=PengadaanValidationTest` and verify all pass**
- [ ] **Step 7: Commit**

---

### Task 3: Unified Audit Trail & Activity Timeline Engine

**Files:**
- Modify: `app/Domains/Pengadaan/Models/PengajuanPengadaan.php`
- Create: `tests/Unit/Pengadaan/TimelineEventsTest.php`

**Interfaces:**
- Produces: `PengajuanPengadaan::timelineEvents(): \Illuminate\Support\Collection`
  - Event structure: `['type', 'title', 'actor_name', 'actor_role', 'timestamp', 'badge_tone', 'status_label', 'notes', 'meta']`

- [ ] **Step 1: Write failing unit test `TimelineEventsTest.php`**
- [ ] **Step 2: Implement `timelineEvents()` method on `PengajuanPengadaan`**
- [ ] **Step 3: Run `php artisan test --filter=TimelineEventsTest` and verify it passes**
- [ ] **Step 4: Commit**

---

### Task 4: Frontend UI Validation & Unified Timeline Blade Integration

**Files:**
- Modify: `resources/views/portals/lembaga/pengadaan/proposal/create.blade.php`
- Modify: `resources/views/portals/lembaga/pengadaan/proposal/show.blade.php`
- Modify: `resources/views/portals/lembaga/pengadaan/lpj/create.blade.php`
- Modify: `resources/views/portals/yayasan/pengadaan/disbursement/index.blade.php`
- Modify: `resources/views/portals/yayasan/pengadaan/audit-lpj/show.blade.php`

**Interfaces:**
- Produces: Visual timeline card and `<x-input-error>` fields across all forms.

- [ ] **Step 1: Update `create.blade.php` with Alpine.js item validation & `<x-input-error>` on all fields**
- [ ] **Step 2: Update `show.blade.php` to render unified Timeline component from `$proposal->timelineEvents()`**
- [ ] **Step 3: Update `lpj/create.blade.php` with `<x-input-error>` and receipt file validation**
- [ ] **Step 4: Update `disbursement/index.blade.php` with modal validation & `<x-input-error>`**
- [ ] **Step 5: Update `audit-lpj/show.blade.php` with review notes validation & timeline**
- [ ] **Step 6: Commit**

---

### Task 5: End-to-End Verification & Regression Testing

**Files:**
- Verify: Full test suite

- [ ] **Step 1: Run all feature and unit tests for Pengadaan, Sarpras, and Workflow**
- [ ] **Step 2: Verify zero lint/type errors**
- [ ] **Step 3: Prepare handoff summary log in `.agents/logs/standardisasi-validasi-dan-timeline-pengadaan.md`**
