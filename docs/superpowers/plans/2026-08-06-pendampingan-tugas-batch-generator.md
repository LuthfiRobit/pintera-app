# Sistem Generate Tugas Berkala (Batch) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Konselor mengisi satu definisi tugas (judul, instruksi, frekuensi, rentang tanggal, dan
tanggal jatuh tempo bulanan bila relevan); sistem menghitung dan membuat banyak baris
`kasus_tugas` independen sesuai frekuensi (setelah aturan fallback diterapkan), menggantikan
mekanisme checklist-per-tanggal 2026-08-05 secara total.

**Architecture:** Satu service murni (`TugasBatchGenerator`) berisi seluruh logic
fallback+generate tanggal, dipakai baik oleh endpoint submit sungguhan maupun endpoint preview
dry-run — satu-satunya sumber kebenaran, tidak ada duplikasi logic di JavaScript. Baris-baris
hasil generate berbagi `batch_id` (UUID) + `batch_urutan`/`batch_total` untuk pengelompokan
tampilan. Urutan pengerjaan: migrasi & model → service generator (TDD, unit test murni) →
FormRequest validasi → controller submit → endpoint preview → pembongkaran mekanisme lama →
UI Blade → regresi penuh.

**Tech Stack:** Laravel 12, Pest 4, Carbon, MySQL, Blade + Alpine.js (untuk live-preview via
fetch ke endpoint preview).

## Global Constraints

- `frekuensi` yang dipilih user bisa berubah (fallback) sebelum dipakai untuk generate — SATU
  fungsi (`TugasBatchGenerator::tentukanFrekuensiAkhir()`) adalah satu-satunya tempat aturan
  fallback ditentukan; dipakai baik oleh path submit maupun path preview, tidak pernah ditulis
  dua kali dengan kemungkinan berbeda.
- Ambang validasi (`> 7 hari`, `> 30 hari`) dihitung dengan `Carbon::diffInDays()` (bukan
  `diffInMonths`) — lihat spec untuk alasan (ambiguitas jumlah hari per bulan berbeda-beda).
- Sisa hari mingguan yang tidak genap 7 di akhir rentang menjadi **blok pendek terpisah**,
  bukan digabung ke blok sebelumnya.
- `tanggal_pengumpulan_bulanan` wajib hanya kalau **frekuensi akhir setelah fallback** =
  `bulanan` (bukan frekuensi yang dipilih di form).
- `batch_id` (UUID, char 36) DB-level **nullable** untuk keamanan migrasi (supaya migrasi ini
  tetap valid dijalankan lewat `php artisan migrate` biasa pada tabel yang sudah berisi data,
  tidak hanya lewat `migrate:fresh`) — tapi lapisan aplikasi (`TugasBatchGenerator` +
  `KasusTugasController::store()`) **selalu** mengisinya untuk setiap baris baru, tidak pernah
  meninggalkannya null pada baris yang baru dibuat.
- Mekanisme checklist-per-tanggal 2026-08-05 (kolom `kasus_tugas_submission.tanggal`, lock
  per-tanggal di `KasusTugasSubmissionController::store()`/`review()`, cabang tampilan
  `@if ($tugas->frekuensi === 'harian')` di `_tab-tugas.blade.php`) **dicopot total**, bukan
  dipertahankan sebagai kode mati.
- Endpoint preview TIDAK PERNAH menulis ke database — dry-run murni, memanggil service yang
  sama persis dengan path submit sungguhan.
- Siswa dan orang tua kontak utama menerima **satu** notifikasi ringkasan per batch
  (`TugasBatchDibuatNotification`) saat tugas dibuat, bukan satu notifikasi per baris hasil
  generate — keputusan desain 2026-08-06 untuk mencegah batch besar (harian 30 hari, bulanan
  berbulan-bulan) membanjiri penerima dengan puluhan notifikasi terpisah dalam satu submit.
- `markSelesai()`, `assertKonselorPemegangKasus()`, guard status kasus
  (`Ditugaskan`/`Berjalan`/`Eskalasi`), job `terlewat` — semuanya sudah bekerja per-baris
  `kasus_tugas` individual sejak Sub-proyek 4 dan **tidak perlu diubah** untuk mendukung model
  batch ini (baris hasil generate hanyalah baris `kasus_tugas` biasa, mekanisme di sekitarnya
  sudah cocok tanpa modifikasi).
- Karena seluruh data `kasus`/`kasus_tugas` saat ini adalah data `demo`, tidak ada migrasi data
  lama yang perlu dijaga tetap valid — proyek menjalankan `migrate:fresh --seed` di akhir
  (Task 8), bukan migrasi mundur yang kompatibel dengan data lama.

---

### Task 1: Migrasi skema + lapisan model

**Files:**
- Create: `database/migrations/2026_08_08_090000_add_batch_columns_to_kasus_tugas.php`
- Create: `database/migrations/2026_08_08_090100_drop_tanggal_from_kasus_tugas_submission.php`
- Modify: `app/Models/KasusTugas.php`
- Modify: `app/Models/KasusTugasSubmission.php`
- Test: `tests/Feature/KasusTugasBatchSchemaTest.php`

**Interfaces:**
- Produces: `KasusTugas::$batch_id` (string, nullable di DB, selalu diisi aplikasi),
  `KasusTugas::$batch_urutan` (int), `KasusTugas::$batch_total` (int) — dipakai Task 2's
  service saat membuat baris, dan Task 7's tampilan untuk mengelompokkan.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php
// tests/Feature/KasusTugasBatchSchemaTest.php

use App\Models\Kasus;
use App\Models\KasusTugas;
use App\Models\KasusTugasSubmission;

it('stores batch_id, batch_urutan, and batch_total on kasus_tugas', function () {
    $kasus = Kasus::factory()->create();

    $tugas = KasusTugas::create([
        'kasus_id' => $kasus->id,
        'judul' => 'Jurnal Emosi',
        'instruksi' => 'Tulis jurnal harian.',
        'frekuensi' => 'harian',
        'mulai_pada' => '2026-08-10',
        'batas_selesai_pada' => '2026-08-10',
        'batch_id' => 'batch-uuid-contoh',
        'batch_urutan' => 1,
        'batch_total' => 3,
    ]);

    $tugas->refresh();
    expect($tugas->batch_id)->toBe('batch-uuid-contoh');
    expect($tugas->batch_urutan)->toBe(1);
    expect($tugas->batch_total)->toBe(3);
});

it('no longer has a tanggal column on kasus_tugas_submission', function () {
    expect(\Illuminate\Support\Facades\Schema::hasColumn('kasus_tugas_submission', 'tanggal'))->toBeFalse();
});

it('creates a kasus_tugas_submission without a tanggal field at all', function () {
    $kasus = Kasus::factory()->create();
    $tugas = KasusTugas::factory()->create(['kasus_id' => $kasus->id]);

    $submission = KasusTugasSubmission::create([
        'tugas_id' => $tugas->id,
        'teks' => 'Bukti pengerjaan.',
    ]);

    expect($submission->fresh())->not->toBeNull();
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=KasusTugasBatchSchemaTest`
Expected: FAIL — kolom `batch_id`/`batch_urutan`/`batch_total` belum ada
(`QueryException`/mass-assignment silently dropped), dan kolom `tanggal` masih ada di
`kasus_tugas_submission`.

- [ ] **Step 3: Tulis migrasi kolom batch**

```php
<?php
// database/migrations/2026_08_08_090000_add_batch_columns_to_kasus_tugas.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kasus_tugas', function (Blueprint $table) {
            $table->char('batch_id', 36)->nullable()->after('frekuensi');
            $table->unsignedInteger('batch_urutan')->default(1)->after('batch_id');
            $table->unsignedInteger('batch_total')->default(1)->after('batch_urutan');
        });
    }

    public function down(): void
    {
        Schema::table('kasus_tugas', function (Blueprint $table) {
            $table->dropColumn(['batch_id', 'batch_urutan', 'batch_total']);
        });
    }
};
```

- [ ] **Step 4: Tulis migrasi drop kolom `tanggal`**

```php
<?php
// database/migrations/2026_08_08_090100_drop_tanggal_from_kasus_tugas_submission.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kasus_tugas_submission', function (Blueprint $table) {
            $table->dropColumn('tanggal');
        });
    }

    public function down(): void
    {
        Schema::table('kasus_tugas_submission', function (Blueprint $table) {
            $table->date('tanggal')->nullable()->after('orang_tua_id');
        });
    }
};
```

Run: `php artisan migrate`

- [ ] **Step 5: Perbarui `app/Models/KasusTugas.php`**

Ubah:
```php
    protected $fillable = [
        'kasus_id', 'judul', 'instruksi', 'frekuensi',
        'mulai_pada', 'batas_selesai_pada', 'status',
    ];
```
menjadi:
```php
    protected $fillable = [
        'kasus_id', 'judul', 'instruksi', 'frekuensi',
        'batch_id', 'batch_urutan', 'batch_total',
        'mulai_pada', 'batas_selesai_pada', 'status',
    ];
```

- [ ] **Step 6: Perbarui `app/Models/KasusTugasSubmission.php`**

Ubah:
```php
    protected $fillable = [
        'tugas_id', 'siswa_id', 'orang_tua_id', 'teks', 'lampiran',
        'status_review', 'catatan_revisi', 'tanggal',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }
```
menjadi:
```php
    protected $fillable = [
        'tugas_id', 'siswa_id', 'orang_tua_id', 'teks', 'lampiran',
        'status_review', 'catatan_revisi',
    ];
```
(hapus method `casts()` sepenuhnya kalau `tanggal` adalah satu-satunya isi cast-nya — periksa
file saat ini dulu untuk memastikan tidak ada cast lain yang perlu dipertahankan).

- [ ] **Step 7: Jalankan test, pastikan lolos**

Run: `php artisan test --filter=KasusTugasBatchSchemaTest`
Expected: PASS (3 test)

- [ ] **Step 8: Jalankan regresi Kasus yang lebih luas**

Run: `php artisan test --filter=Kasus`
Expected: sebagian BESAR lolos, TAPI test yang bergantung pada kolom `tanggal` (di
`tests/Feature/KasusTugasSubmissionHarianTest.php`, `tests/Feature/KasusTugasHarianViewTest.php`,
`tests/Feature/KasusTugasSubmissionTanggalSchemaTest.php`,
`tests/Feature/KasusTugasSubmissionTanggalBackfillTest.php`) akan GAGAL sekarang — ini
DIHARAPKAN, file-file itu dihapus di Task 6 setelah mekanisme lama benar-benar dicopot dari
controller. Jangan hapus file-file itu di task ini; catat saja jumlah kegagalan yang
diharapkan dalam laporan.

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_08_08_090000_add_batch_columns_to_kasus_tugas.php database/migrations/2026_08_08_090100_drop_tanggal_from_kasus_tugas_submission.php app/Models/KasusTugas.php app/Models/KasusTugasSubmission.php tests/Feature/KasusTugasBatchSchemaTest.php
git commit -m "feat(pendampingan): add batch columns to kasus_tugas, drop tanggal from submission"
```

