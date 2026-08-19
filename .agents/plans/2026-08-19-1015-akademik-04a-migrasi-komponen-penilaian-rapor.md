# Sub-Task 04a: Migrasi Komponen Penilaian, Asesmen, Nilai Siswa & Rapor ke Domain Akademik — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pindahkan `KomponenPenilaian`, `Asesmen`, `NilaiSiswa`, `JenisAsesmen` dari `app/Models`/`app/Enums` ke `app/Domains/Akademik/`, hilangkan duplikasi logic bisnis antara controller Admin dan Guru dengan Action bersama, dan ekstrak `RaporCalculationService` dari `RaporController` — semuanya tanpa mengubah perilaku HTTP yang bisa diamati pengguna (route, permission, response shape, pesan error tetap identik).

**Architecture:** Strangler-fig migration murni struktural (pola yang sama dengan Sub-Task 03a). Model pindah ke `app/Domains/Akademik/Models/`, logic bisnis (validasi bobot ≤100%, guard "sudah dipakai", batch simpan nilai, kalkulasi rekap tertimbang) diekstrak jadi Action/Service di `app/Domains/Akademik/{Actions,Services}/`, dipanggil dari FormRequest+Action oleh Thin Controller. Cross-lembaga boundary check yang spesifik-Admin (yayasan-scope bisa mencampur lembaga A & B) TETAP di controller Admin, tidak dipindah ke Action bersama — supaya perilaku Guru (lembaga-scope, tidak butuh cek ini) tidak berubah.

**Tech Stack:** Laravel 11, Pest, MySQL, pola domain existing di `app/Domains/Akademik/` (lihat `SesiPembelajaran`, `Rpp` sebagai referensi konvensi).

## Global Constraints

- **Tidak ada perubahan route path, nama route, atau permission gate.** Route lama tetap ada, tetap mengarah ke controller yang sama (isinya berubah, namanya tidak).
- **Tidak ada perubahan skema tabel/migrasi** (`komponen_penilaian`, `asesmen`, `asesmen_komponen_penilaian`, `nilai_siswa`). Kolom `predikat` di `nilai_siswa` dibiarkan tidak terpakai (di luar scope).
- **Tidak ada perubahan pesan/format response** yang teramati test existing — semua test existing yang di-migrasi namespace-nya harus tetap hijau tanpa mengubah isi assertion-nya.
- Setiap Action yang membungkus mutasi multi-baris (create asesmen + populate nilai kosong, batch simpan nilai) HARUS dibungkus `DB::transaction()`.
- Setiap file baru pakai `declare(strict_types=1);` dan `final class`/`final readonly class` — konsisten dengan `app/Domains/Akademik/Actions/Jadwal/CreateJadwalPelajaranAction.php` dan `app/Domains/Akademik/DataTransferObjects/JurnalPresensiData.php`.
- Model pindahan tetap pakai `HasFactory, BelongsToTenant`, dan override `newFactory()` mengembalikan `\Database\Factories\{Model}Factory::new()` — factory class-nya SENDIRI TIDAK PINDAH, tetap di `database/factories/` (pola persis `SesiPembelajaran`).
- Jalankan `php artisan test` FULL SUITE hanya SATU KALI, di Task terakhir (final review), dan hanya setelah bertanya dulu ke user apakah mau dijalankan. Selama task 1-5, jalankan HANYA test yang scoped ke file yang disentuh task tsb.
- Dispatch subagent (implementer/reviewer) TIDAK BOLEH pakai model tier paling mahal (termasuk untuk final whole-branch review) — pakai model standar/mid-tier dengan efalgo reasoning tinggi.

---

### Task 1: Pindahkan Model & Enum ke Domain Akademik

**Files:**
- Create: `app/Domains/Akademik/Models/KomponenPenilaian.php`
- Create: `app/Domains/Akademik/Models/Asesmen.php`
- Create: `app/Domains/Akademik/Models/NilaiSiswa.php`
- Create: `app/Domains/Akademik/Enums/JenisAsesmen.php`
- Delete: `app/Models/KomponenPenilaian.php`, `app/Models/Asesmen.php`, `app/Models/NilaiSiswa.php`, `app/Enums/JenisAsesmen.php`
- Modify (update `use` statement only, tidak ada perubahan logic): `app/Http/Controllers/Admin/KomponenPenilaianController.php`, `app/Http/Controllers/Guru/KomponenPenilaianController.php`, `app/Http/Controllers/Guru/AsesmenController.php`, `app/Http/Controllers/Admin/RaporController.php`, `database/factories/KomponenPenilaianFactory.php`, `database/factories/AsesmenFactory.php`, `database/factories/NilaiSiswaFactory.php`, `database/seeders/KomponenPenilaianSeeder.php`, `database/seeders/AsesmenSeeder.php`, `database/seeders/NilaiSiswaSeeder.php`, `tests/Feature/Admin/KomponenPenilaianCrudTest.php`, `tests/Feature/Admin/RaporControllerTest.php`, `tests/Feature/Guru/KomponenPenilaianControllerTest.php`, `tests/Feature/Guru/AsesmenControllerTest.php`, `tests/Feature/AkademikTenantScopeTest.php`, `tests/Unit/KomponenPenilaianSeederTest.php`, `tests/Unit/NilaiSiswaSeederTest.php`, `tests/Unit/AsesmenSeederTest.php`, `tests/Unit/Models/NilaiSiswaTest.php`, `tests/Unit/Models/AsesmenTest.php`, `tests/Unit/Enums/JenisAsesmenTest.php`

**Interfaces:**
- Consumes: nothing (foundation task).
- Produces: `App\Domains\Akademik\Models\KomponenPenilaian`, `App\Domains\Akademik\Models\Asesmen`, `App\Domains\Akademik\Models\NilaiSiswa`, `App\Domains\Akademik\Enums\JenisAsesmen` — semua Task berikutnya mengimpor dari sini, bukan dari `App\Models\*`/`App\Enums\*`.

- [ ] **Step 1: Verifikasi tidak ada referensi tersisa sebelum mulai**

Jalankan (dari root project):

```bash
grep -rln "App\\\\Models\\\\KomponenPenilaian\|App\\\\Models\\\\Asesmen\|App\\\\Models\\\\NilaiSiswa\|App\\\\Enums\\\\JenisAsesmen" --include="*.php" app database resources tests routes
```

Catat semua file yang muncul. Daftar di atas (bagian **Files**) sudah mencakup semua file yang ditemukan saat plan ini ditulis (19 Agustus 2026) — tapi WAJIB jalankan ulang grep ini sekarang untuk memastikan tidak ada file baru yang ditambahkan sejak itu. Kalau ada file baru yang muncul di luar daftar, tambahkan ke daftar **Files** di atas sebelum lanjut.

- [ ] **Step 2: Buat `app/Domains/Akademik/Enums/JenisAsesmen.php`**

```php
<?php

namespace App\Domains\Akademik\Enums;

enum JenisAsesmen: string
{
    case DiagnostikKognitif = 'diagnostik_kognitif';
    case DiagnostikNonKognitif = 'diagnostik_non_kognitif';
    case Formatif = 'formatif';
    case SumatifLingkupMateri = 'sumatif_lingkup_materi';
    case SumatifAkhirSemester = 'sumatif_akhir_semester';
    case SumatifAkhirJenjang = 'sumatif_akhir_jenjang';

    public function label(): string
    {
        return match ($this) {
            self::DiagnostikKognitif => 'Diagnostik Kognitif',
            self::DiagnostikNonKognitif => 'Diagnostik Non-Kognitif',
            self::Formatif => 'Formatif',
            self::SumatifLingkupMateri => 'Sumatif Lingkup Materi',
            self::SumatifAkhirSemester => 'Sumatif Akhir Semester',
            self::SumatifAkhirJenjang => 'Sumatif Akhir Jenjang',
        };
    }

    /**
     * @return array<int, self>
     */
    public static function v1Didukung(): array
    {
        return [
            self::SumatifLingkupMateri,
            self::SumatifAkhirSemester,
            self::SumatifAkhirJenjang,
        ];
    }
}
```

Hapus `app/Enums/JenisAsesmen.php` setelahnya.

- [ ] **Step 3: Buat `app/Domains/Akademik/Models/KomponenPenilaian.php`**

```php
<?php

namespace App\Domains\Akademik\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\Semester;
use Database\Factories\KomponenPenilaianFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KomponenPenilaian extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'komponen_penilaian';

    protected $fillable = ['mata_pelajaran_id', 'semester_id', 'lembaga_id', 'kode', 'deskripsi', 'bobot', 'kktp'];

    protected static function newFactory(): KomponenPenilaianFactory
    {
        return KomponenPenilaianFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (self $komponenPenilaian) {
            if (empty($komponenPenilaian->lembaga_id)) {
                $komponenPenilaian->lembaga_id = MataPelajaran::withoutGlobalScopes()
                    ->findOrFail($komponenPenilaian->mata_pelajaran_id)
                    ->lembaga_id;
            }
        });
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function asesmen(): BelongsToMany
    {
        return $this->belongsToMany(Asesmen::class, 'asesmen_komponen_penilaian', 'komponen_penilaian_id', 'asesmen_id');
    }

    public function nilaiSiswa(): HasMany
    {
        return $this->hasMany(NilaiSiswa::class);
    }
}
```

Hapus `app/Models/KomponenPenilaian.php` setelahnya.

- [ ] **Step 4: Buat `app/Domains/Akademik/Models/Asesmen.php`**

