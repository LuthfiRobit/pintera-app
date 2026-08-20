# Migrasi 3 Modul Akademik Tersisa ke Domains\Akademik — Spec

## 1. Latar Belakang

Analisis sidebar-by-sidebar (2026-08-20) menemukan bahwa dari 8 item menu sidebar grup "Akademik", 5 sudah bermigrasi ke `Domains\Akademik` dan 3 belum:

| Menu Sidebar | Controller | Status |
|---|---|---|
| Pengaturan Akademik | `PengaturanAkademikController` + `KalenderAkademikController` | ⚠️ Belum |
| Pola Jam | `PolaJamController` + `JamPelajaranController` | ⚠️ Belum |
| Kenaikan Kelas | `KenaikanKelasController` | ⚠️ Belum |
| Jadwal Pelajaran, RPP, Komponen Penilaian, Rekap Rapor, Persetujuan Rapor | — | ✅ Sudah |

Standar arsitektur wajib: [`.agents/skills/laravel-feature-standard/SKILL.md`](file:///d:/laragon/www/pintera-app/.agents/skills/laravel-feature-standard/SKILL.md) — **SEMUA keputusan desain di spec ini tunduk pada standar itu**; bagian di bawah hanya memetakan standar itu ke 3 modul spesifik ini, tidak menggantikannya.

Pola migrasi yang SUDAH terbukti dipakai di modul yang sudah bermigrasi (diverifikasi lewat isi nyata `Domains\Akademik\Actions\Jadwal\CreateJadwalPelajaranAction`): Eloquent model LAMA yang sudah dipakai luas lintas modul (`Lembaga`, `Kelas`, `Siswa`, `TahunAjaran`, `Semester`) **tetap di `app/Models/`**, diimpor dari domain. Model yang BARU atau blast-radius-nya kecil (dipakai eksklusif oleh 1 fitur) **dipindah ke `Domains\<Domain>\Models\`**. Keputusan pindah/tidak untuk 3 modul ini didasarkan pada audit pemakaian nyata (`grep`), bukan asumsi — lihat §2-4.

## 2. Modul 1 — Kalender & Pengaturan Akademik

### 2.1 Cakupan
- `app/Http/Controllers/Admin/PengaturanAkademikController.php` — `index()`, `updateHariAktif()`
- `app/Http/Controllers/Admin/KalenderAkademikController.php` — `store()`, `update()`, `destroy()`
- `app/Models/KalenderAkademik.php` — **DIPINDAH** ke `app/Domains/Akademik/Models/KalenderAkademik.php`. Audit pemakaian: hanya 8 file, semua eksklusif fitur ini (controller x2, test x3, factory, service, service test).
- `app/Services/KalenderAkademikResolver.php` — **DIPINDAH** ke `app/Domains/Akademik/Services/KalenderAkademikResolver.php`. Sudah dipakai oleh `Domains\Akademik\Services\SesiPembelajaranGenerator` dan `SesiTematikGenerator` — memindahkannya menghilangkan dependency lintas-batas domain yang sudah ada sekarang (domain service memanggil service lama di luar domain).
- `App\Models\Lembaga` — **TETAP** di `app/Models/`. Dipakai 382 file lintas seluruh aplikasi (audit `grep`, 2026-08-20). Modul ini hanya menulis/membaca field `hari_libur_mingguan` miliknya.

### 2.2 Action Baru (`Domains\Akademik\Actions\Kalender\`)
- `UpdateHariAktifLembagaAction` — logika dari `PengaturanAkademikController::updateHariAktif()` (baris 40-63 versi saat ini): terima `Lembaga` + array hari aktif (0-6), hitung `hari_libur_mingguan` sebagai komplemen, update.
- `CreateKalenderAkademikAction` — logika dari `KalenderAkademikController::store()` + `tumpangTindih()` (baris 16-60, 123-132): validasi tumpang-tindih rentang tanggal dalam scope yang sama (nasional vs per-lembaga), lalu create.
- `UpdateKalenderAkademikAction` — logika dari `KalenderAkademikController::update()` (baris 62-88): guard kepemilikan lembaga (404 kalau beda lembaga), guard permission nasional, update.
- `DeleteKalenderAkademikAction` — logika dari `KalenderAkademikController::destroy()` (baris 90-110): guard yang sama, delete.

### 2.3 DTO Baru (`Domains\Akademik\DataTransferObjects\`)
- `HariAktifLembagaData` — readonly, field: `array $hariAktif` (int 0-6).
- `KalenderAkademikData` — readonly, field: `string $tanggal`, `?string $tanggalSelesai`, `string $nama`, `string $tipe`, `?string $keterangan`, `bool $berlakuNasional`.

### 2.4 Perilaku
**Zero behavior change.** Semua guard, pesan error, dan urutan validasi yang ada sekarang (termasuk perbedaan response JSON vs redirect berdasar `$request->wantsJson()`) dipertahankan persis. `KalenderAkademikResolver` dipindah tanpa mengubah 1 baris logic pun (murni ganti namespace + lokasi file).

## 3. Modul 2 — Pola Jam

### 3.1 Cakupan
- `app/Http/Controllers/Admin/PolaJamController.php` — semua 8 method (`index`, `create`, `store`, `edit`, `update`, `destroy`, `assignKelas`, `duplicate`)
- `app/Http/Controllers/Admin/JamPelajaranController.php` — semua 4 method (`store`, `edit`, `update`, `destroy`)
- `app/Models/PolaJam.php` — **DIPINDAH** ke `app/Domains/Akademik/Models/PolaJam.php`
- `app/Models/JamPelajaran.php` — **DIPINDAH** ke `app/Domains/Akademik/Models/JamPelajaran.php` (1 agregat dengan `PolaJam`, sesuai keputusan eksplisit — meski dipakai di 33 file, mayoritas test/seeder/factory yang tinggal update `use` statement)
- `App\Models\Kelas` — **TETAP** di `app/Models/`. Titik sentuh: relasi `belongsTo(PolaJam::class)` (baris 36 `Kelas.php`) dan eager-load di `KelasController.php` (Data Induk) — KEDUANYA cuma butuh update `use` statement import (`PolaJam` sekarang datang dari `Domains\Akademik\Models\PolaJam`), TIDAK ada logic yang berubah.

### 3.2 Action Baru (`Domains\Akademik\Actions\PolaJam\`)
- `CreatePolaJamAction` — dari `store()` (baris 35-56): validasi nama, set `lembaga_id` untuk aktor yayasan, create.
- `UpdatePolaJamAction` — dari `update()` (baris 65-76).
- `DeletePolaJamAction` — dari `destroy()` (baris 78-93): 2 guard dipertahankan persis — (a) `kelas()->exists()`, (b) `jamPelajaran()->whereHas('jadwalPelajaran')->exists()`.
- `AssignKelasToPolaJamAction` — dari `assignKelas()` (baris 95-121): validasi kelas ditemukan semua, validasi kelas & pola jam 1 lembaga, unlink kelas lama + link kelas baru.
- `DuplicatePolaJamAction` — dari `duplicate()` (baris 123-148): clone `PolaJam` + semua `JamPelajaran` anaknya dalam `DB::transaction`.

### 3.3 Action Baru (`Domains\Akademik\Actions\PolaJam\` — sub JamPelajaran, satu folder yang sama karena 1 agregat)
- `CreateJamPelajaranAction` — dari `JamPelajaranController::store()` + `tabrakanSlot()` (baris 18-63, 122-129): loop per hari, skip yang tabrakan, kumpul `$berhasil`/`$dilewati`, return keduanya untuk pesan status.
- `UpdateJamPelajaranAction` — dari `update()` (baris 76-103).
- `DeleteJamPelajaranAction` — dari `destroy()` (baris 105-120): guard `jadwalPelajaran()->exists()` dipertahankan.

### 3.4 DTO Baru
- `PolaJamData` — `string $nama`, `?int $lembagaId`.
- `AssignKelasData` — `int $polaJamId`, `array $kelasIds`.
- `JamPelajaranData` — `int $polaJamId`, `array $hari` (untuk create, multi-hari sekaligus), `int $urutan`, `string $label`, `string $jamMulai`, `string $jamSelesai`, `bool $isPelajaran`.

### 3.5 Perilaku
**Zero behavior change.** Semua pesan error Indonesia, guard, dan format respons dipertahankan persis kata per kata.

## 4. Modul 3 — Kenaikan Kelas

### 4.1 Cakupan
- `app/Http/Controllers/Admin/KenaikanKelasController.php` — `index()`, `store()`
- **Tidak ada model yang dipindah** — orkestrasi murni lintas `Siswa`, `Kelas`, `TahunAjaran`, `Semester`, `JadwalPelajaran` (semua tetap `app/Models/`, semua sudah dipakai lintas modul lain).

### 4.2 Action Baru (`Domains\Akademik\Actions\KenaikanKelas\`)
- `ProsesKenaikanKelasAction` — dari `store()` (baris 45-93): terima DTO berisi array mapping (`kelasLamaId => ['tindakan', 'kelasBaruId', 'salinJadwal', 'semesterTujuanId']`), untuk tiap mapping:
  - `tindakan === 'lulus'`: update semua siswa di kelas itu jadi `StatusSiswa::Lulus`, `kelas_id = null`.
  - `tindakan === 'naik'`: validasi kelas tujuan ada & 1 lembaga (abort 404 kalau tidak), validasi kelas tujuan BUKAN tahun ajaran yang sama (`DomainException` kalau sama — dipertahankan persis), update `kelas_id` semua siswa di kelas lama.
  - Kalau `salinJadwal` true + `semesterTujuanId` diisi: panggil sub-logic salin jadwal (§4.3).
  - Seluruh proses PER-MAPPING (siswa naik/lulus) tetap dalam SATU `DB::transaction()` — kegagalan validasi kelas tujuan (`DomainException`) tetap membatalkan SELURUH proses seperti sekarang (**tidak berubah**, ini beda dari salin-jadwal §4.3 yang justru sengaja TIDAK all-or-nothing).

### 4.3 Perubahan Perilaku — Salin Jadwal (KEPUTUSAN EKSPLISIT, bukan zero-behavior-change)

**Sebelum:** `KenaikanKelasController::salinJadwal()` (baris 95-109 versi saat ini) menyalin `JadwalPelajaran` langsung pakai `firstOrCreate()`, TANPA validasi bentrok ruangan/guru.

**Sesudah:** memanggil `Domains\Akademik\Actions\Jadwal\CreateJadwalPelajaranAction` yang sudah ada (validasi bentrok ruangan via `ValidateRoomClashAction` + bentrok guru), DENGAN aturan penanganan kegagalan yang BERBEDA dari jalur create manual:

- Jalur create manual (sudah ada, TIDAK berubah): 1 baris bentrok → `ValidationException` → seluruh submit form gagal (memang cuma 1 baris per submit, jadi wajar).
- Jalur salin-saat-kenaikan-kelas (BARU): loop tiap baris jadwal lama, panggil `CreateJadwalPelajaranAction::execute()` per baris **dibungkus try-catch lokal** (BUKAN membiarkan exception menembus ke `DB::transaction()` luar `ProsesKenaikanKelasAction`). Baris yang gagal (`ValidationException` tertangkap) di-skip, dikumpulkan ke array `$jadwalGagal` (format: nama kelas lama, label jam pelajaran, alasan gagal). Baris yang berhasil tetap tersalin. Siswa TETAP naik/lulus terlepas dari hasil salin-jadwal.
- Pesan status setelah submit HARUS melaporkan jumlah baris yang gagal, kalau ada — format: `"Kenaikan kelas berhasil diproses. N jadwal tidak tersalin karena bentrok: [detail]."` (kalau `$jadwalGagal` kosong, pesan tetap seperti sekarang: `"Kenaikan kelas berhasil diproses."`).

### 4.4 DTO Baru
- `KenaikanKelasData` — `array $mapping` (struktur sama seperti `$data['mapping']` tervalidasi sekarang).

### 4.5 Kenapa Ini Beda dari Prinsip "Zero Behavior Change" di Modul 1 & 2
Modul 1 & 2 murni relokasi kode (guard/pesan/urutan dipertahankan identik). Modul 3 SENGAJA mengubah perilaku salin-jadwal karena user (2026-08-20, brainstorming session) memutuskan menutup celah bentrok-diam-diam yang sudah dikonfirmasi ada di kode saat ini — bukan sekadar preferensi desain, tapi keputusan sadar untuk memperbaiki behavior sambil migrasi, dengan mitigasi risiko (skip-dan-laporkan, bukan rollback total) yang juga sudah disepakati eksplisit.

## 5. Prinsip Arsitektur Wajib (dari `laravel-feature-standard`, ringkasan penerapan)

- **Controller thin:** tiap method controller di 3 modul ini, setelah migrasi, hanya: validasi input (`$request->validate()` inline tetap boleh dipakai untuk kasus sederhana ini — FormRequest terpisah TIDAK wajib kalau rule validasinya singkat dan tidak dipakai ulang, sesuai §23 SKILL.md "jangan tambah layer tanpa alasan") → bangun DTO → panggil Action → return view/redirect/json.
- **Action:** 1 use-case per Action, method `execute()`, terima DTO (bukan `Request`), pakai `DB::transaction()` untuk mutasi multi-tabel.
- **DTO:** `final readonly class`, dengan named constructor `fromRequest()` atau dibangun langsung dari data tervalidasi di controller.
- **Model pindahan** (`KalenderAkademik`, `PolaJam`, `JamPelajaran`): HANYA `$fillable`, `casts()`, relationship (`belongsTo`/`hasMany`), local scope. TIDAK ada business logic method di model.
- **Tenant isolation:** semua guard kepemilikan lembaga yang sudah ada di controller SEKARANG (`abort(404)` kalau beda lembaga, dsb) dipertahankan persis — dipindah ke Action, bukan dihapus atau dilonggarkan.
- **Test:** setiap Action baru dan tiap perubahan perilaku (khusus §4.3) wajib test baru; test yang sudah ada untuk 3 controller ini (`PengaturanAkademikControllerTest`, `KalenderAkademikCrudTest`, `PolaJamCrudTest`, `JamPelajaranCrudTest`, `KenaikanKelasControllerTest`, dan test model `PolaJamTest`/`JamPelajaranTest`/`KalenderAkademikTest`) HARUS tetap hijau tanpa modifikasi assertion (kecuali test yang secara spesifik menguji celah bentrok-diam-diam di §4.3, yang assertion-nya MEMANG harus berubah karena perilakunya sengaja diubah).

## 6. Testing

- Per modul: test lama tetap hijau (bukti zero-behavior-change untuk Modul 1 & 2) + test baru untuk tiap Action (unit test Action, bukan cuma feature test lewat HTTP).
- Modul 3 tambahan wajib: test baru yang membuktikan (a) baris jadwal bentrok di-skip bukan membatalkan seluruh kenaikan kelas, (b) siswa tetap naik/lulus meski ada baris jadwal yang gagal disalin, (c) pesan status memuat daftar baris yang gagal.
- Full suite dijalankan sekali di akhir seluruh migrasi (3 modul), setelah user diberi kesempatan approve — pola yang sama dengan RBAC v2 dan FASE 5.1.

## 7. Di Luar Cakupan

- Model `Lembaga`, `Kelas`, `Siswa`, `TahunAjaran`, `Semester`, `JadwalPelajaran` — TIDAK dipindah, tetap di `app/Models/`.
- Migrasi route file (sudah selesai di FASE 5.1) — spec ini tidak menyentuh `routes/admin/akademik-master.php`, hanya isi controller yang di-`require` di dalamnya.
- Perbaikan celah bentrok lain di luar `salinJadwal` (mis. penguatan `CreateJadwalPelajaranAction` itu sendiri) — di luar scope, tidak disentuh.
- FASE 5.1 route restructuring dan RBAC v2 — sudah selesai sebelumnya, tidak bagian dari spec ini.

## 8. Asumsi

- Baseline kode yang dipetakan di spec ini adalah versi pada commit `c761cab` (HEAD `rbac-v2` saat spec ini ditulis). Kalau ada commit baru yang mengubah salah satu dari 4 controller / 3 model ini sebelum plan dieksekusi, isi & baris yang dikutip perlu diverifikasi ulang.
- `ValidateRoomClashAction` dan `CreateJadwalPelajaranAction` (dipakai ulang di §4.3) tidak diubah interface-nya oleh spec ini — dipanggil apa adanya.