---

### Task 2: Service `TugasBatchGenerator` + TDD unit test murni

**Files:**
- Create: `app/Services/TugasBatchGenerator.php`
- Test: `tests/Unit/TugasBatchGeneratorTest.php`

**Interfaces:**
- Consumes: tidak ada (service murni, tidak bergantung pada model Eloquent apapun — menerima
  dan mengembalikan `Carbon`/array/`Collection` saja, supaya bisa diuji sebagai unit test murni
  tanpa database).
- Produces: `TugasBatchGenerator::tentukanFrekuensiAkhir(string $frekuensiDipilih, Carbon
  $tanggalMulai, Carbon $tanggalSelesai): string` dan `TugasBatchGenerator::generate(string
  $frekuensiDipilih, Carbon $tanggalMulai, Carbon $tanggalSelesai, ?int
  $tanggalPengumpulanBulanan = null, bool $akhirBulan = false): Collection` yang mengembalikan
  `Collection` berisi array `['mulai_pada' => Carbon, 'batas_selesai_pada' => Carbon]` per
  baris — dipakai Task 3 (validasi), Task 4 (controller submit), dan Task 5 (endpoint
  preview), semuanya memanggil method yang SAMA PERSIS ini.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php
// tests/Unit/TugasBatchGeneratorTest.php

use App\Services\TugasBatchGenerator;
use Carbon\Carbon;

beforeEach(function () {
    $this->generator = new TugasBatchGenerator();
});

it('keeps sekali as sekali regardless of range length', function () {
    $hasil = $this->generator->tentukanFrekuensiAkhir('sekali', Carbon::parse('2026-08-01'), Carbon::parse('2026-12-31'));
    expect($hasil)->toBe('sekali');
});

it('keeps harian as harian regardless of range length', function () {
    $hasil = $this->generator->tentukanFrekuensiAkhir('harian', Carbon::parse('2026-08-01'), Carbon::parse('2026-08-01'));
    expect($hasil)->toBe('harian');
});

it('falls back mingguan to harian when the range is 7 days or less', function () {
    $hasil = $this->generator->tentukanFrekuensiAkhir('mingguan', Carbon::parse('2026-08-01'), Carbon::parse('2026-08-08'));
    expect($hasil)->toBe('harian');
});

it('keeps mingguan as mingguan when the range is more than 7 days', function () {
    $hasil = $this->generator->tentukanFrekuensiAkhir('mingguan', Carbon::parse('2026-08-01'), Carbon::parse('2026-08-09'));
    expect($hasil)->toBe('mingguan');
});

it('falls back bulanan to mingguan when the range is 30 days or less', function () {
    $hasil = $this->generator->tentukanFrekuensiAkhir('bulanan', Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));
    expect($hasil)->toBe('mingguan');
});

it('chains bulanan all the way down to harian when the range is 7 days or less', function () {
    $hasil = $this->generator->tentukanFrekuensiAkhir('bulanan', Carbon::parse('2026-08-01'), Carbon::parse('2026-08-05'));
    expect($hasil)->toBe('harian');
});

it('keeps bulanan as bulanan when the range is more than 30 days', function () {
    $hasil = $this->generator->tentukanFrekuensiAkhir('bulanan', Carbon::parse('2026-08-01'), Carbon::parse('2026-09-02'));
    expect($hasil)->toBe('bulanan');
});

it('generates one row for sekali spanning the whole range', function () {
    $baris = $this->generator->generate('sekali', Carbon::parse('2026-08-01'), Carbon::parse('2026-08-20'));

    expect($baris)->toHaveCount(1);
    expect($baris[0]['mulai_pada']->toDateString())->toBe('2026-08-01');
    expect($baris[0]['batas_selesai_pada']->toDateString())->toBe('2026-08-20');
});

it('generates one row per calendar day for harian', function () {
    $baris = $this->generator->generate('harian', Carbon::parse('2026-08-10'), Carbon::parse('2026-08-12'));

    expect($baris)->toHaveCount(3);
    expect($baris[0]['mulai_pada']->toDateString())->toBe('2026-08-10');
    expect($baris[0]['batas_selesai_pada']->toDateString())->toBe('2026-08-10');
    expect($baris[1]['mulai_pada']->toDateString())->toBe('2026-08-11');
    expect($baris[2]['mulai_pada']->toDateString())->toBe('2026-08-12');
});

it('falls back mingguan to harian rows when the range is too short', function () {
    $baris = $this->generator->generate('mingguan', Carbon::parse('2026-08-01'), Carbon::parse('2026-08-05'));

    expect($baris)->toHaveCount(5);
    expect($baris[0]['mulai_pada']->toDateString())->toBe($baris[0]['batas_selesai_pada']->toDateString());
});

