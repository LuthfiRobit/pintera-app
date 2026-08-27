# Fix Filter Jenis Asesmen di 3 Consumer Lain (Post-Priority 6 Audit) — Design Spec

**Tanggal**: 2026-08-27
**Branch**: `akademik-v2`
**Konteks**: Audit sistematis penuh (2 agent independen + database-schema Laravel Boost) terhadap SELURUH consumer `NilaiSiswa`/`Asesmen`/`hitungRekapKelas` di codebase, dipicu pertanyaan user pasca-Priority #6 (Asesmen Diagnostik & Formatif). Dikonfirmasi TIDAK ADA consumer tersembunyi lain di luar 3 yang di-fix spec ini — audit mencakup Controllers, Services, Actions, Requests, Jobs, Console, Notifications, Events, Listeners, Exports (Jobs/Notifications/Events/Listeners/Exports dikonfirmasi nihil referensi Asesmen/NilaiSiswa sama sekali).

---

## 1. Latar Belakang & Masalah

Priority #6 membuka `JenisAsesmen::DiagnostikKognitif`/`DiagnostikNonKognitif`/`Formatif` ke form guru, dengan `RaporCalculationService::hitungRekapKelas()` diberi filter `whereIn('jenis', JenisAsesmen::masukRapor())` sbg blocker supaya jenis non-rapor tidak mencemari rekap rapor. **Filter ini TIDAK diterapkan ke 3 consumer lain** yang juga membaca `NilaiSiswa`/`Asesmen` scr independen dari `RaporCalculationService`:

1. `CapaianKompetensiGenerator.php:35-38` — generator narasi capaian tertinggi/terendah, dipakai fitur "Generate Narasi" (guru, saat isi Catatan Wali Kelas) DAN ikut ke PDF rapor cetak resmi (via `GenerateNarasiPerkembanganAction` → `RaporPdfDataBuilder::build()`).
2. `DashboardStatsService::statistikProgressRaporKelas()` (baris 128-154) — progress bar "kesiapan rapor" di dashboard guru/wali kelas.
3. `Admin\DashboardController.php:134-139` (siswa) dan `:204-213` (orang tua) — widget "5 Nilai Terbaru".

Efek nyata: begitu guru memakai Asesmen Diagnostik/Formatif, (a) narasi di rapor CETAK RESMI bisa salah (mencampur skor diagnostik/formatif ke rata-rata capaian), (b) wali kelas bisa salah kira rapor sudah siap dicetak padahal nilai Sumatif belum lengkap, (c) siswa/orang tua bisa lihat nilai Diagnostik/Formatif tercampur di dashboard tanpa konteks.

## 2. Keputusan Desain

