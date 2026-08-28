# Handoff Log: Fix Rapor Semester Mismatch & Kenaikan Kelas Mundur

**Tanggal**: 28 Agustus 2026 (WIB)  
**Branch**: `akademik-v2`  
**Spec**: [`.agents/specs/2026-08-28-akademik-fix-rapor-semester-mismatch-dan-kenaikan-kelas-mundur.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-28-akademik-fix-rapor-semester-mismatch-dan-kenaikan-kelas-mundur.md)  
**Plan**: [`.agents/plans/2026-08-28-akademik-fix-rapor-semester-mismatch-dan-kenaikan-kelas-mundur.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-28-akademik-fix-rapor-semester-mismatch-dan-kenaikan-kelas-mundur.md)  

---

## 1. Apa yang Dikerjakan

Menutup 2 celah inkonsistensi periode (semester/tahun ajaran) dan integritas arah waktu pada modul Akademik:

### Task 1: Cross-Check Semester vs Tahun Ajaran Kelas di `Guru\RaporController`
1. Menambahkan guard `abort_if` pada 4 method (`edit()`, `generateNarasi()`, `ajukan()`, `cetak()`) di `app/Http/Controllers/Guru/RaporController.php` untuk memvalidasi bahwa `semester->tahun_ajaran_id` harus identik dengan `tahun_ajaran_id` milik kelas siswa / kelas terkait:
   - `edit()`, `generateNarasi()`, `cetak()`: `abort_if($semester->tahun_ajaran_id !== $siswa->kelas->tahun_ajaran_id, 404);`
   - `ajukan()`: `abort_if($semester->tahun_ajaran_id !== $kelas->tahun_ajaran_id, 404);`
2. Menambahkan 4 test reproduksi di `tests/Feature/Guru/RaporControllerTest.php`.
   - Fase RED terverifikasi: 4 test gagal saat semester dari tahun ajaran lain dikirim.
   - Fase GREEN: 21 test passed di `RaporControllerTest.php`.
3. **Commit**: [`dd757eb2`](file:///d:/laragon/www/pintera-app/app/Http/Controllers/Guru/RaporController.php) (`fix(akademik): cross-check semester vs tahun ajaran kelas pada Guru RaporController`).

### Task 2: Validasi Arah Waktu Tahun Ajaran pada Kenaikan Kelas
1. Menambahkan pengecekan kronologis `tanggal_mulai` pada `app/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasAction.php`:
   ```php
   $tahunAjaranLama = TahunAjaran::findOrFail($kelasLama->tahun_ajaran_id);
   $tahunAjaranBaru = TahunAjaran::findOrFail($kelasBaru->tahun_ajaran_id);

   if ($tahunAjaranBaru->tanggal_mulai < $tahunAjaranLama->tanggal_mulai) {
       throw new \DomainException("Kelas tujuan \"{$kelasBaru->nama}\" berada di tahun ajaran \"{$tahunAjaranBaru->nama}\" yang lebih lama dari tahun ajaran kelas asal \"{$tahunAjaranLama->nama}\". Pilih kelas tujuan dari tahun ajaran berikutnya.");
   }
   ```
2. Menambahkan 2 unit test di `tests/Unit/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasActionTest.php` (reproduksi error ketika tanggal_mulai mundur, dan sukses ketika tanggal_mulai lebih baru).
   - Fase RED terverifikasi (exception tidak dilempar sebelum fix).
   - Fase GREEN: 5 test passed di unit test Action, dan 12 test passed pada controller feature tests (`KenaikanKelasControllerTest.php` dan `KenaikanKelasControllerUxTest.php`).
3. Menjalankan Laravel Pint (`vendor/bin/pint --dirty --format agent`).
4. **Commit**: [`3f48d712`](file:///d:/laragon/www/pintera-app/app/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasAction.php) (`fix(akademik): validasi tahun ajaran tujuan kenaikan kelas tidak boleh mundur`).

---

## 2. Keputusan Penting yang Diambil

1. **Penggunaan Operator Strict `<` (Bukan `<=`) pada Validasi Kenaikan Kelas**:
   - Kolom `tanggal_mulai` bertipe `date`, dan fixture factory bawaan (`TahunAjaranFactory`) menetapkan `now()` secara default. Menggunakan `<=` akan menyebabkan test existing yang membuat dua instance `TahunAjaran` tanpa eksplisit custom date gagal (karena tanggalnya identik). Dengan operator `<`, validasi hanya menolak tahun ajaran yang secara tegas mundur (`tanggal_mulai` lebih awal).
2. **Mempertahankan Guard Existing Tahun Ajaran Sama**:
   - Pengecekan `$kelasBaru->tahun_ajaran_id === $kelasLama->tahun_ajaran_id` tetap dipertahankan dengan pesan error spesifiknya sendiri sebelum pengecekan `tanggal_mulai`.
3. **Pencatatan Dokumentasi Perkembangan**:
   - Dicatat di `PETA_PENGEMBANGAN.md` pada **Commit** [`f5aa97c4`](file:///d:/laragon/www/pintera-app/PETA_PENGEMBANGAN.md).

---

## 3. Hal yang Perlu Direview Manusia / Claude

- Seluruh test scoped:
  - `tests/Feature/Guru/RaporControllerTest.php`: 21 passed (38 assertions)
  - `tests/Unit/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasActionTest.php`: 5 passed (11 assertions)
  - `tests/Feature/Admin/KenaikanKelasControllerTest.php` & `tests/Feature/Akademik/KenaikanKelasControllerUxTest.php`: 12 passed (54 assertions)
- Git State:
  - Branch: `akademik-v2`
  - Bersih dan siap.
