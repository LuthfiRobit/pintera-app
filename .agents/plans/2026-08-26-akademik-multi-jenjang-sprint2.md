# Fondasi Akademik Multi-Jenjang — Sprint 2 (Assessment Type) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `KomponenPenilaian` tidak lagi mengasumsikan penilaian pasti berupa angka 0-100 — mendukung `numeric`/`narrative`/`predicate` (BB/MB/BSH/BSB), dengan validasi & penyimpanan yang konsisten dan 3 consumer yang sebelumnya akan menghasilkan angka salah (`RaporCalculationService`, `CapaianKompetensiGenerator`, `DashboardStatsService`) diperbaiki eksplisit.

**Architecture:** Kolom baru aditif (`assessment_type` default `'numeric'`) → enum baru → geser DTO/Action/Request/UI komponen-penilaian → geser input nilai guru + validasi kondisional server-side (fail keras via 2 layer: HTTP request + Action invariant) → perbaiki 3 consumer Bucket C → test matrix penuh + regresi.

**Tech Stack:** Laravel 12.63.0, Pest, MySQL.

**Bergantung pada:** Sprint 1 (SELESAI, `subjek_type`/`subjek_id` live).

## Global Constraints

- `assessment_type` DB default `'numeric'` adalah FAKTA kolom, bukan logika. Default berbasis `subjek_type` (elemen_cp→narrative, mata_pelajaran→numeric) HANYA hidup di `CreateKomponenPenilaianAction`, dipicu ketika DTO menerima `null` — bukan trigger DB, bukan di form otomatis mengunci pilihan.
- `AssessmentType` (cara nilai disimpan) dan `PredikatPaud` (vocabulary nilai utk tipe predicate) adalah 2 enum terpisah. Dilarang ada `AssessmentType::Paud` atau `AssessmentType::BB` dsb.
- `UpdateNilaiSiswaRequest` WAJIB membangun rules dari `KomponenPenilaian.assessment_type` yang di-query dari DB — dilarang membaca/mempercayai field `assessment_type` apa pun dari payload request.
- `SimpanNilaiSiswaAction` WAJIB memaksa konsistensi data per tipe (null-kan field yang tidak relevan) — ini defense-in-depth, independen dari validasi Request, diuji sbg unit test terpisah yang memanggil Action langsung dgn payload sengaja "kotor".
- Non-goal Sprint 2 (JANGAN dikerjakan): rapor PDF format PAUD, progress bar UI (`asesmen/show.blade.php` `$filledCount`), widget "nilai terbaru" dashboard, Report Engine, tabel konfigurasi predikat per-lembaga.
- Regresi C1-C3: yang dijamin sama adalah HASIL HITUNG untuk kasus seluruh komponen numeric — bukan kode/implementasinya tidak berubah (memang sengaja diubah).
- Jalankan test scoped di setiap task; full suite HANYA di Task 6 (final).

---

### Task 1: Skema, Enum, Model

**Files:**
- Create: `database/migrations/2026_08_26_110000_add_assessment_type_to_komponen_penilaian_table.php`
- Create: `app/Domains/Akademik/Enums/AssessmentType.php`
- Create: `app/Domains/Akademik/Enums/PredikatPaud.php`
- Modify: `app/Domains/Akademik/Models/KomponenPenilaian.php`
- Modify: `app/Domains/Akademik/Models/NilaiSiswa.php`
- Test: `tests/Unit/Models/KomponenPenilaianAssessmentTypeTest.php`

**Interfaces:**
- Produces: `KomponenPenilaian.assessment_type` (string, default `'numeric'`), `AssessmentType`/`PredikatPaud` enum — dipakai semua task berikutnya.

- [ ] **Step 1: Migration kolom `assessment_type`**

```php
<?php
// database/migrations/2026_08_26_110000_add_assessment_type_to_komponen_penilaian_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('komponen_penilaian', function (Blueprint $table) {
            $table->string('assessment_type')->default('numeric')->after('subjek_id');
        });
    }

    public function down(): void
    {
        Schema::table('komponen_penilaian', function (Blueprint $table) {
            $table->dropColumn('assessment_type');
        });
    }
};
```

- [ ] **Step 2: Enum `AssessmentType`**

```php
<?php
// app/Domains/Akademik/Enums/AssessmentType.php

namespace App\Domains\Akademik\Enums;

enum AssessmentType: string
{
    case Numeric = 'numeric';
    case Narrative = 'narrative';
    case Predicate = 'predicate';

    public function label(): string
    {
        return match ($this) {
            self::Numeric => 'Nilai Angka',
            self::Narrative => 'Naratif/Deskriptif',
            self::Predicate => 'Predikat Capaian',
        };
    }
}
```

- [ ] **Step 3: Enum `PredikatPaud`**

```php
<?php
// app/Domains/Akademik/Enums/PredikatPaud.php

namespace App\Domains\Akademik\Enums;

enum PredikatPaud: string
{
    case BB = 'BB';
    case MB = 'MB';
    case BSH = 'BSH';
    case BSB = 'BSB';

    public function label(): string
    {
        return match ($this) {
            self::BB => 'Belum Berkembang',
            self::MB => 'Mulai Berkembang',
            self::BSH => 'Berkembang Sesuai Harapan',
            self::BSB => 'Berkembang Sangat Baik',
        };
    }
}
```

- [ ] **Step 4: `KomponenPenilaian` model — fillable + cast**

Tambah `'assessment_type'` ke `$fillable` (setelah `'subjek_id'`), tambah import `use App\Domains\Akademik\Enums\AssessmentType;`, dan di method `casts()` tambah:
```php
protected function casts(): array
{
    return [
        'assessment_type' => AssessmentType::class,
    ];
}
```
(Model ini sebelumnya belum punya method `casts()` sama sekali sejak Sprint 1 — tambahkan baru, jangan bingung dengan `KomponenPenilaian` versi PRA-Sprint-1 yang dulu punya cast `elemen_cp`, itu sudah dihapus.)

- [ ] **Step 5: `NilaiSiswa` model — cast `predikat`**

Tambah import `use App\Domains\Akademik\Enums\PredikatPaud;`, ubah method `casts()`:
```php
protected function casts(): array
{
    return [
        'nilai_angka' => 'integer',
        'predikat' => PredikatPaud::class,
    ];
}
```

- [ ] **Step 6: Test — default DB & cast bekerja**