it('generates 7-day blocks for mingguan with a trailing short block, not merged into the last full block', function () {
    // 1-31 Agustus = 30 hari selisih, per contoh spesifikasi user.
    $baris = $this->generator->generate('mingguan', Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

    expect($baris)->toHaveCount(5);
    expect([$baris[0]['mulai_pada']->toDateString(), $baris[0]['batas_selesai_pada']->toDateString()])->toBe(['2026-08-01', '2026-08-07']);
    expect([$baris[1]['mulai_pada']->toDateString(), $baris[1]['batas_selesai_pada']->toDateString()])->toBe(['2026-08-08', '2026-08-14']);
    expect([$baris[2]['mulai_pada']->toDateString(), $baris[2]['batas_selesai_pada']->toDateString()])->toBe(['2026-08-15', '2026-08-21']);
    expect([$baris[3]['mulai_pada']->toDateString(), $baris[3]['batas_selesai_pada']->toDateString()])->toBe(['2026-08-22', '2026-08-28']);
    // Blok terakhir hanya 3 hari (29-31), TERPISAH — bukan digabung jadi blok ke-4 yang 10 hari.
    expect([$baris[4]['mulai_pada']->toDateString(), $baris[4]['batas_selesai_pada']->toDateString()])->toBe(['2026-08-29', '2026-08-31']);
});

it('generates exactly 7-day blocks with no trailing block when the range divides evenly', function () {
    $baris = $this->generator->generate('mingguan', Carbon::parse('2026-08-01'), Carbon::parse('2026-08-14'));

    expect($baris)->toHaveCount(2);
    expect([$baris[1]['mulai_pada']->toDateString(), $baris[1]['batas_selesai_pada']->toDateString()])->toBe(['2026-08-08', '2026-08-14']);
});

it('falls back bulanan to mingguan-shaped rows when the range is too short for monthly', function () {
    $baris = $this->generator->generate('bulanan', Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

    expect($baris)->toHaveCount(5);
});

it('generates one row per month segment for bulanan, due date clamped to the day of month', function () {
    $baris = $this->generator->generate('bulanan', Carbon::parse('2026-08-01'), Carbon::parse('2026-11-30'), tanggalPengumpulanBulanan: 15);

    expect($baris)->toHaveCount(5);
    expect([$baris[0]['mulai_pada']->toDateString(), $baris[0]['batas_selesai_pada']->toDateString()])->toBe(['2026-08-01', '2026-08-15']);
    expect([$baris[1]['mulai_pada']->toDateString(), $baris[1]['batas_selesai_pada']->toDateString()])->toBe(['2026-08-16', '2026-09-15']);
    expect([$baris[2]['mulai_pada']->toDateString(), $baris[2]['batas_selesai_pada']->toDateString()])->toBe(['2026-09-16', '2026-10-15']);
    expect([$baris[3]['mulai_pada']->toDateString(), $baris[3]['batas_selesai_pada']->toDateString()])->toBe(['2026-10-16', '2026-11-15']);
    // Baris terakhir dipotong (min()) ke tanggal_selesai keseluruhan (30 Nov), bukan Des 15.
    expect([$baris[4]['mulai_pada']->toDateString(), $baris[4]['batas_selesai_pada']->toDateString()])->toBe(['2026-11-16', '2026-11-30']);
});

it('generates bulanan rows using end-of-month due dates, correctly handling February', function () {
    $baris = $this->generator->generate('bulanan', Carbon::parse('2026-01-01'), Carbon::parse('2026-04-30'), akhirBulan: true);

    expect($baris)->toHaveCount(4);
    expect($baris[0]['batas_selesai_pada']->toDateString())->toBe('2026-01-31');
    expect($baris[1]['batas_selesai_pada']->toDateString())->toBe('2026-02-28');
    expect($baris[2]['batas_selesai_pada']->toDateString())->toBe('2026-03-31');
    expect($baris[3]['batas_selesai_pada']->toDateString())->toBe('2026-04-30');
});

it('pushes the first monthly due date to the next month when the range starts after that day of month', function () {
    // Mulai 20 Agustus, tanggal_pengumpulan_bulanan=15 -> jatuh tempo pertama BUKAN 15 Agustus
    // (sudah lewat relatif terhadap tanggal_mulai), melainkan 15 September.
    $baris = $this->generator->generate('bulanan', Carbon::parse('2026-08-20'), Carbon::parse('2026-11-30'), tanggalPengumpulanBulanan: 15);

    expect($baris[0]['batas_selesai_pada']->toDateString())->toBe('2026-09-15');
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=TugasBatchGeneratorTest`
Expected: FAIL — `App\Services\TugasBatchGenerator` belum ada (`Class not found`).

- [ ] **Step 3: Tulis service-nya**

```php
<?php
// app/Services/TugasBatchGenerator.php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class TugasBatchGenerator
{
    public function tentukanFrekuensiAkhir(string $frekuensiDipilih, Carbon $tanggalMulai, Carbon $tanggalSelesai): string
    {
        $selisihHari = $tanggalMulai->diffInDays($tanggalSelesai);

        $frekuensiAkhir = $frekuensiDipilih;

        if ($frekuensiAkhir === 'bulanan' && $selisihHari <= 30) {
            $frekuensiAkhir = 'mingguan';
        }

        if ($frekuensiAkhir === 'mingguan' && $selisihHari <= 7) {
            $frekuensiAkhir = 'harian';
        }

        return $frekuensiAkhir;
    }

    /**
     * @return Collection<int, array{mulai_pada: Carbon, batas_selesai_pada: Carbon}>
     */
    public function generate(
        string $frekuensiDipilih,
        Carbon $tanggalMulai,
        Carbon $tanggalSelesai,
        ?int $tanggalPengumpulanBulanan = null,
        bool $akhirBulan = false,
    ): Collection {
        $frekuensiAkhir = $this->tentukanFrekuensiAkhir($frekuensiDipilih, $tanggalMulai, $tanggalSelesai);

        return match ($frekuensiAkhir) {
            'sekali' => collect([
                ['mulai_pada' => $tanggalMulai->copy(), 'batas_selesai_pada' => $tanggalSelesai->copy()],
            ]),
            'harian' => $this->generateHarian($tanggalMulai, $tanggalSelesai),
            'mingguan' => $this->generateMingguan($tanggalMulai, $tanggalSelesai),
            'bulanan' => $this->generateBulanan($tanggalMulai, $tanggalSelesai, $tanggalPengumpulanBulanan, $akhirBulan),
        };
    }

    private function generateHarian(Carbon $tanggalMulai, Carbon $tanggalSelesai): Collection
    {
        $baris = collect();
        $kursor = $tanggalMulai->copy();

        while ($kursor->lte($tanggalSelesai)) {
            $baris->push(['mulai_pada' => $kursor->copy(), 'batas_selesai_pada' => $kursor->copy()]);
            $kursor->addDay();
        }

        return $baris;
    }

    private function generateMingguan(Carbon $tanggalMulai, Carbon $tanggalSelesai): Collection
    {
        $baris = collect();
        $kursor = $tanggalMulai->copy();

        while ($kursor->lte($tanggalSelesai)) {
            $akhirBlok = $kursor->copy()->addDays(6);
            if ($akhirBlok->gt($tanggalSelesai)) {
                $akhirBlok = $tanggalSelesai->copy();
            }

            $baris->push(['mulai_pada' => $kursor->copy(), 'batas_selesai_pada' => $akhirBlok->copy()]);
            $kursor = $akhirBlok->copy()->addDay();
        }

        return $baris;
    }

    private function generateBulanan(Carbon $tanggalMulai, Carbon $tanggalSelesai, ?int $tanggalPengumpulanBulanan, bool $akhirBulan): Collection
    {
        $baris = collect();
        $mulaiSegmen = $tanggalMulai->copy();

        while (true) {
            $jatuhTempo = $this->hitungJatuhTempoBulanan($mulaiSegmen, $tanggalPengumpulanBulanan, $akhirBulan);
            $batasSelesai = $jatuhTempo->gt($tanggalSelesai) ? $tanggalSelesai->copy() : $jatuhTempo->copy();

            $baris->push(['mulai_pada' => $mulaiSegmen->copy(), 'batas_selesai_pada' => $batasSelesai->copy()]);

            if ($batasSelesai->gte($tanggalSelesai)) {
                break;
            }

            $mulaiSegmen = $batasSelesai->copy()->addDay();
        }

        return $baris;
    }

    private function hitungJatuhTempoBulanan(Carbon $dariTanggal, ?int $tanggalPengumpulanBulanan, bool $akhirBulan): Carbon
    {
        $kandidat = $akhirBulan
            ? $dariTanggal->copy()->endOfMonth()
            : $dariTanggal->copy()->day(min($tanggalPengumpulanBulanan, $dariTanggal->daysInMonth));

        if ($kandidat->lt($dariTanggal)) {
            $bulanBerikutnya = $dariTanggal->copy()->addMonthNoOverflow();
            $kandidat = $akhirBulan
                ? $bulanBerikutnya->copy()->endOfMonth()
                : $bulanBerikutnya->copy()->day(min($tanggalPengumpulanBulanan, $bulanBerikutnya->daysInMonth));
        }

        return $kandidat;
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lolos**

Run: `php artisan test --filter=TugasBatchGeneratorTest`
Expected: PASS (semua test di file ini — hitung jumlahnya dari daftar `it(...)` di atas)

- [ ] **Step 5: Commit**

```bash
git add app/Services/TugasBatchGenerator.php tests/Unit/TugasBatchGeneratorTest.php
git commit -m "feat(pendampingan): add TugasBatchGenerator service with fallback and date-block logic"
```

---

### Task 3: FormRequest validasi (`StoreKasusTugasBatchRequest`)

**Files:**
- Create: `app/Http/Requests/StoreKasusTugasBatchRequest.php`
- Test: `tests/Feature/StoreKasusTugasBatchRequestTest.php`

**Interfaces:**
- Consumes: `App\Services\TugasBatchGenerator::tentukanFrekuensiAkhir()` (Task 2) — dipanggil di
  dalam `withValidator()`/rule kondisional untuk menentukan apakah
  `tanggal_pengumpulan_bulanan` wajib.
- Produces: `StoreKasusTugasBatchRequest::validated(): array` dengan kunci `judul`,
  `instruksi`, `frekuensi`, `tanggal_mulai`, `tanggal_selesai`,
  `tanggal_pengumpulan_bulanan` (nullable), `akhir_bulan` (bool) — dipakai Task 4's controller.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php
// tests/Feature/StoreKasusTugasBatchRequestTest.php

use App\Http\Requests\StoreKasusTugasBatchRequest;
use Illuminate\Support\Facades\Validator;

function validasiTugasBatch(array $data): \Illuminate\Contracts\Validation\Validator
{
    $request = new StoreKasusTugasBatchRequest();

    return Validator::make($data, $request->rules(), [], []);
}

it('passes with a minimal valid sekali payload', function () {
    $validator = validasiTugasBatch([
        'judul' => 'Refleksi Mingguan',
        'instruksi' => 'Tulis refleksi.',
        'frekuensi' => 'sekali',
        'tanggal_mulai' => '2026-08-10',
        'tanggal_selesai' => '2026-08-10',
    ]);

    expect($validator->fails())->toBeFalse();
});

it('rejects tanggal_selesai before tanggal_mulai', function () {
    $validator = validasiTugasBatch([
        'judul' => 'X', 'instruksi' => 'Y', 'frekuensi' => 'sekali',
        'tanggal_mulai' => '2026-08-10', 'tanggal_selesai' => '2026-08-05',
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('tanggal_selesai'))->toBeTrue();
});

it('requires tanggal_pengumpulan_bulanan when the final frequency is bulanan', function () {
    $validator = validasiTugasBatch([
        'judul' => 'X', 'instruksi' => 'Y', 'frekuensi' => 'bulanan',
        'tanggal_mulai' => '2026-08-01', 'tanggal_selesai' => '2026-12-31',
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('tanggal_pengumpulan_bulanan'))->toBeTrue();
});

it('does not require tanggal_pengumpulan_bulanan when bulanan falls back to mingguan', function () {
    $validator = validasiTugasBatch([
        'judul' => 'X', 'instruksi' => 'Y', 'frekuensi' => 'bulanan',
        'tanggal_mulai' => '2026-08-01', 'tanggal_selesai' => '2026-08-20',
    ]);

    expect($validator->fails())->toBeFalse();
});

it('accepts tanggal_pengumpulan_bulanan as an integer between 1 and 31', function () {
    $validator = validasiTugasBatch([
        'judul' => 'X', 'instruksi' => 'Y', 'frekuensi' => 'bulanan',
        'tanggal_mulai' => '2026-08-01', 'tanggal_selesai' => '2026-12-31',
        'tanggal_pengumpulan_bulanan' => '15',
    ]);

    expect($validator->fails())->toBeFalse();
});

it('accepts akhir_bulan as the tanggal_pengumpulan_bulanan value', function () {
    $validator = validasiTugasBatch([
        'judul' => 'X', 'instruksi' => 'Y', 'frekuensi' => 'bulanan',
        'tanggal_mulai' => '2026-08-01', 'tanggal_selesai' => '2026-12-31',
        'tanggal_pengumpulan_bulanan' => 'akhir_bulan',
    ]);

    expect($validator->fails())->toBeFalse();
});

it('rejects a tanggal_pengumpulan_bulanan value that is neither a valid day nor akhir_bulan', function () {
    $validator = validasiTugasBatch([
        'judul' => 'X', 'instruksi' => 'Y', 'frekuensi' => 'bulanan',
        'tanggal_mulai' => '2026-08-01', 'tanggal_selesai' => '2026-12-31',
        'tanggal_pengumpulan_bulanan' => '35',
    ]);

    expect($validator->fails())->toBeTrue();
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=StoreKasusTugasBatchRequestTest`
Expected: FAIL — `App\Http\Requests\StoreKasusTugasBatchRequest` belum ada.

- [ ] **Step 3: Tulis FormRequest-nya**

```php
<?php
// app/Http/Requests/StoreKasusTugasBatchRequest.php

namespace App\Http\Requests;

use App\Services\TugasBatchGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKasusTugasBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $frekuensiAkhir = $this->frekuensiAkhir();

        return [
            'judul' => ['required', 'string', 'max:255'],
            'instruksi' => ['required', 'string'],
            'frekuensi' => ['required', 'in:sekali,harian,mingguan,bulanan'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'tanggal_pengumpulan_bulanan' => [
                Rule::requiredIf($frekuensiAkhir === 'bulanan'),
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === 'akhir_bulan') {
                        return;
                    }
                    if (! is_numeric($value) || (int) $value < 1 || (int) $value > 31) {
                        $fail('Tanggal pengumpulan bulanan harus berupa angka 1-31 atau "akhir_bulan".');
                    }
                },
            ],
        ];
    }

    private function frekuensiAkhir(): ?string
    {
        $frekuensi = $this->input('frekuensi');
        $mulai = $this->input('tanggal_mulai');
        $selesai = $this->input('tanggal_selesai');

        if (! $frekuensi || ! $mulai || ! $selesai || ! strtotime($mulai) || ! strtotime($selesai)) {
            return null;
        }

        return (new TugasBatchGenerator())->tentukanFrekuensiAkhir($frekuensi, Carbon::parse($mulai), Carbon::parse($selesai));
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lolos**

Run: `php artisan test --filter=StoreKasusTugasBatchRequestTest`
Expected: PASS (7 test)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/StoreKasusTugasBatchRequest.php tests/Feature/StoreKasusTugasBatchRequestTest.php
git commit -m "feat(pendampingan): add fallback-aware FormRequest for the tugas batch form"
```

---

### Task 4: Controller submit — `KasusTugasController::store()` pakai FormRequest + service + notifikasi ringkasan per-batch

**Files:**
- Create: `app/Notifications/TugasBatchDibuatNotification.php`
- Create: `app/Mail/TugasBatchDibuatMail.php`
- Create: `resources/views/mail/tugas-batch-dibuat.blade.php`
- Modify: `app/Http/Controllers/KasusTugasController.php`
- Modify: `tests/Feature/KasusTugasBeriTest.php`

**Interfaces:**
- Consumes: `StoreKasusTugasBatchRequest` (Task 3), `TugasBatchGenerator::generate()` (Task 2).
- Produces: `KasusTugasController::store(StoreKasusTugasBatchRequest $request, Kasus $kasus):
  RedirectResponse` — signature method berubah (tipe request-nya), route name
  (`kasus.tugas.store`) dan URL TIDAK berubah. `markSelesai()` tidak disentuh sama sekali.
  `App\Notifications\TugasBatchDibuatNotification(Collection $barisTugas)` — SATU notifikasi
  per penerima (siswa & orang tua kontak utama) untuk SELURUH batch, menggantikan pola lama
  yang mengirim satu notifikasi `TugasDitugaskanNotification` per baris. **Keputusan desain
  (dikonfirmasi user 2026-08-06):** batch besar (harian 30 hari, bulanan 6 bulan) tidak boleh
  membanjiri siswa/orang tua dengan puluhan notifikasi terpisah dalam satu submit — cukup satu
  notifikasi ringkasan ("Tugas baru: Jurnal Emosi Harian, 30 baris, 1-30 Agustus").
  `TugasDitugaskanNotification`/`TugasDitugaskanMail` yang lama TETAP ADA tanpa perubahan (masih
  dipakai di tempat lain kalau ada — periksa dulu dengan `grep -rn TugasDitugaskanNotification
  app/` sebelum menghapus apa pun; kalau ternyata tidak dipakai di tempat lain sama sekali
  setelah task ini, itu di luar cakupan task ini untuk dihapus, biarkan saja sebagai kode yang
  sudah tidak dipanggil tapi tidak mengganggu).

- [ ] **Step 1: Tulis `TugasBatchDibuatNotification`, `TugasBatchDibuatMail`, dan view mail-nya**

```php
<?php
// app/Notifications/TugasBatchDibuatNotification.php

namespace App\Notifications;

use App\Mail\TugasBatchDibuatMail;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class TugasBatchDibuatNotification extends Notification
{
    /**
     * @param Collection<int, \App\Models\KasusTugas> $barisTugas
     */
    public function __construct(public Collection $barisTugas)
    {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (filled($notifiable->routeNotificationFor('mail'))) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): TugasBatchDibuatMail
    {
        $mail = new TugasBatchDibuatMail($this->barisTugas);

        $email = $notifiable->routeNotificationFor('mail');

        if ($email !== null && $email !== '') {
            $mail->to($email);
        }

        return $mail;
    }

    public function toDatabase(object $notifiable): array
    {
        $pertama = $this->barisTugas->first();

        return [
            'kasus_tugas_batch_id' => $pertama->batch_id,
            'jumlah_baris' => $this->barisTugas->count(),
            'message' => 'Tugas baru "'.$pertama->judul.'" telah diberikan ('
                .$this->barisTugas->count().' baris, '.ucfirst($pertama->frekuensi).').',
        ];
    }
}
```

```php
<?php
// app/Mail/TugasBatchDibuatMail.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class TugasBatchDibuatMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param Collection<int, \App\Models\KasusTugas> $barisTugas
     */
    public function __construct(public Collection $barisTugas)
    {
    }

    public function build(): self
    {
        $pertama = $this->barisTugas->first();
        $mulaiPada = $this->barisTugas->sortBy('mulai_pada')->first()->mulai_pada;
        $batasSelesaiPada = $this->barisTugas->sortByDesc('batas_selesai_pada')->first()->batas_selesai_pada;

        return $this->subject('Tugas Pendampingan Baru: '.$pertama->judul)
            ->view('mail.tugas-batch-dibuat')
            ->with([
                'judul' => $pertama->judul,
                'frekuensi' => $pertama->frekuensi,
                'jumlahBaris' => $this->barisTugas->count(),
                'mulaiPada' => $mulaiPada,
                'batasSelesaiPada' => $batasSelesaiPada,
            ]);
    }
}
```

```blade
{{-- resources/views/mail/tugas-batch-dibuat.blade.php --}}
<p>
    Tugas baru "{{ $judul }}" ({{ ucfirst($frekuensi) }}) telah diberikan — {{ $jumlahBaris }}
    baris tugas, dari {{ $mulaiPada->format('d M Y') }} sampai {{ $batasSelesaiPada->format('d M Y') }}.
</p>
```

- [ ] **Step 2: Baca dulu `tests/Feature/KasusTugasBeriTest.php` yang sudah ada secara utuh**

File ini punya test yang mengasumsikan payload lama (`tugas` sebagai array multi-baris) — SEMUA
test yang mengirim payload lewat `route('kasus.tugas.store', ...)` perlu ditulis ulang memakai
bentuk payload BARU (satu definisi tugas, bukan array). Baca isi file saat ini dulu (termasuk
helper `buatKasusDitugaskanKeGuruBkUntukTugas()`) sebelum mengubah — pertahankan
struktur/nama helper yang ada, ganti hanya payload dan asersi tiap `it(...)` yang perlu
disesuaikan dengan model baru.

- [ ] **Step 3: Tulis ulang test-test yang mengirim payload lama**

Ganti isi `tests/Feature/KasusTugasBeriTest.php` — pertahankan helper
`buatKasusDitugaskanKeGuruBkUntukTugas()` apa adanya, ganti seluruh `it(...)` yang mengirim
`route('kasus.tugas.store', ...)` dengan array `tugas` lama menjadi bentuk baru. Contoh
penggantian untuk beberapa test kunci (terapkan pola yang sama ke SEMUA test lain di file yang
mengirim ke route ini):

```php
it('lets the assigned konselor give a sekali tugas', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);

    $this->actingAs($konselorUser)->post(route('kasus.tugas.store', $kasus), [
        'judul' => 'Refleksi Mingguan',
        'instruksi' => 'Tulis refleksi kegiatan minggu ini.',
        'frekuensi' => 'sekali',
        'tanggal_mulai' => now()->toDateString(),
        'tanggal_selesai' => now()->addDays(3)->toDateString(),
    ])->assertRedirect(route('kasus.show', $kasus));

    expect(\App\Models\KasusTugas::where('kasus_id', $kasus->id)->count())->toBe(1);
});

it('generates multiple tugas rows sharing one batch_id for a harian submission', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);

    $this->actingAs($konselorUser)->post(route('kasus.tugas.store', $kasus), [
        'judul' => 'Jurnal Emosi', 'instruksi' => 'Tulis jurnal harian.', 'frekuensi' => 'harian',
        'tanggal_mulai' => '2026-08-10', 'tanggal_selesai' => '2026-08-12',
    ])->assertRedirect(route('kasus.show', $kasus));

    $barisBatch = \App\Models\KasusTugas::where('kasus_id', $kasus->id)->orderBy('batch_urutan')->get();
    expect($barisBatch)->toHaveCount(3);
    expect($barisBatch->pluck('batch_id')->unique())->toHaveCount(1);
    expect($barisBatch->pluck('batch_total')->unique()->first())->toBe(3);
    expect($barisBatch->pluck('batch_urutan')->all())->toBe([1, 2, 3]);
});

it('rejects an invalid payload and creates no row', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);

    $this->actingAs($konselorUser)->post(route('kasus.tugas.store', $kasus), [
        'judul' => '', 'instruksi' => 'Y', 'frekuensi' => 'sekali',
        'tanggal_mulai' => now()->toDateString(), 'tanggal_selesai' => now()->toDateString(),
    ])->assertSessionHasErrors('judul');

    expect(\App\Models\KasusTugas::where('kasus_id', $kasus->id)->count())->toBe(0);
});
```

Untuk test yang menguji notifikasi (`'notifies siswa and orang tua when a tugas is given'`,
`'does not 500 when notifying...'`, `'does not call Fonnte...'`) — ganti payload-nya ke bentuk
baru juga, DAN ganti referensi kelas notifikasi dari `TugasDitugaskanNotification` ke
`TugasBatchDibuatNotification`, dan ganti asersi jumlah pengiriman: sekarang **SATU** notifikasi
terkirim per penerima (siswa & orang tua kontak utama) untuk SELURUH batch, TIDAK PEDULI berapa
banyak baris `KasusTugas` yang dihasilkan (`Notification::assertSentTimes(TugasBatchDibuatNotification::class,
1)` per penerima, bukan dikalikan jumlah baris seperti pola lama). Untuk test "does not 500 when
notifying... for real (no Notification::fake)" — pastikan payload menghasilkan batch dengan
LEBIH DARI SATU baris (mis. `frekuensi: 'harian'` rentang 3 hari) supaya test itu benar-benar
membuktikan kirim-mail-sungguhan bekerja untuk kasus banyak-baris, bukan cuma kasus satu baris.

Untuk test guard status kasus (`'403s a POST to give tugas against an already-selesai kasus'`,
`'menunggu_consent'`, `'diajukan'`, `'lets the assigned konselor give tugas against a berjalan
kasus'`) — cukup ganti payload ke bentuk baru (`sekali` dengan satu tanggal), esensi pengujian
(guard status) tidak berubah.

- [ ] **Step 4: Jalankan test, pastikan gagal (kode controller belum berubah)**

Run: `php artisan test --filter=KasusTugasBeriTest`
Expected: FAIL — controller masih mengharapkan payload array `tugas.*`, bukan bentuk baru.

- [ ] **Step 5: Tulis ulang `KasusTugasController::store()`**

```php
<?php
// app/Http/Controllers/KasusTugasController.php

namespace App\Http\Controllers;

use App\Enums\StatusKasus;
use App\Http\Requests\StoreKasusTugasBatchRequest;
use App\Models\Kasus;
use App\Models\KasusTugas;
use App\Models\Scopes\TenantScope;
use App\Notifications\TugasBatchDibuatNotification;
use App\Notifications\TugasSelesaiNotification;
use App\Services\TugasBatchGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KasusTugasController extends BaseController
{
    use AuthorizesRequests;

    public function store(StoreKasusTugasBatchRequest $request, Kasus $kasus, TugasBatchGenerator $generator): RedirectResponse
    {
        $this->authorize('kasus.view');
        $this->assertKonselorPemegangKasus($kasus);
        abort_if($kasus->trashed(), 404);
        abort_unless(in_array($kasus->status, [StatusKasus::Ditugaskan, StatusKasus::Berjalan, StatusKasus::Eskalasi], true), 403);

        $data = $request->validated();

        $tanggalPengumpulanBulanan = null;
        $akhirBulan = false;
        if (($data['tanggal_pengumpulan_bulanan'] ?? null) === 'akhir_bulan') {
            $akhirBulan = true;
        } elseif (! empty($data['tanggal_pengumpulan_bulanan'])) {
            $tanggalPengumpulanBulanan = (int) $data['tanggal_pengumpulan_bulanan'];
        }

        $barisTanggal = $generator->generate(
            $data['frekuensi'],
            Carbon::parse($data['tanggal_mulai']),
            Carbon::parse($data['tanggal_selesai']),
            $tanggalPengumpulanBulanan,
            $akhirBulan,
        );

        $created = DB::transaction(function () use ($data, $kasus, $barisTanggal) {
            $batchId = (string) Str::uuid();
            $batchTotal = $barisTanggal->count();

            $rows = $barisTanggal->values()->map(fn ($baris, $index) => KasusTugas::create([
                'kasus_id' => $kasus->id,
                'judul' => $data['judul'],
                'instruksi' => $data['instruksi'],
                'frekuensi' => $data['frekuensi'],
                'batch_id' => $batchId,
                'batch_urutan' => $index + 1,
                'batch_total' => $batchTotal,
                'mulai_pada' => $baris['mulai_pada'],
                'batas_selesai_pada' => $baris['batas_selesai_pada'],
            ]));

            if ($kasus->status->value === 'ditugaskan') {
                $kasus->update(['status' => 'berjalan']);
            }

            return $rows;
        });

        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
        $kontakUtama = $siswa?->orangTua()->wherePivot('is_kontak_utama', true)->first();
        $siswaUser = $siswa?->user()->withoutGlobalScope(TenantScope::class)->first();

        // Satu notifikasi ringkasan per penerima untuk SELURUH batch, bukan satu notifikasi
        // per baris — sebuah batch harian/bulanan yang panjang bisa menghasilkan puluhan
        // baris kasus_tugas dalam satu submit, dan mengirim notifikasi terpisah untuk
        // masing-masing akan membanjiri siswa/orang tua (keputusan desain 2026-08-06).
        $siswaUser?->notify(new TugasBatchDibuatNotification($created));
        $kontakUtama?->notify(new TugasBatchDibuatNotification($created));

        return redirect()->route('kasus.show', $kasus)->with('status', "Tugas berhasil diberikan ({$created->count()} baris dibuat).");
    }

    public function markSelesai(Kasus $kasus, KasusTugas $kasusTugas): RedirectResponse
    {
        $this->authorize('kasus.view');
        abort_if($kasusTugas->kasus_id !== $kasus->id, 404);
        $this->assertKonselorPemegangKasus($kasus);

        $kasusTugas->update(['status' => 'selesai']);

        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
        $kontakUtama = $siswa?->orangTua()->wherePivot('is_kontak_utama', true)->first();
        $kontakUtama?->notify(new TugasSelesaiNotification($kasusTugas));

        return redirect()->route('kasus.show', $kasus)->with('status', 'Tugas ditandai selesai.');
    }

    private function assertKonselorPemegangKasus(Kasus $kasus): void
    {
        $user = auth()->user();
        $karyawanId = $user->karyawan()->withoutGlobalScope(TenantScope::class)->first()?->id;
        $isKonselor = ($kasus->konselor_guru_id !== null && $kasus->konselor_guru_id === $user->guru?->id)
            || ($kasus->konselor_karyawan_id !== null && $kasus->konselor_karyawan_id === $karyawanId);

        abort_unless($isKonselor, 403);
    }
}
```

`markSelesai()` dan `assertKonselorPemegangKasus()` disalin apa adanya dari file sebelumnya —
tidak ada perubahan perilaku di kedua method ini, hanya ikut ditulis ulang di sini karena
seluruh isi file diganti.

- [ ] **Step 6: Jalankan test, pastikan lolos**

Run: `php artisan test --filter=KasusTugasBeriTest`
Expected: PASS (jumlah test sama seperti sebelum ditulis ulang, semua lolos)

- [ ] **Step 7: Jalankan regresi Kasus**

Run: `php artisan test --filter=Kasus`
Expected: kegagalan yang SAMA seperti di akhir Task 1 (test yang bergantung pada mekanisme lama
— dihapus di Task 6), tidak ada kegagalan BARU selain itu.

- [ ] **Step 8: Commit**

```bash
git add app/Notifications/TugasBatchDibuatNotification.php app/Mail/TugasBatchDibuatMail.php resources/views/mail/tugas-batch-dibuat.blade.php app/Http/Controllers/KasusTugasController.php tests/Feature/KasusTugasBeriTest.php
git commit -m "feat(pendampingan): wire KasusTugasController::store() to the batch generator, notify once per batch"
```

---

### Task 5: Endpoint preview (dry-run)

**Files:**
- Create: `app/Http/Controllers/KasusTugasBatchPreviewController.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/KasusTugasBatchPreviewTest.php`

**Interfaces:**
- Consumes: `StoreKasusTugasBatchRequest` (Task 3, dipakai ulang untuk validasi input preview
  yang sama persis dengan submit sungguhan), `TugasBatchGenerator::generate()` (Task 2).
- Produces: route `kasus.tugas.preview` (POST) mengembalikan JSON
  `{frekuensi_akhir: string, jumlah_baris: int, baris: [{mulai_pada: string, batas_selesai_pada: string}, ...]}`.
  Dipakai Task 7's JavaScript live-preview.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php
// tests/Feature/KasusTugasBatchPreviewTest.php

use App\Models\Lembaga;
use App\Models\Yayasan;

it('returns a JSON preview without creating any kasus_tugas row', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);

    $response = $this->actingAs($konselorUser)->postJson(route('kasus.tugas.preview', $kasus), [
        'judul' => 'Jurnal Emosi', 'instruksi' => 'Tulis jurnal harian.', 'frekuensi' => 'mingguan',
        'tanggal_mulai' => '2026-08-01', 'tanggal_selesai' => '2026-08-05',
    ]);

    $response->assertOk();
    $response->assertJson(['frekuensi_akhir' => 'harian', 'jumlah_baris' => 5]);
    expect(\App\Models\KasusTugas::where('kasus_id', $kasus->id)->count())->toBe(0);
});

