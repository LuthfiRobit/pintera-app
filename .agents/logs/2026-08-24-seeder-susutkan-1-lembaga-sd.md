# Handoff Log: Penyusutan Seeder Demo ke 1 Lembaga (SDIT PINTERA) — Data Volume Realistis

**Tanggal**: 2026-08-24  
**Branch**: `rbac-v2`  
**Referensi Dokumen**:
- Spec: `.agents/specs/2026-08-24-seeder-susutkan-1-lembaga-sd.md`
- Plan: `.agents/plans/2026-08-24-seeder-susutkan-1-lembaga-sd.md`
- Skill Standar: `.agents/skills/seeder-standard/SKILL.md`

---

## 1. Ringkasan Pekerjaan (Apa yang Dikerjakan)

Sub-project ini menyusutkan data seed demo multi-lembaga (KB, TK, SD, SMP) menjadi **hanya 1 lembaga: SDIT PINTERA (NPSN `20223333`, kode `SDITPTR`)** dengan kedalaman data realistis (seperti data riil sekolah dasar aktif, bukan token/placeholder minimal).

Seluruh 20 task dalam implementation plan telah dieksekusi secara berurutan dan disiplin:

1. **Task 1 (`4139fdb`)**: `LembagaSeeder.php` — Menyisakan hanya 1 blok `firstOrCreate` untuk SDIT PINTERA (NPSN `20223333`), menghapus KB, TK, dan SMP.
2. **Task 2 & 3 (`35f9c87`)**:
   - `GuruSeeder.php` — Retarget ke SDIT (`20223333`), menyediakan 12 wali kelas (`guru_kelas` untuk Kelas 1-A s.d. 6-B) + 3 guru mapel spesialis (total 15 guru).
   - `UserSeeder.php` — Memperbaiki isolasi `Admin Yayasan` (query langsung ke `Yayasan::firstOrFail()` tanpa keterikatan lembaga KB), mendaftarkan 15 akun guru SD + pimpinan SD.
3. **Task 4 (`0592e08`)**: `KelasSeeder.php` — Mengembangkan `$kelasConfigs['SD']` menjadi 12 rombel (2 rombel per tingkat: Kelas 1-A & 1-B s.d. Kelas 6-A & 6-B).
4. **Task 5 (`7458943`)**: `SiswaSeeder.php` — Menaikkan volume ke 28 siswa per kelas (~336 total siswa aktif), menggunakan generator kombinasi 30 nama depan x 16 nama belakang realistis, membersihkan map email peninggalan KB/TK/SMP.
5. **Task 6 (`112dad2`)**: `OrangTuaKaryawanSeeder.php` — Mengangkat `hendra.gunawan@demo.test` sebagai `guru_bk` SD, memindahkan 4 data orang tua + 1 demo login `ortu.sd@demo.test` ke siswa SD, menghapus fungsi lintas-lembaga dan demo KB/TK.
6. **Task 7 (`0e32843`)**: `GelombangJalurSeeder.php` — Retarget gelombang/jalur PPDB ke SDIT (`20223333`).
7. **Task 8 (`23a6151`)**: `PendampinganSeeder.php` — Memindahkan 7 skenario siklus kasus lengkap (diajukan, menunggu_consent, ditugaskan, berjalan, eskalasi, selesai, dan kasus ringan tugas batch harian) ke SDIT, menghapus skenario lintas-jenjang.
8. **Task 9**: Verifikasi Gate Checkpoint 1 — `migrate:fresh --seed` zero-crash terbukti lulus.
9. **Task 10 (`fe54695`)**: `EssentialUserSeeder.php` — Retarget KB ke SD (`20223333`), memperbarui label email `*.sd@demo.test`.
10. **Task 11 (`48b2b90`)**: `KehadiranSdmDemoSeeder.php` — Retarget dari SMP ke SD, menggunakan guru SD (`hendra.gunawan`, `maya.anggraini`, `taufik.hidayat`) dan akun administrasi SD.
11. **Task 12 (`a2510ac`)**: `SarprasPengadaanDemoSeeder.php` — Retarget ke SD, mengubah identitas ruang menjadi "Ruang Kelas 1-A" & "Ruang Kelas 1-B", menyesuaikan kapasitas dan kuantitas aset batch menjadi 28 unit dengan perhitungan harga proporsional.
12. **Task 13 (`b574eae`)**: `KeuanganDemoSeeder.php` — Menyisakan `ortu.sd@demo.test` (NIK `0000019901850001`), menghapus parent KB/TK.
13. **Task 14 (`cdd82dd`)**: `AkunPendaftarSeeder.php` — Menghapus entri pendaftar KB, TK, SMP; menyisakan `pendaftar.sd@demo.test`.
14. **Task 15 (`d978d7b`)**: `AsesmenSeeder.php` — Detail treatment asesmen sumatif SD Kelas 1-A untuk mapel Matematika (Bilangan Cacah) dan IPAS (Pengenalan Ekosistem) oleh guru spesialis.
15. **Task 16 (`3bab821`)**: `JadwalPelajaranSeeder.php` — Detail treatment jadwal pelajaran silang Kelas 1-A dan 1-B untuk Matematika dan IPAS.
16. **Task 17 (`c86a251`)**: `SesiPembelajaranSeeder.php` — Detail treatment sesi pembelajaran terlaksana kemarin untuk Kelas 1-A dengan materi SD dasar.
17. **Task 18 (`d6b3c2a`)**: `NilaiSiswaSeeder.php` — Detail treatment nilai siswa Kelas 1-A untuk 28 siswa dengan formula deterministik variatif (rentang 70-99) dan variasi catatan guru.
18. **Task 19 (`31eac9b`)**: `PresensiSeeder.php` — Presensi deterministik untuk 28 siswa per kelas dengan rasio sakit, izin, dan hadir yang realistis.
19. **Task 20**: Verifikasi Final Menyeluruh & Handoff Log.

