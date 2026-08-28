# Handoff Log: Fix Privilege Escalation Lintas-Lembaga pada Approval Workflow Generik

**Tanggal**: 2026-08-28
**Branch**: `akademik-v2`
**Spec**: `.agents/specs/2026-08-28-workflow-fix-approval-lembaga-privilege-escalation.md`
**Plan**: `.agents/plans/2026-08-28-workflow-fix-approval-lembaga-privilege-escalation.md`

---

## Apa yang Dikerjakan

Tiga task berurutan untuk menutup celah privilege escalation lintas-lembaga pada approval workflow generik (Rapor, Izin/Cuti SDM, Pengadaan Sarpras — semua pakai engine yang sama).

### Task 1 — Fail-closed `ApproverResolverService::checkRoleApprover()`

**Commit**: `79fd78a5`
**File diubah**:
- `app/Domains/Workflow/Services/ApproverResolverService.php`
- `tests/Unit/Domains/Workflow/ApproverResolverServiceTest.php` (BARU, 5 test)

**Bug**: `checkRoleApprover()` fail-open — kondisi guard `$targetLembagaId && $user->lembaga_id && ...` dilewati ketika `$user->lembaga_id` null (aktor yayasan tanpa lembaga aktif), sehingga approval lintas-lembaga diloloskan.

**Fix**: Ganti kondisi guard menjadi `effectiveLembagaId` pattern. Ketika `$targetLembagaId !== null`, resolve lembaga efektif via `widestScopeLevel()`:
- Aktor yayasan → `session('active_lembaga_id')`
- Aktor lembaga biasa → `$user->lembaga_id`
Jika `effectiveLembagaId === null` atau tidak cocok, return false (fail-closed).

**Syarat fail-closed**: HANYA aktif ketika `$targetLembagaId !== null`. Skenario `$targetLembagaId === null` (workflow generik tanpa konteks tenant di `WorkflowEngineTest.php`) tetap return true — backward-compatible.

**Test regresi**: `WorkflowEngineTest.php` tetap PASS (1/1).

---

### Task 2 — Guard `scope_level` di `RoleController::update()`

**Commit**: `06c9fd83`
**File diubah**:
- `app/Http/Controllers/Admin/RoleController.php`
- `tests/Feature/Admin/RoleBuilderTest.php` (+2 test baru)

**Bug**: `RoleController::update()` mengizinkan mengubah `scope_level` role fungsional (`is_protected: false`) ke level apapun tanpa cek apakah role itu dipakai sebagai approver di `workflow_steps` dengan `scope_level` berbeda. Ini membuka jalur kedua untuk privilege escalation: ubah `kepala_sekolah (lembaga)` → `kepala_sekolah (yayasan)`, lalu buat user dengan role itu.

**Fix**: Tambahkan guard sebelum mutasi `$role->name`/`$role->scope_level`: jika `$data['scope_level'] !== $role->scope_level`, cek apakah ada `WorkflowStep` dengan `approver_type=Role`, `approver_value=$role->name`, dan `scope_level != $data['scope_level']`. Jika ada, return `errorResponse` dengan flash error pada field `scope_level`.

**Urutan assignment penting**: `$role->name = ...` dan `$role->scope_level = ...` dipindah ke BAWAH blok pengecekan (setelah kedua guard lulus). Ini berbeda dari baseline yang menassign `$role->name` di atas scope rank check.

**Test**: 33/33 PASS di `RoleBuilderTest.php`.

---

### Task 3 — Perbaiki Guard Lembaga di `ApprovePengajuanRaporAction`/`VerifyPengajuanRaporAction`

**Commit**: `965da69f`
**File diubah**:
- `app/Domains/Akademik/Actions/Rapor/ApprovePengajuanRaporAction.php`
- `app/Domains/Akademik/Actions/Rapor/VerifyPengajuanRaporAction.php`
- `tests/Feature/Akademik/RaporApprovalTenantScopeTest.php` (+2 test baru)

**Bug**: Guard lokal di kedua action menggunakan `$user->lembaga_id` mentah — aktor yayasan (lembaga_id null) selalu gagal guard ini meski punya `active_lembaga_id` sesi yang benar, sehingga waka kurikulum yayasan tidak pernah bisa verify/approve rapor.

**Fix**: Ganti guard `(int) $pengajuanRapor->lembaga_id !== (int) $user->lembaga_id` dengan `effectiveLembagaId` pattern yang konsisten dengan Task 1:
```php
$effectiveLembagaId = $user->widestScopeLevel() === 'yayasan'
    ? session('active_lembaga_id')
    : $user->lembaga_id;

if ($effectiveLembagaId === null || (int) $pengajuanRapor->lembaga_id !== (int) $effectiveLembagaId) {
    throw ValidationException::...
}
```

**Guard TIDAK dihapus** — hanya logikanya yang diperbaiki. Guard tetap ada sebagai defense-in-depth terhadap instance `PengajuanRapor` yang diteruskan langsung ke Action tanpa lewat `find()`/`TenantScope` (mis. dari command/job internal). Ini dibuktikan oleh test existing `RaporApprovalTenantScopeTest.php:44-53` yang tetap PASS.