Semua 3 fix murni **menerapkan pola `JenisAsesmen::masukRapor()` yang sudah disetujui & established di `RaporCalculationService`** (Priority #6) — TIDAK ADA keputusan bisnis baru, TIDAK ADA constraint/wording baru. Konsisten dgn keputusan v1-minimal sebelumnya: Diagnostik/Formatif HANYA bisa dilihat guru lewat halaman `AsesmenController::show()` yang sudah ada — tidak pernah muncul di rapor, progress kesiapan rapor, atau dashboard siswa/orang tua.

## 3. Perbaikan #1 — `CapaianKompetensiGenerator.php`

Tambahkan import:

```php
use App\Domains\Akademik\Enums\JenisAsesmen;
```

Ubah query `$asesmenIds` (baris 35-38):

```php
        $asesmenIds = Asesmen::where('subjek_type', $subjek->getMorphClass())
            ->where('subjek_id', $subjek->getKey())
            ->where('semester_id', $semester->id)
            ->whereIn('jenis', JenisAsesmen::masukRapor())
            ->pluck('id');
```

Baris lain di method (filter `assessment_type=numeric` pada `$komponenList`, logic narasi tertinggi/terendah, ambang KKTP) TIDAK BERUBAH.

## 4. Perbaikan #2 — `DashboardStatsService::statistikProgressRaporKelas()`

Tambahkan import:

```php
use App\Domains\Akademik\Enums\JenisAsesmen;
```

Ubah query `$totalTerisi` (baris 142-145):

```php
        $totalTerisi = NilaiSiswa::whereHas('siswa', fn ($q) => $q->where('kelas_id', $kelas->id))
            ->whereHas('komponenPenilaian', fn ($q) => $q->where('semester_id', $semester->id))
            ->whereHas('asesmen', fn ($q) => $q->whereIn('jenis', JenisAsesmen::masukRapor()))
            ->whereNotNull('nilai_angka')
            ->count();
```

`$totalSiswa`, `$totalKomponen` (denominator), dan perhitungan `$totalSlot`/`persen` TIDAK BERUBAH.

## 5. Perbaikan #3 — `Admin\DashboardController.php` (siswa & orang tua)

Tambahkan import:

```php
use App\Domains\Akademik\Enums\JenisAsesmen;
```

**Branch siswa** (baris 134-139), tambahkan `whereHas` SEBELUM `->latest('id')->limit(5)`:

```php
                $nilaiTerbaru = NilaiSiswa::where('siswa_id', $siswa->id)
                    ->whereNotNull('nilai_angka')
                    ->whereHas('asesmen', fn ($q) => $q->whereIn('jenis', JenisAsesmen::masukRapor()))
                    ->with(['komponenPenilaian.subjek', 'asesmen.subjek'])
                    ->latest('id')
                    ->limit(5)
                    ->get();
```

**Branch orang tua** (baris 204-213), tambahkan `whereHas` dgn pola `withoutGlobalScope(TenantScope::class)` yang SAMA seperti relasi lain di query ini (konsisten dgn baris 207-209 yang sudah memakai pola itu):

```php
                $nilaiTerbaru = NilaiSiswa::withoutGlobalScope(TenantScope::class)->whereIn('siswa_id', $siswaIds)
                    ->whereNotNull('nilai_angka')
                    ->whereHas('asesmen', fn ($q) => $q->withoutGlobalScope(TenantScope::class)->whereIn('jenis', JenisAsesmen::masukRapor()))
                    ->with([
                        'komponenPenilaian' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->with(['subjek' => fn ($q2) => $q2->withoutGlobalScope(TenantScope::class)]),
                        'asesmen' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->with(['subjek' => fn ($q2) => $q2->withoutGlobalScope(TenantScope::class)]),
                        'siswa' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                    ])
                    ->latest('id')
                    ->limit(5)
                    ->get();
```

Baris lain di kedua branch (tagihan, presensi, dll) TIDAK BERUBAH.

**Penempatan `whereHas` SEBELUM `limit(5)` WAJIB** — supaya widget menampilkan 5 nilai Sumatif TERBARU (query-level filter lalu limit), bukan "ambil 5 dari semua jenis lalu kebetulan sebagian ke-filter jadi kurang dari 5".

## 6. Perbaikan #4 — Perbarui Docblock `JenisAsesmen::masukRapor()`

Komentar existing (`app/Domains/Akademik/Enums/JenisAsesmen.php`) menyatakan method ini "dipakai `RaporCalculationService::hitungRekapKelas()` sebagai satu-satunya filter" — pernyataan ini TIDAK AKURAT lagi setelah fix ini (sekarang dipakai di 4 tempat). Update jadi:

```php
    /**
     * Jenis asesmen yang secara semantik merupakan SUMBER PERHITUNGAN RAPOR.
     * Dipakai sbg filter di SEMUA tempat yang mengagregasi/menampilkan nilai
     * sbg representasi rapor: RaporCalculationService::hitungRekapKelas(),
     * CapaianKompetensiGenerator::generateNarasi(),
     * DashboardStatsService::statistikProgressRaporKelas(), dan widget
     * "Nilai Terbaru" siswa/orang tua di Admin\DashboardController.
     * Diagnostik dan Formatif SENGAJA tidak termasuk -- keduanya asesmen
     * untuk proses pembelajaran (pemetaan kesiapan belajar, penyesuaian
     * metode ajar), bukan komponen nilai rapor.
     *
     * Kalau menambah case baru ke enum ini di masa depan, WAJIB secara sadar
     * memutuskan apakah case itu masuk daftar ini atau tidak -- jangan
     * dibiarkan default masuk/keluar tanpa keputusan eksplisit. Kalau
     * menambah CONSUMER BARU yang membaca nilai sbg representasi rapor,
     * WAJIB pakai method ini sbg filter -- lihat riwayat 3 consumer yang
     * sempat lolos audit awal Priority #6 (27 Agustus 2026).
     *
     * @return array<int, self>
     */
```

## 7. Non-Goals (eksplisit di luar scope)

- Tidak menyentuh `RaporController`/`PersetujuanController`/`RaporPdfDataBuilder`/`pdf/rekap-rapor.blade.php` — sudah benar (mewarisi filter dari `RaporCalculationService`).
- Tidak menyentuh 9 lokasi `->asesmen()->exists()`/`->nilaiSiswa()->exists()` — bukan agregasi nilai, tidak relevan.
- Tidak menyentuh `Guru\AsesmenController` (index/show/insertOrIgnore) — scoped ke satu asesmen spesifik, jenis sudah given oleh konteks.
- Tidak ada migration/perubahan skema.
- Tidak memperluas audit ke area Akademik lain (Kenaikan Kelas, Jadwal Pelajaran, Pola Jam, Kalender Akademik, RPP) — di luar scope spec ini, keputusan user eksplisit menunda pembahasan itu sampai 3 fix ini selesai.

## 8. Testing (acceptance criteria wajib)

Pola test SAMA seperti `RaporCalculationJenisAsesmenTest.php` (Priority #6) — buktikan EXCLUSION sungguhan (data non-rapor benar-benar tersimpan dulu), bukan sekadar ketiadaan.

**8.1 — `CapaianKompetensiGeneratorTest.php` (BARU — dikonfirmasi belum ada file test utk service ini sama sekali)**:
- Siswa dgn 1 komponen numeric, nilai Sumatif terisi tinggi (mis. 90, di atas KKTP) DAN nilai Formatif rendah (mis. 40, di bawah KKTP) pada semester+subjek yang sama → `generateNarasi()` HARUS menghasilkan narasi "penguasaan sangat baik" (dari Sumatif), BUKAN narasi "perlu bimbingan" (yang akan muncul kalau Formatif ikut dihitung dan menurunkan rata-rata di bawah ambang).
- Assert dulu Asesmen Formatif + NilaiSiswa-nya benar-benar tersimpan sebelum assert narasi.

**8.2 — Test `DashboardStatsService::statistikProgressRaporKelas()`**:
- Kelas dgn 1 siswa, 1 komponen numeric, HANYA ada NilaiSiswa dari Asesmen Formatif (bukan Sumatif) → `$totalTerisi` HARUS `0` (bukan `1`), `persen` HARUS `0.0`.
- Kelas yang sama ditambah 1 NilaiSiswa dari Asesmen Sumatif → `$totalTerisi` jadi `1`.

**8.3 — Test `DashboardController` widget nilai terbaru (siswa & orang tua)**:
- Siswa dgn nilai Formatif (dibuat lebih baru/`latest('id')`) DAN nilai Sumatif (dibuat lebih lama) → `$nilaiTerbaru` yang dikembalikan HARUS berisi nilai Sumatif, BUKAN nilai Formatif — buktikan filter bekerja di level query, bukan cuma soal urutan waktu.
- Ulangi utk branch orang tua (lintas anak, dgn `withoutGlobalScope`).

## 9. Ringkasan Alur

```text
JenisAsesmen::masukRapor()  -- SATU-SATUNYA sumber kebenaran "jenis apa masuk rapor"
        │
        ├── RaporCalculationService::hitungRekapKelas()          [sudah benar]
        ├── CapaianKompetensiGenerator::generateNarasi()          [FIX #1]
        ├── DashboardStatsService::statistikProgressRaporKelas() [FIX #2]
        └── DashboardController (siswa & orang tua, nilaiTerbaru) [FIX #3]
```
