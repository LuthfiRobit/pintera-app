# Migrasi 3 Modul Akademik Tersisa ke Domains\Akademik Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrasikan 3 modul Akademik terakhir yang belum ikut pola `Domains\Akademik` (Kalender & Pengaturan Akademik, Pola Jam, Kenaikan Kelas) mengikuti standar `.agents/skills/laravel-feature-standard/SKILL.md` dan pola yang sudah dipakai modul Akademik lain yang sudah bermigrasi.

**Architecture:** Controller thin (validasi → DTO → Action → response). Model `KalenderAkademik`, `PolaJam`, `JamPelajaran` dipindah fisik ke `app/Domains/Akademik/Models/`. Service `KalenderAkademikResolver` dipindah ke `app/Domains/Akademik/Services/`. Semua business logic yang sekarang ada di controller diekstrak jadi Action class di `app/Domains/Akademik/Actions/{Kalender,PolaJam,KenaikanKelas}/`.

**Tech Stack:** Laravel 11, Pest (test), pola Action/DTO yang sudah dipakai `Domains\Akademik\Actions\Jadwal\CreateJadwalPelajaranAction` sebagai referensi konkret.

## Global Constraints

- **Zero behavior change** untuk Modul 1 (Kalender & Pengaturan Akademik) dan Modul 2 (Pola Jam): setiap guard, pesan error (bahasa Indonesia, kata-per-kata), urutan validasi, dan format respons (redirect vs JSON) HARUS identik dengan sebelum migrasi. Test yang sudah ada untuk kedua modul ini TIDAK BOLEH diubah assertion-nya — kalau ada test yang gagal setelah migrasi, itu bug di implementasi task, bukan alasan untuk mengubah test.
- **Modul 3 (Kenaikan Kelas) SENGAJA mengubah perilaku** pada satu titik spesifik: proses salin-jadwal saat kenaikan kelas. Ini didetailkan di Task 7 — jangan generalisasi "zero behavior change" ke modul ini.
- Setiap task yang memindah model/service WAJIB: (a) pindah file fisik, (b) ubah namespace di baris pertama file (baris `namespace ...;`), (c) update SEMUA file lain yang meng-import class itu (daftar exact sudah didapat lewat `grep`, tercantum di tiap task — JANGAN grep ulang dan berharap dapat daftar yang sama persis, pakai daftar yang sudah diberikan di task karena file baru sepanjang migrasi bisa mengubah hasil grep).
- Model pindahan (`KalenderAkademik`, `PolaJam`, `JamPelajaran`) HANYA boleh berisi `$fillable`, `casts()`, relationship, local scope — TIDAK ADA business logic method.
- Action: 1 use-case per class, method `execute()`, terima DTO (bukan `Illuminate\Http\Request` langsung), pakai `DB::transaction()` untuk mutasi multi-tabel.
- DTO: `final readonly class` di `app/Domains/Akademik/DataTransferObjects/`.
- Testing policy: tiap task jalankan test SCOPED (file yang disebut di task itu saja). Full suite HANYA di Task 8 (task terakhir), dan HARUS minta izin eksplisit ke user sebelum dijalankan — jangan asumsikan izin dari task-task sebelumnya.
- Baseline kode: commit `b8b6242` di branch `rbac-v2`. Kalau ada commit baru yang mengubah salah satu file yang disebut plan ini sebelum eksekusi dimulai, verifikasi ulang isi file itu dulu sebelum mengikuti instruksi task secara membabi buta.

---

## Task 1: Pindahkan Model `KalenderAkademik` ke Domain

**Files:**
- Move: `app/Models/KalenderAkademik.php` → `app/Domains/Akademik/Models/KalenderAkademik.php`
- Modify: `app/Http/Controllers/Admin/PengaturanAkademikController.php`
- Modify: `app/Http/Controllers/Admin/KalenderAkademikController.php`
- Modify: `database/factories/KalenderAkademikFactory.php`
- Modify: `tests/Unit/Models/KalenderAkademikTest.php`
- Modify: `tests/Feature/Admin/KalenderAkademikCrudTest.php`
- Modify: `tests/Feature/Admin/PengaturanAkademikControllerTest.php`
- Modify: `tests/Unit/Services/KalenderAkademikResolverTest.php`
- Modify: `app/Services/KalenderAkademikResolver.php`

**Interfaces:**
- Consumes: tidak ada (task independen pertama)
- Produces: `App\Domains\Akademik\Models\KalenderAkademik` — dipakai Task 2 (service resolver) dan Task 3 (Action Kalender)

Daftar 8 file di atas adalah HASIL GREP NYATA (`grep -rln "^use App\\\\Models\\\\KalenderAkademik;"`) dijalankan 2026-08-20 terhadap commit `b8b6242` — bukan tebakan.

- [ ] **Step 1: Pindahkan file model secara fisik**

```bash
git mv app/Models/KalenderAkademik.php app/Domains/Akademik/Models/KalenderAkademik.php
```

- [ ] **Step 2: Ubah namespace di file yang dipindah**

Isi `app/Domains/Akademik/Models/KalenderAkademik.php` — ganti baris 3 (`namespace App\Models;`) menjadi `namespace App\Domains\Akademik\Models;`, dan tambahkan `use App\Models\Lembaga;` (karena relasi `lembaga()` butuh referensi ke model itu, yang TETAP di `App\Models`). Hasil akhir file:

```php
<?php

namespace App\Domains\Akademik\Models;

use App\Enums\TipeKalenderAkademik;
use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KalenderAkademik extends Model
{
    use HasFactory;

    protected $table = 'kalender_akademik';

    protected $fillable = ['lembaga_id', 'tanggal', 'tanggal_selesai', 'nama', 'tipe', 'keterangan'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'tanggal_selesai' => 'date',
            'tipe' => TipeKalenderAkademik::class,
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function scopeNasional($query)
    {
        return $query->whereNull('lembaga_id');
    }

    public function scopeUntukLembaga($query, int $lembagaId)
    {
        return $query->where('lembaga_id', $lembagaId);
    }
}
```

- [ ] **Step 3: Update `use` statement di 7 file consumer**

Di TIAP file berikut, cari baris persis `use App\Models\KalenderAkademik;` dan ganti dengan `use App\Domains\Akademik\Models\KalenderAkademik;` (satu baris, tidak ada perubahan lain):

- `app/Http/Controllers/Admin/PengaturanAkademikController.php`
- `app/Http/Controllers/Admin/KalenderAkademikController.php`
- `database/factories/KalenderAkademikFactory.php`
- `tests/Unit/Models/KalenderAkademikTest.php`
- `tests/Feature/Admin/KalenderAkademikCrudTest.php`
- `tests/Feature/Admin/PengaturanAkademikControllerTest.php`
- `app/Services/KalenderAkademikResolver.php`

(File ke-8 dari daftar grep, `tests/Unit/Services/KalenderAkademikResolverTest.php`, TIDAK punya baris `use App\Models\KalenderAkademik;` langsung — dia memakai `KalenderAkademik::factory()` lewat namespace penuh atau lewat factory; verifikasi dengan `grep -n "KalenderAkademik" tests/Unit/Services/KalenderAkademikResolverTest.php` dan update SEMUA kemunculan `App\Models\KalenderAkademik` yang ditemukan ke `App\Domains\Akademik\Models\KalenderAkademik`, bukan cuma baris `use`.)

- [ ] **Step 4: Cari factory model yang mendaftarkan namespace model (`protected $model`)**

Buka `database/factories/KalenderAkademikFactory.php`, cari baris `protected $model = ...` atau method `model()` yang menyebut kelas model — update juga ke `App\Domains\Akademik\Models\KalenderAkademik::class` kalau ada referensi eksplisit di situ (Laravel factory modern biasa pakai auto-discovery lewat naming convention `<Model>Factory`, tapi kalau ada override eksplisit, HARUS diupdate).

- [ ] **Step 5: Jalankan test scoped, pastikan tidak ada "Class not found"**

Run: `php artisan test tests/Unit/Models/KalenderAkademikTest.php tests/Feature/Admin/KalenderAkademikCrudTest.php tests/Feature/Admin/PengaturanAkademikControllerTest.php tests/Unit/Services/KalenderAkademikResolverTest.php`
Expected: semua test PASS, jumlah sama seperti sebelum perubahan (cek dulu jumlahnya dengan `git stash` lalu run test yang sama SEBELUM melakukan perubahan apapun, kalau belum tahu baseline-nya).

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Akademik/Models/KalenderAkademik.php app/Http/Controllers/Admin/PengaturanAkademikController.php app/Http/Controllers/Admin/KalenderAkademikController.php app/Services/KalenderAkademikResolver.php database/factories/KalenderAkademikFactory.php tests/Unit/Models/KalenderAkademikTest.php tests/Feature/Admin/KalenderAkademikCrudTest.php tests/Feature/Admin/PengaturanAkademikControllerTest.php tests/Unit/Services/KalenderAkademikResolverTest.php
git status
git add -A -- app/Models/KalenderAkademik.php
git commit -m "refactor(akademik): pindah model KalenderAkademik ke Domains\Akademik\Models"
```

---

## Task 2: Pindahkan Service `KalenderAkademikResolver` ke Domain

**Files:**
- Move: `app/Services/KalenderAkademikResolver.php` → `app/Domains/Akademik/Services/KalenderAkademikResolver.php`
- Modify: `app/Domains/Akademik/Services/SesiPembelajaranGenerator.php`
- Modify: `app/Domains/Akademik/Services/SesiTematikGenerator.php`
- Modify: `tests/Unit/Services/KalenderAkademikResolverTest.php`

**Interfaces:**
- Consumes: `App\Domains\Akademik\Models\KalenderAkademik` (dari Task 1)
- Produces: `App\Domains\Akademik\Services\KalenderAkademikResolver` dengan method `resolve(Lembaga $lembaga, CarbonInterface $tanggal): array{libur: bool, alasan: string}` — dipakai Task 3

Ini menghilangkan dependency lintas-domain yang SUDAH ADA sekarang: `SesiPembelajaranGenerator` dan `SesiTematikGenerator` (keduanya sudah di `Domains\Akademik\Services\`) memanggil `App\Services\KalenderAkademikResolver` yang ada DI LUAR domain. Setelah task ini, keduanya memanggil service yang sama-sama di dalam domain.

- [ ] **Step 1: Pindahkan file service secara fisik**

```bash
git mv app/Services/KalenderAkademikResolver.php app/Domains/Akademik/Services/KalenderAkademikResolver.php
```

- [ ] **Step 2: Ubah namespace dan import di file yang dipindah**

Isi `app/Domains/Akademik/Services/KalenderAkademikResolver.php` — ganti baris 3 jadi `namespace App\Domains\Akademik\Services;`, dan baris `use App\Models\KalenderAkademik;` jadi `use App\Domains\Akademik\Models\KalenderAkademik;`. `use App\Models\Lembaga;` TIDAK berubah (Lembaga tetap di app/Models). Hasil akhir:

```php
<?php

namespace App\Domains\Akademik\Services;

use App\Enums\TipeKalenderAkademik;
use App\Domains\Akademik\Models\KalenderAkademik;
use App\Models\Lembaga;
use Carbon\CarbonInterface;