```php
<?php
// tests/Unit/Models/KomponenPenilaianAssessmentTypeTest.php

use App\Domains\Akademik\Enums\AssessmentType;
use App\Domains\Akademik\Enums\PredikatPaud;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Models\Semester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('defaults assessment_type to numeric at the database level when not specified', function () {
    $mapel = MataPelajaran::factory()->create();
    $semester = Semester::factory()->create(['lembaga_id' => $mapel->lembaga_id]);

    $komponen = KomponenPenilaian::create([
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
        'semester_id' => $semester->id,
        'lembaga_id' => $mapel->lembaga_id,
        'deskripsi' => 'Tes default DB',
        'bobot' => 10,
    ]);

    expect($komponen->fresh()->assessment_type)->toBe(AssessmentType::Numeric);
});

it('casts predikat on NilaiSiswa to the PredikatPaud enum', function () {
    $nilai = NilaiSiswa::factory()->create(['predikat' => 'BSH', 'nilai_angka' => null]);

    expect($nilai->fresh()->predikat)->toBe(PredikatPaud::BSH);
});
```

- [ ] **Step 7: Jalankan test scoped**

Run: `php artisan test --filter=KomponenPenilaianAssessmentTypeTest`
Expected: PASS. (Kalau `NilaiSiswaFactory` belum punya field `predikat` di definition, tambahkan sbg `nullable` default sebelum test ini bisa jalan — cek factory dulu.)

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_26_110000_add_assessment_type_to_komponen_penilaian_table.php app/Domains/Akademik/Enums/AssessmentType.php app/Domains/Akademik/Enums/PredikatPaud.php app/Domains/Akademik/Models/KomponenPenilaian.php app/Domains/Akademik/Models/NilaiSiswa.php tests/Unit/Models/KomponenPenilaianAssessmentTypeTest.php
git commit -m "feat(akademik): tambah kolom assessment_type + enum AssessmentType/PredikatPaud"
```

---

### Task 2: DTO, Action, Form Request untuk CRUD Komponen Penilaian

**Files:**
- Modify: `app/Domains/Akademik/DataTransferObjects/KomponenPenilaianData.php`
- Modify: `app/Domains/Akademik/DataTransferObjects/UpdateKomponenPenilaianData.php`
- Modify: `app/Domains/Akademik/Actions/Penilaian/CreateKomponenPenilaianAction.php`
- Modify: `app/Domains/Akademik/Actions/Penilaian/UpdateKomponenPenilaianAction.php`
- Modify: `app/Http/Requests/Akademik/StoreKomponenPenilaianRequest.php`
- Modify: `app/Http/Requests/Akademik/UpdateKomponenPenilaianRequest.php`
- Modify: `app/Http/Requests/Akademik/StoreKomponenPenilaianSendiriRequest.php`
- Modify: `app/Http/Requests/Akademik/UpdateKomponenPenilaianSendiriRequest.php`
- Test: `tests/Feature/Akademik/AssessmentTypeDefaultingTest.php`

**Interfaces:**
- Consumes: `AssessmentType` (Task 1).
- Produces: `KomponenPenilaian.assessment_type` terisi benar saat create/update — dipakai Task 3 (UI) & Task 4 (validasi nilai).

- [ ] **Step 1: `KomponenPenilaianData` DTO — tambah `assessmentType` nullable**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class KomponenPenilaianData
{
    public function __construct(
        public string $subjekType,
        public int $subjekId,
        public int $semesterId,
        public ?string $kode,
        public string $deskripsi,
        public int $bobot,
        public ?string $kktp,
        public ?int $kktpMinimal,
        public ?string $assessmentType,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            subjekType: (string) $data['subjek_type'],
            subjekId: (int) $data['subjek_id'],
            semesterId: (int) $data['semester_id'],
            kode: $data['kode'] ?? null,
            deskripsi: $data['deskripsi'],
            bobot: isset($data['bobot']) ? (int) $data['bobot'] : 10,
            kktp: $data['kktp'] ?? null,
            kktpMinimal: isset($data['kktp_minimal']) ? (int) $data['kktp_minimal'] : null,
            assessmentType: isset($data['assessment_type']) && $data['assessment_type'] !== ''
                ? (string) $data['assessment_type']
                : null,
        );
    }
}
```

- [ ] **Step 2: `UpdateKomponenPenilaianData` DTO — pola sama**

Tambah `public ?string $assessmentType` di constructor (posisi terakhir), dan di `fromArray()`:
```php
assessmentType: isset($data['assessment_type']) && $data['assessment_type'] !== ''
    ? (string) $data['assessment_type']
    : null,
```

- [ ] **Step 3: `CreateKomponenPenilaianAction` — hitung default HANYA saat null**

Tambah `use App\Domains\Akademik\Enums\AssessmentType;`, ubah pemanggilan `KomponenPenilaian::create()`:
```php
$assessmentType = $data->assessmentType ?? match ($data->subjekType) {
    'elemen_cp' => AssessmentType::Narrative->value,
    'mata_pelajaran' => AssessmentType::Numeric->value,
};

return KomponenPenilaian::create([
    'subjek_type' => $data->subjekType,
    'subjek_id' => $data->subjekId,
    'semester_id' => $data->semesterId,
    'lembaga_id' => Semester::findOrFail($data->semesterId)->lembaga_id,
    'kode' => $data->kode,
    'deskripsi' => $data->deskripsi,
    'bobot' => $data->bobot,
    'kktp' => $data->kktp,
    'kktp_minimal' => $data->kktpMinimal,
    'assessment_type' => $assessmentType,
]);
```

- [ ] **Step 4: `UpdateKomponenPenilaianAction` — `assessment_type` ikut terkunci saat `$dipakai`**

`assessment_type` diperlakukan SAMA seperti `subjek_type`/`semester_id` — kalau komponen sudah punya asesmen/nilai (`$dipakai`), tidak boleh diubah lagi (mengubah tipe penilaian komponen yang sudah punya data akan membuat data lama semantically orphan). Tambahkan ke blok yang sama:
```php
if (! $dipakai && $data->subjekType !== null && $data->subjekId !== null && $data->semesterId !== null) {
    $komponen->subjek_type = $data->subjekType;
    $komponen->subjek_id = $data->subjekId;
    $komponen->semester_id = $data->semesterId;
    if ($data->assessmentType !== null) {
        $komponen->assessment_type = $data->assessmentType;
    }
}
```

