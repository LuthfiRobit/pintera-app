# Sub-Task 03a — Migrasi Jurnal KBM & Presensi ke Pola Domain Baru — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pindahkan modul Jurnal KBM & Presensi (mode Sesi Mapel) dari struktur lama ke `app/Domains/Akademik/`, ekstrak Action/DTO/FormRequest dari controller, dan tambahkan fitur rekap kehadiran semesteran untuk Wali Kelas — tanpa mengubah perilaku bisnis alur yang sudah ada.

**Architecture:** Domain-oriented (Thin Controller → FormRequest → DTO → Action → Eloquent), mengikuti `.agents/skills/laravel-feature-standard/SKILL.md`. Isolasi tenant (`BelongsToTenant`, pengecekan kepemilikan per-guru) yang sudah benar di kode lama **tidak diubah sama sekali** — hanya dipindah lokasi filenya.

**Tech Stack:** Laravel, Pest (test), Eloquent, Spatie Permission.

## Global Constraints

- Tidak ada perubahan perilaku bisnis pada alur existing (spec §2, "Out of Scope").
- Route baru: flat `guru.jurnal-kbm.*` (bukan nested `guru.akademik.*`) — keputusan disepakati, revisit di FASE 5.1 master plan bila perlu.
- Semua mutasi multi-baris wajib dibungkus `DB::transaction()`.
- Setiap task diakhiri dengan `php artisan test` yang confirmed hijau sebelum lanjut ke task berikutnya — jangan menumpuk perubahan tanpa verifikasi.
- Baseline saat ini: `php artisan test` penuh = 1742 passed, 0 failed. Ini harus tetap 0 failed di setiap checkpoint.

---

## Task 1: Relokasi Enum, Model, dan Service ke `app/Domains/Akademik/`

**Files:**
- Create: `app/Domains/Akademik/Enums/StatusSesiPembelajaran.php`
- Create: `app/Domains/Akademik/Enums/StatusPresensi.php`
- Create: `app/Domains/Akademik/Models/SesiPembelajaran.php`
- Create: `app/Domains/Akademik/Models/Presensi.php`
- Create: `app/Domains/Akademik/Services/SesiPembelajaranGenerator.php`
- Delete: `app/Enums/StatusSesiPembelajaran.php`, `app/Enums/StatusPresensi.php`, `app/Models/SesiPembelajaran.php`, `app/Models/Presensi.php`, `app/Services/SesiPembelajaranGenerator.php`
- Modify: `database/factories/SesiPembelajaranFactory.php`
- Modify: `database/factories/PresensiFactory.php`
- Modify: `database/seeders/PresensiSeeder.php`
- Modify: `database/seeders/SesiPembelajaranSeeder.php`
- Modify: `tests/Unit/Enums/PresensiEnumsTest.php`
- Modify: `tests/Unit/Models/SesiPembelajaranTest.php`
- Modify: `tests/Unit/Models/PresensiTest.php`
- Modify: `tests/Unit/Services/SesiPembelajaranGeneratorTest.php`
- Modify: `tests/Unit/PresensiSeederTest.php`
- Modify: `tests/Unit/SesiPembelajaranSeederTest.php`
- Modify: `tests/Feature/AkademikTenantScopeTest.php`

**Interfaces:**
- Produces: `App\Domains\Akademik\Enums\StatusSesiPembelajaran`, `App\Domains\Akademik\Enums\StatusPresensi`, `App\Domains\Akademik\Models\SesiPembelajaran`, `App\Domains\Akademik\Models\Presensi`, `App\Domains\Akademik\Services\SesiPembelajaranGenerator` — semua task berikutnya import dari path ini.

- [ ] **Step 1: Buat enum baru di lokasi domain**

Buat `app/Domains/Akademik/Enums/StatusSesiPembelajaran.php`:
```php
<?php

namespace App\Domains\Akademik\Enums;

enum StatusSesiPembelajaran: string
{
    case Terlaksana = 'terlaksana';
    case Diganti = 'diganti';
    case Kosong = 'kosong';

    public function label(): string
    {
        return match ($this) {
            self::Terlaksana => 'Terlaksana',
            self::Diganti => 'Diganti',
            self::Kosong => 'Kosong',
        };
    }
}
```

Buat `app/Domains/Akademik/Enums/StatusPresensi.php`:
```php
<?php

namespace App\Domains\Akademik\Enums;

enum StatusPresensi: string
{
    case Hadir = 'hadir';
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Alpa = 'alpa';
    case Terlambat = 'terlambat';

    public function label(): string
    {
        return match ($this) {
            self::Hadir => 'Hadir',
            self::Izin => 'Izin',
            self::Sakit => 'Sakit',
            self::Alpa => 'Alpa',
            self::Terlambat => 'Terlambat',
        };
    }
}
```

Hapus file lama:
```bash
git rm app/Enums/StatusSesiPembelajaran.php app/Enums/StatusPresensi.php
```

- [ ] **Step 2: Buat model baru di lokasi domain (dengan `newFactory()` eksplisit)**

Buat `app/Domains/Akademik/Models/SesiPembelajaran.php`:
```php
<?php

namespace App\Domains\Akademik\Models;

use App\Domains\Akademik\Enums\StatusSesiPembelajaran;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use Database\Factories\SesiPembelajaranFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SesiPembelajaran extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'sesi_pembelajaran';

    protected $fillable = [
        'jadwal_pelajaran_id', 'kelas_id', 'guru_id', 'mata_pelajaran_id', 'lembaga_id',
        'tanggal', 'jam_mulai', 'jam_selesai', 'materi', 'status',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'status' => StatusSesiPembelajaran::class,
        ];
    }

    protected static function newFactory(): SesiPembelajaranFactory
    {
        return SesiPembelajaranFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (self $sesiPembelajaran) {
            if (empty($sesiPembelajaran->lembaga_id)) {
                $sesiPembelajaran->lembaga_id = Kelas::withoutGlobalScopes()
                    ->findOrFail($sesiPembelajaran->kelas_id)
                    ->lembaga_id;
            }
        });
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function jadwalPelajaran(): BelongsTo
    {
        return $this->belongsTo(JadwalPelajaran::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function presensi(): HasMany
    {
        return $this->hasMany(Presensi::class);
    }
}
```

