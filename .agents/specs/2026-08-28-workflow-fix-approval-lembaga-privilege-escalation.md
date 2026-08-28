# Fix: Privilege Escalation Lintas-Lembaga pada Approval Workflow Generik — Design Spec

**Tanggal**: 2026-08-28
**Branch**: `akademik-v2`
**Konteks**: Ditemukan saat mengaudit approval Rapor (modul Akademik), tapi bug-nya ada di komponen **shared Workflow engine** (`app/Domains/Workflow/`), dipakai oleh 3 workflow: `RAPOR_SEMESTER`, `IZIN_CUTI_SDM`, `PENGADAAN_SARPRAS`. Scope fix ini LEBIH BESAR dari modul Akademik — menyentuh domain Workflow generik dan RBAC (`RoleController`).

---

## 1. Latar Belakang & Masalah

### Bug 1 (Critical, akar masalah) — `ApproverResolverService::checkRoleApprover()` fail-open

```php
// app/Domains/Workflow/Services/ApproverResolverService.php:25-39
protected function checkRoleApprover(WorkflowStep $step, User $user, ApprovalRequest $request): bool
{
    if (! $user->hasRole($step->approver_value)) {
        return false;
    }

    if ($step->scope_level === 'lembaga') {
        $targetLembagaId = $request->approvable?->lembaga_id ?? $request->requester?->lembaga_id;
        if ($targetLembagaId && $user->lembaga_id && (int) $targetLembagaId !== (int) $user->lembaga_id) {
            return false;
        }
    }

    return true;
}
```

Kondisi `$targetLembagaId && $user->lembaga_id && ...` mensyaratkan KEDUA truthy sebelum pengecekan mismatch dijalankan. Kalau `$user->lembaga_id` NULL (aktor level yayasan — desain normal, dia pakai `session('active_lembaga_id')` bukan `lembaga_id` tetap), seluruh percabangan `scope_level === 'lembaga'` jadi tidak relevan → `return true` di baris terakhir, TANPA PEDULI lembaga mana yang dituju. **Fail-open**, seharusnya fail-closed.

### Bug 2 (enabler/pemicu) — `RoleController::update()` tidak menjaga konsistensi `scope_level` role vs `workflow_steps.scope_level`

`app/Http/Controllers/Admin/RoleController.php:138-176` mengizinkan `scope_level` role NON-PROTECTED (termasuk `kepala_sekolah`, `wakasek_kurikulum`, `admin_sdm` — semuanya `is_protected: false` di `RoleSeeder.php`) diubah bebas, HANYA dibatasi `scopeRank` terhadap scope aktor sendiri. Tidak ada pengecekan apakah role tersebut dipakai sebagai `approver_value` di `workflow_steps` dengan `scope_level` yang berbeda. `workflow_steps.approver_value` menyimpan NAMA role (string), independen dari `roles.scope_level` — begitu `roles.scope_level` diubah, TIDAK ADA sinkronisasi ke `workflow_steps.scope_level`, sehingga kombinasi role+user+workflow-step bisa jadi tidak konsisten (persis skenario yang memicu Bug 1).

### Bug 3 (fungsional, sudah ditemukan sejak awal, terkait tapi bukan security) — `ApprovePengajuanRaporAction`/`VerifyPengajuanRaporAction` menolak SEMUA aktor yayasan tanpa syarat

```php
// app/Domains/Akademik/Actions/Rapor/ApprovePengajuanRaporAction.php:28 (identik di VerifyPengajuanRaporAction.php:28)
if ((int) $pengajuanRapor->lembaga_id !== (int) $user->lembaga_id) {
    throw ValidationException::withMessages([...]);
}
```

**Guard ini SENGAJA ADA sebagai defense-in-depth** — dikonfirmasi oleh komentar di `tests/Feature/Akademik/RaporApprovalTenantScopeTest.php:48-51`: *"Kalau instance PengajuanRapor lembaga lain tetap diteruskan langsung ke Action (bukan lewat find(), misal lewat command/job internal), Action wajib menolak berdasarkan lembaga_id-nya sendiri - jangan hanya bergantung pada ApproverResolverService, yang fail-open..."* — jadi guard ini TIDAK BOLEH DIHAPUS, harus DIPERBAIKI supaya tetap menolak kasus lintas-lembaga TAPI juga meloloskan aktor yayasan yang sudah pilih lembaga aktif yang benar (keputusan desain yang sudah dikonfirmasi user sebelumnya).

