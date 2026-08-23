# Serap Model Data Induk Sempit ke Domain Pemiliknya — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memindahkan `JenisKaryawanMaster` + `JabatanTambahanMaster` ke `app/Domains/Sdm/Models/`, dan `MataPelajaran` ke `app/Domains/Akademik/Models/`, beserta controller (direfactor jadi Action/DTO, namespace pindah ke `Lembaga\Sdm\`/`Lembaga\Akademik\`) dan view (pindah ke `portals/lembaga/sdm/`/`portals/lembaga/akademik/`) — tanpa mengubah perilaku aplikasi.

**Architecture:** Model pindah fisik → domain baru (hanya `$fillable`/`casts()`/relationship, tidak ada business logic). Controller lama diekstrak jadi Action (1 use-case per class) + DTO, dipanggil dari controller thin di namespace baru. Route name & path TIDAK berubah — hanya `use` statement controller di file route yang diupdate.

**Tech Stack:** Laravel 12, Pest.

## Global Constraints

- **Zero-behavior-change** — pesan error, kode status HTTP, urutan validasi, format respons JSON/redirect HARUS identik kata-per-kata dengan sebelum migrasi. Kalau ditemukan celah/inkonsistensi di kode lama, JANGAN diperbaiki diam-diam — laporkan ke user sebagai keputusan terpisah.
- Route NAME dan route PATH tidak berubah sama sekali. Hanya baris `use App\Http\Controllers\Admin\{X}Controller;` di file route yang diganti ke namespace baru.
- Model pindahan HANYA berisi `$fillable`, `casts()`, relationship — TIDAK ADA method business logic.
- `newFactory()` WAJIB ditambahkan untuk model yang pakai `HasFactory` (`JenisKaryawanMaster`, `MataPelajaran`). `JabatanTambahanMaster` TIDAK pakai `HasFactory` sama sekali — JANGAN ditambahkan (bukan celah untuk "sekalian dibenerin").
- Referensi lintas-namespace pakai **FQCN inline** di method relationship, BUKAN `use` statement tambahan di file yang TETAP di `app/Models/`.
- Baseline kode yang dikutip plan ini: commit `31a03ab` di branch `refactor-v1`. Kalau isi file yang kamu baca BEDA signifikan dari yang dikutip plan (bukan cuma beda baris), STOP, jangan menebak — laporkan ke user.
- Tiap task: jalankan test SCOPED dulu SEBELUM commit. Full suite HANYA di task terakhir, dan HARUS izin eksplisit user dulu.

---

## Task 1: Pindahkan Model `JenisKaryawanMaster`

**Files:**
- Move: `app/Models/JenisKaryawanMaster.php` → `app/Domains/Sdm/Models/JenisKaryawanMaster.php`
- Modify (22 file — 21 hasil grep `use App\Models\JenisKaryawanMaster;` + 1 gotcha referensi implisit):
  - `app/Http/Controllers/Admin/AttendanceConfigurationController.php`
  - `tests/Feature/Sdm/KuotaCutiConfigTest.php`
  - `app/Domains/Sdm/Models/KuotaCutiConfig.php`
  - `tests/Unit/Services/AttendancePolicyResolverTest.php`
  - `tests/Feature/Sdm/AttendancePolicyModelTest.php`
  - `tests/Feature/Admin/AttendancePolicyControllerTest.php`
  - `app/Domains/Sdm/Models/AttendancePolicy.php`
  - `tests/Unit/Services/KonselorAllocationResolverTest.php`
  - `tests/Feature/KasusEvaluasiTest.php`
  - `tests/Feature/Admin/KaryawanCrudTest.php`
  - `tests/Feature/Admin/JenisKaryawanMasterCrudTest.php`
  - `database/seeders/OrangTuaKaryawanSeeder.php`
  - `app/Http/Controllers/Admin/KaryawanController.php`
  - `tests/Feature/KaryawanDashboardTest.php`
  - `tests/Unit/JenisKaryawanMasterSeederTest.php`
  - `database/seeders/JenisKaryawanMasterSeeder.php`
  - `database/factories/JenisKaryawanMasterFactory.php`
  - `app/Http/Controllers/Admin/JenisKaryawanMasterController.php`
  - `tests/Unit/Services/AkunKaryawanGeneratorTest.php`
  - `database/factories/KaryawanFactory.php`
  - `tests/Feature/KaryawanSchemaTest.php`
  - `app/Models/Karyawan.php` (gotcha: referensi implisit tanpa `use`, baris 59)

**Interfaces:**
- Produces: `App\Domains\Sdm\Models\JenisKaryawanMaster` — dipakai Task 4.

- [ ] **Step 1: Pindahkan file fisik**

```bash
git mv app/Models/JenisKaryawanMaster.php app/Domains/Sdm/Models/JenisKaryawanMaster.php
```

- [ ] **Step 2: Ubah isi file — namespace + `newFactory()`**

Timpa seluruh isi `app/Domains/Sdm/Models/JenisKaryawanMaster.php` dengan:

```php
<?php

namespace App\Domains\Sdm\Models;

use App\Models\Karyawan;
use Database\Factories\JenisKaryawanMasterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisKaryawanMaster extends Model
{
    use HasFactory;

    protected static function newFactory(): JenisKaryawanMasterFactory
    {
        return JenisKaryawanMasterFactory::new();
    }

    protected $table = 'jenis_karyawan_master';

    protected $fillable = ['nama', 'is_konselor'];

    protected function casts(): array
    {
        return [
            'is_konselor' => 'boolean',
        ];
    }

    public function karyawan(): HasMany
    {
        return $this->hasMany(Karyawan::class, 'jenis_karyawan_id');
    }
}
```

- [ ] **Step 3: Update `database/factories/JenisKaryawanMasterFactory.php`**

Ganti baris `use App\Models\JenisKaryawanMaster;` menjadi `use App\Domains\Sdm\Models\JenisKaryawanMaster;`. Tidak ada perubahan lain di file ini.

- [ ] **Step 4: Update 20 file consumer lain (SELAIN Factory yang sudah di Step 3, dan SELAIN `Karyawan.php` yang beda pola di Step 5)**

Di SETIAP file berikut, cari baris persis `use App\Models\JenisKaryawanMaster;` dan ganti dengan `use App\Domains\Sdm\Models\JenisKaryawanMaster;`. Tidak ada perubahan lain di file-file ini:

```
app/Http/Controllers/Admin/AttendanceConfigurationController.php
tests/Feature/Sdm/KuotaCutiConfigTest.php
app/Domains/Sdm/Models/KuotaCutiConfig.php
tests/Unit/Services/AttendancePolicyResolverTest.php
tests/Feature/Sdm/AttendancePolicyModelTest.php
tests/Feature/Admin/AttendancePolicyControllerTest.php
app/Domains/Sdm/Models/AttendancePolicy.php
tests/Unit/Services/KonselorAllocationResolverTest.php
tests/Feature/KasusEvaluasiTest.php
tests/Feature/Admin/KaryawanCrudTest.php
tests/Feature/Admin/JenisKaryawanMasterCrudTest.php
database/seeders/OrangTuaKaryawanSeeder.php
app/Http/Controllers/Admin/KaryawanController.php
tests/Feature/KaryawanDashboardTest.php
tests/Unit/JenisKaryawanMasterSeederTest.php
database/seeders/JenisKaryawanMasterSeeder.php
app/Http/Controllers/Admin/JenisKaryawanMasterController.php
tests/Unit/Services/AkunKaryawanGeneratorTest.php
database/factories/KaryawanFactory.php
tests/Feature/KaryawanSchemaTest.php
```

- [ ] **Step 5: Perbaiki gotcha referensi implisit di `app/Models/Karyawan.php`**

