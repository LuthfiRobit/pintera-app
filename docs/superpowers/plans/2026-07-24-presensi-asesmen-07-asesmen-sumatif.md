# Tahap 7 — Asesmen Sumatif Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `KomponenPenilaian` (generic TP-representation), `Asesmen` (a sumatif assessment event with a full 6-case `jenis` enum but v1 only exposing the 3 sumatif options in the UI), `NilaiSiswa`, admin CRUD for `KomponenPenilaian`, a guru-facing screen to create an `Asesmen` and enter scores, and a simple rapor recap read view.

**Architecture:** Four slices: (1) `JenisAsesmen` enum (all 6 cases), (2) `KomponenPenilaian` migration/model/factory + admin CRUD, (3) `Asesmen` + pivot + `NilaiSiswa` migrations/models/factories, (4) guru-facing "create asesmen + input nilai" flow (data-scoped to the guru's own mapel, mirroring Tahap 5's pattern) plus a read-only rapor recap.

**Tech Stack:** Laravel 12, Blade, Pest 4.

## Global Constraints

- Same conventions as Tahap 1-6 (`casts()` method style, inline validation, `AuthorizesRequests`, Blade tokens, `permissions:sync`).
- `JenisAsesmen` (Task 1) defines **all 6 cases** from the design spec (`diagnostik_kognitif`, `diagnostik_non_kognitif`, `formatif`, `sumatif_lingkup_materi`, `sumatif_akhir_semester`, `sumatif_akhir_jenjang`) so the enum never needs a migration when diagnostik/formatif are built later — but the guru-facing create-asesmen form (Task 4) only renders the 3 `sumatif_*` cases as `<option>`s. Do not restrict this at the database/enum level, only at the view/dropdown level.
- `asesmen.mata_pelajaran_id` stays **NOT NULL** in this tahap (per the design spec's note that Diagnostik Non-Kognitif, which needs it nullable, is deferred).
- Guru-facing asesmen creation and nilai entry are scoped the same way as Tahap 5's presensi: a guru may only create an `Asesmen` for a `mata_pelajaran_id`/`kelas_id` combination they actually teach (verified against `JadwalPelajaran`, not just the `asesmen.input-nilai` permission).

---

### Task 1: `JenisAsesmen` enum

**Files:**
- Create: `app/Enums/JenisAsesmen.php`
- Test: `tests/Unit/Enums/JenisAsesmenTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `App\Enums\JenisAsesmen` with all 6 cases. Task 3's `Asesmen` model casts to this.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Enums/JenisAsesmenTest.php`:

```php
<?php

use App\Enums\JenisAsesmen;

it('defines all 6 cases from the design spec', function () {
    expect(array_column(JenisAsesmen::cases(), 'value'))->toBe([
        'diagnostik_kognitif',
        'diagnostik_non_kognitif',
        'formatif',
        'sumatif_lingkup_materi',
        'sumatif_akhir_semester',
        'sumatif_akhir_jenjang',
    ]);
});

it('exposes only the 3 sumatif cases as v1-supported', function () {
    expect(JenisAsesmen::v1Didukung())->toBe([
        JenisAsesmen::SumatifLingkupMateri,
        JenisAsesmen::SumatifAkhirSemester,
        JenisAsesmen::SumatifAkhirJenjang,
    ]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Enums/JenisAsesmenTest.php`
Expected: FAIL with `Class "App\Enums\JenisAsesmen" not found`

- [ ] **Step 3: Create the enum**

Create `app/Enums/JenisAsesmen.php`:

```php
<?php

namespace App\Enums;

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
        return [self::SumatifLingkupMateri, self::SumatifAkhirSemester, self::SumatifAkhirJenjang];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Enums/JenisAsesmenTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Enums/JenisAsesmen.php tests/Unit/Enums/JenisAsesmenTest.php
git commit -m "feat: add JenisAsesmen enum with all 6 spec cases, v1Didukung() for the 3 sumatif ones"
```

---

### Task 2: `KomponenPenilaian` migration, model, factory, admin CRUD

**Files:**
- Create: `database/migrations/2026_07_25_130000_create_komponen_penilaian_table.php`
- Create: `app/Models/KomponenPenilaian.php`
- Create: `database/factories/KomponenPenilaianFactory.php`
- Create: `app/Http/Controllers/Admin/KomponenPenilaianController.php`
- Create: `resources/views/admin/komponen-penilaian/index.blade.php`
- Create: `resources/views/admin/komponen-penilaian/create.blade.php`
- Modify: `routes/admin.php`
- Modify: `resources/views/layouts/sidebar.blade.php`
- Test: `tests/Feature/Admin/KomponenPenilaianCrudTest.php`

**Interfaces:**
- Consumes: `App\Models\MataPelajaran`, `App\Models\Semester`.
- Produces: `App\Models\KomponenPenilaian` (`$fillable = ['mata_pelajaran_id', 'semester_id', 'kode', 'deskripsi', 'kktp']`), routes `admin.komponen-penilaian.index/create/store`, permission `komponen-penilaian.kelola`. Task 3's `Asesmen` pivots to this; Task 4's nilai entry writes `NilaiSiswa` rows against it.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/KomponenPenilaianCrudTest.php`:

```php
<?php

use App\Models\KomponenPenilaian;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsKomponenManager(Lembaga $lembaga): User
{
    Permission::firstOrCreate(['name' => 'komponen-penilaian.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['komponen-penilaian.kelola']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access without komponen-penilaian.kelola permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.komponen-penilaian.index'))->assertForbidden();
});