Buat `app/Domains/Akademik/Models/Presensi.php`:
```php
<?php

namespace App\Domains\Akademik\Models;

use App\Domains\Akademik\Enums\StatusPresensi;
use App\Models\Siswa;
use Database\Factories\PresensiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presensi extends Model
{
    use HasFactory;

    protected $table = 'presensi';

    protected $fillable = ['sesi_pembelajaran_id', 'siswa_id', 'status', 'keterangan'];

    protected function casts(): array
    {
        return [
            'status' => StatusPresensi::class,
        ];
    }

    protected static function newFactory(): PresensiFactory
    {
        return PresensiFactory::new();
    }

    public function sesiPembelajaran(): BelongsTo
    {
        return $this->belongsTo(SesiPembelajaran::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }
}
```

Hapus file lama:
```bash
git rm app/Models/SesiPembelajaran.php app/Models/Presensi.php
```

- [ ] **Step 3: Buat service baru di lokasi domain**

Buat `app/Domains/Akademik/Services/SesiPembelajaranGenerator.php` — isi identik dengan `app/Services/SesiPembelajaranGenerator.php` lama, hanya `namespace` dan `use` import model yang berubah:
```php
<?php

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Enums\Hari;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class SesiPembelajaranGenerator
{
    /**
     * @return Collection<int, SesiPembelajaran>
     */
    public function generateUntukTanggal(Kelas $kelas, CarbonInterface $tanggal, int $semesterId): Collection
    {
        $resolusi = (new \App\Services\KalenderAkademikResolver)->resolve($kelas->lembaga, $tanggal);

        if ($resolusi['libur'] || $kelas->pola_jam_id === null) {
            return collect();
        }

        $hari = Hari::fromCarbonDayOfWeek($tanggal->dayOfWeek);

        $jadwalHariIni = JadwalPelajaran::where('kelas_id', $kelas->id)
            ->where('semester_id', $semesterId)
            ->whereHas('jamPelajaran', fn ($q) => $q->where('pola_jam_id', $kelas->pola_jam_id)->where('hari', $hari->value))
            ->with('jamPelajaran')
            ->get()
            ->sortBy(fn (JadwalPelajaran $jadwal) => $jadwal->jamPelajaran->urutan)
            ->values();

        return $this->kelompokkanJadiBlok($jadwalHariIni)
            ->map(fn (Collection $blok) => $this->buatSesi($kelas, $blok, $tanggal));
    }

    /**
     * Groups same-day JadwalPelajaran rows (already sorted by jam_pelajaran.urutan) into
     * blocks of consecutive slots sharing the same mata_pelajaran_id and guru_id — a
     * "double period" taught by the same guru is one teaching session, not two.
     *
     * @param  Collection<int, JadwalPelajaran>  $jadwalHariIni
     * @return Collection<int, Collection<int, JadwalPelajaran>>
     */
    private function kelompokkanJadiBlok(Collection $jadwalHariIni): Collection
    {
        $semuaBlok = collect();
        $blokSaatIni = collect();

        foreach ($jadwalHariIni as $jadwal) {
            if ($blokSaatIni->isNotEmpty()) {
                $terakhir = $blokSaatIni->last();
                $berurutan = $jadwal->jamPelajaran->urutan === $terakhir->jamPelajaran->urutan + 1;
                $samaMapelDanGuru = $jadwal->mata_pelajaran_id === $terakhir->mata_pelajaran_id
                    && $jadwal->guru_id === $terakhir->guru_id;

                if (! ($berurutan && $samaMapelDanGuru)) {
                    $semuaBlok->push($blokSaatIni);
                    $blokSaatIni = collect();
                }
            }

            $blokSaatIni->push($jadwal);
        }

        if ($blokSaatIni->isNotEmpty()) {
            $semuaBlok->push($blokSaatIni);
        }

        return $semuaBlok;
    }

    /**
     * @param  Collection<int, JadwalPelajaran>  $blok  one or more consecutive same-mapel/guru slots
     */
    private function buatSesi(Kelas $kelas, Collection $blok, CarbonInterface $tanggal): SesiPembelajaran
    {
        $jadwalPertama = $blok->first();
        $jadwalTerakhir = $blok->last();

        $sesi = SesiPembelajaran::firstOrCreate(
            [
                'jadwal_pelajaran_id' => $jadwalPertama->id,
                'tanggal' => $tanggal->toDateString(),
            ],
            [
                'kelas_id' => $kelas->id,
                'guru_id' => $jadwalPertama->guru_id,
                'mata_pelajaran_id' => $jadwalPertama->mata_pelajaran_id,
                'jam_mulai' => $jadwalPertama->jamPelajaran->jam_mulai,
                'jam_selesai' => $jadwalTerakhir->jamPelajaran->jam_selesai,
                'status' => 'terlaksana',
            ]
        );

        if ($sesi->wasRecentlyCreated) {
            foreach ($kelas->siswa()->where('status', 'aktif')->get() as $siswa) {
                Presensi::firstOrCreate(
                    ['sesi_pembelajaran_id' => $sesi->id, 'siswa_id' => $siswa->id],
                    ['status' => 'hadir']
                );
            }
        }

        return $sesi;
    }
}
```