## 2. Keputusan Desain

Konsep **"lembaga efektif"** dipakai konsisten di 3 lokasi, mirror pola `GuruController::resolveLembagaId()`/`PolaJamController::store()` yang sudah established di codebase ini:

```php
$effectiveLembagaId = $user->widestScopeLevel() === 'yayasan'
    ? session('active_lembaga_id')
    : $user->lembaga_id;
```

### Fix Bug 1 — `ApproverResolverService::checkRoleApprover()`, fail-closed

```php
protected function checkRoleApprover(WorkflowStep $step, User $user, ApprovalRequest $request): bool
{
    if (! $user->hasRole($step->approver_value)) {
        return false;
    }

    if ($step->scope_level === 'lembaga') {
        $targetLembagaId = $request->approvable?->lembaga_id ?? $request->requester?->lembaga_id;

        if ($targetLembagaId !== null) {
            $effectiveLembagaId = $user->widestScopeLevel() === 'yayasan'
                ? session('active_lembaga_id')
                : $user->lembaga_id;

            if ($effectiveLembagaId === null || (int) $targetLembagaId !== (int) $effectiveLembagaId) {
                return false;
            }
        }
    }

    return true;
}
```

**PENTING — fail-closed HANYA berlaku ketika `$targetLembagaId` tidak null.** Kalau `$targetLembagaId` NULL (baik `approvable` maupun `requester` sama-sama tidak punya `lembaga_id` — skenario workflow generik tanpa konteks tenant sama sekali, dibuktikan oleh `tests/Unit/Domains/Workflow/WorkflowEngineTest.php` yang memakai `User::factory()->create()` polos tanpa `lembaga_id` sebagai `approvable`/`requester`), TIDAK ADA yang perlu dilindungi — percabangan lembaga dilewati sepenuhnya, method lanjut ke `return true` seperti sebelumnya. **JANGAN membuat fail-closed berlaku tanpa syarat** (`$targetLembagaId === null → false`) — itu akan MEMATAHKAN `WorkflowEngineTest::test_can_initialize_and_progress_through_multi_step_workflow()` yang sengaja menguji step machine murni tanpa konteks lembaga sama sekali (baik `$userKepsek->lembaga_id` maupun `$requester->lembaga_id` default NULL dari factory, `scope_level` role `kepala_sekolah` juga tidak di-set eksplisit di test itu).

Perubahan dari existing: (a) fail-closed HANYA aktif kalau ada `$targetLembagaId` nyata untuk dilindungi (dulu tidak ada pengecekan null sama sekali di jalur manapun); (b) `$user->lembaga_id` di perbandingan mismatch diganti `$effectiveLembagaId` yang menghitung nilai efektif untuk aktor yayasan (dulu `$user->lembaga_id &&` sebagai syarat truthy adalah akar bug, sekarang digantikan pengecekan `$effectiveLembagaId === null` yang benar-benar fail-closed KETIKA ada target yang perlu dilindungi).

**Dampak untuk 3 workflow existing**: RAPOR_SEMESTER, IZIN_CUTI_SDM, PENGADAAN_SARPRAS — semua step approver-nya `approver_type=Role` + `scope_level='lembaga'` (kecuali `bendahara_yayasan` yang sudah `scope_level='yayasan'`, tidak tersentuh fix ini karena beda percabangan). Fix ini otomatis berlaku untuk ketiganya sekaligus.

### Fix Bug 2 — `RoleController::update()`, cegah scope_level role menyimpang dari workflow_steps