---

## 2. Bukti Verifikasi

### A. Kebersihan String & NPSN Lama (Grep Audit)
```bash
grep -rn "20223311\|20223322\|20223344\|KBITPTR\|TKITPTR\|SMPITPTR" database/seeders/*.php
```
**Hasil**: **0 match** (bersih total dari seluruh file seeder).

### B. Audit Email Domain Lama
```bash
User::where('email', 'like', '%.kb@demo.test')->orWhere('email', 'like', '%.tk@demo.test')->orWhere('email', 'like', '%.smp@demo.test')->count()
```
**Hasil**: **0 user** (tidak ada akun peninggalan jenjang lama).

### C. Volume Data Database Aktual Pasca `migrate:fresh --seed`
- **Lembaga**: `1` (SDIT PINTERA)
- **Kelas**: `24` (12 rombel aktif x 2 tahun ajaran yang di-seed)
- **Siswa**: `336` (28 siswa x 12 kelas pada tahun ajaran aktif)
- **Guru**: `15` (12 wali kelas + 3 guru mapel spesialis)
- **Orang Tua**: `5` (4 orang tua siswa + 1 akun demo login `ortu.sd@demo.test`)
- **Kasus Pendampingan**: `7` (lengkap seluruh variasi status kasus pada jenjang SD)

### D. Eksekusi `migrate:fresh --seed`
- Status: **Sukses tanpa exception/warning**.
- Waktu eksekusi: ~6-8 detik, semua 53 seeder selesai dengan status `DONE`.

---

## 3. Keputusan Penting yang Diambil

1. **Struktur Multi-Tahun Ajaran**: `TahunAjaranSeeder` membuat 2 Tahun Ajaran (2025/2026 dan 2026/2027), sehingga total entri `Kelas` adalah 24 (12 rombel x 2 TA). Volume `Siswa` terfokus 336 pada tahun ajaran aktif.
2. **Kapasitas Sarpras**: Kapasitas ruang teori di `SarprasPengadaanDemoSeeder` diselaraskan menjadi 28 kursi/meja mengikuti kuota siswa rombel SD 28 siswa.
3. **Deterministik Seeding**: Perhitungan presensi modulo dan formula nilai siswa `70 + (($i * 7 + 13) % 30)` memastikan data demo bervariasi secara realistis tanpa menghasilkan 'flaky' diff pada pengujian berulang.

---

## 4. Catatan untuk Review Manusia / Claude

- **Full Test Suite**: Sesuai arahan eksplisit user ("tak perlu menjalankan full test suite, jelaskan saja di handoff kalau belum dijalankan"), perintah `php artisan test` **tidak dijalankan** pada sesi ini untuk efisiensi waktu, karena seluruh seeder telah diverifikasi sukses secara langsung via database seeding runtime (`migrate:fresh --seed`) dan audit query tinker.
- **Git State**:
  - Branch: `rbac-v2`
  - Semua perubahan seeder telah di-commit secara modular dan terverifikasi di branch ini.
  - File `RoleSeeder.php` dan `RolePermissionAssignmentSeeder.php` **tidak disentuh** sama sekali (sesuai batasan scope).
