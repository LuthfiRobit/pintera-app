# 📝 Handoff Log: Sub-Task 03b — Mode Tematik/Harian (KB/TK/SD/SLB/TPA/SPS) untuk Jurnal KBM & Presensi

- **Tanggal & Waktu:** 19 Agustus 2026, ~05:10 WIB
- **Terkait Spec:** [`.agents/specs/2026-08-18-2200-akademik-03b-mode-tematik-kbtksd.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-18-2200-akademik-03b-mode-tematik-kbtksd.md)
- **Terkait Plan:** [`.agents/plans/2026-08-18-2200-akademik-03b-mode-tematik-kbtksd.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-18-2200-akademik-03b-mode-tematik-kbtksd.md)
- **Metode Eksekusi:** Subagent-Driven Development (task-by-task execution + task-scoped review + final test suite verification), langsung di branch `akademik-v2`, tanpa git worktree.
- **Status Sub-Task:** 🟢 SELESAI (COMPLETED)

---

## 1. Apa yang Dikerjakan

Menyelesaikan implementasi "Mode Tematik/Harian" (satu sesi guru-kelas per hari untuk jenjang KB, TK, SD, SLB, TPA, SPS, dan jenjang baru masa depan) pada fitur Jurnal KBM & Presensi Siswa. Sub-task ini melengkapi Sub-Task 03a (mode Sesi Mapel per jam pelajaran untuk SMP/SMA/SMK) dengan memanfaatkan struktur skema dan HTTP/UI layer yang sudah dirancang mode-agnostic.

**Rincian 4 task yang diselesaikan:**

1. **Enum `ModePembelajaran`** (`app/Domains/Akademik/Enums/ModePembelajaran.php`):
   - Pure PHP enum dengan case `SesiMapel` dan `Tematik`.
   - Method static `fromBentukPendidikan(string $bentukPendidikan): self` dengan negative whitelist (`SMP`, `SMA`, `SMK` → `SesiMapel`; default semua jenjang lain → `Tematik`).
   - Unit test `tests/Unit/Domains/Akademik/ModePembelajaranTest.php` (10 passed, 10 assertions).

2. **Service `SesiTematikGenerator`** (`app/Domains/Akademik/Services/SesiTematikGenerator.php`):
   - Method `generateUntukTanggal(Kelas $kelas, CarbonInterface $tanggal, int $semesterId): ?SesiPembelajaran`.
   - Skip jika bukan hari efektif (via `KalenderAkademikResolver`), jika kelas belum punya `wali_kelas_guru_id`, atau belum punya jam pelajaran operasional.
   - Menggunakan `firstOrCreate` dengan key `['kelas_id', 'tanggal', 'jadwal_pelajaran_id' => null]` dan mengisi `guru_id = wali_kelas_guru_id`, `mata_pelajaran_id = null`, jam mulai & selesai dari rentang slot aktif kelas pada hari tersebut.
   - Auto-seeding baris `Presensi` default `hadir` untuk setiap siswa aktif saat sesi baru dibuat.
   - Unit test `tests/Unit/Services/SesiTematikGeneratorTest.php` (5 passed, 14 assertions).

3. **Wiring ke `GenerateSesiHarianAction` & Aksesor `isTematik()`** (`app/Domains/Akademik/Actions/Presensi/GenerateSesiHarianAction.php`, `app/Domains/Akademik/Models/SesiPembelajaran.php`):
   - Menambahkan method `isTematik(): bool` (`return $this->jadwal_pelajaran_id === null;`) pada model `SesiPembelajaran`.
   - Memperbarui `GenerateSesiHarianAction` agar mengecek `ModePembelajaran::fromBentukPendidikan($kelas->lembaga->bentuk_pendidikan)` dan memanggil generator yang tepat (`generator` vs `generatorTematik`).
   - Unit test `tests/Unit/Domains/Akademik/SesiPembelajaranIsTematikTest.php` (2 passed, 2 assertions).
   - Feature test adaptif `tests/Feature/Akademik/JurnalKbmAdaptiveTest.php` (3 passed, 15 assertions).
   - Scoped regression test set (8 file test): 44 passed, 86 assertions.

4. **Handoff Log & Sinkronisasi Master Plan**:
   - Menulis log ini dan memperbarui status Sub-Task 03b di dokumen master plan `.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md`.

**Hasil Akhir Pengujian:**
- Scoped regression suite (8 test files): **44 passed, 86 assertions** (durasi ~12.4s).
- Full test suite (`php artisan test`) dijalankan atas konfirmasi eksplisit user: **1773 passed, 0 failed, 5476 assertions** (durasi 343.21s, bertambah +20 test dari baseline 1753 passed).

