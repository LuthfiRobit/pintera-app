# 📋 Spec: Sub-Task 03b — Mode Tematik/Harian (KB/TK/SD/SLB/TPA/SPS) untuk Jurnal KBM & Presensi

- **Document ID / Slug:** `2026-08-18-2200-akademik-03b-mode-tematik-kbtksd`
- **Master Plan File:** [`.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md) — bagian dari FASE 3 (Jurnal KBM Adaptif & Presensi Siswa Multi-Jenjang), menyusul Sub-Task 03a (migrasi mode Sesi Mapel, sudah SELESAI).
- **Terkait Spec Sebelumnya:** [`.agents/specs/2026-08-18-1651-akademik-03a-migrasi-jurnal-presensi.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-18-1651-akademik-03a-migrasi-jurnal-presensi.md)
- **Target Domain:** `App\Domains\Akademik\`
- **Tanggal & Waktu:** 18 Agustus 2026, 22:00 WIB
- **Status:** 🟡 SPEC DRAFT — menunggu review user sebelum lanjut ke Plan

---

## 1. Latar Belakang

Sub-Task 03a memindahkan & merapikan mode **Sesi Mapel** (presensi per jam pelajaran, dipakai jenjang SMP/SMA/SMK) ke `app/Domains/Akademik/`. Mode **Tematik/Harian** (satu sesi guru-kelas per hari, dipakai jenjang KB/TK/SD dan sejenisnya) sengaja ditunda ke sub-task ini, sesuai keputusan eksplisit saat brainstorming 03a.

Audit codebase saat brainstorming sub-task ini menemukan skema & sebagian besar layer aplikasi **sudah disiapkan** untuk mode ini sejak 03a, tanpa direalisasikan penuh:
- Kolom `sesi_pembelajaran.jadwal_pelajaran_id` dan `sesi_pembelajaran.mata_pelajaran_id` sudah **nullable** sejak migrasi awal.
- `GenerateSesiHarianAction::execute()` sudah query kelas di mana guru adalah `wali_kelas_guru_id` (`orWhere('wali_kelas_guru_id', $guru->id)`), tapi hasil query itu diperlakukan sama seperti kelas yang diajar sebagai guru mapel — diteruskan ke `SesiPembelajaranGenerator` yang mengandalkan `JadwalPelajaran` (yang tidak ada untuk kelas tematik), sehingga secara diam-diam tidak menghasilkan sesi apa pun untuk guru wali kelas.
- View `index.blade.php` dan `show.blade.php` (dari 03a) sudah null-safe untuk `mataPelajaran` (fallback tampilan "(tanpa mapel)").

Sub-task ini melengkapi bagian yang hilang: generator sesi harian untuk mode Tematik, dan mekanisme penentuan mode berdasarkan jenjang lembaga.

## 2. Keputusan Desain (dari sesi brainstorming)

1. **Kriteria pemisahan mode** — whitelist-negatif: `bentuk_pendidikan` **IN** (`SMP`, `SMA`, `SMK`) → mode Sesi Mapel; **SEMUA** nilai lain (`KB`, `TPA`, `SPS`, `TK`, `SD`, `SLB`, dan jenjang baru apa pun yang mungkin ditambah ke enum `bentuk_pendidikan` di masa depan) → mode Tematik. Dipilih karena default ke mode yang lebih sederhana lebih aman untuk nilai enum baru yang belum dikenal kode.
2. **Reuse model existing** (`SesiPembelajaran`/`Presensi`), bukan model terpisah — `jadwal_pelajaran_id` dan `mata_pelajaran_id` diisi `NULL` untuk sesi tematik, `guru_id` diambil dari `Kelas.wali_kelas_guru_id`. Alasan: skema sudah nullable sejak awal (bukan kebetulan), dan `PresensiAggregationService`/`RekapKehadiranController` (hasil 03a) otomatis berfungsi untuk kedua mode tanpa modifikasi apa pun.
3. **Kewajiban menutup kekurangan Opsi A** — unique index DB `['jadwal_pelajaran_id', 'tanggal']` tidak melindungi banyak baris `NULL` dari duplikasi (perilaku standar MySQL). Proteksi duplikasi untuk sesi tematik dilakukan di level Action (`firstOrCreate` dengan key eksplisit `kelas_id` + `tanggal` + `jadwal_pelajaran_id = null`), bukan mengandalkan constraint DB semata.
4. **Aksesor eksplisit** `SesiPembelajaran::isTematik(): bool` ditambahkan supaya makna `NULL` pada `jadwal_pelajaran_id` jelas bagi pembaca kode berikutnya, bukan terlihat seperti data hilang.
5. **UX index tetap satu halaman** — `guru.jurnal-kbm.index` dipakai kedua mode (list berisi 1 item untuk guru wali kelas tematik, banyak item untuk guru mapel). Tidak ada redirect otomatis, tidak ada route baru.
6. **Kelas tanpa wali (`wali_kelas_guru_id` NULL`)** — generator skip, tidak membuat sesi, konsisten dengan pola existing "skip generate kalau prasyarat data belum lengkap" (sama seperti mode Sesi Mapel skip kalau `pola_jam_id` NULL).