it('returns the exact same row dates the real submit would create', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);
    $payload = [
        'judul' => 'Jurnal Emosi', 'instruksi' => 'Tulis jurnal harian.', 'frekuensi' => 'harian',
        'tanggal_mulai' => '2026-08-10', 'tanggal_selesai' => '2026-08-12',
    ];

    $preview = $this->actingAs($konselorUser)->postJson(route('kasus.tugas.preview', $kasus), $payload);
    $this->actingAs($konselorUser)->post(route('kasus.tugas.store', $kasus), $payload);

    $barisAsli = \App\Models\KasusTugas::where('kasus_id', $kasus->id)->orderBy('batch_urutan')->pluck('mulai_pada')->map->toDateString()->all();
    $barisPreview = collect($preview->json('baris'))->pluck('mulai_pada')->all();

    expect($barisPreview)->toBe($barisAsli);
});

it('403s a user who is not the assigned konselor from previewing', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);
    $strangerUser = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($strangerUser)->postJson(route('kasus.tugas.preview', $kasus), [
        'judul' => 'X', 'instruksi' => 'Y', 'frekuensi' => 'sekali',
        'tanggal_mulai' => '2026-08-01', 'tanggal_selesai' => '2026-08-01',
    ])->assertForbidden();
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=KasusTugasBatchPreviewTest`
Expected: FAIL — route `kasus.tugas.preview` belum ada.

- [ ] **Step 3: Tambah route**

Di `routes/admin.php`, ubah:
```php
    Route::post('{kasus}/tugas', [KasusTugasController::class, 'store'])->name('tugas.store');