```php
<?php

namespace App\Domains\Akademik\Models;

use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\Semester;
use Database\Factories\AsesmenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asesmen extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'asesmen';

    protected $fillable = [
        'guru_id',
        'kelas_id',
        'mata_pelajaran_id',
        'semester_id',
        'lembaga_id',
        'jenis',
        'judul',
        'tanggal',
    ];

    protected $casts = [
        'jenis' => JenisAsesmen::class,
        'tanggal' => 'date',
    ];

    protected static function newFactory(): AsesmenFactory
    {
        return AsesmenFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (self $asesmen) {
            if (empty($asesmen->lembaga_id)) {
                $asesmen->lembaga_id = Kelas::withoutGlobalScopes()
                    ->findOrFail($asesmen->kelas_id)
                    ->lembaga_id;
            }
        });
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function komponenPenilaian(): BelongsToMany
    {
        return $this->belongsToMany(KomponenPenilaian::class, 'asesmen_komponen_penilaian', 'asesmen_id', 'komponen_penilaian_id');
    }

    public function nilaiSiswa(): HasMany
    {
        return $this->hasMany(NilaiSiswa::class);
    }
}
```

Hapus `app/Models/Asesmen.php` setelahnya.

- [ ] **Step 5: Buat `app/Domains/Akademik/Models/NilaiSiswa.php`**

```php
<?php

namespace App\Domains\Akademik\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use App\Models\Siswa;
use Database\Factories\NilaiSiswaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NilaiSiswa extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'nilai_siswa';

    protected $fillable = [
        'asesmen_id',
        'siswa_id',
        'komponen_penilaian_id',
        'lembaga_id',
        'nilai_angka',
        'predikat',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'nilai_angka' => 'integer',
        ];
    }

    protected static function newFactory(): NilaiSiswaFactory
    {
        return NilaiSiswaFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (self $nilaiSiswa) {
            if (empty($nilaiSiswa->lembaga_id)) {
                $nilaiSiswa->lembaga_id = Siswa::withoutGlobalScopes()
                    ->findOrFail($nilaiSiswa->siswa_id)
                    ->lembaga_id;
            }
        });
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function asesmen(): BelongsTo
    {
        return $this->belongsTo(Asesmen::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function komponenPenilaian(): BelongsTo
    {
        return $this->belongsTo(KomponenPenilaian::class);
    }
}
```

Hapus `app/Models/NilaiSiswa.php` setelahnya.

- [ ] **Step 6: Update `use` statement di ketiga factory (model pindah, factory class tetap di `database/factories/`)**

`database/factories/KomponenPenilaianFactory.php` — ganti baris `use App\Models\KomponenPenilaian;` menjadi `use App\Domains\Akademik\Models\KomponenPenilaian;` (baris lain tidak berubah).

`database/factories/AsesmenFactory.php` — ganti:
```php
use App\Enums\JenisAsesmen;
use App\Models\Asesmen;
```
menjadi:
```php
use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Models\Asesmen;
```
(baris `use App\Models\Guru;`, `use App\Models\Kelas;`, `use App\Models\MataPelajaran;`, `use App\Models\Semester;` tidak berubah — model-model itu tidak ikut pindah).

`database/factories/NilaiSiswaFactory.php` — ganti:
```php
use App\Models\Asesmen;
use App\Models\KomponenPenilaian;
use App\Models\NilaiSiswa;
```
menjadi:
```php
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
```
(baris `use App\Models\Siswa;` tidak berubah).

- [ ] **Step 7: Update `use` statement di ketiga seeder**

`database/seeders/KomponenPenilaianSeeder.php` — ganti `use App\Models\KomponenPenilaian;` menjadi `use App\Domains\Akademik\Models\KomponenPenilaian;`.

`database/seeders/AsesmenSeeder.php` — ganti:
```php
use App\Enums\JenisAsesmen;
use App\Models\Asesmen;
...
use App\Models\KomponenPenilaian;
```
menjadi (posisi baris menyesuaikan urutan alfabetis import, sisanya tetap):
```php
use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
```

`database/seeders/NilaiSiswaSeeder.php` — ganti:
```php
use App\Models\Asesmen;
...
use App\Models\KomponenPenilaian;
...
use App\Models\NilaiSiswa;
```
menjadi:
```php
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
```
(baris import model lain yang tidak pindah — `Kelas`, `Lembaga`, `MataPelajaran`, `Siswa`, `TahunAjaran` — tetap `App\Models\...`).

- [ ] **Step 8: Update `use` statement di 4 controller (logic BELUM berubah di step ini — itu Task 2-4)**

Di `app/Http/Controllers/Admin/KomponenPenilaianController.php`: ganti `use App\Models\KomponenPenilaian;` → `use App\Domains\Akademik\Models\KomponenPenilaian;`.

Di `app/Http/Controllers/Guru/KomponenPenilaianController.php`: ganti `use App\Models\KomponenPenilaian;` → `use App\Domains\Akademik\Models\KomponenPenilaian;`.

Di `app/Http/Controllers/Guru/AsesmenController.php`: ganti:
```php
use App\Enums\JenisAsesmen;
use App\Models\Asesmen;
...
use App\Models\KomponenPenilaian;
...
use App\Models\NilaiSiswa;
```
menjadi:
```php
use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
```

Di `app/Http/Controllers/Admin/RaporController.php`: ganti:
```php
use App\Models\Asesmen;
...
use App\Models\NilaiSiswa;
```
menjadi:
```php
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\NilaiSiswa;
```
(baris `use App\Models\Kelas;`, `use App\Models\Semester;`, `use App\Models\Siswa;`, `use App\Models\TahunAjaran;` tetap).

- [ ] **Step 9: Update `use` statement di 11 file test**

Untuk SETIAP file test di bawah ini, cari baris import lama dan ganti persis seperti pola Step 6-8 di atas (`App\Models\{KomponenPenilaian,Asesmen,NilaiSiswa}` → `App\Domains\Akademik\Models\{KomponenPenilaian,Asesmen,NilaiSiswa}`, `App\Enums\JenisAsesmen` → `App\Domains\Akademik\Enums\JenisAsesmen`). Import model lain (`Kelas`, `Lembaga`, `Guru`, dst) yang TIDAK pindah domain — jangan diubah:

- `tests/Feature/Admin/KomponenPenilaianCrudTest.php`
- `tests/Feature/Admin/RaporControllerTest.php`
- `tests/Feature/Guru/KomponenPenilaianControllerTest.php`
- `tests/Feature/Guru/AsesmenControllerTest.php`
- `tests/Feature/AkademikTenantScopeTest.php`
- `tests/Unit/KomponenPenilaianSeederTest.php`
- `tests/Unit/NilaiSiswaSeederTest.php`
- `tests/Unit/AsesmenSeederTest.php`
- `tests/Unit/Models/NilaiSiswaTest.php`
- `tests/Unit/Models/AsesmenTest.php`
- `tests/Unit/Enums/JenisAsesmenTest.php`

Isi assertion di dalam masing-masing file TIDAK berubah sama sekali — murni ganti baris `use`.

- [ ] **Step 10: Jalankan grep ulang untuk pastikan tidak ada referensi lama tersisa**

```bash
grep -rln "App\\\\Models\\\\KomponenPenilaian\|App\\\\Models\\\\Asesmen\|App\\\\Models\\\\NilaiSiswa\|App\\\\Enums\\\\JenisAsesmen" --include="*.php" app database resources tests routes
```

Expected: tidak ada output (kosong). Kalau ada, cek apakah itu match substring yang tidak relevan (mis. `Guru\AsesmenController` mengandung teks "Asesmen" tapi bukan `App\Models\Asesmen`, jadi grep pattern di atas sudah cukup spesifik dengan namespace lengkap — false positive tidak seharusnya terjadi, tapi verifikasi manual tetap perlu).

- [ ] **Step 11: Jalankan test yang tersentuh untuk pastikan migrasi murni namespace tidak merusak apa pun**

```bash
php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php tests/Feature/Admin/RaporControllerTest.php tests/Feature/Guru/KomponenPenilaianControllerTest.php tests/Feature/Guru/AsesmenControllerTest.php tests/Feature/AkademikTenantScopeTest.php tests/Unit/KomponenPenilaianSeederTest.php tests/Unit/NilaiSiswaSeederTest.php tests/Unit/AsesmenSeederTest.php tests/Unit/Models/NilaiSiswaTest.php tests/Unit/Models/AsesmenTest.php tests/Unit/Enums/JenisAsesmenTest.php
```

Expected: semua PASS, jumlah test/assertion sama dengan sebelum migrasi (tidak ada yang di-skip/error).

- [ ] **Step 12: Commit**

```bash
git add app/Domains/Akademik/Models/KomponenPenilaian.php app/Domains/Akademik/Models/Asesmen.php app/Domains/Akademik/Models/NilaiSiswa.php app/Domains/Akademik/Enums/JenisAsesmen.php app/Http/Controllers/Admin/KomponenPenilaianController.php app/Http/Controllers/Guru/KomponenPenilaianController.php app/Http/Controllers/Guru/AsesmenController.php app/Http/Controllers/Admin/RaporController.php database/factories/KomponenPenilaianFactory.php database/factories/AsesmenFactory.php database/factories/NilaiSiswaFactory.php database/seeders/KomponenPenilaianSeeder.php database/seeders/AsesmenSeeder.php database/seeders/NilaiSiswaSeeder.php tests/Feature/Admin/KomponenPenilaianCrudTest.php tests/Feature/Admin/RaporControllerTest.php tests/Feature/Guru/KomponenPenilaianControllerTest.php tests/Feature/Guru/AsesmenControllerTest.php tests/Feature/AkademikTenantScopeTest.php tests/Unit/KomponenPenilaianSeederTest.php tests/Unit/NilaiSiswaSeederTest.php tests/Unit/AsesmenSeederTest.php tests/Unit/Models/NilaiSiswaTest.php tests/Unit/Models/AsesmenTest.php tests/Unit/Enums/JenisAsesmenTest.php
git rm app/Models/KomponenPenilaian.php app/Models/Asesmen.php app/Models/NilaiSiswa.php app/Enums/JenisAsesmen.php
git commit -m "refactor(akademik): pindahkan KomponenPenilaian, Asesmen, NilaiSiswa, JenisAsesmen ke domain Akademik"
```