Baca `app/Models/Karyawan.php`, cari baris 59 (persis):
```php
        return $this->belongsTo(JenisKaryawanMaster::class, 'jenis_karyawan_id');
```
Ganti jadi (FQCN inline, BUKAN `use` statement tambahan — `Karyawan.php` tetap di `App\Models`):
```php
        return $this->belongsTo(\App\Domains\Sdm\Models\JenisKaryawanMaster::class, 'jenis_karyawan_id');
```

- [ ] **Step 6: Verifikasi tidak ada yang kelewat**

```bash
grep -rln "use App\\\\Models\\\\JenisKaryawanMaster;" --include="*.php" app database tests
```
Expected: kosong (tidak ada output).

```bash
grep -rn "JenisKaryawanMaster::class" --include="*.php" app/Models
```
Expected: kosong (tidak ada output — kalau masih ada berarti Step 5 belum lengkap).

- [ ] **Step 7: Jalankan test scoped**

```bash
php artisan test tests/Feature/Sdm/KuotaCutiConfigTest.php tests/Unit/Services/AttendancePolicyResolverTest.php tests/Feature/Sdm/AttendancePolicyModelTest.php tests/Feature/Admin/AttendancePolicyControllerTest.php tests/Unit/Services/KonselorAllocationResolverTest.php tests/Feature/KasusEvaluasiTest.php tests/Feature/Admin/KaryawanCrudTest.php tests/Feature/Admin/JenisKaryawanMasterCrudTest.php tests/Feature/KaryawanDashboardTest.php tests/Unit/JenisKaryawanMasterSeederTest.php tests/Unit/Services/AkunKaryawanGeneratorTest.php tests/Feature/KaryawanSchemaTest.php
```
Expected: semua PASS, 0 failed, 0 error (baseline sebelum migrasi: minimal 10 test lulus dari `JenisKaryawanMasterCrudTest`+`JenisKaryawanMasterSeederTest` saja — jumlah pasti tercatat di output, tidak boleh berkurang).

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "refactor(sdm): pindah model JenisKaryawanMaster ke Domains\Sdm\Models, update 22 file consumer"
```

---

## Task 2: Pindahkan Model `JabatanTambahanMaster`

**Files:**
- Move: `app/Models/JabatanTambahanMaster.php` → `app/Domains/Sdm/Models/JabatanTambahanMaster.php`
- Modify (8 file — 7 hasil grep + 1 gotcha referensi implisit):
  - `database/seeders/GuruJabatanTambahanSeeder.php`
  - `app/Http/Controllers/Admin/GuruController.php`
  - `tests/Feature/Admin/JabatanTambahanMasterCrudTest.php`
  - `app/Http/Controllers/Admin/JabatanTambahanMasterController.php`
  - `tests/Feature/Admin/GuruRelationalProfileTest.php`
  - `database/seeders/JabatanTambahanMasterSeeder.php`
  - `tests/Unit/JabatanTambahanTest.php`
  - `app/Models/Guru.php` (gotcha: referensi implisit tanpa `use`, baris 76)

**Interfaces:**
- Produces: `App\Domains\Sdm\Models\JabatanTambahanMaster` — dipakai Task 5.

- [ ] **Step 1: Pindahkan file fisik**

```bash
git mv app/Models/JabatanTambahanMaster.php app/Domains/Sdm/Models/JabatanTambahanMaster.php
```

- [ ] **Step 2: Ubah isi file — namespace (TANPA `newFactory()`, model ini tidak pakai `HasFactory`)**

Timpa seluruh isi `app/Domains/Sdm/Models/JabatanTambahanMaster.php` dengan:

```php
<?php

namespace App\Domains\Sdm\Models;

use App\Models\Guru;
use App\Models\GuruJabatanTambahan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class JabatanTambahanMaster extends Model
{
    protected $table = 'jabatan_tambahan_master';

    protected $fillable = ['nama', 'kelompok'];

    public function guru(): BelongsToMany
    {
        return $this->belongsToMany(Guru::class, 'guru_jabatan_tambahan')
            ->withPivot(['mulai_periode', 'akhir_periode', 'no_sk'])
            ->withTimestamps()
            ->using(GuruJabatanTambahan::class);
    }
}
```

Catatan: `GuruJabatanTambahan` (Pivot, TETAP di `App\Models`) sekarang jadi referensi lintas-namespace dari model ini — sudah ditambahkan sebagai `use` statement biasa (BUKAN FQCN inline) karena aturan FQCN-inline hanya berlaku untuk file yang TETAP di `app/Models/` mereferensikan model yang PINDAH (supaya tidak nambah `use` baru di file lama) — untuk model yang BARU pindah sendiri, `use` statement normal tetap dipakai seperti biasa.

- [ ] **Step 3: Update 6 file consumer lain**

Di SETIAP file berikut, cari baris persis `use App\Models\JabatanTambahanMaster;` dan ganti dengan `use App\Domains\Sdm\Models\JabatanTambahanMaster;`. Tidak ada perubahan lain di file-file ini:

```
database/seeders/GuruJabatanTambahanSeeder.php
app/Http/Controllers/Admin/GuruController.php
tests/Feature/Admin/JabatanTambahanMasterCrudTest.php
app/Http/Controllers/Admin/JabatanTambahanMasterController.php
tests/Feature/Admin/GuruRelationalProfileTest.php
database/seeders/JabatanTambahanMasterSeeder.php
tests/Unit/JabatanTambahanTest.php
```

- [ ] **Step 4: Perbaiki gotcha referensi implisit di `app/Models/Guru.php`**

Baca `app/Models/Guru.php`, cari baris 76 (persis):
```php
        return $this->belongsToMany(JabatanTambahanMaster::class, 'guru_jabatan_tambahan')
```
Ganti jadi (FQCN inline, `Guru.php` tetap di `App\Models`):
```php
        return $this->belongsToMany(\App\Domains\Sdm\Models\JabatanTambahanMaster::class, 'guru_jabatan_tambahan')
```

- [ ] **Step 5: Verifikasi tidak ada yang kelewat**

```bash
grep -rln "use App\\\\Models\\\\JabatanTambahanMaster;" --include="*.php" app database tests
```
Expected: kosong.

```bash
grep -rn "JabatanTambahanMaster::class" --include="*.php" app/Models
```
Expected: kosong.

- [ ] **Step 6: Jalankan test scoped**

```bash
php artisan test tests/Feature/Admin/JabatanTambahanMasterCrudTest.php tests/Feature/Admin/GuruRelationalProfileTest.php tests/Unit/JabatanTambahanTest.php
```
Expected: semua PASS, 0 failed, 0 error.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "refactor(sdm): pindah model JabatanTambahanMaster ke Domains\Sdm\Models, update 8 file consumer"
```

---

## Task 3: Pindahkan Model `MataPelajaran`

**Files:**
- Move: `app/Models/MataPelajaran.php` → `app/Domains/Akademik/Models/MataPelajaran.php`
- Modify (49 file — 48 hasil grep + 1 gotcha referensi implisit):
  - (daftar 48 file di bawah)
  - `app/Models/JadwalPelajaran.php` (gotcha: referensi implisit tanpa `use`, baris 51)

**Interfaces:**
- Produces: `App\Domains\Akademik\Models\MataPelajaran` — dipakai Task 6.

- [ ] **Step 1: Pindahkan file fisik**

```bash
git mv app/Models/MataPelajaran.php app/Domains/Akademik/Models/MataPelajaran.php
```

- [ ] **Step 2: Ubah isi file — namespace + `newFactory()`**

Timpa seluruh isi `app/Domains/Akademik/Models/MataPelajaran.php` dengan:

```php
<?php

namespace App\Domains\Akademik\Models;

use App\Enums\KelompokMataPelajaran;
use App\Enums\StatusMataPelajaran;
use App\Enums\TipeMataPelajaran;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use Database\Factories\MataPelajaranFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MataPelajaran extends Model
{
    use HasFactory, BelongsToTenant;

    protected static function newFactory(): MataPelajaranFactory
    {
        return MataPelajaranFactory::new();
    }

    protected $table = 'mata_pelajaran';

    protected $fillable = [
        'lembaga_id',
        'kode',
        'nama',
        'no_urut',
        'tipe',
        'kelompok',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tipe' => TipeMataPelajaran::class,
            'kelompok' => KelompokMataPelajaran::class,
            'status' => StatusMataPelajaran::class,
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }
}
```

- [ ] **Step 3: Update `database/factories/MataPelajaranFactory.php`**

Ganti baris `use App\Models\MataPelajaran;` menjadi `use App\Domains\Akademik\Models\MataPelajaran;`. Tidak ada perubahan lain di file ini.

- [ ] **Step 4: Update 47 file consumer lain (SELAIN Factory di Step 3, SELAIN `JadwalPelajaran.php` di Step 5)**

Di SETIAP file berikut, cari baris persis `use App\Models\MataPelajaran;` dan ganti dengan `use App\Domains\Akademik\Models\MataPelajaran;`. Tidak ada perubahan lain di file-file ini:

```
tests/Feature/Guru/JurnalKbmTenantScopeTest.php
tests/Unit/Services/SesiPembelajaranGeneratorTest.php
tests/Unit/Services/RaporCalculationServiceTest.php
tests/Unit/Models/NilaiSiswaTest.php
tests/Unit/Models/JadwalPelajaranTest.php
tests/Unit/Models/AsesmenTest.php
tests/Unit/KomponenPenilaianSeederTest.php
tests/Unit/Domains/Sarpras/SarprasModelsTest.php
tests/Unit/Domains/Sarpras/GedungRuanganActionTest.php
tests/Feature/Guru/KomponenPenilaianControllerTest.php
tests/Feature/Guru/JurnalKbmControllerTest.php
tests/Feature/Guru/AsesmenControllerTest.php
tests/Feature/AkademikTenantScopeTest.php
tests/Feature/Akademik/RaporPdfDataBuilderTest.php
tests/Feature/Akademik/RppWorkflowTest.php
tests/Feature/Akademik/RaporApprovalActionsTest.php
tests/Feature/Akademik/JurnalKbmAdaptiveTest.php
tests/Feature/Akademik/JadwalSarprasCollisionTest.php
tests/Feature/Akademik/CapaianKompetensiGeneratorTest.php
tests/Feature/Akademik/GenerateNarasiPerkembanganActionTest.php
tests/Feature/Admin/RaporControllerTest.php
tests/Feature/Admin/KomponenPenilaianCrudTest.php
tests/Feature/Admin/KenaikanKelasControllerTest.php
tests/Feature/Admin/JadwalPelajaranCrudTest.php
database/seeders/SesiPembelajaranSeeder.php
database/seeders/NilaiSiswaSeeder.php
database/seeders/KomponenPenilaianSeeder.php
database/seeders/JadwalPelajaranSeeder.php
database/seeders/AsesmenSeeder.php
database/factories/KomponenPenilaianFactory.php
database/factories/JadwalPelajaranFactory.php
database/factories/AsesmenFactory.php
app/Http/Controllers/Guru/KomponenPenilaianController.php
app/Http/Controllers/Guru/AsesmenController.php
app/Http/Controllers/Admin/RppController.php
app/Http/Controllers/Admin/KomponenPenilaianController.php
app/Http/Controllers/Admin/JadwalPelajaranController.php
app/Domains/Akademik/Services/CapaianKompetensiGenerator.php
app/Domains/Akademik/Models/Rpp.php
app/Domains/Akademik/Models/SesiPembelajaran.php
app/Domains/Akademik/Models/KomponenPenilaian.php
app/Domains/Akademik/Models/Asesmen.php
app/Http/Controllers/Admin/MataPelajaranController.php
tests/Feature/Admin/MataPelajaranCrudTest.php
database/seeders/MataPelajaranSeeder.php
tests/Unit/MataPelajaranSeederTest.php
tests/Unit/Models/MataPelajaranTest.php
```

- [ ] **Step 5: Perbaiki gotcha referensi implisit di `app/Models/JadwalPelajaran.php`**

Baca `app/Models/JadwalPelajaran.php`, cari baris 51 (persis):
```php
        return $this->belongsTo(MataPelajaran::class);
```
Ganti jadi (FQCN inline, `JadwalPelajaran.php` tetap di `App\Models`):
```php
        return $this->belongsTo(\App\Domains\Akademik\Models\MataPelajaran::class);
```

- [ ] **Step 6: Verifikasi tidak ada yang kelewat**

```bash
grep -rln "use App\\\\Models\\\\MataPelajaran;" --include="*.php" app database tests
```
Expected: kosong.

```bash
grep -rn "MataPelajaran::class" --include="*.php" app/Models
```
Expected: kosong.

- [ ] **Step 7: Jalankan test scoped luas**

```bash
php artisan test tests/Feature/Guru tests/Feature/Akademik tests/Feature/Admin/RaporControllerTest.php tests/Feature/Admin/KomponenPenilaianCrudTest.php tests/Feature/Admin/KenaikanKelasControllerTest.php tests/Feature/Admin/JadwalPelajaranCrudTest.php tests/Feature/Admin/MataPelajaranCrudTest.php tests/Feature/AkademikTenantScopeTest.php tests/Unit/Services/SesiPembelajaranGeneratorTest.php tests/Unit/Services/RaporCalculationServiceTest.php tests/Unit/Models tests/Unit/KomponenPenilaianSeederTest.php tests/Unit/Domains/Sarpras tests/Unit/MataPelajaranSeederTest.php
```
Expected: semua PASS, 0 failed, 0 error.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "refactor(akademik): pindah model MataPelajaran ke Domains\Akademik\Models, update 49 file consumer"
```

---

## Task 4: Refactor `JenisKaryawanMasterController` — Action/DTO + Namespace + View

**Files:**
- Create: `app/Domains/Sdm/DataTransferObjects/JenisKaryawanMasterData.php`
- Create: `app/Domains/Sdm/Actions/JenisKaryawan/CreateJenisKaryawanAction.php`
- Create: `app/Domains/Sdm/Actions/JenisKaryawan/UpdateJenisKaryawanAction.php`
- Create: `app/Domains/Sdm/Actions/JenisKaryawan/DeleteJenisKaryawanAction.php`
- Create: `app/Http/Controllers/Lembaga/Sdm/JenisKaryawanMasterController.php`
- Delete: `app/Http/Controllers/Admin/JenisKaryawanMasterController.php`
- Move: `resources/views/admin/jenis-karyawan-master/index.blade.php` → `resources/views/portals/lembaga/sdm/jenis-karyawan-master/index.blade.php`
- Modify: `routes/admin/guru-data.php`
- Test: `tests/Feature/Admin/JenisKaryawanMasterCrudTest.php` (sudah ada, TIDAK diubah isi test-nya — cuma harus tetap lulus)

**Interfaces:**
- Consumes: `App\Domains\Sdm\Models\JenisKaryawanMaster` (Task 1).

Isi controller SAAT INI (baseline, `app/Http/Controllers/Admin/JenisKaryawanMasterController.php` — baca dulu untuk konfirmasi sebelum edit):

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\JenisKaryawanMaster;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JenisKaryawanMasterController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('jenis-karyawan-master.view');

        $jenisList = JenisKaryawanMaster::withCount('karyawan')->orderBy('nama')->get();

        if ($request->wantsJson()) {
            return response()->json(['items' => $jenisList]);
        }

        return view('admin.jenis-karyawan-master.index', [
            'jenisList' => $jenisList,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('jenis-karyawan-master.create');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', 'unique:jenis_karyawan_master,nama'],
        ]);

        $item = JenisKaryawanMaster::create($data)->loadCount('karyawan');

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Jenis karyawan berhasil ditambahkan.',
                'item' => $item,
            ], 201);
        }

        return back()->with('success', 'Jenis karyawan berhasil ditambahkan.');
    }

    public function update(Request $request, JenisKaryawanMaster $jenisKaryawanMaster): JsonResponse|RedirectResponse
    {
        $this->authorize('jenis-karyawan-master.edit');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('jenis_karyawan_master', 'nama')->ignore($jenisKaryawanMaster->id)],
        ]);

        $jenisKaryawanMaster->update($data);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Jenis karyawan berhasil diperbarui.',
                'item' => $jenisKaryawanMaster->fresh()->loadCount('karyawan'),
            ], 200);
        }

        return back()->with('success', 'Jenis karyawan berhasil diperbarui.');
    }

    public function destroy(Request $request, JenisKaryawanMaster $jenisKaryawanMaster): JsonResponse|RedirectResponse
    {
        $this->authorize('jenis-karyawan-master.delete');

        $karyawanCount = $jenisKaryawanMaster->karyawan()->count();
        if ($karyawanCount > 0) {
            $message = "Jenis karyawan tidak dapat dihapus karena masih dipakai oleh {$karyawanCount} karyawan.";

            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $jenisKaryawanMaster->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Jenis karyawan telah dihapus.'], 200);
        }

        return back()->with('success', 'Jenis karyawan telah dihapus.');
    }
}
```