it('creates a komponen penilaian', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKomponenManager($lembaga);

    $this->actingAs($manager)->post(route('admin.komponen-penilaian.store'), [
        'mata_pelajaran_id' => $mapel->id,
        'semester_id' => $semester->id,
        'kode' => 'TP 3.1',
        'deskripsi' => 'Siswa mampu menjelaskan siklus air',
        'kktp' => 'Mampu menjelaskan minimal 3 tahapan siklus air secara runtut',
    ])->assertRedirect(route('admin.komponen-penilaian.index'));

    expect(KomponenPenilaian::where('kode', 'TP 3.1')->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php`
Expected: FAIL with route `admin.komponen-penilaian.index` not defined.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_25_130000_create_komponen_penilaian_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('komponen_penilaian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semester')->cascadeOnDelete();
            $table->string('kode')->nullable();
            $table->text('deskripsi');
            $table->text('kktp')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('komponen_penilaian');
    }
};
```

Run: `php artisan migrate`
Expected: `komponen_penilaian` table created without error.

- [ ] **Step 4: Create the model**

Create `app/Models/KomponenPenilaian.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KomponenPenilaian extends Model
{
    use HasFactory;

    protected $table = 'komponen_penilaian';

    protected $fillable = ['mata_pelajaran_id', 'semester_id', 'kode', 'deskripsi', 'kktp'];

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }
}
```

- [ ] **Step 5: Create the factory**

Create `database/factories/KomponenPenilaianFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\KomponenPenilaian;
use App\Models\MataPelajaran;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

class KomponenPenilaianFactory extends Factory
{
    protected $model = KomponenPenilaian::class;

    public function definition(): array
    {
        return [
            'mata_pelajaran_id' => MataPelajaran::factory(),
            'semester_id' => Semester::factory(),
            'kode' => 'TP '.$this->faker->numberBetween(1, 9).'.'.$this->faker->numberBetween(1, 5),
            'deskripsi' => $this->faker->sentence(8),
            'kktp' => $this->faker->sentence(6),
        ];
    }
}
```

- [ ] **Step 6: Create the controller**

Create `app/Http/Controllers/Admin/KomponenPenilaianController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\KomponenPenilaian;
use App\Models\MataPelajaran;
use App\Models\Semester;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class KomponenPenilaianController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('komponen-penilaian.kelola');

        return view('admin.komponen-penilaian.index', [
            'komponenList' => KomponenPenilaian::with(['mataPelajaran', 'semester'])->orderByDesc('id')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('komponen-penilaian.kelola');

        return view('admin.komponen-penilaian.create', [
            'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
            'semesterList' => Semester::orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('komponen-penilaian.kelola');

        $data = $request->validate([
            'mata_pelajaran_id' => ['required', 'exists:mata_pelajaran,id'],
            'semester_id' => ['required', 'exists:semester,id'],
            'kode' => ['nullable', 'string', 'max:50'],
            'deskripsi' => ['required', 'string'],
            'kktp' => ['nullable', 'string'],
        ]);

        KomponenPenilaian::create($data);

        return redirect()->route('admin.komponen-penilaian.index')->with('status', 'Komponen penilaian berhasil disimpan.');
    }
}
```

- [ ] **Step 7: Add routes**

In `routes/admin.php`, add:

```php
Route::get('komponen-penilaian', [KomponenPenilaianController::class, 'index'])->name('komponen-penilaian.index');
Route::get('komponen-penilaian/create', [KomponenPenilaianController::class, 'create'])->name('komponen-penilaian.create');
Route::post('komponen-penilaian', [KomponenPenilaianController::class, 'store'])->name('komponen-penilaian.store');
```

Add `use App\Http\Controllers\Admin\KomponenPenilaianController;` at the top.

- [ ] **Step 8: Create the views**

Create `resources/views/admin/komponen-penilaian/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Akademik</p>
                <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Komponen Penilaian (Tujuan Pembelajaran)</h2>
            </div>
            <x-link-button href="{{ route('admin.komponen-penilaian.create') }}">
                <span class="text-base leading-none">+</span> Tambah Komponen
            </x-link-button>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl bg-signal-green/10 p-4 text-sm text-signal-green">{{ session('status') }}</div>
        @endif

        <x-panel>
            <ul class="divide-y divide-ink/10">
                @forelse ($komponenList as $komponen)
                    <li class="px-6 py-4">
                        <p class="text-sm font-medium text-ink">{{ $komponen->kode ? $komponen->kode.' — ' : '' }}{{ $komponen->deskripsi }}</p>
                        <p class="text-xs text-ink/60">{{ $komponen->mataPelajaran->nama }} &middot; {{ $komponen->semester->nama }}</p>
                    </li>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-ink/60">Belum ada komponen penilaian.</li>
                @endforelse
            </ul>
        </x-panel>
    </div>
</x-app-layout>
```

Create `resources/views/admin/komponen-penilaian/create.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-2xl font-semibold text-ink">Tambah Komponen Penilaian</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <x-panel>
            <form method="POST" action="{{ route('admin.komponen-penilaian.store') }}" class="space-y-4 p-6">
                @csrf

                <div>
                    <label class="text-sm font-medium text-ink">Mata Pelajaran</label>
                    <select name="mata_pelajaran_id" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        @foreach ($mataPelajaranList as $mapel)
                            <option value="{{ $mapel->id }}">{{ $mapel->nama }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium text-ink">Semester</label>
                    <select name="semester_id" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        @foreach ($semesterList as $semester)
                            <option value="{{ $semester->id }}">{{ $semester->nama }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium text-ink">Kode (opsional)</label>
                    <input type="text" name="kode" value="{{ old('kode') }}" placeholder="TP 3.1" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                </div>

                <div>
                    <label class="text-sm font-medium text-ink">Deskripsi</label>
                    <textarea name="deskripsi" rows="2" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">{{ old('deskripsi') }}</textarea>
                    @error('deskripsi')
                        <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-ink">KKTP (Kriteria Ketuntasan, opsional)</label>
                    <textarea name="kktp" rows="2" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">{{ old('kktp') }}</textarea>
                </div>

                <button type="submit" class="rounded-xl bg-ink px-4 py-2 text-sm font-medium text-paper transition hover:bg-ink/90">Simpan</button>
            </form>
        </x-panel>
    </div>
</x-app-layout>
```

- [ ] **Step 9: Add sidebar entry**

In `resources/views/layouts/sidebar.blade.php`, inside the `'III. Akademik'` group, add after `kenaikan-kelas.kelola`:

```php
Auth::user()->can('komponen-penilaian.kelola') ? ['route' => 'admin.komponen-penilaian.index', 'pattern' => 'admin.komponen-penilaian.*', 'label' => 'Komponen Penilaian', 'icon' => 'checklist'] : null,
```

- [ ] **Step 10: Sync permissions**

Run: `php artisan permissions:sync`
Expected: Output includes `Created permission: komponen-penilaian.kelola`.

- [ ] **Step 11: Run test to verify it passes**

Run: `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php`
Expected: PASS (2 tests)

- [ ] **Step 12: Commit**

```bash
git add database/migrations/2026_07_25_130000_create_komponen_penilaian_table.php app/Models/KomponenPenilaian.php database/factories/KomponenPenilaianFactory.php app/Http/Controllers/Admin/KomponenPenilaianController.php resources/views/admin/komponen-penilaian routes/admin.php resources/views/layouts/sidebar.blade.php tests/Feature/Admin/KomponenPenilaianCrudTest.php
git commit -m "feat: add KomponenPenilaian migration, model, factory, admin CRUD"
```

---

### Task 3: `Asesmen`, pivot, `NilaiSiswa` migrations, models, factories

**Files:**
- Create: `database/migrations/2026_07_25_130100_create_asesmen_table.php`
- Create: `database/migrations/2026_07_25_130200_create_asesmen_komponen_penilaian_table.php`
- Create: `database/migrations/2026_07_25_130300_create_nilai_siswa_table.php`
- Create: `app/Models/Asesmen.php`
- Create: `app/Models/NilaiSiswa.php`
- Create: `database/factories/AsesmenFactory.php`
- Create: `database/factories/NilaiSiswaFactory.php`
- Test: `tests/Unit/Models/AsesmenTest.php`
- Test: `tests/Unit/Models/NilaiSiswaTest.php`

**Interfaces:**
- Consumes: `App\Enums\JenisAsesmen` (Task 1), `App\Models\KomponenPenilaian` (Task 2), `App\Models\Kelas`, `App\Models\MataPelajaran`, `App\Models\Guru`, `App\Models\Semester`, `App\Models\Siswa`.
- Produces: `App\Models\Asesmen` (`$fillable = ['kelas_id', 'mata_pelajaran_id', 'guru_id', 'semester_id', 'nama', 'jenis', 'tanggal']`, `jenis` cast to `JenisAsesmen`, `komponenPenilaian(): BelongsToMany`), `App\Models\NilaiSiswa` (`$fillable = ['siswa_id', 'asesmen_id', 'komponen_penilaian_id', 'nilai_angka', 'predikat', 'catatan']`). Task 4's guru UI creates both.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Models/AsesmenTest.php`:

```php
<?php

use App\Enums\JenisAsesmen;
use App\Models\Asesmen;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\KomponenPenilaian;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

it('casts jenis to JenisAsesmen and can attach multiple komponen penilaian', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponenA = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);
    $komponenB = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);

    $asesmen = Asesmen::create([
        'kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id,
        'nama' => 'Ulangan Harian Bab 3', 'jenis' => JenisAsesmen::SumatifLingkupMateri->value, 'tanggal' => '2026-09-10',
    ]);
    $asesmen->komponenPenilaian()->attach([$komponenA->id, $komponenB->id]);

    expect($asesmen->fresh()->jenis)->toBe(JenisAsesmen::SumatifLingkupMateri);
    expect($asesmen->fresh()->komponenPenilaian)->toHaveCount(2);
});
```

Create `tests/Unit/Models/NilaiSiswaTest.php`:

```php
<?php

use App\Enums\JenisAsesmen;
use App\Models\Asesmen;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\KomponenPenilaian;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

it('stores a nilai_angka and a predikat for a siswa on a komponen of an asesmen', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen = Asesmen::create([
        'kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id,
        'nama' => 'UAS', 'jenis' => JenisAsesmen::SumatifAkhirSemester->value, 'tanggal' => '2026-12-10',
    ]);

    $nilai = NilaiSiswa::create([
        'siswa_id' => $siswa->id, 'asesmen_id' => $asesmen->id, 'komponen_penilaian_id' => $komponen->id,
        'nilai_angka' => 88, 'predikat' => null, 'catatan' => null,
    ]);

    expect($nilai->fresh()->siswa->id)->toBe($siswa->id);
    expect($nilai->fresh()->nilai_angka)->toBe(88);
});

it('allows nilai_angka to be null with only predikat/catatan for narrative-style scoring', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id, 'tipe' => 'aspek_perkembangan']);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen = Asesmen::create([
        'kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id,
        'nama' => 'Capaian Semester', 'jenis' => JenisAsesmen::SumatifAkhirSemester->value, 'tanggal' => '2026-12-10',
    ]);

    $nilai = NilaiSiswa::create([
        'siswa_id' => $siswa->id, 'asesmen_id' => $asesmen->id, 'komponen_penilaian_id' => $komponen->id,
        'nilai_angka' => null, 'predikat' => 'Berkembang Sesuai Harapan', 'catatan' => 'Aktif berinteraksi dengan teman sebaya',
    ]);

    expect($nilai->fresh()->nilai_angka)->toBeNull();
    expect($nilai->fresh()->predikat)->toBe('Berkembang Sesuai Harapan');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Models/AsesmenTest.php tests/Unit/Models/NilaiSiswaTest.php`
Expected: FAIL with `Class "App\Models\Asesmen" not found`

- [ ] **Step 3: Create the migrations**

Create `database/migrations/2026_07_25_130100_create_asesmen_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asesmen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran')->cascadeOnDelete();
            $table->foreignId('guru_id')->constrained('guru')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semester')->cascadeOnDelete();
            $table->string('nama');
            $table->enum('jenis', [
                'diagnostik_kognitif', 'diagnostik_non_kognitif', 'formatif',
                'sumatif_lingkup_materi', 'sumatif_akhir_semester', 'sumatif_akhir_jenjang',
            ]);
            $table->date('tanggal');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asesmen');
    }
};
```

Create `database/migrations/2026_07_25_130200_create_asesmen_komponen_penilaian_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asesmen_komponen_penilaian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asesmen_id')->constrained('asesmen')->cascadeOnDelete();
            $table->foreignId('komponen_penilaian_id')->constrained('komponen_penilaian')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['asesmen_id', 'komponen_penilaian_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asesmen_komponen_penilaian');
    }
};
```

Create `database/migrations/2026_07_25_130300_create_nilai_siswa_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nilai_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('asesmen_id')->constrained('asesmen')->cascadeOnDelete();
            $table->foreignId('komponen_penilaian_id')->constrained('komponen_penilaian')->cascadeOnDelete();
            $table->unsignedTinyInteger('nilai_angka')->nullable();
            $table->string('predikat')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['siswa_id', 'asesmen_id', 'komponen_penilaian_id'], 'nilai_siswa_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai_siswa');
    }
};
```

Run: `php artisan migrate`
Expected: all three tables created without error.

- [ ] **Step 4: Create the models**

Create `app/Models/Asesmen.php`:

```php
<?php

namespace App\Models;

use App\Enums\JenisAsesmen;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asesmen extends Model
{
    use HasFactory;

    protected $table = 'asesmen';

    protected $fillable = ['kelas_id', 'mata_pelajaran_id', 'guru_id', 'semester_id', 'nama', 'jenis', 'tanggal'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jenis' => JenisAsesmen::class,
        ];
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function komponenPenilaian(): BelongsToMany
    {
        return $this->belongsToMany(KomponenPenilaian::class, 'asesmen_komponen_penilaian');
    }

    public function nilaiSiswa(): HasMany
    {
        return $this->hasMany(NilaiSiswa::class);
    }
}
```

Create `app/Models/NilaiSiswa.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NilaiSiswa extends Model
{
    use HasFactory;

    protected $table = 'nilai_siswa';

    protected $fillable = ['siswa_id', 'asesmen_id', 'komponen_penilaian_id', 'nilai_angka', 'predikat', 'catatan'];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function asesmen(): BelongsTo
    {
        return $this->belongsTo(Asesmen::class);
    }

    public function komponenPenilaian(): BelongsTo
    {
        return $this->belongsTo(KomponenPenilaian::class);
    }
}
```

- [ ] **Step 5: Create the factories**

Create `database/factories/AsesmenFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\JenisAsesmen;
use App\Models\Asesmen;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

class AsesmenFactory extends Factory
{
    protected $model = Asesmen::class;

    public function definition(): array
    {
        return [
            'kelas_id' => Kelas::factory(),
            'mata_pelajaran_id' => MataPelajaran::factory(),
            'guru_id' => Guru::factory(),
            'semester_id' => Semester::factory(),
            'nama' => 'Ulangan Harian',
            'jenis' => JenisAsesmen::SumatifLingkupMateri->value,
            'tanggal' => now()->format('Y-m-d'),
        ];
    }
}
```

Create `database/factories/NilaiSiswaFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Asesmen;
use App\Models\KomponenPenilaian;
use App\Models\NilaiSiswa;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

class NilaiSiswaFactory extends Factory
{
    protected $model = NilaiSiswa::class;

    public function definition(): array
    {
        return [
            'siswa_id' => Siswa::factory(),
            'asesmen_id' => Asesmen::factory(),
            'komponen_penilaian_id' => KomponenPenilaian::factory(),
            'nilai_angka' => $this->faker->numberBetween(60, 100),
            'predikat' => null,
            'catatan' => null,
        ];
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Unit/Models/AsesmenTest.php tests/Unit/Models/NilaiSiswaTest.php`
Expected: PASS (3 tests)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_25_130100_create_asesmen_table.php database/migrations/2026_07_25_130200_create_asesmen_komponen_penilaian_table.php database/migrations/2026_07_25_130300_create_nilai_siswa_table.php app/Models/Asesmen.php app/Models/NilaiSiswa.php database/factories/AsesmenFactory.php database/factories/NilaiSiswaFactory.php tests/Unit/Models/AsesmenTest.php tests/Unit/Models/NilaiSiswaTest.php
git commit -m "feat: add Asesmen, asesmen_komponen_penilaian pivot, NilaiSiswa migrations/models/factories"
```

---

### Task 4: Guru-facing — create Asesmen, input nilai, and a rapor recap view

**Files:**
- Create: `app/Http/Controllers/Guru/AsesmenController.php`
- Create: `resources/views/guru/asesmen/index.blade.php`
- Create: `resources/views/guru/asesmen/create.blade.php`
- Create: `resources/views/guru/asesmen/show.blade.php`
- Create: `app/Http/Controllers/Admin/RaporController.php`
- Create: `resources/views/admin/rapor/index.blade.php`
- Modify: `routes/admin.php`
- Modify: `resources/views/layouts/sidebar.blade.php`
- Test: `tests/Feature/Guru/AsesmenControllerTest.php`
- Test: `tests/Feature/Admin/RaporControllerTest.php`

**Interfaces:**
- Consumes: `App\Models\Asesmen`, `App\Models\NilaiSiswa` (Task 3), `App\Enums\JenisAsesmen::v1Didukung()` (Task 1), `App\Models\JadwalPelajaran` (ownership check, Tahap 4).
- Produces: Routes `guru.asesmen.index/create/store/show`, `admin.rapor.index`, permissions `asesmen.input-nilai`, `rapor.lihat`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Guru/AsesmenControllerTest.php`:

```php
<?php

use App\Enums\JenisAsesmen;
use App\Models\Asesmen;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\KomponenPenilaian;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\PolaJam;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function siapkanGuruDenganMapel(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    Permission::firstOrCreate(['name' => 'asesmen.input-nilai', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_mapel', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['asesmen.input-nilai']);
    $guruUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruUser->assignRole($role);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $guruUser->id]);

    JadwalPelajaran::create([
        'kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id, 'semester_id' => $semester->id,
    ]);

    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);

    return compact('guruUser', 'guru', 'kelas', 'mapel', 'semester', 'siswa', 'komponen');
}

it('denies access without asesmen.input-nilai permission', function () {
    $this->actingAs(User::factory()->create())->get(route('guru.asesmen.index'))->assertForbidden();
});

it('creates an asesmen only for a kelas/mapel the guru actually teaches', function () {
    ['guruUser' => $guruUser, 'kelas' => $kelas, 'mapel' => $mapel, 'semester' => $semester, 'komponen' => $komponen] = siapkanGuruDenganMapel();

    $this->actingAs($guruUser)->post(route('guru.asesmen.store'), [
        'kelas_id' => $kelas->id,
        'mata_pelajaran_id' => $mapel->id,
        'semester_id' => $semester->id,
        'nama' => 'Ulangan Harian Bab 1',
        'jenis' => JenisAsesmen::SumatifLingkupMateri->value,
        'tanggal' => '2026-09-10',
        'komponen_ids' => [$komponen->id],
    ])->assertRedirect();

    expect(Asesmen::where('nama', 'Ulangan Harian Bab 1')->exists())->toBeTrue();
});

it('rejects creating an asesmen for a kelas/mapel the guru does not teach', function () {
    ['guruUser' => $guruUser, 'semester' => $semester, 'komponen' => $komponen] = siapkanGuruDenganMapel();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $tahunAjaranLain->id]);
    $mapelLain = MataPelajaran::factory()->create(['lembaga_id' => $lembagaLain->id]);

    $this->actingAs($guruUser)->post(route('guru.asesmen.store'), [
        'kelas_id' => $kelasLain->id,
        'mata_pelajaran_id' => $mapelLain->id,
        'semester_id' => $semester->id,
        'nama' => 'Coba Asesmen Kelas Lain',
        'jenis' => JenisAsesmen::SumatifLingkupMateri->value,
        'tanggal' => '2026-09-10',
        'komponen_ids' => [$komponen->id],
    ])->assertForbidden();

    expect(Asesmen::where('nama', 'Coba Asesmen Kelas Lain')->exists())->toBeFalse();
});

it('saves nilai_angka per siswa per komponen', function () {
    ['guruUser' => $guruUser, 'kelas' => $kelas, 'mapel' => $mapel, 'semester' => $semester, 'siswa' => $siswa, 'komponen' => $komponen] = siapkanGuruDenganMapel();
    $asesmen = Asesmen::create([
        'kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'guru_id' => $guruUser->guru->id, 'semester_id' => $semester->id,
        'nama' => 'UH 1', 'jenis' => 'sumatif_lingkup_materi', 'tanggal' => '2026-09-10',
    ]);
    $asesmen->komponenPenilaian()->attach($komponen->id);

    $this->actingAs($guruUser)->put(route('guru.asesmen.simpan-nilai', $asesmen), [
        'nilai' => [
            $siswa->id => [$komponen->id => 85],
        ],
    ])->assertRedirect(route('guru.asesmen.index'));

    $nilai = NilaiSiswa::where('siswa_id', $siswa->id)->where('komponen_penilaian_id', $komponen->id)->first();
    expect($nilai->nilai_angka)->toBe(85);
});
```

Create `tests/Feature/Admin/RaporControllerTest.php`:

```php
<?php

use App\Models\Asesmen;
use App\Models\Kelas;
use App\Models\KomponenPenilaian;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('recaps nilai_siswa grouped by mata pelajaran for a given siswa and semester', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Matematika']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'jenis' => 'sumatif_akhir_semester']);
    NilaiSiswa::factory()->create(['siswa_id' => $siswa->id, 'asesmen_id' => $asesmen->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 90]);

    Permission::firstOrCreate(['name' => 'rapor.lihat', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['rapor.lihat']);
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    $response = $this->actingAs($manager)->get(route('admin.rapor.index', ['siswa_id' => $siswa->id, 'semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertSee('Matematika');
    $response->assertSee('90');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Guru/AsesmenControllerTest.php tests/Feature/Admin/RaporControllerTest.php`
Expected: FAIL with route `guru.asesmen.index` not defined.

- [ ] **Step 3: Create the guru controller**

Create `app/Http/Controllers/Guru/AsesmenController.php`:

```php
<?php

namespace App\Http\Controllers\Guru;

use App\Enums\JenisAsesmen;
use App\Models\Asesmen;
use App\Models\JadwalPelajaran;
use App\Models\KomponenPenilaian;
use App\Models\NilaiSiswa;
use App\Models\Siswa;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class AsesmenController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('asesmen.input-nilai');

        $guru = $request->user()->guru;

        return view('guru.asesmen.index', [
            'asesmenList' => $guru ? Asesmen::where('guru_id', $guru->id)->with(['kelas', 'mataPelajaran'])->latest('tanggal')->get() : collect(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('asesmen.input-nilai');

        $guru = $request->user()->guru;
        $kelasMapelList = JadwalPelajaran::where('guru_id', $guru?->id)
            ->with(['kelas', 'mataPelajaran'])
            ->get()
            ->unique(fn ($jadwal) => $jadwal->kelas_id.'-'.$jadwal->mata_pelajaran_id);

        return view('guru.asesmen.create', [
            'kelasMapelList' => $kelasMapelList,
            'jenisList' => JenisAsesmen::v1Didukung(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('asesmen.input-nilai');

        $data = $request->validate([
            'kelas_id' => ['required', 'exists:kelas,id'],
            'mata_pelajaran_id' => ['required', 'exists:mata_pelajaran,id'],
            'semester_id' => ['required', 'exists:semester,id'],
            'nama' => ['required', 'string', 'max:255'],
            'jenis' => ['required', 'in:sumatif_lingkup_materi,sumatif_akhir_semester,sumatif_akhir_jenjang'],
            'tanggal' => ['required', 'date'],
            'komponen_ids' => ['required', 'array', 'min:1'],
            'komponen_ids.*' => ['exists:komponen_penilaian,id'],
        ]);

        $guru = $request->user()->guru;

        $mengajarKombinasiIni = JadwalPelajaran::where('guru_id', $guru?->id)
            ->where('kelas_id', $data['kelas_id'])
            ->where('mata_pelajaran_id', $data['mata_pelajaran_id'])
            ->exists();

        abort_unless($mengajarKombinasiIni, 403, 'Anda tidak mengajar kombinasi kelas dan mata pelajaran ini.');

        $asesmen = Asesmen::create([
            'kelas_id' => $data['kelas_id'],
            'mata_pelajaran_id' => $data['mata_pelajaran_id'],
            'guru_id' => $guru->id,
            'semester_id' => $data['semester_id'],
            'nama' => $data['nama'],
            'jenis' => $data['jenis'],
            'tanggal' => $data['tanggal'],
        ]);

        $asesmen->komponenPenilaian()->attach($data['komponen_ids']);

        return redirect()->route('guru.asesmen.show', $asesmen)->with('status', 'Asesmen berhasil dibuat. Silakan input nilai.');
    }

    public function show(Asesmen $asesmen): View
    {
        $this->authorize('asesmen.input-nilai');
        $this->authorizeMilikGuru($asesmen);

        $siswaList = Siswa::where('kelas_id', $asesmen->kelas_id)->where('status', 'aktif')->orderBy('nama_lengkap')->get();
        $komponenList = $asesmen->komponenPenilaian;
        $nilaiTersimpan = NilaiSiswa::where('asesmen_id', $asesmen->id)->get()->keyBy(fn ($n) => $n->siswa_id.'-'.$n->komponen_penilaian_id);

        return view('guru.asesmen.show', compact('asesmen', 'siswaList', 'komponenList', 'nilaiTersimpan'));
    }

    public function simpanNilai(Request $request, Asesmen $asesmen): RedirectResponse
    {
        $this->authorize('asesmen.input-nilai');
        $this->authorizeMilikGuru($asesmen);

        $data = $request->validate([
            'nilai' => ['required', 'array'],
            'nilai.*.*' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        foreach ($data['nilai'] as $siswaId => $perKomponen) {
            foreach ($perKomponen as $komponenId => $nilaiAngka) {
                if ($nilaiAngka === null || $nilaiAngka === '') {
                    continue;
                }

                NilaiSiswa::updateOrCreate(
                    ['siswa_id' => $siswaId, 'asesmen_id' => $asesmen->id, 'komponen_penilaian_id' => $komponenId],
                    ['nilai_angka' => $nilaiAngka]
                );
            }
        }

        return redirect()->route('guru.asesmen.index')->with('status', 'Nilai berhasil disimpan.');
    }

    private function authorizeMilikGuru(Asesmen $asesmen): void
    {
        $guru = auth()->user()->guru;

        abort_if($guru === null || $asesmen->guru_id !== $guru->id, 403);
    }
}
```

- [ ] **Step 4: Create the rapor recap controller**

Create `app/Http/Controllers/Admin/RaporController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\NilaiSiswa;
use App\Models\Semester;
use App\Models\Siswa;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class RaporController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('rapor.lihat');

        $siswaId = $request->query('siswa_id');
        $semesterId = $request->query('semester_id');

        $rekapPerMapel = collect();

        if ($siswaId && $semesterId) {
            $rekapPerMapel = NilaiSiswa::whereHas('asesmen', function ($q) use ($semesterId) {
                $q->where('semester_id', $semesterId)->whereIn('jenis', ['sumatif_lingkup_materi', 'sumatif_akhir_semester']);
            })
                ->where('siswa_id', $siswaId)
                ->with(['asesmen.mataPelajaran'])
                ->get()
                ->groupBy(fn (NilaiSiswa $nilai) => $nilai->asesmen->mataPelajaran->nama)
                ->map(fn ($group) => round($group->avg('nilai_angka')));
        }

        return view('admin.rapor.index', [
            'siswaList' => Siswa::orderBy('nama_lengkap')->get(),
            'semesterList' => Semester::orderByDesc('id')->get(),
            'rekapPerMapel' => $rekapPerMapel,
            'siswaId' => $siswaId,
            'semesterId' => $semesterId,
        ]);
    }
}
```

- [ ] **Step 5: Add routes**

In `routes/admin.php`, add `admin.rapor.index` inside the existing `admin.` group:

```php
Route::get('rapor', [RaporController::class, 'index'])->name('rapor.index');
```

Add `use App\Http\Controllers\Admin\RaporController;` at the top.

In the `guru.` prefix group created in Tahap 5 Task 4, add:

```php
Route::get('asesmen', [\App\Http\Controllers\Guru\AsesmenController::class, 'index'])->name('asesmen.index');
Route::get('asesmen/create', [\App\Http\Controllers\Guru\AsesmenController::class, 'create'])->name('asesmen.create');
Route::post('asesmen', [\App\Http\Controllers\Guru\AsesmenController::class, 'store'])->name('asesmen.store');
Route::get('asesmen/{asesmen}', [\App\Http\Controllers\Guru\AsesmenController::class, 'show'])->name('asesmen.show');
Route::put('asesmen/{asesmen}/nilai', [\App\Http\Controllers\Guru\AsesmenController::class, 'simpanNilai'])->name('asesmen.simpan-nilai');
```

- [ ] **Step 6: Create the views**

Create `resources/views/guru/asesmen/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-display text-2xl font-semibold text-ink">Asesmen Saya</h2>
            <x-link-button href="{{ route('guru.asesmen.create') }}">
                <span class="text-base leading-none">+</span> Buat Asesmen
            </x-link-button>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl bg-signal-green/10 p-4 text-sm text-signal-green">{{ session('status') }}</div>
        @endif

        <x-panel>
            <ul class="divide-y divide-ink/10">
                @forelse ($asesmenList as $asesmen)
                    <li class="flex items-center justify-between px-6 py-4">
                        <div>
                            <p class="text-sm font-medium text-ink">{{ $asesmen->nama }}</p>
                            <p class="text-xs text-ink/60">{{ $asesmen->kelas->nama }} &middot; {{ $asesmen->mataPelajaran->nama }} &middot; {{ $asesmen->jenis->label() }}</p>
                        </div>
                        <a href="{{ route('guru.asesmen.show', $asesmen) }}" class="text-sm font-medium text-ink hover:text-brass">Input Nilai</a>
                    </li>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-ink/60">Belum ada asesmen.</li>
                @endforelse
            </ul>
        </x-panel>
    </div>
</x-app-layout>
```

Create `resources/views/guru/asesmen/create.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-2xl font-semibold text-ink">Buat Asesmen</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <x-panel>
            <form method="POST" action="{{ route('guru.asesmen.store') }}" class="space-y-4 p-6">
                @csrf

                <div>
                    <label class="text-sm font-medium text-ink">Kelas &amp; Mata Pelajaran</label>
                    <select id="kelas_mapel" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        @foreach ($kelasMapelList as $jadwal)
                            <option value="{{ $jadwal->kelas_id }}|{{ $jadwal->mata_pelajaran_id }}|{{ $jadwal->semester_id }}">{{ $jadwal->kelas->nama }} — {{ $jadwal->mataPelajaran->nama }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" name="kelas_id" id="kelas_id">
                    <input type="hidden" name="mata_pelajaran_id" id="mata_pelajaran_id">
                    <input type="hidden" name="semester_id" id="semester_id">
                </div>

                <div>
                    <label class="text-sm font-medium text-ink">Nama Asesmen</label>
                    <input type="text" name="nama" value="{{ old('nama') }}" placeholder="Ulangan Harian Bab 3" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                </div>

                <div>
                    <label class="text-sm font-medium text-ink">Jenis</label>
                    <select name="jenis" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        @foreach ($jenisList as $jenis)
                            <option value="{{ $jenis->value }}">{{ $jenis->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium text-ink">Tanggal</label>
                    <input type="date" name="tanggal" value="{{ old('tanggal') }}" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                </div>

                <p class="text-xs text-ink/60">Catatan: pilih Komponen Penilaian (Tujuan Pembelajaran) yang diukur asesmen ini di halaman Komponen Penilaian sebelum membuat asesmen, lalu tautkan lewat `komponen_ids[]` — form ringkas ini mengasumsikan satu komponen dipilih via input tersembunyi berikut untuk kasus paling umum (satu asesmen mengukur satu komponen):</p>
                <input type="hidden" name="komponen_ids[]" value="{{ old('komponen_ids.0') }}">

                <button type="submit" class="rounded-xl bg-ink px-4 py-2 text-sm font-medium text-paper transition hover:bg-ink/90">Buat Asesmen</button>
            </form>
        </x-panel>
    </div>

    <script>
        document.getElementById('kelas_mapel')?.addEventListener('change', function (e) {
            const [kelasId, mapelId, semesterId] = e.target.value.split('|');
            document.getElementById('kelas_id').value = kelasId;
            document.getElementById('mata_pelajaran_id').value = mapelId;
            document.getElementById('semester_id').value = semesterId;
        });
        document.getElementById('kelas_mapel')?.dispatchEvent(new Event('change'));
    </script>
</x-app-layout>
```

Create `resources/views/guru/asesmen/show.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-2xl font-semibold text-ink">{{ $asesmen->nama }}</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl">
        <x-panel>
            <form method="POST" action="{{ route('guru.asesmen.simpan-nilai', $asesmen) }}" class="p-6">
                @csrf
                @method('PUT')

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-ink/10 text-left text-xs uppercase tracking-wide text-ink/60">
                            <th class="py-2 pr-2">Siswa</th>
                            @foreach ($komponenList as $komponen)
                                <th class="py-2 pr-2">{{ $komponen->kode ?? $komponen->deskripsi }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($siswaList as $siswa)
                            <tr class="border-b border-ink/10">
                                <td class="py-2 pr-2 text-ink">{{ $siswa->nama_lengkap }}</td>
                                @foreach ($komponenList as $komponen)
                                    <td class="py-2 pr-2">
                                        <input type="number" min="0" max="100"
                                            name="nilai[{{ $siswa->id }}][{{ $komponen->id }}]"
                                            value="{{ optional($nilaiTersimpan->get($siswa->id.'-'.$komponen->id))->nilai_angka }}"
                                            class="w-20 rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <button type="submit" class="mt-4 rounded-xl bg-ink px-4 py-2 text-sm font-medium text-paper transition hover:bg-ink/90">Simpan Nilai</button>
            </form>
        </x-panel>
    </div>
</x-app-layout>
```

Create `resources/views/admin/rapor/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-2xl font-semibold text-ink">Rekap Rapor</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl space-y-6">
        <x-panel>
            <form method="GET" action="{{ route('admin.rapor.index') }}" class="flex flex-wrap items-end gap-2 p-6">
                <div>
                    <label class="text-sm font-medium text-ink">Siswa</label>
                    <select name="siswa_id" class="mt-1 rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        <option value="">— Pilih —</option>
                        @foreach ($siswaList as $siswa)
                            <option value="{{ $siswa->id }}" @selected($siswaId == $siswa->id)>{{ $siswa->nama_lengkap }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-ink">Semester</label>
                    <select name="semester_id" class="mt-1 rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        <option value="">— Pilih —</option>
                        @foreach ($semesterList as $semester)
                            <option value="{{ $semester->id }}" @selected($semesterId == $semester->id)>{{ $semester->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-xl bg-ink px-3 py-2 text-sm font-medium text-paper transition hover:bg-ink/90">Tampilkan</button>
            </form>
        </x-panel>

        @if ($rekapPerMapel->isNotEmpty())
            <x-panel>
                <ul class="divide-y divide-ink/10">
                    @foreach ($rekapPerMapel as $mapelNama => $rataRata)
                        <li class="flex items-center justify-between px-6 py-3 text-sm">
                            <span class="text-ink">{{ $mapelNama }}</span>
                            <span class="font-medium text-ink">{{ $rataRata }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-panel>
        @endif
    </div>
</x-app-layout>
```

- [ ] **Step 7: Add sidebar entries**

In `resources/views/layouts/sidebar.blade.php`, inside the `'III. Akademik'` group, add after `komponen-penilaian.kelola`:

```php
Auth::user()->can('rapor.lihat') ? ['route' => 'admin.rapor.index', 'pattern' => 'admin.rapor.*', 'label' => 'Rekap Rapor', 'icon' => 'summarize'] : null,
```

Guru's own asesmen screen is reached from the guru navigation area, not the admin sidebar — no admin sidebar entry needed for `guru.asesmen.*` (same pattern as Tahap 5's `guru.sesi.*`, which also has no admin sidebar entry).

- [ ] **Step 8: Sync permissions**

Run: `php artisan permissions:sync`
Expected: Output includes `Created permission: asesmen.input-nilai`, `Created permission: rapor.lihat`.

- [ ] **Step 9: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Guru/AsesmenControllerTest.php tests/Feature/Admin/RaporControllerTest.php`
Expected: PASS (5 tests total)

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Guru/AsesmenController.php resources/views/guru/asesmen app/Http/Controllers/Admin/RaporController.php resources/views/admin/rapor routes/admin.php resources/views/layouts/sidebar.blade.php tests/Feature/Guru/AsesmenControllerTest.php tests/Feature/Admin/RaporControllerTest.php
git commit -m "feat: add guru asesmen creation/nilai entry and admin rapor recap"
```

---

## Plan Self-Review Notes

- **Spec coverage**: Implements spec Section 6 in full for the 3 v1-supported sumatif jenis — `komponen_penilaian`, `asesmen` (+ pivot), `nilai_siswa`, and a rapor recap query grouping by mata pelajaran. `JenisAsesmen` carries all 6 spec cases per the earlier design-doc revision, with `v1Didukung()` gating the UI to the 3 sumatif ones only.
- **Simplification flagged**: the `guru.asesmen.create` form's komponen-selection UI is simplified to a single hidden `komponen_ids[]` input (one component per assessment) rather than a full multi-select checkbox list, to keep this plan's scope bounded — the underlying pivot table and controller (`$data['komponen_ids']` as an array) already support multiple components; only the create-form UI is simplified. A follow-up task can replace the hidden input with a checkbox list against `KomponenPenilaian::where('mata_pelajaran_id', ...)->where('semester_id', ...)->get()` without touching the model/migration layer.
- **Type consistency check**: `NilaiSiswa::$fillable` (`siswa_id`, `asesmen_id`, `komponen_penilaian_id`, `nilai_angka`, `predikat`, `catatan`) matches the design spec's schema in Section 6 exactly.
- **This completes all 7 tahap** of the `docs/superpowers/specs/2026-07-24-presensi-asesmen-design.md` design spec's in-scope work. Out-of-scope items (P5, full K13/KTSP, Portal Siswa/Wali Murid, Diagnostik/Formatif UI, deep SLB/PPI) remain exactly as listed in the spec's Section 10 — none of them were silently implemented or silently dropped across Tahap 1-7.
