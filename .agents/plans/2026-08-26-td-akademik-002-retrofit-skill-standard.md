# TD-AKADEMIK-002 — Retrofit ke `laravel-feature-standard` Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Retrofit 2 controller (`Admin\FaseDefaultMappingController`, `Admin\KelasController`) supaya patuh `laravel-feature-standard` (FormRequest+DTO+Action), dan pindahkan folder `Support/` (bukan folder resmi skill) ke `Services/` — SEMUA sbg refactor internal murni, TANPA mengubah behavior/HTTP status code apa pun yang sudah ada.

**Architecture:** 3 bagian independen (A: pindah folder, B: retrofit FaseDefaultMappingController, C: retrofit KelasController). Tidak ada migration, tidak ada perubahan skema DB. Setiap bagian: pindahkan logic existing ke layer yang benar (FormRequest = validasi format, Action = business/ownership logic, DTO = boundary), TANPA mengubah urutan/hasil logic itu sendiri.

**Tech Stack:** Laravel 12.63.0, Pest, PHP 8.x.

**Bergantung pada:** Sprint 1-5 Fondasi Akademik Multi-Jenjang (SELESAI semua).

**Spec:** `.agents/specs/2026-08-26-td-akademik-002-retrofit-skill-standard.md`

## Global Constraints

- **Prinsip utama: refactor internal murni, BUKAN kesempatan mengubah behavior.** Setiap validasi/abort/pesan error/HTTP status code yang ada SEKARANG harus identik setelah refactor. Kalau menemukan celah/bug saat membaca ulang kode lama, STOP dan laporkan ke user — JANGAN diam-diam "sekalian diperbaiki".
- **DILARANG menambah `exists:` rule baru** di `StoreKelasRequest`/`UpdateKelasRequest` untuk `tahun_ajaran_id`/`wali_kelas_guru_id`/`pola_jam_id` — kode existing sengaja pakai `integer` polos + manual `find()`+`abort_if(...,404)`, BUKAN `exists:` (yang akan mengubah response dari 404 jadi 422). `fase_id` SATU-SATUNYA field yang sudah punya `exists:fase,id` sejak Sprint 3 — itu saja yang dipertahankan.
- **Uniqueness check & `authorizeMappingScope()` di `FaseDefaultMappingController` TETAP di controller**, TIDAK pindah ke Action — keduanya menghasilkan HTTP response (`back()->withErrors()`, `abort(403)`), bukan business mutation murni.
- **`lembaga_id` override di `CreateKelasAction`**: SUDAH diverifikasi aman disertakan tanpa kondisional (lihat spec §Bagian C) — `BelongsToTenant::bootBelongsToTenant()` mengecek `$model->lembaga_id === null` di event `creating`, jadi baik `null` maupun ID eksplisit sama-sama aman.
- TIDAK menyentuh method lain di kedua controller (`index`, `create`, `edit`, `destroy`, `faseSuggestion`) — HANYA `store()`/`update()`.
- TIDAK menyentuh `TD-AKADEMIK-001` (`ElemenCp`) — debt terpisah.
- Jalankan test scoped di tiap task; full suite HANYA di task terakhir.

---

### Task 1: Pindahkan `Support/` → `Services/`

**Files:**
- Move: `app/Domains/Akademik/Support/SubjekPenilaianKey.php` → `app/Domains/Akademik/Services/SubjekPenilaianKey.php`
- Move: `app/Domains/Akademik/Support/AcademicProfile.php` → `app/Domains/Akademik/Services/AcademicProfile.php`
- Move: `tests/Unit/Support/SubjekPenilaianKeyTest.php` → `tests/Unit/Services/SubjekPenilaianKeyTest.php`
- Move: `tests/Unit/Support/AcademicProfileTest.php` → `tests/Unit/Services/AcademicProfileTest.php`
- Modify: `app/Domains/Akademik/Services/RaporPdfDataBuilder.php`
- Modify: `app/Domains/Akademik/Services/RaporCalculationService.php`

**Interfaces:**
- Produces: `App\Domains\Akademik\Services\SubjekPenilaianKey`, `App\Domains\Akademik\Services\AcademicProfile` — namespace baru dipakai Task 2 (Action baru akan `use` `SubjekPenilaianKey` kalau perlu — cek per task, tapi tidak wajib).

- [ ] **Step 1: Baca isi 2 file yang akan dipindah (WAJIB — verifikasi baseline sebelum pindah)**

Baca `app/Domains/Akademik/Support/SubjekPenilaianKey.php` dan `app/Domains/Akademik/Support/AcademicProfile.php` — catat isi lengkapnya (hanya baris `namespace` yang berubah, isi lain PERSIS sama).

- [ ] **Step 2: Pindahkan `SubjekPenilaianKey.php`**

Buat `app/Domains/Akademik/Services/SubjekPenilaianKey.php` dgn isi PERSIS file lama, HANYA baris `namespace App\Domains\Akademik\Support;` diganti `namespace App\Domains\Akademik\Services;`. Hapus file lama `app/Domains/Akademik/Support/SubjekPenilaianKey.php`.