---

### Task 2: Action & FormRequest untuk Komponen Penilaian (hilangkan duplikasi Admin/Guru)

**Files:**
- Create: `app/Domains/Akademik/DataTransferObjects/KomponenPenilaianData.php`
- Create: `app/Domains/Akademik/DataTransferObjects/UpdateKomponenPenilaianData.php`
- Create: `app/Domains/Akademik/Actions/Penilaian/CreateKomponenPenilaianAction.php`
- Create: `app/Domains/Akademik/Actions/Penilaian/UpdateKomponenPenilaianAction.php`
- Create: `app/Domains/Akademik/Actions/Penilaian/DeleteKomponenPenilaianAction.php`
- Create: `app/Http/Requests/Akademik/StoreKomponenPenilaianRequest.php` (Admin)
- Create: `app/Http/Requests/Akademik/UpdateKomponenPenilaianRequest.php` (Admin)
- Create: `app/Http/Requests/Akademik/StoreKomponenPenilaianSendiriRequest.php` (Guru)
- Create: `app/Http/Requests/Akademik/UpdateKomponenPenilaianSendiriRequest.php` (Guru)
- Modify: `app/Http/Controllers/Admin/KomponenPenilaianController.php` (`store`, `update`, `destroy` — jadi thin)
- Modify: `app/Http/Controllers/Guru/KomponenPenilaianController.php` (`store`, `update`, `destroy` — jadi thin)
- Test: `tests/Feature/Admin/KomponenPenilaianCrudTest.php`, `tests/Feature/Guru/KomponenPenilaianControllerTest.php` (existing, TIDAK diubah isinya — jadi regression net untuk task ini)

**Interfaces:**
- Consumes: `App\Domains\Akademik\Models\KomponenPenilaian` (dari Task 1).
- Produces: `CreateKomponenPenilaianAction::execute(KomponenPenilaianData $data): KomponenPenilaian`, `UpdateKomponenPenilaianAction::execute(KomponenPenilaian $komponen, UpdateKomponenPenilaianData $data): KomponenPenilaian`, `DeleteKomponenPenilaianAction::execute(KomponenPenilaian $komponen): void` — dipakai kedua controller di task ini saja (tidak dipakai task lain di plan ini).

- [ ] **Step 1: Buat DTO `KomponenPenilaianData` (dipakai untuk create)**

`app/Domains/Akademik/DataTransferObjects/KomponenPenilaianData.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class KomponenPenilaianData
{
    public function __construct(
        public int $mataPelajaranId,
        public int $semesterId,
        public ?string $kode,
        public string $deskripsi,
        public int $bobot,
        public ?string $kktp,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            mataPelajaranId: (int) $data['mata_pelajaran_id'],
            semesterId: (int) $data['semester_id'],
            kode: $data['kode'] ?? null,
            deskripsi: $data['deskripsi'],
            bobot: isset($data['bobot']) ? (int) $data['bobot'] : 10,
            kktp: $data['kktp'] ?? null,
        );
    }
}
```

- [ ] **Step 2: Buat DTO `UpdateKomponenPenilaianData` (dipakai untuk update — field bisa null kalau tidak dikirim/tidak boleh diubah)**

`app/Domains/Akademik/DataTransferObjects/UpdateKomponenPenilaianData.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class UpdateKomponenPenilaianData
{
    public function __construct(
        public ?int $mataPelajaranId,
        public ?int $semesterId,
        public ?string $kode,
        public string $deskripsi,
        public ?int $bobot,
        public ?string $kktp,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            mataPelajaranId: isset($data['mata_pelajaran_id']) ? (int) $data['mata_pelajaran_id'] : null,
            semesterId: isset($data['semester_id']) ? (int) $data['semester_id'] : null,
            kode: $data['kode'] ?? null,
            deskripsi: $data['deskripsi'],
            bobot: isset($data['bobot']) ? (int) $data['bobot'] : null,
            kktp: $data['kktp'] ?? null,
        );
    }
}
```

- [ ] **Step 3: Buat `CreateKomponenPenilaianAction`**

`app/Domains/Akademik/Actions/Penilaian/CreateKomponenPenilaianAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Penilaian;

use App\Domains\Akademik\DataTransferObjects\KomponenPenilaianData;
use App\Domains\Akademik\Models\KomponenPenilaian;
use Illuminate\Validation\ValidationException;

final class CreateKomponenPenilaianAction
{
    /**
     * @throws ValidationException
     */
    public function execute(KomponenPenilaianData $data): KomponenPenilaian
    {
        $existingSum = KomponenPenilaian::where('mata_pelajaran_id', $data->mataPelajaranId)
            ->where('semester_id', $data->semesterId)
            ->sum('bobot');

        if (($existingSum + $data->bobot) > 100) {
            $remaining = max(0, 100 - $existingSum);
            throw ValidationException::withMessages([
                'bobot' => "Total bobot melebihi 100%. Sisa bobot yang tersedia untuk mata pelajaran ini adalah {$remaining}%.",
            ]);
        }

        return KomponenPenilaian::create([
            'mata_pelajaran_id' => $data->mataPelajaranId,
            'semester_id' => $data->semesterId,
            'kode' => $data->kode,
            'deskripsi' => $data->deskripsi,
            'bobot' => $data->bobot,
            'kktp' => $data->kktp,
        ]);
    }
}
```

- [ ] **Step 4: Buat `UpdateKomponenPenilaianAction`**

`app/Domains/Akademik/Actions/Penilaian/UpdateKomponenPenilaianAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Penilaian;

use App\Domains\Akademik\DataTransferObjects\UpdateKomponenPenilaianData;
use App\Domains\Akademik\Models\KomponenPenilaian;
use Illuminate\Validation\ValidationException;

final class UpdateKomponenPenilaianAction
{
    /**
     * @throws ValidationException
     */
    public function execute(KomponenPenilaian $komponen, UpdateKomponenPenilaianData $data): KomponenPenilaian
    {
        $dipakai = $komponen->asesmen()->exists() || $komponen->nilaiSiswa()->exists();

        if (! $dipakai && $data->mataPelajaranId !== null && $data->semesterId !== null) {
            $komponen->mata_pelajaran_id = $data->mataPelajaranId;
            $komponen->semester_id = $data->semesterId;
        }

        $newBobot = $data->bobot ?? $komponen->bobot;
        $existingSum = KomponenPenilaian::where('mata_pelajaran_id', $komponen->mata_pelajaran_id)
            ->where('semester_id', $komponen->semester_id)
            ->where('id', '!=', $komponen->id)
            ->sum('bobot');

        if (($existingSum + $newBobot) > 100) {
            $remaining = max(0, 100 - $existingSum);
            throw ValidationException::withMessages([
                'bobot' => "Total bobot melebihi 100%. Sisa bobot yang tersedia untuk mata pelajaran ini adalah {$remaining}%.",
            ]);
        }

        $komponen->kode = $data->kode;
        $komponen->deskripsi = $data->deskripsi;
        $komponen->bobot = $newBobot;
        $komponen->kktp = $data->kktp;
        $komponen->save();

        return $komponen;
    }
}
```

- [ ] **Step 5: Buat `DeleteKomponenPenilaianAction`**

`app/Domains/Akademik/Actions/Penilaian/DeleteKomponenPenilaianAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Penilaian;

use App\Domains\Akademik\Models\KomponenPenilaian;
use Illuminate\Validation\ValidationException;

final class DeleteKomponenPenilaianAction
{
    /**
     * @throws ValidationException
     */
    public function execute(KomponenPenilaian $komponen): void
    {
        if ($komponen->asesmen()->exists() || $komponen->nilaiSiswa()->exists()) {
            throw ValidationException::withMessages([
                'komponen_penilaian' => 'Komponen ini sudah dipakai pada asesmen atau nilai siswa — tidak bisa dihapus.',
            ]);
        }

        $komponen->delete();
    }
}
```

- [ ] **Step 6: Buat FormRequest Admin — `StoreKomponenPenilaianRequest`**

`app/Http/Requests/Akademik/StoreKomponenPenilaianRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\DataTransferObjects\KomponenPenilaianData;
use Illuminate\Foundation\Http\FormRequest;

final class StoreKomponenPenilaianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('komponen-penilaian.kelola');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'mata_pelajaran_id' => ['required', 'integer'],
            'semester_id' => ['required', 'integer'],
            'kode' => ['nullable', 'string', 'max:50'],
            'deskripsi' => ['required', 'string'],
            'bobot' => ['nullable', 'integer', 'min:1', 'max:100'],
            'kktp' => ['nullable', 'string'],
        ];
    }

    public function toDTO(): KomponenPenilaianData
    {
        return KomponenPenilaianData::fromArray($this->validated());
    }
}
```