```php
public function update(Request $request, Role $role): RedirectResponse|JsonResponse
{
    $this->authorize('update', $role);

    $rules = [
        'permissions' => ['array'],
        'permissions.*' => ['integer', 'exists:permissions,id'],
    ];

    if (! $role->is_protected) {
        $rules['name'] = ['required', 'string', 'max:255', 'unique:roles,name,'.$role->id];
        $rules['scope_level'] = ['required', 'in:yayasan,lembaga,diri_sendiri,platform'];
    }

    $data = $request->validate($rules);

    if (! $role->is_protected) {
        $actingRank = $this->scopeRank($request->user()->widestScopeLevel());
        if ($this->scopeRank($data['scope_level']) > $actingRank) {
            return $this->errorResponse(
                $request,
                'scope_level',
                'Anda tidak dapat mengubah role ke scope lebih luas dari scope Anda sendiri.'
            );
        }

        if ($data['scope_level'] !== $role->scope_level) {
            $dipakaiWorkflowBerbeda = WorkflowStep::where('approver_type', ApproverType::Role)
                ->where('approver_value', $role->name)
                ->where('scope_level', '!=', $data['scope_level'])
                ->exists();

            if ($dipakaiWorkflowBerbeda) {
                return $this->errorResponse(
                    $request,
                    'scope_level',
                    'Role ini dipakai sebagai approver pada langkah workflow dengan scope_level berbeda. Selaraskan scope_level langkah workflow terkait terlebih dahulu.'
                );
            }
        }

        $role->name = $data['name'];
        $role->scope_level = $data['scope_level'];
    }

    $role->save();
    $role->syncPermissions(Permission::whereIn('id', $data['permissions'] ?? [])->get());

    if ($request->wantsJson()) {
        return response()->json(['message' => 'Role berhasil diperbarui.']);
    }

    return redirect()->route('admin.roles.index')->with('status', 'Role berhasil diperbarui.');
}
```

**Penting — urutan operasi**: cek dilakukan MENGGUNAKAN `$role->name`/`$role->scope_level` yang MASIH NILAI LAMA (belum di-assign `$data['name']`/`$data['scope_level']`), supaya perbandingan `approver_value` (nama role saat ini di DB) akurat. `$role->name`/`$role->scope_level` baru di-assign SETELAH lolos pengecekan.

Tambah import: `use App\Domains\Workflow\Enums\ApproverType;` dan `use App\Domains\Workflow\Models\WorkflowStep;` di `RoleController.php`.

### Fix Bug 3 — `ApprovePengajuanRaporAction`/`VerifyPengajuanRaporAction`, pakai "lembaga efektif"

```php
public function execute(PengajuanRapor $pengajuanRapor, User $user, ApprovalAction $action, ?string $catatan = null): PengajuanRapor
{
    $effectiveLembagaId = $user->widestScopeLevel() === 'yayasan'
        ? session('active_lembaga_id')
        : $user->lembaga_id;

    if ($effectiveLembagaId === null || (int) $pengajuanRapor->lembaga_id !== (int) $effectiveLembagaId) {
        throw ValidationException::withMessages([
            'approval' => 'Anda tidak berwenang menyetujui pengajuan rapor lembaga lain.', // "memverifikasi" di VerifyPengajuanRaporAction
        ]);
    }

    // ... sisanya TIDAK BERUBAH
}
```

Ini PERSIS desain yang sudah disetujui user di siklus sebelumnya (sebelum dijeda untuk audit `ApproverResolverService`) — sekarang dikonfirmasi TETAP BENAR karena test existing membuktikan guard lokal ini harus dipertahankan (defense-in-depth terhadap instance yang lolos dari `find()`/`TenantScope`), bukan dihapus dan digantikan sepenuhnya oleh fix resolver.

## 3. Non-Goals (eksplisit di luar scope)

- Tidak mengubah `ProcessApprovalAction`, `WorkflowStep`/`WorkflowDefinition`/`ApprovalRequest` model, `InitializeApprovalRequestAction`.
- Tidak mengubah `checkDirectRelationApprover()` (wali_kelas) — sudah dikonfirmasi aman oleh audit, tidak ada celah lembaga di situ.
- Tidak mengubah `AjukanIzinCutiAction`/`SubmitPengajuanAction` (Izin/Cuti SDM, Pengadaan Sarpras) — fix di resolver otomatis berlaku untuk keduanya tanpa perlu sentuh kode masing-masing domain.
- Tidak menambahkan migrasi data untuk merapikan role yang MUNGKIN sudah terlanjur diubah `scope_level`-nya di database production manapun sebelum fix ini — di luar scope kode; kalau ada data yang sudah terlanjur salah konfigurasi, itu keputusan operasional terpisah (audit manual data existing).
- Tidak mengubah `RolePolicy::update()` — fix Bug 2 cukup di level `RoleController::update()`, tidak perlu menyentuh policy authorization gate yang terpisah.
- Tidak ada perubahan skema/migration.

## 4. Testing (acceptance criteria wajib)

