# 🎯 Spesifikasi Sub-Task 01: Fondasi Domain Akademik & Integrasi Jadwal-Sarpras Anti-Bentrok

- **Document ID / Slug:** `2026-08-17-1030-akademik-01-jadwal-dan-sarpras`
- **Spec File:** `.agents/specs/2026-08-17-1030-akademik-01-jadwal-dan-sarpras.md`
- **Plan File:** `.agents/plans/2026-08-17-1030-akademik-01-jadwal-dan-sarpras.md`
- **Handoff Log File:** `.agents/logs/2026-08-17-1030-akademik-01-jadwal-dan-sarpras.md`
- **Tanggal & Waktu:** 17 Agustus 2026, 10:30 WIB
- **Fokus:** Scaffolding Domain Akademik, DTO & Actions Jadwal, Integrasi Sarpras `ruangan_id` & Validasi Ganda Anti-Bentrok (Ruangan & Guru).

---

## 1. Tujuan Sub-Task

1. Membangun struktur direktori domain `App\Domains\Akademik\` (Actions, DTOs, Enums, Models, Services) sesuai [`laravel-feature-standard`](file:///d:/laragon/www/pintera-app/.agents/skills/laravel-feature-standard/SKILL.md).
2. Menghubungkan dropdown `ruangan_id` pada antarmuka jadwal pelajaran (otomatis mengambil default dari *Home Room* `kelas.ruangan_id`).
3. Mengintegrasikan validasi ganda anti-bentrok:
   - **Anti-Bentrok Ruangan:** Memanfaatkan [`ValidateRoomClashAction`](file:///d:/laragon/www/pintera-app/app/Domains/Sarpras/Actions/ValidateRoomClashAction.php).
   - **Anti-Bentrok Guru:** Memvalidasi guru tidak mengajar di kelas lain pada `jam_pelajaran_id` & `semester_id` yang sama.
4. Merefaktor [`Admin\JadwalPelajaranController`](file:///d:/laragon/www/pintera-app/app/Http/Controllers/Admin/JadwalPelajaranController.php) menjadi Thin Controller berbasis Action tanpa merusak kontrak response AJAX partial `_daftar.blade.php`.
5. Memastikan 67 existing test suite tetap lulus 100% dan menambah unit/feature test untuk anti-bentrok.

---

## 2. Struktur File & Perubahan

### A. Domain Layer (`App\Domains\Akademik\`)
- `DataTransferObjects/JadwalPelajaranData.php` &mdash; Readonly DTO typed data jadwal.
- `Actions/Jadwal/CreateJadwalPelajaranAction.php` &mdash; Eksekusi validasi collision & insert jadwal.
- `Actions/Jadwal/UpdateJadwalPelajaranAction.php` &mdash; Update data jadwal dengan pengecualian self-record.
- `Actions/Jadwal/DuplicateJadwalAction.php` &mdash; Duplikasi jadwal antar kelas/semester dengan validasi bentrok.

### B. HTTP Layer & FormRequests
- `app/Http/Requests/Akademik/StoreJadwalPelajaranRequest.php`
- `app/Http/Requests/Akademik/UpdateJadwalPelajaranRequest.php`
- `app/Http/Requests/Akademik/DuplicateJadwalRequest.php`
- Refactor `app/Http/Controllers/Admin/JadwalPelajaranController.php` (mendelegasikan logika ke Actions).

### C. View & UI Layer
- `resources/views/admin/jadwal-pelajaran/create.blade.php` &mdash; Tambah dropdown Ruangan Sarpras (default terisi ruangan kelas).
- `resources/views/admin/jadwal-pelajaran/edit.blade.php` &mdash; Dropdown Ruangan Sarpras.
- `resources/views/admin/jadwal-pelajaran/_daftar.blade.php` &mdash; Tampilkan badge ruangan (misal: "R. 101" / "Lab Komputer").

---

## 3. Kriteria Penerimaan (*Acceptance Criteria*)

1. Menyimpan jadwal dengan ruangan yang sudah terisi di jam & semester yang sama $\to$ Gagal dengan pesan error: `"Ruangan sudah digunakan oleh kelas lain pada jam ini."`
2. Menyimpan jadwal dengan guru yang sudah mengajar di kelas lain pada jam & semester yang sama $\to$ Gagal dengan pesan error: `"Guru yang bersangkutan sudah memiliki jadwal mengajar di kelas lain pada jam ini."`
3. Saat membuka form create jadwal untuk Kelas X-A, dropdown Ruangan otomatis terpilih sesuai `kelas.ruangan_id` jika tersedia.
4. Duplikasi jadwal antar kelas berjalan sukses jika tidak ada bentrok guru/ruangan.
5. Seluruh test suite (`php artisan test --filter=JadwalPelajaran`) lulus 100%.