class KalenderAkademikResolver
{
    /**
     * @return array{libur: bool, alasan: string}
     */
    public function resolve(Lembaga $lembaga, CarbonInterface $tanggal): array
    {
        $entriLembaga = KalenderAkademik::untukLembaga($lembaga->id)
            ->where(fn ($q) => $this->cocokRentang($q, $tanggal))
            ->first();

        if ($entriLembaga) {
            return [
                'libur' => $entriLembaga->tipe === TipeKalenderAkademik::Libur,
                'alasan' => $entriLembaga->nama,
            ];
        }

        $entriNasional = KalenderAkademik::nasional()
            ->where(fn ($q) => $this->cocokRentang($q, $tanggal))
            ->first();

        if ($entriNasional) {
            return [
                'libur' => $entriNasional->tipe === TipeKalenderAkademik::Libur,
                'alasan' => $entriNasional->nama,
            ];
        }

        if (in_array($tanggal->dayOfWeek, $lembaga->hari_libur_mingguan ?? [], true)) {
            return ['libur' => true, 'alasan' => 'Libur mingguan'];
        }

        return ['libur' => false, 'alasan' => 'Hari efektif belajar'];
    }

    /**
     * Matches a $tanggal that falls within an entry's [tanggal, tanggal_selesai]
     * range, inclusive. When tanggal_selesai is null the entry is a single day,
     * so the effective end date falls back to tanggal itself.
     */
    private function cocokRentang($query, CarbonInterface $tanggal)
    {
        $tgl = $tanggal->toDateString();

        return $query
            ->whereDate('tanggal', '<=', $tgl)
            ->where(fn ($q) => $q->whereDate('tanggal_selesai', '>=', $tgl)
                ->orWhere(fn ($q2) => $q2->whereNull('tanggal_selesai')->whereDate('tanggal', '>=', $tgl))
            );
    }
}
```

- [ ] **Step 3: Update `use` statement di 2 consumer domain service**

Di `app/Domains/Akademik/Services/SesiPembelajaranGenerator.php`, cari baris persis `use App\Services\KalenderAkademikResolver;` (sekitar baris 10) dan ganti dengan `use App\Domains\Akademik\Services\KalenderAkademikResolver;`.

Di `app/Domains/Akademik/Services/SesiTematikGenerator.php`, cari baris persis `use App\Services\KalenderAkademikResolver;` (sekitar baris 9) dan ganti dengan `use App\Domains\Akademik\Services\KalenderAkademikResolver;`.

Tidak ada perubahan lain di kedua file itu — pemanggilan `(new KalenderAkademikResolver)->resolve(...)` tetap sama persis.

- [ ] **Step 4: Update `use` statement di test-nya sendiri**

Di `tests/Unit/Services/KalenderAkademikResolverTest.php`, cari SEMUA kemunculan `App\Services\KalenderAkademikResolver` dan `App\Models\KalenderAkademik` (bisa lebih dari satu baris — cek dengan `grep -n "App\\\\Services\\\\KalenderAkademikResolver\|App\\\\Models\\\\KalenderAkademik" tests/Unit/Services/KalenderAkademikResolverTest.php`), ganti masing-masing ke `App\Domains\Akademik\Services\KalenderAkademikResolver` dan `App\Domains\Akademik\Models\KalenderAkademik`.

- [ ] **Step 5: Jalankan test scoped**

Run: `php artisan test tests/Unit/Services/KalenderAkademikResolverTest.php tests/Unit/Services/SesiPembelajaranGeneratorTest.php tests/Unit/Services/SesiTematikGeneratorTest.php`
Expected: semua test PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Akademik/Services/KalenderAkademikResolver.php app/Domains/Akademik/Services/SesiPembelajaranGenerator.php app/Domains/Akademik/Services/SesiTematikGenerator.php tests/Unit/Services/KalenderAkademikResolverTest.php
git add -A -- app/Services/KalenderAkademikResolver.php
git commit -m "refactor(akademik): pindah KalenderAkademikResolver ke Domains\Akademik\Services, hilangkan dependency lintas-domain"
```

---

## Task 3: Buat Action & DTO Modul Kalender, Refaktor Controller

**Files:**
- Create: `app/Domains/Akademik/DataTransferObjects/HariAktifLembagaData.php`
- Create: `app/Domains/Akademik/DataTransferObjects/KalenderAkademikData.php`
- Create: `app/Domains/Akademik/Actions/Kalender/UpdateHariAktifLembagaAction.php`
- Create: `app/Domains/Akademik/Actions/Kalender/CreateKalenderAkademikAction.php`
- Create: `app/Domains/Akademik/Actions/Kalender/UpdateKalenderAkademikAction.php`
- Create: `app/Domains/Akademik/Actions/Kalender/DeleteKalenderAkademikAction.php`
- Test: `tests/Unit/Domains/Akademik/Actions/Kalender/UpdateHariAktifLembagaActionTest.php`
- Test: `tests/Unit/Domains/Akademik/Actions/Kalender/CreateKalenderAkademikActionTest.php`
- Modify: `app/Http/Controllers/Admin/PengaturanAkademikController.php`
- Modify: `app/Http/Controllers/Admin/KalenderAkademikController.php`

**Interfaces:**
- Consumes: `App\Domains\Akademik\Models\KalenderAkademik` (Task 1)
- Produces: 4 Action class dipakai controller ini saja, tidak dikonsumsi task lain

- [ ] **Step 1: Buat `HariAktifLembagaData`**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class HariAktifLembagaData
{
    /**
     * @param  array<int, int>  $hariAktif  hari 0 (minggu) - 6 (sabtu) yang aktif
     */
    public function __construct(
        public array $hariAktif,
    ) {}
}
```

- [ ] **Step 2: Buat `KalenderAkademikData`**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class KalenderAkademikData
{
    public function __construct(
        public string $tanggal,
        public ?string $tanggalSelesai,
        public string $nama,
        public string $tipe,
        public ?string $keterangan,
        public bool $berlakuNasional,
    ) {}
}
```

- [ ] **Step 3: Buat `UpdateHariAktifLembagaAction`**

Logika dipindah persis dari `PengaturanAkademikController::updateHariAktif()` baris 56-62 versi sebelum migrasi.

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Kalender;

use App\Domains\Akademik\DataTransferObjects\HariAktifLembagaData;
use App\Models\Lembaga;

final class UpdateHariAktifLembagaAction
{
    public function execute(Lembaga $lembaga, HariAktifLembagaData $data): Lembaga
    {
        $hariLibur = array_values(array_diff(range(0, 6), $data->hariAktif));

        $lembaga->update(['hari_libur_mingguan' => $hariLibur]);

        return $lembaga->fresh();
    }
}
```

- [ ] **Step 4: Tulis test untuk `UpdateHariAktifLembagaAction`**

```php
<?php

use App\Domains\Akademik\Actions\Kalender\UpdateHariAktifLembagaAction;
use App\Domains\Akademik\DataTransferObjects\HariAktifLembagaData;
use App\Models\Lembaga;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sets hari_libur_mingguan as the complement of the active days provided', function () {
    $lembaga = Lembaga::factory()->create();

    $result = (new UpdateHariAktifLembagaAction)->execute(
        $lembaga,
        new HariAktifLembagaData(hariAktif: [1, 2, 3, 4, 5])
    );

    expect($result->hari_libur_mingguan)->toEqualCanonicalizing([0, 6]);
});

it('marks every day as libur when no active day is provided', function () {
    $lembaga = Lembaga::factory()->create();

    $result = (new UpdateHariAktifLembagaAction)->execute(
        $lembaga,
        new HariAktifLembagaData(hariAktif: [])
    );

    expect($result->hari_libur_mingguan)->toEqualCanonicalizing([0, 1, 2, 3, 4, 5, 6]);
});
```

- [ ] **Step 5: Jalankan test baru, verifikasi PASS**

Run: `php artisan test tests/Unit/Domains/Akademik/Actions/Kalender/UpdateHariAktifLembagaActionTest.php`
Expected: 2 passed.

- [ ] **Step 6: Buat `CreateKalenderAkademikAction`**

Logika dipindah persis dari `KalenderAkademikController::store()` + `tumpangTindih()` baris 16-60 dan 123-132 versi sebelum migrasi. Perilaku permission (`kalender-akademik.kelola-nasional` untuk entri nasional) TETAP dicek DI CONTROLLER (bukan di Action — Action tidak menerima objek `Request`/user, itu tanggung jawab HTTP layer sesuai SKILL.md §5), Action hanya menerima parameter yang sudah divalidasi/diotorisasi.

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Kalender;

use App\Domains\Akademik\DataTransferObjects\KalenderAkademikData;
use App\Domains\Akademik\Models\KalenderAkademik;
use Illuminate\Validation\ValidationException;

final class CreateKalenderAkademikAction
{
    public function execute(KalenderAkademikData $data, ?int $lembagaId): KalenderAkademik
    {
        $tanggalSelesai = $data->tanggalSelesai ?? $data->tanggal;

        if ($this->tumpangTindih($lembagaId, $data->tanggal, $tanggalSelesai)) {
            throw ValidationException::withMessages([
                'tanggal' => 'Rentang tanggal ini tumpang tindih dengan entri lain pada cakupan yang sama.',
            ]);
        }

        return KalenderAkademik::create([
            'lembaga_id' => $lembagaId,
            'tanggal' => $data->tanggal,
            'tanggal_selesai' => $tanggalSelesai,
            'nama' => $data->nama,
            'tipe' => $data->tipe,
            'keterangan' => $data->keterangan,
        ]);
    }

    /**
     * Detects whether [$mulai, $selesai] overlaps an existing entry in the
     * same scope (same lembaga_id, or both national when $lembagaId is
     * null). Mirrors KalenderAkademikResolver::cocokRentang's handling of a
     * null tanggal_selesai: such a row is a single-day entry whose
     * *effective* end date is its own `tanggal`, not an open-ended/unbounded
     * range. Treating "tanggal_selesai IS NULL" as unconditionally
     * overlapping (i.e. ORing it in without also checking the existing
     * row's `tanggal` against $mulai) produces false positives for any new
     * range that starts after such a single-day entry.
     */
    private function tumpangTindih(?int $lembagaId, string $mulai, string $selesai, ?int $kecualiId = null): bool
    {
        return KalenderAkademik::where(fn ($q) => $lembagaId === null ? $q->whereNull('lembaga_id') : $q->where('lembaga_id', $lembagaId))
            ->when($kecualiId, fn ($q) => $q->where('id', '!=', $kecualiId))
            ->where('tanggal', '<=', $selesai)
            ->where(fn ($q) => $q->where('tanggal_selesai', '>=', $mulai)
                ->orWhere(fn ($q2) => $q2->whereNull('tanggal_selesai')->where('tanggal', '>=', $mulai))
            )
            ->exists();
    }
}
```

- [ ] **Step 7: Tulis test untuk `CreateKalenderAkademikAction`**