- [ ] **Step 3: Pindahkan `AcademicProfile.php`**

Buat `app/Domains/Akademik/Services/AcademicProfile.php` dgn isi PERSIS file lama, HANYA baris `namespace App\Domains\Akademik\Support;` diganti `namespace App\Domains\Akademik\Services;`. Hapus file lama `app/Domains/Akademik/Support/AcademicProfile.php`.

- [ ] **Step 4: Update 2 file consumer**

`app/Domains/Akademik/Services/RaporPdfDataBuilder.php`: ganti
```php
use App\Domains\Akademik\Support\SubjekPenilaianKey;
```
menjadi
```php
use App\Domains\Akademik\Services\SubjekPenilaianKey;
```
Dan (dari Sprint 5) ganti
```php
use App\Domains\Akademik\Support\AcademicProfile;
```
menjadi
```php
use App\Domains\Akademik\Services\AcademicProfile;
```

`app/Domains/Akademik/Services/RaporCalculationService.php`: ganti
```php
use App\Domains\Akademik\Support\SubjekPenilaianKey;
```
menjadi
```php
use App\Domains\Akademik\Services\SubjekPenilaianKey;
```

**Catatan**: kedua file consumer sudah berada di namespace `App\Domains\Akademik\Services` — secara teknis `use` bisa dihapus total (class di namespace sama tidak perlu `use`), TAPI pertahankan `use` eksplisit apa adanya (cuma ganti path) supaya diff minimal dan mudah direview — jangan sekalian "membersihkan" `use` yang jadi redundan, itu di luar scope task ini.

- [ ] **Step 5: Pindahkan 2 file test**

Sama polanya: copy isi, ganti `namespace`/`use` yang merujuk `Support` jadi `Services`, hapus file lama di `tests/Unit/Support/`.

- [ ] **Step 6: Hapus folder kosong**

```bash
rmdir app/Domains/Akademik/Support 2>/dev/null || true
rmdir tests/Unit/Support 2>/dev/null || true
```
Verifikasi kedua folder benar-benar kosong sebelum ini (`ls app/Domains/Akademik/Support` harus "No such file or directory" atau kosong) — kalau ternyata masih ada file lain di situ yang tidak disebut plan ini, STOP dan laporkan ke user.

- [ ] **Step 7: Verifikasi tidak ada referensi tersisa**

```bash
grep -rn "Domains\\\\Akademik\\\\Support" app/ tests/
```
Expected: nol hasil.

- [ ] **Step 8: Jalankan test scoped**

```bash
php artisan test --filter=SubjekPenilaianKeyTest
php artisan test --filter=AcademicProfileTest
php artisan test --filter=RaporPdfDataBuilderTest
php artisan test --filter=RaporCalculationServiceTest
```
Expected: semua PASS, jumlah test sama persis dgn sebelum dipindah (murni pindah lokasi, bukan tambah/kurang test).

- [ ] **Step 9: `php -l` dan commit**

```bash
php -l app/Domains/Akademik/Services/SubjekPenilaianKey.php
php -l app/Domains/Akademik/Services/AcademicProfile.php
php -l app/Domains/Akademik/Services/RaporPdfDataBuilder.php
php -l app/Domains/Akademik/Services/RaporCalculationService.php
git add -A app/Domains/Akademik/Support app/Domains/Akademik/Services tests/Unit/Support tests/Unit/Services
git commit -m "refactor(akademik): pindahkan Support/ ke Services/ - Support/ bukan folder resmi laravel-feature-standard"
```

---

### Task 2: Retrofit `Admin\FaseDefaultMappingController`

**Files:**
- Create: `app/Domains/Akademik/DataTransferObjects/FaseDefaultMappingData.php`
- Create: `app/Domains/Akademik/Actions/FaseMapping/CreateFaseDefaultMappingAction.php`
- Create: `app/Domains/Akademik/Actions/FaseMapping/UpdateFaseDefaultMappingAction.php`
- Create: `app/Http/Requests/Akademik/StoreFaseDefaultMappingRequest.php`
- Create: `app/Http/Requests/Akademik/UpdateFaseDefaultMappingRequest.php`
- Modify: `app/Http/Controllers/Admin/FaseDefaultMappingController.php`
- Create: `tests/Unit/Domains/Akademik/Actions/FaseMapping/CreateFaseDefaultMappingActionTest.php`
- Create: `tests/Unit/Domains/Akademik/Actions/FaseMapping/UpdateFaseDefaultMappingActionTest.php`

**Interfaces:**
- Consumes: `App\Domains\Akademik\Models\FaseDefaultMapping` (existing, Sprint 3).
- Produces: `FaseDefaultMappingData(string $bentukPendidikan, ?string $tingkat, int $faseId, ?int $lembagaId)`, `CreateFaseDefaultMappingAction::execute(FaseDefaultMappingData): FaseDefaultMapping`, `UpdateFaseDefaultMappingAction::execute(FaseDefaultMapping, FaseDefaultMappingData): FaseDefaultMapping` — dipakai controller Step 6.