- [ ] **Step 5: Form Requests — validasi `assessment_type` (nullable, enum-valid)**

`StoreKomponenPenilaianRequest`, `StoreKomponenPenilaianSendiriRequest` — tambah ke `rules()`, plus import `use App\Domains\Akademik\Enums\AssessmentType;`:
```php
'assessment_type' => ['nullable', Rule::enum(AssessmentType::class)],
```
`UpdateKomponenPenilaianRequest`, `UpdateKomponenPenilaianSendiriRequest` — tambah rule yang sama TAPI di DALAM blok `if (! $dipakai)` (konsisten dengan Step 4 — field ini hanya bisa dikirim/divalidasi kalau komponen belum dipakai):
```php
if (! $dipakai) {
    $rules['subjek_type'] = [...]; // existing
    $rules['subjek_id'] = [...]; // existing
    $rules['semester_id'] = [...]; // existing
    $rules['assessment_type'] = ['nullable', Rule::enum(AssessmentType::class)];
}
```

- [ ] **Step 6: Test — defaulting & override & invalid-reject**

```php
<?php
// tests/Feature/Akademik/AssessmentTypeDefaultingTest.php

use App\Domains\Akademik\Enums\AssessmentType;
use App\Domains\Akademik\Models\ElemenCp;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
});

it('defaults to narrative when subjek_type=elemen_cp and assessment_type is not sent', function () {
    $elemen = ElemenCp::factory()->create();
    $semester = Semester::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $semester->lembaga_id]);
    $user->givePermissionTo('komponen-penilaian.kelola');

    $this->actingAs($user)->post(route('admin.komponen-penilaian.store'), [
        'subjek_type' => 'elemen_cp',
        'subjek_id' => $elemen->id,
        'semester_id' => $semester->id,
        'deskripsi' => 'Tes default narrative',
        'bobot' => 10,
    ])->assertRedirect();

    $komponen = KomponenPenilaian::where('subjek_type', 'elemen_cp')->where('subjek_id', $elemen->id)->firstOrFail();
    expect($komponen->assessment_type)->toBe(AssessmentType::Narrative);
});

it('defaults to numeric when subjek_type=mata_pelajaran and assessment_type is not sent', function () {
    $mapel = MataPelajaran::factory()->create();
    $semester = Semester::factory()->create(['lembaga_id' => $mapel->lembaga_id]);
    $user = User::factory()->create(['lembaga_id' => $semester->lembaga_id]);
    $user->givePermissionTo('komponen-penilaian.kelola');

    $this->actingAs($user)->post(route('admin.komponen-penilaian.store'), [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
        'semester_id' => $semester->id,
        'deskripsi' => 'Tes default numeric',
        'bobot' => 10,
    ])->assertRedirect();

    $komponen = KomponenPenilaian::where('subjek_type', 'mata_pelajaran')->where('subjek_id', $mapel->id)->firstOrFail();
    expect($komponen->assessment_type)->toBe(AssessmentType::Numeric);
});

it('honors an explicit assessment_type override even when it contradicts the subjek_type default', function () {
    $elemen = ElemenCp::factory()->create();
    $semester = Semester::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $semester->lembaga_id]);
    $user->givePermissionTo('komponen-penilaian.kelola');

    $this->actingAs($user)->post(route('admin.komponen-penilaian.store'), [
        'subjek_type' => 'elemen_cp',
        'subjek_id' => $elemen->id,
        'semester_id' => $semester->id,
        'deskripsi' => 'Tes override',
        'bobot' => 10,
        'assessment_type' => 'predicate',
    ])->assertRedirect();

    $komponen = KomponenPenilaian::where('subjek_type', 'elemen_cp')->where('subjek_id', $elemen->id)->firstOrFail();
    expect($komponen->assessment_type)->toBe(AssessmentType::Predicate);
});

it('rejects an invalid assessment_type value at the request boundary', function () {
    $mapel = MataPelajaran::factory()->create();
    $semester = Semester::factory()->create(['lembaga_id' => $mapel->lembaga_id]);
    $user = User::factory()->create(['lembaga_id' => $semester->lembaga_id]);
    $user->givePermissionTo('komponen-penilaian.kelola');

    $response = $this->actingAs($user)->post(route('admin.komponen-penilaian.store'), [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
        'semester_id' => $semester->id,
        'deskripsi' => 'Tes invalid',
        'bobot' => 10,
        'assessment_type' => 'foobar',
    ]);

    $response->assertSessionHasErrors('assessment_type');
    expect(KomponenPenilaian::where('deskripsi', 'Tes invalid')->exists())->toBeFalse();
});
```

- [ ] **Step 7: Jalankan test scoped**

Run: `php artisan test --filter=AssessmentTypeDefaultingTest`
Expected: 4 test PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Domains/Akademik/DataTransferObjects/KomponenPenilaianData.php app/Domains/Akademik/DataTransferObjects/UpdateKomponenPenilaianData.php app/Domains/Akademik/Actions/Penilaian/CreateKomponenPenilaianAction.php app/Domains/Akademik/Actions/Penilaian/UpdateKomponenPenilaianAction.php app/Http/Requests/Akademik/StoreKomponenPenilaianRequest.php app/Http/Requests/Akademik/UpdateKomponenPenilaianRequest.php app/Http/Requests/Akademik/StoreKomponenPenilaianSendiriRequest.php app/Http/Requests/Akademik/UpdateKomponenPenilaianSendiriRequest.php tests/Feature/Akademik/AssessmentTypeDefaultingTest.php
git commit -m "feat(akademik): DTO/Action/Request dukung assessment_type dgn domain-layer default"
```

---

### Task 3: UI Form Komponen Penilaian — Field "Tipe Penilaian"

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/komponen-penilaian/create.blade.php`
- Modify: `resources/views/portals/lembaga/akademik/komponen-penilaian/edit.blade.php`
- Modify: `resources/views/portals/guru/akademik/komponen-penilaian/create.blade.php`
- Modify: `resources/views/portals/guru/akademik/komponen-penilaian/edit.blade.php`
- Test: `tests/Feature/Guru/KomponenPenilaianAssessmentTypeUiTest.php`

**Interfaces:**
- Consumes: `AssessmentType` enum (Task 1), `subjekType` Alpine state dari Sprint 1 (sudah ada di `x-data` keempat file ini).

- [ ] **Step 1: Tambah select "Tipe Penilaian" + Alpine auto-preselect (4 file, pola identik)**

