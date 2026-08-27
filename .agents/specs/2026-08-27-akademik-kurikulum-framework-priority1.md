# Kurikulum Framework (Priority 1) — Design Spec

**Tanggal**: 2026-08-27
**Branch**: `akademik-v2`
**Konteks roadmap**: `PETA_PENGEMBANGAN.md` §"🔵 Roadmap Kurikulum Dinamis", Prioritas #1 — fondasi wajib sebelum Prioritas #2 (`RaporCalculationService` type-aware), #3 (kelulusan PAUD), #4 (struktur KD vs CP+TP), #5 (Kemenag/KMA-450) bisa dikerjakan.

---

## 1. Latar Belakang & Masalah

Sistem sekarang tidak punya cara untuk mengetahui satu kelas memakai kurikulum apa (K13 atau Kurikulum Merdeka). Ini bukan cacat kosmetik — transisi Kurikulum Merdeka nasional (2022-2024, ditegaskan Permendikbudristek No. 12/2024 sebagai Kurikulum Nasional) berjalan **bertahap per tingkat, bukan serentak per sekolah**: kelas 1 & 4 di satu SD bisa sudah Merdeka sementara kelas 2, 3, 5, 6 di SD yang SAMA masih K13, pada tahun ajaran yang sama. K13 (`KI`→`KD` per mapel per semester) dan Kurikulum Merdeka (`CP` per Fase → `TP` turunan guru) juga berbeda STRUKTUR penilaian, bukan cuma istilah — `komponen_penilaian` sistem sekarang bergaya Merdeka ("TP"), salah struktur kalau dipakai kelas K13.

Tanpa entitas ini, seluruh keputusan turunan (istilah TP/KD, pemilihan template rapor, agregasi nilai) tidak punya pijakan data. Sprint ini HANYA membangun fondasi: entitas kurikulum, mekanisme assignment per konteks, dan snapshot ke `Kelas`. Konsumen turunan (form Komponen Penilaian, `RaporCalculationService`, dsb) eksplisit di luar scope — lihat §7 Non-Goals.

## 2. Model Domain

```text
KurikulumFramework (PHP backed enum, bukan tabel)
    = vocabulary jenis kurikulum yang didukung kode
    cases: K13 = 'k13', Merdeka = 'merdeka'

KurikulumAssignment (Model + tabel baru)
    = keputusan admin: kurikulum apa yang berlaku untuk
      (lembaga, tahun_ajaran, bentuk_pendidikan, tingkat)

Kelas.kurikulum (kolom baru, nullable, string, di-cast ke KurikulumFramework)
    = SNAPSHOT hasil resolusi assignment pada saat Kelas dibuat
```

**Keputusan desain kunci** (dari diskusi user, WAJIB diikuti persis):

