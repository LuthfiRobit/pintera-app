# Handoff Log — Batas Waktu Edit Presensi & Jurnal KBM (Proyek A)

**Tanggal**: 6 September 2026  
**Branch**: `akademik-v2`  
**Spec**: `.agents/specs/2026-09-06-batas-edit-presensi-jurnal.md`  
**Plan**: `.agents/plans/2026-09-06-batas-edit-presensi-jurnal.md`  
**Base commit**: `837a1787` (setelah Opsi A3 / Scan Presensi Kartu Digital Siswa) → **HEAD**: `47502c25` (Task 6)

---

## 1. Apa yang Dikerjakan

Penyelesaian backlog audit Low-severity (sejak 27-28 Agustus 2026): `Guru\Akademik\JurnalKbmController::update()` sebelumnya tidak memiliki batasan waktu untuk mengubah presensi/jurnal pada `SesiPembelajaran` masa lalu. Hal ini memicu risiko integritas data historis di mana kehadiran siswa yang sudah menjadi dasar rekapitulasi rapor dapat berubah diam-diam.

Fitur ini diimplementasikan melalui pendekatan konfigurasi per-Lembaga:
1. **Migrasi & Model**: Menambahkan kolom `batas_edit_absen_hari` (unsigned integer, default `3`) pada tabel `lembaga` dan mendaftarkannya ke `$fillable` di model `App\Models\Lembaga`.
2. **DTO & Action**: Membangun DTO `BatasEditAbsenLembagaData` dan Action domain `UpdateBatasEditAbsenLembagaAction` di `App\Domains\Akademik\Actions\Kalender` sesuai arsitektur DDD dan konvensi sibling action (`UpdateHariAktifLembagaAction`).
3. **Endpoint Admin**: Menambahkan endpoint `PUT admin/pengaturan/akademik/batas-edit-absen` (`admin.pengaturan.akademik.batas-edit-absen`) pada `PengaturanAkademikController` dengan validasi integer `between:1,365` dan otorisasi `pengaturan-akademik.kelola`.
4. **UI Admin**: Menambahkan input konfigurasi "Batas Waktu Edit Presensi" pada tab "Hari Aktif Sekolah" di halaman Pengaturan Akademik lembaga (`resources/views/portals/lembaga/akademik/pengaturan/akademik.blade.php`).
5. **Enforcement Controller**: Menambahkan private method `sesiTerkunci(SesiPembelajaran $sesi, Guru $guru): bool` di `JurnalKbmController`:
   - Mengecualikan Wali Kelas untuk kelasnya sendiri (`$sesi->kelas->wali_kelas_guru_id === $guru->id`).
   - Mengunci sesi jika `$sesi->tanggal->lt(now()->subDays($batasHari)->startOfDay())`.
   - Mengirim status `$terkunci` dan `$batasEditHari` ke view di `show()`.
   - Menolak eksekusi `update()` dan mengalihkan dengan pesan flash error tanpa pernah mengeksekusi `RecordJurnalDanPresensiAction`.
6. **Frontend Guru**: Menambahkan banner peringatan berwarna amber saat sesi terkunci, me-nonaktifkan field materi, status radio presensi, catatan keterangan izin/sakit, dan tombol submit, serta menyembunyikan blok modal "Scan Presensi via Kartu Digital" saat sesi terkunci tanpa mengganggu integritas fitur Scan Presensi (A3) pada sesi yang masih aktif.

---

## 2. Daftar Commit

| Task | Commit | Pesan Commit & Ringkasan |
|---|---|---|
| Task 1 | `138ed491` | `feat(akademik): tambah kolom batas_edit_absen_hari di tabel lembaga & model` |
| Task 2 | `9bcf9945` | `feat(akademik): DTO & Action UpdateBatasEditAbsenLembagaAction` |
| Task 3 | `58edb102` | `feat(akademik): endpoint update batas edit absen di Pengaturan Akademik` |
| Task 4 | `26b50b4b` | `feat(akademik): field Batas Waktu Edit Presensi di halaman Pengaturan Akademik` |
| Task 5 | `cf4d8ae8` | `feat(akademik): enforcement batas edit absen di JurnalKbmController -- wali kelas dikecualikan` |
| Task 6 | `47502c25` | `feat(akademik): banner & disable form Jurnal KBM saat sesi terkunci batas edit` |
| Task 7 | `pending` | `docs(akademik): handoff log & update roadmap -- batas edit presensi jurnal selesai` |

---

## 3. Keputusan Penting yang Diambil

1. **Basis Cutoff Rolling N Hari dari Hari Ini**:
   - Diputuskan cutoff dihitung secara dinamis dari `now()->subDays($batasHari)->startOfDay()`, bukan terikat pada status pembagian rapor atau semester aktif. Keputusan ini menjaga fleksibilitas lintas jenjang dan kalender administrasi yang heterogen.
2. **Pengecualian Khusus Wali Kelas Kelas Terkait**:
   - Pengecualian hanya berlaku jika `$sesi->kelas->wali_kelas_guru_id === $guru->id`. Jika seorang guru adalah wali kelas untuk kelas lain namun mengajar mapel biasa di kelas ini, pembatasan cutoff tetap berlaku penuh.
3. **Pemisahan Tegas dari Proyek B & C**:
   - **Proyek B** (Waka Kurikulum akses & edit lintas-guru) dan **Proyek C** ("Guru piket" pengisian darurat real-time) sama sekali TIDAK disertakan dalam implementasi ini sesuai kesepakatan kickoff.
4. **UX Fail-Early**:
   - Status `terkunci` dievaluasi di method `show()` dan dikirim ke view sebelum guru sempat mengetik, mencegah frustrasi guru mengisi jurnal panjang yang kemudian ditolak saat submit.