```php
<?php

use App\Domains\Akademik\Actions\Kalender\CreateKalenderAkademikAction;
use App\Domains\Akademik\DataTransferObjects\KalenderAkademikData;
use App\Domains\Akademik\Models\KalenderAkademik;
use App\Models\Lembaga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('creates a lembaga-scoped kalender entry', function () {
    $lembaga = Lembaga::factory()->create();

    $entri = (new CreateKalenderAkademikAction)->execute(
        new KalenderAkademikData(
            tanggal: '2026-09-01',
            tanggalSelesai: null,
            nama: 'Libur Semester',
            tipe: 'libur',
            keterangan: null,
            berlakuNasional: false,
        ),
        $lembaga->id
    );

    expect($entri->lembaga_id)->toBe($lembaga->id)
        ->and($entri->nama)->toBe('Libur Semester');
});

it('rejects a date range that overlaps an existing entry in the same scope', function () {
    $lembaga = Lembaga::factory()->create();
    KalenderAkademik::factory()->create([
        'lembaga_id' => $lembaga->id,
        'tanggal' => '2026-09-01',
        'tanggal_selesai' => '2026-09-05',
    ]);

    expect(fn () => (new CreateKalenderAkademikAction)->execute(
        new KalenderAkademikData(
            tanggal: '2026-09-03',
            tanggalSelesai: '2026-09-10',
            nama: 'Entri Baru',
            tipe: 'libur',
            keterangan: null,
            berlakuNasional: false,
        ),
        $lembaga->id
    ))->toThrow(ValidationException::class);
});

it('does not flag overlap against a different lembaga scope', function () {
    $lembagaA = Lembaga::factory()->create();
    $lembagaB = Lembaga::factory()->create();
    KalenderAkademik::factory()->create([
        'lembaga_id' => $lembagaA->id,
        'tanggal' => '2026-09-01',
        'tanggal_selesai' => '2026-09-05',
    ]);

    $entri = (new CreateKalenderAkademikAction)->execute(
        new KalenderAkademikData(
            tanggal: '2026-09-03',
            tanggalSelesai: '2026-09-05',
            nama: 'Entri Lembaga Lain',
            tipe: 'libur',
            keterangan: null,
            berlakuNasional: false,
        ),
        $lembagaB->id
    );

    expect($entri->lembaga_id)->toBe($lembagaB->id);
});
```

- [ ] **Step 8: Jalankan test baru, verifikasi PASS**

Run: `php artisan test tests/Unit/Domains/Akademik/Actions/Kalender/CreateKalenderAkademikActionTest.php`
Expected: 3 passed.

- [ ] **Step 9: Buat `UpdateKalenderAkademikAction` dan `DeleteKalenderAkademikAction`**

Logika dipindah persis dari `KalenderAkademikController::update()` (baris 62-88) dan `destroy()` (baris 90-110) versi sebelum migrasi. Guard kepemilikan lembaga & permission nasional TETAP di controller (butuh akses `$request->user()`/session), Action hanya eksekusi setelah guard lolos.

`app/Domains/Akademik/Actions/Kalender/UpdateKalenderAkademikAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Kalender;

use App\Domains\Akademik\DataTransferObjects\KalenderAkademikData;
use App\Domains\Akademik\Models\KalenderAkademik;

final class UpdateKalenderAkademikAction
{
    public function execute(KalenderAkademik $kalenderAkademik, KalenderAkademikData $data): KalenderAkademik
    {
        $kalenderAkademik->update([
            'nama' => $data->nama,
            'tipe' => $data->tipe,
            'keterangan' => $data->keterangan,
        ]);

        return $kalenderAkademik->fresh();
    }
}
```

`app/Domains/Akademik/Actions/Kalender/DeleteKalenderAkademikAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Kalender;

use App\Domains\Akademik\Models\KalenderAkademik;

final class DeleteKalenderAkademikAction
{
    public function execute(KalenderAkademik $kalenderAkademik): void
    {
        $kalenderAkademik->delete();
    }
}
```

- [ ] **Step 10: Refaktor `PengaturanAkademikController`**

Ganti seluruh isi `app/Http/Controllers/Admin/PengaturanAkademikController.php` jadi:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\Kalender\UpdateHariAktifLembagaAction;
use App\Domains\Akademik\DataTransferObjects\HariAktifLembagaData;
use App\Domains\Akademik\Models\KalenderAkademik;
use App\Models\Lembaga;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class PengaturanAkademikController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View|RedirectResponse
    {
        $this->authorize('kalender-akademik.view');

        if ($request->user()->widestScopeLevel() === 'yayasan' && session('active_lembaga_id') === null) {
            return redirect()->route('dashboard')
                ->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga untuk mengakses Pengaturan Akademik.']);
        }

        $lembagaId = $request->user()->lembaga_id ?? session('active_lembaga_id');
        $lembaga = Lembaga::findOrFail($lembagaId);

        return view('admin.pengaturan.akademik', [
            'lembaga' => $lembaga,
            'entriList' => KalenderAkademik::where(fn ($q) => $q->whereNull('lembaga_id')->orWhere('lembaga_id', $lembagaId))
                ->orderBy('tanggal')
                ->get(),
            'bolehNasional' => $request->user()->can('kalender-akademik.kelola-nasional'),
            'bolehKelolaHariAktif' => $request->user()->can('pengaturan-akademik.kelola'),
        ]);
    }

    public function updateHariAktif(Request $request, UpdateHariAktifLembagaAction $action): JsonResponse
    {
        $this->authorize('pengaturan-akademik.kelola');

        if ($request->user()->widestScopeLevel() === 'yayasan' && session('active_lembaga_id') === null) {
            return response()->json([
                'message' => 'Pilih lembaga aktif melalui pengalih lembaga terlebih dahulu.',
                'errors' => ['lembaga_id' => ['Pilih lembaga aktif melalui pengalih lembaga terlebih dahulu.']],
            ], 422);
        }

        $data = $request->validate([
            'hari_aktif' => ['present', 'array'],
            'hari_aktif.*' => ['integer', 'between:0,6'],
        ]);

        $lembagaId = $request->user()->lembaga_id ?? session('active_lembaga_id');
        $lembaga = Lembaga::findOrFail($lembagaId);

        $lembaga = $action->execute($lembaga, new HariAktifLembagaData(hariAktif: $data['hari_aktif']));

        return response()->json(['data' => ['hari_libur_mingguan' => $lembaga->hari_libur_mingguan]]);
    }
}
```

- [ ] **Step 11: Refaktor `KalenderAkademikController`**

Ganti seluruh isi `app/Http/Controllers/Admin/KalenderAkademikController.php` jadi:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\Kalender\CreateKalenderAkademikAction;
use App\Domains\Akademik\Actions\Kalender\DeleteKalenderAkademikAction;
use App\Domains\Akademik\Actions\Kalender\UpdateKalenderAkademikAction;
use App\Domains\Akademik\DataTransferObjects\KalenderAkademikData;
use App\Domains\Akademik\Models\KalenderAkademik;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\ValidationException;

class KalenderAkademikController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request, CreateKalenderAkademikAction $action): RedirectResponse|JsonResponse
    {
        $this->authorize('kalender-akademik.kelola');

        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal'],
            'nama' => ['required', 'string', 'max:255'],
            'tipe' => ['required', 'in:libur,kerja'],
            'keterangan' => ['nullable', 'string'],
            'berlaku_nasional' => ['nullable', 'boolean'],
        ]);

        $nasional = $request->boolean('berlaku_nasional');

        if ($nasional) {
            $this->authorize('kalender-akademik.kelola-nasional');
        }

        if (! $nasional && $request->user()->widestScopeLevel() === 'yayasan' && session('active_lembaga_id') === null) {
            return $this->errorResponse($request, 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah entri kalender.', 'lembaga_id');
        }

        $lembagaId = $nasional ? null : ($request->user()->lembaga_id ?? session('active_lembaga_id'));

        try {
            $entri = $action->execute(
                new KalenderAkademikData(
                    tanggal: $data['tanggal'],
                    tanggalSelesai: $data['tanggal_selesai'] ?? null,
                    nama: $data['nama'],
                    tipe: $data['tipe'],
                    keterangan: $data['keterangan'] ?? null,
                    berlakuNasional: $nasional,
                ),
                $lembagaId
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($request, $e->validator->errors()->first('tanggal'), 'tanggal');
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $entri], 201);
        }

        return redirect()->route('admin.pengaturan.akademik.index')->with('status', 'Entri kalender berhasil disimpan.');
    }

    public function update(Request $request, KalenderAkademik $kalenderAkademik, UpdateKalenderAkademikAction $action): RedirectResponse|JsonResponse
    {
        $this->authorize('kalender-akademik.kelola');

        $lembagaId = $request->user()->lembaga_id ?? session('active_lembaga_id');
        if ($kalenderAkademik->lembaga_id !== null && $kalenderAkademik->lembaga_id !== $lembagaId) {
            abort(404);
        }

        if ($kalenderAkademik->lembaga_id === null) {
            $this->authorize('kalender-akademik.kelola-nasional');
        }

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'tipe' => ['required', 'in:libur,kerja'],
            'keterangan' => ['nullable', 'string'],
        ]);

        $kalenderAkademik = $action->execute(
            $kalenderAkademik,
            new KalenderAkademikData(
                tanggal: $kalenderAkademik->tanggal->toDateString(),
                tanggalSelesai: $kalenderAkademik->tanggal_selesai?->toDateString(),
                nama: $data['nama'],
                tipe: $data['tipe'],
                keterangan: $data['keterangan'] ?? null,
                berlakuNasional: $kalenderAkademik->lembaga_id === null,
            )
        );

        if ($request->wantsJson()) {
            return response()->json(['data' => $kalenderAkademik]);
        }

        return redirect()->route('admin.pengaturan.akademik.index')->with('status', 'Entri kalender berhasil diperbarui.');
    }

    public function destroy(Request $request, KalenderAkademik $kalenderAkademik, DeleteKalenderAkademikAction $action): RedirectResponse|JsonResponse
    {
        $this->authorize('kalender-akademik.kelola');

        $lembagaId = $request->user()->lembaga_id ?? session('active_lembaga_id');
        if ($kalenderAkademik->lembaga_id !== null && $kalenderAkademik->lembaga_id !== $lembagaId) {
            abort(404);
        }

        if ($kalenderAkademik->lembaga_id === null) {
            $this->authorize('kalender-akademik.kelola-nasional');
        }

        $action->execute($kalenderAkademik);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Entri kalender berhasil dihapus.']);
        }

        return redirect()->route('admin.pengaturan.akademik.index')->with('status', 'Entri kalender berhasil dihapus.');
    }

    private function errorResponse(Request $request, string $message, string $field): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'errors' => [$field => [$message]]], 422);
        }

        return back()->withErrors([$field => $message])->withInput();
    }
}
```

**PENTING — perbedaan halus yang HARUS diverifikasi manual:** versi asli `update()`/`destroy()` TIDAK memvalidasi tumpang-tindih ulang saat update (cuma saat create). Kode di atas mempertahankan itu — `UpdateKalenderAkademikAction` TIDAK memanggil pengecekan tumpang-tindih. Jangan tambahkan validasi baru yang tidak ada di kode asli.

- [ ] **Step 12: Jalankan SEMUA test Modul 1, verifikasi hijau semua**

Run: `php artisan test tests/Unit/Models/KalenderAkademikTest.php tests/Feature/Admin/KalenderAkademikCrudTest.php tests/Feature/Admin/PengaturanAkademikControllerTest.php tests/Unit/Services/KalenderAkademikResolverTest.php tests/Unit/Domains/Akademik/Actions/Kalender/UpdateHariAktifLembagaActionTest.php tests/Unit/Domains/Akademik/Actions/Kalender/CreateKalenderAkademikActionTest.php`
Expected: semua PASS, 0 failed. Kalau `KalenderAkademikCrudTest.php` atau `PengaturanAkademikControllerTest.php` gagal, itu tanda ada perbedaan perilaku yang tidak sengaja — bandingkan pesan error test dengan controller ASLI (lihat `git show b8b6242:app/Http/Controllers/Admin/KalenderAkademikController.php`) untuk cari selisihnya.

