# 📋 Rencana Implementasi Sub-Task 01: Fondasi Domain Akademik & Integrasi Jadwal-Sarpras Anti-Bentrok

- **Document ID / Slug:** `2026-08-17-1030-akademik-01-jadwal-dan-sarpras`
- **Spec File:** [`.agents/specs/2026-08-17-1030-akademik-01-jadwal-dan-sarpras.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-17-1030-akademik-01-jadwal-dan-sarpras.md)
- **Plan File:** `.agents/plans/2026-08-17-1030-akademik-01-jadwal-dan-sarpras.md`
- **Handoff Log File:** `.agents/logs/2026-08-17-1030-akademik-01-jadwal-dan-sarpras.md`
- **Tanggal & Waktu:** 17 Agustus 2026, 10:30 WIB
- **Status:** DRAFT (Ready for Execution)

---

## Checklist Rinci Tahapan Kerja (*Atomic Checklist*)

- [x] **Langkah 1: Scaffolding Domain Folder & DTO**
  - [x] Buat direktori `app/Domains/Akademik/Actions/Jadwal/`, `app/Domains/Akademik/DataTransferObjects/`, `app/Domains/Akademik/Enums/`, `app/Domains/Akademik/Models/`, `app/Domains/Akademik/Services/`.
  - [x] Buat file `app/Domains/Akademik/DataTransferObjects/JadwalPelajaranData.php` (`final readonly class`).

- [x] **Langkah 2: Pembuatan Action Classes (Anti-Bentrok Ruangan & Guru)**
  - [x] Buat `app/Domains/Akademik/Actions/Jadwal/CreateJadwalPelajaranAction.php`:
    - Mengintegrasikan `ValidateRoomClashAction::execute()`.
    - Menambahkan validasi anti-bentrok guru (`JadwalPelajaran::where('guru_id', ...)->where('jam_pelajaran_id', ...)->where('semester_id', ...)`).
  - [x] Buat `app/Domains/Akademik/Actions/Jadwal/UpdateJadwalPelajaranAction.php`.
  - [x] Buat `app/Domains/Akademik/Actions/Jadwal/DuplicateJadwalAction.php`.

- [x] **Langkah 3: FormRequests & Thin Controller Refactor**
  - [x] Buat `app/Http/Requests/Akademik/StoreJadwalPelajaranRequest.php`.
  - [x] Buat `app/Http/Requests/Akademik/UpdateJadwalPelajaranRequest.php`.
  - [x] Buat `app/Http/Requests/Akademik/DuplicateJadwalRequest.php`.
  - [x] Refactor `app/Http/Controllers/Admin/JadwalPelajaranController.php` untuk memanfaatkan DTO dan Action classes.
  - [x] Pastikan query builder di controller mengirimkan `$ruanganList` (dari `App\Domains\Sarpras\Models\Ruangan`) ke view.

- [x] **Langkah 4: Pembaruan Blade Views UI**
  - [x] Perbarui `resources/views/admin/jadwal-pelajaran/create.blade.php`: Tambahkan dropdown `ruangan_id` (auto-selected dari `kelas.ruangan_id`).
  - [x] Perbarui `resources/views/admin/jadwal-pelajaran/edit.blade.php`: Dropdown `ruangan_id`.
  - [x] Perbarui `resources/views/admin/jadwal-pelajaran/_daftar.blade.php`: Tampilkan label/badge nama ruangan.
  - [x] Perbarui `resources/views/admin/jadwal-pelajaran/_modal-form.blade.php`: Dropdown `ruangan_id` pada AJAX modal.
  - [x] Perbarui `resources/views/admin/jadwal-pelajaran/_matrix-roster.blade.php`: Tampilkan badge ruangan di kartu jadwal.

- [x] **Langkah 5: Pengujian Otomatis (*Automated Testing*)**
  - [x] Buat file test `tests/Feature/Akademik/JadwalSarprasCollisionTest.php`.
  - [x] Jalankan `php artisan test --filter=JadwalSarprasCollisionTest` (4 passed, 11 assertions).
  - [x] Jalankan seluruh suite `php artisan test --filter=Akademik` (71 passed, 189 assertions).
  - [x] Jalankan seluruh suite `php artisan test --filter=JadwalPelajaran` (52 passed, 152 assertions).

- [x] **Langkah 6: Verifikasi Manual di Browser**
  - [x] Buka browser subagent $\to$ login sebagai Admin $\to$ navigasi ke halaman Jadwal Pelajaran.
  - [x] Filter Tahun Ajaran 2026/2027, Semester Genap, Kelas 1-A SDIT.
  - [x] Buka form modal slot jadwal $\to$ verifikasi dropdown Ruangan Sarpras terisi ruangan aktif.
  - [x] Screenshot dan video WebP tersimpan di artifacts.

- [x] **Langkah 7: Serah Terima & Handoff Log**
  - [x] Tulis `.agents/logs/2026-08-17-1030-akademik-01-jadwal-dan-sarpras.md`.