5. **Penanganan Blade Tag `<x-primary-button>`**:
   - Penggunaan direktif Blade `@disabled($terkunci)` di dalam tag komponen custom `<x-primary-button ...>` memicu parser error Blade (`syntax error, unexpected token "endif"`). Solusi yang diterapkan adalah menggunakan dynamic attribute binding `:disabled="$terkunci"`, yang diteruskan secara mulus ke `$attributes` komponen.
6. **Integritas Fitur Scan Presensi (A3)**:
   - Blok Scan Presensi disembunyikan menggunakan `@unless($terkunci) ... @endunless`. Saat sesi belum terkunci, komponen kamera dan event listener `@presensi-scanned.window` bekerja normal 100%.

---

## 4. Hasil Verifikasi Pengujian

### A. Pengujian Otomatis (Pest & Feature Tests)
- `tests/Unit/Domains/Akademik/LembagaBatasEditAbsenTest.php`: **2/2 passed**
- `tests/Unit/Domains/Akademik/UpdateBatasEditAbsenLembagaActionTest.php`: **1/1 passed**
- `tests/Feature/Admin/PengaturanBatasEditAbsenTest.php`: **3/3 passed**
- `tests/Feature/Guru/JurnalKbmBatasEditTest.php`: **4/4 passed**
- `tests/Feature/Guru/JurnalKbmControllerTest.php`: **14/14 passed** (termasuk 2 test baru banner lock)
- `tests/Feature/Guru/JurnalKbmResolveKartuTest.php`: **4/4 passed** (regresi A3)
- `tests/Feature/Akademik/JurnalKbmTanggalSusulanTest.php`: **2/2 passed**
- **Full Test Suite (`php artisan test --compact`)**:
  - Total: **2,884 passed, 4 failed** (7,817 assertions)
  - Regresi baru: **0** (seluruh test fitur baru & lama lulus penuh).
  - 4 kegagalan adalah pre-existing seeder debt (`M3DemoDataSeederTest` x 2, `PresensiSeederTest`, `SesiPembelajaranSeederTest`) yang tidak terkait dengan fitur ini.
- **Pint Formatter (`vendor/bin/pint --dirty --format agent`)**:
  - Hasil: `{"tool":"pint","result":"passed"}` (100% lulus style check).

### B. Verifikasi Manual Browser

1. **Task 4 (Pengaturan Akademik di Portal Lembaga)**:
   - Pengujian dilakukan pada `http://127.0.0.1:8000/admin/pengaturan/akademik` menggunakan akun admin kurikulum (`kurikulum.sd@demo.test`).
   - Nilai default awal: `3` hari terisi otomatis pada input.
   - Perubahan nilai menjadi `7` hari berhasil disimpan via AJAX PUT, notifikasi toast sukses muncul, dan persistensi terbukti saat halaman di-refresh.
   - Pengujian input invalid (angka `0` dan `400`) mengembalikan validasi error 422 dengan pesan error yang ditangkap dan ditampilkan rapi oleh Alpine toast tanpa terjadi HTTP 500.
   - Nilai dikembalikan ke default `3` hari.
   - Bukti tangkapan layar: `batas_absen_initial_1788699587126.png`, `batas_absen_save_success_1788699653972.png`, `batas_absen_persisted_7_1788699687072.png`, `batas_absen_validation_error_1788699750701.png`, `batas_absen_reset_3_1788699801747.png`.

2. **Task 6 (Jurnal KBM Guru - Sesi Terkunci vs Terbuka)**:
   - Pengujian dilakukan pada `http://127.0.0.1:8000/guru/jurnal-kbm/{id}` menggunakan akun guru (`guru.sd1@demo.test`).
   - **Skenario Terkunci (Sesi > 3 hari yang lalu, guru bukan wali kelas)**:
     - Banner peringatan warna amber tampil jelas di bagian atas form: *"Sesi ini sudah melewati batas waktu edit (3 hari). Form di bawah ditampilkan hanya untuk dilihat. Hubungi Wali Kelas kelas ini kalau perlu koreksi."*
     - Textarea materi dan input catatan keterangan berstatus disabled (background abu-abu).
     - Tombol radio pilihan status kehadiran (Hadir, Sakit, Izin, Alpa) berstatus disabled dan tidak merespon klik.
     - Blok "Scan Presensi via Kartu Digital" tidak ditampilkan sama sekali.
     - Tombol `<x-primary-button>` "Simpan Jurnal & Presensi" berstatus disabled.
     - Bukti tangkapan layar: `sesi_2_banner_1788700411962.png`, `sesi_2_locked_1788700400557.png`.
   - **Skenario Terbuka (Sesi Hari Ini)**:
     - Banner amber tidak muncul.
     - Textarea materi dan radio button presensi aktif sepenuhnya.
     - Blok tombol "Scan Presensi via Kartu Digital" tampil dan siap digunakan.
     - Tombol simpan aktif.
     - Bukti tangkapan layar: `sesi_1_editable_1788700496727.png`.

---

## 5. Hal yang Masih Perlu Direview Manusia / Claude

1. **Git State**:
   - Seluruh perubahan berada di branch `akademik-v2`.
   - **TIDAK di-merge ke main** sesuai batasan instruksi operasional, menunggu persetujuan rilis gabungan dari user.
2. **Backlog Terpisah**:
   - Proyek B (Waka Kurikulum akses lintas-guru) dan Proyek C (Guru piket) tetap tercatat sebagai item backlog terbuka di `PETA_PENGEMBANGAN.md`.