Hapus file lama:
```bash
git rm app/Services/SesiPembelajaranGenerator.php
```

- [ ] **Step 4: Update `use` import di factory**

Di `database/factories/SesiPembelajaranFactory.php`, ganti baris:
```php
use App\Models\SesiPembelajaran;
```
menjadi:
```php
use App\Domains\Akademik\Models\SesiPembelajaran;
```

Di `database/factories/PresensiFactory.php`, ganti:
```php
use App\Models\Presensi;
use App\Models\SesiPembelajaran;
```
menjadi:
```php
use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Models\SesiPembelajaran;
```

- [ ] **Step 5: Update `use` import di seeder**

Di `database/seeders/PresensiSeeder.php`, ganti:
```php
use App\Models\Presensi;
use App\Models\SesiPembelajaran;
```
menjadi:
```php
use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Models\SesiPembelajaran;
```

Di `database/seeders/SesiPembelajaranSeeder.php`, ganti:
```php
use App\Models\SesiPembelajaran;
```
menjadi:
```php
use App\Domains\Akademik\Models\SesiPembelajaran;
```

- [ ] **Step 6: Update `use` import di 7 file test**

Di `tests/Unit/Enums/PresensiEnumsTest.php`, ganti:
```php
use App\Enums\StatusPresensi;
use App\Enums\StatusSesiPembelajaran;
```
menjadi:
```php
use App\Domains\Akademik\Enums\StatusPresensi;
use App\Domains\Akademik\Enums\StatusSesiPembelajaran;
```

Di `tests/Unit/Models/SesiPembelajaranTest.php`, ganti:
```php
use App\Enums\StatusSesiPembelajaran;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\SesiPembelajaran;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
```
menjadi:
```php
use App\Domains\Akademik\Enums\StatusSesiPembelajaran;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
```

Di `tests/Unit/Models/PresensiTest.php`, ganti:
```php
use App\Enums\StatusPresensi;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Presensi;
use App\Models\SesiPembelajaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
```
menjadi:
```php
use App\Domains\Akademik\Enums\StatusPresensi;
use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
```

Di `tests/Unit/Services/SesiPembelajaranGeneratorTest.php`, ganti:
```php
use App\Models\SesiPembelajaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use App\Services\SesiPembelajaranGenerator;
```
menjadi:
```php
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Domains\Akademik\Services\SesiPembelajaranGenerator;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
```

Di `tests/Unit/PresensiSeederTest.php`, ganti:
```php
use App\Models\Presensi;
use App\Models\SesiPembelajaran;
```
menjadi:
```php
use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Models\SesiPembelajaran;
```

Di `tests/Unit/SesiPembelajaranSeederTest.php`, ganti:
```php
use App\Models\SesiPembelajaran;
```
menjadi:
```php
use App\Domains\Akademik\Models\SesiPembelajaran;
```

Di `tests/Feature/AkademikTenantScopeTest.php`, ganti:
```php
use App\Models\SesiPembelajaran;
```
menjadi:
```php
use App\Domains\Akademik\Models\SesiPembelajaran;
```
(baris lain di file ini — `Asesmen`, `KomponenPenilaian`, `NilaiSiswa`, dll — TIDAK disentuh, di luar scope sub-task ini.)

- [ ] **Step 7: Jalankan full test suite, pastikan tetap hijau**

```bash
php artisan test
```
Expected: `1742 passed, 0 failed` (sama seperti baseline — task ini murni pemindahan lokasi file, tidak ada perubahan perilaku).

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "refactor(akademik): pindahkan enum, model, dan service presensi ke app/Domains/Akademik"
```

---

## Task 2: DTO, FormRequest, dan Action Baru

**Files:**
- Create: `app/Domains/Akademik/DataTransferObjects/JurnalPresensiData.php`
- Create: `app/Http/Requests/Akademik/UpdateJurnalPresensiRequest.php`
- Create: `app/Domains/Akademik/Actions/Presensi/GenerateSesiHarianAction.php`
- Create: `app/Domains/Akademik/Actions/Presensi/RecordJurnalDanPresensiAction.php`

**Interfaces:**
- Consumes: `App\Domains\Akademik\Models\SesiPembelajaran`, `App\Domains\Akademik\Services\SesiPembelajaranGenerator` (dari Task 1).
- Produces: `JurnalPresensiData::fromArray(array $data): self`; `UpdateJurnalPresensiRequest::toDTO(): JurnalPresensiData`; `GenerateSesiHarianAction::execute(Guru $guru, CarbonInterface $tanggal): void`; `RecordJurnalDanPresensiAction::execute(SesiPembelajaran $sesi, JurnalPresensiData $data): SesiPembelajaran` — dipakai Task 3 (Controller).

- [ ] **Step 1: Buat DTO `JurnalPresensiData`**

```php
<?php

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class JurnalPresensiData
{
    /**
     * @param  array<int, string>  $presensi  siswa_id (key) => status value (mis. 'hadir', 'izin')
     */
    public function __construct(
        public ?string $materi,
        public array $presensi,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            materi: $data['materi'] ?? null,
            presensi: $data['presensi'] ?? [],
        );
    }
}
```

- [ ] **Step 2: Buat FormRequest `UpdateJurnalPresensiRequest`**

```php
<?php

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\DataTransferObjects\JurnalPresensiData;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateJurnalPresensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'materi' => ['nullable', 'string'],
            'presensi' => ['required', 'array'],
            'presensi.*' => ['required', 'in:hadir,izin,sakit,alpa,terlambat'],
        ];
    }

    public function toDTO(): JurnalPresensiData
    {
        return JurnalPresensiData::fromArray($this->validated());
    }
}
```

> Catatan: `authorize()` sengaja `true` — pengecekan kepemilikan sesi terhadap guru yang login (`authorizeMilikGuru`) tetap di controller, persis pola lama, karena butuh akses ke route-bound `SesiPembelajaran $sesi` yang FormRequest tidak resolve lebih dulu di titik ini.

- [ ] **Step 3: Buat `GenerateSesiHarianAction`**

```php
<?php