- [ ] **Step 7: Buat FormRequest Admin — `UpdateKomponenPenilaianRequest`**

`app/Http/Requests/Akademik/UpdateKomponenPenilaianRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\DataTransferObjects\UpdateKomponenPenilaianData;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateKomponenPenilaianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('komponen-penilaian.kelola');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $komponen = $this->route('komponenPenilaian');
        $dipakai = $komponen->asesmen()->exists() || $komponen->nilaiSiswa()->exists();

        $rules = [
            'kode' => ['nullable', 'string', 'max:50'],
            'deskripsi' => ['required', 'string'],
            'bobot' => ['nullable', 'integer', 'min:1', 'max:100'],
            'kktp' => ['nullable', 'string'],
        ];

        if (! $dipakai) {
            $rules['mata_pelajaran_id'] = ['required', 'integer'];
            $rules['semester_id'] = ['required', 'integer'];
        }

        return $rules;
    }

    public function toDTO(): UpdateKomponenPenilaianData
    {
        return UpdateKomponenPenilaianData::fromArray($this->validated());
    }
}
```

- [ ] **Step 8: Buat FormRequest Guru — `StoreKomponenPenilaianSendiriRequest`**

`app/Http/Requests/Akademik/StoreKomponenPenilaianSendiriRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\DataTransferObjects\KomponenPenilaianData;
use Illuminate\Foundation\Http\FormRequest;

final class StoreKomponenPenilaianSendiriRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('komponen-penilaian.kelola-sendiri');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'mata_pelajaran_id' => ['required', 'integer'],
            'semester_id' => ['required', 'integer'],
            'kode' => ['nullable', 'string', 'max:50'],
            'deskripsi' => ['required', 'string'],
            'bobot' => ['nullable', 'integer', 'min:1', 'max:100'],
            'kktp' => ['nullable', 'string'],
        ];
    }

    public function toDTO(): KomponenPenilaianData
    {
        return KomponenPenilaianData::fromArray($this->validated());
    }
}
```

- [ ] **Step 9: Buat FormRequest Guru — `UpdateKomponenPenilaianSendiriRequest`**

`app/Http/Requests/Akademik/UpdateKomponenPenilaianSendiriRequest.php` — Guru TIDAK PERNAH mengirim `mata_pelajaran_id`/`semester_id` saat update (lihat controller lama), jadi rules-nya statis, tidak perlu cek `$dipakai`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\DataTransferObjects\UpdateKomponenPenilaianData;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateKomponenPenilaianSendiriRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('komponen-penilaian.kelola-sendiri');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'kode' => ['nullable', 'string', 'max:50'],
            'deskripsi' => ['required', 'string'],
            'bobot' => ['nullable', 'integer', 'min:1', 'max:100'],
            'kktp' => ['nullable', 'string'],
        ];
    }

    public function toDTO(): UpdateKomponenPenilaianData
    {
        return UpdateKomponenPenilaianData::fromArray($this->validated());
    }
}
```

- [ ] **Step 10: Refactor `Admin\KomponenPenilaianController` — `store`, `update`, `destroy` jadi thin**

Di `app/Http/Controllers/Admin/KomponenPenilaianController.php`, tambahkan import di bagian atas:

```php
use App\Domains\Akademik\Actions\Penilaian\CreateKomponenPenilaianAction;
use App\Domains\Akademik\Actions\Penilaian\DeleteKomponenPenilaianAction;
use App\Domains\Akademik\Actions\Penilaian\UpdateKomponenPenilaianAction;
use App\Http\Requests\Akademik\StoreKomponenPenilaianRequest;
use App\Http\Requests\Akademik\UpdateKomponenPenilaianRequest;
use Illuminate\Validation\ValidationException;
```

Tambahkan constructor (kelas belum punya constructor):

```php
    public function __construct(
        private readonly CreateKomponenPenilaianAction $createKomponenPenilaianAction,
        private readonly UpdateKomponenPenilaianAction $updateKomponenPenilaianAction,
        private readonly DeleteKomponenPenilaianAction $deleteKomponenPenilaianAction,
    ) {
    }
```

Ganti method `store()` (cross-lembaga check TETAP di controller — ini kebijakan khusus Admin, lihat Global Constraints):

```php
    public function store(StoreKomponenPenilaianRequest $request): RedirectResponse|JsonResponse
    {
        $data = $request->validated();

        $mataPelajaran = MataPelajaran::find($data['mata_pelajaran_id']);
        $semester = Semester::find($data['semester_id']);
        abort_if($mataPelajaran === null || $semester === null, 404);
        abort_if($mataPelajaran->lembaga_id !== $semester->lembaga_id, 404);

        try {
            $this->createKomponenPenilaianAction->execute($request->toDTO());
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->collapse()->first();
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg], 422);
            }
            return back()->withInput()->withErrors($e->errors());
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => 'Komponen penilaian (TP) berhasil disimpan.']);
        }

        return redirect()->route('admin.komponen-penilaian.index')->with('status', 'Komponen penilaian (TP) berhasil disimpan.');
    }
```

Ganti method `update()`:

```php
    public function update(UpdateKomponenPenilaianRequest $request, KomponenPenilaian $komponenPenilaian): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $dipakai = $komponenPenilaian->asesmen()->exists() || $komponenPenilaian->nilaiSiswa()->exists();

        if (! $dipakai && isset($data['mata_pelajaran_id'], $data['semester_id'])) {
            $mataPelajaran = MataPelajaran::find($data['mata_pelajaran_id']);
            $semester = Semester::find($data['semester_id']);
            abort_if($mataPelajaran === null || $semester === null, 404);
            abort_if($mataPelajaran->lembaga_id !== $semester->lembaga_id, 404);
        }

        try {
            $this->updateKomponenPenilaianAction->execute($komponenPenilaian, $request->toDTO());
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->collapse()->first();
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg], 422);
            }
            return back()->withInput()->withErrors($e->errors());
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => 'Komponen penilaian (TP) berhasil diperbarui.']);
        }

        return redirect()->route('admin.komponen-penilaian.index')->with('status', 'Komponen penilaian (TP) berhasil diperbarui.');
    }
```

Ganti method `destroy()`:

```php
    public function destroy(KomponenPenilaian $komponenPenilaian): RedirectResponse|JsonResponse
    {
        $this->authorize('komponen-penilaian.kelola');

        $mataPelajaran = MataPelajaran::find($komponenPenilaian->mata_pelajaran_id);
        if (! $mataPelajaran) {
            abort(404);
        }

        try {
            $this->deleteKomponenPenilaianAction->execute($komponenPenilaian);
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->collapse()->first();
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg], 422);
            }
            return back()->withErrors($e->errors());
        }

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => 'Komponen penilaian (TP) berhasil dihapus.']);
        }

        return redirect()->route('admin.komponen-penilaian.index')->with('status', 'Komponen penilaian (TP) berhasil dihapus.');
    }
```

`edit()` method TIDAK berubah (masih pakai `$dipakai` inline untuk kirim ke view — biarkan apa adanya, murni tampilan).

- [ ] **Step 11: Refactor `Guru\KomponenPenilaianController` — `store`, `update`, `destroy` jadi thin**

Di `app/Http/Controllers/Guru/KomponenPenilaianController.php`, tambahkan import:

```php
use App\Domains\Akademik\Actions\Penilaian\CreateKomponenPenilaianAction;
use App\Domains\Akademik\Actions\Penilaian\DeleteKomponenPenilaianAction;
use App\Domains\Akademik\Actions\Penilaian\UpdateKomponenPenilaianAction;
use App\Http\Requests\Akademik\StoreKomponenPenilaianSendiriRequest;
use App\Http\Requests\Akademik\UpdateKomponenPenilaianSendiriRequest;
use Illuminate\Validation\ValidationException;
```

Tambahkan constructor:

```php
    public function __construct(
        private readonly CreateKomponenPenilaianAction $createKomponenPenilaianAction,
        private readonly UpdateKomponenPenilaianAction $updateKomponenPenilaianAction,
        private readonly DeleteKomponenPenilaianAction $deleteKomponenPenilaianAction,
    ) {
    }
```

Ganti method `store()` (guard "mengajar kombinasi ini" TETAP di controller, sesuai perilaku asli — tidak ada cross-lembaga check di jalur Guru):

```php
    public function store(StoreKomponenPenilaianSendiriRequest $request): RedirectResponse|JsonResponse
    {
        $guru = $request->user()->guru;
        abort_if(! $guru, 403, 'Profil guru tidak ditemukan untuk akun ini.');

        $data = $request->validated();
        $mengajarKombinasiIni = JadwalPelajaran::where('guru_id', $guru->id)
            ->where('mata_pelajaran_id', $data['mata_pelajaran_id'])
            ->where('semester_id', $data['semester_id'])
            ->exists();

        abort_unless($mengajarKombinasiIni, 403, 'Anda tidak mengajar kombinasi mata pelajaran dan semester ini.');

        try {
            $this->createKomponenPenilaianAction->execute($request->toDTO());
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->collapse()->first();
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg], 422);
            }
            return back()->withInput()->withErrors($e->errors());
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => 'Komponen penilaian (TP) berhasil disimpan.']);
        }

        return redirect()->route('guru.komponen-penilaian.index')->with('status', 'Komponen penilaian (TP) berhasil disimpan.');
    }