## 3. Scope

### In Scope
1. **Enum baru** `App\Domains\Akademik\Enums\ModePembelajaran` — case `SesiMapel`, `Tematik`; static factory `fromBentukPendidikan(string $bentukPendidikan): self` mengimplementasikan whitelist-negatif keputusan #1.
2. **Service baru** `App\Domains\Akademik\Services\SesiTematikGenerator` — method `generateUntukTanggal(Kelas $kelas, CarbonInterface $tanggal, int $semesterId): ?SesiPembelajaran`:
   - Skip (return `null`) kalau libur (reuse `KalenderAkademikResolver`, pola sama seperti `SesiPembelajaranGenerator`).
   - Skip (return `null`) kalau `$kelas->wali_kelas_guru_id` NULL.
   - `firstOrCreate` satu `SesiPembelajaran` per kelas per tanggal (`jadwal_pelajaran_id` NULL, `mata_pelajaran_id` NULL, `guru_id` = `wali_kelas_guru_id`, `jam_mulai`/`jam_selesai` = jam operasional harian kelas — lihat Asumsi).
   - Kalau baru dibuat (`wasRecentlyCreated`), buat `Presensi` default `hadir` untuk semua siswa aktif di kelas (pola sama seperti `SesiPembelajaranGenerator::buatSesi()`).
3. **Modifikasi** `GenerateSesiHarianAction::execute()` — untuk tiap `$kelas` hasil query existing, tentukan `ModePembelajaran::fromBentukPendidikan($kelas->lembaga->bentuk_pendidikan)` lalu panggil `SesiPembelajaranGenerator` atau `SesiTematikGenerator` sesuai hasilnya.
4. **Aksesor** `SesiPembelajaran::isTematik(): bool` (`return $this->jadwal_pelajaran_id === null;`).
5. Test baru:
   - `Tests\Unit\Domains\Akademik\ModePembelajaranTest` — 9 nilai `bentuk_pendidikan` termapping benar.
   - `Tests\Unit\Services\SesiTematikGeneratorTest` — generate sukses, skip wali NULL, skip libur, tidak duplikat saat dipanggil ulang di hari sama.
   - `Tests\Feature\Akademik\JurnalKbmAdaptiveTest` — guru wali kelas KB/TK/SD login → sesi tematik ter-generate otomatis di `index` → isi jurnal+presensi via `show`/`update` → tersimpan; guru mapel SMP/SMA/SMK di lembaga lain tidak terpengaruh (regression-net lintas mode).

### Out of Scope (sengaja tidak termasuk)
- Perubahan pada `Guru\Akademik\JurnalKbmController`, `UpdateJurnalPresensiRequest`, `RecordJurnalDanPresensiAction`, atau kedua Blade view (`index`/`show`) — semuanya sudah mode-agnostic sejak 03a, tidak perlu disentuh.
- Field spesifik kurikulum PAUD (tema mingguan, checklist perkembangan anak, dll.) — di luar scope, textarea `materi` generik dipakai sama seperti mode Sesi Mapel. Kalau dibutuhkan nanti, bisa jadi kolom nullable tambahan atau tabel anak terpisah tanpa membongkar arsitektur ini (dicatat sebagai catatan desain, bukan pekerjaan sub-task ini).
- Perubahan pada `PresensiAggregationService`/`RekapKehadiranController` — sudah otomatis berfungsi untuk kedua mode tanpa modifikasi (konsekuensi dari keputusan reuse model), tidak ada task eksplisit untuk ini.
- Migrasi skema baru — semua kolom yang dibutuhkan sudah ada (nullable sejak 03a/awal).

