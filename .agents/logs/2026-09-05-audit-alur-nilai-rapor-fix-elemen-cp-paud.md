# Handoff Log: Audit Alur Bisnis Nilai s.d. Rapor & Fix Jalur Elemen CP (PAUD)

- **Tanggal**: 2026-09-05
- **Branch**: `akademik-v2`
- **Sifat pekerjaan**: Audit investigatif langsung (bukan lewat spec/plan/kickoff formal) — dipicu permintaan user untuk audit mendalam alur bisnis "pengisian nilai sampai rapor", lalu laporan user sendiri soal UI "Tambah TP"/"Buat Asesmen" yang masih menampilkan pilihan "Jenis Subjek Penilaian" yang seharusnya otomatis mengikuti jenjang lembaga.
- **Base Commit**: `65fb4124` (`docs(akademik): update PETA_PENGEMBANGAN.md -- rekap sesi 4-5 September & TD-003 selesai`)
- **Head Commit**: (belum di-commit saat log ini ditulis — lihat bagian Status Git di bawah)

---

## 1. Konteks & Metode Audit

Audit dilakukan menyusuri satu alur bisnis penuh dari skema database sampai frontend: `komponen_penilaian`/`asesmen`/`nilai_siswa` (skema via `mcp__laravel-boost__database-schema`) → Model (`Asesmen`, `KomponenPenilaian`, `NilaiSiswa`) → Action (`CreateAsesmenAction`, `SimpanNilaiSiswaAction`, `CreateKomponenPenilaianAction`) → Controller (`Guru\AsesmenController`, `Guru\KomponenPenilaianController`) → FormRequest → Blade view. Beberapa klaim diverifikasi lewat **test reproduksi yang benar-benar dijalankan** (dibuat sementara di `tests/Feature/Guru/ZZZRepro*Test.php`, dijalankan, lalu dihapus setelah bukti didapat — tidak masuk commit).

## 2. Temuan