```

Ganti method `update()` (guard `authorizeMengajarMapel()` yang sudah ada di versi lama TETAP dipanggil di controller — bukan business logic bobot/dipakai, tapi ownership check "guru ini benar mengajar mapel dari komponen ini", jadi tidak masuk ke Action bersama):

```php
    public function update(UpdateKomponenPenilaianSendiriRequest $request, KomponenPenilaian $komponenPenilaian): RedirectResponse|JsonResponse
    {
        $this->authorizeMengajarMapel($komponenPenilaian);

        try {
            $this->updateKomponenPenilaianAction->execute($komponenPenilaian, $request->toDTO());
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->collapse()->first();
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg], 422);
            }
            return back()->withInput()->withErrors($e->errors());
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => 'Komponen penilaian (TP) berhasil diperbarui.']);
        }

        return redirect()->route('guru.komponen-penilaian.index')->with('status', 'Komponen penilaian (TP) berhasil diperbarui.');
    }
```

Ganti method `destroy()`:

```php
    public function destroy(KomponenPenilaian $komponenPenilaian): RedirectResponse|JsonResponse
    {
        $this->authorize('komponen-penilaian.kelola-sendiri');
        $this->authorizeMengajarMapel($komponenPenilaian);

        try {
            $this->deleteKomponenPenilaianAction->execute($komponenPenilaian);
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->collapse()->first();
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg], 422);
            }
            return back()->withErrors($e->errors());
        }

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json(['status' => 'success', 'message' => 'Komponen penilaian (TP) berhasil dihapus.']);
        }

        return redirect()->route('guru.komponen-penilaian.index')->with('status', 'Komponen penilaian (TP) berhasil dihapus.');
    }
```

`edit()` dan `authorizeMengajarMapel()` TIDAK berubah. **Catatan:** karena `store()`/`update()` sekarang lewat FormRequest yang `authorize()`-nya sudah mengecek permission `komponen-penilaian.kelola-sendiri`, method-method itu TIDAK memanggil `$this->authorize(...)` lagi secara manual (persis pola `RppController::store()`) — tapi `authorizeMengajarMapel()` (ownership check, bukan permission check) TETAP dipanggil eksplisit di `update()` dan `destroy()`, persis seperti versi lama.

- [ ] **Step 12: Jalankan test regresi**

```bash
php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php tests/Feature/Guru/KomponenPenilaianControllerTest.php
```

Expected: semua test PASS tanpa perubahan assertion (test-test ini adalah regression net murni untuk task ini — mereka menguji perilaku HTTP end-to-end yang tidak boleh berubah).

- [ ] **Step 13: Commit**

```bash
git add app/Domains/Akademik/DataTransferObjects/KomponenPenilaianData.php app/Domains/Akademik/DataTransferObjects/UpdateKomponenPenilaianData.php app/Domains/Akademik/Actions/Penilaian/CreateKomponenPenilaianAction.php app/Domains/Akademik/Actions/Penilaian/UpdateKomponenPenilaianAction.php app/Domains/Akademik/Actions/Penilaian/DeleteKomponenPenilaianAction.php app/Http/Requests/Akademik/StoreKomponenPenilaianRequest.php app/Http/Requests/Akademik/UpdateKomponenPenilaianRequest.php app/Http/Requests/Akademik/StoreKomponenPenilaianSendiriRequest.php app/Http/Requests/Akademik/UpdateKomponenPenilaianSendiriRequest.php app/Http/Controllers/Admin/KomponenPenilaianController.php app/Http/Controllers/Guru/KomponenPenilaianController.php
git commit -m "refactor(akademik): ekstrak Action Komponen Penilaian, hapus duplikasi Admin/Guru"
```

---

### Task 3: Action & FormRequest untuk Asesmen & Nilai Siswa

**Files:**
- Create: `app/Domains/Akademik/DataTransferObjects/AsesmenData.php`
- Create: `app/Domains/Akademik/DataTransferObjects/NilaiSiswaBatchData.php`
- Create: `app/Domains/Akademik/Actions/Penilaian/CreateAsesmenAction.php`
- Create: `app/Domains/Akademik/Actions/Penilaian/SimpanNilaiSiswaAction.php`
- Create: `app/Http/Requests/Akademik/StoreAsesmenRequest.php`
- Create: `app/Http/Requests/Akademik/UpdateNilaiSiswaRequest.php`
- Modify: `app/Http/Controllers/Guru/AsesmenController.php` (`store`, `updateNilai` — jadi thin)
- Test: `tests/Feature/Guru/AsesmenControllerTest.php` (existing, TIDAK diubah isinya — regression net)

**Interfaces:**
- Consumes: `App\Domains\Akademik\Models\{Asesmen,KomponenPenilaian,NilaiSiswa}`, `App\Domains\Akademik\Enums\JenisAsesmen` (dari Task 1).
- Produces: `CreateAsesmenAction::execute(Guru $guru, AsesmenData $data): Asesmen`, `SimpanNilaiSiswaAction::execute(Asesmen $asesmen, NilaiSiswaBatchData $data): void` — tidak dipakai task lain di plan ini.

- [ ] **Step 1: Buat DTO `AsesmenData`**

`app/Domains/Akademik/DataTransferObjects/AsesmenData.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class AsesmenData
{
    /**
     * @param  array<int, int>  $komponenId
     */
    public function __construct(
        public int $kelasId,
        public int $mataPelajaranId,
        public int $semesterId,
        public string $jenis,
        public string $judul,
        public string $tanggal,
        public array $komponenId,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            kelasId: (int) $data['kelas_id'],
            mataPelajaranId: (int) $data['mata_pelajaran_id'],
            semesterId: (int) $data['semester_id'],
            jenis: (string) $data['jenis'],
            judul: (string) $data['judul'],
            tanggal: (string) $data['tanggal'],
            komponenId: array_map('intval', $data['komponen_id'] ?? []),
        );
    }
}
```

- [ ] **Step 2: Buat DTO `NilaiSiswaBatchData`**

`app/Domains/Akademik/DataTransferObjects/NilaiSiswaBatchData.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class NilaiSiswaBatchData
{
    /**
     * @param  array<int|string, array<int|string, array{nilai_angka?: int|string|null, catatan?: string|null}>>  $nilai  siswa_id => komponen_penilaian_id => [nilai_angka, catatan]
     */
    public function __construct(
        public array $nilai,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            nilai: $data['nilai'] ?? [],
        );
    }
}
```

- [ ] **Step 3: Buat `CreateAsesmenAction`**

`app/Domains/Akademik/Actions/Penilaian/CreateAsesmenAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Penilaian;

use App\Domains\Akademik\DataTransferObjects\AsesmenData;
use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Models\Guru;
use Illuminate\Support\Facades\DB;

final class CreateAsesmenAction
{
    public function execute(Guru $guru, AsesmenData $data): Asesmen
    {
        $komponenIds = ! empty($data->komponenId)
            ? KomponenPenilaian::whereIn('id', $data->komponenId)->where('mata_pelajaran_id', $data->mataPelajaranId)->pluck('id')
            : collect();

        return DB::transaction(function () use ($guru, $data, $komponenIds) {
            $asesmen = Asesmen::create([
                'guru_id' => $guru->id,
                'kelas_id' => $data->kelasId,
                'mata_pelajaran_id' => $data->mataPelajaranId,
                'semester_id' => $data->semesterId,
                'jenis' => JenisAsesmen::from($data->jenis),
                'judul' => $data->judul,
                'tanggal' => $data->tanggal,
            ]);

            if ($komponenIds->isNotEmpty()) {
                $asesmen->komponenPenilaian()->attach($komponenIds);
            }

            $siswaList = $asesmen->kelas->siswa()->get();
            foreach ($siswaList as $siswa) {
                foreach ($komponenIds as $komponenId) {
                    NilaiSiswa::firstOrCreate([
                        'asesmen_id' => $asesmen->id,
                        'siswa_id' => $siswa->id,
                        'komponen_penilaian_id' => $komponenId,
                    ]);
                }
            }

            return $asesmen;
        });
    }
}
```

- [ ] **Step 4: Buat `SimpanNilaiSiswaAction`**

`app/Domains/Akademik/Actions/Penilaian/SimpanNilaiSiswaAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Penilaian;

use App\Domains\Akademik\DataTransferObjects\NilaiSiswaBatchData;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\NilaiSiswa;
use Illuminate\Support\Facades\DB;

final class SimpanNilaiSiswaAction
{
    public function execute(Asesmen $asesmen, NilaiSiswaBatchData $data): void
    {
        $komponenIds = $asesmen->komponenPenilaian()->pluck('komponen_penilaian.id');
        $siswaIds = $asesmen->kelas->siswa()->pluck('id');

        DB::transaction(function () use ($asesmen, $data, $komponenIds, $siswaIds) {
            foreach ($data->nilai as $siswaId => $perKomponen) {
                if (! $siswaIds->contains((int) $siswaId)) {
                    continue;
                }

                foreach ($perKomponen as $komponenId => $values) {
                    if (! $komponenIds->contains((int) $komponenId)) {
                        continue;
                    }

                    NilaiSiswa::updateOrCreate(
                        ['asesmen_id' => $asesmen->id, 'siswa_id' => $siswaId, 'komponen_penilaian_id' => $komponenId],
                        [
                            'nilai_angka' => isset($values['nilai_angka']) && $values['nilai_angka'] !== '' ? (int) $values['nilai_angka'] : null,
                            'catatan' => $values['catatan'] ?? null,
                        ]
                    );
                }
            }
        });
    }
}
```

- [ ] **Step 5: Buat `StoreAsesmenRequest`**

`app/Http/Requests/Akademik/StoreAsesmenRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\DataTransferObjects\AsesmenData;
use Illuminate\Foundation\Http\FormRequest;