namespace App\Domains\Akademik\Actions\Presensi;

use App\Domains\Akademik\Services\SesiPembelajaranGenerator;
use App\Models\Guru;
use App\Models\Kelas;
use Carbon\CarbonInterface;

final class GenerateSesiHarianAction
{
    public function __construct(
        private readonly SesiPembelajaranGenerator $generator,
    ) {
    }

    public function execute(Guru $guru, CarbonInterface $tanggal): void
    {
        $kelasList = Kelas::where(function ($query) use ($guru) {
            $query->whereHas('jadwalPelajaran', fn ($q) => $q->where('guru_id', $guru->id))
                ->orWhere('wali_kelas_guru_id', $guru->id);
        })->get();

        foreach ($kelasList as $kelas) {
            $semesterId = optional($kelas->tahunAjaran->semester()->where('status_aktif', true)->first())->id;
            if ($semesterId) {
                $this->generator->generateUntukTanggal($kelas, $tanggal, $semesterId);
            }
        }
    }
}
```

- [ ] **Step 4: Buat `RecordJurnalDanPresensiAction`**

```php
<?php

namespace App\Domains\Akademik\Actions\Presensi;

use App\Domains\Akademik\DataTransferObjects\JurnalPresensiData;
use App\Domains\Akademik\Models\SesiPembelajaran;
use Illuminate\Support\Facades\DB;

final class RecordJurnalDanPresensiAction
{
    public function execute(SesiPembelajaran $sesi, JurnalPresensiData $data): SesiPembelajaran
    {
        return DB::transaction(function () use ($sesi, $data) {
            $sesi->update(['materi' => $data->materi]);

            foreach ($data->presensi as $siswaId => $status) {
                $sesi->presensi()->where('siswa_id', $siswaId)->update(['status' => $status]);
            }

            return $sesi->fresh();
        });
    }
}
```

- [ ] **Step 5: Jalankan full test suite (belum ada test baru untuk file-file ini secara langsung — dipakai & diuji lewat Task 3)**

```bash
php artisan test
```
Expected: `1742 passed, 0 failed` (file baru belum dipakai di mana pun, tidak mempengaruhi test lain).

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Akademik/DataTransferObjects/JurnalPresensiData.php \
  app/Http/Requests/Akademik/UpdateJurnalPresensiRequest.php \
  app/Domains/Akademik/Actions/Presensi/GenerateSesiHarianAction.php \
  app/Domains/Akademik/Actions/Presensi/RecordJurnalDanPresensiAction.php
git commit -m "feat(akademik): tambah DTO, FormRequest, dan Action untuk jurnal & presensi"
```

---

## Task 3: Relokasi & Refactor Controller, Route, View, Sidebar

**Files:**
- Create: `app/Http/Controllers/Guru/Akademik/JurnalKbmController.php`
- Delete: `app/Http/Controllers/Guru/SesiPembelajaranController.php`
- Modify: `routes/admin.php`
- Modify: `resources/views/layouts/sidebar.blade.php`
- Move: `resources/views/guru/sesi-pembelajaran/index.blade.php` → `resources/views/portals/guru/akademik/jurnal-kbm/index.blade.php`
- Move: `resources/views/guru/sesi-pembelajaran/show.blade.php` → `resources/views/portals/guru/akademik/jurnal-kbm/show.blade.php`
- Rename & Modify: `tests/Feature/Guru/SesiPembelajaranControllerTest.php` → `tests/Feature/Guru/JurnalKbmControllerTest.php`
- Rename & Modify: `tests/Feature/Guru/SesiPembelajaranTenantScopeTest.php` → `tests/Feature/Guru/JurnalKbmTenantScopeTest.php`

**Interfaces:**
- Consumes: `GenerateSesiHarianAction`, `RecordJurnalDanPresensiAction`, `UpdateJurnalPresensiRequest` (dari Task 2); `SesiPembelajaran` (dari Task 1).
- Produces: route names `guru.jurnal-kbm.index`, `guru.jurnal-kbm.show`, `guru.jurnal-kbm.update` — dipakai Task 5 (`RekapKehadiranController` redirect balik ke index yang sama family).

- [ ] **Step 1: Buat controller baru (thin) di lokasi domain**

Buat `app/Http/Controllers/Guru/Akademik/JurnalKbmController.php`:
```php
<?php

namespace App\Http\Controllers\Guru\Akademik;

use App\Domains\Akademik\Actions\Presensi\GenerateSesiHarianAction;
use App\Domains\Akademik\Actions\Presensi\RecordJurnalDanPresensiAction;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Http\Requests\Akademik\UpdateJurnalPresensiRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class JurnalKbmController extends BaseController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly GenerateSesiHarianAction $generateSesiHarianAction,
        private readonly RecordJurnalDanPresensiAction $recordJurnalDanPresensiAction,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('presensi.isi');

        $guru = $request->user()->guru;
        $hariIni = now();

        if ($guru) {
            $this->generateSesiHarianAction->execute($guru, $hariIni);
        }

        return view('portals.guru.akademik.jurnal-kbm.index', [
            'sesiList' => $guru
                ? SesiPembelajaran::where('guru_id', $guru->id)->whereDate('tanggal', $hariIni)->with('kelas', 'mataPelajaran')->get()
                : collect(),
        ]);
    }

    public function show(SesiPembelajaran $sesi): View
    {
        $this->authorize('presensi.isi');
        $this->authorizeMilikGuru($sesi);

        return view('portals.guru.akademik.jurnal-kbm.show', [
            'sesi' => $sesi,
            'presensiList' => $sesi->presensi()->with('siswa')->get(),
        ]);
    }

    public function update(UpdateJurnalPresensiRequest $request, SesiPembelajaran $sesi): RedirectResponse
    {
        $this->authorize('presensi.isi');
        $this->authorizeMilikGuru($sesi);

        $this->recordJurnalDanPresensiAction->execute($sesi, $request->toDTO());

        return redirect()->route('guru.jurnal-kbm.index')->with('status', 'Jurnal dan presensi berhasil disimpan.');
    }

    private function authorizeMilikGuru(SesiPembelajaran $sesi): void
    {
        $guru = auth()->user()->guru;

        abort_if($guru === null || $sesi->guru_id !== $guru->id, 403);
    }
}
```