---

## 2. Keputusan Penting yang Diambil

1. **Negative Whitelist untuk Deteksi Mode:**
   - `bentuk_pendidikan` IN (`SMP`, `SMA`, `SMK`) dipetakan ke `ModePembelajaran::SesiMapel`, sedangkan semua nilai lain (`KB`, `TPA`, `SPS`, `TK`, `SD`, `SLB`, serta jenjang baru apa pun di masa depan) masuk ke `ModePembelajaran::Tematik`. Hal ini lebih aman (fail-safe) untuk nilai enum baru di masa depan daripada positive whitelist eksplisit.

2. **Reuse Model & Skema `SesiPembelajaran` (Zero New Migration):**
   - Sesi tematik disimpan sebagai baris normal `sesi_pembelajaran` dengan `jadwal_pelajaran_id = NULL` dan `mata_pelajaran_id = NULL`.
   - Tidak ada modifikasi pada `JurnalKbmController`, `UpdateJurnalPresensiRequest`, `RecordJurnalDanPresensiAction`, `JurnalPresensiData`, `RekapKehadiranController`, maupun kedua Blade view (`index.blade.php`, `show.blade.php`), karena semuanya sudah dirancang mode-agnostic sejak Sub-Task 03a.

3. **Proteksi Idempotensi di Level Aplikasi:**
   - Unique index DB `['jadwal_pelajaran_id', 'tanggal']` di MySQL memperlakukan `NULL` sebagai nilai yang tidak saling konflik. Proteksi duplikasi sesi tematik dilakukan di layer aplikasi melalui pencarian `firstOrCreate` dengan key `['kelas_id', 'tanggal', 'jadwal_pelajaran_id' => null]`.

4. **Penyesuaian Helper Test pada `JurnalKbmControllerTest.php`:**
   - Saat integrasi Task 3, `JurnalKbmControllerTest` sempat gagal karena `LembagaFactory` secara acak memilih `bentuk_pendidikan` (Faker), sehingga bila terpilih 'TK'/'SD', action mengarah ke mode tematik padahal fixture tes tersebut menyiapkan `JadwalPelajaran` tanpa `wali_kelas_guru_id`.
   - Helper `siapkanGuruDenganJadwalHariIni()` di `tests/Feature/Guru/JurnalKbmControllerTest.php` diperjelas dengan `'bentuk_pendidikan' => 'SMP'` agar tes spesifik mode Sesi Mapel bersifat deterministik dan tidak bergantung pada seed Faker.

---

## 3. Hal yang Perlu Direview Manusia / Tahap Selanjutnya

1. **Verifikasi Visual Manual di Browser:**
   - Login sebagai guru yang menjadi wali kelas pada lembaga jenjang KB/TK/SD/SLB (memiliki permission `presensi.isi`).
   - Buka menu **"Jurnal & Presensi"** (`/guru/jurnal-kbm`) → pastikan muncul tepat 1 sesi harian untuk hari ini dengan label "(tanpa mapel)" atau nama kelas.
   - Buka sesi tersebut (`/guru/jurnal-kbm/{id}`) → isi form catatan jurnal (materi) dan ubah status presensi siswa (Hadir/Sakit/Izin/Alpa) → simpan.
   - Buka menu **"Rekap Kehadiran"** (`/guru/rekap-kehadiran`) → pastikan rekapitulasi semesteran untuk kelas tersebut menghitung presensi dari sesi tematik yang baru diisi.

2. **Status Git:**
   - Seluruh commit sub-task 03b berada di branch `akademik-v2` lokal (belum di-push ke remote repo):
     - `04d61a1` `feat(akademik): tambah enum ModePembelajaran untuk deteksi mode Sesi Mapel vs Tematik`
     - `5a0f09f` `feat(akademik): tambah SesiTematikGenerator untuk sesi harian mode Tematik`
     - `70087d4` `feat(akademik): sambungkan generator tematik ke GenerateSesiHarianAction, tambah isTematik() accessor`
     - Commit dokumentasi handoff log & master plan (Task 4).

3. **Tahap Selanjutnya:**
   - FASE 3 (Jurnal KBM Adaptif & Presensi Siswa Multi-Jenjang) untuk Sub-Task 03a dan 03b sudah **100% SELESAI**.
   - Proyek dapat melanjutkan ke sub-task berikutnya sesuai rencana di master plan (misal FASE 4: Modul Nilai, Rapor, dan Asesmen Kurikulum Merdeka).