```
menjadi:
```php
    Route::post('{kasus}/tugas', [KasusTugasController::class, 'store'])->name('tugas.store');
    Route::post('{kasus}/tugas/preview', [KasusTugasBatchPreviewController::class, 'preview'])->name('tugas.preview');
```
Tambahkan import di bagian atas file, sejajar dengan `use App\Http\Controllers\KasusTugasController;` yang sudah ada:
```php
use App\Http\Controllers\KasusTugasBatchPreviewController;
```

- [ ] **Step 4: Tulis controller preview-nya**

```php
<?php
// app/Http/Controllers/KasusTugasBatchPreviewController.php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKasusTugasBatchRequest;
use App\Models\Kasus;
use App\Models\Scopes\TenantScope;
use App\Services\TugasBatchGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;

class KasusTugasBatchPreviewController extends BaseController
{
    use AuthorizesRequests;

    public function preview(StoreKasusTugasBatchRequest $request, Kasus $kasus, TugasBatchGenerator $generator): JsonResponse
    {
        $this->authorize('kasus.view');
        $this->assertKonselorPemegangKasus($kasus);

        $data = $request->validated();

        $tanggalPengumpulanBulanan = null;
        $akhirBulan = false;
        if (($data['tanggal_pengumpulan_bulanan'] ?? null) === 'akhir_bulan') {
            $akhirBulan = true;
        } elseif (! empty($data['tanggal_pengumpulan_bulanan'])) {
            $tanggalPengumpulanBulanan = (int) $data['tanggal_pengumpulan_bulanan'];
        }

        $tanggalMulai = Carbon::parse($data['tanggal_mulai']);
        $tanggalSelesai = Carbon::parse($data['tanggal_selesai']);

        $frekuensiAkhir = $generator->tentukanFrekuensiAkhir($data['frekuensi'], $tanggalMulai, $tanggalSelesai);
        $barisTanggal = $generator->generate($data['frekuensi'], $tanggalMulai, $tanggalSelesai, $tanggalPengumpulanBulanan, $akhirBulan);

        return response()->json([
            'frekuensi_akhir' => $frekuensiAkhir,
            'jumlah_baris' => $barisTanggal->count(),
            'baris' => $barisTanggal->map(fn ($baris) => [
                'mulai_pada' => $baris['mulai_pada']->toDateString(),
                'batas_selesai_pada' => $baris['batas_selesai_pada']->toDateString(),
            ])->values(),
        ]);
    }