Hapus controller lama:
```bash
git rm app/Http/Controllers/Guru/SesiPembelajaranController.php
```

- [ ] **Step 2: Pindahkan Blade view ke lokasi portal standar**

```bash
mkdir -p resources/views/portals/guru/akademik/jurnal-kbm
git mv resources/views/guru/sesi-pembelajaran/index.blade.php resources/views/portals/guru/akademik/jurnal-kbm/index.blade.php
git mv resources/views/guru/sesi-pembelajaran/show.blade.php resources/views/portals/guru/akademik/jurnal-kbm/show.blade.php
```

Buka kedua file yang baru dipindah, cari referensi `route('guru.sesi.` di dalamnya (link, form action, dsb.) dan ganti jadi `route('guru.jurnal-kbm.` — nama parameter route tetap sama (`index`, `show`, `update`), hanya prefix `sesi` → `jurnal-kbm`.

- [ ] **Step 3: Update route di `routes/admin.php`**

Cari baris:
```php
use App\Http\Controllers\Guru\SesiPembelajaranController;
```
ganti jadi:
```php
use App\Http\Controllers\Guru\Akademik\JurnalKbmController;
```

Cari blok:
```php
    Route::get('sesi', [SesiPembelajaranController::class, 'index'])->name('sesi.index');
    Route::get('sesi/{sesi}', [SesiPembelajaranController::class, 'show'])->name('sesi.show');
    Route::put('sesi/{sesi}', [SesiPembelajaranController::class, 'update'])->name('sesi.update');
```
ganti jadi:
```php
    Route::get('jurnal-kbm', [JurnalKbmController::class, 'index'])->name('jurnal-kbm.index');
    Route::get('jurnal-kbm/{sesi}', [JurnalKbmController::class, 'show'])->name('jurnal-kbm.show');
    Route::put('jurnal-kbm/{sesi}', [JurnalKbmController::class, 'update'])->name('jurnal-kbm.update');
```

- [ ] **Step 4: Update link menu di sidebar**

Di `resources/views/layouts/sidebar.blade.php` baris 14, ganti:
```php
Auth::user()->can('presensi.isi') ? ['route' => 'guru.sesi.index', 'pattern' => 'guru.sesi.*', 'label' => 'Jurnal & Presensi', 'icon' => 'file-pen'] : null,
```
menjadi:
```php
Auth::user()->can('presensi.isi') ? ['route' => 'guru.jurnal-kbm.index', 'pattern' => 'guru.jurnal-kbm.*', 'label' => 'Jurnal & Presensi', 'icon' => 'file-pen'] : null,
```

- [ ] **Step 5: Rename & update 2 file test controller**

```bash
git mv tests/Feature/Guru/SesiPembelajaranControllerTest.php tests/Feature/Guru/JurnalKbmControllerTest.php
git mv tests/Feature/Guru/SesiPembelajaranTenantScopeTest.php tests/Feature/Guru/JurnalKbmTenantScopeTest.php
```

Di `tests/Feature/Guru/JurnalKbmControllerTest.php`:
- Ganti `use App\Models\SesiPembelajaran;` → `use App\Domains\Akademik\Models\SesiPembelajaran;`
- Ganti semua `route('guru.sesi.index')` → `route('guru.jurnal-kbm.index')`
- Ganti semua `route('guru.sesi.update', $sesi)` → `route('guru.jurnal-kbm.update', $sesi)`

Di `tests/Feature/Guru/JurnalKbmTenantScopeTest.php`:
- Ganti semua `route('guru.sesi.index')` → `route('guru.jurnal-kbm.index')`
- (File ini tidak import `SesiPembelajaran` langsung — tidak perlu ubah `use` statement.)

- [ ] **Step 6: Jalankan full test suite, pastikan tetap hijau**

```bash
php artisan test
```
Expected: `1742 passed, 0 failed`.

- [ ] **Step 7: Compile asset frontend (Blade view dipindah, tidak ada JS baru — tapi pastikan build tidak rusak)**

