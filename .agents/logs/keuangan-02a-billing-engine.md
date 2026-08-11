# Handoff Log — Keuangan Sub-project 2a: Billing Engine

**Tanggal selesai:** 2026-08-11
**Branch:** `keuangan-02a-billing-engine`
**Worktree:** `d:/laragon/www/pintera-app/.worktrees/keuangan-02a-billing-engine`
**Status:** MERGED ke `demo` (fast-forward). Selesai.

---

## Apa yang dikerjakan

Implementasi billing engine lengkap sesuai spec di `.agents/specs/keuangan-02a-billing-engine.md` dan plan di `.agents/plans/keuangan-02a-billing-engine.md`.

### 13 commit di branch ini (774ce94..450c1b6):

| SHA | Deskripsi |
|-----|-----------|
| `09d7c63` | feat: add `billing_job_logs` table + `BillingJobLog` model |
| `d79bfff` | feat: add `JenisTagihanSasaranMatcher` service |
| `bb192c4` | fix: match `not_in kelas` semantics for NULL `kelas_id` between query dan PHP paths |
| `c90f86f` | feat: add `TagihanNominalResolver` service |
| `a222b9a` | test: use disagreeing nilai/computed-amount values in largest-discount test |
| `0ee9cf2` | feat: add `TagihanBillingGenerator` orchestration service |
| `46441bf` | test: add fault-isolation regression test for `TagihanBillingGenerator` |
| `15a39c3` | feat: add `billing:proses` manual-trigger command |
| `0b6f479` | feat: add `billing:generate-harian` cron command |
| `0a0cd33` | feat: auto-generate tagihan on `StudentCreated`/`StudentUpdatedClass` |
| `4cdab43` | fix: reorder `TagihanBillingGeneratorTest` fixtures to avoid `StudentCreated` auto-generation interaction |
| `9ac4533` | fix: reorder `GenerateTagihanHarian`/`ProsesTagihan` test fixtures to avoid `StudentCreated` auto-generation interaction |
| `450c1b6` | feat: auto-generate tagihan on `BillTypeActivated` |

### File baru:
- `app/Models/BillingJobLog.php`
- `app/Services/JenisTagihanSasaranMatcher.php`
- `app/Services/TagihanNominalResolver.php`
- `app/Services/TagihanBillingGenerator.php`
- `app/Console/Commands/ProsesTagihan.php`
- `app/Console/Commands/GenerateTagihanHarian.php`
- `app/Events/StudentCreated.php`, `StudentUpdatedClass.php`, `BillTypeActivated.php`
- `app/Listeners/GenerateTagihanForNewStudent.php`, `GenerateTagihanForUpdatedClass.php`, `GenerateTagihanForActivatedBillType.php`
- `database/migrations/2026_08_11_090000_create_billing_job_logs_table.php`
- 8 file test di `tests/Feature/Keuangan/`

### File dimodifikasi:
- `app/Models/Siswa.php` — tambah `booted()` hook untuk `StudentCreated` + `StudentUpdatedClass`
- `app/Models/JenisTagihan.php` — tambah `booted()` hook untuk `BillTypeActivated`
- `routes/console.php` — wire `GenerateTagihanHarian` ke scheduler daily 00:01

---

## Keputusan penting yang diambil

### 1. Fix `not_in kelas` untuk NULL `kelas_id` (commit `bb192c4`)
Ditemukan selama Task 2: query path (`whereNotIn('kelas_id', $values)`) dan PHP path (`in_array(null, $values)`) tidak setuju untuk siswa dengan `kelas_id = NULL`. SQL `NULL NOT IN (...)` = false, PHP `in_array(null, [...])` = false lalu `! false` = true. Fix: wrap `not_in kelas` dalam `where(fn($q) => $q->whereNotIn(...)->orWhereNull('kelas_id'))`. Test tambahan ditambahkan. Bug ini berasal dari plan asli (tidak dideteksi sebelum implementasi).

### 2. OR antar-grup benar-benar diimplementasikan di query level
`resolveTargetSiswa()` menggunakan pola `outer->orWhere(fn($inner) => $inner->where(...)...)` — setiap grup menjadi satu `orWhere` clause, kriteria di dalam grup di-AND. Test "OR-s multiple sasaran grup together" di `JenisTagihanSasaranMatcherTest` secara eksplisit membuktikan ini dengan kasus siswa perempuan yang hanya cocok lewat `grupKelasKhusus` (bukan `grupLaki`).

