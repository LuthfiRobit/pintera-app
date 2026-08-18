# 📋 Spec: Sub-Task 03a — Migrasi Jurnal KBM & Presensi (Mode Sesi Mapel) ke Pola Domain Baru

- **Document ID / Slug:** `2026-08-18-1651-akademik-03a-migrasi-jurnal-presensi`
- **Master Plan File:** [`.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md) — bagian dari FASE 3 (Jurnal KBM Adaptif & Presensi Siswa Multi-Jenjang), dipecah jadi 03a (sub-task ini) dan 03b (mode Tematik/Harian KB-TK-SD, menyusul setelah 03a).
- **Target Domain:** `App\Domains\Akademik\`
- **Tanggal & Waktu:** 18 Agustus 2026, 16:51 WIB
- **Status:** 🟡 SPEC DRAFT — menunggu review user sebelum lanjut ke Plan

---

## 1. Latar Belakang

Modul Jurnal Mengajar & Presensi untuk mode Sesi Mapel (dipakai jenjang SMP/SMA/SMK — presensi per jam pelajaran, satu sesi per blok jadwal) sudah ada dan berfungsi (`Guru\SesiPembelajaranController`, model `SesiPembelajaran`/`Presensi`, service `SesiPembelajaranGenerator`), tapi dibangun sebelum pola domain-oriented (`app/Domains/[Domain]/{Actions,DataTransferObjects,Enums,Models,Services}`) diterapkan di codebase ini.

Audit arsitektur (lihat percakapan sesi ini, memakai skill `laravel-backend-audit`) menemukan:
- **Isolasi tenant sudah solid** — `SesiPembelajaran` sudah pakai `BelongsToTenant`, `Presensi` aman sebagai model anak (tidak ada route yang bind langsung), plus ada pengecekan kepemilikan per-guru eksplisit (`authorizeMilikGuru`) yang lebih ketat dari sekadar isolasi tenant. **Tidak ada temuan keamanan** di modul ini.
- **Test coverage sudah baik**, termasuk test tenant-isolation di level single-resource (`SesiPembelajaranTenantScopeTest.php`) — modul pertama yang diaudit di project ini yang sudah punya test kelas ini sejak awal.
- Yang jadi masalah murni **struktural**: file tersebar di `app/Models/`, `app/Enums/`, `app/Services/` (bukan `app/Domains/Akademik/`); controller punya logic bisnis inline (auto-generate sesi, mutasi jurnal+presensi) tanpa Action/DTO; mutasi multi-baris tidak dibungkus `DB::transaction()`; validasi masih `$request->validate()` inline, bukan FormRequest.

Sub-task ini murni migrasi struktural + satu fitur baru yang memang ada di checklist master plan (rekap kehadiran semesteran) — **tidak ada perubahan perilaku bisnis pada alur existing**.

## 2. Scope

### In Scope
1. Pindahkan file existing ke `app/Domains/Akademik/`:
   - `app/Models/SesiPembelajaran.php` → `app/Domains/Akademik/Models/SesiPembelajaran.php`
   - `app/Models/Presensi.php` → `app/Domains/Akademik/Models/Presensi.php`
   - `app/Enums/StatusSesiPembelajaran.php` → `app/Domains/Akademik/Enums/StatusSesiPembelajaran.php`
   - `app/Enums/StatusPresensi.php` → `app/Domains/Akademik/Enums/StatusPresensi.php`
   - `app/Services/SesiPembelajaranGenerator.php` → `app/Domains/Akademik/Services/SesiPembelajaranGenerator.php`
   - `app/Http/Controllers/Guru/SesiPembelajaranController.php` → `app/Http/Controllers/Guru/Akademik/JurnalKbmController.php`
   - Update seluruh `use` statement yang mereferensikan file-file ini (controller, test, factory, seeder).
2. Ekstrak logic dari controller ke Action + DTO + FormRequest:
   - `app/Domains/Akademik/Actions/Presensi/GenerateSesiHarianAction.php`
   - `app/Domains/Akademik/Actions/Presensi/RecordJurnalDanPresensiAction.php` (dibungkus `DB::transaction()`)
   - `app/Domains/Akademik/DataTransferObjects/JurnalPresensiData.php`
   - `app/Http/Requests/Akademik/UpdateJurnalPresensiRequest.php` (dengan `toDTO()`)
3. Fitur baru sesuai checklist master plan FASE 3.1/3.3 (rekap kehadiran semesteran):
   - `app/Domains/Akademik/Services/PresensiAggregationService.php` — hitung total Hadir/Sakit/Izin/Alpa per siswa per semester.
   - `app/Http/Controllers/Guru/Akademik/RekapKehadiranController.php` — halaman rekap untuk Wali Kelas.
4. Route: rename `guru.sesi.index/show/update` → `guru.jurnal-kbm.index/show/update`, tambah `guru.jurnal-kbm.rekap`. **Flat naming** (tidak nested `guru.akademik.*`) — konsisten dengan tetangga langsungnya di grup route yang sama (`guru.asesmen.*`, `guru.kasus.*`). Keputusan soal penyeragaman pola nesting rute lintas-domain (kalau diperlukan) sengaja ditunda ke **FASE 5.1** (Restrukturisasi Rute) di master plan, bukan diputuskan sepihak di sini.
5. Test: update namespace/route reference di 8 file test existing (isi assertion tidak berubah — jadi regression-net murni), tambah `PresensiAggregationServiceTest` (unit) dan `RekapKehadiranControllerTest` (feature, termasuk isolasi wali-kelas — hanya lihat kelas yang di-wali-i sendiri).

### Out of Scope (sengaja ditunda)
- Mode Tematik/Harian untuk KB/TK/SD — ini **Sub-Task 03b**, terpisah, menyusul setelah 03a selesai & direview.
- Perubahan perilaku bisnis apa pun pada alur existing (mis. isi presensi tanggal lampau, notifikasi wali murid) — di luar scope, tidak diminta.
- Restrukturisasi rute lintas-domain / route alias formal — itu FASE 5.1.
- Integrasi rekap kehadiran ke E-Rapor (FASE 4) — `PresensiAggregationService` dibangun agar bisa dipakai ulang nanti, tapi integrasi aktualnya bukan bagian sub-task ini.

## 3. Asumsi

- `Kelas.wali_kelas_guru_id` adalah sumber kebenaran untuk menentukan kelas yang di-wali-i seorang guru (dipakai di `RekapKehadiranController` untuk scoping).
- Semester aktif ditentukan lewat `Semester.status_aktif = true` per `tahun_ajaran_id` kelas (pola yang sama dipakai di `SesiPembelajaranController::index()` existing).
- Tidak ada kode lain di luar file yang disebutkan di atas yang mereferensikan `App\Models\SesiPembelajaran`/`App\Models\Presensi`/`App\Enums\StatusSesiPembelajaran`/`App\Enums\StatusPresensi` secara langsung — perlu diverifikasi ulang dengan `grep` menyeluruh di awal tahap eksekusi sebelum memindahkan file (bukan diasumsikan begitu saja).

## 4. Kriteria Penerimaan (Acceptance Criteria)

- [ ] Tidak ada file tersisa di `app/Models/SesiPembelajaran.php`, `app/Models/Presensi.php`, `app/Enums/StatusSesiPembelajaran.php`, `app/Enums/StatusPresensi.php`, `app/Services/SesiPembelajaranGenerator.php` — semua sudah pindah ke `app/Domains/Akademik/`.
- [ ] `Guru\Akademik\JurnalKbmController` adalah Thin Controller: hanya `authorize()` → FormRequest/route-model-binding → panggil Action → return view/redirect.
- [ ] `RecordJurnalDanPresensiAction` membungkus mutasi materi + presensi dalam satu `DB::transaction()`.
- [ ] Route lama (`guru.sesi.*`) sudah tidak ada; route baru (`guru.jurnal-kbm.*`) berfungsi identik secara perilaku dengan yang lama.
- [ ] `PresensiAggregationService` mengembalikan total Hadir/Sakit/Izin/Alpa yang benar untuk kasus: siswa dengan presensi campuran, siswa tanpa presensi sama sekali, kelas tanpa sesi di semester tsb.
- [ ] `RekapKehadiranController` hanya menampilkan kelas yang di-wali-i oleh guru yang login (403/kosong untuk kelas lain, termasuk kelas lembaga lain).
- [ ] Seluruh 8 test existing (dengan namespace/route ter-update) tetap hijau, ditambah test baru untuk `PresensiAggregationService` dan `RekapKehadiranController`.
- [ ] `php artisan test` full suite tetap 0 gagal (baseline saat ini: 1742 passed / 0 failed).

## 5. Rencana Pengujian

```bash
php artisan test --filter=Akademik
php artisan test --filter=Presensi
php artisan test --filter=SesiPembelajaran
php artisan test --filter=JurnalKbm
php artisan test --filter=RekapKehadiran
php artisan test
```

Verifikasi manual: login sebagai guru mapel SMP/SMA/SMK → buka Jurnal KBM → isi materi + presensi → submit → cek redirect & data tersimpan. Login sebagai wali kelas → buka Rekap Kehadiran → cek angka S/I/A sesuai data presensi yang sudah diisi.
