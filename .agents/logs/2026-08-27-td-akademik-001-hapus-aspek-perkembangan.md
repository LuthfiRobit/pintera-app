# Handoff Log: TD-AKADEMIK-001 (Hapus `TipeMataPelajaran::AspekPerkembangan`)

- **Tanggal**: 2026-08-27
- **Branch**: `akademik-v2`
- **Spec**: `.agents/specs/2026-08-27-td-akademik-001-hapus-aspek-perkembangan.md`
- **Plan**: `.agents/plans/2026-08-27-td-akademik-001-hapus-aspek-perkembangan.md`
- **Dieksekusi via**: `superpowers:subagent-driven-development` (fresh implementer subagent per task + task reviewer + final whole-plan review, di sesi ini langsung, tanpa worktree)
- **Status Akhir**: SELESAI & TERVERIFIKASI (Full Test Suite: 2240 passed, 4 skipped, 0 failed, 6163 assertions)

---

## 1. Apa yang Dikerjakan

Menghapus total `TipeMataPelajaran::AspekPerkembangan` — CRUD yang berfungsi & diuji tapi tidak terintegrasi ke defaulting `assessment_type` (bug laten) dan tidak pernah dipakai data nyata (gap seeder SD-only). `ElemenCp` menjadi satu-satunya jalur resmi penilaian PAUD non-formal-subject.

1. **Task 1 — Migration sempitkan ENUM DB** (`05fc0213`, implementer: Haiku):
   - `database/migrations/2026_08_27_100000_remove_aspek_perkembangan_from_mata_pelajaran_tipe.php` — guard `RuntimeException` sebelum `ALTER TABLE mata_pelajaran MODIFY COLUMN tipe ENUM('mapel') NOT NULL`.
   - `tests/Unit/Migrations/RemoveAspekPerkembanganFromMataPelajaranTipeTest.php` — 2 test (insert `aspek_perkembangan` ditolak, `mapel` tetap valid).
   - Review: Approved, 0 issues.

2. **Task 2 — Hapus dari Kode Aplikasi** (`a1fa7c93`, implementer: Sonnet, 1 commit tunggal krn interdependen):
   - `app/Enums/TipeMataPelajaran.php` — cuma 1 case (`Mapel`).
   - `app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php` — hapus `countAspek`, validasi `tipe` jadi `in:mapel`.
   - `resources/views/.../mata-pelajaran/_form.blade.php` — hapus opsi dropdown.
   - `resources/views/.../mata-pelajaran/index.blade.php` — hapus KPI card ke-3, grid `sm:grid-cols-3`→`sm:grid-cols-2`.
   - 3 file test disesuaikan (`AcademicEnumsTest`, `MataPelajaranCrudTest`, `MataPelajaranTest` — test kelompok-nullable diADAPTASI pakai `TipeMataPelajaran::Mapel`, bukan dihapus).
   - Review: Approved, 1 **Minor** ditemukan (teks subtitle "...dan aspek perkembangan..." tersisa di `index.blade.php`, di luar 7 file yang secara literal disebut task) — diperbaiki langsung oleh controller sesi ini (commit `8e72ff5c`), diverifikasi ulang `MataPelajaranCrudTest` tetap 6/6.

3. **Task 3 — Regresi Penuh**:
   - Grep akhir: 0 referensi liar di kode aplikasi (3 hit tersisa semuanya sah — migration histori asli, migration guard baru, test-nya sendiri).
   - Full test suite: **2240 passed, 4 skipped, 0 failed** (6163 assertions, 493.54s) — naik +2 dari baseline 2238 (Task 1 menambah 2 test baru).
   - `php artisan migrate` dijalankan di dev database nyata — guard lolos (0 baris `aspek_perkembangan` ditemukan, sesuai asumsi audit).

4. **Final Whole-Plan Review** (Sonnet, cakupan commit `bfcbdbd5..8e72ff5c`): Approved, 0 issues. Verifikasi tambahan: `ElemenCp` muncul di 77 tempat repo-wide, TIDAK SATU PUN tersentuh diff ini; urutan migration benar; commit fix minor (`8e72ff5c`) legitimate, tidak ada sisa teks basi lain yang terlewat.

---

## 2. Keputusan Penting yang Diambil

1. **Audit dikoreksi 2x sebelum eksekusi** (di luar sesi eksekusi ini, saat brainstorming): klaim awal "dead code total" salah — CRUD-nya berfungsi & diuji; tapi genuinely bug krn tidak terintegrasi ke `CreateKomponenPenilaianAction`; ketiadaan data nyata adalah gap seeder (SD-only), bukan bukti tidak penting.
2. **Hapus total sampai level ENUM database**, bukan cuma kode aplikasi — kolom `mata_pelajaran.tipe` adalah `ENUM` MySQL asli, bukan cuma PHP enum.
3. **Guard defense-in-depth di migration** — gagal keras (bukan diam-diam menghapus data) kalau asumsi "nol baris terpakai" ternyata salah di environment lain.
4. **Test kelompok-nullable diadaptasi, bukan dihapus** — konsepnya berlaku umum (bukan spesifik `aspek_perkembangan`), jadi cakupan testnya dipertahankan dgn fixture berbeda (`TipeMataPelajaran::Mapel`, "Muatan Lokal").

---

## 3. Hal yang Perlu Direview Manusia / Claude

1. **Git State**:
   - Branch: `akademik-v2`
   - Commit: `05fc0213` (Task 1), `a1fa7c93` (Task 2), `8e72ff5c` (fix minor)
   - Working tree: Clean.
   - Dev database: sudah dimigrasi (`php artisan migrate`, batch terbaru).

2. **Status Technical Debt Akademik**:
   - `TD-AKADEMIK-001`: **SELESAI TOTAL**.
   - `TD-AKADEMIK-002`: SELESAI TOTAL (sesi sebelumnya).
   - Seluruh roadmap "Fondasi Akademik Multi-Jenjang" (Sprint 1-5) + kedua technical debt turunannya kini tuntas.