- [ ] **Step 13: Commit**

```bash
git add app/Domains/Akademik/DataTransferObjects/HariAktifLembagaData.php app/Domains/Akademik/DataTransferObjects/KalenderAkademikData.php app/Domains/Akademik/Actions/Kalender/ app/Http/Controllers/Admin/PengaturanAkademikController.php app/Http/Controllers/Admin/KalenderAkademikController.php tests/Unit/Domains/Akademik/Actions/Kalender/
git commit -m "refactor(akademik): ekstrak Action Modul Kalender & Pengaturan Akademik, controller jadi thin"
```

---

## Task 4: Pindahkan Model `PolaJam` dan `JamPelajaran` ke Domain

**Files:**
- Move: `app/Models/PolaJam.php` → `app/Domains/Akademik/Models/PolaJam.php`
- Move: `app/Models/JamPelajaran.php` → `app/Domains/Akademik/Models/JamPelajaran.php`
- Modify (32 file, hasil grep nyata `grep -rln "^use App\\\\Models\\\\PolaJam;"` 2026-08-20 terhadap `b8b6242`):
  - `tests/Feature/Admin/PolaJamCrudTest.php`
  - `tests/Feature/Admin/JadwalPelajaranCrudTest.php`
  - `tests/Feature/Guru/KomponenPenilaianControllerTest.php`
  - `tests/Feature/Guru/AsesmenControllerTest.php`
  - `tests/Feature/Akademik/JurnalKbmAdaptiveTest.php`
  - `tests/Feature/Guru/JurnalKbmControllerTest.php`
  - `tests/Unit/Services/SesiTematikGeneratorTest.php`
  - `tests/Feature/Guru/JurnalKbmTenantScopeTest.php`
  - `tests/Unit/Services/SesiPembelajaranGeneratorTest.php`
  - `database/seeders/SesiPembelajaranSeeder.php`
  - `tests/Feature/Akademik/JadwalSarprasCollisionTest.php`
  - `tests/Unit/Domains/Sarpras/GedungRuanganActionTest.php`
  - `tests/Unit/Domains/Sarpras/SarprasModelsTest.php`
  - `app/Http/Controllers/Admin/KelasController.php`
  - `app/Http/Controllers/Admin/PolaJamController.php`
  - `database/seeders/JadwalPelajaranSeeder.php`
  - `tests/Unit/KelasSeederTest.php`
  - `database/seeders/KelasSeeder.php`
  - `database/seeders/JamPelajaranSeeder.php`
  - `database/seeders/PolaJamSeeder.php`
  - `tests/Unit/JamPelajaranSeederTest.php`
  - `tests/Unit/PolaJamSeederTest.php`
  - `tests/Feature/Admin/KenaikanKelasControllerTest.php`
  - `app/Http/Controllers/Admin/JamPelajaranController.php`
  - `tests/Feature/Admin/KelasCrudTest.php`
  - `tests/Feature/Admin/JamPelajaranCrudTest.php`
  - `tests/Feature/Admin/KelasPolaJamTest.php`
  - `tests/Unit/Models/JadwalPelajaranTest.php`
  - `tests/Unit/Models/JamPelajaranTest.php`
  - `tests/Unit/Models/PolaJamTest.php`
  - `database/factories/PolaJamFactory.php`
  - `database/factories/JamPelajaranFactory.php`
- Modify tambahan (hasil grep nyata `grep -rln "^use App\\\\Models\\\\JamPelajaran;"` — 4 file yang TIDAK ada di daftar 32 di atas):
  - `app/Http/Controllers/Admin/JadwalPelajaranController.php`
  - `app/Domains/Akademik/Actions/Jadwal/DuplicateJadwalAction.php`
  - `database/factories/JadwalPelajaranFactory.php`
- Modify: `app/Models/Kelas.php` (relasi `belongsTo(PolaJam::class)`)

**Interfaces:**
- Consumes: tidak ada
- Produces: `App\Domains\Akademik\Models\PolaJam`, `App\Domains\Akademik\Models\JamPelajaran` — dipakai Task 5, 6

Total unik file yang perlu diupdate importnya: 32 + 3 (dari daftar kedua, exclude yang sudah tercakup) + `Kelas.php` = 36 file (tidak termasuk 2 file model yang dipindah sendiri).

- [ ] **Step 1: Pindahkan kedua file model secara fisik**

```bash
git mv app/Models/PolaJam.php app/Domains/Akademik/Models/PolaJam.php
git mv app/Models/JamPelajaran.php app/Domains/Akademik/Models/JamPelajaran.php
```

- [ ] **Step 2: Ubah namespace & import di `PolaJam.php`**

```php
<?php

namespace App\Domains\Akademik\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Kelas;
use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PolaJam extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'pola_jam';

    protected $fillable = ['lembaga_id', 'nama'];

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function jamPelajaran(): HasMany
    {
        return $this->hasMany(JamPelajaran::class);
    }

    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class);
    }
}
```

- [ ] **Step 3: Ubah namespace & import di `JamPelajaran.php`**

```php
<?php

namespace App\Domains\Akademik\Models;

use App\Enums\Hari;
use App\Models\JadwalPelajaran;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JamPelajaran extends Model
{
    use HasFactory;

    protected $table = 'jam_pelajaran';

    protected $fillable = ['pola_jam_id', 'hari', 'urutan', 'label', 'jam_mulai', 'jam_selesai', 'is_pelajaran'];

    protected function casts(): array
    {
        return [
            'hari' => Hari::class,
            'is_pelajaran' => 'boolean',
        ];
    }

    public function polaJam(): BelongsTo
    {
        return $this->belongsTo(PolaJam::class);
    }

    public function jadwalPelajaran(): HasMany
    {
        return $this->hasMany(JadwalPelajaran::class);
    }

    public function scopeIsPelajaran($query)
    {
        return $query->where('is_pelajaran', true);
    }
}
```

- [ ] **Step 4: Update `use App\Models\PolaJam;` di 32 file dari daftar pertama**

Di SETIAP file yang tercantum di bagian "Modify (32 file...)" di atas Files section, cari baris persis `use App\Models\PolaJam;` dan ganti dengan `use App\Domains\Akademik\Models\PolaJam;`. Kalau file itu JUGA punya baris `use App\Models\JamPelajaran;`, ganti juga jadi `use App\Domains\Akademik\Models\JamPelajaran;` di kesempatan yang sama (jangan buka file itu dua kali).

Verifikasi tidak ada yang kelewat dengan:
```bash
grep -rln "use App\\\\Models\\\\PolaJam;" --include="*.php" app database tests
```
Expected: kosong (tidak ada output).

- [ ] **Step 5: Update `use App\Models\JamPelajaran;` di 3 file tambahan**

- `app/Http/Controllers/Admin/JadwalPelajaranController.php`
- `app/Domains/Akademik/Actions/Jadwal/DuplicateJadwalAction.php`
- `database/factories/JadwalPelajaranFactory.php`

Cari baris persis `use App\Models\JamPelajaran;` di tiap file, ganti jadi `use App\Domains\Akademik\Models\JamPelajaran;`.

Verifikasi:
```bash
grep -rln "use App\\\\Models\\\\JamPelajaran;" --include="*.php" app database tests
```
Expected: kosong.

- [ ] **Step 6: Update relasi di `app/Models/Kelas.php`**

Cari baris:
```php
    public function polaJam(): BelongsTo
    {
        return $this->belongsTo(PolaJam::class);
    }
```

Tambahkan `use App\Domains\Akademik\Models\PolaJam;` ke bagian `use` di atas file (Kelas.php TETAP di `App\Models`, cuma menambah 1 import baru), lalu ganti method itu jadi:

```php
    public function polaJam(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Akademik\Models\PolaJam::class);
    }
```

(Pakai FQCN inline di sini, BUKAN `use` statement tambahan, supaya konsisten dengan pola relasi `ruangan()` di file yang sama — baris 21 — yang sudah pakai FQCN inline untuk model lintas-domain.)

- [ ] **Step 7: Jalankan test scoped luas (bukti tidak ada "Class not found" di seluruh ekosistem Akademik yang bersinggungan)**

Run: `php artisan test tests/Unit/Models/PolaJamTest.php tests/Unit/Models/JamPelajaranTest.php tests/Unit/Models/JadwalPelajaranTest.php tests/Feature/Admin/PolaJamCrudTest.php tests/Feature/Admin/JamPelajaranCrudTest.php tests/Feature/Admin/JadwalPelajaranCrudTest.php tests/Feature/Admin/KelasCrudTest.php tests/Feature/Admin/KelasPolaJamTest.php tests/Unit/KelasSeederTest.php tests/Unit/PolaJamSeederTest.php tests/Unit/JamPelajaranSeederTest.php tests/Unit/Services/SesiPembelajaranGeneratorTest.php tests/Unit/Services/SesiTematikGeneratorTest.php tests/Feature/Guru/JurnalKbmControllerTest.php tests/Feature/Guru/JurnalKbmTenantScopeTest.php tests/Feature/Akademik/JurnalKbmAdaptiveTest.php tests/Feature/Akademik/JadwalSarprasCollisionTest.php tests/Unit/Domains/Sarpras/GedungRuanganActionTest.php tests/Unit/Domains/Sarpras/SarprasModelsTest.php tests/Feature/Guru/KomponenPenilaianControllerTest.php tests/Feature/Guru/AsesmenControllerTest.php tests/Feature/Admin/KenaikanKelasControllerTest.php`
Expected: semua PASS, 0 failed, 0 error.

- [ ] **Step 8: Commit**

```bash
git add -A
git status
git commit -m "refactor(akademik): pindah model PolaJam & JamPelajaran ke Domains\Akademik\Models, update 36 file consumer"
```

---

## Task 5: Buat Action & DTO Modul Pola Jam, Refaktor `PolaJamController`

**Files:**
- Create: `app/Domains/Akademik/DataTransferObjects/PolaJamData.php`
- Create: `app/Domains/Akademik/DataTransferObjects/AssignKelasData.php`
- Create: `app/Domains/Akademik/Actions/PolaJam/CreatePolaJamAction.php`
- Create: `app/Domains/Akademik/Actions/PolaJam/UpdatePolaJamAction.php`
- Create: `app/Domains/Akademik/Actions/PolaJam/DeletePolaJamAction.php`
- Create: `app/Domains/Akademik/Actions/PolaJam/AssignKelasToPolaJamAction.php`
- Create: `app/Domains/Akademik/Actions/PolaJam/DuplicatePolaJamAction.php`
- Test: `tests/Unit/Domains/Akademik/Actions/PolaJam/DeletePolaJamActionTest.php`
- Test: `tests/Unit/Domains/Akademik/Actions/PolaJam/DuplicatePolaJamActionTest.php`
- Modify: `app/Http/Controllers/Admin/PolaJamController.php`

**Interfaces:**
- Consumes: `App\Domains\Akademik\Models\PolaJam`, `App\Domains\Akademik\Models\JamPelajaran` (Task 4)
- Produces: 5 Action, dipakai controller ini saja