Kalau isi file yang kamu baca BEDA dari kutipan di atas, STOP dan laporkan ke user.

- [ ] **Step 1: Buat DTO**

`app/Domains/Sdm/DataTransferObjects/JenisKaryawanMasterData.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\DataTransferObjects;

final readonly class JenisKaryawanMasterData
{
    public function __construct(
        public string $nama,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            nama: $data['nama'],
        );
    }
}
```

- [ ] **Step 2: Buat 3 Action**

`app/Domains/Sdm/Actions/JenisKaryawan/CreateJenisKaryawanAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions\JenisKaryawan;

use App\Domains\Sdm\DataTransferObjects\JenisKaryawanMasterData;
use App\Domains\Sdm\Models\JenisKaryawanMaster;

final class CreateJenisKaryawanAction
{
    public function execute(JenisKaryawanMasterData $data): JenisKaryawanMaster
    {
        return JenisKaryawanMaster::create(['nama' => $data->nama])->loadCount('karyawan');
    }
}
```

`app/Domains/Sdm/Actions/JenisKaryawan/UpdateJenisKaryawanAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions\JenisKaryawan;

use App\Domains\Sdm\DataTransferObjects\JenisKaryawanMasterData;
use App\Domains\Sdm\Models\JenisKaryawanMaster;

final class UpdateJenisKaryawanAction
{
    public function execute(JenisKaryawanMaster $jenisKaryawanMaster, JenisKaryawanMasterData $data): JenisKaryawanMaster
    {
        $jenisKaryawanMaster->update(['nama' => $data->nama]);

        return $jenisKaryawanMaster->fresh()->loadCount('karyawan');
    }
}
```

`app/Domains/Sdm/Actions/JenisKaryawan/DeleteJenisKaryawanAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions\JenisKaryawan;

use App\Domains\Sdm\Models\JenisKaryawanMaster;
use Illuminate\Validation\ValidationException;

final class DeleteJenisKaryawanAction
{
    public function execute(JenisKaryawanMaster $jenisKaryawanMaster): void
    {
        $karyawanCount = $jenisKaryawanMaster->karyawan()->count();

        if ($karyawanCount > 0) {
            throw ValidationException::withMessages([
                'jenis_karyawan' => "Jenis karyawan tidak dapat dihapus karena masih dipakai oleh {$karyawanCount} karyawan.",
            ]);
        }

        $jenisKaryawanMaster->delete();
    }
}
```

- [ ] **Step 3: Buat controller baru di namespace `Lembaga\Sdm\`**

Buat `app/Http/Controllers/Lembaga/Sdm/JenisKaryawanMasterController.php`:

```php
<?php

namespace App\Http\Controllers\Lembaga\Sdm;

use App\Domains\Sdm\Actions\JenisKaryawan\CreateJenisKaryawanAction;
use App\Domains\Sdm\Actions\JenisKaryawan\DeleteJenisKaryawanAction;
use App\Domains\Sdm\Actions\JenisKaryawan\UpdateJenisKaryawanAction;
use App\Domains\Sdm\DataTransferObjects\JenisKaryawanMasterData;
use App\Domains\Sdm\Models\JenisKaryawanMaster;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JenisKaryawanMasterController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('jenis-karyawan-master.view');

        $jenisList = JenisKaryawanMaster::withCount('karyawan')->orderBy('nama')->get();

        if ($request->wantsJson()) {
            return response()->json(['items' => $jenisList]);
        }

        return view('portals.lembaga.sdm.jenis-karyawan-master.index', [
            'jenisList' => $jenisList,
        ]);
    }

    public function store(Request $request, CreateJenisKaryawanAction $action): JsonResponse|RedirectResponse
    {
        $this->authorize('jenis-karyawan-master.create');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', 'unique:jenis_karyawan_master,nama'],
        ]);

        $item = $action->execute(JenisKaryawanMasterData::fromArray($data));

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Jenis karyawan berhasil ditambahkan.',
                'item' => $item,
            ], 201);
        }

        return back()->with('success', 'Jenis karyawan berhasil ditambahkan.');
    }

    public function update(Request $request, JenisKaryawanMaster $jenisKaryawanMaster, UpdateJenisKaryawanAction $action): JsonResponse|RedirectResponse
    {
        $this->authorize('jenis-karyawan-master.edit');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('jenis_karyawan_master', 'nama')->ignore($jenisKaryawanMaster->id)],
        ]);

        $item = $action->execute($jenisKaryawanMaster, JenisKaryawanMasterData::fromArray($data));

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Jenis karyawan berhasil diperbarui.',
                'item' => $item,
            ], 200);
        }

        return back()->with('success', 'Jenis karyawan berhasil diperbarui.');
    }

    public function destroy(Request $request, JenisKaryawanMaster $jenisKaryawanMaster, DeleteJenisKaryawanAction $action): JsonResponse|RedirectResponse
    {
        $this->authorize('jenis-karyawan-master.delete');

        try {
            $action->execute($jenisKaryawanMaster);
        } catch (ValidationException $exception) {
            $message = $exception->errors()['jenis_karyawan'][0];

            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Jenis karyawan telah dihapus.'], 200);
        }

        return back()->with('success', 'Jenis karyawan telah dihapus.');
    }
}
```

- [ ] **Step 4: Hapus controller lama**

```bash
git rm app/Http/Controllers/Admin/JenisKaryawanMasterController.php
```

- [ ] **Step 5: Pindahkan view**

```bash
mkdir -p resources/views/portals/lembaga/sdm/jenis-karyawan-master
git mv resources/views/admin/jenis-karyawan-master/index.blade.php resources/views/portals/lembaga/sdm/jenis-karyawan-master/index.blade.php
```

Isi file TIDAK diubah sama sekali (murni pindah lokasi) — kecuali kalau file itu sendiri berisi `@include('admin.jenis-karyawan-master...')` atau `route('admin.jenis-karyawan-master...')`. Kalau ada baris `@include`, ganti path include-nya sesuai lokasi baru. Baris `route(...)` (kalau ada) JANGAN diubah — nama route tetap sama.

- [ ] **Step 6: Update `routes/admin/guru-data.php`**

Ganti baris:
```php
use App\Http\Controllers\Admin\JenisKaryawanMasterController;
```
menjadi:
```php
use App\Http\Controllers\Lembaga\Sdm\JenisKaryawanMasterController;
```

Baris `Route::get('jenis-karyawan-master', [JenisKaryawanMasterController::class, 'index'])->name('jenis-karyawan-master.index');` dan 3 baris route lain di bawahnya TIDAK diubah — hanya `use` di atas yang berubah.

- [ ] **Step 7: Verifikasi tidak ada route/view yang salah path**

```bash
grep -rn "route('portals\." resources/views/portals
```
Expected: kosong.

```bash
php artisan route:list --name=jenis-karyawan-master
```
Expected: 4 route tampil, nama SAMA seperti sebelumnya (`jenis-karyawan-master.index/.store/.update/.destroy`), Action mengarah ke `Lembaga\Sdm\JenisKaryawanMasterController`.

- [ ] **Step 8: Jalankan test scoped**

```bash
php artisan test tests/Feature/Admin/JenisKaryawanMasterCrudTest.php
```
Expected: PASS, 8 passed (jumlah test sama seperti baseline sebelum migrasi — lihat §4 spec).

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "refactor(sdm): refactor JenisKaryawanMasterController jadi Action/DTO, pindah ke Lembaga\Sdm\, view ke portals/lembaga/sdm/"
```

