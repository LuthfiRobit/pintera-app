# Handoff Log: Fix ruangan_id Lolos Cross-Lembaga pada Jadwal Pelajaran

**Tanggal**: 28 Agustus 2026 (WIB)  
**Branch**: `akademik-v2`  
**Spec**: [`.agents/specs/2026-08-28-akademik-fix-ruangan-id-cross-lembaga-jadwal-pelajaran.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-28-akademik-fix-ruangan-id-cross-lembaga-jadwal-pelajaran.md)  
**Plan**: [`.agents/plans/2026-08-28-akademik-fix-ruangan-id-cross-lembaga-jadwal-pelajaran.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-28-akademik-fix-ruangan-id-cross-lembaga-jadwal-pelajaran.md)  

---

## 1. Apa yang Dikerjakan

Menutup celah cross-tenant pada penugasan `ruangan_id` di fitur Jadwal Pelajaran (`app/Http/Controllers/Admin/JadwalPelajaranController.php`):

1. **Cross-Check Kepemilikan Lembaga pada `store()` & `update()`**:
   - Menambahkan blok validasi server-side sebelum pembentukan `$ruanganId`:
     ```php
     if (! empty($data['ruangan_id'])) {
         $ruangan = Ruangan::withoutGlobalScope(TenantScope::class)->find((int) $data['ruangan_id']);
         if (! $ruangan || (! $ruangan->is_shared && $ruangan->lembaga_id !== $kelas->lembaga_id)) {
             $msg = 'Ruangan harus berasal dari lembaga yang sama dengan kelas ini, atau berupa ruangan bersama.';
             if ($request->ajax() || $request->wantsJson()) {
                 return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['ruangan_id' => [$msg]]], 422);
             }
             return back()->withErrors(['ruangan_id' => $msg])->withInput();
         }
     }
     ```
   - Mengakomodasi pengecualian sah untuk ruangan bersama (`is_shared = true`).

2. **Pengujian TDD & Reproduksi Bug (`tests/Feature/Admin/JadwalPelajaranCrudTest.php`)**:
   - Menambahkan helper `buatRuanganUntukLembaga()` (menggunakan `Gedung::create()` + `Ruangan::create()`).
   - Menambahkan 4 test baru:
     - `it('rejects a ruangan_id belonging to another lembaga on store')` (red → green).
     - `it('accepts a shared ruangan_id from another lembaga on store')` (green).
     - `it('accepts a ruangan_id from the same lembaga on store')` (green).
     - `it('rejects updating ruangan_id to a ruangan from another lembaga')` (red → green).
   - **Commit**: [`524394be`](file:///d:/laragon/www/pintera-app/app/Http/Controllers/Admin/JadwalPelajaranController.php) (`fix(akademik): cross-check ruangan_id vs lembaga kelas pada jadwal pelajaran`).

3. **Verifikasi Checkpoint Test Scoped & Dokumentasi**:
   - Menjalankan `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --compact`: **52 passed, 0 failed (153 assertions)**.
   - Mencatat pada `PETA_PENGEMBANGAN.md`.
   - **Commit**: [`d580d673`](file:///d:/laragon/www/pintera-app/PETA_PENGEMBANGAN.md) (`docs: catat fix cross-check ruangan_id jadwal pelajaran di peta pengembangan`).

---

## 2. Keputusan Penting yang Diambil

1. **Bypass Explicit Scope dengan `withoutGlobalScope(TenantScope::class)`**:
   - Validasi kepemilikan ruangan sengaja membypass `TenantScope` lalu membandingkan `lembaga_id` secara manual, agar aturan validasi berperilaku konsisten untuk semua aktor (admin lembaga maupun yayasan).
2. **Preservasi Fitur Ruangan Bersama (`is_shared`)**:
   - Ruangan yang ditandai `is_shared = true` tetap diizinkan dipakai lintas-lembaga di bawah yayasan yang sama.
3. **Fallback `$kelas->ruangan_id` Tidak Divalidasi Ulang**:
   - Jika `ruangan_id` tidak disertakan dalam payload, fallback menggunakan ruangan bawaan kelas tanpa validasi ulang di controller ini.

---

## 3. Hal yang Perlu Direview Manusia / Claude

- Seluruh 52 test di `JadwalPelajaranCrudTest.php` lulus 100%.
- Git State:
  - Branch: `akademik-v2`
  - Bersih, semua perubahan di-commit secara rapi dan terdokumentasi.