- [ ] **Step 1: Buat `PolaJamData` dan `AssignKelasData`**

`app/Domains/Akademik/DataTransferObjects/PolaJamData.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class PolaJamData
{
    public function __construct(
        public string $nama,
        public ?int $lembagaId = null,
    ) {}
}
```

`app/Domains/Akademik/DataTransferObjects/AssignKelasData.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class AssignKelasData
{
    /**
     * @param  array<int, int>  $kelasIds
     */
    public function __construct(
        public array $kelasIds,
    ) {}
}
```

- [ ] **Step 2: Buat `CreatePolaJamAction` dan `UpdatePolaJamAction`**

`app/Domains/Akademik/Actions/PolaJam/CreatePolaJamAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\PolaJam;

use App\Domains\Akademik\DataTransferObjects\PolaJamData;
use App\Domains\Akademik\Models\PolaJam;

final class CreatePolaJamAction
{
    public function execute(PolaJamData $data): PolaJam
    {
        return PolaJam::create([
            'nama' => $data->nama,
            'lembaga_id' => $data->lembagaId,
        ]);
    }
}
```

`app/Domains/Akademik/Actions/PolaJam/UpdatePolaJamAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\PolaJam;

use App\Domains\Akademik\DataTransferObjects\PolaJamData;
use App\Domains\Akademik\Models\PolaJam;

final class UpdatePolaJamAction
{
    public function execute(PolaJam $polaJam, PolaJamData $data): PolaJam
    {
        $polaJam->update(['nama' => $data->nama]);

        return $polaJam->fresh();
    }
}
```

- [ ] **Step 3: Buat `DeletePolaJamAction`**

Logika dipindah persis dari `PolaJamController::destroy()` baris 78-93 versi sebelum migrasi — 2 guard dipertahankan persis.

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\PolaJam;

use App\Domains\Akademik\Models\PolaJam;
use Illuminate\Validation\ValidationException;

final class DeletePolaJamAction
{
    public function execute(PolaJam $polaJam): void
    {
        if ($polaJam->kelas()->exists()) {
            throw ValidationException::withMessages([
                'pola_jam' => 'Pola jam ini masih dipakai oleh satu atau lebih kelas — lepaskan dulu sebelum menghapus.',
            ]);
        }

        if ($polaJam->jamPelajaran()->whereHas('jadwalPelajaran')->exists()) {
            throw ValidationException::withMessages([
                'pola_jam' => 'Pola jam ini memiliki jam pelajaran yang sudah dipakai di Jadwal Pelajaran — hapus jadwalnya dulu sebelum menghapus pola jam ini.',
            ]);
        }

        $polaJam->delete();
    }
}
```

- [ ] **Step 4: Tulis test untuk `DeletePolaJamAction`**

```php
<?php

use App\Domains\Akademik\Actions\PolaJam\DeletePolaJamAction;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('deletes a pola jam with no dependents', function () {
    $polaJam = PolaJam::factory()->create();

    (new DeletePolaJamAction)->execute($polaJam);

    expect(PolaJam::find($polaJam->id))->toBeNull();
});

it('refuses to delete a pola jam still linked to a kelas', function () {
    $polaJam = PolaJam::factory()->create();
    Kelas::factory()->create(['pola_jam_id' => $polaJam->id]);

    expect(fn () => (new DeletePolaJamAction)->execute($polaJam))
        ->toThrow(ValidationException::class);
    expect(PolaJam::find($polaJam->id))->not->toBeNull();
});

it('refuses to delete a pola jam whose jam pelajaran is already used in jadwal pelajaran', function () {
    $polaJam = PolaJam::factory()->has(
        \App\Domains\Akademik\Models\JamPelajaran::factory()->count(1),
        'jamPelajaran'
    )->create();
    $jamPelajaran = $polaJam->jamPelajaran->first();
    JadwalPelajaran::factory()->create(['jam_pelajaran_id' => $jamPelajaran->id]);

    expect(fn () => (new DeletePolaJamAction)->execute($polaJam))
        ->toThrow(ValidationException::class);
});
```

- [ ] **Step 5: Jalankan test baru, verifikasi PASS**

Run: `php artisan test tests/Unit/Domains/Akademik/Actions/PolaJam/DeletePolaJamActionTest.php`
Expected: 3 passed. Kalau factory `JadwalPelajaran::factory()` butuh field lain yang required (`kelas_id`, `mata_pelajaran_id`, `guru_id`, `semester_id`), tambahkan sesuai default factory yang ada — cek `database/factories/JadwalPelajaranFactory.php` untuk field yang wajib diisi eksplisit.

- [ ] **Step 6: Buat `AssignKelasToPolaJamAction`**

Logika dipindah persis dari `assignKelas()` baris 95-121 versi sebelum migrasi.

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\PolaJam;

use App\Domains\Akademik\DataTransferObjects\AssignKelasData;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\Kelas;
use Illuminate\Validation\ValidationException;

final class AssignKelasToPolaJamAction
{
    public function execute(PolaJam $polaJam, AssignKelasData $data): void
    {
        $kelasTerpilih = Kelas::whereIn('id', $data->kelasIds)->get();

        if ($kelasTerpilih->count() !== count($data->kelasIds)) {
            throw ValidationException::withMessages([
                'kelas_ids' => 'Salah satu kelas yang dipilih tidak ditemukan.',
            ]);
        }

        foreach ($kelasTerpilih as $kelas) {
            if ($kelas->lembaga_id !== $polaJam->lembaga_id) {
                throw ValidationException::withMessages([
                    'kelas_ids' => 'Kelas dan pola jam harus berasal dari lembaga yang sama.',
                ]);
            }
        }

        Kelas::where('pola_jam_id', $polaJam->id)->whereNotIn('id', $data->kelasIds)->update(['pola_jam_id' => null]);
        Kelas::whereIn('id', $data->kelasIds)->update(['pola_jam_id' => $polaJam->id]);
    }
}
```

- [ ] **Step 7: Buat `DuplicatePolaJamAction`**

Logika dipindah persis dari `duplicate()` baris 123-148 versi sebelum migrasi.

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\PolaJam;

use App\Domains\Akademik\Models\PolaJam;
use Illuminate\Support\Facades\DB;

final class DuplicatePolaJamAction
{
    public function execute(PolaJam $polaJam): array
    {
        return DB::transaction(function () use ($polaJam) {
            $newPola = PolaJam::create([
                'nama' => $polaJam->nama . ' (Salinan)',
                'lembaga_id' => $polaJam->lembaga_id,
            ]);

            foreach ($polaJam->jamPelajaran as $slot) {
                $newPola->jamPelajaran()->create([
                    'hari' => $slot->hari->value,
                    'urutan' => $slot->urutan,
                    'jam_mulai' => $slot->jam_mulai,
                    'jam_selesai' => $slot->jam_selesai,
                    'label' => $slot->label,
                    'is_pelajaran' => $slot->is_pelajaran,
                ]);
            }

            return [$newPola, $polaJam->jamPelajaran->count()];
        });
    }
}
```

- [ ] **Step 8: Tulis test untuk `DuplicatePolaJamAction`**

```php
<?php

use App\Domains\Akademik\Actions\PolaJam\DuplicatePolaJamAction;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Akademik\Models\PolaJam;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('duplicates a pola jam and all of its jam pelajaran slots', function () {
    $polaJam = PolaJam::factory()->create(['nama' => 'Pola Reguler']);
    JamPelajaran::factory()->count(3)->create(['pola_jam_id' => $polaJam->id]);

    [$duplikat, $jumlahSlot] = (new DuplicatePolaJamAction)->execute($polaJam->fresh(['jamPelajaran']));

    expect($duplikat->nama)->toBe('Pola Reguler (Salinan)')
        ->and($jumlahSlot)->toBe(3)
        ->and($duplikat->jamPelajaran()->count())->toBe(3);
});
```

- [ ] **Step 9: Jalankan test baru, verifikasi PASS**

Run: `php artisan test tests/Unit/Domains/Akademik/Actions/PolaJam/DuplicatePolaJamActionTest.php`
Expected: 1 passed.

- [ ] **Step 10: Refaktor `PolaJamController`**

Ganti seluruh isi `app/Http/Controllers/Admin/PolaJamController.php` jadi:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\PolaJam\AssignKelasToPolaJamAction;
use App\Domains\Akademik\Actions\PolaJam\CreatePolaJamAction;
use App\Domains\Akademik\Actions\PolaJam\DeletePolaJamAction;
use App\Domains\Akademik\Actions\PolaJam\DuplicatePolaJamAction;
use App\Domains\Akademik\Actions\PolaJam\UpdatePolaJamAction;
use App\Domains\Akademik\DataTransferObjects\AssignKelasData;
use App\Domains\Akademik\DataTransferObjects\PolaJamData;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\Kelas;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PolaJamController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('pola-jam.view');

        return view('admin.pola-jam.index', [
            'polaJamList' => PolaJam::with(['jamPelajaran', 'lembaga', 'kelas.tahunAjaran'])->orderBy('nama')->get(),
            'kelasList' => Kelas::with(['tahunAjaran', 'polaJam'])->orderBy('nama')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('pola-jam.create');

        return view('admin.pola-jam.create');
    }

    public function store(Request $request, CreatePolaJamAction $action): RedirectResponse
    {
        $this->authorize('pola-jam.create');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
        ]);

        $lembagaId = null;
        if ($request->user()->widestScopeLevel() === 'yayasan') {
            $lembagaId = session('active_lembaga_id');

            if ($lembagaId === null) {
                return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat pola jam.'])->withInput();
            }
        }

        $action->execute(new PolaJamData(nama: $data['nama'], lembagaId: $lembagaId));

        return redirect()->route('admin.pola-jam.index')->with('status', 'Pola jam berhasil dibuat.');
    }

    public function edit(PolaJam $polaJam): View
    {
        $this->authorize('pola-jam.edit');

        return view('admin.pola-jam.edit', ['polaJam' => $polaJam]);
    }

    public function update(Request $request, PolaJam $polaJam, UpdatePolaJamAction $action): RedirectResponse
    {
        $this->authorize('pola-jam.edit');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
        ]);

        $action->execute($polaJam, new PolaJamData(nama: $data['nama'], lembagaId: $polaJam->lembaga_id));

        return redirect()->route('admin.pola-jam.index')->with('status', 'Pola jam berhasil diperbarui.');
    }

    public function destroy(PolaJam $polaJam, DeletePolaJamAction $action): RedirectResponse
    {
        $this->authorize('pola-jam.delete');

        try {
            $action->execute($polaJam);
        } catch (ValidationException $e) {
            return back()->withErrors(['pola_jam' => $e->validator->errors()->first('pola_jam')]);
        }

        return redirect()->route('admin.pola-jam.index')->with('status', 'Pola jam berhasil dihapus.');
    }

    public function assignKelas(Request $request, PolaJam $polaJam, AssignKelasToPolaJamAction $action): RedirectResponse
    {
        $this->authorize('kelas.edit');

        $data = $request->validate([
            'kelas_ids' => ['nullable', 'array'],
            'kelas_ids.*' => ['integer'],
        ]);

        try {
            $action->execute($polaJam, new AssignKelasData(kelasIds: $data['kelas_ids'] ?? []));
        } catch (ValidationException $e) {
            return back()->withErrors(['kelas_ids' => $e->validator->errors()->first('kelas_ids')]);
        }

        return redirect()->route('admin.pola-jam.index')->with('status', 'Tautan kelas untuk pola jam ini berhasil disimpan.');
    }

    public function duplicate(PolaJam $polaJam, DuplicatePolaJamAction $action): RedirectResponse
    {
        $this->authorize('pola-jam.create');

        [$newPola, $count] = $action->execute($polaJam);

        return redirect()->route('admin.pola-jam.index')->with('status', "Pola jam \"{$polaJam->nama}\" beserta {$count} slot jam berhasil diduplikasi.");
    }
}
```