    private function assertKonselorPemegangKasus(Kasus $kasus): void
    {
        $user = auth()->user();
        $karyawanId = $user->karyawan()->withoutGlobalScope(TenantScope::class)->first()?->id;
        $isKonselor = ($kasus->konselor_guru_id !== null && $kasus->konselor_guru_id === $user->guru?->id)
            || ($kasus->konselor_karyawan_id !== null && $kasus->konselor_karyawan_id === $karyawanId);

        abort_unless($isKonselor, 403);
    }
}
```

- [ ] **Step 5: Jalankan test, pastikan lolos**

Run: `php artisan test --filter=KasusTugasBatchPreviewTest`
Expected: PASS (3 test)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/KasusTugasBatchPreviewController.php routes/admin.php tests/Feature/KasusTugasBatchPreviewTest.php
git commit -m "feat(pendampingan): add dry-run preview endpoint backed by the same generator"
```

---

### Task 6: Copot mekanisme checklist-per-tanggal lama

**Files:**
- Modify: `app/Http/Controllers/KasusTugasSubmissionController.php`
- Delete: `tests/Feature/KasusTugasSubmissionHarianTest.php`
- Delete: `tests/Feature/KasusTugasHarianViewTest.php`
- Delete: `tests/Feature/KasusTugasSubmissionTanggalSchemaTest.php`
- Delete: `tests/Feature/KasusTugasSubmissionTanggalBackfillTest.php`
- Delete: `database/migrations/2026_08_06_100000_add_tanggal_to_kasus_tugas_submission.php`
- Delete: `database/migrations/2026_08_07_000000_backfill_tanggal_kasus_tugas_submission_harian.php`

**Interfaces:**
- Produces: `KasusTugasSubmissionController::store()` kembali ke SATU alur submit generik untuk
  SEMUA frekuensi (tidak ada lagi cabang `if ($kasusTugas->frekuensi === 'harian')`) — dipakai
  Task 7's tampilan tunggal (tidak ada lagi dua cabang tampilan).

- [ ] **Step 1: Hapus file migrasi lama yang sudah tidak relevan**

Migrasi `2026_08_06_100000_add_tanggal_to_kasus_tugas_submission.php` dan
`2026_08_07_000000_backfill_tanggal_kasus_tugas_submission_harian.php` sudah digantikan oleh
migrasi drop-kolom di Task 1 — menghapusnya (bukan membiarkannya dengan `down()` yang tak
pernah dipanggil) mencegah kebingungan riwayat migrasi kalau ada yang membaca urutan migrasi
nanti. **Sebelum menghapus, konfirmasi migrasi Task 1
(`2026_08_08_090100_drop_tanggal_from_kasus_tugas_submission.php`) sudah benar-benar berjalan
(`php artisan migrate:status`) — urutan penghapusan yang salah bisa merusak riwayat migrasi
kalau belum.**

```bash
git rm database/migrations/2026_08_06_100000_add_tanggal_to_kasus_tugas_submission.php
git rm database/migrations/2026_08_07_000000_backfill_tanggal_kasus_tugas_submission_harian.php
```

- [ ] **Step 2: Hapus 4 file test yang menguji mekanisme lama**

```bash
git rm tests/Feature/KasusTugasSubmissionHarianTest.php
git rm tests/Feature/KasusTugasHarianViewTest.php
git rm tests/Feature/KasusTugasSubmissionTanggalSchemaTest.php
git rm tests/Feature/KasusTugasSubmissionTanggalBackfillTest.php
```

- [ ] **Step 3: Kembalikan `KasusTugasSubmissionController::store()`/`review()` ke bentuk sebelum 2026-08-05**

Ganti isi `app/Http/Controllers/KasusTugasSubmissionController.php` seluruhnya menjadi:

```php
<?php
// app/Http/Controllers/KasusTugasSubmissionController.php

namespace App\Http\Controllers;

use App\Models\Kasus;
use App\Models\KasusConsent;
use App\Models\KasusTugas;
use App\Models\KasusTugasSubmission;
use App\Models\Scopes\TenantScope;
use App\Notifications\SubmissionRevisiNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KasusTugasSubmissionController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request, Kasus $kasus, KasusTugas $kasusTugas): RedirectResponse
    {
        $this->authorize('kasus.view');
        abort_if($kasusTugas->kasus_id !== $kasus->id, 404);
        abort_if(! in_array($kasusTugas->status->value, ['ditugaskan', 'dikerjakan', 'revisi'], true), 403);

        $user = $request->user();
        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
        abort_if($siswa === null, 404);

        $isSiswaTerkait = $user->siswa !== null && $user->siswa->id === $siswa->id;
        $isKontakUtama = $user->orangTua !== null
            && $siswa->orangTua()->where('orang_tua_id', $user->orangTua->id)->wherePivot('is_kontak_utama', true)->exists();

        abort_unless($isSiswaTerkait || $isKontakUtama, 403);

        $mediaDisetujui = KasusConsent::where('kasus_id', $kasus->id)
            ->where('jenis', 'pengumpulan_media')->where('status', 'disetujui')->exists();
        $hasLampiran = $mediaDisetujui && $request->hasFile('lampiran');

        $rules = ['teks' => [$hasLampiran ? 'nullable' : 'required', 'string']];
        if ($mediaDisetujui) {
            $rules['lampiran'] = ['nullable', 'file', 'mimes:jpg,jpeg,png,mp4,mov', 'max:20480'];
        }
        $data = $request->validate($rules);

        $lampiranPath = ($mediaDisetujui && $request->hasFile('lampiran'))
            ? $request->file('lampiran')->store('kasus-tugas-lampiran', 'local')
            : null;

        KasusTugasSubmission::create([
            'tugas_id' => $kasusTugas->id,
            'siswa_id' => $isSiswaTerkait ? $siswa->id : null,
            'orang_tua_id' => $isSiswaTerkait ? null : $user->orangTua->id,
            'teks' => $data['teks'] ?? null,
            'lampiran' => $lampiranPath,
        ]);

        return redirect()->route('kasus.show', $kasus)->with('status', 'Bukti pengerjaan berhasil dikirim.');
    }

    public function review(Request $request, Kasus $kasus, KasusTugas $kasusTugas, KasusTugasSubmission $kasusTugasSubmission): RedirectResponse
    {
        $this->authorize('kasus.view');
        abort_if($kasusTugas->kasus_id !== $kasus->id, 404);
        abort_if($kasusTugasSubmission->tugas_id !== $kasusTugas->id, 404);
        $this->assertKonselorPemegangKasus($kasus);

        $data = $request->validate([
            'status_review' => ['required', 'in:diterima,revisi_diminta'],
            'catatan_revisi' => ['required_if:status_review,revisi_diminta', 'nullable', 'string'],
        ]);

        $kasusTugasSubmission->update([
            'status_review' => $data['status_review'],
            'catatan_revisi' => $data['catatan_revisi'] ?? null,
        ]);

        if ($data['status_review'] === 'revisi_diminta') {
            $kasusTugas->update(['status' => 'revisi']);

            $notifiable = $kasusTugasSubmission->siswa_id !== null
                ? $kasusTugasSubmission->siswa()->withoutGlobalScope(TenantScope::class)->first()
                    ?->user()->withoutGlobalScope(TenantScope::class)->first()
                : $kasusTugasSubmission->orangTua;
            $notifiable?->notify(new SubmissionRevisiNotification($kasusTugasSubmission));
        }

        return redirect()->route('kasus.show', $kasus)->with('status', 'Review submission berhasil disimpan.');
    }

    public function download(Kasus $kasus, KasusTugas $kasusTugas, KasusTugasSubmission $kasusTugasSubmission): StreamedResponse
    {
        $this->authorize('kasus.view');
        abort_if($kasusTugas->kasus_id !== $kasus->id, 404);
        abort_if($kasusTugasSubmission->tugas_id !== $kasusTugas->id, 404);
        abort_if($kasusTugasSubmission->lampiran === null, 404);

        $user = auth()->user();
        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
        abort_if($siswa === null, 404);

        $isSubmitter = ($kasusTugasSubmission->siswa_id !== null && $kasusTugasSubmission->siswa_id === $user->siswa?->id)
            || ($kasusTugasSubmission->orang_tua_id !== null && $kasusTugasSubmission->orang_tua_id === $user->orangTua?->id);
        $karyawanId = $user->karyawan()->withoutGlobalScope(TenantScope::class)->first()?->id;
        $isKonselor = ($kasus->konselor_guru_id !== null && $kasus->konselor_guru_id === $user->guru?->id)
            || ($kasus->konselor_karyawan_id !== null && $kasus->konselor_karyawan_id === $karyawanId);
        $isKontakUtama = $user->orangTua !== null
            && $siswa->orangTua()->where('orang_tua_id', $user->orangTua->id)->wherePivot('is_kontak_utama', true)->exists();
        $isTriaseAdmin = $user->can('kasus.triase')
            && ($user->widestScopeLevel() === 'yayasan' || $kasus->lembaga_id === $user->lembaga_id);

        abort_if(! $isSubmitter && ! $isKonselor && ! $isKontakUtama && ! $isTriaseAdmin, 404);

        return Storage::disk('local')->download($kasusTugasSubmission->lampiran);
    }

    private function assertKonselorPemegangKasus(Kasus $kasus): void
    {
        $user = auth()->user();
        $karyawanId = $user->karyawan()->withoutGlobalScope(TenantScope::class)->first()?->id;
        $isKonselor = ($kasus->konselor_guru_id !== null && $kasus->konselor_guru_id === $user->guru?->id)
            || ($kasus->konselor_karyawan_id !== null && $kasus->konselor_karyawan_id === $karyawanId);

        abort_unless($isKonselor, 403);
    }
}
```

- [ ] **Step 4: Jalankan regresi Kasus penuh**