Di keempat file, cari blok `x-data` yang sudah punya `subjekType` (dari Sprint 1) — tambah state `assessmentType` dan sebuah `x-effect`/`@change` handler pada radio subjek supaya assessmentType ikut ter-update sbg *pre-fill*, BUKAN dikunci:

```blade
{{-- tambahkan di x-data yang sudah ada, jangan bikin x-data baru --}}
x-data="{ subjekType: '...', assessmentType: '{{ old('assessment_type', $komponenPenilaian->assessment_type?->value ?? 'numeric') }}' }"
```
Pada kedua radio `subjek_type` (existing dari Sprint 1), tambah `@change`:
```blade
<input type="radio" name="subjek_type" value="mata_pelajaran" x-model="subjekType" @change="assessmentType = 'numeric'">
<input type="radio" name="subjek_type" value="elemen_cp" x-model="subjekType" @change="assessmentType = 'narrative'">
```
Lalu tambah select baru (setelah blok toggle subjek, sebelum field Semester/Tahun Ajaran):
```blade
<div>
    <x-input-label value="Tipe Penilaian *" />
    <select name="assessment_type" x-model="assessmentType" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
        <option value="numeric">Nilai Angka</option>
        <option value="narrative">Naratif/Deskriptif</option>
        <option value="predicate">Predikat Capaian (BB/MB/BSH/BSB)</option>
    </select>
    <x-input-error :messages="$errors->get('assessment_type')" class="mt-1" />
</div>
```
**Penting untuk `edit.blade.php` (kedua portal)**: select ini harus `disabled` (dengan hidden input fallback agar tetap terkirim value lama) kalau `$dipakai` true — konsisten dgn Task 2 Step 4/5 yang mengunci `assessment_type` begitu komponen sudah dipakai:
```blade
<select name="assessment_type" x-model="assessmentType" :disabled="{{ $dipakai ? 'true' : 'false' }}" ...>
```

- [ ] **Step 2: Test — field tampil & tersimpan di kedua portal**

```php
<?php
// tests/Feature/Guru/KomponenPenilaianAssessmentTypeUiTest.php

use App\Models\Semester;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
});

it('shows the Tipe Penilaian select on the guru komponen penilaian create form', function () {
    $semester = Semester::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $semester->lembaga_id]);
    $user->assignRole('guru');
    \App\Models\Guru::factory()->create(['user_id' => $user->id, 'lembaga_id' => $semester->lembaga_id]);

    $response = $this->actingAs($user)->get(route('guru.komponen-penilaian.create'));

    $response->assertOk();
    $response->assertSee('Tipe Penilaian');
    $response->assertSee('Predikat Capaian', false);
});
```
**Catatan implementer**: verifikasi nama route Guru create persis (sama seperti dicatat di kickoff Sprint 1) sebelum finalisasi.

- [ ] **Step 3: Jalankan test scoped**

Run: `php artisan test --filter=KomponenPenilaianAssessmentTypeUiTest`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/views/portals/lembaga/akademik/komponen-penilaian/ resources/views/portals/guru/akademik/komponen-penilaian/ tests/Feature/Guru/KomponenPenilaianAssessmentTypeUiTest.php
git commit -m "feat(akademik): UI Tipe Penilaian dgn auto-preselect berbasis subjek_type"
```

---

### Task 4: Input Nilai Guru — Validasi Kondisional 2-Layer + UI per Tipe

**Files:**
- Modify: `app/Http/Requests/Akademik/UpdateNilaiSiswaRequest.php`
- Modify: `app/Domains/Akademik/Actions/Penilaian/SimpanNilaiSiswaAction.php`
- Modify: `resources/views/portals/guru/akademik/asesmen/show.blade.php`
- Test: `tests/Feature/Akademik/UpdateNilaiSiswaValidationTest.php`
- Test: `tests/Unit/Actions/SimpanNilaiSiswaActionInvariantTest.php`

**Interfaces:**
- Consumes: `KomponenPenilaian.assessment_type`, `AssessmentType`, `PredikatPaud` (Task 1).
- Produces: `NilaiSiswa` rows yang konsisten per tipe — dikonsumsi Task 5 (rekap/rapor).

- [ ] **Step 1 (RED WAJIB — verifikasi wildcard SEBELUM lanjut): test nested validation 2 siswa × 2 komponen tipe berbeda**

Tulis test ini LEBIH DULU dan jalankan sebelum menulis rules final di Step 2 — tujuannya membuktikan pola wildcard Laravel yang benar untuk struktur `nilai[siswa_id][komponen_id][field]` dgn komponen_id yang diketahui di server (bukan wildcard `*` di kedua level):

```php
<?php
// tests/Feature/Akademik/UpdateNilaiSiswaValidationTest.php

use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    (new PermissionSeeder())->run();
    (new RoleSeeder())->run();
});

function buatAsesmenDuaKomponenTipeBeda(): array
{
    $mapel = MataPelajaran::factory()->create();
    $lembaga = $mapel->lembaga_id;
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga]);
    $user = User::factory()->create(['lembaga_id' => $lembaga]);
    $user->assignRole('guru');
    $guru = Guru::factory()->create(['user_id' => $user->id, 'lembaga_id' => $lembaga]);

    $siswaSatu = Siswa::factory()->create(['lembaga_id' => $lembaga, 'kelas_id' => $kelas->id]);
    $siswaDua = Siswa::factory()->create(['lembaga_id' => $lembaga, 'kelas_id' => $kelas->id]);

    $komponenNumeric = KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
        'lembaga_id' => $lembaga, 'assessment_type' => 'numeric',
    ]);
    $komponenNarrative = KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
        'lembaga_id' => $lembaga, 'assessment_type' => 'narrative',
    ]);

    $asesmen = Asesmen::factory()->create([
        'guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
    ]);
    $asesmen->komponenPenilaian()->attach([$komponenNumeric->id, $komponenNarrative->id]);

    return compact('user', 'asesmen', 'siswaSatu', 'siswaDua', 'komponenNumeric', 'komponenNarrative');
}

