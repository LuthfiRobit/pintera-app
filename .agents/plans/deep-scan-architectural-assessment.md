# Plan: Deep Scan & Architectural Assessment (Pintera App)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menghasilkan laporan audit arsitektural dan pemetaan strategi koeksistensi domain, gap keamanan multi-tenant, serta kesiapan Spatie Permissions untuk Pintera App.

**Architecture:** Read-only static analysis pada codebase Laravel 12 multi-scope, verifikasi PSR-4 autoloading, identifikasi model tanpa tenant scope, evaluasi skema RBAC Spatie, dan pembuatan laporan terstruktur di `docs/`.

**Tech Stack:** Laravel 12, PHP 8.2+, Spatie Laravel-Permission 8.3, Tailwind CSS, Alpine.js.

## Global Constraints
- Tidak melakukan perubahan kode atau pemindahan file aplikasi.
- Seluruh temuan harus berbasis bukti baris kode riil pada repository.

---

### Task 1: Codebase Scanning & Autoloading Feasibility Check
- [x] Pindai `composer.json` untuk verifikasi mapping PSR-4 `"App\\": "app/"`.
- [x] Analisis struktur `app/`, `routes/`, dan `resources/` untuk memverifikasi kompatibilitas `app/Domains/`.
- [x] Konfirmasi bahwa penambahan domain baru tidak menyebabkan konflik namespace atau merusak alur route legacy.

### Task 2: Multi-Tenant Security & Query Gap Analysis
- [x] Pindai seluruh model Eloquent di `app/Models/` untuk memeriksa penggunaan trait `BelongsToTenant`.
- [x] Identifikasi model tanpa tenant isolation (`Tagihan`, `Pembayaran`, `Wallet`, `ManualPaymentRequest`, sub-model PPDB & BK).
- [x] Temukan potensi bug fatal runtime pada `Admin\TagihanController` dan `Admin\PembayaranController` terkait tagihan polymorphic non-PPDB.
- [x] Analisis implikasi user scope Yayasan saat `active_lembaga_id` kosong dan ketiadaan `yayasan_id` pada tabel `users`.

### Task 3: Spatie Permissions Multi-Team Setup Analysis
- [x] Analisis `config/permission.php` (`'teams' => false`).
- [x] Pindai migrasi permission tables dan verifikasi ketiadaan kolom `team_id`.
- [x] Evaluasi implikasi kustom `scope_level enum` pada tabel `roles` dan keterbatasan multi-role per lembaga.

### Task 4: Matriks Prioritas & Arsitektur Bridge
- [x] Susun tabel matriks prioritas P0 (Wajib Segera), P1 (Prioritas Tinggi), dan P2 (Dapat Ditunda/Paralel).
- [x] Rancang struktur folder bridge `app/Domains/Shared/` (`TenantContext`, `DomainServiceProvider`, `SafeBelongsToTenant`).
- [x] Simpan dokumen audit lengkap di `docs/deep_scan_architectural_assessment.md`.