- [ ] **Step 1: Baca isi controller existing (WAJIB — verifikasi baseline)**

Baca `app/Http/Controllers/Admin/FaseDefaultMappingController.php` lengkap. Bandingkan `store()`/`update()` dengan kutipan di spec §Bagian B — kalau berbeda, STOP dan laporkan ke user.

- [ ] **Step 2: Buat DTO `FaseDefaultMappingData`**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class FaseDefaultMappingData
{
    public function __construct(
        public string $bentukPendidikan,
        public ?string $tingkat,
        public int $faseId,
        public ?int $lembagaId,
    ) {}
}
```

- [ ] **Step 3: Test Action (RED dulu)**

```php
<?php
// tests/Unit/Domains/Akademik/Actions/FaseMapping/CreateFaseDefaultMappingActionTest.php

use App\Domains\Akademik\Actions\FaseMapping\CreateFaseDefaultMappingAction;
use App\Domains\Akademik\DataTransferObjects\FaseDefaultMappingData;
use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;
use App\Models\Lembaga;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a platform-wide mapping when lembagaId is null', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);

    $mapping = app(CreateFaseDefaultMappingAction::class)->execute(new FaseDefaultMappingData(
        bentukPendidikan: 'SD',
        tingkat: '1',
        faseId: $fase->id,
        lembagaId: null,
    ));

    expect($mapping->fresh()->lembaga_id)->toBeNull();
    expect($mapping->fresh()->bentuk_pendidikan)->toBe('SD');
    expect($mapping->fresh()->tingkat)->toBe('1');
    expect($mapping->fresh()->fase_id)->toBe($fase->id);
});

it('creates a lembaga-specific mapping when lembagaId is provided', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $lembaga = Lembaga::factory()->create();

    $mapping = app(CreateFaseDefaultMappingAction::class)->execute(new FaseDefaultMappingData(
        bentukPendidikan: 'SD',
        tingkat: null,
        faseId: $fase->id,
        lembagaId: $lembaga->id,
    ));

    expect($mapping->fresh()->lembaga_id)->toBe($lembaga->id);
    expect($mapping->fresh()->tingkat)->toBeNull();
});
```

```php
<?php
// tests/Unit/Domains/Akademik/Actions/FaseMapping/UpdateFaseDefaultMappingActionTest.php

use App\Domains\Akademik\Actions\FaseMapping\UpdateFaseDefaultMappingAction;
use App\Domains\Akademik\DataTransferObjects\FaseDefaultMappingData;
use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('updates bentuk_pendidikan, tingkat, and fase_id without touching lembaga_id', function () {
    $faseA = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $faseB = Fase::create(['kode' => 'b', 'nama' => 'Fase B', 'urutan' => 2]);
    $mapping = FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $faseA->id]);

    $hasil = app(UpdateFaseDefaultMappingAction::class)->execute($mapping, new FaseDefaultMappingData(
        bentukPendidikan: 'SD',
        tingkat: '2',
        faseId: $faseB->id,
        lembagaId: null,
    ));

    expect($hasil->fresh()->tingkat)->toBe('2');
    expect($hasil->fresh()->fase_id)->toBe($faseB->id);
    expect($hasil->fresh()->lembaga_id)->toBeNull();
});
```

Run: `php artisan test --filter=CreateFaseDefaultMappingActionTest`
Run: `php artisan test --filter=UpdateFaseDefaultMappingActionTest`
Expected: FAIL — Action belum ada.

- [ ] **Step 4: Implementasi Action**

```php
<?php
// app/Domains/Akademik/Actions/FaseMapping/CreateFaseDefaultMappingAction.php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\FaseMapping;

use App\Domains\Akademik\DataTransferObjects\FaseDefaultMappingData;
use App\Domains\Akademik\Models\FaseDefaultMapping;

final class CreateFaseDefaultMappingAction
{
    public function execute(FaseDefaultMappingData $data): FaseDefaultMapping
    {
        return FaseDefaultMapping::create([
            'lembaga_id' => $data->lembagaId,
            'bentuk_pendidikan' => $data->bentukPendidikan,
            'tingkat' => $data->tingkat,
            'fase_id' => $data->faseId,
        ]);
    }
}
```

```php
<?php
// app/Domains/Akademik/Actions/FaseMapping/UpdateFaseDefaultMappingAction.php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\FaseMapping;

use App\Domains\Akademik\DataTransferObjects\FaseDefaultMappingData;
use App\Domains\Akademik\Models\FaseDefaultMapping;