it('validates each siswa x komponen cell against ITS OWN assessment_type, not mixed up across siswa or komponen', function () {
    ['user' => $user, 'asesmen' => $asesmen, 'siswaSatu' => $siswaSatu, 'siswaDua' => $siswaDua, 'komponenNumeric' => $komponenNumeric, 'komponenNarrative' => $komponenNarrative] = buatAsesmenDuaKomponenTipeBeda();

    // Siswa 1: numeric diisi benar, narrative diisi benar.
    // Siswa 2: numeric diisi benar, narrative DIKOSONGKAN (harus ditolak).
    $response = $this->actingAs($user)->put(route('guru.asesmen.update-nilai', $asesmen), [
        'nilai' => [
            $siswaSatu->id => [
                $komponenNumeric->id => ['nilai_angka' => 80],
                $komponenNarrative->id => ['catatan' => 'Berkembang baik'],
            ],
            $siswaDua->id => [
                $komponenNumeric->id => ['nilai_angka' => 90],
                $komponenNarrative->id => ['catatan' => ''],
            ],
        ],
    ]);

    $response->assertSessionHasErrors("nilai.{$siswaDua->id}.{$komponenNarrative->id}.catatan");
    $response->assertSessionDoesntHaveErrors("nilai.{$siswaSatu->id}.{$komponenNumeric->id}.nilai_angka");
    $response->assertSessionDoesntHaveErrors("nilai.{$siswaSatu->id}.{$komponenNarrative->id}.catatan");
    $response->assertSessionDoesntHaveErrors("nilai.{$siswaDua->id}.{$komponenNumeric->id}.nilai_angka");
});
```

- [ ] **Step 2: Jalankan test — WAJIB verifikasi pola wildcard sebelum lanjut**

Run: `php artisan test --filter=UpdateNilaiSiswaValidationTest`
Expected: pada percobaan pertama kemungkinan GAGAL (karena `UpdateNilaiSiswaRequest::rules()` belum diubah). Setelah Step 3 diimplementasikan, test ini WAJIB hijau — kalau pola rule dengan key literal `komponen_id` (bukan wildcard `*` di level itu) ternyata tidak menghasilkan pesan error di path session yang diharapkan (`nilai.{siswaId}.{komponenId}.catatan`), STOP dan laporkan — jangan modifikasi test supaya "lolos" dgn path error yang berbeda dari yang benar-benar dikirim form.

- [ ] **Step 3: `UpdateNilaiSiswaRequest` — rules per komponen dari DB**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\DataTransferObjects\NilaiSiswaBatchData;
use App\Domains\Akademik\Enums\PredikatPaud;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateNilaiSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('asesmen.kelola');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = ['nilai' => ['required', 'array']];

        /** @var \App\Domains\Akademik\Models\Asesmen $asesmen */
        $asesmen = $this->route('asesmen');
        $tipePerKomponen = $asesmen->komponenPenilaian()->pluck('assessment_type', 'komponen_penilaian.id');

        foreach ($this->input('nilai', []) as $siswaId => $perKomponen) {
            foreach (array_keys($perKomponen) as $komponenId) {
                $tipe = $tipePerKomponen->get((int) $komponenId)?->value;
                $prefix = "nilai.{$siswaId}.{$komponenId}";

                $rules["{$prefix}.nilai_angka"] = $tipe === 'numeric'
                    ? ['nullable', 'integer', 'min:0', 'max:100']
                    : ['prohibited'];
                $rules["{$prefix}.predikat"] = $tipe === 'predicate'
                    ? ['required', Rule::in(array_column(PredikatPaud::cases(), 'value'))]
                    : ['prohibited'];
                $rules["{$prefix}.catatan"] = $tipe === 'narrative'
                    ? ['required', 'string']
                    : ['nullable', 'string'];
            }
        }

        return $rules;
    }

    public function toDTO(): NilaiSiswaBatchData
    {
        return NilaiSiswaBatchData::fromArray($this->validated());
    }
}
```
**Catatan implementer**: rules dibangun secara DINAMIS dari `$this->input('nilai', [])` (siswa_id & komponen_id sbg literal key hasil iterasi input, BUKAN wildcard `*`) digabung dengan `$tipePerKomponen` dari DB — ini pola yang perlu dibuktikan benar oleh Step 1-2 sebelum dianggap final. Kalau Step 2 gagal dgn pola ini, kemungkinan perlu pendekatan `Validator::make()` manual dgn `after()` callback alih-alih murni deklaratif `rules()` — evaluasi berdasarkan hasil test nyata.

- [ ] **Step 4: `NilaiSiswaBatchData` DTO — tambah dukungan `predikat`**

Update docblock `@param` di constructor jadi menyebut `predikat` juga (`array{nilai_angka?: int|string|null, predikat?: string|null, catatan?: string|null}`) — TIDAK ada perubahan struktural, `$nilai` tetap array asosiatif mentah yang diteruskan apa adanya ke Action.

- [ ] **Step 5: `SimpanNilaiSiswaAction` — invariant per tipe**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Penilaian;

use App\Domains\Akademik\DataTransferObjects\NilaiSiswaBatchData;
use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Models\PengajuanRapor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SimpanNilaiSiswaAction
{
    /**
     * @throws ValidationException
     */
    public function execute(Asesmen $asesmen, NilaiSiswaBatchData $data): void
    {
        $terkunci = PengajuanRapor::where('kelas_id', $asesmen->kelas_id)
            ->where('semester_id', $asesmen->semester_id)
            ->where('status', StatusPengajuanRapor::Disetujui)
            ->exists();

        if ($terkunci) {
            throw ValidationException::withMessages([
                'nilai' => 'Nilai untuk kelas dan semester ini sudah dikunci karena rapor sudah disetujui.',
            ]);
        }

        $tipePerKomponen = $asesmen->komponenPenilaian()->pluck('assessment_type', 'komponen_penilaian.id');
        $siswaIds = $asesmen->kelas->siswa()->pluck('id');

        DB::transaction(function () use ($asesmen, $data, $tipePerKomponen, $siswaIds) {
            foreach ($data->nilai as $siswaId => $perKomponen) {
                if (! $siswaIds->contains((int) $siswaId)) {
                    continue;
                }

                foreach ($perKomponen as $komponenId => $values) {
                    $tipe = $tipePerKomponen->get((int) $komponenId)?->value;
                    if ($tipe === null) {
                        continue;
                    }

                    $payload = match ($tipe) {
                        'numeric' => [
                            'nilai_angka' => isset($values['nilai_angka']) && $values['nilai_angka'] !== '' ? (int) $values['nilai_angka'] : null,
                            'predikat' => null,
                            'catatan' => $values['catatan'] ?? null,
                        ],
                        'narrative' => [
                            'nilai_angka' => null,
                            'predikat' => null,
                            'catatan' => $values['catatan'] ?? null,
                        ],
                        'predicate' => [
                            'nilai_angka' => null,
                            'predikat' => $values['predikat'] ?? null,
                            'catatan' => $values['catatan'] ?? null,
                        ],
                        default => null,
                    };

                    if ($payload === null) {
                        continue;
                    }

                    NilaiSiswa::updateOrCreate(
                        ['asesmen_id' => $asesmen->id, 'siswa_id' => $siswaId, 'komponen_penilaian_id' => $komponenId],
                        $payload
                    );
                }
            }
        });
    }
}
```

- [ ] **Step 6: Test — invariant Action (Layer 2, panggil Action langsung, payload sengaja kotor)**

```php
<?php
// tests/Unit/Actions/SimpanNilaiSiswaActionInvariantTest.php

