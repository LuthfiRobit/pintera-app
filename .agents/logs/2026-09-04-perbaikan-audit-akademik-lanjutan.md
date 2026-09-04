# Handoff Log: Perbaikan Audit Akademik Lanjutan

**Tanggal**: 2026-09-04  
**Branch**: `akademik-v2` (tetap di branch ini sesuai instruksi eksplisit user)  
**Base Commit**: `dc10a17d`  
**Spec**: [`.agents/specs/2026-09-04-perbaikan-audit-akademik-lanjutan.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-09-04-perbaikan-audit-akademik-lanjutan.md)  
**Plan**: [`.agents/plans/2026-09-04-perbaikan-audit-akademik-lanjutan.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-09-04-perbaikan-audit-akademik-lanjutan.md)  

---

## 1. Apa yang Dikerjakan

Paket ini menutup 1 kerentanan IDOR Critical (dua pintu berbeda) dan 3 bug Important yang ditemukan pada audit menyeluruh 2 putaran modul Akademik. Terbagi dalam 7 task berurutan dengan metodologi TDD:

1. **Task 1 — `ResolveLembagaScopeTrait` (`bfacf4ce`)**:
   - Dibuat trait [`app/Domains/Akademik/Support/ResolveLembagaScopeTrait.php`](file:///d:/laragon/www/pintera-app/app/Domains/Akademik/Support/ResolveLembagaScopeTrait.php) untuk mengisolasi resolusi `lembaga_id` baru saat create ("derive, jangan validate").
   - Actor non-platform tidak pernah mengambil `lembaga_id` dari request body/parameter.
   - Actor yayasan selalu me-resolve dari `session('active_lembaga_id')` dengan re-verifikasi kepemilikan yayasan di titik pakai (`Lembaga::where('id', $id)->where('yayasan_id', $actor->yayasan_id)->exists()`).
   - Global mapping (`lembaga_id = null`) hanya boleh dibuat oleh platform admin.
   - Test unit: [`tests/Unit/Support/ResolveLembagaScopeTraitTest.php`](file:///d:/laragon/www/pintera-app/tests/Unit/Support/ResolveLembagaScopeTraitTest.php) (5 tests passing).

2. **Task 2 — IDOR KurikulumAssignment (`9126bb03`)**:
   - Menutup 2 pintu IDOR pada `KurikulumAssignment`:
     - **Pintu #1 (Nilai yang ditulis saat Create)**: [`AssignKurikulumAction.php`](file:///d:/laragon/www/pintera-app/app/Domains/Akademik/Actions/KurikulumAssignment/AssignKurikulumAction.php) dan [`KurikulumAssignmentController::store()`](file:///d:/laragon/www/pintera-app/app/Http/Controllers/Admin/KurikulumAssignmentController.php) menggunakan `ResolveLembagaScopeTrait`. Form `create.blade.php` hanya menampilkan dropdown lembaga untuk platform admin; untuk non-platform hanya info teks read-only.
     - **Pintu #2 (Akses ke baris existing pada Edit/Update/Destroy)**: Ditambahkan `authorizeExistingAssignmentScope($actor, $existingLembagaId)` pada `edit()`, `update()`, dan `destroy()`. Mencegah yayasan A memodifikasi assignment milik yayasan B atau assignment global (`null`).
   - Query `index()`: Yayasan hanya melihat assignment global + lembaga di bawah yayasannya sendiri.
   - 3 test lama yang meng-encode perilaku yayasan=platform ditulis ulang dan 1 penyesuaian setup di [`KurikulumAssignmentDestroyGuardTest.php`](file:///d:/laragon/www/pintera-app/tests/Feature/Admin/KurikulumAssignmentDestroyGuardTest.php).
   - Test: [`tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`](file:///d:/laragon/www/pintera-app/tests/Feature/Akademik/KurikulumAssignmentControllerTest.php) (17 tests passing).

3. **Task 3 — IDOR FaseDefaultMapping (`06904d89`)**:
   - Menutup 2 pintu IDOR pada `FaseDefaultMapping` (cerminan Task 2 tanpa kolom `tahun_ajaran_id`):
     - **Pintu #1**: Dibuat [`SetFaseDefaultMappingAction.php`](file:///d:/laragon/www/pintera-app/app/Domains/Akademik/Actions/FaseMapping/SetFaseDefaultMappingAction.php) dengan `ResolveLembagaScopeTrait`, dan diselaraskan pada [`FaseDefaultMappingController::store()`](file:///d:/laragon/www/pintera-app/app/Http/Controllers/Admin/FaseDefaultMappingController.php).
     - **Pintu #2**: Ditambahkan `authorizeExistingMappingScope($actor, $existingLembagaId)` pada `edit()`, `update()`, dan `destroy()`.
     - Form `create.blade.php` dan `edit.blade.php` disesuaikan untuk menyembunyikan dropdown lembaga bagi non-platform.
     - Query `index()` disesuaikan untuk yayasan.
   - 3 test lama ditulis ulang untuk menguji aturan baru.
   - Test: [`tests/Feature/Akademik/FaseDefaultMappingControllerTest.php`](file:///d:/laragon/www/pintera-app/tests/Feature/Akademik/FaseDefaultMappingControllerTest.php) (13 tests passing).

4. **Task 4 — RPP Verify untuk Actor Yayasan (`68557130`)**:
   - Memperbaiki [`app/Http/Controllers/Admin/RppController.php`](file:///d:/laragon/www/pintera-app/app/Http/Controllers/Admin/RppController.php) method `verify()` yang sebelumnya selalu melempar 422 "tidak berwenang" karena `$actor->lembaga_id` selalu null untuk user yayasan.
   - Menggunakan pola `effectiveLembagaId`: mengambil `session('active_lembaga_id')` untuk yayasan, disertai `abort_if($effectiveLembagaId === null, 422)`.
   - Test: [`tests/Feature/Admin/RppVerifyTest.php`](file:///d:/laragon/www/pintera-app/tests/Feature/Admin/RppVerifyTest.php) (2 tests passing, 51 test RPP lolos).

5. **Task 5 — Deteksi Bentrok Jadwal Berbasis Waktu Wall-Clock (`8ea84f98`)**:
   - Memperbaiki deteksi bentrok jadwal guru dan ruangan dari pengecekan `jam_pelajaran_id` mentah menjadi overlap waktu wall-clock (`hari = ? AND jam_mulai < ? AND jam_selesai > ?`):
     - [`CreateJadwalPelajaranAction.php`](file:///d:/laragon/www/pintera-app/app/Domains/Akademik/Actions/Jadwal/CreateJadwalPelajaranAction.php): Bentrok guru memeriksa overlap rentang waktu slot yang dipilih terhadap slot yang sudah terjadwal.
     - [`UpdateJadwalPelajaranAction.php`](file:///d:/laragon/www/pintera-app/app/Domains/Akademik/Actions/Jadwal/UpdateJadwalPelajaranAction.php): Bentrok guru memeriksa overlap waktu mengabaikan self-record (`id != $jadwal->id`).
     - [`ValidateRoomClashAction.php`](file:///d:/laragon/www/pintera-app/app/Domains/Sarpras/Actions/ValidateRoomClashAction.php): Bentrok ruangan sarpras memeriksa overlap rentang waktu slot pada ruangan dan semester terkait.
     - [`database/factories/JamPelajaranFactory.php`](file:///d:/laragon/www/pintera-app/database/factories/JamPelajaranFactory.php): Mengatur default `jam_mulai` dan `jam_selesai` dinamis berdasarkan `urutan` slot (35 menit per jam) agar slot dengan urutan berbeda tidak default overlap di test data.
   - Test: [`tests/Feature/Admin/JadwalPelajaranBentrokWaktuTest.php`](file:///d:/laragon/www/pintera-app/tests/Feature/Admin/JadwalPelajaranBentrokWaktuTest.php) (3 tests passing), seluruh 52 test `JadwalPelajaranCrudTest` passing, 104 test Jadwal passing.

6. **Task 6 — Precedence Resolver Fase dan Kurikulum (`22aad828`)**:
   - Memperbaiki bug pada [`FaseDefaultResolver.php`](file:///d:/laragon/www/pintera-app/app/Domains/Akademik/Services/FaseDefaultResolver.php) dan [`KurikulumAssignmentResolver.php`](file:///d:/laragon/www/pintera-app/app/Domains/Akademik/Services/KurikulumAssignmentResolver.php) di mana baris tingkat spesifik yang tidak cocok (misal tingkat 5) mengalahkan baris catch-all (tingkat null) saat menyelesaikan permintaan tingkat lain (misal tingkat 7) karena `orderByRaw('tingkat IS NULL')`.
   - Mengubah query menjadi memfilter tingkat di klausa `WHERE`: `->where(fn ($q) => $q->where('tingkat', $tingkat)->orWhereNull('tingkat'))` dan menghapus `orderByRaw('tingkat = ? DESC', [$tingkat])`.
   - Baris dengan tingkat tidak cocok langsung tereliminasi dari query.
   - Test: [`tests/Unit/Services/FaseDefaultResolverTest.php`](file:///d:/laragon/www/pintera-app/tests/Unit/Services/FaseDefaultResolverTest.php) (7 tests passing), [`tests/Unit/Services/KurikulumAssignmentResolverTest.php`](file:///d:/laragon/www/pintera-app/tests/Unit/Services/KurikulumAssignmentResolverTest.php) (8 tests passing), dan regresi konsumen Kelas (219 tests passing).

7. **Task 7 — Full Test Suite Final & Cleanup (`58ef6cb4`)**:
   - Seluruh test suite dijalankan sendirian: **2.766 passed (7.543 assertions), 0 failures**!
   - Pint dijalankan bersih: `vendor/bin/pint --dirty --format agent` (passed).
   - Checklist plan diupdate dan dicommit.

---

## 2. Keputusan Penting yang Diambil

1. **Pola "Derive, Jangan Validate" + Explicit Authorization (Tanpa Observer)**:
   - Sesuai `.ai/rules/models.md` ("No model Observers or lifecycle-hook closures"), penutupan IDOR tidak menggunakan model event/Observer, melainkan:
     - Derive `lembaga_id` di Action/Controller via `ResolveLembagaScopeTrait` saat Create (Pintu #1).
     - Explicit authorization check via `authorizeExistingAssignmentScope` / `authorizeExistingMappingScope` pada baris existing sebelum mutating operation berjalan (Pintu #2).
2. **`TenantScope` pada Validasi `TahunAjaran` di Controller**:
   - Saat platform admin membuat `KurikulumAssignment` untuk lembaga tertentu, `TahunAjaran::whereKey($id)->where('lembaga_id', $lembagaId)` secara default dikenai `TenantScope` yang menambahkan `WHERE lembaga_id IS NULL` untuk platform user. Digunakan `TahunAjaran::withoutGlobalScope(TenantScope::class)` agar platform admin dapat mengaitkan assignment ke tahun ajaran lembaga manapun lintas yayasan.
3. **Penyesuaian `KurikulumAssignmentDestroyGuardTest`**:
   - Test ini memvalidasi proteksi in-use kelas saat assignment dihapus oleh yayasan. Setup lama membuat lembaga A dan lembaga B lintas yayasan yang memicu 403 baru. Setup disesuaikan agar kedua lembaga berada di bawah `yayasan_id` yang sama milik user penguji.
4. **Perhitungan Waktu di `JamPelajaranFactory`**:
   - Untuk mencegah slot jam ke-1 dan jam ke-2 yang dibuat lewat factory memiliki jam_mulai/selesai identik (07:00-07:35), factory diperbarui untuk menghitung rentang waktu berdasarkan `urutan`. Urutan 1 tetap 07:00-07:35 (kompatibel 100% dengan test lama).

---

## 3. Catatan Serah-Terima (Hal yang Perlu Diperhatikan / Di Luar Scope Paket Ini)

1. **2 Temuan Minor (Sengaja Tidak Masuk Paket Ini)**:
   - *Race condition validasi total bobot*: Di `CreateKomponenPenilaianAction`/`UpdateKomponenPenilaianAction` tanpa `lockForUpdate()`.
   - *Fallback guru acak diam-diam*: Di `RppController.php` saat `guru_id` tidak dikirim eksplisit.
   - Keduanya tidak disentuh sesuai instruksi scope paket ini.
2. **Pola "Percaya `session('active_lembaga_id')` Tanpa Re-verifikasi di Titik Pakai" di Controller Lain**:
   - Ditemukan pada controller lain: `GuruController`, `JalurPpdbController`, `KalenderAkademikController`, `PengaturanAkademikController`, `GelombangPpdbController`.
   - Controller-controller tersebut membaca session langsung tanpa re-verifikasi `Lembaga::where('id', ...)->where('yayasan_id', $actor->yayasan_id)->exists()`. Jika di masa mendatang `yayasan_id` user berubah di tengah sesi aktif, ini berpotensi menjadi celah serupa. Perlu audit/refactor terpisah pada controller-controller tersebut.

---

## 4. Status Git & Verifikasi

- **Branch**: `akademik-v2`
- **Working Tree**: Clean (hanya storage/debugbar untracked)
- **Commit History Paket Ini**:
  - `bfacf4ce`: feat(akademik): ResolveLembagaScopeTrait -- lembaga_id non-platform tidak pernah dari request
  - `9126bb03`: fix(akademik): tutup 2 pintu IDOR KurikulumAssignment -- nilai ditulis (resolveLembagaId) dan akses baris existing (authorizeExistingAssignmentScope)
  - `06904d89`: fix(akademik): tutup 2 pintu IDOR FaseDefaultMapping -- cerminan perbaikan KurikulumAssignment
  - `68557130`: fix(akademik): verifikasi RPP oleh actor yayasan pakai effectiveLembagaId, bukan lembaga_id mentah yang selalu null
  - `8ea84f98`: fix(akademik): deteksi bentrok guru/ruangan berbasis waktu wall-clock, bukan jam_pelajaran_id mentah
  - `22aad828`: fix(akademik): resolver Fase/Kurikulum -- filter tingkat di WHERE, bukan cuma ORDER BY, cegah tingkat tak cocok mengalahkan catch-all
  - `58ef6cb4`: docs(akademik): update checklist plan perbaikan audit akademik lanjutan -- semua 7 task selesai
- **Hasil Test Final**: 2.766 test passed (7.543 assertions), 0 failures (100% pass).
