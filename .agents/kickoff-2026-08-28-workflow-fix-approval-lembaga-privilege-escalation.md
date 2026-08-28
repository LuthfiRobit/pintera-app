# Kickoff: Fix Privilege Escalation Lintas-Lembaga pada Approval Workflow Generik

**Untuk**: Antigravity (execution agent)
**Branch**: `akademik-v2` (lanjutkan di branch ini, JANGAN buat branch baru)
**Spec**: `.agents/specs/2026-08-28-workflow-fix-approval-lembaga-privilege-escalation.md`
**Plan**: `.agents/plans/2026-08-28-workflow-fix-approval-lembaga-privilege-escalation.md`

---

## Peringatan Kritis — Baca Dulu Sebelum Mulai

1. **Baca `.agents/plans/2026-08-28-workflow-fix-approval-lembaga-privilege-escalation.md` SELURUHNYA sebelum menulis kode apa pun.** Plan ini punya 3 task yang HARUS dikerjakan BERURUTAN (bukan paralel/acak) — Task 1 dulu, lalu Task 2, lalu Task 3. Task 3 Step 6 menjalankan regresi gabungan yang mengasumsikan Task 1 dan Task 2 sudah commit.
2. **Scope fix ini LINTAS-MODUL**: menyentuh `app/Domains/Workflow/` (engine generik dipakai Rapor+Izin/Cuti SDM+Pengadaan Sarpras), `app/Http/Controllers/Admin/RoleController.php` (RBAC), dan `app/Domains/Akademik/Actions/Rapor/` (spesifik Rapor). JANGAN memperluas scope ke domain Sdm/Pengadaan meski secara tema terkait — fix di Task 1 sudah otomatis berlaku untuk domain itu tanpa perlu sentuh kodenya.
3. **JANGAN HAPUS guard lokal di `ApprovePengajuanRaporAction`/`VerifyPengajuanRaporAction`** (Task 3) — plan menekankan ini eksplisit karena ada test existing (`RaporApprovalTenantScopeTest.php:44-53`) yang MEMBUKTIKAN guard itu perlu sebagai defense-in-depth. Task 3 MEMPERBAIKI logikanya (pakai "lembaga efektif"), bukan menghapusnya.
4. **Fail-closed di Task 1 PUNYA SYARAT** — HANYA aktif kalau ada `$targetLembagaId` nyata (bukan null). Baca catatan "PENTING" di Task 1 Step 1 dengan seksama — kalau membuat fail-closed berlaku tanpa syarat, `WorkflowEngineTest.php` existing akan pecah.
5. **Verifikasi baseline sebelum edit** — Step 1 di setiap task meminta kamu membaca ulang file sumber dan membandingkan dengan baseline yang tertulis di plan. Kalau berbeda, STOP dan laporkan.

## Konteks Masalah (ringkas)

Ditemukan saat mengaudit approval Rapor (modul Akademik), tapi bug-nya ada di komponen shared: `ApproverResolverService::checkRoleApprover()` (`app/Domains/Workflow/Services/ApproverResolverService.php:33`) fail-open — kalau `$user->lembaga_id` NULL (aktor level yayasan), seluruh pengecekan lembaga dilewati dan approval lintas-lembaga MANAPUN diloloskan tanpa syarat pilih lembaga aktif. Ini reachable lewat 2 langkah admin biasa (bukan bug tambahan): ubah `scope_level` role fungsional (`kepala_sekolah` dkk, semuanya `is_protected: false`) dari `lembaga` ke `yayasan` lewat `RoleController::update()` (tidak ada guard), lalu buat user baru dengan role itu — sistem tidak lagi mewajibkan `lembaga_id` karena baca `scope_level` dari record Role di DB.

3 fix digabung jadi 1 siklus:
1. **Akar masalah** — `ApproverResolverService` fail-closed.
2. **Enabler/pemicu** — `RoleController::update()` cegah `scope_level` menyimpang dari `workflow_steps` yang memakai role itu.
3. **Efek samping yang sudah ditemukan lebih dulu** — `ApprovePengajuanRaporAction`/`VerifyPengajuanRaporAction` sebelumnya SELALU menolak aktor yayasan (arah sebaliknya dari bug utama) — diperbaiki bersamaan pakai konsep yang sama ("lembaga efektif").

## Urutan Eksekusi

Task 1 → Task 2 → Task 3, berurutan, masing-masing: baca baseline → tulis test gagal → jalankan & pastikan gagal → implementasi fix → jalankan & pastikan semua lolos → (Task 1: regresi WorkflowEngineTest; Task 3: regresi gabungan + pint) → commit.

## Titik Verifikasi Wajib

**Task 1:**
- Setelah Step 3: 2 test reproduksi (`test_denies_yayasan_scoped_user_without_active_lembaga...`, `test_denies_yayasan_scoped_user_with_wrong_active_lembaga`) HARUS gagal. Kalau sudah PASS, STOP.
- Setelah Step 5: `php artisan test tests/Unit/Domains/Workflow/ApproverResolverServiceTest.php --compact` HARUS hijau (5 test).
- **Step 6 KRITIS**: `php artisan test tests/Unit/Domains/Workflow/WorkflowEngineTest.php --compact` HARUS TETAP HIJAU. Ini bukti fail-closed tidak mematahkan skenario tanpa konteks lembaga. Kalau test ini pecah, fix Task 1 SALAH — kembali ke Step 4, cek lagi syarat `$targetLembagaId !== null`.