1. `KurikulumFramework` TIDAK menjadi model/tabel — kurikulum baru (mis. Cambridge) selalu butuh kerja struktural kode juga (Prioritas #4), jadi tabel admin-editable tidak menambah fleksibilitas nyata. Enum di-cast langsung di model (`'kurikulum' => KurikulumFramework::class` via method `casts()`, bukan properti `$casts` — ikuti konvensi Laravel 12 project ini).
2. **Cakupan enum v1: HANYA `K13` dan `Merdeka`.** KTSP sudah defunct resmi sejak K13 penuh berlaku; Cambridge/internasional butuh struktur data yang sama sekali berbeda (di luar scope, sudah ditandai "Prioritas Rendah" di roadmap Fase 10). Menambah case baru nanti tidak perlu migrasi data karena backed enum.
3. `Kelas.kurikulum` **benar-benar terkunci setelah dibuat** — beda perlakuan dari `Kelas.fase_id` (yang ternyata cuma default-saran yang tetap bisa diubah manual via `UpdateKelasAction`, ditemukan saat eksplorasi kode). `UpdateKelasAction` TIDAK BOLEH menyentuh kolom ini sama sekali — tidak ada field-nya di `KelasData`, tidak ada di form edit Kelas.
4. `Kelas.kurikulum = null` HANYA berarti satu hal: **kelas legacy yang dibuat sebelum fitur ini ada** (nullable di level DB semata-mata untuk kompatibilitas mundur — sama seperti cara `fase_id` ditambahkan). Ini BUKAN kondisi valid untuk kelas baru — lihat §4.
5. Assignment DIRESOLVE OTOMATIS saat `CreateKelasAction` jalan — **tidak ada dropdown pilihan bebas** di form Kelas. Kalau tidak ada assignment yang cocok, pembuatan Kelas DITOLAK (422), bukan dibiarkan `kurikulum = null`.
6. Mengedit/menghapus `KurikulumAssignment` **tidak pernah mengubah** `Kelas.kurikulum` pada kelas yang sudah ada — assignment cuma dipakai sekali, pada saat resolusi.

## 3. Skema Data

### 3.1 Migration `create_kurikulum_assignment_table`

```php
Schema::create('kurikulum_assignment', function (Blueprint $table) {
    $table->id();
    $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->cascadeOnDelete();
    $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->cascadeOnDelete();
    $table->string('bentuk_pendidikan', 10);
    $table->string('tingkat', 10)->nullable();
    $table->string('kurikulum', 20); // backing value KurikulumFramework
    $table->unsignedBigInteger('lembaga_key')->virtualAs('COALESCE(lembaga_id, 0)');
    $table->string('tingkat_key', 10)->virtualAs("COALESCE(tingkat, '*')");
    $table->timestamps();

    $table->unique(
        ['lembaga_key', 'tahun_ajaran_id', 'bentuk_pendidikan', 'tingkat_key'],
        'kurikulum_assignment_scope_unique'
    );
});
```

Pola virtual-column ini identik dengan `fase_default_mapping` (`database/migrations/2026_08_27_090100_create_fase_default_mapping_table.php`) — trik yang sama supaya kombinasi `NULL` tetap dianggap unik oleh MySQL `UNIQUE` constraint. Beda kunci: `tahun_ajaran_id` WAJIB ada di sini (rollout kurikulum bertahap per tahun), sedangkan `fase_default_mapping` tidak punya sumbu tahun sama sekali (Fase relatif statis antar tahun).

`kurikulum` TIDAK diberi foreign key — dia backing value enum PHP, bukan referensi row.

### 3.2 Migration `add_kurikulum_to_kelas_table`

```php
Schema::table('kelas', function (Blueprint $table) {
    $table->string('kurikulum', 20)->nullable()->after('fase_id');
});
```

Nullable di DB (kelas lama = `null` = legacy, lihat §2 poin 4). Tanpa FK — backing value enum, sama seperti kolom `kurikulum` di `kurikulum_assignment`.

### 3.3 Model `KurikulumFramework` (enum)

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Enums;

enum KurikulumFramework: string
{
    case K13 = 'k13';
    case Merdeka = 'merdeka';

    public function label(): string
    {
        return match ($this) {
            self::K13 => 'Kurikulum 2013 (K13)',
            self::Merdeka => 'Kurikulum Merdeka',
        };
    }
}
```

### 3.4 Model `KurikulumAssignment`

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Models;

use App\Domains\Akademik\Enums\KurikulumFramework;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KurikulumAssignment extends Model
{
    protected $table = 'kurikulum_assignment';

    protected $fillable = [
        'lembaga_id',
        'tahun_ajaran_id',
        'bentuk_pendidikan',
        'tingkat',
        'kurikulum',
    ];

    protected function casts(): array
    {
        return [
            'kurikulum' => KurikulumFramework::class,
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }
}
```

### 3.5 `BentukPendidikan` enum (scoped ke fitur ini SAJA)

Temuan audit: project **tidak punya** sumber tunggal untuk `bentuk_pendidikan` — nilainya di-hardcode terpisah di 4 tempat berbeda dengan daftar yang bahkan tidak selalu identik (`database/migrations/2026_07_12_090702_create_lembaga_table.php:21`, `StoreFaseDefaultMappingRequest.php:12`, `LembagaController.php:164`, `AcademicProfile.php`/`RaporPdfDataBuilder.php`). Keputusan user: buat enum baru, **HANYA dipakai di fitur `KurikulumAssignment` ini** — retrofit 4 lokasi lama TIDAK masuk scope plan ini (dicatat sbg technical debt terpisah, lihat §8).

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Enums;

enum BentukPendidikan: string
{
    case Kb = 'KB';
    case Tpa = 'TPA';
    case Sps = 'SPS';
    case Tk = 'TK';
    case Sd = 'SD';
    case Smp = 'SMP';
    case Sma = 'SMA';
    case Smk = 'SMK';
    case Slb = 'SLB';

    /** @return array<int, string> Tingkat valid untuk bentuk pendidikan ini; array kosong = tidak berjenjang (tingkat harus null). */
    public function validTingkatValues(): array
    {
        return match ($this) {
            self::Kb, self::Tpa, self::Sps, self::Tk => ['A', 'B'],
            self::Sd, self::Slb => ['1', '2', '3', '4', '5', '6'],
            self::Smp => ['7', '8', '9'],
            self::Sma, self::Smk => ['10', '11', '12'],
        };
    }
}
```

Nilai `validTingkatValues()` diturunkan langsung dari pola nyata di `database/seeders/FaseDefaultMappingSeeder.php` dan `database/seeders/KelasSeeder.php` (SD/SMP/SMA/SMK numerik, PAUD `A`/`B`). **Asumsi yang perlu dikonfirmasi user sebelum implementasi**: SLB memakai skema tingkat 1-6 yang sama dengan SD (belum ada data seeder SLB yang mengonfirmasi ini secara eksplisit) — kalau ternyata beda, cukup ubah 1 match-arm ini, tidak berdampak ke desain lain.

### 3.6 `KurikulumAssignmentResolver`

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\Enums\KurikulumFramework;
use App\Domains\Akademik\Exceptions\KurikulumAssignmentNotFoundException;
use App\Domains\Akademik\Models\KurikulumAssignment;

class KurikulumAssignmentResolver
{
    /**
     * Precedence (paling spesifik -> paling umum), dinyatakan sbg ORDER BY,
     * pola sama seperti FaseDefaultResolver::resolve().
     *
     * @throws KurikulumAssignmentNotFoundException kalau tidak ada assignment yang cocok sama sekali.
     */
    public function resolve(int $tahunAjaranId, string $bentukPendidikan, ?string $tingkat, ?int $lembagaId): KurikulumFramework
    {
        $query = KurikulumAssignment::where('tahun_ajaran_id', $tahunAjaranId)
            ->where('bentuk_pendidikan', $bentukPendidikan)
            ->where(function ($q) use ($lembagaId) {
                $q->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            })
            ->orderByRaw('lembaga_id IS NULL')
            ->orderByRaw('tingkat IS NULL');

        if ($tingkat !== null) {
            $query->orderByRaw('tingkat = ? DESC', [$tingkat]);
        }

        $match = $query->first();

        if ($match === null) {
            throw new KurikulumAssignmentNotFoundException(
                "Kurikulum belum diatur untuk tahun_ajaran_id={$tahunAjaranId}, bentuk_pendidikan={$bentukPendidikan}, tingkat=".($tingkat ?? 'null').'.'
            );
        }

        return $match->kurikulum;
    }
}
```

`tahun_ajaran_id` adalah filter EKSAK (bukan bagian precedence) — assignment tahun lain tidak pernah dipakai sebagai fallback, karena kurikulum yang berlaku secara sengaja bisa beda tiap tahun ajaran.

**4 level precedence yang harus dibuktikan lewat test** (paling spesifik menang):
1. `lembaga_id = X` DAN `tingkat = T` (match eksak)
2. `lembaga_id = X` DAN `tingkat = null` (default lembaga ini, semua tingkat)
3. `lembaga_id = null` DAN `tingkat = T` (default lintas-lembaga, tingkat ini)
4. `lembaga_id = null` DAN `tingkat = null` (default paling umum)

### 3.7 `KurikulumAssignmentNotFoundException`

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Exceptions;

use RuntimeException;

final class KurikulumAssignmentNotFoundException extends RuntimeException {}
```

## 4. Integrasi ke `Kelas`

`CreateKelasAction` (`app/Domains/Akademik/Actions/Kelas/CreateKelasAction.php`) diubah: inject `KurikulumAssignmentResolver` via constructor, resolve kurikulum SEBELUM `Kelas::create()`, pakai `$tahunAjaran->lembaga_id` (bukan `$lembagaIdOverride`, yang bisa `null` untuk kasus tertentu) sebagai `lembagaId` dan `Lembaga::find($tahunAjaran->lembaga_id)->bentuk_pendidikan` sebagai `bentukPendidikan`:

```php
final class CreateKelasAction
{
    public function __construct(
        private readonly KurikulumAssignmentResolver $kurikulumResolver,
    ) {}

    public function execute(KelasData $data, ?int $lembagaIdOverride = null): Kelas
    {
        $tahunAjaran = TahunAjaran::find($data->tahunAjaranId);
        abort_if($tahunAjaran === null, 404);

        // ...logic wali kelas & pola jam existing TIDAK BERUBAH...

        $lembaga = Lembaga::find($tahunAjaran->lembaga_id);
        abort_if($lembaga === null, 404);

        $kurikulum = $this->kurikulumResolver->resolve(
            tahunAjaranId: $tahunAjaran->id,
            bentukPendidikan: $lembaga->bentuk_pendidikan,
            tingkat: $data->tingkat,
            lembagaId: $tahunAjaran->lembaga_id,
        );

        return Kelas::create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => $data->nama,
            'tingkat' => $data->tingkat,
            'fase_id' => $data->faseId,
            'wali_kelas_guru_id' => $waliKelasGuruId,
            'pola_jam_id' => $polaJamId,
            'lembaga_id' => $lembagaIdOverride,
            'kurikulum' => $kurikulum,
        ]);
    }
}
```

`KurikulumAssignmentNotFoundException` TIDAK ditangkap di Action — dibiarkan menjalar ke `KelasController::store()`, yang menangkapnya dan mengubahnya jadi `ValidationException` (422):

```php
public function store(StoreKelasRequest $request, CreateKelasAction $action): RedirectResponse
{
    // ...existing scope resolution...

    try {
        $action->execute(KelasData::fromValidated($validated), $lembagaIdOverride);
    } catch (KurikulumAssignmentNotFoundException $e) {
        return back()->withErrors([
            'tingkat' => 'Kurikulum belum diatur untuk kombinasi jenjang dan tingkat ini. Atur dulu di menu Pengaturan Kurikulum.',
        ])->withInput();
    }

    return redirect()->route('admin.kelas.index')->with('status', 'Kelas berhasil dibuat.');
}
```

`UpdateKelasAction` **TIDAK DIUBAH SAMA SEKALI** — tidak menerima `kurikulum`, tidak menulis kolom ini. `KelasData` juga tidak bertambah field untuk `kurikulum` (karena bukan input form, murni hasil resolusi internal Action).

**Tampilan minimal**: kolom `kurikulum` ditambahkan sebagai badge read-only di halaman detail/index Kelas (label dari `KurikulumFramework::label()`, tampilkan "—" kalau `null`/legacy). Ini satu-satunya perubahan UI di luar halaman `KurikulumAssignment` sendiri — tidak ada behavior baru, murni informational.

## 5. UI & Controller `KurikulumAssignment`

Mengikuti pola `FaseDefaultMappingController` PERSIS (§ struktur file, otorisasi scope platform/yayasan vs lembaga, duplicate-check manual + unique constraint DB), dengan tambahan field `tahun_ajaran_id`:

- **Model/Migration**: §3.1, §3.4.
- **DTO**: `KurikulumAssignmentData` (readonly, field: `lembagaId`, `tahunAjaranId`, `bentukPendidikan`, `tingkat`, `kurikulum`).
- **Actions**: `CreateKurikulumAssignmentAction`, `UpdateKurikulumAssignmentAction` (`app/Domains/Akademik/Actions/KurikulumAssignment/`).
- **FormRequests**: `StoreKurikulumAssignmentRequest`, `UpdateKurikulumAssignmentRequest` (`app/Http/Requests/Akademik/`) — validasi `bentuk_pendidikan` via `Rule::enum(BentukPendidikan::class)`, `tingkat` via `Rule::in()` dinamis dari `BentukPendidikan::from($input['bentuk_pendidikan'])->validTingkatValues()` (custom rule/`withValidator` closure, karena bergantung pada field lain), `kurikulum` via `Rule::enum(KurikulumFramework::class)`, `tahun_ajaran_id` via `exists:tahun_ajaran,id`.
- **Controller**: `Admin\KurikulumAssignmentController` — `index/create/store/edit/update/destroy`, otorisasi scope sama seperti `FaseDefaultMappingController::authorizeMappingScope()`, duplicate-check manual sebelum create/update (selain unique constraint DB) dengan pesan sama gayanya ("Sudah ada assignment kurikulum untuk kombinasi tahun ajaran, jenjang, dan tingkat ini.").
- **Permission**: `kurikulum-assignment.view/create/edit/delete` (pola sama `fase-mapping.*`), didaftarkan di seeder permission yang sudah ada.
- **Menu/Views**: halaman baru "Pengaturan Kurikulum", sejajar menu "Pengaturan Fase Default" yang sudah ada di area Akademik.

**Acceptance criterion eksplisit**: mengedit atau menghapus `KurikulumAssignment` TIDAK PERNAH mengubah `Kelas.kurikulum` pada Kelas yang sudah ada — assignment cuma dibaca sekali saat `CreateKelasAction` jalan, tidak ada relasi/observer yang menyebar perubahan mundur.

## 6. Testing (acceptance criteria wajib)

1. **4 level precedence resolver** — test masing-masing kombinasi (lembaga+tingkat, lembaga+null, null+tingkat, null+null) memenangkan match yang benar ketika beberapa row berpotensi cocok sekaligus.
2. **Unique constraint mencegah duplikat** — insert dua row dengan `(lembaga_key, tahun_ajaran_id, bentuk_pendidikan, tingkat_key)` identik harus gagal di level DB (test langsung terhadap constraint, bukan cuma lewat Controller).
3. **Create Kelas gagal 422 kalau assignment tidak ditemukan** — test feature: `KurikulumAssignmentResolver` throw → response redirect `back()->withErrors()`, Kelas TIDAK tersimpan di DB.
4. **Snapshot tidak berubah ketika assignment diedit** — buat Kelas (snapshot `kurikulum=k13` misalnya), lalu ubah `KurikulumAssignment` terkait jadi `merdeka`, assert `Kelas::find($id)->kurikulum` tetap `k13`.
5. **Kelas legacy (`kurikulum=null`) tetap bisa dibaca/diakses** — test index/detail Kelas dengan `kurikulum=null` tidak error, badge tampil "—".
6. **`UpdateKelasAction` tidak menyentuh kolom `kurikulum`** — test: update Kelas manapun (nama/tingkat/dll), assert `kurikulum` sebelum & sesudah update identik.
7. **`BentukPendidikan::validTingkatValues()` dan validasi request** — test tiap case enum mengembalikan daftar sesuai §3.5, dan `StoreKurikulumAssignmentRequest` menolak kombinasi tidak valid (mis. `bentuk_pendidikan=SD`, `tingkat='13'`).

## 7. Non-Goals (eksplisit di luar scope Priority 1 ini)

- **Tidak menyentuh `RaporCalculationService`** (Prioritas #2 roadmap) — tetap numeric-only untuk sekarang.
- **Tidak membuat struktur form KD (K13) vs CP+TP (Merdeka)** (Prioritas #4) — `Kelas.kurikulum` baru tersedia sbg data, konsumennya belum dibangun.
- **Tidak menyentuh `isTingkatAkhir()`/kelulusan PAUD** (Prioritas #3).
- **Tidak membangun workflow Kemenag/KMA-450/P5-PPRA** (Prioritas #5).
- **Tidak retrofit 4 lokasi lama yang masih hardcode `bentuk_pendidikan`** (`StoreFaseDefaultMappingRequest`, `LembagaController`, `AcademicProfile`, `RaporPdfDataBuilder`) ke enum `BentukPendidikan` baru — dicatat sbg technical debt terpisah, lihat §8.
- **Tidak mengubah `FaseDefaultMapping`/`FaseDefaultResolver`** — keduanya tetap seperti sekarang, tidak digabung dengan `KurikulumAssignment` walau strukturnya mirip (sumbu kuncinya beda: `KurikulumAssignment` wajib scoped per tahun ajaran, `FaseDefaultMapping` tidak).

## 8. Technical Debt Baru yang Dicatat (bukan bagian plan ini)

Setelah spec ini disetujui dan Prioritas #1 selesai, catat di `PETA_PENGEMBANGAN.md`:

> **TD-AKADEMIK-003 (kandidat)**: `bentuk_pendidikan` masih di-hardcode terpisah di 4 lokasi (`StoreFaseDefaultMappingRequest.php:12`, `LembagaController.php:164`, `AcademicProfile.php`, `RaporPdfDataBuilder.php`) dengan daftar yang tidak selalu identik satu sama lain. Enum `BentukPendidikan` yang baru dibuat untuk `KurikulumAssignment` (Prioritas #1 roadmap kurikulum) bisa jadi sumber tunggal kalau 4 lokasi ini di-retrofit — effort Kecil-Sedang, tidak urgent (semua lokasi lama sudah jalan & full-tested).

## 9. Ringkasan Alur Akhir

```text
                ┌──────────────────────┐
                │ KurikulumFramework   │  (enum, vocabulary)
                │ K13 | Merdeka        │
                └──────────┬───────────┘
                           │
                           ▼
                ┌──────────────────────┐
                │ KurikulumAssignment  │  (tabel, keputusan admin)
                │ lembaga (nullable)   │
                │ tahun_ajaran (wajib) │
                │ bentuk_pendidikan    │
                │ tingkat (nullable)   │
                │ kurikulum            │
                └──────────┬───────────┘
                           │
                  KurikulumAssignmentResolver::resolve()
                  (4-level precedence, THROW kalau tidak ketemu)
                           │
                           ▼
                 ┌──────────────────┐
                 │ CreateKelasAction│
                 └────────┬─────────┘
                          │ snapshot (write-once)
                          ▼
                 ┌──────────────────┐
                 │ Kelas.kurikulum  │  immutable, tidak disentuh UpdateKelasAction
                 │ null = legacy    │
                 └──────────────────┘
```

Setelah ini, Prioritas #4 bisa aman menulis: `Kelas.kurikulum === KurikulumFramework::K13 → workflow KD/KI`, `=== KurikulumFramework::Merdeka → workflow CP/Fase/TP`.
