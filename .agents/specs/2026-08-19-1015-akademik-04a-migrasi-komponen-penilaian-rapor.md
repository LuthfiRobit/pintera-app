# 📋 Spec: Sub-Task 04a — Migrasi Komponen Penilaian, Asesmen, Nilai Siswa & Rapor ke Domain Akademik

- **Document ID / Slug:** `2026-08-19-1015-akademik-04a-migrasi-komponen-penilaian-rapor`
- **Master Plan File:** [`.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md) — bagian dari FASE 4 (Adaptive E-Rapor Engine), dipecah jadi 04a (sub-task ini) dan 04b (E-Rapor Engine baru: narasi TP, presensi, approval workflow, PDF berjenjang — menyusul setelah 04a selesai & direview).
- **Target Domain:** `App\Domains\Akademik\`
- **Tanggal & Waktu:** 19 Agustus 2026, 10:15 WIB
- **Status:** 🟡 SPEC DRAFT — menunggu review user sebelum lanjut ke Plan

---

## 1. Latar Belakang

Modul penilaian numerik (`KomponenPenilaian` → `Asesmen` → `NilaiSiswa`, plus rekap tertimbang di `Admin\RaporController`) sudah ada dan berfungsi, tapi dibangun sebelum pola domain-oriented (`app/Domains/[Domain]/{Actions,DataTransferObjects,Enums,Models,Services}`) diterapkan di codebase ini — sama seperti kondisi Jurnal KBM sebelum Sub-Task 03a.

Sub-Task 04b (E-Rapor Engine: kalkulasi nilai akhir berbobot, narasi capaian kompetensi, workflow approval, 4 template PDF berjenjang) akan membutuhkan logic kalkulasi nilai (`RaporCalculationService`) sebagai fondasi. Kalau fondasi ini dibangun di atas model yang masih tinggal di `app/Models/` (di luar domain), maka domain baru akan bergantung pada kode di luar domain-nya sendiri — retak arsitektur yang sama yang dulu terjadi sebelum 03a. Sub-task ini menyelesaikan itu dulu: migrasi struktural murni, tanpa fitur bisnis baru, supaya 04b bisa dibangun di atas fondasi yang bersih.

Survei kode existing (dilakukan sesi ini) menemukan:
- **Duplikasi logic signifikan**: `Admin\KomponenPenilaianController` dan `Guru\KomponenPenilaianController` punya logic create/update/delete + validasi total-bobot-maks-100% yang **identik** (hanya beda scope query — Admin lihat semua, Guru hanya mapel yang diajar sendiri). Ini kandidat kuat untuk disatukan jadi satu Action yang dipakai kedua controller.
- **Tenant isolation sudah baik di level model** — `KomponenPenilaian`, `Asesmen`, `NilaiSiswa` semua pakai `BelongsToTenant`, dan ada test baseline (`tests/Feature/AkademikTenantScopeTest.php`) yang memverifikasi auto-derive `lembaga_id` + tidak bisa resolve row lembaga lain by ID. Ini fondasi yang solid, tapi **belum pernah diaudit di level controller** (query manual seperti `MataPelajaran::find($id)`, `Semester::find($id)` di beberapa tempat — perlu dipastikan semuanya benar-benar terikat scope tenant aktor, bukan cuma "kebetulan aman" karena global scope).
- **Kolom `predikat` di `nilai_siswa`** sudah ada di migrasi (komentar: untuk "narrative-style grading, misal PAUD aspek perkembangan") tapi **tidak dipakai** oleh `updateNilai()` manapun saat ini — kolom vestigial, disiapkan untuk kebutuhan masa depan yang belum diimplementasikan. Dibiarkan sebagaimana adanya di sub-task ini (bukan scope untuk mengisi/memakainya).
- Yang jadi masalah murni **struktural**: file tersebar di `app/Models/` (bukan `app/Domains/Akademik/`); ke-4 controller (`Admin\KomponenPenilaianController`, `Guru\KomponenPenilaianController`, `Guru\AsesmenController`, `Admin\RaporController`) punya logic bisnis inline tanpa Action/DTO; `RaporController::hitungRekap()` adalah private method yang tidak reusable dari luar controller — persis yang dibutuhkan 04b sebagai service.

## 2. Scope

### In Scope

1. **Pindahkan model ke domain:**
   - `app/Models/KomponenPenilaian.php` → `app/Domains/Akademik/Models/KomponenPenilaian.php`
   - `app/Models/Asesmen.php` → `app/Domains/Akademik/Models/Asesmen.php`
   - `app/Models/NilaiSiswa.php` → `app/Domains/Akademik/Models/NilaiSiswa.php`
   - `app/Enums/JenisAsesmen.php` → `app/Domains/Akademik/Enums/JenisAsesmen.php`
   - Update seluruh `use` statement yang mereferensikan class-class ini (controller, test, factory, seeder, relasi model lain seperti `Kelas`, `MataPelajaran`, `Semester` yang punya relasi ke `Asesmen`/`NilaiSiswa`/`KomponenPenilaian`).

2. **Satukan logic Komponen Penilaian (Admin + Guru) jadi Action bersama** — menghapus duplikasi:
   - `app/Domains/Akademik/DataTransferObjects/KomponenPenilaianData.php`
   - `app/Domains/Akademik/Actions/Penilaian/CreateKomponenPenilaianAction.php` (termasuk validasi total-bobot ≤ 100%)
   - `app/Domains/Akademik/Actions/Penilaian/UpdateKomponenPenilaianAction.php` (termasuk validasi total-bobot ≤ 100%, exclude diri sendiri)
   - `app/Domains/Akademik/Actions/Penilaian/DeleteKomponenPenilaianAction.php` (guard "sudah dipakai di asesmen/nilai")
   - `app/Http/Requests/Akademik/StoreKomponenPenilaianRequest.php` & `UpdateKomponenPenilaianRequest.php` (dengan `toDTO()`)
   - Refactor `Admin\KomponenPenilaianController` dan `Guru\KomponenPenilaianController` jadi Thin Controller yang sama-sama memanggil Action ini (query index/opsi/create tetap beda per controller karena scope-nya memang beda — itu bukan duplikasi, itu authorization boundary).

3. **Ekstrak logic Asesmen ke Action:**
   - `app/Domains/Akademik/DataTransferObjects/AsesmenData.php`
   - `app/Domains/Akademik/Actions/Penilaian/CreateAsesmenAction.php` (create asesmen + attach komponen + populate baris `NilaiSiswa` kosong, dibungkus `DB::transaction()` — logic yang sudah ada di `AsesmenController::store()` saat ini, hanya dipindah)
   - `app/Domains/Akademik/Actions/Penilaian/SimpanNilaiSiswaAction.php` (batch update/create nilai per asesmen, dibungkus `DB::transaction()` — logic dari `updateNilai()` saat ini)
   - `app/Http/Requests/Akademik/StoreAsesmenRequest.php` & `UpdateNilaiSiswaRequest.php` (dengan `toDTO()`)
   - Refactor `Guru\AsesmenController` jadi Thin Controller.

4. **Ekstrak `RaporCalculationService` dari `RaporController::hitungRekap()`:**
   - `app/Domains/Akademik/Services/RaporCalculationService.php` — method publik `hitungRekapKelas(Kelas $kelas, Semester $semester): array` (signature & isi return array identik dengan `hitungRekap()` saat ini: `siswaList`, `mapelList`, `rekapNilai`, `classAvg`, `highestScore`), supaya **langsung reusable** oleh Sub-Task 04b tanpa perubahan.
   - Refactor `Admin\RaporController` jadi Thin Controller yang memanggil service ini.

5. **Audit tenant-scoping di ke-4 controller** (bukan cuma model — titik-titik seperti `MataPelajaran::find($id)`, `Semester::find($id)`, `TahunAjaran::find($id)`, `Kelas::find($id)` di controller/Action baru):
   - Verifikasi setiap lookup manual benar-benar terikat `TenantScope` (bukan `withoutGlobalScopes()` tanpa alasan) dan tidak ada celah di mana ID dari tenant lain bisa lolos ke validasi/pembuatan data.
   - Kalau ditemukan celah, perbaiki + tambah test regresi cross-tenant (pola dari `tests/Feature/Guru/JurnalKbmTenantScopeTest.php` / Sub-Task 03c).

6. **Test:** update namespace/import di seluruh test existing yang mereferensikan model-model ini (regression-net murni, assertion tidak berubah): `tests/Feature/Admin/{KomponenPenilaianCrudTest,RaporControllerTest}.php`, `tests/Feature/Guru/{KomponenPenilaianControllerTest,AsesmenControllerTest}.php`, `tests/Feature/AkademikTenantScopeTest.php`, `tests/Unit/{KomponenPenilaianSeederTest,NilaiSiswaSeederTest,AsesmenSeederTest}.php`, `tests/Unit/Models/{NilaiSiswaTest,AsesmenTest}.php`. Tambah test baru untuk `RaporCalculationService` (unit) dan untuk temuan audit tenant-scoping (kalau ada gap yang diperbaiki).

### Out of Scope (sengaja ditunda)

- Fitur E-Rapor baru apa pun (narasi TP otomatis, `PengajuanRapor`/`CatatanWaliKelas`, approval workflow, 4 template PDF berjenjang, integrasi presensi ke rapor) — semua ini **Sub-Task 04b**.
- Perubahan route path, nama route, atau permission gate — tetap identik dengan yang ada sekarang (backward compat, tidak ada breaking change ke frontend/test URL).
- Perubahan struktur tabel/migrasi (`komponen_penilaian`, `asesmen`, `asesmen_komponen_penilaian`, `nilai_siswa`) — kolom `predikat` yang belum terpakai dibiarkan apa adanya.
- Perubahan perilaku bisnis apa pun pada alur existing (mis. validasi baru, UI baru) — di luar scope, tidak diminta.
- Migrasi `Admin\RaporController`'s Blade views (`resources/views/admin/rapor/*`, `pdf/rekap-rapor.blade.php`) ke lokasi lain — tetap di tempatnya, hanya controller & logic kalkulasi yang direfactor.

## 3. Asumsi

- `MataPelajaran`, `Semester`, `TahunAjaran`, `Kelas`, `Siswa` (dependency dari modul ini) sudah cukup matang tenant-scope-nya (dipakai luas di modul lain yang sudah diaudit sebelumnya) — sub-task ini tidak mengaudit model-model tersebut ulang, hanya titik pemakaiannya di 4 controller yang dimigrasi.
- Tidak ada kode lain di luar file yang disebutkan di atas yang mereferensikan `App\Models\{KomponenPenilaian,Asesmen,NilaiSiswa}` atau `App\Enums\JenisAsesmen` secara langsung — perlu diverifikasi ulang dengan `grep` menyeluruh di awal tahap eksekusi sebelum memindahkan file (bukan diasumsikan begitu saja, sama seperti disiplin yang diterapkan di 03a).
- `RaporCalculationService::hitungRekapKelas()` yang diekstrak di sub-task ini akan dipanggil apa adanya oleh 04b tanpa perubahan signature — kalau 04b nanti ternyata butuh signature/return-shape berbeda, itu keputusan yang diambil saat brainstorming 04b, bukan diantisipasi sekarang (YAGNI).

## 4. Kriteria Penerimaan (Acceptance Criteria)

- [ ] Tidak ada file tersisa di `app/Models/{KomponenPenilaian,Asesmen,NilaiSiswa}.php` atau `app/Enums/JenisAsesmen.php` — semua sudah pindah ke `app/Domains/Akademik/`.
- [ ] `Admin\KomponenPenilaianController` dan `Guru\KomponenPenilaianController` sama-sama memanggil `CreateKomponenPenilaianAction`/`UpdateKomponenPenilaianAction`/`DeleteKomponenPenilaianAction` yang sama — tidak ada lagi duplikasi logic validasi bobot antara keduanya.
- [ ] `Guru\AsesmenController` adalah Thin Controller: hanya `authorize()` → FormRequest/route-model-binding → panggil Action → return view/redirect.
- [ ] `CreateAsesmenAction` dan `SimpanNilaiSiswaAction` membungkus mutasi multi-baris dalam `DB::transaction()`.
- [ ] `RaporCalculationService::hitungRekapKelas()` mengembalikan hasil identik dengan `RaporController::hitungRekap()` versi lama untuk input yang sama (dibuktikan lewat test yang membandingkan skenario yang sama).
- [ ] Route lama (`admin.komponen-penilaian.*`, `guru.komponen-penilaian.*`, `guru.asesmen.*`, `admin.rapor.*`) tetap ada dan berfungsi identik secara perilaku — tidak ada rename.
- [ ] Audit tenant-scoping selesai dilakukan pada ke-4 controller; setiap gap yang ditemukan sudah diperbaiki dan punya test regresi cross-tenant.
- [ ] Seluruh test existing yang di-migrasi namespace-nya tetap hijau, ditambah test baru untuk `RaporCalculationService` dan temuan audit (kalau ada).
- [ ] `php artisan test` full suite tetap 0 gagal (baseline dicek ulang di awal eksekusi — bukan angka dari sesi sebelumnya, karena jumlah test terus bertambah).

## 5. Rencana Pengujian

```bash
php artisan test --filter=KomponenPenilaian
php artisan test --filter=Asesmen
php artisan test --filter=Rapor
php artisan test --filter=Akademik
php artisan test
```

Verifikasi manual: login sebagai Admin → kelola Komponen Penilaian (tambah/edit/hapus, cek validasi bobot 100%) → cetak Rekap Rapor PDF. Login sebagai Guru → kelola Komponen Penilaian mapel sendiri → buat Asesmen baru → isi nilai siswa → cek tersimpan. Bandingkan hasil rekap PDF sebelum & sesudah migrasi untuk kelas+semester yang sama — harus identik angkanya.
