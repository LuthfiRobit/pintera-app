# 📝 Handoff Log Sub-Task 01: Fondasi Domain Akademik & Integrasi Jadwal-Sarpras Anti-Bentrok

- **Document ID / Slug:** `2026-08-17-1030-akademik-01-jadwal-dan-sarpras`
- **Spec File:** [`.agents/specs/2026-08-17-1030-akademik-01-jadwal-dan-sarpras.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-17-1030-akademik-01-jadwal-dan-sarpras.md)
- **Plan File:** [`.agents/plans/2026-08-17-1030-akademik-01-jadwal-dan-sarpras.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-17-1030-akademik-01-jadwal-dan-sarpras.md)
- **Handoff Log File:** `.agents/logs/2026-08-17-1030-akademik-01-jadwal-dan-sarpras.md`
- **Tanggal & Waktu:** 17 Agustus 2026, 10:38 WIB
- **Status:** ✅ COMPLETED & VERIFIED (100% Tests Pass, Browser Verified)

---

## 1. Apa yang Dikerjakan
1. **Scaffolding Domain Akademik (`App\Domains\Akademik\`):**
   - Membuat struktur direktori domain: `Actions/Jadwal/`, `DataTransferObjects/`, `Enums/`, `Models/`, `Services/`.
   - Membuat `JadwalPelajaranData` DTO (`final readonly class`).
2. **Implementasi Atomic Actions Anti-Bentrok:**
   - `CreateJadwalPelajaranAction`: Validasi anti-bentrok ruangan via `ValidateRoomClashAction`, anti-bentrok guru, dan slot jam kelas.
   - `UpdateJadwalPelajaranAction`: Validasi anti-bentrok dengan pengecualian self-record ID.
   - `DuplicateJadwalAction`: Duplikasi jadwal antar kelas/semester dengan bypass otomatis slot yang bentrok.
3. **FormRequests & Thin Controller Refactor:**
   - Membuat `StoreJadwalPelajaranRequest`, `UpdateJadwalPelajaranRequest`, `DuplicateJadwalRequest`.
   - Merefaktor `Admin\JadwalPelajaranController` menjadi Thin Controller berbasis Domain Actions.
4. **Integrasi Antarmuka Blade UI:**
   - Menambahkan dropdown `Ruangan Sarpras` pada `create.blade.php`, `edit.blade.php`, dan AJAX `_modal-form.blade.php`.
   - Menampilkan badge ruangan pada `_daftar.blade.php`.
   - Memperbarui kartu jadwal pada `_matrix-roster.blade.php` dengan susunan vertikal: Waktu, Mata Pelajaran, Guru Pengampu, dan Ruangan Sarpras.
5. **Pengujian & Verifikasi:**
   - Membuat feature test `tests/Feature/Akademik/JadwalSarprasCollisionTest.php` (5 passed, 16 assertions).
   - Menjalankan seluruh test `php artisan test --filter=JadwalPelajaran` (52 passed, 152 assertions).
   - Menjalankan seluruh test `php artisan test --filter=Akademik` (71 passed, 189 assertions).
   - Melakukan verifikasi interaktif di browser dan mandiri oleh user.

---

## 2. Keputusan Penting yang Diambil
- **Isolasi Tenant Ruangan Sarpras:** Query opsi ruangan pada `JadwalPelajaranController` (pada `index`, `create`, `edit`, dan AJAX modal) telah dibatasi secara ketat hanya mengambil ruangan milik lembaga terkait (`lembaga_id == $targetLembagaId`) ditambah ruangan fasilitas bersama (`is_shared == true`) dengan status aktif (`is_aktif == true`). Ruangan dari lembaga/unit lain tidak bocor ke antarmuka.
- **Fallback Ruangan Otomatis:** Jika input `ruangan_id` tidak dipilih/kosong, sistem otomatis mengambil default dari `kelas.ruangan_id` (Home Room) kelas tersebut.
- **Support PAUD / Tematik:** Properti `mata_pelajaran_id` pada `JadwalPelajaranData` disetel nullable sehingga kelas TK/PAUD yang menggunakan model tematik / sentra tanpa nama mapel spesifik tetap dapat menggunakan engine jadwal ini.
- **Graceful Duplicate Handling:** Pada aksi duplikasi jadwal, slot yang bentrok ruangan/guru dilewati (*skipped*) tanpa membatalkan slot lain yang valid, dan total sukses/dilewati dilaporkan secara detail kepada pengguna.

---

## 3. Hal yang Perlu Direview / Status Git
- **Branch Aktif:** `akademik-v2`
- **Zero Regression & Full Scoping:** 5 test collision & tenant isolation + 52 test Jadwal Pelajaran + 71 test Akademik lulus 100% (Total: 72 passed, 0 failures).
- **Kesiapan Sub-Task Berikutnya:** Siap melanjutkan ke **Sub-Task 02: Manajemen Perangkat Mengajar (RPP / Modul Ajar)**.