use App\Domains\Akademik\Actions\Penilaian\SimpanNilaiSiswaAction;
use App\Domains\Akademik\DataTransferObjects\NilaiSiswaBatchData;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('forces nilai_angka/predikat/catatan to the correct combination regardless of what the payload contains, bypassing HTTP validation entirely', function (string $tipe, array $payloadKotor, array $expectedTersimpan) {
    $mapel = MataPelajaran::factory()->create();
    $lembaga = $mapel->lembaga_id;
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga, 'kelas_id' => $kelas->id]);

    $komponen = KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
        'lembaga_id' => $lembaga, 'assessment_type' => $tipe,
    ]);
    $asesmen = Asesmen::factory()->create([
        'guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
    ]);
    $asesmen->komponenPenilaian()->attach($komponen->id);

    app(SimpanNilaiSiswaAction::class)->execute($asesmen, NilaiSiswaBatchData::fromArray([
        'nilai' => [$siswa->id => [$komponen->id => $payloadKotor]],
    ]));

    $nilai = NilaiSiswa::where('asesmen_id', $asesmen->id)->where('siswa_id', $siswa->id)->where('komponen_penilaian_id', $komponen->id)->first();

    expect($nilai->nilai_angka)->toBe($expectedTersimpan['nilai_angka']);
    expect($nilai->predikat?->value)->toBe($expectedTersimpan['predikat']);
    expect($nilai->catatan)->toBe($expectedTersimpan['catatan']);
})->with([
    'numeric komponen dgn predikat dipaksa ikut' => ['numeric', ['nilai_angka' => 85, 'predikat' => 'BSH', 'catatan' => 'x'], ['nilai_angka' => 85, 'predikat' => null, 'catatan' => 'x']],
    'narrative komponen dgn nilai_angka & predikat dipaksa ikut' => ['narrative', ['nilai_angka' => 85, 'predikat' => 'BSH', 'catatan' => 'y'], ['nilai_angka' => null, 'predikat' => null, 'catatan' => 'y']],
    'predicate komponen dgn nilai_angka dipaksa ikut' => ['predicate', ['nilai_angka' => 85, 'predikat' => 'BSH', 'catatan' => 'z'], ['nilai_angka' => null, 'predikat' => 'BSH', 'catatan' => 'z']],
]);
```

- [ ] **Step 7: Test — field `assessment_type` yang dipaksa masuk payload nilai diabaikan total (acceptance criterion §6)**

Tambahkan ke `UpdateNilaiSiswaValidationTest.php`:
```php
it('ignores an assessment_type field forcibly injected into the nilai payload, and does not let it affect validation', function () {
    ['user' => $user, 'asesmen' => $asesmen, 'siswaSatu' => $siswaSatu, 'komponenNumeric' => $komponenNumeric] = buatAsesmenDuaKomponenTipeBeda();

    // Guru/klien coba menyamarkan komponen numeric seolah predikat lewat field liar --
    // request TIDAK PERNAH membaca field ini dari payload, jadi harus tetap divalidasi
    // sbg numeric (assessment_type asli komponen dari DB), bukan ikut nilai yang dikirim.
    $response = $this->actingAs($user)->put(route('guru.asesmen.update-nilai', $asesmen), [
        'nilai' => [
            $siswaSatu->id => [
                $komponenNumeric->id => ['nilai_angka' => 80, 'assessment_type' => 'predicate'],
            ],
        ],
    ]);

    $response->assertSessionDoesntHaveErrors("nilai.{$siswaSatu->id}.{$komponenNumeric->id}.nilai_angka");
});
```

- [ ] **Step 8: UI `asesmen/show.blade.php` — render kondisional per tipe**

Ganti blok `@foreach ($komponenList as $komponen)` di dalam `<tbody>` (yang sebelumnya selalu render 2 `<input>`) persis sesuai kode di spec §8 (numeric: input angka + catatan; narrative: textarea wajib; predicate: select BB/MB/BSH/BSB + catatan opsional). Tambah `use App\Domains\Akademik\Enums\AssessmentType;`/`PredikatPaud` import di bagian `@php` atas file kalau perlu, atau pakai FQCN langsung di blade seperti dicontohkan spec.

- [ ] **Step 9: Jalankan seluruh test scoped Task 4**

Run: `php artisan test --filter="UpdateNilaiSiswaValidationTest|SimpanNilaiSiswaActionInvariantTest"`
Expected: semua PASS (1 test 2-siswa-2-komponen + 1 test assessment_type-diabaikan + 3 test invariant Action via `->with()`).

- [ ] **Step 10: Commit**

```bash
git add app/Http/Requests/Akademik/UpdateNilaiSiswaRequest.php app/Domains/Akademik/DataTransferObjects/NilaiSiswaBatchData.php app/Domains/Akademik/Actions/Penilaian/SimpanNilaiSiswaAction.php resources/views/portals/guru/akademik/asesmen/show.blade.php tests/Feature/Akademik/UpdateNilaiSiswaValidationTest.php tests/Unit/Actions/SimpanNilaiSiswaActionInvariantTest.php
git commit -m "feat(akademik): validasi kondisional 2-layer + UI input nilai per assessment_type"
```

---

### Task 5: Perbaiki 3 Consumer Bucket C (Correctness, Bukan Sekadar Non-Crash)

**Files:**
- Modify: `app/Domains/Akademik/Services/RaporCalculationService.php`
- Modify: `app/Domains/Akademik/Services/CapaianKompetensiGenerator.php`
- Modify: `app/Services/DashboardStatsService.php`
- Test: `tests/Unit/Services/RaporCalculationServiceAssessmentTypeTest.php`
- Test: `tests/Feature/Akademik/CapaianKompetensiGeneratorAssessmentTypeTest.php`
- Test: `tests/Feature/DashboardStatsServiceAssessmentTypeTest.php`

**Interfaces:**
- Consumes: `KomponenPenilaian.assessment_type` (Task 1).
- Produces: hasil hitung yang benar walau ada campuran tipe dalam satu kelas/semester.

- [ ] **Step 1: `RaporCalculationService::hitungRekapKelas()` — filter eksplisit ke numeric**

Ganti baris:
```php
$scores = $allNilai->whereIn('asesmen_id', $subjekAsesmenIds)
    ->where('siswa_id', $siswa->id)
    ->whereNotNull('nilai_angka');