Run: `php artisan test --filter=Kasus`
Expected: SEMUA lolos sekarang — tidak ada lagi test yang bergantung pada mekanisme yang sudah
dicopot (dihapus di Step 2), dan `KasusTugasSubmissionTest.php`/`KasusTugasReviewTest.php`
(yang menguji `sekali`/frekuensi lain, tidak pernah menyentuh mekanisme harian) tetap lolos
tanpa perubahan apapun pada file itu sendiri.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/KasusTugasSubmissionController.php
git add -u tests/Feature/KasusTugasSubmissionHarianTest.php tests/Feature/KasusTugasHarianViewTest.php tests/Feature/KasusTugasSubmissionTanggalSchemaTest.php tests/Feature/KasusTugasSubmissionTanggalBackfillTest.php
git add -u database/migrations/2026_08_06_100000_add_tanggal_to_kasus_tugas_submission.php database/migrations/2026_08_07_000000_backfill_tanggal_kasus_tugas_submission_harian.php
git commit -m "refactor(pendampingan): remove the per-date checklist mechanism, superseded by batch generation"
```

---

### Task 7: UI Blade — form satu-definisi + live preview + tampilan terkelompok per batch

**Files:**
- Modify: `resources/views/kasus/partials/_tab-tugas.blade.php`
- Test: `tests/Feature/KasusTugasBatchViewTest.php`

**Interfaces:**
- Consumes: route `kasus.tugas.preview` (Task 5), route `kasus.tugas.store` (Task 4, bentuk
  payload baru), `KasusTugas::$batch_id`/`$batch_urutan`/`$batch_total` (Task 1).

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php
// tests/Feature/KasusTugasBatchViewTest.php

use App\Models\Kasus;
use App\Models\KasusTugas;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Support\Str;

it('shows the single-definition tugas form fields, not the old multi-row form', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);

    $response = $this->actingAs($konselorUser)->get(route('kasus.show', $kasus));

    $response->assertOk();
    $response->assertSee('name="judul"', false);
    $response->assertSee('name="tanggal_mulai"', false);
    $response->assertSee('name="tanggal_selesai"', false);
    $response->assertDontSee('Tambah Baris');
});

it('groups tugas rows from the same batch under one header showing progress', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);
    $batchId = (string) Str::uuid();
    foreach ([1, 2, 3] as $urutan) {
        KasusTugas::factory()->create([
            'kasus_id' => $kasus->id, 'judul' => 'Jurnal Emosi', 'frekuensi' => 'harian',
            'batch_id' => $batchId, 'batch_urutan' => $urutan, 'batch_total' => 3,
            'mulai_pada' => now()->addDays($urutan), 'batas_selesai_pada' => now()->addDays($urutan),
        ]);
    }

    $response = $this->actingAs($konselorUser)->get(route('kasus.show', $kasus));

    $response->assertOk();
    $response->assertSeeInOrder(['Jurnal Emosi', 'Hari 1 dari 3', 'Hari 2 dari 3', 'Hari 3 dari 3']);
});

it('does not render the removed per-tanggal checklist markup anywhere', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);
    KasusTugas::factory()->create(['kasus_id' => $kasus->id, 'frekuensi' => 'harian']);

    $response = $this->actingAs($konselorUser)->get(route('kasus.show', $kasus));

    $response->assertOk();
    $response->assertDontSee('Checklist Harian');
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=KasusTugasBatchViewTest`
Expected: FAIL — form masih bentuk lama, belum ada pengelompokan batch di tampilan.

- [ ] **Step 3: Tulis ulang `_tab-tugas.blade.php`**

Baca dulu isi file saat ini secara utuh (sudah dibaca sepanjang percakapan brainstorming —
sekitar 397 baris, terdiri dari: header + tombol toggle form, form multi-baris lama, dan loop
`@foreach ($kasus->tugas as $tugas)` dengan dua cabang `harian`/`else`). Ganti SELURUH isi file
menjadi:

```blade
<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-6" x-data="tugasBatchForm()">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 pb-4">
        <div>
            <h3 class="font-display text-base font-bold text-gray-900">Tugas & Refleksi Pendampingan</h3>
            <p class="text-xs text-gray-500 mt-0.5">Penugasan mandiri bagi siswa untuk dikerjakan dan disetujui selama periode pembinaan.</p>
        </div>

        @if ($isKonselor && in_array($kasus->status->value, ['ditugaskan', 'berjalan', 'eskalasi'], true))
            <button
                type="button"
                @click="showForm = !showForm"
                :class="showForm ? 'bg-gray-100 text-gray-700 border-gray-300' : 'bg-brand-500 text-white border-transparent shadow-sm hover:bg-brand-600'"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border text-xs font-bold transition whitespace-nowrap"
            >
                <x-icon name="checklist" class="h-4 w-4" />
                <span x-text="showForm ? 'Tutup Formulir' : '+ Beri Tugas Pendampingan'">+ Beri Tugas Pendampingan</span>
            </button>
        @endif
    </div>

    {{-- Formulir Satu Definisi Tugas --}}
    @if ($isKonselor && in_array($kasus->status->value, ['ditugaskan', 'berjalan', 'eskalasi'], true))
        <div x-show="showForm" style="display: none;" x-transition:enter="transition ease-out duration-200" class="rounded-xl border border-brand-200 bg-brand-50/20 p-5 shadow-2xs">
            <h4 class="font-display text-sm font-bold text-gray-900 mb-1">Formulir Pemberian Tugas Baru</h4>
            <p class="text-xs text-gray-500 mb-4">Sistem akan membuat baris tugas sesuai frekuensi dan rentang tanggal yang dipilih.</p>

            <form method="POST" action="{{ route('kasus.tugas.store', $kasus) }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-12">
                    <div class="sm:col-span-8">
                        <x-input-label value="Judul Tugas *" class="text-xs font-bold text-gray-700" />
                        <input type="text" name="judul" x-model="form.judul" required placeholder="Contoh: Jurnal Emosi Harian / Evaluasi Sikap" class="mt-1.5 block w-full rounded-lg border-gray-200 text-xs font-semibold text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div class="sm:col-span-4">
                        <x-input-label value="Frekuensi *" class="text-xs font-bold text-gray-700" />
                        <select name="frekuensi" x-model="form.frekuensi" @change="pratinjau()" class="mt-1.5 block w-full rounded-lg border-gray-200 text-xs font-semibold text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                            <option value="sekali">Sekali (One-off)</option>
                            <option value="harian">Harian</option>
                            <option value="mingguan">Mingguan</option>
                            <option value="bulanan">Bulanan</option>
                        </select>
                    </div>
                    <div class="sm:col-span-12">
                        <x-input-label value="Instruksi Pengerjaan *" class="text-xs font-bold text-gray-700" />
                        <textarea name="instruksi" x-model="form.instruksi" rows="2" required placeholder="Jelaskan langkah-langkah detail pengerjaan atau pertanyaan yang harus dijawab siswa..." class="mt-1.5 block w-full rounded-lg border-gray-200 text-xs font-medium text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500"></textarea>
                    </div>
                    <div class="sm:col-span-4">
                        <x-input-label value="Tanggal Mulai *" class="text-xs font-bold text-gray-700" />
                        <input type="date" name="tanggal_mulai" x-model="form.tanggal_mulai" @change="pratinjau()" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-xs font-semibold text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div class="sm:col-span-4">
                        <x-input-label value="Tanggal Selesai *" class="text-xs font-bold text-gray-700" />
                        <input type="date" name="tanggal_selesai" x-model="form.tanggal_selesai" @change="pratinjau()" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-xs font-semibold text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div class="sm:col-span-4" x-show="frekuensiAkhir === 'bulanan'" style="display: none;">
                        <x-input-label value="Tanggal Pengumpulan Bulanan *" class="text-xs font-bold text-gray-700" />
                        <select name="tanggal_pengumpulan_bulanan" x-model="form.tanggal_pengumpulan_bulanan" @change="pratinjau()" class="mt-1.5 block w-full rounded-lg border-gray-200 text-xs font-semibold text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Pilih tanggal...</option>
                            <template x-for="hari in 31" :key="hari">
                                <option :value="hari" x-text="hari"></option>
                            </template>
                            <option value="akhir_bulan">Hari terakhir bulan</option>
                        </select>
                    </div>
                </div>

                {{-- Pratinjau Real-Time --}}
                <div x-show="pratinjauLoaded" style="display: none;" class="rounded-lg border p-3" :class="frekuensiAkhir !== form.frekuensi ? 'border-amber-300 bg-amber-50/60' : 'border-gray-200 bg-gray-50'">
                    <p class="text-xs font-bold flex items-center gap-1.5" :class="frekuensiAkhir !== form.frekuensi ? 'text-amber-800' : 'text-gray-700'">
                        <x-icon name="warning" class="h-3.5 w-3.5" x-show="frekuensiAkhir !== form.frekuensi" style="display: none;" />
                        <span x-text="frekuensiAkhir !== form.frekuensi
                            ? `Rentang tidak memenuhi syarat '${form.frekuensi}' — akan diproses sebagai '${frekuensiAkhir}'`
                            : `Akan dibuat sebagai '${frekuensiAkhir}'`"></span>
                    </p>
                    <p class="text-xs text-gray-600 mt-1">Jumlah baris tugas yang akan dibuat: <b x-text="jumlahBaris"></b></p>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2 pt-2">
                    <button type="button" @click="showForm = false" class="px-4 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition">
                        Batal
                    </button>
                    <x-primary-button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold shadow-sm">
                        Beri Tugas
                    </x-primary-button>
                </div>
            </form>
        </div>
    @endif

    {{-- Daftar Tugas Terkelompok per Batch --}}
    @if ($kasus->tugas->isNotEmpty())
        @php
            $tugasPerBatch = $kasus->tugas->sortBy('batch_urutan')->groupBy('batch_id');
        @endphp
        <div class="space-y-6">
            @foreach ($tugasPerBatch as $batchId => $barisBatch)
                @php $tugasPertama = $barisBatch->first(); @endphp
                <div class="space-y-3">
                    <p class="font-display text-sm font-bold text-gray-900 flex items-center gap-2">
                        <x-icon name="checklist" class="h-4 w-4 text-brand-500 shrink-0" />
                        {{ $tugasPertama->judul }}
                        <span class="capitalize bg-gray-100 px-2 py-0.5 rounded text-[11px] font-semibold text-gray-700">{{ ucfirst($tugasPertama->frekuensi) }}</span>
                    </p>

                    <div class="space-y-3 pl-2 border-l-2 border-gray-100">
                        @foreach ($barisBatch as $tugas)
                            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-2xs transition hover:shadow-card space-y-4">
                                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 border-b border-gray-100 pb-3.5">
                                    <div class="space-y-1">
                                        <p class="text-xs font-bold text-gray-700">
                                            @if ($tugas->batch_total > 1)
                                                {{ $tugas->frekuensi === 'harian' ? 'Hari' : ($tugas->frekuensi === 'mingguan' ? 'Minggu' : 'Bulan') }} {{ $tugas->batch_urutan }} dari {{ $tugas->batch_total }}
                                            @else
                                                Tugas
                                            @endif
                                        </p>
                                        <p class="text-xs font-semibold text-gray-500 flex items-center gap-2">
                                            <span class="text-amber-600 font-bold">Batas Selesai: {{ $tugas->batas_selesai_pada->format('d M Y') }}</span>
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <x-badge :tone="$tugas->status->value === 'selesai' ? 'green' : ($tugas->status->value === 'terlewat' ? 'red' : ($tugas->status->value === 'revisi' ? 'amber' : 'blue'))" class="text-xs font-bold px-3 py-1">
                                            {{ $tugas->status->label() }}
                                        </x-badge>

                                        @if ($isKonselor && ! in_array($tugas->status->value, ['selesai', 'terlewat'], true))
                                            <form method="POST" action="{{ route('kasus.tugas.selesai', [$kasus, $tugas]) }}">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-success-50 hover:bg-success-100 px-2.5 py-1 text-[11px] font-extrabold text-success-700 transition border border-success-200" title="Selesaikan tugas ini secara manual">
                                                    <x-icon name="check_circle" class="h-3.5 w-3.5" />
                                                    Tandai Selesai
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>

                                @if ($tugas->instruksi)
                                    <div class="rounded-lg bg-gray-50 p-3 text-xs text-gray-700 border border-gray-100">
                                        <span class="font-bold text-gray-600 block mb-1">Instruksi:</span>
                                        <p class="leading-relaxed font-medium text-gray-800">{{ $tugas->instruksi }}</p>
                                    </div>
                                @endif

                                @if ($tugas->submissions->isNotEmpty())
                                    <div class="space-y-2 pt-1">
                                        <p class="text-[11px] font-extrabold text-gray-400 uppercase tracking-wider">Riwayat Bukti & Hasil Pengerjaan:</p>
                                        <div class="space-y-2.5 divide-y divide-gray-100 rounded-xl border border-gray-100 bg-gray-50/50 p-3.5">
                                            @foreach ($tugas->submissions as $submission)
                                                <div class="pt-2.5 first:pt-0 space-y-2 text-xs">
                                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                                        <div>
                                                            <span class="font-bold text-gray-800">{{ $submission->created_at->format('d M Y H:i') }}:</span>
                                                            <span class="text-gray-700 ml-1 font-medium">{{ $submission->teks ?? '(Lampiran saja)' }}</span>
                                                        </div>
                                                        <div class="flex items-center gap-2 shrink-0">
                                                            @if ($submission->lampiran)
                                                                <a href="{{ route('kasus.tugas.submission.lampiran', [$kasus, $tugas, $submission]) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-bold text-brand-600 hover:text-brand-700 hover:underline bg-white px-2 py-1 rounded border border-brand-200 shadow-2xs text-[11px]">
                                                                    <x-icon name="description" class="h-3 w-3" />
                                                                    Lihat Lampiran
                                                                </a>
                                                            @endif
                                                            <x-badge :tone="$submission->status_review === 'diterima' ? 'green' : ($submission->status_review === 'revisi_diminta' ? 'amber' : 'slate')" class="text-[10px] font-extrabold">
                                                                {{ str_replace('_', ' ', ucfirst($submission->status_review)) }}
                                                            </x-badge>
                                                        </div>
                                                    </div>

                                                    @if ($isKonselor && $submission->status_review === 'menunggu_review')
                                                        <div x-data="{ revisi: false }" class="rounded-lg bg-white p-3 border border-gray-200 shadow-2xs mt-2">
                                                            <div class="flex items-center justify-between gap-3 text-xs">
                                                                <span class="font-bold text-gray-700">Tindakan Review:</span>
                                                                <div class="flex items-center gap-2">
                                                                    <form method="POST" action="{{ route('kasus.tugas.submission.review', [$kasus, $tugas, $submission]) }}">
                                                                        @csrf @method('PATCH')
                                                                        <input type="hidden" name="status_review" value="diterima">
                                                                        <button type="submit" class="inline-flex items-center gap-1 font-bold text-success-700 hover:bg-success-100 bg-success-50 px-3 py-1.5 rounded-lg transition border border-success-200">
                                                                            <x-icon name="check_circle" class="h-3.5 w-3.5" />
                                                                            Terima Hasil
                                                                        </button>
                                                                    </form>
                                                                    <button type="button" @click="revisi = !revisi" class="inline-flex items-center gap-1 font-bold text-amber-700 hover:bg-amber-100 bg-amber-50 px-3 py-1.5 rounded-lg transition border border-amber-200">
                                                                        <x-icon name="edit_note" class="h-3.5 w-3.5" />
                                                                        Minta Revisi
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <form x-show="revisi" style="display: none;" method="POST" action="{{ route('kasus.tugas.submission.review', [$kasus, $tugas, $submission]) }}" class="mt-3 pt-3 border-t border-gray-100 space-y-2">
                                                                @csrf @method('PATCH')
                                                                <input type="hidden" name="status_review" value="revisi_diminta">
                                                                <label class="block text-[11px] font-bold text-amber-900">Catatan Perbaikan untuk Siswa:</label>
                                                                <div class="flex items-center gap-2">
                                                                    <input type="text" name="catatan_revisi" required placeholder="Contoh: Harap lampirkan bukti foto refleksi..." class="block w-full rounded-lg border-gray-200 text-xs font-medium text-gray-900 shadow-2xs focus:border-amber-500 focus:ring-amber-500">
                                                                    <button type="submit" class="whitespace-nowrap font-bold text-white bg-amber-600 hover:bg-amber-700 px-4 py-2 rounded-lg text-xs transition shadow-sm">Kirim Catatan</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if (($isSiswaTerkait || $isKontakUtama) && in_array($tugas->status->value, ['ditugaskan', 'dikerjakan', 'revisi'], true))
                                    <form method="POST" action="{{ route('kasus.tugas.submission.store', [$kasus, $tugas]) }}" enctype="multipart/form-data" class="space-y-3 border-t border-gray-100 pt-4">
                                        @csrf
                                        <div class="rounded-xl border border-brand-200 bg-brand-50/10 p-4 space-y-3">
                                            <h5 class="font-display text-xs font-bold text-brand-900">Kirim Hasil / Bukti Pengerjaan Tugas</h5>
                                            <textarea name="teks" rows="2" placeholder="Ceritakan bukti atau jelaskan hasil refleksi pengerjaan tugas Anda di sini..." class="block w-full rounded-lg border-gray-200 text-xs font-medium text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500"></textarea>
                                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
                                                <div>
                                                    @if ($kasus->consents->firstWhere('jenis', 'pengumpulan_media')?->status === 'disetujui')
                                                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Unggah File/Foto Bukti (Opsional):</label>
                                                        <input type="file" name="lampiran" class="block w-full text-xs text-gray-700 file:mr-3 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 transition">
                                                    @else
                                                        <p class="text-[11px] font-medium text-gray-400 italic">Unggah media dinonaktifkan hingga informed consent media disetujui.</p>
                                                    @endif
                                                </div>
                                                <x-primary-button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold shrink-0">Kirim Bukti</x-primary-button>
                                            </div>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50/50 p-8 text-center text-gray-400">
            <x-icon name="checklist" class="mx-auto h-10 w-10 text-gray-300 mb-2" />
            <p class="text-sm font-bold text-gray-600">Belum Ada Tugas Diberikan</p>
            <p class="mt-0.5 text-xs text-gray-400 max-w-md mx-auto">Konselor belum memberikan instruksi tugas mandiri atau lembar refleksi untuk kasus ini.</p>
        </div>
    @endif
</div>

@if ($isKonselor)
    <script>
    function tugasBatchForm() {
        return {
            showForm: false,
            form: { judul: '', instruksi: '', frekuensi: 'sekali', tanggal_mulai: '', tanggal_selesai: '', tanggal_pengumpulan_bulanan: '' },
            frekuensiAkhir: 'sekali',
            jumlahBaris: 0,
            pratinjauLoaded: false,

            async pratinjau() {
                if (!this.form.tanggal_mulai || !this.form.tanggal_selesai) return;

                const response = await fetch('{{ route('kasus.tugas.preview', $kasus) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.form),
                });

                if (!response.ok) { this.pratinjauLoaded = false; return; }

                const data = await response.json();
                this.frekuensiAkhir = data.frekuensi_akhir;
                this.jumlahBaris = data.jumlah_baris;
                this.pratinjauLoaded = true;
            },
        };
    }
    </script>
@endif
```

Ikon `checklist`, `warning`, `check_circle`, `description`, `edit_note` semuanya sudah
terkonfirmasi ada sebagai `@case` di `resources/views/components/icon.blade.php` (periksa file
itu untuk memastikan sebelum commit — daftar `@case` bisa berubah lagi setelah plan ini
ditulis).

- [ ] **Step 4: Jalankan test, pastikan lolos**

Run: `php artisan test --filter=KasusTugasBatchViewTest`
Expected: PASS (3 test)

- [ ] **Step 5: Jalankan regresi Kasus penuh**

Run: `php artisan test --filter=Kasus`
Expected: semua lolos.

- [ ] **Step 6: Commit**

```bash
git add resources/views/kasus/partials/_tab-tugas.blade.php tests/Feature/KasusTugasBatchViewTest.php
git commit -m "feat(pendampingan): rewrite tugas tab UI for single-definition batch form with live preview"
```

---

### Task 8: Regresi penuh + reset data demo

- [ ] **Step 1: Jalankan seluruh suite**

Run: `php artisan test`
Expected: semua lolos, tidak ada regresi di luar Program Pendampingan.

- [ ] **Step 2: `migrate:fresh --seed`**

Run: `php artisan migrate:fresh --seed`
Expected: berhasil tanpa error — skema baru (kolom batch + tanpa kolom `tanggal`) diterapkan
dari nol, seluruh seeder (termasuk `PendampinganSeeder`) berjalan di atasnya.

Catatan: `PendampinganSeeder`'s skenario tugas (kalau ada yang membuat `KasusTugas` langsung,
bukan lewat `TugasBatchGenerator`) perlu diperiksa — kalau ada baris yang dibuat manual tanpa
`batch_id`/`batch_urutan`/`batch_total`, tampilan Task 7 tetap harus tidak error (baris tanpa
batch tetap dikelompokkan sendiri lewat `groupBy('batch_id')`, dengan `batch_id = null`
menghasilkan satu grup — pastikan tidak menyebabkan exception saat generate `$batchId` sebagai
array key `null`, uji manual sekali lewat `php artisan tinker` kalau seeder memang membuat
`KasusTugas` langsung).

- [ ] **Step 3: Smoke check manual (kalau memungkinkan)**

Login sebagai konselor pada salah satu kasus seed, buka form "Beri Tugas Pendampingan", coba
ketik tanggal dengan frekuensi "Mingguan" rentang pendek (≤7 hari) — konfirmasi pratinjau
menampilkan peringatan fallback ke "Harian" sebelum submit sungguhan.