1. **Dropdown Kelas kosong total untuk guru wali kelas PAUD** — `Guru\AsesmenController::create()` menyumber `kelasList` HANYA dari `JadwalPelajaran::where('guru_id', ...)`. Kelas mode Tematik (PAUD: KB/TPA/SPS/TK) tidak pernah punya baris `JadwalPelajaran` sama sekali (dikonfirmasi lewat `SesiTematikGenerator` — penugasan gurunya lewat `Kelas.wali_kelas_guru_id`). **Dibuktikan lewat test reproduksi yang gagal** sebelum fix (dropdown kosong, nama kelas guru sendiri tidak muncul di halaman).
2. **Dropdown Semester kosong pada form "Tambah TP"** — `Guru\KomponenPenilaianController::create()` punya bug akar yang sama persis (semesterIds hanya dari JadwalPelajaran). **Dibuktikan lewat test reproduksi kedua**.
3. **`komponenList` (checklist TP) di form Asesmen tidak pernah query `subjek_type=elemen_cp`** — query aslinya `KomponenPenilaian::where('subjek_type', 'mata_pelajaran')->whereIn('subjek_id', $mapelIds)` tanpa cabang PAUD sama sekali, jadi checklist TP selalu kosong untuk guru PAUD walau #1 sudah diperbaiki.
4. **Tidak ada cek kepemilikan (`wali_kelas_guru_id`) untuk cabang `elemen_cp` di `AsesmenController::store()`** — cabang `mata_pelajaran` sudah benar (cek `JadwalPelajaran`), cabang `elemen_cp` sama sekali tidak dicek, artinya guru mana pun di lembaga yang sama bisa membuat/menilai Asesmen PAUD untuk kelas yang bukan miliknya (lewat request langsung, karena dropdown UI normal sebelum fix #1 tidak akan pernah menawarkan opsi itu).
5. **(Laporan user) `subjek_type` adalah pilihan manual (radio) di 2 form guru**, padahal seharusnya otomatis mengikuti `bentuk_pendidikan` lembaga aktor — bukan preferensi guru. Duplikasi whitelist PAUD (`['KB','TPA','SPS','TK']`) ditemukan di 6+ lokasi terpisah di kode (2 di antaranya persis di form yang sedang diperbaiki).

## 3. Perbaikan

1. **`BentukPendidikan::isPaud(): bool`** (baru) — sumber tunggal untuk menentukan apakah suatu jenjang memakai jalur Elemen CP, dipakai menggantikan whitelist array manual di titik-titik yang disentuh.
2. **`subjek_type` sekarang derivasi server, tidak pernah dipercaya dari klien** — `StoreAsesmenRequest`/`StoreKomponenPenilaianSendiriRequest` menimpa `subjek_type` di `prepareForValidation()` berdasarkan `$this->user()->lembaga->bentuk_pendidikan`, apa pun yang dikirim form/klien. Radio toggle dihapus dari kedua view create, diganti hidden input tetap; dropdown subjek yang tidak relevan sekarang beneran tidak dirender (`@if` server-side, bukan `x-show` Alpine).
3. **`Guru\AsesmenController::create()`**: `kelasList`/`semesterIds` sekarang union dari `JadwalPelajaran` (mata_pelajaran) DAN `Kelas::where('wali_kelas_guru_id', $guru->id)` (elemen_cp/Tematik). `komponenList` sekarang bercabang sesuai `$subjekType` yang sudah diresolusi.
4. **`Guru\KomponenPenilaianController::create()`**: `semesterIds` union yang sama (tahun ajaran dari kelas wali).
5. **`Guru\AsesmenController::store()`**: tambah cabang `else` — cek `Kelas::where('id', $data['kelas_id'])->where('wali_kelas_guru_id', $guru->id)->exists()` untuk `elemen_cp`, mirror pola `mata_pelajaran`.

## 4. Test

- 7 test baru: dropdown kelas/semester PAUD terisi, create+grading Asesmen elemen_cp end-to-end, penolakan 403 utk kelas bukan wali sendiri, pembuktian `subjek_type` kiriman klien diabaikan (di `AsesmenControllerTest.php` dan `KomponenPenilaianElemenCpUiTest.php`).
- 18 test lama diperbaiki — sebelumnya diam-diam bergantung pada `Lembaga::factory()`'s `bentuk_pendidikan` yang **random di antara 9 nilai termasuk PAUD** (`LembagaFactory.php:20`), padahal skenario testnya jelas-jelas alur mata_pelajaran. Sekarang eksplisit `'bentuk_pendidikan' => 'SD'`. Tanpa fix ini, kombinasi random PAUD sesekali akan bikin test-test lama gagal secara flaky.
- Test "shows the subjek_type radio options..." diganti namanya + assersinya jadi "derives subjek_type from bentuk_pendidikan..., without a manual toggle" — mencerminkan perilaku baru (bukan sekadar `assertSee` field, tapi cek `assertViewHas` + memastikan `type="radio"` tidak ada lagi).
- Hasil akhir: **302 passed (813 assertions)** untuk `tests/Feature/Guru` + `tests/Feature/Akademik` + `tests/Feature/Admin/KomponenPenilaianCrudTest.php`. Pint bersih.

## 5. Status Git (belum commit saat log ditulis)

```
M app/Domains/Akademik/Enums/BentukPendidikan.php
M app/Http/Controllers/Guru/AsesmenController.php
M app/Http/Controllers/Guru/KomponenPenilaianController.php
M app/Http/Requests/Akademik/StoreAsesmenRequest.php
M app/Http/Requests/Akademik/StoreKomponenPenilaianSendiriRequest.php
M resources/views/portals/guru/akademik/asesmen/create.blade.php
M resources/views/portals/guru/akademik/komponen-penilaian/create.blade.php
M tests/Feature/Guru/AsesmenControllerTest.php
M tests/Feature/Guru/AsesmenDiagnostikFormatifUsabilityTest.php
M tests/Feature/Guru/KomponenPenilaianControllerTest.php
M tests/Feature/Guru/KomponenPenilaianElemenCpUiTest.php
```

## 6. Di Luar Cakupan / Belum Disentuh

- **`Guru\KomponenPenilaianController::edit()`** masih meneruskan `bentukPendidikan` ke view padahal `edit.blade.php` tidak memakainya sama sekali (dead variable pre-existing) — tidak disentuh karena di luar apa yang dilaporkan/ditemukan rusak.
- **4 lokasi lain** yang masih hardcode whitelist PAUD (`resources/views/admin/lembaga/index.blade.php`, `_form.blade.php`, `resources/views/portals/lembaga/akademik/komponen-penilaian/create.blade.php`, `Guru\RaporController.php`) TIDAK diretrofit ke `BentukPendidikan::isPaud()` — di luar cakupan form yang dilaporkan user, kandidat pembersihan lanjutan kalau file-file itu disentuh lagi.
- Sisa alur (kalkulasi rekap rapor, submit → verifikasi → persetujuan → cetak PDF) sudah di-scan di audit yang sama tapi tidak ditemukan temuan baru — sudah tertutup oleh audit-audit sebelumnya di sesi ini (lihat `PETA_PENGEMBANGAN.md`).

## 7. Follow-up

- **Belum di-commit.** User belum konfirmasi jadi 1 commit atau dipecah; menunggu arahan.
- **`PETA_PENGEMBANGAN.md` belum diupdate** untuk temuan/fix sesi ini — perlu ditambahkan setelah commit.