---

## Task 5: Refactor `JabatanTambahanMasterController` — Action/DTO + Namespace + View

**Files:**
- Create: `app/Domains/Sdm/DataTransferObjects/JabatanTambahanMasterData.php`
- Create: `app/Domains/Sdm/Actions/JabatanTambahan/CreateJabatanTambahanAction.php`
- Create: `app/Domains/Sdm/Actions/JabatanTambahan/UpdateJabatanTambahanAction.php`
- Create: `app/Domains/Sdm/Actions/JabatanTambahan/DeleteJabatanTambahanAction.php`
- Create: `app/Http/Controllers/Lembaga/Sdm/JabatanTambahanMasterController.php`
- Delete: `app/Http/Controllers/Admin/JabatanTambahanMasterController.php`
- Move: `resources/views/admin/jabatan-tambahan-master/index.blade.php` → `resources/views/portals/lembaga/sdm/jabatan-tambahan-master/index.blade.php`
- Modify: `routes/admin/guru-data.php`
- Test: `tests/Feature/Admin/JabatanTambahanMasterCrudTest.php` (sudah ada, TIDAK diubah isi-nya)

**Interfaces:**
- Consumes: `App\Domains\Sdm\Models\JabatanTambahanMaster` (Task 2).

Isi controller SAAT INI (baseline — baca dulu untuk konfirmasi sebelum edit):

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\JabatanTambahanMaster;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JabatanTambahanMasterController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('jabatan-tambahan-master.view');

        $jabatanList = JabatanTambahanMaster::withCount(['guru' => fn ($q) => $q->withoutGlobalScopes()])->orderBy('kelompok')->orderBy('nama')->get();

        if ($request->wantsJson()) {
            return response()->json(['items' => $jabatanList]);
        }

        return view('admin.jabatan-tambahan-master.index', [
            'jabatanList' => $jabatanList,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('jabatan-tambahan-master.create');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', 'unique:jabatan_tambahan_master,nama'],
            'kelompok' => ['required', Rule::in(['struktural', 'fungsional'])],
        ]);

        $item = JabatanTambahanMaster::create($data)->loadCount(['guru' => fn ($q) => $q->withoutGlobalScopes()]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Jabatan tambahan berhasil dirilis',
                'item' => $item,
            ], 201);
        }

        return back()->with('success', 'Jabatan tambahan berhasil ditambahkan.');
    }

    public function update(Request $request, JabatanTambahanMaster $jabatanTambahanMaster): JsonResponse|RedirectResponse
    {
        $this->authorize('jabatan-tambahan-master.edit');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('jabatan_tambahan_master', 'nama')->ignore($jabatanTambahanMaster->id)],
            'kelompok' => ['required', Rule::in(['struktural', 'fungsional'])],
        ]);

        $jabatanTambahanMaster->update($data);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Data jabatan berhasil diperbarui',
                'item' => $jabatanTambahanMaster->fresh(['guru' => fn ($q) => $q->withoutGlobalScopes()])->loadCount(['guru' => fn ($q) => $q->withoutGlobalScopes()]),
            ], 200);
        }

        return back()->with('success', 'Jabatan tambahan berhasil diperbarui.');
    }

    public function destroy(Request $request, JabatanTambahanMaster $jabatanTambahanMaster): JsonResponse|RedirectResponse
    {
        $this->authorize('jabatan-tambahan-master.delete');

        $guruCount = $jabatanTambahanMaster->guru()->withoutGlobalScopes()->count();
        if ($guruCount > 0) {
            $message = "Jabatan tidak dapat dihapus karena saat ini masih disandang oleh {$guruCount} Guru aktif. Lepaskan tautan jabatan pada guru bersangkutan sebelum menghapusnya.";

            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $jabatanTambahanMaster->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Jabatan telah dihapus permanen.'], 200);
        }

        return back()->with('success', 'Jabatan telah dihapus permanen.');
    }
}
```

Kalau isi file yang kamu baca BEDA dari kutipan di atas, STOP dan laporkan ke user.

- [ ] **Step 1: Buat DTO**

`app/Domains/Sdm/DataTransferObjects/JabatanTambahanMasterData.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\DataTransferObjects;

final readonly class JabatanTambahanMasterData
{
    public function __construct(
        public string $nama,
        public string $kelompok,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            nama: $data['nama'],
            kelompok: $data['kelompok'],
        );
    }
}
```

- [ ] **Step 2: Buat 3 Action**

`app/Domains/Sdm/Actions/JabatanTambahan/CreateJabatanTambahanAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions\JabatanTambahan;

use App\Domains\Sdm\DataTransferObjects\JabatanTambahanMasterData;
use App\Domains\Sdm\Models\JabatanTambahanMaster;

final class CreateJabatanTambahanAction
{
    public function execute(JabatanTambahanMasterData $data): JabatanTambahanMaster
    {
        return JabatanTambahanMaster::create([
            'nama' => $data->nama,
            'kelompok' => $data->kelompok,
        ])->loadCount(['guru' => fn ($q) => $q->withoutGlobalScopes()]);
    }
}
```

`app/Domains/Sdm/Actions/JabatanTambahan/UpdateJabatanTambahanAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions\JabatanTambahan;

use App\Domains\Sdm\DataTransferObjects\JabatanTambahanMasterData;
use App\Domains\Sdm\Models\JabatanTambahanMaster;

final class UpdateJabatanTambahanAction
{
    public function execute(JabatanTambahanMaster $jabatanTambahanMaster, JabatanTambahanMasterData $data): JabatanTambahanMaster
    {
        $jabatanTambahanMaster->update([
            'nama' => $data->nama,
            'kelompok' => $data->kelompok,
        ]);

        return $jabatanTambahanMaster->fresh(['guru' => fn ($q) => $q->withoutGlobalScopes()])
            ->loadCount(['guru' => fn ($q) => $q->withoutGlobalScopes()]);
    }
}
```

`app/Domains/Sdm/Actions/JabatanTambahan/DeleteJabatanTambahanAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions\JabatanTambahan;

use App\Domains\Sdm\Models\JabatanTambahanMaster;
use Illuminate\Validation\ValidationException;

final class DeleteJabatanTambahanAction
{
    public function execute(JabatanTambahanMaster $jabatanTambahanMaster): void
    {
        $guruCount = $jabatanTambahanMaster->guru()->withoutGlobalScopes()->count();

        if ($guruCount > 0) {
            throw ValidationException::withMessages([
                'jabatan' => "Jabatan tidak dapat dihapus karena saat ini masih disandang oleh {$guruCount} Guru aktif. Lepaskan tautan jabatan pada guru bersangkutan sebelum menghapusnya.",
            ]);
        }

        $jabatanTambahanMaster->delete();
    }
}
```

- [ ] **Step 3: Buat controller baru di namespace `Lembaga\Sdm\`**

Buat `app/Http/Controllers/Lembaga/Sdm/JabatanTambahanMasterController.php`:

```php
<?php