**Task 2:**
- Setelah Step 3: test reproduksi HARUS gagal (`assertSessionHasErrors` gagal karena request sukses & `scope_level` di DB sudah berubah).
- Setelah Step 5: `php artisan test tests/Feature/Admin/RoleBuilderTest.php --compact` HARUS hijau semua (baseline + 2 test baru).

**Task 3:**
- Setelah Step 3: test reproduksi HARUS gagal (exception dilempar padahal seharusnya sukses).
- Setelah Step 5: `php artisan test tests/Feature/Akademik/RaporApprovalTenantScopeTest.php --compact` HARUS hijau (baseline 2 + 2 baru).
- **Step 6 — regresi gabungan lintas-modul**, jalankan SEMUA file ini sekaligus:
  ```
  php artisan test tests/Feature/Akademik/RaporApprovalActionsTest.php tests/Feature/Akademik/RaporApprovalTenantScopeTest.php tests/Feature/Akademik/RaporPdfDataBuilderTest.php tests/Feature/Rapor/RaporPersetujuanControllerTest.php tests/Unit/Domains/Workflow/WorkflowEngineTest.php tests/Unit/Domains/Workflow/ApproverResolverServiceTest.php tests/Feature/Admin/RoleBuilderTest.php --compact
  ```
  HARUS hijau semua — ini pembuktian akhir bahwa Task 1-3 bekerja konsisten bersama.

**TIDAK PERLU full suite project** (bukan diminta eksplisit sebagai konstraint, tapi scope fix ini lintas-modul jadi regresi gabungan Step 6 Task 3 SUDAH cukup luas cakupannya — jangan jalankan full suite kecuali diminta terpisah).

Jalankan `vendor/bin/pint --dirty --format agent` sebelum commit Task 3 (Step 7).

## Pelajaran Penting

- **Bug ini bukan di modul Akademik** — akar masalahnya di komponen Workflow generik yang dipakai 3 domain sekaligus (Rapor, Izin/Cuti SDM, Pengadaan Sarpras). Fix di 1 tempat (`ApproverResolverService`) otomatis menutup celah yang sama di ketiga domain itu — JANGAN duplikasi fix ke masing-masing domain.
- **Ada spec lama yang SUDAH DITANDAI SUPERSEDED**: `.agents/specs/2026-08-28-akademik-fix-approval-rapor-yayasan-lembaga-aktif.md` — file itu masih ada di repo tapi punya catatan eksplisit "JANGAN buat plan/kickoff dari file ini". Abaikan file itu, pakai spec/plan yang disebut di kickoff ini.
- **Fail-open vs fail-closed pada kondisi `null`**: pelajaran umum dari bug ini — kalau sebuah pengecekan otorisasi memakai `&&` untuk menggabungkan 2 kondisi truthy sebelum menolak (`if ($a && $b && $a !== $b) return false;`), maka `null` di salah satu sisi membuat pengecekan itu TIDAK PERNAH jalan — ini pola anti-pattern yang harus selalu dicurigai kalau menemukan kode serupa di tempat lain.

## Kalau Menemukan Sesuatu Tidak Sesuai Plan

- Kalau baseline kode di repo berbeda dari yang tertulis di plan Step 1 (task manapun), STOP dan laporkan detail perbedaannya di handoff log.
- Kalau menemukan celah serupa di domain Sdm/Izin-Cuti atau Pengadaan/Sarpras saat mengerjakan plan ini, JANGAN memperbaikinya sekalian — laporkan sebagai temuan terpisah di handoff log untuk dibuatkan siklus fix sendiri kalau memang perlu (meski secara teori celah utamanya sudah tertutup oleh Task 1).
- Kalau ada test lain (di luar yang disebut di plan) yang tiba-tiba FAIL setelah fix diterapkan, JANGAN diam-diam mengubah assertion test itu. Laporkan sebagai temuan di handoff log.

## Definisi Selesai

- [ ] `ApproverResolverService.php::checkRoleApprover()` diubah sesuai plan Task 1 Step 4.
- [ ] `tests/Unit/Domains/Workflow/ApproverResolverServiceTest.php` dibuat dengan 5 test, semua PASS.
- [ ] `tests/Unit/Domains/Workflow/WorkflowEngineTest.php` (existing) tetap PASS tanpa modifikasi.
- [ ] `RoleController.php::update()` diubah sesuai plan Task 2 Step 4, + 2 import baru.
- [ ] `tests/Feature/Admin/RoleBuilderTest.php` mendapat 2 test baru, semua PASS termasuk baseline.
- [ ] `ApprovePengajuanRaporAction.php` dan `VerifyPengajuanRaporAction.php` diubah sesuai plan Task 3 Step 4.
- [ ] `tests/Feature/Akademik/RaporApprovalTenantScopeTest.php` mendapat 2 test baru, semua PASS termasuk baseline.
- [ ] Regresi gabungan 7 file test (Task 3 Step 6) hijau semua.
- [ ] `vendor/bin/pint --dirty --format agent` sudah dijalankan.
- [ ] 3 commit dibuat sesuai pesan di plan Task 1 Step 7, Task 2 Step 6, Task 3 Step 8.
- [ ] Handoff log ditulis ke `.agents/logs/2026-08-28-workflow-fix-approval-lembaga-privilege-escalation.md` mengikuti format handoff log sebelumnya (lihat `.agents/logs/2026-08-28-akademik-fix-polajam-null-lembaga-dan-catatan-wali-semester.md` sebagai contoh format): ringkasan apa yang dikerjakan (ketiga task), keputusan penting yang diambil (terutama soal syarat fail-closed dan kenapa guard lokal Rapor dipertahankan bukan dihapus), hal yang perlu direview manusia.