```bash
npm run build
```
Expected: build sukses tanpa error.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "refactor(akademik): pindahkan controller jurnal-presensi ke Guru/Akademik, rename route ke guru.jurnal-kbm.*"
```

---

## Task 4: Bangun `PresensiAggregationService` (TDD)

**Files:**
- Create: `app/Domains/Akademik/Services/PresensiAggregationService.php`
- Test: `tests/Unit/Services/PresensiAggregationServiceTest.php`

**Interfaces:**
- Consumes: `App\Domains\Akademik\Models\Presensi`, `App\Models\Siswa`, `App\Models\Semester` (existing).
- Produces: `PresensiAggregationService::agregasiPerKelas(int $kelasId, \App\Models\Semester $semester): \Illuminate\Support\Collection` — array asosiatif per siswa berisi `siswa_id`, `nama`, `hadir`, `izin`, `sakit`, `alpa`, `terlambat` (int). Dipakai Task 5 (`RekapKehadiranController`).

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Unit/Services/PresensiAggregationServiceTest.php`:
```php
<?php

use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Domains\Akademik\Services\PresensiAggregationService;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function siapkanKelasUntukRekap(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create([
        'tahun_ajaran_id' => $tahunAjaran->id,
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2026-12-31',
    ]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    return compact('kelas', 'semester');
}

it('menghitung total hadir, izin, sakit, alpa, dan terlambat per siswa dalam rentang semester', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasUntukRekap();
    $siswa = Siswa::factory()->create(['lembaga_id' => $kelas->lembaga_id, 'kelas_id' => $kelas->id, 'status' => 'aktif']);

    $sesi1 = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => '2026-08-10']);
    $sesi2 = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => '2026-08-11']);
    $sesi3 = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => '2026-08-12']);

    Presensi::create(['sesi_pembelajaran_id' => $sesi1->id, 'siswa_id' => $siswa->id, 'status' => 'hadir']);
    Presensi::create(['sesi_pembelajaran_id' => $sesi2->id, 'siswa_id' => $siswa->id, 'status' => 'izin']);
    Presensi::create(['sesi_pembelajaran_id' => $sesi3->id, 'siswa_id' => $siswa->id, 'status' => 'alpa']);

    $rekap = (new PresensiAggregationService())->agregasiPerKelas($kelas->id, $semester);

    $baris = $rekap->firstWhere('siswa_id', $siswa->id);
    expect($baris)->not->toBeNull()
        ->and($baris['nama'])->toBe($siswa->nama)
        ->and($baris['hadir'])->toBe(1)
        ->and($baris['izin'])->toBe(1)
        ->and($baris['alpa'])->toBe(1)
        ->and($baris['sakit'])->toBe(0)
        ->and($baris['terlambat'])->toBe(0);
});

it('mengecualikan presensi dari sesi di luar rentang tanggal semester', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasUntukRekap();
    $siswa = Siswa::factory()->create(['lembaga_id' => $kelas->lembaga_id, 'kelas_id' => $kelas->id, 'status' => 'aktif']);

    $sesiDiLuarSemester = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => '2027-01-15']);
    Presensi::create(['sesi_pembelajaran_id' => $sesiDiLuarSemester->id, 'siswa_id' => $siswa->id, 'status' => 'hadir']);

    $rekap = (new PresensiAggregationService())->agregasiPerKelas($kelas->id, $semester);

    $baris = $rekap->firstWhere('siswa_id', $siswa->id);
    expect($baris['hadir'])->toBe(0);
});

it('menyertakan siswa aktif tanpa presensi sama sekali dengan semua total nol', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasUntukRekap();
    $siswa = Siswa::factory()->create(['lembaga_id' => $kelas->lembaga_id, 'kelas_id' => $kelas->id, 'status' => 'aktif']);

    $rekap = (new PresensiAggregationService())->agregasiPerKelas($kelas->id, $semester);

    $baris = $rekap->firstWhere('siswa_id', $siswa->id);
    expect($baris)->not->toBeNull()
        ->and($baris['hadir'])->toBe(0)
        ->and($baris['izin'])->toBe(0)
        ->and($baris['sakit'])->toBe(0)
        ->and($baris['alpa'])->toBe(0)
        ->and($baris['terlambat'])->toBe(0);
});

it('mengembalikan collection kosong untuk kelas tanpa siswa aktif', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasUntukRekap();

    $rekap = (new PresensiAggregationService())->agregasiPerKelas($kelas->id, $semester);

    expect($rekap)->toHaveCount(0);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal (class belum ada)**

```bash
php artisan test tests/Unit/Services/PresensiAggregationServiceTest.php
```
Expected: FAIL — `Class "App\Domains\Akademik\Services\PresensiAggregationService" not found`.

- [ ] **Step 3: Implementasikan service**

Buat `app/Domains/Akademik/Services/PresensiAggregationService.php`:
```php
<?php

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\Models\Presensi;
use App\Models\Semester;
use App\Models\Siswa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PresensiAggregationService
{
    /**
     * @return Collection<int, array{siswa_id:int,nama:string,hadir:int,izin:int,sakit:int,alpa:int,terlambat:int}>
     */
    public function agregasiPerKelas(int $kelasId, Semester $semester): Collection
    {
        $siswaList = Siswa::where('kelas_id', $kelasId)->where('status', 'aktif')->orderBy('nama')->get();

        $counts = Presensi::query()
            ->select('presensi.siswa_id', 'presensi.status', DB::raw('count(*) as total'))
            ->join('sesi_pembelajaran', 'sesi_pembelajaran.id', '=', 'presensi.sesi_pembelajaran_id')
            ->where('sesi_pembelajaran.kelas_id', $kelasId)
            ->whereBetween('sesi_pembelajaran.tanggal', [$semester->tanggal_mulai, $semester->tanggal_selesai])
            ->groupBy('presensi.siswa_id', 'presensi.status')
            ->get()
            ->groupBy('siswa_id');

        return $siswaList->map(function (Siswa $siswa) use ($counts) {
            $byStatus = $counts->get($siswa->id, collect())->pluck('total', 'status');

            return [
                'siswa_id' => $siswa->id,
                'nama' => $siswa->nama,
                'hadir' => (int) ($byStatus['hadir'] ?? 0),
                'izin' => (int) ($byStatus['izin'] ?? 0),
                'sakit' => (int) ($byStatus['sakit'] ?? 0),
                'alpa' => (int) ($byStatus['alpa'] ?? 0),
                'terlambat' => (int) ($byStatus['terlambat'] ?? 0),
            ];
        });
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

```bash
php artisan test tests/Unit/Services/PresensiAggregationServiceTest.php
```
Expected: PASS — 4 passed.

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Akademik/Services/PresensiAggregationService.php tests/Unit/Services/PresensiAggregationServiceTest.php
git commit -m "feat(akademik): tambah PresensiAggregationService untuk rekap kehadiran semesteran"
```

---

## Task 5: Bangun `RekapKehadiranController` + View + Route (TDD)

**Files:**
- Create: `app/Http/Controllers/Guru/Akademik/RekapKehadiranController.php`
- Create: `resources/views/portals/guru/akademik/jurnal-kbm/rekap.blade.php`
- Modify: `routes/admin.php`
- Modify: `resources/views/layouts/sidebar.blade.php` (opsional: link tambahan — lihat Step 4)
- Test: `tests/Feature/Guru/RekapKehadiranControllerTest.php`

**Interfaces:**
- Consumes: `PresensiAggregationService::agregasiPerKelas()` (dari Task 4); `Kelas::waliKelas()`/`wali_kelas_guru_id` (existing).
- Produces: route `guru.jurnal-kbm.rekap`.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/Guru/RekapKehadiranControllerTest.php`:
```php
<?php

use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function siapkanWaliKelasDenganSiswa(): array
{
    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_wali', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create([
        'tahun_ajaran_id' => $tahunAjaran->id,
        'status_aktif' => true,
        'tanggal_mulai' => now()->subMonth()->toDateString(),
        'tanggal_selesai' => now()->addMonth()->toDateString(),
    ]);

    $guruUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruUser->assignRole($role);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $guruUser->id]);

    $kelas = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'wali_kelas_guru_id' => $guru->id,
    ]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'status' => 'aktif']);
    $sesi = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => now()->toDateString()]);
    Presensi::create(['sesi_pembelajaran_id' => $sesi->id, 'siswa_id' => $siswa->id, 'status' => 'hadir']);

    return compact('guruUser', 'guru', 'kelas', 'siswa', 'lembaga', 'yayasan');
}

it('denies access without presensi.isi permission', function () {
    $this->actingAs(User::factory()->create())->get(route('guru.jurnal-kbm.rekap'))->assertForbidden();
});

it('shows attendance recap for the kelas the guru is wali kelas of', function () {
    ['guruUser' => $guruUser, 'kelas' => $kelas, 'siswa' => $siswa] = siapkanWaliKelasDenganSiswa();

    $response = $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.rekap', ['kelas_id' => $kelas->id]));

    $response->assertOk();
    $response->assertViewHas('rekap', function ($rekap) use ($siswa) {
        $baris = $rekap->firstWhere('siswa_id', $siswa->id);

        return $baris !== null && $baris['hadir'] === 1;
    });
});

it('does not show a kelas the guru is not wali kelas of, even from their own lembaga', function () {
    ['guruUser' => $guruUser, 'lembaga' => $lembaga] = siapkanWaliKelasDenganSiswa();
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasBukanWali = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $response = $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.rekap', ['kelas_id' => $kelasBukanWali->id]));

    $response->assertOk();
    $response->assertViewHas('kelas', fn ($kelas) => $kelas === null);
});

it('does not show a kelas from another lembaga even if the guru id happens to match a wali_kelas_guru_id there', function () {
    ['guruUser' => $guruUser, 'guru' => $guru, 'yayasan' => $yayasan] = siapkanWaliKelasDenganSiswa();

    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $kelasLain = Kelas::withoutGlobalScopes()->create([
        'lembaga_id' => $lembagaLain->id,
        'tahun_ajaran_id' => $tahunAjaranLain->id,
        'nama' => 'Kelas Lembaga Lain',
        'wali_kelas_guru_id' => $guru->id,
    ]);

    $response = $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.rekap', ['kelas_id' => $kelasLain->id]));

    $response->assertOk();
    $response->assertViewHas('kelas', fn ($kelas) => $kelas === null);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

```bash
php artisan test tests/Feature/Guru/RekapKehadiranControllerTest.php
```
Expected: FAIL — route `guru.jurnal-kbm.rekap` belum terdaftar.

- [ ] **Step 3: Buat controller**

Buat `app/Http/Controllers/Guru/Akademik/RekapKehadiranController.php`:
```php
<?php

namespace App\Http\Controllers\Guru\Akademik;

use App\Domains\Akademik\Services\PresensiAggregationService;
use App\Models\Kelas;
use App\Models\Semester;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class RekapKehadiranController extends BaseController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly PresensiAggregationService $aggregationService,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('presensi.isi');

        $guru = $request->user()->guru;
        abort_if($guru === null, 403);

        $kelasList = Kelas::where('wali_kelas_guru_id', $guru->id)->orderBy('nama')->get();
        $kelasId = (int) $request->input('kelas_id', optional($kelasList->first())->id);
        $kelas = $kelasList->firstWhere('id', $kelasId);

        $rekap = collect();
        $semester = null;
        if ($kelas) {
            $semester = Semester::where('tahun_ajaran_id', $kelas->tahun_ajaran_id)->where('status_aktif', true)->first();
            if ($semester) {
                $rekap = $this->aggregationService->agregasiPerKelas($kelas->id, $semester);
            }
        }

        return view('portals.guru.akademik.jurnal-kbm.rekap', [
            'kelasList' => $kelasList,
            'kelas' => $kelas,
            'semester' => $semester,
            'rekap' => $rekap,
        ]);
    }
}
```

> Catatan tenant safety: `Kelas::where('wali_kelas_guru_id', $guru->id)` otomatis ter-scope oleh `BelongsToTenant` milik `Kelas` (guru yang login sudah pasti berada dalam satu lembaga). `$kelasList->firstWhere('id', $kelasId)` mencari HANYA di dalam list yang sudah ter-scope tadi — kalau `kelas_id` dari query string menunjuk ke kelas lembaga lain (atau kelas yang bukan milik guru ini sebagai wali), `firstWhere` akan menghasilkan `null`, bukan mengambil row asing. Ini yang membuat test skenario ke-3 dan ke-4 di atas lulus tanpa perlu `abort_unless` tambahan.

- [ ] **Step 4: Tambah route**

Di `routes/admin.php`, di dalam grup `guru.` (setelah baris route `jurnal-kbm.update` yang dibuat di Task 3), tambahkan:
```php
use App\Http\Controllers\Guru\Akademik\RekapKehadiranController;
```
(tambahkan di bagian atas file bersama `use` lain)

Lalu tambahkan route:
```php
    Route::get('jurnal-kbm-rekap', [RekapKehadiranController::class, 'index'])->name('jurnal-kbm.rekap');