```
menjadi (filter berdasarkan `assessment_type` komponen, bukan lagi proxy `whereNotNull`):
```php
$scores = $allNilai->whereIn('asesmen_id', $subjekAsesmenIds)
    ->where('siswa_id', $siswa->id)
    ->filter(fn ($n) => $n->komponenPenilaian?->assessment_type?->value === 'numeric' && $n->nilai_angka !== null);
```
(`$allNilai` sudah eager-load `komponenPenilaian` sejak kode existing — tidak perlu query tambahan.)

- [ ] **Step 2: `CapaianKompetensiGenerator::generateNarasi()` — filter komponen ke numeric**

Ganti query `$komponenList`:
```php
$komponenList = KomponenPenilaian::where('subjek_type', $subjek->getMorphClass())
    ->where('subjek_id', $subjek->getKey())
    ->where('semester_id', $semester->id)
    ->where('assessment_type', 'numeric')
    ->get();
```

- [ ] **Step 3: `DashboardStatsService::statistikProgressRaporKelas()` — samakan numerator & denominator**

Ganti:
```php
$totalKomponen = KomponenPenilaian::where('semester_id', $semester->id)
    ->whereHasMorph('subjek', [MataPelajaran::class], fn ($q) => $q->where('lembaga_id', $kelas->lembaga_id))
    ->count();
```
menjadi (tambah filter `assessment_type`):
```php
$totalKomponen = KomponenPenilaian::where('semester_id', $semester->id)
    ->where('assessment_type', 'numeric')
    ->whereHasMorph('subjek', [MataPelajaran::class], fn ($q) => $q->where('lembaga_id', $kelas->lembaga_id))
    ->count();
```
`$totalTerisi` (baris di bawahnya, `NilaiSiswa::...->whereNotNull('nilai_angka')->...->count()`) TIDAK perlu diubah — begitu `$totalKomponen` sudah dibatasi numeric, keduanya otomatis konsisten lagi (komponen non-numeric tidak pernah punya `nilai_angka` terisi, jadi tidak pernah menyumbang ke `$totalTerisi` juga).

- [ ] **Step 4: Test — C1 (`RaporCalculationService`)**

```php
<?php
// tests/Unit/Services/RaporCalculationServiceAssessmentTypeTest.php

use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Services\RaporCalculationService;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('excludes non-numeric komponen from the weighted average entirely, keeping the numeric-only result unchanged', function () {
    $mapel = MataPelajaran::factory()->create();
    $lembaga = $mapel->lembaga_id;
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga, 'kelas_id' => $kelas->id]);

    $komponenNumeric = KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
        'lembaga_id' => $lembaga, 'assessment_type' => 'numeric', 'bobot' => 100,
    ]);
    $komponenNarrative = KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
        'lembaga_id' => $lembaga, 'assessment_type' => 'narrative', 'bobot' => 100,
    ]);

    $asesmen = Asesmen::factory()->create([
        'guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
    ]);
    $asesmen->komponenPenilaian()->attach([$komponenNumeric->id, $komponenNarrative->id]);

    NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenNumeric->id, 'nilai_angka' => 80]);
    NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenNarrative->id, 'nilai_angka' => null, 'catatan' => 'Deskripsi perkembangan']);

    $rekap = app(RaporCalculationService::class)->hitungRekapKelas($kelas, $semester);

    // Hanya komponen numeric yang menyumbang -- hasilnya harus 80 persis,
    // BUKAN rata-rata tertimbang yang bergeser krn komponen narrative ikut
    // dihitung sbg "kosong"/mempengaruhi total bobot.
    $key = 'mata_pelajaran:'.$mapel->id;
    expect($rekap['rekapNilai'][$siswa->id][$key])->toBe(80.0);
});
```

- [ ] **Step 5: Test — C2 (`CapaianKompetensiGenerator`)**

```php
<?php
// tests/Feature/Akademik/CapaianKompetensiGeneratorAssessmentTypeTest.php

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

it('only ranks numeric-type komponen when generating narasi tertinggi/terendah', function () {
    $mapel = MataPelajaran::factory()->create();
    $lembaga = $mapel->lembaga_id;
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga, 'kelas_id' => $kelas->id]);

    $komponenNumeric = KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
        'lembaga_id' => $lembaga, 'assessment_type' => 'numeric', 'kktp_minimal' => 75, 'deskripsi' => 'Numerik A',
    ]);
    $komponenNarrative = KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
        'lembaga_id' => $lembaga, 'assessment_type' => 'narrative', 'deskripsi' => 'Naratif B',
    ]);

    $asesmen = Asesmen::factory()->create([
        'guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
    ]);
    $asesmen->komponenPenilaian()->attach([$komponenNumeric->id, $komponenNarrative->id]);

    NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenNumeric->id, 'nilai_angka' => 90]);
    NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenNarrative->id, 'catatan' => 'Deskripsi'.random_int(1, 1000)]);

    $narasi = app(CapaianKompetensiGenerator::class)->generateNarasi($siswa, $mapel, $semester);

    expect($narasi['tertinggi'])->toContain('Numerik A');
    expect($narasi['tertinggi'])->not->toContain('Naratif B');
});
```

- [ ] **Step 6: Test — C3 (`DashboardStatsService`)**

```php
<?php
// tests/Feature/DashboardStatsServiceAssessmentTypeTest.php

