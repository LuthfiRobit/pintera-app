# Fix Filter Jenis Asesmen di 3 Consumer Lain Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tutup 3 celah filter jenis asesmen yang ditemukan audit sistematis pasca-Priority #6 — narasi rapor resmi, progress kesiapan rapor guru, dan widget nilai terbaru siswa/orang tua semuanya harus mengecualikan Diagnostik/Formatif, konsisten dgn `RaporCalculationService`.

**Architecture:** Tambahkan `whereIn('jenis', JenisAsesmen::masukRapor())` (langsung atau via `whereHas('asesmen', ...)`) di 3 titik query independen yang selama ini tidak ikut filter ini. Tidak ada perubahan struktur data/interface — murni penyempitan hasil query.

**Tech Stack:** Laravel 12.63.0, Pest v4, MySQL 8.0.30. Tidak ada migration, tidak ada perubahan skema.

## Global Constraints

- Semua 3 fix HANYA menambah filter `JenisAsesmen::masukRapor()` — TIDAK ADA perubahan logic/constraint/wording lain di file yang disentuh.
- Fix di `DashboardController` HARUS menempatkan `whereHas('asesmen', ...)` SEBELUM `->latest('id')->limit(5)` — supaya limit 5 diterapkan SETELAH filter (5 nilai Sumatif terbaru), bukan sebelum.
- Branch orang tua di `DashboardController` HARUS pakai `withoutGlobalScope(TenantScope::class)` pada relasi `asesmen` di dalam `whereHas`, konsisten dgn pola yang sudah ada di query yang sama (relasi lain di situ semuanya pakai pola ini).
- Tidak ada migration baru, tidak ada perubahan skema, tidak memperluas audit ke area Akademik lain (Kenaikan Kelas, Jadwal Pelajaran, dst — di luar scope, ditunda sesuai keputusan user).

---

## Task 1: Fix `CapaianKompetensiGenerator` — Narasi Rapor Resmi

**Files:**
- Modify: `app/Domains/Akademik/Services/CapaianKompetensiGenerator.php`
- Test: `tests/Unit/Services/CapaianKompetensiGeneratorTest.php` (BARU — belum ada file test sama sekali utk service ini)