**4.1 — Regresi wajib**: seluruh test existing di `tests/Feature/Akademik/RaporApprovalActionsTest.php`, `tests/Feature/Akademik/RaporApprovalTenantScopeTest.php`, `tests/Feature/Akademik/RaporPdfDataBuilderTest.php`, `tests/Feature/Rapor/RaporPersetujuanControllerTest.php`, `tests/Feature/Admin/RoleBuilderTest.php` (test existing untuk `RoleController::update()`, sudah dikonfirmasi file-nya — BUKAN `RoleControllerTest.php`, yang tidak ada), dan `tests/Unit/Domains/Workflow/WorkflowEngineTest.php` HARUS tetap PASS tanpa modifikasi assertion apa pun.

**4.2 — Bug reproduction Bug 1 (resolver fail-open)**: buat `WorkflowStep` dengan `scope_level='lembaga'`, buat `ApprovalRequest` dengan `approvable` yang PUNYA `lembaga_id` nyata (bukan model tanpa lembaga_id seperti di `WorkflowEngineTest` existing — WAJIB pakai model yang benar-benar punya `lembaga_id`, misal `Kelas`/`PengajuanRapor`, supaya `$targetLembagaId` tidak null dan skenario ini benar-benar menguji jalur fail-closed), buat `User` dengan role yang cocok TAPI `lembaga_id = NULL` DAN tanpa `session('active_lembaga_id')` — `ApproverResolverService::canUserApprove()` HARUS `false` (SEBELUM fix: `true`, bug).

**4.3 — Bug reproduction Bug 1 varian yayasan dengan lembaga aktif BENAR**: user yayasan (`lembaga_id=NULL`) dengan `session('active_lembaga_id')` di-set SAMA dengan `$request->approvable->lembaga_id` → HARUS `true` (lolos, sesuai keputusan desain).

**4.4 — Bug reproduction Bug 1 varian yayasan dengan lembaga aktif SALAH**: user yayasan dengan `session('active_lembaga_id')` di-set BEDA dari `$request->approvable->lembaga_id` → HARUS `false`.

**4.5 — Bug reproduction Bug 2**: buat `WorkflowStep` dengan `approver_value='kepala_sekolah'` dan `scope_level='lembaga'` (mirror seed asli), buat `Role::firstOrCreate(['name'=>'kepala_sekolah'], ['scope_level'=>'lembaga','is_protected'=>false])`, lalu aktor dengan `roles.edit` mencoba `PUT admin.roles.update` mengubah `scope_level` role itu jadi `'yayasan'` → HARUS ditolak (`assertSessionHasErrors('scope_level')` atau setara), dan `Role::scope_level` di DB TIDAK berubah.

**4.6 — Kasus tidak berubah (regresi negatif Bug 2)**: mengubah `scope_level` role yang TIDAK dipakai di `workflow_steps` manapun (atau dipakai dengan `scope_level` yang SAMA dengan yang diminta) → tetap sukses seperti sebelumnya.

**4.7 — Bug reproduction Bug 3**: aktor yayasan dengan `session('active_lembaga_id')` benar mencoba `ApprovePengajuanRaporAction`/`VerifyPengajuanRaporAction` untuk `PengajuanRapor` milik lembaga aktif itu → HARUS SUKSES (SEBELUM fix: exception, karena `user->lembaga_id` NULL selalu mismatch).

**4.8 — Kasus tidak berubah (regresi negatif Bug 3)**: aktor lembaga-scoped biasa approve/verify pengajuan rapor miliknya sendiri → tetap sukses (test existing). Aktor yayasan TANPA lembaga aktif → tetap ditolak (pesan sama seperti existing, karena `$effectiveLembagaId === null` juga masuk kondisi gagal).

## 5. Ringkasan Perubahan File

```text
app/Domains/Workflow/Services/ApproverResolverService.php           [fail-closed checkRoleApprover(), pakai effectiveLembagaId]
app/Http/Controllers/Admin/RoleController.php                        [+cek konsistensi scope_level vs workflow_steps sebelum update]
app/Domains/Akademik/Actions/Rapor/ApprovePengajuanRaporAction.php   [ganti guard jadi effectiveLembagaId]
app/Domains/Akademik/Actions/Rapor/VerifyPengajuanRaporAction.php    [ganti guard jadi effectiveLembagaId]
tests/Unit/Domains/Workflow/ApproverResolverServiceTest.php (baru)   [+test reproduksi Bug 1]
tests/Feature/Admin/RoleBuilderTest.php                               [+test reproduksi Bug 2]
tests/Feature/Akademik/RaporApprovalTenantScopeTest.php               [+test reproduksi Bug 3]
```