**Catatan teknis tentang test Task 3**: Test baru menggunakan `tap(Role::firstOrCreate(...), fn($r) => $r->update(['scope_level' => 'yayasan']))` — bukan sekadar `firstOrCreate`. Ini karena `RolePermissionSeeder` (dipanggil sebelum test) sudah membuat `wakasek_kurikulum` dengan `scope_level='lembaga'`. `firstOrCreate` tidak mengupdate record yang sudah ada, sehingga tanpa `update()` eksplisit, `widestScopeLevel()` tetap mengembalikan `'lembaga'` dan guard akan tetap pakai `$user->lembaga_id` (null) — test tidak akan mensimulasikan skenario yang benar.

---

## Keputusan Penting yang Diambil

1. **Syarat fail-closed Task 1** — `$targetLembagaId !== null` sebagai syarat aktivasi fail-closed adalah keputusan kritis dari plan. Tanpa syarat ini, `WorkflowEngineTest.php` existing (yang tidak membuat data tenant) akan pecah. Ini dicatat di kickoff sebagai "Peringatan Kritis #4".

2. **Guard lokal Rapor DIPERTAHANKAN, bukan dihapus** — Plan secara eksplisit melarang penghapusan guard di `ApprovePengajuanRaporAction`/`VerifyPengajuanRaporAction`. Test `never resolves a PengajuanRapor belonging to another lembaga by id...` membuktikan guard ini diperlukan sebagai defense-in-depth layer kedua (di luar ApproverResolverService). Task 3 hanya memperbaiki logikanya.

3. **Urutan assignment di RoleController Task 2** — `$role->name` dan `$role->scope_level` dipindah ke bawah kedua guard check. Plan secara eksplisit menekankan ini ("PENTING — urutan pengecekan sebelum mutasi"). Jika `$role->name` di-assign sebelum workflow guard, `$role->name` (yang dipakai sebagai kunci query WorkflowStep) menjadi nilai baru, bukan nilai lama — guard akan gagal mendeteksi divergensi.

4. **Test Task 3 pakai `tap+update` bukan `firstOrCreate` saja** — Ini keputusan yang tidak ada dalam plan verbatim, tapi diperlukan karena `RolePermissionSeeder` sudah membuat role `wakasek_kurikulum` dengan `scope_level='lembaga'`. `firstOrCreate` dengan param kedua `['scope_level' => 'yayasan']` tidak mengupdate record yang sudah ada. Tanpa `update()` eksplisit, `widestScopeLevel()` salah dan test mensimulasikan skenario yang berbeda dari yang dimaksud.

5. **Fix Task 1 otomatis berlaku untuk domain Sdm/Pengadaan** — Sesuai catatan plan, `ApproverResolverService` adalah komponen generik yang dipakai semua domain. Fix di Task 1 sudah menutup celah di semua domain tanpa perlu menyentuh kode Sdm/Pengadaan/Izin-Cuti.

---

## Hal yang Masih Perlu Direview Manusia

1. **Celah serupa di domain lain** — Plan mencatat bahwa jika ditemukan celah serupa di `AjukanIzinCutiAction` atau action Pengadaan, itu harus dilaporkan sebagai temuan terpisah. Saat pengerjaan Task 3, hanya file Rapor yang disentuh sesuai scope. Perlu dicek manual apakah action lain (Sdm, Pengadaan) juga memiliki guard lokal yang pakai `$user->lembaga_id` mentah — kemungkinan besar ada, dan Task 1 sudah menutupnya di layer ApproverResolverService, tapi guard lokal di tiap action mungkin masih fail-closed untuk aktor yayasan.

2. **Update `scope_level` role di test Task 3 bisa trigger guard Task 2** — Test Task 3 menggunakan `tap+update` untuk mengubah `scope_level` `wakasek_kurikulum` ke `'yayasan'`. Dalam test environment dengan `RefreshDatabase`, ini tidak masalah. Tapi kalau ada test yang menggabungkan Task 2 dan Task 3 dalam satu DB state, update role itu akan diblokir oleh guard Task 2 (karena ada workflow step yang pakai `wakasek_kurikulum` dengan `scope_level='lembaga'`). Saat ini tidak ada test seperti itu, tapi perlu diperhatikan jika ada reorganisasi test di masa depan.

3. **Git state** — Branch `akademik-v2`, belum di-merge ke main. Commits:
   - `79fd78a5` — Task 1 (ApproverResolverService fail-closed)
   - `06c9fd83` — Task 2 (RoleController scope_level guard)
   - `965da69f` — Task 3 (Rapor action lembaga efektif)
   
   Branch belum di-push ke remote. Regresi gabungan 7 file: **80 passed (187 assertions)**. Siap untuk PR/merge setelah user review.

---

## Ringkasan Test Akhir

| File | Tests | Status |
|------|-------|--------|
| `ApproverResolverServiceTest.php` (baru) | 5 | ✅ PASS |
| `WorkflowEngineTest.php` (existing) | 1 | ✅ PASS |
| `RoleBuilderTest.php` | 33 | ✅ PASS |
| `RaporApprovalTenantScopeTest.php` | 4 | ✅ PASS |
| `RaporApprovalActionsTest.php` | (included in 80) | ✅ PASS |
| `RaporPdfDataBuilderTest.php` | (included in 80) | ✅ PASS |
| `RaporPersetujuanControllerTest.php` | (included in 80) | ✅ PASS |
| **Total regresi gabungan** | **80** | ✅ **187 assertions** |