```

- [ ] **Step 5: Buat Blade view rekap**

Buat `resources/views/portals/guru/akademik/jurnal-kbm/rekap.blade.php`:
```blade
<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Rekap Kehadiran Semesteran</h1>
                <p class="text-xs text-gray-500 mt-0.5">Ringkasan kehadiran siswa untuk kelas yang Anda wali-i.</p>
            </div>
        </div>

        @if ($kelasList->isEmpty())
            <div class="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500 shadow-card">
                Anda belum menjadi wali kelas untuk kelas manapun.
            </div>
        @else
            <form method="GET" class="flex items-center gap-2">
                <select name="kelas_id" onchange="this.form.submit()" class="rounded-lg border-gray-200 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($kelasList as $opsi)
                        <option value="{{ $opsi->id }}" @selected($kelas && $kelas->id === $opsi->id)>{{ $opsi->nama }}</option>
                    @endforeach
                </select>
            </form>

            @if (! $kelas)
                <div class="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500 shadow-card">
                    Kelas tidak ditemukan atau bukan kelas yang Anda wali-i.
                </div>
            @elseif (! $semester)
                <div class="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500 shadow-card">
                    Tidak ada semester aktif untuk kelas ini.
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-card">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                <th class="px-5 py-3">Nama Siswa</th>
                                <th class="px-5 py-3">Hadir</th>
                                <th class="px-5 py-3">Izin</th>
                                <th class="px-5 py-3">Sakit</th>
                                <th class="px-5 py-3">Alpa</th>
                                <th class="px-5 py-3">Terlambat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($rekap as $baris)
                                <tr>
                                    <td class="px-5 py-3 font-medium text-gray-900">{{ $baris['nama'] }}</td>
                                    <td class="px-5 py-3 font-mono text-gray-600">{{ $baris['hadir'] }}</td>
                                    <td class="px-5 py-3 font-mono text-gray-600">{{ $baris['izin'] }}</td>
                                    <td class="px-5 py-3 font-mono text-gray-600">{{ $baris['sakit'] }}</td>
                                    <td class="px-5 py-3 font-mono text-gray-600">{{ $baris['alpa'] }}</td>
                                    <td class="px-5 py-3 font-mono text-gray-600">{{ $baris['terlambat'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-8 text-center text-gray-500">Belum ada siswa aktif di kelas ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
```

- [ ] **Step 6: Jalankan test, pastikan lulus**

```bash
php artisan test tests/Feature/Guru/RekapKehadiranControllerTest.php
```
Expected: PASS — 4 passed.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Guru/Akademik/RekapKehadiranController.php \
  resources/views/portals/guru/akademik/jurnal-kbm/rekap.blade.php \
  routes/admin.php \
  tests/Feature/Guru/RekapKehadiranControllerTest.php
git commit -m "feat(akademik): tambah halaman rekap kehadiran semesteran untuk wali kelas"
```

---

## Task 6: Verifikasi Regresi Penuh & Handoff Log

**Files:**
- Create: `.agents/logs/2026-08-18-1651-akademik-03a-migrasi-jurnal-presensi.md`
- Modify: `.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md` (update status baris 03a)

- [ ] **Step 1: Jalankan full regression suite**

```bash
php artisan test
```
Expected: semua lolos, termasuk baseline 1742 test lama + test baru dari Task 4 (4 test) + Task 5 (4 test) = minimal 1750 passed, 0 failed.

- [ ] **Step 2: Verifikasi manual (dokumentasikan hasilnya di handoff log)**

- Login sebagai guru mapel (role dengan permission `presensi.isi`) → buka `/admin/jurnal-kbm` → pastikan sesi hari ini muncul (auto-generate jalan) → buka salah satu sesi → isi materi + ubah status presensi seorang siswa → submit → pastikan redirect balik ke index dengan pesan sukses, dan data tersimpan.
- Login sebagai guru yang menjadi wali kelas → buka `/admin/jurnal-kbm-rekap` → pastikan tabel rekap menampilkan angka H/I/S/A/T yang sesuai dengan data presensi yang sudah diisi sebelumnya.

- [ ] **Step 3: Tulis handoff log**

Buat `.agents/logs/2026-08-18-1651-akademik-03a-migrasi-jurnal-presensi.md` mengikuti format wajib di `.agents/AGENTS.md` Stage 7 (Apa yang Dikerjakan / Keputusan Penting / Hal yang Perlu Direview).

- [ ] **Step 4: Update status di master plan**

Di `.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md`, ubah baris tabel navigasi sub-task **03a** dari `🟡 SPEC DRAFT (menunggu review)` menjadi `🟢 SELESAI (COMPLETED)`.

- [ ] **Step 5: Commit final**

```bash
git add .agents/logs/2026-08-18-1651-akademik-03a-migrasi-jurnal-presensi.md .agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md
git commit -m "docs(akademik): handoff log sub-task 03a migrasi jurnal & presensi"
```