final class UpdateFaseDefaultMappingAction
{
    public function execute(FaseDefaultMapping $mapping, FaseDefaultMappingData $data): FaseDefaultMapping
    {
        $mapping->update([
            'bentuk_pendidikan' => $data->bentukPendidikan,
            'tingkat' => $data->tingkat,
            'fase_id' => $data->faseId,
        ]);

        return $mapping;
    }
}
```

- [ ] **Step 5: Jalankan test Action lagi**

Run: `php artisan test --filter=CreateFaseDefaultMappingActionTest`
Run: `php artisan test --filter=UpdateFaseDefaultMappingActionTest`
Expected: PASS.

- [ ] **Step 6: Buat 2 FormRequest**

```php
<?php
// app/Http/Requests/Akademik/StoreFaseDefaultMappingRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreFaseDefaultMappingRequest extends FormRequest
{
    public const BENTUK_PENDIDIKAN = ['KB', 'TPA', 'SPS', 'TK', 'SD', 'SMP', 'SMA', 'SMK', 'SLB'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bentuk_pendidikan' => ['required', Rule::in(self::BENTUK_PENDIDIKAN)],
            'tingkat' => ['nullable', 'string', 'max:10'],
            'fase_id' => ['required', 'exists:fase,id'],
            'lembaga_id' => ['nullable', 'integer', 'exists:lembaga,id'],
        ];
    }
}
```

```php
<?php
// app/Http/Requests/Akademik/UpdateFaseDefaultMappingRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateFaseDefaultMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bentuk_pendidikan' => ['required', Rule::in(StoreFaseDefaultMappingRequest::BENTUK_PENDIDIKAN)],
            'tingkat' => ['nullable', 'string', 'max:10'],
            'fase_id' => ['required', 'exists:fase,id'],
        ];
    }
}
```

- [ ] **Step 7: Ubah controller — `store()` dan `update()`**

Baca file `app/Http/Controllers/Admin/FaseDefaultMappingController.php` (sudah dibaca Step 1). Tambah `use` baru:
```php
use App\Domains\Akademik\Actions\FaseMapping\CreateFaseDefaultMappingAction;
use App\Domains\Akademik\Actions\FaseMapping\UpdateFaseDefaultMappingAction;
use App\Domains\Akademik\DataTransferObjects\FaseDefaultMappingData;
use App\Http\Requests\Akademik\StoreFaseDefaultMappingRequest;
use App\Http\Requests\Akademik\UpdateFaseDefaultMappingRequest;
```
Hapus `use Illuminate\Http\Request;` KALAU tidak dipakai method lain di file yang sama (`index`/`create`/`edit`/`destroy` masih pakai `Request $request` sbg parameter — JANGAN dihapus, cuma `store`/`update` yang ganti type-hint).
Hapus `use Illuminate\Validation\Rule;` KALAU sudah tidak dipakai di controller (rule pindah ke FormRequest) — cek dulu apakah dipakai di tempat lain di file yang sama sebelum menghapus.

Ganti signature & isi `store()`:
```php
public function store(StoreFaseDefaultMappingRequest $request, CreateFaseDefaultMappingAction $action): RedirectResponse
{
    $this->authorize('fase-mapping.create');

    $validated = $request->validated();
    $tingkat = $validated['tingkat'] !== '' ? ($validated['tingkat'] ?? null) : null;

    $isPlatformOrYayasan = $this->isPlatformOrYayasan($request);
    $lembagaId = $isPlatformOrYayasan ? ($validated['lembaga_id'] ?? null) : $request->user()->lembaga_id;

    $this->authorizeMappingScope($request, $lembagaId);

    if (FaseDefaultMapping::where('lembaga_id', $lembagaId)->where('bentuk_pendidikan', $validated['bentuk_pendidikan'])->where('tingkat', $tingkat)->exists()) {
        return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada mapping default untuk kombinasi jenjang dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
    }

    $action->execute(new FaseDefaultMappingData(
        bentukPendidikan: $validated['bentuk_pendidikan'],
        tingkat: $tingkat,
        faseId: (int) $validated['fase_id'],
        lembagaId: $lembagaId,
    ));

    return redirect()->route('admin.fase-mapping.index')->with('status', 'Mapping default berhasil disimpan.');
}
```

Ganti signature & isi `update()`:
```php
public function update(UpdateFaseDefaultMappingRequest $request, FaseDefaultMapping $faseMapping, UpdateFaseDefaultMappingAction $action): RedirectResponse
{
    $this->authorize('fase-mapping.edit');
    $this->authorizeMappingScope($request, $faseMapping->lembaga_id);

    $validated = $request->validated();
    $tingkat = $validated['tingkat'] !== '' ? ($validated['tingkat'] ?? null) : null;

    if (FaseDefaultMapping::where('id', '!=', $faseMapping->id)->where('lembaga_id', $faseMapping->lembaga_id)->where('bentuk_pendidikan', $validated['bentuk_pendidikan'])->where('tingkat', $tingkat)->exists()) {
        return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada mapping default untuk kombinasi jenjang dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
    }

    $action->execute($faseMapping, new FaseDefaultMappingData(
        bentukPendidikan: $validated['bentuk_pendidikan'],
        tingkat: $tingkat,
        faseId: (int) $validated['fase_id'],
        lembagaId: $faseMapping->lembaga_id,
    ));

    return redirect()->route('admin.fase-mapping.index')->with('status', 'Mapping default berhasil diperbarui.');
}
```
Method `index()`, `create()`, `edit()`, `destroy()`, `isPlatformOrYayasan()`, `authorizeMappingScope()`, `private const BENTUK_PENDIDIKAN` (kalau masih dipakai `create()`/`edit()` untuk kirim `$bentukPendidikanList` ke view — TETAP DIPERTAHANKAN di controller, TIDAK dihapus, HANYA `store()`/`update()` yang berubah).

- [ ] **Step 8: Jalankan test existing**

```bash
php artisan test --filter=FaseDefaultMappingControllerTest
```
Expected: PASS — SEMUA 9 test dari Sprint 3 harus tetap hijau TANPA perubahan assertion (kalau ada yang gagal, itu berarti behavior berubah — STOP, jangan ubah test untuk "menyesuaikan", cari kenapa Action/FormRequest tidak persis meniru logic lama).

- [ ] **Step 9: `php -l` dan commit**

```bash
php -l app/Domains/Akademik/DataTransferObjects/FaseDefaultMappingData.php
php -l app/Domains/Akademik/Actions/FaseMapping/CreateFaseDefaultMappingAction.php
php -l app/Domains/Akademik/Actions/FaseMapping/UpdateFaseDefaultMappingAction.php
php -l app/Http/Requests/Akademik/StoreFaseDefaultMappingRequest.php
php -l app/Http/Requests/Akademik/UpdateFaseDefaultMappingRequest.php
php -l app/Http/Controllers/Admin/FaseDefaultMappingController.php
git add app/Domains/Akademik/DataTransferObjects/FaseDefaultMappingData.php app/Domains/Akademik/Actions/FaseMapping app/Http/Requests/Akademik/StoreFaseDefaultMappingRequest.php app/Http/Requests/Akademik/UpdateFaseDefaultMappingRequest.php app/Http/Controllers/Admin/FaseDefaultMappingController.php tests/Unit/Domains/Akademik/Actions/FaseMapping
git commit -m "refactor(akademik): retrofit FaseDefaultMappingController ke FormRequest+DTO+Action"
```

---

### Task 3: Retrofit `Admin\KelasController`

**Files:**
- Create: `app/Domains/Akademik/DataTransferObjects/KelasData.php`
- Create: `app/Domains/Akademik/Actions/Kelas/CreateKelasAction.php`
- Create: `app/Domains/Akademik/Actions/Kelas/UpdateKelasAction.php`
- Create: `app/Http/Requests/Akademik/StoreKelasRequest.php`
- Create: `app/Http/Requests/Akademik/UpdateKelasRequest.php`
- Modify: `app/Http/Controllers/Admin/KelasController.php`
- Create: `tests/Unit/Domains/Akademik/Actions/Kelas/CreateKelasActionTest.php`
- Create: `tests/Unit/Domains/Akademik/Actions/Kelas/UpdateKelasActionTest.php`

**Interfaces:**
- Consumes: `App\Models\Kelas`, `App\Models\TahunAjaran`, `App\Models\Guru`, `App\Domains\Akademik\Models\PolaJam` (semua existing, tidak diubah).
- Produces: `KelasData(int $tahunAjaranId, string $nama, ?string $tingkat, ?int $faseId, ?int $waliKelasGuruId, ?int $polaJamId)` dgn static factory `KelasData::fromValidated(array): self`, `CreateKelasAction::execute(KelasData, ?int $lembagaIdOverride = null): Kelas`, `UpdateKelasAction::execute(Kelas, KelasData): Kelas`.

- [ ] **Step 1: Baca isi controller existing (WAJIB — verifikasi baseline)**

Baca `app/Http/Controllers/Admin/KelasController.php` lengkap. Bandingkan `store()`/`update()` dengan kutipan di spec §Bagian C — kalau berbeda, STOP dan laporkan ke user.

- [ ] **Step 2: Buat DTO `KelasData`**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class KelasData
{
    public function __construct(
        public int $tahunAjaranId,
        public string $nama,
        public ?string $tingkat,
        public ?int $faseId,
        public ?int $waliKelasGuruId,
        public ?int $polaJamId,
    ) {}

    public static function fromValidated(array $validated): self
    {
        return new self(
            tahunAjaranId: (int) $validated['tahun_ajaran_id'],
            nama: $validated['nama'],
            tingkat: $validated['tingkat'] ?? null,
            faseId: isset($validated['fase_id']) ? (int) $validated['fase_id'] : null,
            waliKelasGuruId: isset($validated['wali_kelas_guru_id']) && $validated['wali_kelas_guru_id'] !== '' ? (int) $validated['wali_kelas_guru_id'] : null,
            polaJamId: isset($validated['pola_jam_id']) && $validated['pola_jam_id'] !== '' ? (int) $validated['pola_jam_id'] : null,
        );
    }
}
```
**Catatan penting**: cek `!== ''` untuk `waliKelasGuruId`/`polaJamId` MENIRU perilaku `empty($data['wali_kelas_guru_id'])` di kode lama (`empty()` menganggap `''`, `null`, `0`, `'0'` semua kosong) — kalau implementer menemukan bahwa `id` `0` sebenarnya valid di suatu skenario (harusnya tidak mungkin krn auto-increment mulai dari 1), pola `empty()` lama sengaja ditiru apa adanya, JANGAN "diperbaiki" jadi `!== null` murni tanpa melaporkan dulu ke user (itu akan mengubah behavior utk kasus edge `'0'` yang mungkin tidak pernah terjadi tapi tetap merupakan perubahan behavior diam-diam).