**Interfaces:**
- Consumes: `JenisAsesmen::masukRapor()` (sudah ada sejak Priority #6).
- Produces: `CapaianKompetensiGenerator::generateNarasi(Siswa $siswa, SubjekPenilaian $subjek, Semester $semester): array{tertinggi: ?string, terendah: ?string}` — signature TIDAK BERUBAH, dipakai `GenerateNarasiPerkembanganAction` (tidak disentuh task ini).

- [x] **Step 1: Tulis test (akan gagal — filter belum ada, narasi masih tercampur Formatif)**

```php
<?php

use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Services\CapaianKompetensiGenerator;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('generates a positive narasi from Sumatif nilai only, ignoring a lower Formatif nilai for the same siswa+subjek+semester', function () {
    $mapel = MataPelajaran::factory()->create();
    $lembaga = $mapel->lembaga_id;
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga, 'kelas_id' => $kelas->id]);

    $komponen = KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
        'lembaga_id' => $lembaga, 'assessment_type' => 'numeric', 'kktp_minimal' => 75,
    ]);

    $asesmenSumatif = Asesmen::factory()->create([
        'guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'jenis' => 'sumatif_lingkup_materi',
    ]);
    NilaiSiswa::create(['asesmen_id' => $asesmenSumatif->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 90]);

    $asesmenFormatif = Asesmen::factory()->create([
        'guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'jenis' => 'formatif',
    ]);
    NilaiSiswa::create(['asesmen_id' => $asesmenFormatif->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 40]);

    // WAJIB dibuktikan dulu: data Formatif benar-benar tersimpan sebelum assert exclusion.
    expect(Asesmen::where('id', $asesmenFormatif->id)->where('jenis', 'formatif')->exists())->toBeTrue();
    expect(NilaiSiswa::where('asesmen_id', $asesmenFormatif->id)->first()->nilai_angka)->toBe(40);

    $narasi = app(CapaianKompetensiGenerator::class)->generateNarasi($siswa, $mapel, $semester);

    // Rata-rata KALAU tercampur: (90+40)/2 = 65, di bawah KKTP 75 -> akan hasilkan narasi "perlu bimbingan".
    // Rata-rata BENAR (Sumatif saja): 90, di atas KKTP 75 -> narasi "penguasaan sangat baik".
    expect($narasi['tertinggi'])->toContain('penguasaan sangat baik');
    expect($narasi['terendah'])->toBeNull();
});
```

Simpan sebagai `tests/Unit/Services/CapaianKompetensiGeneratorTest.php`.

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Unit/Services/CapaianKompetensiGeneratorTest.php`
Expected: FAIL — `$narasi['terendah']` berisi teks "perlu bimbingan" (rata-rata tercampur 65, di bawah KKTP 75), bukan `null`

- [x] **Step 3: Tambah filter jenis**

Tambahkan import di `app/Domains/Akademik/Services/CapaianKompetensiGenerator.php` (setelah `use App\Domains\Akademik\Models\Asesmen;`):

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

Baris lain di method TIDAK BERUBAH.

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Unit/Services/CapaianKompetensiGeneratorTest.php`
Expected: PASS (1 test)

- [x] **Step 5: Lint & commit**

Run: `php -l app/Domains/Akademik/Services/CapaianKompetensiGenerator.php`
Expected: `No syntax errors detected`

```bash
git add app/Domains/Akademik/Services/CapaianKompetensiGenerator.php tests/Unit/Services/CapaianKompetensiGeneratorTest.php
git commit -m "fix(akademik): CapaianKompetensiGenerator kecualikan Diagnostik/Formatif dari narasi rapor"
```

---

## Task 2: Fix `DashboardStatsService::statistikProgressRaporKelas()` — Progress Kesiapan Rapor

**Files:**
- Modify: `app/Services/DashboardStatsService.php`
- Modify: `tests/Feature/DashboardStatsServiceAssessmentTypeTest.php`

**Interfaces:**
- Consumes: `JenisAsesmen::masukRapor()`.
- Produces: `DashboardStatsService::statistikProgressRaporKelas(Kelas $kelas): array{persen: float, terisi: int, total: int}` — signature TIDAK BERUBAH.

- [x] **Step 1: Tambah test (akan gagal — Formatif masih dihitung "terisi")**

Tambahkan ke akhir `tests/Feature/DashboardStatsServiceAssessmentTypeTest.php`:

```php
it('does not count a Formatif nilai as filled progress toward rapor readiness', function () {
    $mapel = MataPelajaran::factory()->create();
    $lembaga = $mapel->lembaga_id;
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga, 'status_aktif' => true]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga, 'kelas_id' => $kelas->id]);

    $komponen = KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
        'lembaga_id' => $lembaga, 'assessment_type' => 'numeric',
    ]);

    $asesmenFormatif = Asesmen::factory()->create([
        'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id,
        'semester_id' => $semester->id, 'jenis' => 'formatif',
    ]);
    NilaiSiswa::create(['asesmen_id' => $asesmenFormatif->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 100]);

    $hasilSebelumSumatif = app(DashboardStatsService::class)->statistikProgressRaporKelas($kelas);

    expect($hasilSebelumSumatif['terisi'])->toBe(0);
    expect($hasilSebelumSumatif['persen'])->toBe(0.0);

    $asesmenSumatif = Asesmen::factory()->create([
        'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id,
        'semester_id' => $semester->id, 'jenis' => 'sumatif_lingkup_materi',
    ]);
    NilaiSiswa::create(['asesmen_id' => $asesmenSumatif->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 80]);

    $hasilSetelahSumatif = app(DashboardStatsService::class)->statistikProgressRaporKelas($kelas);

    expect($hasilSetelahSumatif['terisi'])->toBe(1);
    expect($hasilSetelahSumatif['persen'])->toBe(100.0);
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/DashboardStatsServiceAssessmentTypeTest.php`
Expected: FAIL pada test baru — `$hasilSebelumSumatif['terisi']` bernilai `1` (Formatif ikut dihitung), bukan `0`

- [x] **Step 3: Tambah filter jenis**

Tambahkan import di `app/Services/DashboardStatsService.php` (setelah `use App\Domains\Akademik\Models\KomponenPenilaian;`):

```php
use App\Domains\Akademik\Enums\JenisAsesmen;
```

Ubah query `$totalTerisi` di `statistikProgressRaporKelas()` (baris 142-145):

```php
        $totalTerisi = NilaiSiswa::whereHas('siswa', fn ($q) => $q->where('kelas_id', $kelas->id))
            ->whereHas('komponenPenilaian', fn ($q) => $q->where('semester_id', $semester->id))
            ->whereHas('asesmen', fn ($q) => $q->whereIn('jenis', JenisAsesmen::masukRapor()))
            ->whereNotNull('nilai_angka')
            ->count();
```

Baris lain di method (`$totalSiswa`, `$totalKomponen`, `$totalSlot`, return array) TIDAK BERUBAH.

- [x] **Step 4: Jalankan test, pastikan semua lulus**

Run: `php artisan test tests/Feature/DashboardStatsServiceAssessmentTypeTest.php`
Expected: PASS (semua test di file, termasuk test existing `'reaches 100 percent progress...'`)

- [x] **Step 5: Lint & commit**

Run: `php -l app/Services/DashboardStatsService.php`
Expected: `No syntax errors detected`

```bash
git add app/Services/DashboardStatsService.php tests/Feature/DashboardStatsServiceAssessmentTypeTest.php
git commit -m "fix(akademik): DashboardStatsService kecualikan Diagnostik/Formatif dari progress kesiapan rapor"
```

---

## Task 3: Fix `DashboardController` — Widget Nilai Terbaru Siswa & Orang Tua

**Files:**
- Modify: `app/Http/Controllers/Admin/DashboardController.php`
- Modify: `tests/Feature/DashboardTest.php`

**Interfaces:**
- Consumes: `JenisAsesmen::masukRapor()`.
- Produces: tidak ada interface baru — key `nilaiTerbaru` di kedua view TIDAK BERUBAH strukturnya, cuma isinya sekarang difilter.

- [x] **Step 1: Tambah test (akan gagal — Formatif masih muncul di widget)**

Tambahkan ke akhir `tests/Feature/DashboardTest.php`:

```php
it('excludes a Formatif nilai from the siswa dashboard latest-grade widget, even if it is more recent than the Sumatif nilai', function () {
    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $lembaga = Lembaga::factory()->create();
    $kelas = \App\Models\Kelas::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = \App\Models\Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $mapel = \App\Domains\Akademik\Models\MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = \App\Models\Semester::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = \App\Domains\Akademik\Models\KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'lembaga_id' => $lembaga->id,
    ]);

    $asesmenSumatif = \App\Domains\Akademik\Models\Asesmen::factory()->create([
        'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'lembaga_id' => $lembaga->id,
        'jenis' => 'sumatif_lingkup_materi',
    ]);
    \App\Domains\Akademik\Models\NilaiSiswa::create([
        'siswa_id' => $siswa->id, 'asesmen_id' => $asesmenSumatif->id, 'komponen_penilaian_id' => $komponen->id, 'lembaga_id' => $lembaga->id, 'nilai_angka' => 77,
    ]);

    // Dibuat SETELAH nilai Sumatif -> id lebih besar -> akan "menang" di latest('id') kalau filter jenis tidak ada.
    $asesmenFormatif = \App\Domains\Akademik\Models\Asesmen::factory()->create([
        'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'lembaga_id' => $lembaga->id,
        'jenis' => 'formatif',
    ]);
    \App\Domains\Akademik\Models\NilaiSiswa::create([
        'siswa_id' => $siswa->id, 'asesmen_id' => $asesmenFormatif->id, 'komponen_penilaian_id' => $komponen->id, 'lembaga_id' => $lembaga->id, 'nilai_angka' => 40,
    ]);

    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('siswa');
    $siswa->update(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('77');
    $response->assertDontSee('40');
});

it('excludes a Formatif nilai from the orang tua dashboard latest-grade widget for a linked child', function () {
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $lembaga = Lembaga::factory()->create();
    $kelas = \App\Models\Kelas::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = \App\Models\Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'nama_lengkap' => 'Anak Dashboard Ortu Formatif']);
    $mapel = \App\Domains\Akademik\Models\MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = \App\Models\Semester::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = \App\Domains\Akademik\Models\KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'lembaga_id' => $lembaga->id,
    ]);

    $asesmenSumatif = \App\Domains\Akademik\Models\Asesmen::factory()->create([
        'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'lembaga_id' => $lembaga->id,
        'jenis' => 'sumatif_lingkup_materi',
    ]);
    \App\Domains\Akademik\Models\NilaiSiswa::create([
        'siswa_id' => $siswa->id, 'asesmen_id' => $asesmenSumatif->id, 'komponen_penilaian_id' => $komponen->id, 'lembaga_id' => $lembaga->id, 'nilai_angka' => 77,
    ]);

    $asesmenFormatif = \App\Domains\Akademik\Models\Asesmen::factory()->create([
        'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'lembaga_id' => $lembaga->id,
        'jenis' => 'formatif',
    ]);
    \App\Domains\Akademik\Models\NilaiSiswa::create([
        'siswa_id' => $siswa->id, 'asesmen_id' => $asesmenFormatif->id, 'komponen_penilaian_id' => $komponen->id, 'lembaga_id' => $lembaga->id, 'nilai_angka' => 40,
    ]);

    $orangTuaUser = User::factory()->create(['lembaga_id' => null]);
    $orangTuaUser->assignRole('orang_tua');
    $orangTua = \App\Models\OrangTua::factory()->create(['user_id' => $orangTuaUser->id]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $response = $this->actingAs($orangTuaUser)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('77');
    $response->assertDontSee('40');
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/DashboardTest.php --filter="Formatif"`
Expected: FAIL pada kedua test baru — `assertDontSee('40')` gagal krn nilai Formatif (id lebih besar) muncul duluan di widget, nilai Sumatif (77) malah tidak ke-render krn kalah di `limit(5)` — atau setidaknya `'40'` tetap terlihat.

- [x] **Step 3: Tambah filter jenis ke kedua branch**

Tambahkan import di `app/Http/Controllers/Admin/DashboardController.php` (setelah `use App\Domains\Akademik\Enums\StatusRpp;`):

```php
use App\Domains\Akademik\Enums\JenisAsesmen;
```

Ubah branch siswa (baris 134-139):

```php
                $nilaiTerbaru = NilaiSiswa::where('siswa_id', $siswa->id)
                    ->whereNotNull('nilai_angka')
                    ->whereHas('asesmen', fn ($q) => $q->whereIn('jenis', JenisAsesmen::masukRapor()))
                    ->with(['komponenPenilaian.subjek', 'asesmen.subjek'])
                    ->latest('id')
                    ->limit(5)
                    ->get();
```

Ubah branch orang tua (baris 204-213):

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

- [x] **Step 4: Jalankan test, pastikan semua lulus**

Run: `php artisan test tests/Feature/DashboardTest.php`
Expected: PASS (semua test di file, termasuk 2 test existing `'shows a siswa their latest recorded grade...'` dan `'shows an orang tua the latest recorded grade...'` yang TIDAK BOLEH berubah hasilnya — keduanya pakai `Asesmen::factory()` tanpa `jenis` eksplisit, default `SumatifLingkupMateri`, jadi tetap lolos filter baru ini)

- [x] **Step 5: Lint & commit**

Run: `php -l app/Http/Controllers/Admin/DashboardController.php`
Expected: `No syntax errors detected`

```bash
git add app/Http/Controllers/Admin/DashboardController.php tests/Feature/DashboardTest.php
git commit -m "fix(akademik): DashboardController kecualikan Diagnostik/Formatif dari widget nilai terbaru siswa/orang tua"
```

---

## Task 4: Update Docblock `masukRapor()` & Regresi Penuh

**Files:**
- Modify: `app/Domains/Akademik/Enums/JenisAsesmen.php`
- Modify: `PETA_PENGEMBANGAN.md`

- [x] **Step 1: Perbarui docblock `masukRapor()`**

Ganti docblock method `masukRapor()` di `app/Domains/Akademik/Enums/JenisAsesmen.php` dari:

```php
    /**
     * Jenis asesmen yang secara semantik merupakan SUMBER PERHITUNGAN RAPOR
     * (dipakai RaporCalculationService::hitungRekapKelas() sebagai satu-satunya
     * filter). Diagnostik dan Formatif SENGAJA tidak termasuk -- keduanya
     * asesmen untuk proses pembelajaran (pemetaan kesiapan belajar, penyesuaian
     * metode ajar), bukan komponen nilai rapor.
     *
     * Kalau menambah case baru ke enum ini di masa depan, WAJIB secara sadar
     * memutuskan apakah case itu masuk daftar ini atau tidak -- jangan
     * dibiarkan default masuk/keluar tanpa keputusan eksplisit.
     *
     * @return array<int, self>
     */
```

menjadi:

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

- [x] **Step 2: Jalankan full test suite tanpa filter**

Run: `php artisan test --compact`
Expected: 0 failed. Catat angka pasti (passed/skipped/assertions).

- [x] **Step 3: Update `PETA_PENGEMBANGAN.md`**

Cari paragraf `**Prioritas #6 SELESAI (27 Agustus 2026)**: ...` di bagian `## 🔵 Roadmap Kurikulum Dinamis`, tambahkan kalimat baru di akhir paragraf itu (sebelum titik akhir kalimat terakhir "Dieksekusi lewat..."):

```markdown
**Tindak lanjut (27 Agustus 2026)**: audit sistematis penuh (Laravel Boost `database-schema` + grep menyeluruh seluruh `app/`, termasuk Jobs/Console/Notifications/Exports) menemukan 3 consumer lain yang belum ikut filter `JenisAsesmen::masukRapor()` saat Prioritas #6 dibuka: `CapaianKompetensiGenerator` (narasi rapor resmi), `DashboardStatsService::statistikProgressRaporKelas()` (progress kesiapan rapor guru), `Admin\DashboardController` (widget nilai terbaru siswa/orang tua). Ketiganya sudah diperbaiki, dikonfirmasi tidak ada consumer tersembunyi lain. Dieksekusi lewat `.agents/plans/2026-08-27-akademik-fix-filter-jenis-asesmen-consumer-lain.md`.
```

- [x] **Step 4: Lint & commit**

Run: `php -l app/Domains/Akademik/Enums/JenisAsesmen.php`
Expected: `No syntax errors detected`

```bash
git add app/Domains/Akademik/Enums/JenisAsesmen.php PETA_PENGEMBANGAN.md
git commit -m "docs(akademik): perbarui docblock masukRapor(), catat tindak lanjut Prioritas 6"
```