## 4. Asumsi

- Jam operasional harian (`jam_mulai`/`jam_selesai`) untuk sesi tematik diambil dari slot pertama dan slot terakhir `is_pelajaran = true` milik `pola_jam_id` kelas pada hari itu (sumber: `JamPelajaran`, sama tabel yang dipakai `PolaJamSeeder`/`JamPelajaranSeeder`) — bukan hardcode. Kalau kelas tidak punya `pola_jam_id`, generator skip (return `null`), sama seperti mode Sesi Mapel.
- `Kelas.wali_kelas_guru_id` tetap sumber kebenaran tunggal untuk menentukan guru pemilik sesi tematik (sama seperti keputusan di 03a untuk `RekapKehadiranController`).
- Semester aktif ditentukan lewat pola yang sama dengan 03a (`Semester.status_aktif = true` per `tahun_ajaran_id` kelas).
- Tidak ada kode lain di luar file yang disebutkan di atas yang perlu tahu perbedaan mode secara eksplisit — perlu diverifikasi ulang dengan `grep` menyeluruh terhadap `bentuk_pendidikan`, `SesiPembelajaranGenerator`, dan `GenerateSesiHarianAction` di awal tahap eksekusi, bukan diasumsikan begitu saja.

## 5. Kriteria Penerimaan (Acceptance Criteria)

- [ ] `ModePembelajaran::fromBentukPendidikan()` mengembalikan `SesiMapel` untuk `SMP`/`SMA`/`SMK`, dan `Tematik` untuk keenam nilai enum `bentuk_pendidikan` lainnya (`KB`, `TPA`, `SPS`, `TK`, `SD`, `SLB`).
- [ ] Guru yang menjadi `wali_kelas_guru_id` di kelas berjenjang Tematik, saat membuka `guru.jurnal-kbm.index`, mendapat tepat 1 sesi ter-generate untuk hari itu (kecuali libur atau kelas belum punya wali).
- [ ] Memanggil generator dua kali untuk kelas & tanggal yang sama tidak menghasilkan duplikat baris `sesi_pembelajaran`.
- [ ] Guru mapel di lembaga bermode Sesi Mapel (SMP/SMA/SMK) tidak terpengaruh sama sekali oleh perubahan ini (regression-net).
- [ ] `SesiPembelajaran::isTematik()` mengembalikan `true` untuk sesi tematik, `false` untuk sesi mapel.
- [ ] Guru wali kelas Tematik bisa isi jurnal+presensi lewat form yang sama (`show`/`update`) tanpa error, tanpa perubahan pada Controller/FormRequest/View.
- [ ] `php artisan test` full suite tetap 0 gagal (baseline saat ini: 1753 passed / 0 failed, per handoff log 03a).

## 6. Rencana Pengujian

```bash
php artisan test --filter=ModePembelajaran
php artisan test --filter=SesiTematikGenerator
php artisan test --filter=JurnalKbmAdaptive
php artisan test tests/Feature/Guru/JurnalKbmControllerTest.php tests/Feature/Guru/RekapKehadiranControllerTest.php
```

Full suite (`php artisan test`) dijalankan sekali di akhir, di tahap final whole-branch review, dan hanya setelah ditanyakan dulu ke user (kebijakan baru, lihat catatan proses sesi ini).

Verifikasi manual: login sebagai guru yang jadi wali kelas TK/SD → buka menu "Jurnal & Presensi" → pastikan 1 sesi hari ini muncul tanpa badge mapel → isi materi + presensi → submit → cek tersimpan. Login sebagai wali kelas yang sama → buka "Rekap Kehadiran" → pastikan angka H/I/S/A/T ikut terhitung dari sesi tematik yang baru diisi.