- [ ] **Step 3: Test Action (RED dulu)**

```php
<?php
// tests/Unit/Domains/Akademik/Actions/Kelas/CreateKelasActionTest.php

use App\Domains\Akademik\Actions\Kelas\CreateKelasAction;
use App\Domains\Akademik\DataTransferObjects\KelasData;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a kelas with minimal fields', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $kelas = app(CreateKelasAction::class)->execute(new KelasData(
        tahunAjaranId: $tahunAjaran->id,
        nama: 'Kelas 1A',
        tingkat: '1',
        faseId: null,
        waliKelasGuruId: null,
        polaJamId: null,
    ));

    expect($kelas->fresh()->nama)->toBe('Kelas 1A');
    expect($kelas->fresh()->tahun_ajaran_id)->toBe($tahunAjaran->id);
});

it('aborts with 404 when wali_kelas_guru_id belongs to a different lembaga than the tahun ajaran', function () {
    $lembagaA = Lembaga::factory()->create();
    $lembagaB = Lembaga::factory()->create();
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $guruLain = Guru::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembagaB->id])->id,
        'lembaga_id' => $lembagaB->id,
        'nik' => '3201234567899999',
        'nama' => 'Guru Lembaga Lain',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    $execute = fn () => app(CreateKelasAction::class)->execute(new KelasData(
        tahunAjaranId: $tahunAjaran->id,
        nama: 'Kelas 1A',
        tingkat: '1',
        faseId: null,
        waliKelasGuruId: $guruLain->id,
        polaJamId: null,
    ));

    expect($execute)->toThrow(Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
});

it('overrides lembaga_id when provided (yayasan-scope create)', function () {
    $lembagaTarget = Lembaga::factory()->create();
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaTarget->id]);

    $kelas = app(CreateKelasAction::class)->execute(new KelasData(
        tahunAjaranId: $tahunAjaran->id,
        nama: 'Kelas 1A',
        tingkat: '1',
        faseId: null,
        waliKelasGuruId: null,
        polaJamId: null,
    ), lembagaIdOverride: $lembagaTarget->id);

    expect($kelas->fresh()->lembaga_id)->toBe($lembagaTarget->id);
});
```

