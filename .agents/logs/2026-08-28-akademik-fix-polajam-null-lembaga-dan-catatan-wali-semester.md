# Handoff Log: Fix PolaJam lembaga_id NULL & Catatan Wali Kelas Semester Mismatch

**Tanggal**: 28 Agustus 2026 (WIB)  
**Branch**: `akademik-v2`  
**Spec**: [`.agents/specs/2026-08-28-akademik-fix-polajam-null-lembaga-dan-catatan-wali-semester.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-28-akademik-fix-polajam-null-lembaga-dan-catatan-wali-semester.md)  
**Plan**: [`.agents/plans/2026-08-28-akademik-fix-polajam-null-lembaga-dan-catatan-wali-semester.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-28-akademik-fix-polajam-null-lembaga-dan-catatan-wali-semester.md)  

---

## 1. Apa yang Dikerjakan

### Task 1: Isi `lembaga_id` untuk Aktor Lembaga Biasa di `PolaJamController::store()`
1. **Derivasi Eksplisit `$lembagaId`**:
   - Menyelaraskan implementasi `PolaJamController::store()` dengan mirror pola `GuruController::resolveLembagaId()`:
     ```php
     $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
         ? session('active_lembaga_id')
         : $request->user()->lembaga_id;

     if ($lembagaId === null) {
         return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat pola jam.'])->withInput();
     }
     ```
   - Memastikan data `PolaJam` yang diinput selalu memiliki `lembaga_id` yang valid dan terisi secara eksplisit dari payload controller alih-alih mengandalkan fallback model event `BelongsToTenant`.
2. **Pengujian Komprehensif di `tests/Feature/Admin/PolaJamCrudTest.php`**:
   - Memperkuat assertion pada test `creates a pola jam` dengan memverifikasi `expect($polaJam->lembaga_id)->toBe($lembaga->id)`.
   - Menambahkan 3 test baru:
     - `it('lets the lembaga-scoped manager see the pola jam they just created in the index')`
     - `it('creates a pola jam with the active lembaga for a yayasan-scoped manager')`
     - `it('rejects creating a pola jam for a yayasan-scoped manager with no active lembaga')`
   - Semua 26 test di `PolaJamCrudTest.php` lulus 100%.
3. **Commit**: [`d25ef16d`](file:///d:/laragon/www/pintera-app/app/Http/Controllers/Admin/PolaJamController.php) (`fix(akademik): isi lembaga_id untuk aktor lembaga biasa pada PolaJamController::store()`).

### Task 2: Cross-Check Semester vs Tahun Ajaran Kelas di `Guru\RaporController::update()`
1. **Melengkapi Guard Tahun Ajaran pada Method Write-Path**:
   - Pada fix sebelumnya (`dd757eb2`), 4 method (`edit`, `generateNarasi`, `ajukan`, `cetak`) telah diproteksi dengan guard tahun ajaran.
   - Method `update()` yang merupakan write-path penyimpanan data `CatatanWaliKelas` kini diproteksi dengan guard yang sama:
     ```php
     $semester = Semester::find($request->validated('semester_id'));
     abort_if($semester === null || $semester->tahun_ajaran_id !== $siswa->kelas->tahun_ajaran_id, 404);
     ```
2. **Pengujian TDD di `tests/Feature/Guru/RaporControllerTest.php`**:
   - Menambahkan test reproduksi `it('rejects saving catatan wali kelas when semester_id belongs to a different tahun ajaran than the siswa kelas')`.
   - Fase RED terverifikasi (response redirect 302 alih-alih 404 sebelum fix).
   - Fase GREEN terverifikasi: seluruh 22 test passed.
3. **Pint Code Formatting**:
   - `vendor/bin/pint --dirty --format agent` dijalankan bersih.
4. **Commit**: [`cbd4cc15`](file:///d:/laragon/www/pintera-app/app/Http/Controllers/Guru/RaporController.php) (`fix(akademik): cross-check semester vs tahun ajaran kelas pada update catatan wali kelas`).

---

## 2. Keputusan Penting yang Diambil

1. **Konsistensi Scope Derivation**:
   - Derivasi `lembaga_id` di `PolaJamController::store()` disesuaikan agar sama persis dengan `GuruController`, menutup potensi orphan record jika ada eksekusi tanpa model creating hook.
2. **Kelengkapan Guard Catatan Wali Kelas**:
   - Menutup celah tersisa pada mutasi data catatan wali kelas sehingga seluruh 5 method di `Guru\RaporController` memiliki keseragaman proteksi cross-periode.
3. **Pencatatan Dokumentasi**:
   - Dicatat di `PETA_PENGEMBANGAN.md` pada **Commit** [`a5ee6616`](file:///d:/laragon/www/pintera-app/PETA_PENGEMBANGAN.md).

---

## 3. Hal yang Perlu Direview Manusia / Claude

- Seluruh test scoped:
  - `tests/Feature/Admin/PolaJamCrudTest.php`: 26 passed (84 assertions)
  - `tests/Feature/Guru/RaporControllerTest.php`: 22 passed (40 assertions)
- Git State:
  - Branch: `akademik-v2`
  - Bersih dan siap.