final class StoreAsesmenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('asesmen.kelola');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'kelas_id' => ['required', 'integer'],
            'mata_pelajaran_id' => ['required', 'integer'],
            'semester_id' => ['required', 'integer'],
            'jenis' => ['required', 'in:sumatif_lingkup_materi,sumatif_akhir_semester,sumatif_akhir_jenjang'],
            'judul' => ['required', 'string', 'max:255'],
            'tanggal' => ['required', 'date'],
            'komponen_id' => ['required', 'array', 'min:1'],
            'komponen_id.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'komponen_id.required' => 'Pilih minimal satu Tujuan Pembelajaran.',
            'komponen_id.min' => 'Pilih minimal satu Tujuan Pembelajaran.',
        ];
    }

    public function toDTO(): AsesmenData
    {
        return AsesmenData::fromArray($this->validated());
    }
}
```

- [ ] **Step 6: Buat `UpdateNilaiSiswaRequest`**

`app/Http/Requests/Akademik/UpdateNilaiSiswaRequest.php` — permission check saja di `authorize()`; ownership check (`authorizeMilikGuru`) tetap dipanggil terpisah di controller (persis seperti `show()`), supaya tidak ada logic ownership yang terduplikasi antara FormRequest dan controller:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\DataTransferObjects\NilaiSiswaBatchData;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateNilaiSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('asesmen.kelola');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'nilai' => ['required', 'array'],
            'nilai.*.*.nilai_angka' => ['nullable', 'integer', 'min:0', 'max:100'],
            'nilai.*.*.catatan' => ['nullable', 'string'],
        ];
    }

    public function toDTO(): NilaiSiswaBatchData
    {
        return NilaiSiswaBatchData::fromArray($this->validated());
    }
}
```

- [ ] **Step 7: Refactor `Guru\AsesmenController` — `store`, `updateNilai` jadi thin**

Di `app/Http/Controllers/Guru/AsesmenController.php`, tambahkan import:

```php
use App\Domains\Akademik\Actions\Penilaian\CreateAsesmenAction;
use App\Domains\Akademik\Actions\Penilaian\SimpanNilaiSiswaAction;
use App\Http\Requests\Akademik\StoreAsesmenRequest;
use App\Http\Requests\Akademik\UpdateNilaiSiswaRequest;
```

Hapus import `use Illuminate\Support\Facades\DB;` (tidak dipakai lagi setelah `store`/`updateNilai` pindah ke Action) — **kecuali** masih ada pemakaian `DB::` lain di file ini; cek dulu dengan `grep -n "DB::" app/Http/Controllers/Guru/AsesmenController.php` sebelum menghapus importnya.

Tambahkan constructor:

```php
    public function __construct(
        private readonly CreateAsesmenAction $createAsesmenAction,
        private readonly SimpanNilaiSiswaAction $simpanNilaiSiswaAction,
    ) {
    }
```

Ganti method `store()` (guard "mengajar kombinasi ini" TETAP di controller, persis perilaku asli):

```php
    public function store(StoreAsesmenRequest $request): RedirectResponse
    {
        $guru = $request->user()->guru;
        abort_if(! $guru, 403, 'Profil guru tidak ditemukan.');

        $data = $request->validated();
        $mengajarKombinasiIni = JadwalPelajaran::where('guru_id', $guru->id)
            ->where('kelas_id', $data['kelas_id'])
            ->where('mata_pelajaran_id', $data['mata_pelajaran_id'])
            ->where('semester_id', $data['semester_id'])
            ->exists();

        abort_unless($mengajarKombinasiIni, 403, 'Anda tidak mengajar kombinasi kelas dan mata pelajaran ini.');

        $asesmen = $this->createAsesmenAction->execute($guru, $request->toDTO());

        return redirect()->route('guru.asesmen.show', $asesmen)->with('status', 'Asesmen berhasil dibuat. Silakan masukkan nilai peserta didik.');
    }
```

Ganti method `updateNilai()`:

```php
    public function updateNilai(UpdateNilaiSiswaRequest $request, Asesmen $asesmen): RedirectResponse
    {
        $this->authorizeMilikGuru($asesmen);

        $this->simpanNilaiSiswaAction->execute($asesmen, $request->toDTO());

        return redirect()->route('guru.asesmen.show', $asesmen)->with('status', 'Nilai dan catatan asesmen berhasil disimpan.');
    }
```

`index()`, `create()`, `show()`, `authorizeMilikGuru()` TIDAK berubah.

- [ ] **Step 8: Jalankan test regresi**

```bash
php artisan test tests/Feature/Guru/AsesmenControllerTest.php
```

Expected: semua test PASS tanpa perubahan assertion.

- [ ] **Step 9: Commit**

```bash
git add app/Domains/Akademik/DataTransferObjects/AsesmenData.php app/Domains/Akademik/DataTransferObjects/NilaiSiswaBatchData.php app/Domains/Akademik/Actions/Penilaian/CreateAsesmenAction.php app/Domains/Akademik/Actions/Penilaian/SimpanNilaiSiswaAction.php app/Http/Requests/Akademik/StoreAsesmenRequest.php app/Http/Requests/Akademik/UpdateNilaiSiswaRequest.php app/Http/Controllers/Guru/AsesmenController.php
git commit -m "refactor(akademik): ekstrak Action Asesmen & Nilai Siswa"
```

---

### Task 4: Ekstrak `RaporCalculationService` dari `RaporController`

**Files:**
- Create: `app/Domains/Akademik/Services/RaporCalculationService.php`
- Create: `tests/Unit/Services/RaporCalculationServiceTest.php`
- Modify: `app/Http/Controllers/Admin/RaporController.php` (`hitungRekap` dihapus, dipanggil lewat service)
- Test: `tests/Feature/Admin/RaporControllerTest.php` (existing, TIDAK diubah isinya — regression net)

**Interfaces:**
- Consumes: `App\Domains\Akademik\Models\{Asesmen,KomponenPenilaian,NilaiSiswa}` (dari Task 1), `App\Models\{Kelas,Semester,Siswa}`.
- Produces: `RaporCalculationService::hitungRekapKelas(Kelas $kelas, Semester $semester): array` (bentuk return: `['siswaList' => Collection, 'mapelList' => Collection, 'rekapNilai' => array<int, array<int, float|null>>, 'classAvg' => float|null, 'highestScore' => float|null]`) — akan langsung dipakai Sub-Task 04b tanpa perubahan signature.

- [ ] **Step 1: Tulis test unit untuk `RaporCalculationService` (menulis dulu, sebelum service dibuat — harus FAIL dulu)**

`tests/Unit/Services/RaporCalculationServiceTest.php`:

```php
<?php

use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Services\RaporCalculationService;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('computes a weighted average per siswa per mapel using komponen bobot', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $komponenBerat = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'bobot' => 70]);
    $komponenRingan = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'bobot' => 30]);

    $asesmen = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenBerat->id, 'nilai_angka' => 80]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenRingan->id, 'nilai_angka' => 90]);

    $service = new RaporCalculationService();
    $rekap = $service->hitungRekapKelas($kelas, $semester);

    // (80*70 + 90*30) / 100 = 83.0
    expect($rekap['rekapNilai'][$siswa->id][$mapel->id])->toBe(83.0);
    expect($rekap['classAvg'])->toBe(83.0);
    expect($rekap['highestScore'])->toBe(83.0);
    expect($rekap['siswaList']->pluck('id')->all())->toBe([$siswa->id]);
    expect($rekap['mapelList']->pluck('id')->all())->toBe([$mapel->id]);
});

it('returns null score for a siswa with no nilai on that mapel', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswaTanpaNilai = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    Asesmen::factory()->create(['kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);

    $service = new RaporCalculationService();
    $rekap = $service->hitungRekapKelas($kelas, $semester);

    expect($rekap['rekapNilai'][$siswaTanpaNilai->id][$mapel->id])->toBeNull();
    expect($rekap['classAvg'])->toBeNull();
    expect($rekap['highestScore'])->toBeNull();
});

it('returns empty structure when kelas has no asesmen in the semester', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $service = new RaporCalculationService();
    $rekap = $service->hitungRekapKelas($kelas, $semester);

    expect($rekap['mapelList'])->toBeEmpty();
    expect($rekap['classAvg'])->toBeNull();
    expect($rekap['highestScore'])->toBeNull();
});
```

- [ ] **Step 2: Jalankan test, pastikan FAIL (class belum ada)**

```bash
php artisan test tests/Unit/Services/RaporCalculationServiceTest.php
```

Expected: FAIL dengan error `Class "App\Domains\Akademik\Services\RaporCalculationService" not found`.

- [ ] **Step 3: Buat `RaporCalculationService`**

`app/Domains/Akademik/Services/RaporCalculationService.php` — isi method persis logic `RaporController::hitungRekap()` yang ada sekarang, hanya dipindah & di-uppercase-kan jadi method publik dengan nama `hitungRekapKelas`, dan parameter `?Kelas $kelas, ?Semester $semester` yang bisa null dijadikan non-nullable (`Kelas $kelas, Semester $semester`) karena caller (`RaporController`) SELALU sudah memvalidasi keduanya non-null sebelum memanggil (lihat `index()`/`cetak()` lama yang selalu `abort_if(... === null, 404)` dulu):

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;