```php
<?php
// tests/Unit/Domains/Akademik/Actions/Kelas/UpdateKelasActionTest.php

use App\Domains\Akademik\Actions\Kelas\UpdateKelasAction;
use App\Domains\Akademik\DataTransferObjects\KelasData;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('updates kelas fields', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Kelas Lama']);

    $hasil = app(UpdateKelasAction::class)->execute($kelas, new KelasData(
        tahunAjaranId: $tahunAjaran->id,
        nama: 'Kelas Baru',
        tingkat: '2',
        faseId: null,
        waliKelasGuruId: null,
        polaJamId: null,
    ));

    expect($hasil->fresh()->nama)->toBe('Kelas Baru');
    expect($hasil->fresh()->tingkat)->toBe('2');
});

it('aborts with 404 when tahun_ajaran belongs to a different lembaga than the kelas', function () {
    $lembagaA = Lembaga::factory()->create();
    $lembagaB = Lembaga::factory()->create();
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembagaA->id]);

    $execute = fn () => app(UpdateKelasAction::class)->execute($kelas, new KelasData(
        tahunAjaranId: $tahunAjaranLain->id,
        nama: $kelas->nama,
        tingkat: $kelas->tingkat,
        faseId: null,
        waliKelasGuruId: null,
        polaJamId: null,
    ));

    expect($execute)->toThrow(Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
});
```

Run: `php artisan test --filter=CreateKelasActionTest`
Run: `php artisan test --filter=UpdateKelasActionTest`
Expected: FAIL — Action belum ada.

- [ ] **Step 4: Implementasi Action**