namespace App\Http\Controllers\Lembaga\Sdm;

use App\Domains\Sdm\Actions\JabatanTambahan\CreateJabatanTambahanAction;
use App\Domains\Sdm\Actions\JabatanTambahan\DeleteJabatanTambahanAction;
use App\Domains\Sdm\Actions\JabatanTambahan\UpdateJabatanTambahanAction;
use App\Domains\Sdm\DataTransferObjects\JabatanTambahanMasterData;
use App\Domains\Sdm\Models\JabatanTambahanMaster;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JabatanTambahanMasterController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('jabatan-tambahan-master.view');

        $jabatanList = JabatanTambahanMaster::withCount(['guru' => fn ($q) => $q->withoutGlobalScopes()])->orderBy('kelompok')->orderBy('nama')->get();

        if ($request->wantsJson()) {
            return response()->json(['items' => $jabatanList]);
        }

        return view('portals.lembaga.sdm.jabatan-tambahan-master.index', [
            'jabatanList' => $jabatanList,
        ]);
    }

    public function store(Request $request, CreateJabatanTambahanAction $action): JsonResponse|RedirectResponse
    {
        $this->authorize('jabatan-tambahan-master.create');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', 'unique:jabatan_tambahan_master,nama'],
            'kelompok' => ['required', Rule::in(['struktural', 'fungsional'])],
        ]);

        $item = $action->execute(JabatanTambahanMasterData::fromArray($data));

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Jabatan tambahan berhasil dirilis',
                'item' => $item,
            ], 201);
        }

        return back()->with('success', 'Jabatan tambahan berhasil ditambahkan.');
    }

    public function update(Request $request, JabatanTambahanMaster $jabatanTambahanMaster, UpdateJabatanTambahanAction $action): JsonResponse|RedirectResponse
    {
        $this->authorize('jabatan-tambahan-master.edit');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('jabatan_tambahan_master', 'nama')->ignore($jabatanTambahanMaster->id)],
            'kelompok' => ['required', Rule::in(['struktural', 'fungsional'])],
        ]);

        $item = $action->execute($jabatanTambahanMaster, JabatanTambahanMasterData::fromArray($data));

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Data jabatan berhasil diperbarui',
                'item' => $item,
            ], 200);
        }

        return back()->with('success', 'Jabatan tambahan berhasil diperbarui.');
    }

    public function destroy(Request $request, JabatanTambahanMaster $jabatanTambahanMaster, DeleteJabatanTambahanAction $action): JsonResponse|RedirectResponse
    {
        $this->authorize('jabatan-tambahan-master.delete');

        try {
            $action->execute($jabatanTambahanMaster);
        } catch (ValidationException $exception) {
            $message = $exception->errors()['jabatan'][0];

            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Jabatan telah dihapus permanen.'], 200);
        }

        return back()->with('success', 'Jabatan telah dihapus permanen.');
    }
}
```

- [ ] **Step 4: Hapus controller lama**

```bash
git rm app/Http/Controllers/Admin/JabatanTambahanMasterController.php
```

- [ ] **Step 5: Pindahkan view**

```bash
mkdir -p resources/views/portals/lembaga/sdm/jabatan-tambahan-master
git mv resources/views/admin/jabatan-tambahan-master/index.blade.php resources/views/portals/lembaga/sdm/jabatan-tambahan-master/index.blade.php
```

Isi file TIDAK diubah (murni pindah lokasi), kecuali ada baris `@include('admin.jabatan-tambahan-master...')` yang perlu disesuaikan pathnya. `route(...)` TIDAK diubah.

- [ ] **Step 6: Update `routes/admin/guru-data.php`**

Ganti baris:
```php
use App\Http\Controllers\Admin\JabatanTambahanMasterController;
```
menjadi:
```php
use App\Http\Controllers\Lembaga\Sdm\JabatanTambahanMasterController;
```

4 baris route di bawahnya TIDAK diubah.

- [ ] **Step 7: Verifikasi**

```bash
grep -rn "route('portals\." resources/views/portals
```
Expected: kosong.

```bash
php artisan route:list --name=jabatan-tambahan-master
```
Expected: 4 route, nama sama seperti sebelumnya, Action mengarah ke `Lembaga\Sdm\JabatanTambahanMasterController`.

- [ ] **Step 8: Jalankan test scoped**

```bash
php artisan test tests/Feature/Admin/JabatanTambahanMasterCrudTest.php
```
Expected: PASS, 7 passed.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "refactor(sdm): refactor JabatanTambahanMasterController jadi Action/DTO, pindah ke Lembaga\Sdm\, view ke portals/lembaga/sdm/"
```

---

## Task 6: Refactor `MataPelajaranController` — Action/DTO + Namespace + View

**Files:**
- Create: `app/Domains/Akademik/DataTransferObjects/MataPelajaranData.php`
- Create: `app/Domains/Akademik/Actions/MataPelajaran/CreateMataPelajaranAction.php`
- Create: `app/Domains/Akademik/Actions/MataPelajaran/UpdateMataPelajaranAction.php`
- Create: `app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php`
- Delete: `app/Http/Controllers/Admin/MataPelajaranController.php`
- Move: `resources/views/admin/mata-pelajaran/{index,create,edit,_daftar,_form}.blade.php` → `resources/views/portals/lembaga/akademik/mata-pelajaran/{index,create,edit,_daftar,_form}.blade.php`
- Modify: `routes/admin/akademik-master.php`
- Test: `tests/Feature/Admin/MataPelajaranCrudTest.php` (sudah ada, isi TIDAK diubah KECUALI baris `assertViewIs`)

**Interfaces:**
- Consumes: `App\Domains\Akademik\Models\MataPelajaran` (Task 3).

Isi controller SAAT INI (baseline — baca dulu untuk konfirmasi sebelum edit): lihat isi lengkap `app/Http/Controllers/Admin/MataPelajaranController.php` di commit `31a03ab` (5 method: `index`, `create`, `store`, `edit`, `update` — TIDAK ADA `destroy`, route resource sengaja exclude `show`/`destroy`).

Kalau isi file yang kamu baca BEDA dari yang barusan disebut (jumlah/nama method beda), STOP dan laporkan ke user.

- [ ] **Step 1: Baca isi lengkap controller lama untuk memastikan detail persis (query filter, pagination, validasi unique per-lembaga) sebelum menulis Action**

Baca `app/Http/Controllers/Admin/MataPelajaranController.php` utuh. Perhatikan detail berikut yang WAJIB dipertahankan persis di Action baru:
- `index()`: filter `search` (nama/kode), `tipe`, `kelompok`, `status`; pagination dengan whitelist `per_page` `[10,20,25,50]` default 20; deteksi AJAX (`$request->ajax() || $request->wantsJson() || X-Requested-With header`) mengembalikan partial `_daftar`, selain itu full page `index` dengan `totalMapel`/`countKurikulum`/`countAspek`.
- `store()`: resolve `lembaga_id` dari `widestScopeLevel()`, validasi `kode` unique DI-SCOPE per `lembaga_id` (bukan unique global).
- `update()`: `lembaga_id` diambil dari `$mataPelajaran->lembaga_id` (bukan dari resolve ulang), unique `kode` scoped + `ignore($mataPelajaran->id)`.

- [ ] **Step 2: Buat DTO**