final class RaporCalculationService
{
    /**
     * @return array{siswaList: \Illuminate\Support\Collection, mapelList: \Illuminate\Support\Collection, rekapNilai: array<int, array<int, float|null>>, classAvg: float|null, highestScore: float|null}
     */
    public function hitungRekapKelas(Kelas $kelas, Semester $semester): array
    {
        $siswaList = Siswa::where('kelas_id', $kelas->id)->orderBy('nama_lengkap')->get();

        $asesmenList = Asesmen::where('kelas_id', $kelas->id)
            ->where('semester_id', $semester->id)
            ->with('mataPelajaran')
            ->get();

        $mapelList = $asesmenList->pluck('mataPelajaran')->unique('id')->sortBy('nama');
        $allNilai = NilaiSiswa::whereIn('asesmen_id', $asesmenList->pluck('id'))
            ->with('komponenPenilaian')
            ->get();

        $rekapNilai = [];
        foreach ($siswaList as $siswa) {
            $rekapNilai[$siswa->id] = [];
            foreach ($mapelList as $mapel) {
                $mapelAsesmenIds = $asesmenList->where('mata_pelajaran_id', $mapel->id)->pluck('id');
                $scores = $allNilai->whereIn('asesmen_id', $mapelAsesmenIds)
                    ->where('siswa_id', $siswa->id)
                    ->whereNotNull('nilai_angka');

                if ($scores->count() > 0) {
                    $totalWeight = 0;
                    $weightedSum = 0;
                    foreach ($scores as $item) {
                        $w = $item->komponenPenilaian && $item->komponenPenilaian->bobot > 0 ? (int) $item->komponenPenilaian->bobot : 1;
                        $weightedSum += ($item->nilai_angka * $w);
                        $totalWeight += $w;
                    }
                    $rekapNilai[$siswa->id][$mapel->id] = $totalWeight > 0 ? round($weightedSum / $totalWeight, 1) : null;
                } else {
                    $rekapNilai[$siswa->id][$mapel->id] = null;
                }
            }
        }

        $allScores = collect($rekapNilai)->flatMap(fn ($m) => collect($m)->filter(fn ($v) => $v !== null));

        return [
            'siswaList' => $siswaList,
            'mapelList' => $mapelList,
            'rekapNilai' => $rekapNilai,
            'classAvg' => $allScores->count() > 0 ? round($allScores->avg(), 1) : null,
            'highestScore' => $allScores->count() > 0 ? $allScores->max() : null,
        ];
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan PASS**

```bash
php artisan test tests/Unit/Services/RaporCalculationServiceTest.php
```

Expected: 3 test PASS.

- [ ] **Step 5: Refactor `Admin\RaporController` — panggil service, hapus `hitungRekap()` private method**

Di `app/Http/Controllers/Admin/RaporController.php`, tambahkan import:

```php
use App\Domains\Akademik\Services\RaporCalculationService;
```

Tambahkan constructor:

```php
    public function __construct(
        private readonly RaporCalculationService $raporCalculationService,
    ) {
    }
```

Di method `index()`, ganti baris:

```php
        $rekap = $this->hitungRekap($selectedKelas, $selectedSemester);
```

menjadi:

```php
        $rekap = ($selectedKelas && $selectedSemester)
            ? $this->raporCalculationService->hitungRekapKelas($selectedKelas, $selectedSemester)
            : $this->rekapKosong();
```

Di method `cetak()`, ganti baris:

```php
        $rekap = $this->hitungRekap($selectedKelas, $selectedSemester);
```

menjadi (di `cetak()`, `$selectedKelas`/`$selectedSemester` sudah pasti non-null karena sudah lolos `abort_if` di atasnya, jadi panggil langsung tanpa null-check):

```php
        $rekap = $this->raporCalculationService->hitungRekapKelas($selectedKelas, $selectedSemester);
```

Hapus seluruh method `private function hitungRekap(?Kelas $kelas, ?Semester $semester): array { ... }`, ganti dengan method kecil untuk kasus kelas/semester belum dipilih (perilaku identik dengan cabang awal `hitungRekap()` yang lama):

```php
    /**
     * @return array{siswaList: \Illuminate\Support\Collection, mapelList: \Illuminate\Support\Collection, rekapNilai: array<int, array<int, float|null>>, classAvg: float|null, highestScore: float|null}
     */
    private function rekapKosong(): array
    {
        return [
            'siswaList' => collect(),
            'mapelList' => collect(),
            'rekapNilai' => [],
            'classAvg' => null,
            'highestScore' => null,
        ];
    }
```

Import `App\Domains\Akademik\Models\Asesmen` dan `App\Domains\Akademik\Models\NilaiSiswa` yang sebelumnya dipakai `hitungRekap()` di controller ini sekarang TIDAK terpakai lagi di controller (dipindah ke service) — hapus kedua baris `use` tersebut dari `RaporController.php` kalau tidak ada pemakaian lain di file itu (cek dengan `grep -n "Asesmen::\|NilaiSiswa::" app/Http/Controllers/Admin/RaporController.php` dulu sebelum menghapus).

- [ ] **Step 6: Jalankan test regresi**

```bash
php artisan test tests/Feature/Admin/RaporControllerTest.php tests/Unit/Services/RaporCalculationServiceTest.php
```

Expected: semua PASS tanpa perubahan assertion di `RaporControllerTest.php`.

- [ ] **Step 7: Commit**

```bash
git add app/Domains/Akademik/Services/RaporCalculationService.php tests/Unit/Services/RaporCalculationServiceTest.php app/Http/Controllers/Admin/RaporController.php
git commit -m "refactor(akademik): ekstrak RaporCalculationService dari RaporController"
```

---

### Task 5: Audit Tenant-Scoping di Level Controller & Perbaikan

**Files:**
- Modify (kondisional, tergantung temuan audit): `app/Http/Controllers/Admin/KomponenPenilaianController.php`, `app/Http/Controllers/Guru/KomponenPenilaianController.php`, `app/Http/Controllers/Guru/AsesmenController.php`, `app/Http/Controllers/Admin/RaporController.php`, `app/Domains/Akademik/Actions/Penilaian/*.php`
- Create (kondisional): test regresi baru untuk gap yang ditemukan, ditaruh di file test yang relevan dari Task 2-4, atau file baru `tests/Feature/Admin/KomponenPenilaianTenantScopeTest.php` / `tests/Feature/Admin/RaporTenantScopeTest.php` kalau butuh skenario lintas-lembaga yang belum ada test-nya.

**Interfaces:**
- Consumes: seluruh Action/Controller dari Task 2-4.
- Produces: tidak ada interface baru — task ini murni verifikasi + perbaikan defensif.

- [ ] **Step 1: Baca ulang seluruh lookup manual (`::find`, `::where`) di 4 controller + 5 Action yang sudah dibuat**

Titik-titik yang WAJIB diperiksa satu per satu (semuanya sudah otomatis ter-scope oleh `TenantScope` selama modelnya pakai `BelongsToTenant` — cek untuk MASING-MASING titik ini apakah modelnya benar pakai trait itu, dan apakah ada `withoutGlobalScopes()` yang dipakai TANPA alasan yang eksplisit):

1. `Admin\KomponenPenilaianController::store()`/`update()` — `MataPelajaran::find($data['mata_pelajaran_id'])`, `Semester::find($data['semester_id'])`. Cek: `MataPelajaran` dan `Semester` sama-sama pakai `BelongsToTenant`? (`grep -n "BelongsToTenant" app/Models/MataPelajaran.php app/Models/Semester.php`)
2. `UpdateKomponenPenilaianAction::execute()` route-model-binding `KomponenPenilaian $komponenPenilaian` — Laravel route-model-binding otomatis scoped kalau modelnya pakai global scope. Cek route binding TIDAK memakai `withoutGlobalScopes()` di mana pun.
3. `Guru\AsesmenController::store()` — `JadwalPelajaran::where('guru_id', $guru->id)->where('kelas_id', ...)->...->exists()`. `$guru` sendiri berasal dari `$request->user()->guru` (session user, sudah pasti tenant sendiri), tapi `kelas_id`/`mata_pelajaran_id`/`semester_id` dari INPUT USER — kalau attacker submit `kelas_id` milik lembaga lain, apakah query ini otomatis gagal (karena `JadwalPelajaran` milik guru sendiri tidak mungkin match `kelas_id` lembaga lain), atau apakah ada celah? Analisis: `JadwalPelajaran::where('guru_id', $guru->id)` sudah membatasi ke jadwal milik guru ini SENDIRI (guru cuma py jadwal di lembaga sendiri), jadi kombinasi kelas_id/mapel/semester yang match otomatis dijamin satu lembaga dengan guru — **kemungkinan besar aman**, tapi verifikasi dengan test eksplisit di Step 3.
4. `Guru\AsesmenController::updateNilai()` / `SimpanNilaiSiswaAction` — `$asesmen->kelas->siswa()->pluck('id')` dan `$asesmen->komponenPenilaian()->pluck(...)` — keduanya relasi dari `$asesmen` yang sudah lolos `authorizeMilikGuru()` (guru_id cocok), jadi kelas/komponen yang diturunkan otomatis satu lembaga. **Kemungkinan aman.**
5. `Admin\RaporController::index()`/`cetak()` — `Kelas::find($kelasId)`, `Semester::find($semesterId)`, `TahunAjaran::where('status_aktif', true)->value('id')`. Cek: `TahunAjaran::where('status_aktif', true)` TANPA filter `lembaga_id` eksplisit — apakah `TenantScope` otomatis menambahkan filter lembaga di query ini (karena `TahunAjaran` pakai `BelongsToTenant`), atau apakah ini bisa mengambil tahun ajaran aktif milik lembaga LAIN kalau aktor adalah admin dengan scope yayasan (`widestScopeLevel()`)? Baca `app/Models/Scopes/TenantScope.php` untuk pahami persis bagaimana scope yayasan-wide bekerja di sini.
6. `RaporCalculationService::hitungRekapKelas()` — menerima `Kelas $kelas, Semester $semester` langsung sebagai objek (bukan ID) dari controller yang sudah scoped — **tidak perlu re-check di sini**, tapi WAJIB pastikan pemanggil (`RaporController`) tidak pernah mengirim kombinasi `$kelas`/`$semester` yang berasal dari lembaga berbeda satu sama lain (mirip celah yang sudah diperbaiki di `KomponenPenilaian` create/update) — cek apakah `RaporController::index()`/`cetak()` sudah memvalidasi `$selectedKelas->tahun_ajaran_id === $selectedSemester->tahun_ajaran_id` atau `lembaga_id` yang sama. Baca kode `cetak()` yang sudah ada — ada baris `abort_if($selectedSemester->tahun_ajaran_id !== $selectedKelas->tahun_ajaran_id, 404);` di `cetak()`, tapi **`index()` TIDAK punya pengecekan yang sama** — ini kandidat gap nyata, verifikasi dengan test di Step 3.

- [ ] **Step 2: Baca `app/Models/Scopes/TenantScope.php` untuk memahami perilaku yayasan-wide scope**

```bash
cat app/Models/Scopes/TenantScope.php
```

Pahami: kapan scope memfilter by `lembaga_id` tunggal vs kapan memfilter by seluruh lembaga dalam satu yayasan (`session('active_lembaga_id')` vs `actingUser->lembaga_id`, per catatan arsitektur yang sudah diketahui dari sub-task sebelumnya). Ini menentukan apakah temuan #5 dan #6 di Step 1 benar-benar gap atau sebenarnya sudah aman by design (mis. kalau admin memang boleh melihat rekap lintas-lembaga dalam yayasan yang sama, itu bukan bug — tapi kalau `Kelas` dari lembaga X bisa dipasangkan dengan `Semester` dari lembaga Y dalam yayasan yang sama lalu menghasilkan data campur-aduk yang salah secara bisnis, itu bug).

- [ ] **Step 3: Tulis test untuk `index()` RaporController tanpa validasi kelas/semester cross-lembaga (kandidat gap dari Step 1.6)**

Tambahkan ke `tests/Feature/Admin/RaporControllerTest.php` (baca dulu isi file test ini untuk tahu helper `actingAsRaporViewer()` atau sejenisnya yang sudah ada, lalu ikuti pola yang sama):

```php
it('does not mix a kelas and semester from different lembaga within the same yayasan on the index recap', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaranA->id]);

    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);

    Permission::firstOrCreate(['name' => 'rapor.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_yayasan_rapor', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['rapor.view']);
    $manager = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $manager->assignRole($role);

    $response = $this->actingAs($manager)->get(route('admin.rapor.index', [
        'kelas_id' => $kelasA->id,
        'semester_id' => $semesterB->id,
    ]));

    $response->assertOk();
    $response->assertViewHas('rekapNilai', fn ($rekap) => $rekap === []);
});
```

- [ ] **Step 4: Jalankan test baru, amati hasil**

```bash
php artisan test --filter="does not mix a kelas and semester"
```

Kalau test ini **FAIL** (artinya `index()` memang menghasilkan rekap tidak-kosong walau kelas & semester beda lembaga — gap dikonfirmasi nyata): lanjut ke Step 5 untuk memperbaiki. Kalau test ini **PASS** begitu saja (artinya `TenantScope`/logic lain sudah mencegahnya, mis. karena `KomponenPenilaian`/`Asesmen` di semester B tidak akan pernah match `kelas_id` dari lembaga A jadi `rekapNilai` otomatis kosong meski tidak ada guard eksplisit): tetap simpan test ini sebagai regression net permanen (bukti bahwa perilaku ini sudah benar dan terjaga), lanjut ke Step 6 tanpa perubahan controller.

- [ ] **Step 5: (Hanya jika Step 4 FAIL) Perbaiki `RaporController::index()` — tambahkan guard yang sama seperti `cetak()`**

Di method `index()`, setelah baris `$selectedSemester = $semesterId ? Semester::find($semesterId) : null;`, tambahkan:

```php
        if ($selectedKelas && $selectedSemester && $selectedSemester->tahun_ajaran_id !== $selectedKelas->tahun_ajaran_id) {
            $selectedSemester = null;
        }
```

Ini membuat kombinasi kelas/semester yang tidak nyambung otomatis diperlakukan sebagai "semester belum dipilih" (rekap kosong) — konsisten dengan bagaimana `kelasId`/`semesterId` yang tidak valid sudah ditangani di baris-baris sekitarnya (fallback ke default), bukan `abort(404)` seperti di `cetak()`, karena `index()` adalah halaman filter interaktif (nilai tidak nyambung seharusnya menampilkan rekap kosong, bukan mem-block seluruh halaman).

Jalankan ulang test Step 3, pastikan sekarang PASS.

- [ ] **Step 6: Jalankan seluruh test yang tersentuh Task 2-5 sebagai regresi gabungan**

```bash
php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php tests/Feature/Admin/RaporControllerTest.php tests/Feature/Guru/KomponenPenilaianControllerTest.php tests/Feature/Guru/AsesmenControllerTest.php tests/Feature/AkademikTenantScopeTest.php tests/Unit/Services/RaporCalculationServiceTest.php
```

Expected: semua PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "test(akademik): audit tenant-scoping level controller untuk modul penilaian & rapor"
```

(Kalau Step 4 PASS tanpa perubahan controller, commit ini hanya berisi file test baru. Kalau Step 5 dijalankan, commit ini juga berisi perbaikan `RaporController.php`.)

---

### Task 6: Verifikasi Akhir & Handoff

**Files:**
- Create: `.agents/logs/2026-08-19-1015-akademik-04a-migrasi-komponen-penilaian-rapor.md`
- Modify: `.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md` (update baris status 04a di tabel navigasi jadi SELESAI, tambahkan baris plan-file yang sebelumnya kosong)

**Interfaces:**
- Consumes: hasil seluruh Task 1-5.
- Produces: tidak ada — task penutup.

- [ ] **Step 1: Tanyakan ke user apakah mau menjalankan full test suite**

Sebelum menjalankan `php artisan test` tanpa filter, tanyakan dulu ke user (lihat Global Constraints) — hanya jalankan kalau user menyetujui.

- [ ] **Step 2: (Jika disetujui) Jalankan full suite**

```bash
php artisan test
```

Expected: 0 failed. Kalau ada failure yang TIDAK terkait file yang disentuh plan ini (pre-existing flaky test, sudah pernah terjadi di sub-task sebelumnya karena `KelasFactory` numerik acak) — re-run test yang gagal secara terisolasi 2-3x untuk pastikan itu flaky lama, bukan regresi baru, sebelum menyimpulkan aman.

- [ ] **Step 3: Update tabel navigasi master plan**

Di `.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md`, cari baris:

```
| **04** | **Adaptive E-Rapor Engine** | `.agents/specs/akademik-04-e-rapor.md` | `.agents/plans/akademik-04-e-rapor.md` | `.agents/logs/akademik-04-e-rapor.md` | ⚪ PENDING |
```

Ganti jadi dua baris (04a selesai, 04b menyusul — placeholder path 04b diisi nanti saat sub-task itu dibrainstorm, jangan diisi sekarang):

```
| **04a** | **Migrasi Komponen Penilaian, Asesmen, Nilai Siswa & Rapor ke Domain Akademik** | [`.agents/specs/2026-08-19-1015-akademik-04a-migrasi-komponen-penilaian-rapor.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-19-1015-akademik-04a-migrasi-komponen-penilaian-rapor.md) | [`.agents/plans/2026-08-19-1015-akademik-04a-migrasi-komponen-penilaian-rapor.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-19-1015-akademik-04a-migrasi-komponen-penilaian-rapor.md) | [`.agents/logs/2026-08-19-1015-akademik-04a-migrasi-komponen-penilaian-rapor.md`](file:///d:/laragon/www/pintera-app/.agents/logs/2026-08-19-1015-akademik-04a-migrasi-komponen-penilaian-rapor.md) | 🟢 **SELESAI (COMPLETED)** |
| **04b** | **Adaptive E-Rapor Engine (narasi TP, approval workflow, PDF berjenjang)** | `.agents/specs/akademik-04b-e-rapor.md` | `.agents/plans/akademik-04b-e-rapor.md` | `.agents/logs/akademik-04b-e-rapor.md` | ⚪ PENDING |
```

- [ ] **Step 4: Tulis handoff log**

`.agents/logs/2026-08-19-1015-akademik-04a-migrasi-komponen-penilaian-rapor.md` — isi dengan ringkasan: apa yang dikerjakan per task, hasil test tiap task, temuan audit tenant-scoping Task 5 (gap ditemukan/tidak, dan perbaikan apa kalau ada), commit hash tiap task, dan status akhir. Format bebas mengikuti gaya `.agents/logs/2026-08-18-1651-akademik-03a-migrasi-jurnal-presensi.md` sebagai referensi (baca file itu dulu untuk contoh struktur).

- [ ] **Step 5: Commit**

```bash
git add .agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md .agents/logs/2026-08-19-1015-akademik-04a-migrasi-komponen-penilaian-rapor.md
git commit -m "docs(akademik): tutup Sub-Task 04a, update master plan & handoff log"
```