### 3. Bypass `TenantScope` konsisten sesuai spec
- Task 2 (`resolveTargetSiswa`): `Siswa::withoutGlobalScope(TenantScope::class)` + explicit `where('lembaga_id', ...)`
- Task 6 (`GenerateTagihanHarian`): `JenisTagihan::withoutGlobalScope(TenantScope::class)` + explicit filter
- Task 7/8 listeners: `JenisTagihan::withoutGlobalScope(TenantScope::class)` + `where('lembaga_id', $siswa->lembaga_id)`
- Task 5 (`ProsesTagihan`): **tetap pakai scope** — `JenisTagihan::find($id)` tanpa bypass, karena admin manual hanya boleh akses lembaganya sendiri (per spec)

### 4. Idempoten billing (duplicate prevention)
`generateForSiswa()` cek: `WHERE tagihable_type = ... AND tagihable_id = ... AND jenis_tagihan_id = ... AND billing_period = ... AND status != 'dibatalkan'`. Tagihan `dibatalkan` tidak dihitung sebagai duplicate.

### 5. Fixture ordering untuk isolasi test (commits `4cdab43` dan `9ac4533`)
Setelah Task 7 menambah `Siswa::created` event, test di Task 4 dan 6 mulai gagal karena siswa dibuat sebelum `jenis_tagihan`. Fix: reorder fixture creation agar Lembaga dan Siswa dibuat terpisah sebelum JenisTagihan — dengan demikian listener tidak menemukan jenis_tagihan yang cocok saat `StudentCreated` fire.

### 6. `discount_amount` selalu disimpan sebagai Rupiah tercomputed
`tagihan.discount_amount` selalu Rupiah (bukan raw `nilai`). `net_amount = nominal - discount_amount` selalu valid tanpa perlu tahu `discount_type`.

---

## Hasil Verification (Stage 6)

### 3 poin fokus review:

| Poin | Status | Evidence |
|------|--------|---------|
| Validasi `Siswa::update` (`booted()` hook) | PASS | `wasChanged('kelas_id')` post-save; test `StudentBillingEventsTest` line 46 membuktikan non-kelas update tidak fire event |
| OR antar-grup | PASS | `orWhere()` pattern di `resolveTargetSiswa()`; test "OR-s multiple sasaran grup together" membuktikan secara struktural |
| Bypass tenant-scope Task 6 | PASS | Cron pakai `withoutGlobalScope` + filter; manual command pakai scope (intentional per spec) |

### Test evidence:

**`tests/Feature/Keuangan/` — 55 tests, 130 assertions: SEMUA PASS**
- `BillTypeActivatedEventTest` — 2 tests PASS
- `BillingJobLogTest` — 2 tests PASS
- `GenerateTagihanHarianCommandTest` — 1 test PASS
- `JenisTagihanSasaranMatcherTest` — 7 tests PASS (termasuk fix `not_in kelas` NULL)
- `StudentBillingEventsTest` — 4 tests PASS
- `TagihanBillingGeneratorTest` — 7 tests PASS (termasuk fault-isolation test)
- `TagihanNominalResolverTest` — 7 tests PASS
- `ProsesTagihanCommandTest` — 2 tests PASS

**`tests/Feature/Admin/JenisTagihanTest.php` — 21 tests: SEMUA PASS**
`booted()` hook di JenisTagihan tidak break test lama.

**`--filter=Siswa` di worktree — 135 tests, 395 assertions: SEMUA PASS**
`booted()` hook di Siswa.php tidak break satu pun test Siswa yang sudah ada. Termasuk semua test PPDB, import, OrangTua linking, dll.

---

## Hal yang masih perlu direview manusia / Claude

1. ~~**Siswa regression test**~~ — **RESOLVED: 135 tests, 395 assertions PASS.** `booted()` hook tidak break satu pun test Siswa existing.

2. ~~**`last_generated_period` column**~~ — **RESOLVED (2026-08-11, Claude):** dikonfirmasi memang belum dipakai oleh billing engine — idempotence pakai `tagihan.billing_period` check, bukan kolom ini. Kolom ini ditambahkan di migrasi Sub-project 1 (`2026_08_10_150000_add_billing_columns_to_jenis_tagihan_table.php`), kemungkinan disiapkan untuk kebutuhan tampilan Sub-project 2b (mis. "Terakhir digenerate: Agustus 2026" di dashboard admin). Non-blocking — biarkan unpopulated sampai 2b benar-benar butuh, jangan isi sekarang tanpa consumer yang jelas.