```php
<?php
// app/Domains/Akademik/Actions/Kelas/CreateKelasAction.php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Kelas;

use App\Domains\Akademik\DataTransferObjects\KelasData;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\TahunAjaran;

final class CreateKelasAction
{
    public function execute(KelasData $data, ?int $lembagaIdOverride = null): Kelas
    {
        $tahunAjaran = TahunAjaran::find($data->tahunAjaranId);
        abort_if($tahunAjaran === null, 404);

        $waliKelasGuruId = null;
        if ($data->waliKelasGuruId !== null) {
            $guru = Guru::find($data->waliKelasGuruId);
            abort_if($guru === null || $guru->lembaga_id !== $tahunAjaran->lembaga_id, 404);
            $waliKelasGuruId = $guru->id;
        }

        $polaJamId = null;
        if ($data->polaJamId !== null) {
            $polaJam = PolaJam::find($data->polaJamId);
            abort_if($polaJam === null || $polaJam->lembaga_id !== $tahunAjaran->lembaga_id, 404);
            $polaJamId = $polaJam->id;
        }

        return Kelas::create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => $data->nama,
            'tingkat' => $data->tingkat,
            'fase_id' => $data->faseId,
            'wali_kelas_guru_id' => $waliKelasGuruId,
            'pola_jam_id' => $polaJamId,
            'lembaga_id' => $lembagaIdOverride,
        ]);
    }
}
```

```php
<?php
// app/Domains/Akademik/Actions/Kelas/UpdateKelasAction.php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Kelas;

use App\Domains\Akademik\DataTransferObjects\KelasData;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\TahunAjaran;

final class UpdateKelasAction
{
    public function execute(Kelas $kelas, KelasData $data): Kelas
    {
        $tahunAjaran = TahunAjaran::find($data->tahunAjaranId);
        abort_if($tahunAjaran === null || $tahunAjaran->lembaga_id !== $kelas->lembaga_id, 404);

        $waliKelasGuruId = null;
        if ($data->waliKelasGuruId !== null) {
            $guru = Guru::find($data->waliKelasGuruId);
            abort_if($guru === null || $guru->lembaga_id !== $kelas->lembaga_id, 404);
            $waliKelasGuruId = $guru->id;
        }

        $polaJamId = null;
        if ($data->polaJamId !== null) {
            $polaJam = PolaJam::find($data->polaJamId);
            abort_if($polaJam === null || $polaJam->lembaga_id !== $kelas->lembaga_id, 404);
            $polaJamId = $polaJam->id;
        }

        $kelas->update([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => $data->nama,
            'tingkat' => $data->tingkat,
            'fase_id' => $data->faseId,
            'wali_kelas_guru_id' => $waliKelasGuruId,
            'pola_jam_id' => $polaJamId,
        ]);

        return $kelas;
    }
}
```

- [ ] **Step 5: Jalankan test Action lagi**

Run: `php artisan test --filter=CreateKelasActionTest`
Run: `php artisan test --filter=UpdateKelasActionTest`
Expected: PASS.

- [ ] **Step 6: Buat 2 FormRequest**

```php
<?php
// app/Http/Requests/Akademik/StoreKelasRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use Illuminate\Foundation\Http\FormRequest;

final class StoreKelasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tahun_ajaran_id' => ['required', 'integer'],
            'nama' => ['required', 'string', 'max:255'],
            'tingkat' => ['nullable', 'string', 'max:20'],
            'fase_id' => ['nullable', 'integer', 'exists:fase,id'],
            'wali_kelas_guru_id' => ['nullable', 'integer'],
            'pola_jam_id' => ['nullable', 'integer'],
        ];
    }
}
```

```php
<?php
// app/Http/Requests/Akademik/UpdateKelasRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateKelasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tahun_ajaran_id' => ['required', 'integer'],
            'nama' => ['required', 'string', 'max:255'],
            'tingkat' => ['nullable', 'string', 'max:20'],
            'fase_id' => ['nullable', 'integer', 'exists:fase,id'],
            'wali_kelas_guru_id' => ['nullable', 'integer'],
            'pola_jam_id' => ['nullable', 'integer'],
        ];
    }
}
```

- [ ] **Step 7: Ubah controller — `store()` dan `update()`**

Baca file `app/Http/Controllers/Admin/KelasController.php` (sudah dibaca Step 1). Tambah `use` baru:
```php
use App\Domains\Akademik\Actions\Kelas\CreateKelasAction;
use App\Domains\Akademik\Actions\Kelas\UpdateKelasAction;
use App\Domains\Akademik\DataTransferObjects\KelasData;
use App\Http\Requests\Akademik\StoreKelasRequest;
use App\Http\Requests\Akademik\UpdateKelasRequest;
```
`use Illuminate\Http\Request;` TETAP DIPERTAHANKAN (dipakai `index()`/`faseSuggestion()`).

Ganti signature & isi `store()`:
```php
public function store(StoreKelasRequest $request, CreateKelasAction $action): RedirectResponse
{
    $this->authorize('kelas.create');

    $data = KelasData::fromValidated($request->validated());

    $lembagaIdOverride = null;
    if ($request->user()->widestScopeLevel() === 'yayasan') {
        $lembagaIdOverride = session('active_lembaga_id');

        if ($lembagaIdOverride === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat kelas.'])->withInput();
        }
    }

    $action->execute($data, $lembagaIdOverride);

    return redirect()->route('admin.kelas.index')->with('status', 'Kelas berhasil disimpan.');
}
```