- [ ] **Step 11: Jalankan SEMUA test Modul Pola Jam (controller), verifikasi hijau**

Run: `php artisan test tests/Feature/Admin/PolaJamCrudTest.php tests/Unit/Domains/Akademik/Actions/PolaJam/DeletePolaJamActionTest.php tests/Unit/Domains/Akademik/Actions/PolaJam/DuplicatePolaJamActionTest.php`
Expected: semua PASS. Kalau `PolaJamCrudTest.php` gagal karena pesan error beda, cek `git show b8b6242:app/Http/Controllers/Admin/PolaJamController.php` untuk bandingkan kata-per-kata.

- [ ] **Step 12: Commit**

```bash
git add app/Domains/Akademik/DataTransferObjects/PolaJamData.php app/Domains/Akademik/DataTransferObjects/AssignKelasData.php app/Domains/Akademik/Actions/PolaJam/ app/Http/Controllers/Admin/PolaJamController.php tests/Unit/Domains/Akademik/Actions/PolaJam/
git commit -m "refactor(akademik): ekstrak Action Modul Pola Jam, controller jadi thin"
```

---

## Task 6: Buat Action & DTO Modul Jam Pelajaran, Refaktor `JamPelajaranController`

**Files:**
- Create: `app/Domains/Akademik/DataTransferObjects/JamPelajaranData.php`
- Create: `app/Domains/Akademik/Actions/PolaJam/CreateJamPelajaranAction.php`
- Create: `app/Domains/Akademik/Actions/PolaJam/UpdateJamPelajaranAction.php`
- Create: `app/Domains/Akademik/Actions/PolaJam/DeleteJamPelajaranAction.php`
- Test: `tests/Unit/Domains/Akademik/Actions/PolaJam/CreateJamPelajaranActionTest.php`
- Modify: `app/Http/Controllers/Admin/JamPelajaranController.php`

**Interfaces:**
- Consumes: `App\Domains\Akademik\Models\PolaJam`, `App\Domains\Akademik\Models\JamPelajaran` (Task 4)
- Produces: 3 Action, dipakai controller ini saja

Action ditaruh di folder `Actions/PolaJam/` yang SAMA dengan Task 5 (bukan folder `JamPelajaran/` terpisah) — sesuai spec §3.2/§3.3: `PolaJam` dan `JamPelajaran` 1 agregat, 1 sub-area domain.

- [ ] **Step 1: Buat `JamPelajaranData`**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class JamPelajaranData
{
    /**
     * @param  array<int, string>  $hari  nilai enum App\Enums\Hari (mis. 'senin', 'selasa')
     */
    public function __construct(
        public int $polaJamId,
        public array $hari,
        public int $urutan,
        public string $label,
        public string $jamMulai,
        public string $jamSelesai,
        public bool $isPelajaran,
    ) {}
}
```

- [ ] **Step 2: Buat `CreateJamPelajaranAction`**

Logika dipindah persis dari `JamPelajaranController::store()` + `tabrakanSlot()` + `formatDaftarHari()` baris 18-63, 122-142 versi sebelum migrasi.

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\PolaJam;

use App\Domains\Akademik\DataTransferObjects\JamPelajaranData;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Enums\Hari;

final class CreateJamPelajaranAction
{
    /**
     * @return array{berhasil: array<int, string>, dilewati: array<int, string>}
     */
    public function execute(JamPelajaranData $data): array
    {
        $berhasil = [];
        $dilewati = [];

        foreach ($data->hari as $hari) {
            if ($this->tabrakanSlot($data->polaJamId, $hari, $data->urutan)) {
                $dilewati[] = $hari;
                continue;
            }

            JamPelajaran::create([
                'pola_jam_id' => $data->polaJamId,
                'hari' => $hari,
                'urutan' => $data->urutan,
                'label' => $data->label,
                'jam_mulai' => $data->jamMulai,
                'jam_selesai' => $data->jamSelesai,
                'is_pelajaran' => $data->isPelajaran,
            ]);
            $berhasil[] = $hari;
        }

        return ['berhasil' => $berhasil, 'dilewati' => $dilewati];
    }

    private function tabrakanSlot(int $polaJamId, string $hari, int $urutan, ?int $kecualiId = null): bool
    {
        return JamPelajaran::where('pola_jam_id', $polaJamId)
            ->where('hari', $hari)
            ->where('urutan', $urutan)
            ->when($kecualiId, fn ($q) => $q->where('id', '!=', $kecualiId))
            ->exists();
    }
}
```

- [ ] **Step 3: Tulis test untuk `CreateJamPelajaranAction`**

```php
<?php

use App\Domains\Akademik\Actions\PolaJam\CreateJamPelajaranAction;
use App\Domains\Akademik\DataTransferObjects\JamPelajaranData;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Akademik\Models\PolaJam;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates one slot per requested hari', function () {
    $polaJam = PolaJam::factory()->create();

    $result = (new CreateJamPelajaranAction)->execute(new JamPelajaranData(
        polaJamId: $polaJam->id,
        hari: ['senin', 'selasa'],
        urutan: 1,
        label: 'Jam ke-1',
        jamMulai: '07:00',
        jamSelesai: '07:45',
        isPelajaran: true,
    ));

    expect($result['berhasil'])->toBe(['senin', 'selasa'])
        ->and($result['dilewati'])->toBe([])
        ->and(JamPelajaran::where('pola_jam_id', $polaJam->id)->count())->toBe(2);
});

it('skips a hari whose urutan slot is already taken and reports it', function () {
    $polaJam = PolaJam::factory()->create();
    JamPelajaran::factory()->create(['pola_jam_id' => $polaJam->id, 'hari' => 'senin', 'urutan' => 1]);

    $result = (new CreateJamPelajaranAction)->execute(new JamPelajaranData(
        polaJamId: $polaJam->id,
        hari: ['senin', 'selasa'],
        urutan: 1,
        label: 'Jam ke-1',
        jamMulai: '07:00',
        jamSelesai: '07:45',
        isPelajaran: true,
    ));

    expect($result['berhasil'])->toBe(['selasa'])
        ->and($result['dilewati'])->toBe(['senin']);
});
```

- [ ] **Step 4: Jalankan test baru, verifikasi PASS**

Run: `php artisan test tests/Unit/Domains/Akademik/Actions/PolaJam/CreateJamPelajaranActionTest.php`
Expected: 2 passed.

- [ ] **Step 5: Buat `UpdateJamPelajaranAction` dan `DeleteJamPelajaranAction`**

Logika dipindah persis dari `update()` baris 76-103 dan `destroy()` baris 105-120 versi sebelum migrasi.

`app/Domains/Akademik/Actions/PolaJam/UpdateJamPelajaranAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\PolaJam;

use App\Domains\Akademik\Models\JamPelajaran;
use Illuminate\Validation\ValidationException;

final class UpdateJamPelajaranAction
{
    public function execute(JamPelajaran $jamPelajaran, string $hari, int $urutan, string $label, string $jamMulai, string $jamSelesai, bool $isPelajaran): JamPelajaran
    {
        if ($this->tabrakanSlot($jamPelajaran->pola_jam_id, $hari, $urutan, $jamPelajaran->id)) {
            throw ValidationException::withMessages([
                'urutan' => 'Urutan ini sudah dipakai pada hari yang sama di pola jam ini.',
            ]);
        }

        $jamPelajaran->update([
            'hari' => $hari,
            'urutan' => $urutan,
            'label' => $label,
            'jam_mulai' => $jamMulai,
            'jam_selesai' => $jamSelesai,
            'is_pelajaran' => $isPelajaran,
        ]);

        return $jamPelajaran->fresh();
    }

    private function tabrakanSlot(int $polaJamId, string $hari, int $urutan, ?int $kecualiId = null): bool
    {
        return JamPelajaran::where('pola_jam_id', $polaJamId)
            ->where('hari', $hari)
            ->where('urutan', $urutan)
            ->when($kecualiId, fn ($q) => $q->where('id', '!=', $kecualiId))
            ->exists();
    }
}
```

`app/Domains/Akademik/Actions/PolaJam/DeleteJamPelajaranAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\PolaJam;

use App\Domains\Akademik\Models\JamPelajaran;
use Illuminate\Validation\ValidationException;

final class DeleteJamPelajaranAction
{
    public function execute(JamPelajaran $jamPelajaran): void
    {
        if ($jamPelajaran->jadwalPelajaran()->exists()) {
            throw ValidationException::withMessages([
                'jam_pelajaran' => 'Slot ini masih dipakai di Jadwal Pelajaran — hapus jadwalnya dulu sebelum menghapus slot ini.',
            ]);
        }

        $jamPelajaran->delete();
    }
}
```

- [ ] **Step 6: Refaktor `JamPelajaranController`**