`app/Domains/Akademik/DataTransferObjects/MataPelajaranData.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class MataPelajaranData
{
    public function __construct(
        public int $lembagaId,
        public string $kode,
        public string $nama,
        public int $noUrut,
        public string $tipe,
        public ?string $kelompok,
        public string $status,
    ) {}

    public static function fromArray(array $data, int $lembagaId): self
    {
        return new self(
            lembagaId: $lembagaId,
            kode: $data['kode'],
            nama: $data['nama'],
            noUrut: (int) $data['no_urut'],
            tipe: $data['tipe'],
            kelompok: $data['kelompok'] ?? null,
            status: $data['status'],
        );
    }
}
```

- [ ] **Step 3: Buat 2 Action**

`app/Domains/Akademik/Actions/MataPelajaran/CreateMataPelajaranAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\MataPelajaran;

use App\Domains\Akademik\DataTransferObjects\MataPelajaranData;
use App\Domains\Akademik\Models\MataPelajaran;

final class CreateMataPelajaranAction
{
    public function execute(MataPelajaranData $data): MataPelajaran
    {
        return MataPelajaran::create([
            'lembaga_id' => $data->lembagaId,
            'kode' => $data->kode,
            'nama' => $data->nama,
            'no_urut' => $data->noUrut,
            'tipe' => $data->tipe,
            'kelompok' => $data->kelompok,
            'status' => $data->status,
        ]);
    }
}
```

`app/Domains/Akademik/Actions/MataPelajaran/UpdateMataPelajaranAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\MataPelajaran;

use App\Domains\Akademik\DataTransferObjects\MataPelajaranData;
use App\Domains\Akademik\Models\MataPelajaran;

final class UpdateMataPelajaranAction
{
    public function execute(MataPelajaran $mataPelajaran, MataPelajaranData $data): MataPelajaran
    {
        $mataPelajaran->update([
            'kode' => $data->kode,
            'nama' => $data->nama,
            'no_urut' => $data->noUrut,
            'tipe' => $data->tipe,
            'kelompok' => $data->kelompok,
            'status' => $data->status,
        ]);

        return $mataPelajaran;
    }
}
```

- [ ] **Step 4: Buat controller baru di namespace `Lembaga\Akademik\`**

Buat `app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php`:

```php
<?php

namespace App\Http\Controllers\Lembaga\Akademik;

