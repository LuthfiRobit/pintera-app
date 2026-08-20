# 📋 Handoff Log: RBAC v2 — Konsistensi Penamaan Permission

- **Spec:** [`.agents/specs/2026-08-20-0900-rbac-v2-permission-consistency.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-20-0900-rbac-v2-permission-consistency.md)
- **Plan:** [`.agents/plans/2026-08-20-0900-rbac-v2-permission-consistency.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-20-0900-rbac-v2-permission-consistency.md)
- **Branch:** `rbac-v2`
- **Status:** 🟢 SELESAI

## Ringkasan

`app/Console/Commands/SyncPermissions.php` terbukti rusak (regex cuma terima permission 2 segmen, tidak scan `canAny()`/FormRequest/Policy) — dibuktikan lewat eksekusi nyata sebelum perbaikan dimulai. Diperbaiki lewat `PermissionUsageScanner` baru (dipakai ulang oleh command DAN test regresi permanen `PermissionConsistencyTest`). `PermissionCatalog::MODULE_LABELS` dilengkapi 24 modul yang sebelumnya jatuh fallback `ucfirst()`.

## Per-Task Commit History

| Task | Deskripsi | Commit | Hasil Pengujian |
|---|---|---|---|
| **Task 1** | `PermissionUsageScanner` (sumber kebenaran scan) | `d917692` | 6/6 passed |
| **Task 2** | Refactor `SyncPermissions` pakai scanner | `71cf80f`, fix `5f302cd` | 5/5 passed |
| **Task 3** | `PermissionConsistencyTest` (jaring pengaman permanen) | `ac443c2` | 1/1 passed |
| **Task 4** | 24 label modul `PermissionCatalog` | `9cf03df`, fix `b468494` | 1/1 passed |
| **Task 5** | Verifikasi akhir + handoff log | (commit ini) | Full suite 1861/0 |

## Hasil Audit Nyata (dijalankan sebelum implementasi dimulai)

- **1 permission RUSAK ditemukan & diperbaiki** (commit `2e58e80`, sebelum plan ditulis): `pola-jam.kelola` dipakai di `_matrix-roster.blade.php` tapi tidak pernah terdaftar — diganti jadi `pola-jam.view` (cocok dengan permission yang menggerbang route tujuan tombolnya).
- **4 permission MATI** (terdaftar, tidak dipakai kode manapun — DIBIARKAN per keputusan spec, bukan bug): `audit-log.view`, `keuangan.akses`, `pengadaan.proposal.delete`, `workflow.config.manage`.
- **0 pasangan nama mencurigakan-mirip (typo-like)** ditemukan.

## Temuan Selama Eksekusi (Subagent-Driven Development)

1. **Task 2, Minor**: 2 import mati (`RaporController`, `File`) tertinggal di draft test dari brief — diperbaiki (`5f302cd`), retest bersih.
2. **Task 4, bug plan penulis sendiri**: test Task 4 mengasersi `label !== ucfirst($module)`, tapi label `'rapor' => 'Rapor'` yang ditulis di plan yang sama justru identik dengan `ucfirst('rapor')` — kontradiksi internal di plan. Implementer sempat menyiasati dengan label `'Laporan Penilaian'` (tidak konsisten dengan istilah "Rapor" yang dipakai di seluruh aplikasi). Diperbaiki langsung (`b468494`): label dikembalikan ke `'Rapor'`, test diganti membandingkan ke label persis yang diharapkan (lebih kuat, tidak rentan false-positive kebetulan).

Semua 4 task melewati review independen (spec-compliance + code-quality) dengan verdict **Approved** setelah perbaikan di atas — tidak ada temuan Critical/Important tersisa.

## Verifikasi Akhir

Full test suite: **1861 passed, 0 failed** (5721 assertions, ~595s).

## Item Terbuka

1. FASE 5.1 (Restrukturisasi Rute Modular) — dibahas terpisah, belum masuk sub-task manapun, tidak bergantung pada RBAC v2 ini.
2. UI multi-role assignment — dikonfirmasi di luar scope sesi ini, `Admin\UserController` masih 1 role per user.
3. 4 permission "mati" di atas — kalau memang tidak akan pernah dipakai, bisa dipertimbangkan dihapus dari seeder di sub-task terpisah (bukan bagian pekerjaan ini).