Ganti signature & isi `update()`:
```php
public function update(UpdateKelasRequest $request, Kelas $kelas, UpdateKelasAction $action): RedirectResponse
{
    $this->authorize('kelas.edit');

    $data = KelasData::fromValidated($request->validated());

    $action->execute($kelas, $data);

    return redirect()->route('admin.kelas.index')->with('status', 'Kelas berhasil diperbarui.');
}
```
Method `index()`, `create()`, `edit()`, `faseSuggestion()` TIDAK berubah.

- [ ] **Step 8: Jalankan SEMUA test existing yang menyentuh Kelas CRUD**

```bash
php artisan test --filter=KelasCrudTest
php artisan test --filter=KelasPolaJamTest
php artisan test --filter=KelasFaseAssignmentTest
php artisan test --filter=KelasFaseSuggestionTest
```
Expected: PASS — SEMUA test dari sebelum retrofit (termasuk 10 test `KelasCrudTest` yang menguji ownership-check 404 utk lintas lembaga) harus tetap hijau TANPA perubahan assertion. Kalau ada yang gagal, STOP — itu tandanya ada perbedaan behavior yang tidak sengaja, JANGAN ubah test untuk menyesuaikan.

- [ ] **Step 9: `php -l` dan commit**

```bash
php -l app/Domains/Akademik/DataTransferObjects/KelasData.php
php -l app/Domains/Akademik/Actions/Kelas/CreateKelasAction.php
php -l app/Domains/Akademik/Actions/Kelas/UpdateKelasAction.php
php -l app/Http/Requests/Akademik/StoreKelasRequest.php
php -l app/Http/Requests/Akademik/UpdateKelasRequest.php
php -l app/Http/Controllers/Admin/KelasController.php
git add app/Domains/Akademik/DataTransferObjects/KelasData.php app/Domains/Akademik/Actions/Kelas app/Http/Requests/Akademik/StoreKelasRequest.php app/Http/Requests/Akademik/UpdateKelasRequest.php app/Http/Controllers/Admin/KelasController.php tests/Unit/Domains/Akademik/Actions/Kelas
git commit -m "refactor(akademik): retrofit KelasController ke FormRequest+DTO+Action (full, termasuk field pre-existing)"
```

---

### Task 4: Regresi Penuh

**Files:** Tidak ada file baru — task verifikasi murni.

- [ ] **Step 1: Jalankan full test suite (TANPA filter), sekali, foreground, tidak ada proses lain berjalan bersamaan**

Run: `php artisan test`
Expected: 0 failed. Baseline sebelum retrofit adalah **2230 passed, 4 skipped** (state akhir Sprint 5). Task 2-3 menambah 5 test Action baru (2+3). Laporkan angka NYATA, jangan asumsikan.

- [ ] **Step 2: Verifikasi manual tambahan — cek tidak ada `Support\` yang tersisa di seluruh codebase (bukan cuma app/tests)**

```bash
grep -rn "Domains\\\\Akademik\\\\Support" --include=*.php .
```
Expected: nol hasil (kalau ada hasil di `.agents/` — itu arsip dokumentasi historis, BOLEH tetap ada, JANGAN diubah; hanya file `.php` aktif yang harus nol).

- [ ] **Step 3: Laporkan hasil final ke user**

Ringkasan: jumlah test pass/fail (angka pasti), commit hash tiap task (4 commit), konfirmasi tidak ada behavior/HTTP-status yang berubah (dibuktikan test existing 100% tetap hijau tanpa assertion diubah), konfirmasi folder `Support/` sudah tidak ada lagi.

## Self-Review

- Cakupan spec: §Bagian A → Task 1; §Bagian B → Task 2; §Bagian C → Task 3; §Test Matrix → tersebar di Task 2 Step 3/8 dan Task 3 Step 3/8 (test Action baru + regresi test existing); §Non-Goals → dipatuhi di semua task (tidak ada method lain yang disentuh, tidak ada `exists:` baru ditambah).
- Placeholder scan: tidak ada. Kode Action/DTO/FormRequest ditulis lengkap, bukan kerangka.
- Konsistensi tipe: `KelasData::fromValidated(array): self`, `CreateKelasAction::execute(KelasData, ?int $lembagaIdOverride = null): Kelas`, `UpdateKelasAction::execute(Kelas, KelasData): Kelas` — signature identik antara Task 3 Step 2/4 (definisi) dan Step 7 (pemanggilan di controller) dan Step 3 (test).
- Regression safety: setiap task berat menekankan "jalankan test EXISTING dulu, jangan ubah assertion-nya" (Task 2 Step 8, Task 3 Step 8) — bukan cuma menulis test baru dan menganggap cukup. Test existing (`FaseDefaultMappingControllerTest` 9 test, `KelasCrudTest` 10 test + `KelasPolaJamTest`/`KelasFaseAssignmentTest`/`KelasFaseSuggestionTest`) adalah bukti utama tidak ada regresi, bukan test Action baru yang cuma menguji happy-path.