Ganti seluruh isi `app/Http/Controllers/Admin/JamPelajaranController.php` jadi:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\PolaJam\CreateJamPelajaranAction;
use App\Domains\Akademik\Actions\PolaJam\DeleteJamPelajaranAction;
use App\Domains\Akademik\Actions\PolaJam\UpdateJamPelajaranAction;
use App\Domains\Akademik\DataTransferObjects\JamPelajaranData;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Akademik\Models\PolaJam;
use App\Enums\Hari;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JamPelajaranController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request, CreateJamPelajaranAction $action): RedirectResponse
    {
        $this->authorize('jam-pelajaran.create');

        $data = $request->validate([
            'pola_jam_id' => ['required', 'integer'],
            'hari' => ['required', 'array', 'min:1'],
            'hari.*' => ['in:senin,selasa,rabu,kamis,jumat,sabtu,minggu'],
            'urutan' => ['required', 'integer', 'min:1'],
            'label' => ['required', 'string', 'max:255'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'is_pelajaran' => ['required', 'boolean'],
        ]);

        $polaJam = PolaJam::find($data['pola_jam_id']);
        if (! $polaJam) {
            abort(404);
        }

        $result = $action->execute(new JamPelajaranData(
            polaJamId: $data['pola_jam_id'],
            hari: $data['hari'],
            urutan: $data['urutan'],
            label: $data['label'],
            jamMulai: $data['jam_mulai'],
            jamSelesai: $data['jam_selesai'],
            isPelajaran: $data['is_pelajaran'],
        ));

        if (empty($result['berhasil'])) {
            return back()->withErrors([
                'hari' => 'Semua hari yang dipilih (' . $this->formatDaftarHari($data['hari']) . ') sudah punya slot di urutan ini — tidak ada yang ditambahkan.',
            ])->withInput();
        }

        $status = 'Slot berhasil ditambahkan untuk ' . $this->formatDaftarHari($result['berhasil']) . '.';
        if (! empty($result['dilewati'])) {
            $status .= ' ' . $this->formatDaftarHari($result['dilewati']) . ' dilewati karena urutan ini sudah dipakai.';
        }

        return redirect()->route('admin.pola-jam.index')->with('status', $status);
    }

    public function edit(JamPelajaran $jamPelajaran): View
    {
        $this->authorize('jam-pelajaran.edit');

        if (! PolaJam::find($jamPelajaran->pola_jam_id)) {
            abort(404);
        }

        return view('admin.jam-pelajaran.edit', ['jamPelajaran' => $jamPelajaran]);
    }

    public function update(Request $request, JamPelajaran $jamPelajaran, UpdateJamPelajaranAction $action): RedirectResponse
    {
        $this->authorize('jam-pelajaran.edit');

        if (! PolaJam::find($jamPelajaran->pola_jam_id)) {
            abort(404);
        }

        $data = $request->validate([
            'hari' => ['sometimes', 'in:senin,selasa,rabu,kamis,jumat,sabtu,minggu'],
            'urutan' => ['sometimes', 'integer', 'min:1'],
            'label' => ['required', 'string', 'max:255'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'is_pelajaran' => ['required', 'boolean'],
        ]);

        $hari = $data['hari'] ?? $jamPelajaran->hari->value;
        $urutan = $data['urutan'] ?? $jamPelajaran->urutan;

        try {
            $action->execute($jamPelajaran, $hari, $urutan, $data['label'], $data['jam_mulai'], $data['jam_selesai'], $data['is_pelajaran']);
        } catch (ValidationException $e) {
            return back()->withErrors(['urutan' => $e->validator->errors()->first('urutan')])->withInput();
        }

        return redirect()->route('admin.pola-jam.index')->with('status', 'Jam pelajaran berhasil diperbarui.');
    }

    public function destroy(JamPelajaran $jamPelajaran, DeleteJamPelajaranAction $action): RedirectResponse
    {
        $this->authorize('jam-pelajaran.delete');

        try {
            $action->execute($jamPelajaran);
        } catch (ValidationException $e) {
            return back()->withErrors(['jam_pelajaran' => $e->validator->errors()->first('jam_pelajaran')]);
        }

        return redirect()->route('admin.pola-jam.index')->with('status', 'Jam pelajaran berhasil dihapus.');
    }

    private function formatDaftarHari(array $nilaiHari): string
    {
        $label = collect($nilaiHari)->map(fn ($h) => Hari::from($h)->label())->all();

        if (count($label) === 1) {
            return $label[0];
        }

        $terakhir = array_pop($label);

        return implode(', ', $label) . ' dan ' . $terakhir;
    }
}
```

- [ ] **Step 7: Jalankan SEMUA test Modul Jam Pelajaran, verifikasi hijau**

Run: `php artisan test tests/Feature/Admin/JamPelajaranCrudTest.php tests/Unit/Domains/Akademik/Actions/PolaJam/CreateJamPelajaranActionTest.php`
Expected: semua PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Domains/Akademik/DataTransferObjects/JamPelajaranData.php app/Domains/Akademik/Actions/PolaJam/CreateJamPelajaranAction.php app/Domains/Akademik/Actions/PolaJam/UpdateJamPelajaranAction.php app/Domains/Akademik/Actions/PolaJam/DeleteJamPelajaranAction.php app/Http/Controllers/Admin/JamPelajaranController.php tests/Unit/Domains/Akademik/Actions/PolaJam/CreateJamPelajaranActionTest.php
git commit -m "refactor(akademik): ekstrak Action Modul Jam Pelajaran, controller jadi thin"
```

---

## Task 7: Buat Action Modul Kenaikan Kelas (dengan perubahan perilaku salinJadwal)

**Files:**
- Create: `app/Domains/Akademik/DataTransferObjects/KenaikanKelasData.php`
- Create: `app/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasAction.php`
- Test: `tests/Unit/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasActionTest.php`
- Modify: `app/Http/Controllers/Admin/KenaikanKelasController.php`

**Interfaces:**
- Consumes: `App\Domains\Akademik\Actions\Jadwal\CreateJadwalPelajaranAction` (sudah ada, tidak berubah), `App\Domains\Akademik\DataTransferObjects\JadwalPelajaranData` (sudah ada, konstruktor: `lembagaId, kelasId, guruId, jamPelajaranId, semesterId, mataPelajaranId = null, ruanganId = null`, method `fromArray(array $data): self`)
- Produces: `ProsesKenaikanKelasAction` dengan return type `array{jadwalGagal: array<int, string>}`, dipakai controller ini saja

**PENTING — task ini SATU-SATUNYA yang mengubah perilaku nyata** (bukan zero-behavior-change). Baca §4.3 spec (`.agents/specs/2026-08-20-akademik-05-migrasi-domain-tersisa.md`) sebelum mulai kalau ada keraguan.

- [ ] **Step 1: Buat `KenaikanKelasData`**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class KenaikanKelasData
{
    /**
     * @param  array<int, array{tindakan: string, kelas_baru_id: ?int, salin_jadwal: ?bool, semester_tujuan_id: ?int}>  $mapping  keyed by kelas lama id
     */
    public function __construct(
        public array $mapping,
    ) {}
}
```

- [ ] **Step 2: Buat `ProsesKenaikanKelasAction`**

Logika inti dipindah dari `KenaikanKelasController::store()` baris 45-93 versi sebelum migrasi, DENGAN perubahan `salinJadwal()` sesuai §4.3 spec: memanggil `CreateJadwalPelajaranAction` per baris, menangkap `ValidationException` per baris (skip, bukan propagate ke transaksi luar), mengumpulkan baris yang gagal.

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\KenaikanKelas;

use App\Domains\Akademik\Actions\Jadwal\CreateJadwalPelajaranAction;
use App\Domains\Akademik\DataTransferObjects\JadwalPelajaranData;
use App\Domains\Akademik\DataTransferObjects\KenaikanKelasData;
use App\Enums\StatusSiswa;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProsesKenaikanKelasAction
{
    public function __construct(
        private readonly CreateJadwalPelajaranAction $createJadwalPelajaranAction
    ) {}

    /**
     * @return array{jadwalGagal: array<int, string>}
     *
     * @throws \DomainException kalau kelas tujuan berada di tahun ajaran yang sama dengan kelas asal
     */
    public function execute(KenaikanKelasData $data): array
    {
        $jadwalGagal = [];

        DB::transaction(function () use ($data, &$jadwalGagal) {
            foreach ($data->mapping as $kelasLamaId => $aksi) {
                $kelasLama = Kelas::findOrFail($kelasLamaId);

                if ($aksi['tindakan'] === 'lulus') {
                    Siswa::where('kelas_id', $kelasLama->id)->update([
                        'status' => StatusSiswa::Lulus->value,
                        'kelas_id' => null,
                    ]);

                    continue;
                }

                $kelasBaru = Kelas::find($aksi['kelas_baru_id']);
                abort_if($kelasBaru === null || $kelasBaru->lembaga_id !== $kelasLama->lembaga_id, 404);

                if ($kelasBaru->tahun_ajaran_id === $kelasLama->tahun_ajaran_id) {
                    throw new \DomainException("Kelas tujuan \"{$kelasBaru->nama}\" masih berada di tahun ajaran yang sama dengan kelas asal \"{$kelasLama->nama}\". Pilih kelas tujuan dari tahun ajaran berikutnya.");
                }

                Siswa::where('kelas_id', $kelasLama->id)->update(['kelas_id' => $kelasBaru->id]);

                if (($aksi['salin_jadwal'] ?? false) && ! empty($aksi['semester_tujuan_id'])) {
                    $semesterTujuan = Semester::find($aksi['semester_tujuan_id']);
                    abort_if($semesterTujuan === null || $semesterTujuan->lembaga_id !== $kelasLama->lembaga_id, 404);

                    $gagalDiBaris = $this->salinJadwal($kelasLama, $kelasBaru, $semesterTujuan->id);
                    $jadwalGagal = array_merge($jadwalGagal, $gagalDiBaris);
                }
            }
        });

        return ['jadwalGagal' => $jadwalGagal];
    }

    /**
     * @return array<int, string> deskripsi baris yang gagal disalin karena bentrok
     */
    private function salinJadwal(Kelas $kelasLama, Kelas $kelasBaru, int $semesterTujuanId): array
    {
        $jadwalLama = JadwalPelajaran::where('kelas_id', $kelasLama->id)->with('jamPelajaran')->get();
        $gagal = [];

        foreach ($jadwalLama as $jadwal) {
            $sudahAda = JadwalPelajaran::where('kelas_id', $kelasBaru->id)
                ->where('jam_pelajaran_id', $jadwal->jam_pelajaran_id)
                ->where('semester_id', $semesterTujuanId)
                ->exists();

            if ($sudahAda) {
                continue;
            }

            try {
                $this->createJadwalPelajaranAction->execute(JadwalPelajaranData::fromArray([
                    'lembaga_id' => $kelasBaru->lembaga_id,
                    'kelas_id' => $kelasBaru->id,
                    'guru_id' => $jadwal->guru_id,
                    'jam_pelajaran_id' => $jadwal->jam_pelajaran_id,
                    'semester_id' => $semesterTujuanId,
                    'mata_pelajaran_id' => $jadwal->mata_pelajaran_id,
                    'ruangan_id' => $jadwal->ruangan_id,
                ]));
            } catch (ValidationException $e) {
                $labelJam = $jadwal->jamPelajaran->label ?? "slot #{$jadwal->jam_pelajaran_id}";
                $gagal[] = "{$kelasLama->nama} → {$kelasBaru->nama} ({$labelJam}): " . $e->validator->errors()->first();
            }
        }

        return $gagal;
    }
}
```

**Catatan implementasi penting:**
- `DB::transaction()` di sini membungkus SELURUH proses kenaikan kelas (siswa naik/lulus untuk semua mapping) — SAMA seperti kode asli. `ValidationException` dari `CreateJadwalPelajaranAction` ditangkap LOKAL di dalam `salinJadwal()` (try-catch), TIDAK PERNAH menembus ke `DB::transaction()` luar — inilah mekanisme "skip, bukan rollback total" yang disepakati.
- `$jadwalGagal` diisi lewat referensi (`&$jadwalGagal`) karena closure `DB::transaction()` perlu mengembalikan nilai keluar dari transaksinya sendiri.
- Ditambahkan pengecekan `$sudahAda` (baris slot yang sama persis sudah ada) SEBELUM memanggil Action — ini MEMPERTAHANKAN perilaku `firstOrCreate()` asli (idempoten, tidak membuat duplikat kalau dijalankan dua kali), yang tidak otomatis didapat dari `CreateJadwalPelajaranAction` (yang justru akan melempar `ValidationException` "sudah ada slot" kalau dipanggil untuk baris yang sama — treat that sebagai kondisi normal-skip, bukan gagal-bentrok, makanya dicek manual duluan bukan mengandalkan exception message).

- [ ] **Step 3: Tulis test untuk `ProsesKenaikanKelasAction`**

```php
<?php

use App\Domains\Akademik\Actions\Jadwal\CreateJadwalPelajaranAction;
use App\Domains\Akademik\Actions\KenaikanKelas\ProsesKenaikanKelasAction;
use App\Domains\Akademik\DataTransferObjects\KenaikanKelasData;
use App\Domains\Sarpras\Actions\ValidateRoomClashAction;
use App\Enums\StatusSiswa;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function buatAction(): ProsesKenaikanKelasAction
{
    return new ProsesKenaikanKelasAction(new CreateJadwalPelajaranAction(new ValidateRoomClashAction));
}

it('promotes siswa to the destination kelas and marks lulus siswa accordingly', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id]);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id]);
    $kelasLulus = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id]);
    $siswaNaik = Siswa::factory()->create(['kelas_id' => $kelasLama->id]);
    $siswaLulus = Siswa::factory()->create(['kelas_id' => $kelasLulus->id]);

    $result = buatAction()->execute(new KenaikanKelasData(mapping: [
        $kelasLama->id => ['tindakan' => 'naik', 'kelas_baru_id' => $kelasBaru->id, 'salin_jadwal' => false, 'semester_tujuan_id' => null],
        $kelasLulus->id => ['tindakan' => 'lulus', 'kelas_baru_id' => null, 'salin_jadwal' => false, 'semester_tujuan_id' => null],
    ]));

    expect($result['jadwalGagal'])->toBe([])
        ->and($siswaNaik->fresh()->kelas_id)->toBe($kelasBaru->id)
        ->and($siswaLulus->fresh()->status)->toBe(StatusSiswa::Lulus)
        ->and($siswaLulus->fresh()->kelas_id)->toBeNull();
});

it('throws a DomainException when kelas tujuan is in the same tahun ajaran as kelas lama', function () {
    $lembaga = Lembaga::factory()->create();
    $tahun = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahun->id]);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahun->id]);

    expect(fn () => buatAction()->execute(new KenaikanKelasData(mapping: [
        $kelasLama->id => ['tindakan' => 'naik', 'kelas_baru_id' => $kelasBaru->id, 'salin_jadwal' => false, 'semester_tujuan_id' => null],
    ])))->toThrow(\DomainException::class);
});

it('skips a jadwal row that clashes on guru at the destination and still promotes the siswa', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterTujuan = Semester::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id]);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id]);
    $siswa = Siswa::factory()->create(['kelas_id' => $kelasLama->id]);

    $jamPelajaran = JamPelajaran::factory()->create(['label' => 'Jam ke-1']);
    $guru = \App\Models\Guru::factory()->create();

    // Guru sudah mengajar kelas LAIN pada slot yang sama di semester tujuan — akan bentrok.
    JadwalPelajaran::factory()->create([
        'kelas_id' => Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id])->id,
        'guru_id' => $guru->id,
        'jam_pelajaran_id' => $jamPelajaran->id,
        'semester_id' => $semesterTujuan->id,
    ]);

    // Jadwal lama yang akan disalin, pakai guru yang sama di jam yang sama.
    JadwalPelajaran::factory()->create([
        'kelas_id' => $kelasLama->id,
        'guru_id' => $guru->id,
        'jam_pelajaran_id' => $jamPelajaran->id,
        'semester_id' => Semester::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id])->id,
    ]);

    $result = buatAction()->execute(new KenaikanKelasData(mapping: [
        $kelasLama->id => [
            'tindakan' => 'naik',
            'kelas_baru_id' => $kelasBaru->id,
            'salin_jadwal' => true,
            'semester_tujuan_id' => $semesterTujuan->id,
        ],
    ]));

    expect($result['jadwalGagal'])->toHaveCount(1)
        ->and($siswa->fresh()->kelas_id)->toBe($kelasBaru->id)
        ->and(JadwalPelajaran::where('kelas_id', $kelasBaru->id)->where('semester_id', $semesterTujuan->id)->count())->toBe(0);
});
```

- [ ] **Step 4: Jalankan test baru, verifikasi PASS**

Run: `php artisan test tests/Unit/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasActionTest.php`
Expected: 3 passed. Kalau test ke-3 (skip-bentrok) gagal karena `$result['jadwalGagal']` kosong padahal seharusnya 1, cek dulu apakah `ValidateRoomClashAction`/`CreateJadwalPelajaranAction` benar-benar mendeteksi bentrok guru untuk kombinasi `guru_id` + `semester_id` + `jam_pelajaran_id` yang dipakai di test (lihat isi asli `CreateJadwalPelajaranAction::execute()` baris "Validasi Bentrok Guru Pengampu").

- [ ] **Step 5: Refaktor `KenaikanKelasController`**

Ganti seluruh isi `app/Http/Controllers/Admin/KenaikanKelasController.php` jadi:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\KenaikanKelas\ProsesKenaikanKelasAction;
use App\Domains\Akademik\DataTransferObjects\KenaikanKelasData;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class KenaikanKelasController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('kenaikan-kelas.kelola');

        $tahunAjaranId = $request->query('tahun_ajaran_id');
        $tahunAjaranTujuanId = $request->query('tahun_ajaran_tujuan_id');

        return view('admin.kenaikan-kelas.index', [
            'tahunAjaranList' => TahunAjaran::orderByDesc('tanggal_mulai')->get(),
            'kelasLamaList' => $tahunAjaranId
                ? Kelas::where('tahun_ajaran_id', $tahunAjaranId)->withCount('siswa')->orderBy('nama')->get()
                : collect(),
            'kelasTujuanList' => $tahunAjaranTujuanId
                ? Kelas::where('tahun_ajaran_id', $tahunAjaranTujuanId)->orderBy('nama')->get()
                : collect(),
            'semesterList' => $tahunAjaranTujuanId
                ? Semester::where('tahun_ajaran_id', $tahunAjaranTujuanId)->orderByDesc('id')->get()
                : collect(),
            'tahunAjaranId' => $tahunAjaranId,
            'tahunAjaranTujuanId' => $tahunAjaranTujuanId,
        ]);
    }

    public function store(Request $request, ProsesKenaikanKelasAction $action): RedirectResponse
    {
        $this->authorize('kenaikan-kelas.kelola');

        $data = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.*.tindakan' => ['required', 'in:naik,lulus'],
            'mapping.*.kelas_baru_id' => ['required_if:mapping.*.tindakan,naik', 'nullable', 'integer'],
            'mapping.*.salin_jadwal' => ['nullable', 'boolean'],
            'mapping.*.semester_tujuan_id' => ['nullable', 'integer'],
        ]);

        try {
            $result = $action->execute(new KenaikanKelasData(mapping: $data['mapping']));
        } catch (\DomainException $e) {
            return back()->withErrors(['mapping' => $e->getMessage()]);
        }

        $status = 'Kenaikan kelas berhasil diproses.';
        if (! empty($result['jadwalGagal'])) {
            $status .= ' ' . count($result['jadwalGagal']) . ' jadwal tidak tersalin karena bentrok: ' . implode('; ', $result['jadwalGagal']) . '.';
        }

        return redirect()->route('admin.kelas.index')->with('status', $status);
    }
}
```

- [ ] **Step 6: Jalankan SEMUA test Modul Kenaikan Kelas, verifikasi hijau**

Run: `php artisan test tests/Feature/Admin/KenaikanKelasControllerTest.php tests/Unit/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasActionTest.php`
Expected: semua PASS. **Test lama `KenaikanKelasControllerTest.php` yang menguji perilaku salin-jadwal-tanpa-validasi (kalau ada) BOLEH gagal dan HARUS diupdate assertion-nya** — ini satu-satunya modul di plan ini di mana update assertion test lama dibenarkan, karena perubahan perilakunya memang disengaja (lihat §4.3 spec). Test lain (yang tidak menyentuh salin-jadwal) HARUS tetap hijau tanpa modifikasi.

- [ ] **Step 7: Commit**

```bash
git add app/Domains/Akademik/DataTransferObjects/KenaikanKelasData.php app/Domains/Akademik/Actions/KenaikanKelas/ app/Http/Controllers/Admin/KenaikanKelasController.php tests/Unit/Domains/Akademik/Actions/KenaikanKelas/ tests/Feature/Admin/KenaikanKelasControllerTest.php
git commit -m "refactor(akademik): ekstrak ProsesKenaikanKelasAction, salinJadwal kini pakai CreateJadwalPelajaranAction dengan skip-dan-laporkan baris bentrok"
```

---

## Task 8: Verifikasi Akhir, Full Suite, Handoff Log

**Files:**
- Create: `.agents/logs/2026-08-20-akademik-05-migrasi-domain-tersisa.md`

- [ ] **Step 1: Verifikasi tidak ada referensi tersisa ke lokasi lama**

Run:
```bash
grep -rln "App\\\\Models\\\\KalenderAkademik" --include="*.php" app database tests
grep -rln "App\\\\Models\\\\PolaJam" --include="*.php" app database tests
grep -rln "App\\\\Models\\\\JamPelajaran" --include="*.php" app database tests
grep -rln "App\\\\Services\\\\KalenderAkademikResolver" --include="*.php" app database tests
```
Expected: keempat command menghasilkan output KOSONG (tidak ada baris). Kalau ada, itu file yang kelewat dari Task 1/2/4 — perbaiki sebelum lanjut.

- [ ] **Step 2: Jalankan seluruh test scoped ketiga modul sekaligus**

Run: `php artisan test tests/Unit/Models/KalenderAkademikTest.php tests/Feature/Admin/KalenderAkademikCrudTest.php tests/Feature/Admin/PengaturanAkademikControllerTest.php tests/Unit/Services/KalenderAkademikResolverTest.php tests/Unit/Models/PolaJamTest.php tests/Unit/Models/JamPelajaranTest.php tests/Feature/Admin/PolaJamCrudTest.php tests/Feature/Admin/JamPelajaranCrudTest.php tests/Feature/Admin/KenaikanKelasControllerTest.php tests/Unit/Domains/Akademik/Actions/Kalender tests/Unit/Domains/Akademik/Actions/PolaJam tests/Unit/Domains/Akademik/Actions/KenaikanKelas`
Expected: semua PASS.

- [ ] **Step 3: Minta persetujuan user untuk full suite**

Ikuti pola RBAC v2 & FASE 5.1: tanyakan dulu ke user ("3 modul Akademik selesai dimigrasi, semua test scoped hijau. Jalankan full suite sekarang?"), jangan asumsikan izin.

- [ ] **Step 4: Jalankan full suite setelah disetujui**

Run: `php artisan test`
Expected: semua PASS. Baseline SEBELUM plan ini (commit `b8b6242`): 1861 passed. Jumlah SESUDAH plan ini HARUS lebih besar dari 1861 (bertambah sejumlah test baru yang ditulis di Task 3, 5, 6, 7), dan 0 failed.

- [ ] **Step 5: Tulis handoff log ke `.agents/logs/2026-08-20-akademik-05-migrasi-domain-tersisa.md`**

Isi minimal: ringkasan 3 modul yang dimigrasi, daftar file baru (Action/DTO/Model/Service pindahan), daftar file yang diupdate importnya (rujuk ke Task 4 untuk daftar 36 file), hasil test per task, hasil full suite akhir, konfirmasi eksplisit bahwa Modul 1 & 2 zero-behavior-change (test lama tidak diubah) sementara Modul 3 punya 1 perubahan perilaku yang didokumentasikan (link ke §4.3 spec).

- [ ] **Step 6: Commit**

```bash
git add .agents/logs/2026-08-20-akademik-05-migrasi-domain-tersisa.md
git commit -m "docs(akademik): tutup migrasi 3 modul akademik tersisa ke Domains - handoff log"
```
