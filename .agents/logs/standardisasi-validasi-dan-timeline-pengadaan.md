# Handoff Log: Standardisasi Validasi, Unified Audit Trail, dan Workflow Engine

- **Tanggal / Waktu:** 16 Agustus 2026
- **Spec:** [`.agents/specs/standardisasi-validasi-dan-timeline-pengadaan.md`](file:///d:/laragon/www/pintera-app/.agents/specs/standardisasi-validasi-dan-timeline-pengadaan.md)
- **Plan:** [`.agents/plans/standardisasi-validasi-dan-timeline-pengadaan.md`](file:///d:/laragon/www/pintera-app/.agents/plans/standardisasi-validasi-dan-timeline-pengadaan.md)
- **Git Branch:** `akademik-v2`

---

## 1. Apa yang Dikerjakan

1. **Penyempurnaan Multi-Tenant Scope Engine Workflow (`ApproverResolverService`):**
   - Mengubah `checkRoleApprover()` pada `ApproverResolverService` agar memeriksa target `lembaga_id` secara aman dari `$request->approvable?->lembaga_id ?? $request->requester?->lembaga_id`.
   - Menghilangkan anomali verifikasi pada usulan pengadaan lintas unit/lembaga.

2. **Hardening Validasi Backend & FormRequest dengan Custom Pesan Bahasa Indonesia:**
   - [StorePengajuanRequest.php](file:///d:/laragon/www/pintera-app/app/Http/Requests/Pengadaan/StorePengajuanRequest.php): Validasi ketat rincian item, batas Qty minimal 1 unit, harga estimasi non-negatif, enum tipe pencatatan (`unit`/`batch`), batas upload foto referensi (maksimal 5MB).
   - [StoreLpjRequest.php](file:///d:/laragon/www/pintera-app/app/Http/Requests/Pengadaan/StoreLpjRequest.php): Validasi rincian realisasi belanja riil, batas format nota/struk kas (JPG, PNG, PDF maks 5MB), dan bukti setoran pengembalian sisa kas jika surplus.
   - [StoreDisbursementRequest.php](file:///d:/laragon/www/pintera-app/app/Http/Requests/Pengadaan/StoreDisbursementRequest.php): Validasi minimal nominal pencairan dana kas Rp 1, validitas tanggal pencairan, dan bukti transfer.
   - [ProcessApprovalRequest.php](file:///d:/laragon/www/pintera-app/app/Http/Requests/Pengadaan/ProcessApprovalRequest.php): Validasi enum `ApprovalAction` dan catatan putusan review.
   - Dibuat feature test `tests/Feature/Pengadaan/PengadaanValidationTest.php` untuk memvalidasi penolakan payload cacat.

3. **Unified Audit Trail & Activity Timeline Engine:**
   - Dibuat method `PengajuanPengadaan::timelineEvents()` yang mengagregasikan seluruh fase siklus hidup proposal ke dalam koleksi kronologis terstandar:
     1. Usulan Pengadaan Dibuat & Diajukan (Pengaju Lembaga).
     2. Persetujuan Setiap Step Workflow (Kepsek, Yayasan, beserta catatan & waktu).
     3. Pencairan Kas Kasir Yayasan (Nominal, metode, catatan transfer).
     4. Penyerahan LPJ Belanja (Total realisasi riil, selisih sisa kas).
     5. Audit & Verifikasi LPJ oleh Yayasan (Status audit, catatan verifikasi).
     6. Auto-Register & Konversi Master Aset Sarpras (Kode register & KIR).
   - Dibuat unit test `tests/Unit/Pengadaan/TimelineEventsTest.php`.

4. **Frontend UI Validation & Timeline Blade Component Integration:**
   - Memasang komponen feedback validasi `<x-input-error>` di seluruh form (`create.blade.php`, `lpj/create.blade.php`, `disbursement/index.blade.php`, `audit-lpj/show.blade.php`).
   - Merender **Komponen Timeline Visual Riwayat Lengkap** di `resources/views/portals/lembaga/pengadaan/proposal/show.blade.php` dengan badge warna dinamis, icon SVG, aktor, catatan, dan timestamp.
   - Dibuat feature test `tests/Feature/Pengadaan/UnifiedTimelineViewTest.php`.

---

## 2. Keputusan Penting yang Diambil

1. **Agregasi Timeline Virtual vs Tabel Riwayat Terpisah:**
   - Menggunakan pendekatan **Unified Lifecycle Presenter** di model Eloquent (`timelineEvents()`) daripada membuat tabel log baru di database. Pendekatan ini 100% konsisten terhadap data riil (source of truth), tanpa risiko out-of-sync, dan tanpa memerlukan migrasi database destruktif.
2. **Graceful Fallback Scope Resolver:**
   - `ApproverResolverService` memprioritaskan `approvable->lembaga_id` terlebih dahulu sebelum memeriksa `requester->lembaga_id` agar tetap mendukung model approvable lain di masa mendatang.

---

## 3. Hal yang Perlu Direview / Catatan Lanjutan

- **Hasil Pengujian Otomatis:**
  - `php artisan test tests/Feature/Pengadaan tests/Unit/Pengadaan tests/Feature/Sarpras` $\rightarrow$ **11 tests passed (58 assertions), 0 failures, 0 regressions.**
- **Git State:**
  - Branch: `akademik-v2`
  - Semua commit bersih dan terdokumentasi rapi.