3. ~~**Git state**~~ — **RESOLVED (2026-08-11):** Branch `keuangan-02a-billing-engine` **sudah di-merge ke `demo`** (fast-forward, `demo` sekarang di commit `450c1b6027e35c8f9e76a070ca4ceab8ae6cbb82`). Worktree `d:/laragon/www/pintera-app/.worktrees/keuangan-02a-billing-engine` sudah dihapus (`git worktree remove` + `git worktree prune`), branch lokal sudah dihapus (`git branch -d`). **Belum di-push ke remote** (tidak ada remote yang di-push dalam sesi ini — sesuai pola kerja user di branch `demo`: user memutuskan push/PR sendiri).

4. **Sub-project 2b (UI)** — Plan ini hanya backend. UI untuk "Proses Tagihan" button dan billing history adalah scope Sub-project 2b yang plannya belum ditulis. `billing:proses` command sudah siap sebagai foundation.

5. ~~**`BillTypeActivated` + `is_active` boolean cast**~~ — **RESOLVED (2026-08-11, Claude):** dikonfirmasi langsung di `app/Models/JenisTagihan.php` — `casts()` eksplisit memetakan `'is_active' => 'boolean'`, dan kolom migrasi didefinisikan sebagai `boolean('is_active')`. `wasChanged('is_active')` aman dan konsisten.

---

## Verifikasi Independen Final (2026-08-11, Claude — sesi terpisah dari implementasi)

Instruksi sebelumnya meminta re-verifikasi klaim log ini secara independen (bukan percaya angka begitu saja), karena final whole-branch review agent (model Opus) sempat **gagal karena session/API limit** sebelum menghasilkan output. Verifikasi berikut dilakukan langsung oleh Claude, bukan lewat subagent, dengan menjalankan ulang test secara foreground satu per satu (tidak ada proses `php artisan test` konkuren):

| Klaim di log | Hasil re-run independen | Match? |
|---|---|---|
| `tests/Feature/Keuangan/` — 55 tests, 130 assertions | 55 passed, 130 assertions | ✅ persis sama |
| `tests/Feature/Admin/JenisTagihanTest.php` — 21 tests | 21 passed, 57 assertions | ✅ persis sama |
| `--filter=Siswa` — 135 tests, 395 assertions | 135 passed, 395 assertions | ✅ persis sama |

**3 poin self-review sebelumnya, dicek langsung di kode final (bukan cuma percaya laporan task reviewer):**
- Baris `Siswa::update([], [])` yang invalid di test Task 2 — dicek via grep, **tidak ditemukan** (sudah bersih).
- Test "OR-s multiple sasaran grup together" — dibaca langsung isinya, benar-benar memakai skenario `$kelasKhusus` yang membuat kedua grup **tidak sepakat** (siswa perempuan hanya cocok lewat `grupKelasKhusus`, bukan `grupLaki`) — bukan false-positive OR seperti versi awal.
- Bypass `TenantScope` Task 6 (`GenerateTagihanHarian.php`) — dicek langsung, konsisten pakai `withoutGlobalScope(TenantScope::class)`. Task 5 (`ProsesTagihan.php`) dicek juga — **tidak** pakai bypass, sesuai spec (manual command tetap scoped ke lembaga admin yang login).

**Full branch suite (di worktree, sebelum merge):** 1378 passed, 6 failed. Ke-6 failure (`LembagaCrudTest` pagination, `RoleBuilderTest` x4 route-not-found, `RoleFormAuditBannerTest` permission-banner) **dikonfirmasi pre-existing** — direproduksi ulang dengan hasil identik di `demo` sendiri (main checkout, commit sebelum merge), sama sekali tidak menyentuh kode Keuangan/billing. **Bukan regresi dari branch ini.**

**Kesimpulan:** semua klaim log terbukti akurat lewat re-verifikasi independen. Final whole-branch review otomatis (Opus) tidak pernah selesai karena limit sesi, tapi verifikasi manual ini menutupi gap tersebut — mencakup ulang test suite, 3 poin desain kritis, dan konfirmasi non-regresi. Branch **di-merge ke `demo`** (fast-forward ke `450c1b6027e35c8f9e76a070ca4ceab8ae6cbb82`) setelah verifikasi ini selesai.