use App\Domains\Akademik\Actions\MataPelajaran\CreateMataPelajaranAction;
use App\Domains\Akademik\Actions\MataPelajaran\UpdateMataPelajaranAction;
use App\Domains\Akademik\DataTransferObjects\MataPelajaranData;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Enums\KelompokMataPelajaran;
use App\Enums\StatusMataPelajaran;
use App\Enums\TipeMataPelajaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MataPelajaranController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('mata-pelajaran.view');

        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

        $query = MataPelajaran::orderBy('no_urut')->orderBy('nama');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', '%' . $search . '%')
                  ->orWhere('kode', 'like', '%' . $search . '%');
            });
        }

        if ($tipe = $request->input('tipe')) {
            $query->where('tipe', $tipe);
        }

        if ($kelompok = $request->input('kelompok')) {
            $query->where('kelompok', $kelompok);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $paginated = $query->paginate($perPage)->withQueryString();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('portals.lembaga.akademik.mata-pelajaran._daftar', [
                'mataPelajaranList' => $paginated,
                'perPage'           => $perPage,
            ]);
        }

        return view('portals.lembaga.akademik.mata-pelajaran.index', [
            'mataPelajaranList' => $paginated,
            'tipeList'          => TipeMataPelajaran::cases(),
            'kelompokList'      => KelompokMataPelajaran::cases(),
            'statusList'        => StatusMataPelajaran::cases(),
            'perPage'           => $perPage,
            'totalMapel'        => MataPelajaran::count(),
            'countKurikulum'    => MataPelajaran::where('tipe', TipeMataPelajaran::Mapel->value)->count(),
            'countAspek'        => MataPelajaran::where('tipe', TipeMataPelajaran::AspekPerkembangan->value)->count(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('mata-pelajaran.create');

        return view('portals.lembaga.akademik.mata-pelajaran.create', [
            'tipeList'     => TipeMataPelajaran::cases(),
            'kelompokList' => KelompokMataPelajaran::cases(),
            'statusList'   => StatusMataPelajaran::cases(),
        ]);
    }

    public function store(Request $request, CreateMataPelajaranAction $action): RedirectResponse
    {
        $this->authorize('mata-pelajaran.create');

        $lembagaId = $request->user()->widestScopeLevel() === 'yayasan' ? session('active_lembaga_id') : $request->user()->lembaga_id;
        if ($lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif terlebih dahulu.'])->withInput();
        }

        $data = $request->validate([
            'kode' => [
                'required', 'string', 'max:20',
                Rule::unique('mata_pelajaran', 'kode')->where(fn ($query) => $query->where('lembaga_id', $lembagaId)),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'no_urut' => ['required', 'integer', 'min:1', 'max:9999'],
            'tipe' => ['required', 'in:mapel,aspek_perkembangan'],
            'kelompok' => ['nullable', 'string', Rule::enum(KelompokMataPelajaran::class)],
            'status' => ['required', 'string', Rule::enum(StatusMataPelajaran::class)],
        ]);

        $action->execute(MataPelajaranData::fromArray($data, $lembagaId));

        return redirect()->route('admin.mata-pelajaran.index')->with('status', 'Mata pelajaran berhasil disimpan.');
    }

    public function edit(MataPelajaran $mataPelajaran): View
    {
        $this->authorize('mata-pelajaran.edit');

        return view('portals.lembaga.akademik.mata-pelajaran.edit', [
            'mataPelajaran' => $mataPelajaran,
            'tipeList'      => TipeMataPelajaran::cases(),
            'kelompokList'  => KelompokMataPelajaran::cases(),
            'statusList'    => StatusMataPelajaran::cases(),
        ]);
    }

    public function update(Request $request, MataPelajaran $mataPelajaran, UpdateMataPelajaranAction $action): RedirectResponse
    {
        $this->authorize('mata-pelajaran.edit');

        $lembagaId = $mataPelajaran->lembaga_id;

        $data = $request->validate([
            'kode' => [
                'required', 'string', 'max:20',
                Rule::unique('mata_pelajaran', 'kode')->where(fn ($query) => $query->where('lembaga_id', $lembagaId))->ignore($mataPelajaran->id),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'no_urut' => ['required', 'integer', 'min:1', 'max:9999'],
            'tipe' => ['required', 'in:mapel,aspek_perkembangan'],
            'kelompok' => ['nullable', 'string', Rule::enum(KelompokMataPelajaran::class)],
            'status' => ['required', 'string', Rule::enum(StatusMataPelajaran::class)],
        ]);

        $action->execute($mataPelajaran, MataPelajaranData::fromArray($data, $lembagaId));

        return redirect()->route('admin.mata-pelajaran.index')->with('status', 'Mata pelajaran berhasil diperbarui.');
    }
}
```

- [ ] **Step 5: Hapus controller lama**

```bash
git rm app/Http/Controllers/Admin/MataPelajaranController.php
```

- [ ] **Step 6: Pindahkan 5 file view**

```bash
mkdir -p resources/views/portals/lembaga/akademik/mata-pelajaran
git mv resources/views/admin/mata-pelajaran/index.blade.php resources/views/portals/lembaga/akademik/mata-pelajaran/index.blade.php
git mv resources/views/admin/mata-pelajaran/create.blade.php resources/views/portals/lembaga/akademik/mata-pelajaran/create.blade.php
git mv resources/views/admin/mata-pelajaran/edit.blade.php resources/views/portals/lembaga/akademik/mata-pelajaran/edit.blade.php
git mv resources/views/admin/mata-pelajaran/_daftar.blade.php resources/views/portals/lembaga/akademik/mata-pelajaran/_daftar.blade.php
git mv resources/views/admin/mata-pelajaran/_form.blade.php resources/views/portals/lembaga/akademik/mata-pelajaran/_form.blade.php
```

Baca ke-5 file itu — kalau ada `@include('admin.mata-pelajaran._form')` atau `@include('admin.mata-pelajaran._daftar')`, ganti jadi `@include('portals.lembaga.akademik.mata-pelajaran._form')` / `@include('portals.lembaga.akademik.mata-pelajaran._daftar')`. Baris `route('admin.mata-pelajaran...')` di dalam view TIDAK diubah — nama route tetap sama.

- [ ] **Step 7: Update `routes/admin/akademik-master.php`**

Ganti baris:
```php
use App\Http\Controllers\Admin\MataPelajaranController;
```
menjadi:
```php
use App\Http\Controllers\Lembaga\Akademik\MataPelajaranController;
```

Baris `Route::resource('mata-pelajaran', MataPelajaranController::class)->except(['show', 'destroy']);` TIDAK diubah.

- [ ] **Step 8: Update `tests/Feature/Admin/MataPelajaranCrudTest.php`**

Cari baris 165 (persis):
```php
    $response->assertViewIs('admin.mata-pelajaran._daftar');
```
Ganti jadi:
```php
    $response->assertViewIs('portals.lembaga.akademik.mata-pelajaran._daftar');
```
Tidak ada perubahan lain di file test ini.

- [ ] **Step 9: Verifikasi**

```bash
grep -rn "route('portals\." resources/views/portals
```
Expected: kosong.

```bash
grep -rn "assertViewIs('admin\.mata-pelajaran" tests
```
Expected: kosong (harus sudah semua terupdate ke `portals.lembaga.akademik.mata-pelajaran`).

```bash
php artisan route:list --name=mata-pelajaran
```
Expected: 5 route (index/create/store/edit/update — TANPA show/destroy), nama sama seperti sebelumnya, Action mengarah ke `Lembaga\Akademik\MataPelajaranController`.

- [ ] **Step 10: Jalankan test scoped**

```bash
php artisan test tests/Feature/Admin/MataPelajaranCrudTest.php
```
Expected: PASS, 6 passed.

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "refactor(akademik): refactor MataPelajaranController jadi Action/DTO, pindah ke Lembaga\Akademik\, view ke portals/lembaga/akademik/"
```

---

## Task 7: Catatan Lintas Sub-Task di Roadmap Induk

**Files:**
- Modify: `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md`

- [ ] **Step 1: Tambah baris ke §6 (Tabel Sub-Task)**

Cari tabel di §6, tambahkan baris baru setelah baris "Migrasi Domain Kasus":

```markdown
| 2 | Serap Model Data Induk Sempit (JenisKaryawanMaster, JabatanTambahanMaster, MataPelajaran) | [`.agents/specs/2026-08-23-refactor-01-data-induk-sempit.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-23-refactor-01-data-induk-sempit.md) | [`.agents/plans/2026-08-23-refactor-01-data-induk-sempit.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-23-refactor-01-data-induk-sempit.md) | [`.agents/logs/2026-08-23-refactor-01-data-induk-sempit.md`](file:///d:/laragon/www/pintera-app/.agents/logs/2026-08-23-refactor-01-data-induk-sempit.md) | 🟡 SEDANG DIKERJAKAN |
```

- [ ] **Step 2: Tambah catatan ke §7 (Catatan Lintas Sub-Task)**

Tambahkan paragraf baru di akhir §7:

```markdown
- **Inkonsistensi lokasi view domain SDM (ditemukan 23 Agustus 2026, sub-task "Serap Model Data Induk Sempit"):** `portals/lembaga/sdm/` pertama kali dipakai di sub-task ini (untuk `jenis-karyawan-master` dan `jabatan-tambahan-master`, sesuai konvensi resmi §3.3). TAPI seluruh modul Kehadiran SDM yang dibangun sebelumnya (konfigurasi, scan QR, izin/cuti, dst) masih memakai `resources/views/admin/kehadiran-sdm/` dan `resources/views/sdm/` — TIDAK mengikuti konvensi ini. Kalau sub-task berikutnya menyentuh domain SDM, JANGAN asumsikan satu lokasi view yang seragam — cek dulu file mana yang dipindah kapan. Menyatukan semuanya ke `portals/lembaga/sdm/` adalah proyek kosmetik terpisah, belum dijadwalkan.
```

- [ ] **Step 3: Commit**

```bash
git add .agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md
git commit -m "docs(refactor): update roadmap induk - catat sub-task Data Induk sempit + inkonsistensi lokasi view SDM"
```

---

## Task 8: Verifikasi Akhir + Handoff Log

**Files:**
- Create: `.agents/logs/2026-08-23-refactor-01-data-induk-sempit.md`

- [ ] **Step 1: Jalankan seluruh test scoped gabungan (semua file dari §4 spec + Task 4-6, dalam 1 kali run)**

```bash
php artisan test tests/Feature/Sdm tests/Feature/Admin tests/Feature/Guru tests/Feature/Akademik tests/Feature tests/Unit
```

Catat jumlah pasti passed/failed. **Catatan flaky yang sudah dikenal**: test yang memakai `now()` untuk cek hari libur mingguan (mis. `ScanQrAttendanceActionTest`) bisa gagal kalau kebetulan dijalankan hari Minggu — kalau itu SATU-SATUNYA yang gagal, jalankan ulang test itu sendirian untuk konfirmasi, itu BUKAN regresi dari sub-task ini.

- [ ] **Step 2: Verifikasi tidak ada `use App\Models\{X}` tersisa untuk ketiga model**

```bash
grep -rln "use App\\\\Models\\\\JenisKaryawanMaster;\|use App\\\\Models\\\\JabatanTambahanMaster;\|use App\\\\Models\\\\MataPelajaran;" --include="*.php" app database tests
```
Expected: KOSONG total.

- [ ] **Step 3: Verifikasi tidak ada referensi `X::class` implisit tersisa di `app/Models/`**

```bash
grep -rn "JenisKaryawanMaster::class\|JabatanTambahanMaster::class\|MataPelajaran::class" --include="*.php" app/Models
```
Expected: KOSONG total.

- [ ] **Step 4: Verifikasi 3 file model lama sudah tidak ada di lokasi lama**

```bash
ls app/Models/JenisKaryawanMaster.php app/Models/JabatanTambahanMaster.php app/Models/MataPelajaran.php 2>&1
```
Expected: error "No such file or directory" untuk ketiganya (konfirmasi sudah benar-benar pindah, bukan copy).

- [ ] **Step 5: Minta izin user untuk full test suite**

Tanya ke user: "Task 1-7 selesai, test scoped semua hijau. Boleh saya jalankan full test suite (`php artisan test`) untuk verifikasi akhir?" — TUNGGU jawaban eksplisit sebelum lanjut ke Step 6. JANGAN jalankan otomatis tanpa izin.

- [ ] **Step 6: Jalankan full suite (HANYA setelah izin didapat)**

```bash
php artisan test
```

Catat angka PASTI passed/failed/duration — bandingkan failed count dengan baseline (kalau ada test yang gagal SELAIN flaky hari-Minggu yang sudah dikenal, itu regresi, harus diperbaiki sebelum lanjut).

- [ ] **Step 7: Tulis handoff log**

Buat `.agents/logs/2026-08-23-refactor-01-data-induk-sempit.md` (Bahasa Indonesia): ringkasan tiap task (1-7) dengan commit hash, hasil test dengan angka PASTI dari Step 1 dan Step 6 (jangan klaim tanpa command nyata), hasil Step 2-4 (harus semua "kosong"/"file tidak ada").

- [ ] **Step 8: Commit handoff log**

```bash
git add .agents/logs/2026-08-23-refactor-01-data-induk-sempit.md
git commit -m "docs(refactor): handoff log serap model Data Induk sempit ke domain pemiliknya"
```