use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use App\Services\DashboardStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('reaches 100 percent progress when every numeric komponen is filled, even with narrative komponen present and unfilled', function () {
    $mapel = MataPelajaran::factory()->create();
    $lembaga = $mapel->lembaga_id;
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga, 'kelas_id' => $kelas->id]);

    $komponenNumeric = KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
        'lembaga_id' => $lembaga, 'assessment_type' => 'numeric',
    ]);
    KomponenPenilaian::factory()->create([
        'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
        'lembaga_id' => $lembaga, 'assessment_type' => 'narrative',
    ]);

    $asesmen = \App\Domains\Akademik\Models\Asesmen::factory()->create([
        'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id,
    ]);
    NilaiSiswa::create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenNumeric->id, 'nilai_angka' => 80]);

    $hasil = app(DashboardStatsService::class)->statistikProgressRaporKelas($kelas, $semester);

    expect($hasil['persen'])->toBe(100.0);
});
```
**Catatan implementer**: verifikasi signature persis `statistikProgressRaporKelas($kelas, $semester)` (parameter/return shape) langsung dari `DashboardStatsService.php` sebelum finalisasi — sesuaikan kalau berbeda dari yang diasumsikan di sini.

- [ ] **Step 7: Jalankan test scoped**

Run: `php artisan test --filter="RaporCalculationServiceAssessmentTypeTest|CapaianKompetensiGeneratorAssessmentTypeTest|DashboardStatsServiceAssessmentTypeTest"`
Expected: 3 test PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Domains/Akademik/Services/RaporCalculationService.php app/Domains/Akademik/Services/CapaianKompetensiGenerator.php app/Services/DashboardStatsService.php tests/Unit/Services/RaporCalculationServiceAssessmentTypeTest.php tests/Feature/Akademik/CapaianKompetensiGeneratorAssessmentTypeTest.php tests/Feature/DashboardStatsServiceAssessmentTypeTest.php
git commit -m "fix(akademik): C1-C3 filter eksplisit ke assessment_type=numeric, bukan whereNotNull proxy"
```

---

### Task 6: Regresi Numeric-Only, Full Test Suite, Verifikasi Final

**Files:**
- Modify: file test existing yang membuat `KomponenPenilaian`/`Asesmen`/`NilaiSiswa` langsung terkait rekap/rapor (cek `RaporCalculationServiceTest.php`, `CapaianKompetensiGeneratorTest.php`, `RaporPdfDataBuilderTest.php`, `RaporApprovalActionsTest.php`, `GenerateNarasiPerkembanganActionTest.php` — pastikan seluruhnya masih pakai `assessment_type` default `numeric` implisit dan TETAP hijau tanpa perubahan assertion, karena default DB `numeric` menjamin non-breaking).
- Test: pastikan `database/factories/NilaiSiswaFactory.php` support `predikat` state kalau belum (cek dulu).

**Interfaces:**
- Consumes: seluruh hasil Task 1-5.

- [ ] **Step 1: Jalankan seluruh test Akademik utk cek regresi numeric-only**

Run: `php artisan test --filter=Akademik`
Expected: SEMUA PASS tanpa perubahan assertion di file test lama — kalau ada yang gagal, itu artinya perubahan Task 5 TIDAK netral utk kasus all-numeric (pelanggaran Global Constraints "regresi C1-C3: hasil hitung utk all-numeric harus sama") — perbaiki kode, JANGAN ubah assertion test lama supaya lolos.

- [ ] **Step 2: Migrate fresh + seed penuh**

Run: `php artisan migrate:fresh --seed`
Expected: sukses tanpa error (kolom `assessment_type` baru harus tidak mengganggu seeder existing — semua komponen demo otomatis `numeric`).

- [ ] **Step 3: Full test suite (WAJIB izin eksplisit sebelum ini kalau dijalankan sbg bagian dari sesi interaktif — kalau dieksekusi otomatis oleh subagent, boleh langsung jalan sbg tahap akhir plan)**

Run: `php artisan test`
Expected: 0 failed. Skipped yang SUDAH ada dari Sprint 1 (4 test `BackfillSubjekPenilaianMigrationTest`, guard skip krn kolom legacy sudah di-drop) boleh tetap skip — itu bukan regresi baru.

- [ ] **Step 4: Verifikasi grep — tidak ada tempat lain yang diam-diam mengasumsikan numeric**

```bash
git grep -n "nilai_angka" -- 'app/Domains/Akademik/Services/*.php' 'app/Services/DashboardStatsService.php'
```
Bandingkan hasilnya dengan daftar di spec §3 (C1/C2/C3 + Bucket B1/B2) — pastikan tidak ada TEMPAT KEEMPAT yang terlewat dari audit. Kalau ketemu yang baru, laporkan ke user, JANGAN langsung diperbaiki sendiri tanpa konfirmasi (di luar cakupan yang sudah disepakati).

- [ ] **Step 5: Commit final**

```bash
git add -A
git commit -m "test(akademik): verifikasi regresi numeric-only Sprint 2 + full suite hijau"
```

---

## Self-Review

- **Cakupan spec**: tiap bagian spec (§1 skema, §2 enum, §3 audit C1-C3, §4 UI komponen, §5 DTO/Action, §6 validasi request, §7 invariant Action, §8 UI nilai, §9 test matrix) punya task yang mengimplementasikannya — Task 1 (§1,§2), Task 2 (§5 DTO/Action/Request komponen), Task 3 (§4), Task 4 (§6,§7,§8, plus RED wildcard wajib dari catatan review), Task 5 (§3 C1-C3), Task 6 (§9 regresi + non-goal terjaga).
- **2 koreksi review kedua**: test matrix Numeric+predikat sekarang eksplisit ❌ ditolak (Task 4 Step 1, via `assertSessionHasErrors`), dipisah dari test invariant Action (Task 4 Step 6, `->with()` datasets) yang menguji Layer 2 secara independen. DTO `assessmentType` nullable dgn null→default/valid→override/invalid→reject (Task 2 Step 1,3,6).
- **Placeholder scan**: tidak ada instruksi tanpa kode konkret. 3 titik "Catatan implementer" (pola wildcard Task 4 Step 1-3, nama route Task 3 Step 2, signature `statistikProgressRaporKelas` Task 5 Step 6) adalah verifikasi runtime jujur, bukan placeholder — masing-masing punya instruksi jelas "cek dulu sebelum finalisasi".
- **Non-goal dijaga eksplisit**: tidak ada task yang menyentuh `RaporPdfDataBuilder` (rapor PDF), progress bar `asesmen/show.blade.php` (B1), atau `$nilaiTerbaru` dashboard (B2) — sesuai keputusan review "jangan menambah fitur lain".
