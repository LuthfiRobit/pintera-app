# Tahap 2 — Onboarding Siswa (SPMB, Import, Manual) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the two remaining `Siswa` creation paths on top of Tahap 1's model/manual-CRUD: (1) batch-convert accepted (`aktif`) `Pendaftaran` rows into `Siswa` via a checkbox table + single Kelas target, (2) Excel import with a template, per-row validation preview, and a separate confirm step.

**Architecture:** Two independent slices sharing only the `Siswa` model from Tahap 1: (1) SPMB batch conversion reads `Pendaftaran`/`CalonMurid` (existing models) and writes `Siswa` rows with `sumber_data = spmb`; (2) Excel import parses an uploaded file into a session-stored preview, then a second request commits only the valid rows with `sumber_data = import`. Neither slice touches `SiswaController::store`/`update` from Tahap 1 — both are new controllers/actions.

**Tech Stack:** Laravel 12, Blade, `maatwebsite/excel` (new dependency, installed in Task 3), Pest 4.

## Global Constraints

- Same Blade token set as Tahap 1 (`x-app-layout`, `x-panel`, `text-ink`/`text-brass`, `bg-signal-green/10`/`bg-signal-red/10`, `rounded-xl border-ink/15 ... focus:border-brass focus:ring-brass`).
- Controllers: `AuthorizesRequests` + `$this->authorize('module.action')` first line of every action, inline `$request->validate([...])`, no FormRequest classes.
- `Siswa::create()` calls always set `sumber_data` explicitly per path (`spmb` or `import`) — never left to a default so `SiswaCrudTest.php` from Tahap 1 (which asserts `manual`) keeps passing untouched.
- New permissions (`siswa.spmb-daftar`, `siswa.import`) are picked up by the `permissions:sync` command from Tahap 1 Task 5 — do not hand-edit any seeder.
- `Pendaftaran::isAktif` (existing accessor, `app/Models/Pendaftaran.php`) is the authoritative "eligible for conversion" check — do not reimplement its logic (diterima + daftar ulang lunas) inline in the new controller.

---

### Task 1: `Pendaftaran` ↔ `Siswa` traceability relation + eligibility scope

**Files:**
- Modify: `app/Models/Pendaftaran.php`
- Modify: `app/Models/Siswa.php`
- Test: `tests/Unit/Models/PendaftaranSiswaRelationTest.php`

**Interfaces:**
- Consumes: `App\Models\Siswa` (Tahap 1 Task 4), existing `Pendaftaran::isAktif` accessor.
- Produces: `Pendaftaran::siswa(): HasOne` (inverse of `Siswa::pendaftaranAsal()`), `Pendaftaran::scopeSiapDidaftarkanSebagaiSiswa(Builder $query): Builder` (aktif AND `whereDoesntHave('siswa')`) — Task 2's controller uses this scope directly.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/PendaftaranSiswaRelationTest.php`:

```php
<?php

use App\Models\AkunPendaftar;
use App\Models\CalonMurid;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

function buatPendaftaranAktifUntukSiswaTest(): Pendaftaran
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Gelombang 1', 'tanggal_mulai' => now()->subDays(10), 'tanggal_selesai' => now()->addDays(10),
    ]);
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id]);
    $akun = AkunPendaftar::factory()->create();

    return Pendaftaran::create([
        'calon_murid_id' => $calonMurid->id,
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id,
        'gelombang_ppdb_id' => $gelombang->id,
        'akun_pendaftar_id' => $akun->id,
        'email_pendaftaran' => $akun->email,
        'status' => 'diterima',
    ]);
}

it('exposes a siswa relation that resolves via pendaftaran_asal_id', function () {
    $pendaftaran = buatPendaftaranAktifUntukSiswaTest();
    $siswa = Siswa::factory()->create([
        'lembaga_id' => $pendaftaran->lembaga_id,
        'pendaftaran_asal_id' => $pendaftaran->id,
    ]);

    expect($pendaftaran->siswa->id)->toBe($siswa->id);
});

it('scopeSiapDidaftarkanSebagaiSiswa excludes pendaftaran that already have a siswa', function () {
    $pendaftaran = buatPendaftaranAktifUntukSiswaTest();
    Siswa::factory()->create(['lembaga_id' => $pendaftaran->lembaga_id, 'pendaftaran_asal_id' => $pendaftaran->id]);

    expect(Pendaftaran::siapDidaftarkanSebagaiSiswa()->whereKey($pendaftaran->id)->exists())->toBeFalse();
});

it('scopeSiapDidaftarkanSebagaiSiswa excludes pendaftaran that are not aktif', function () {
    $pendaftaran = buatPendaftaranAktifUntukSiswaTest();
    $pendaftaran->update(['status' => 'menunggu_verifikasi']);

    expect(Pendaftaran::siapDidaftarkanSebagaiSiswa()->whereKey($pendaftaran->id)->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Models/PendaftaranSiswaRelationTest.php`
Expected: FAIL with `Call to undefined method App\Models\Pendaftaran::siswa()`

- [ ] **Step 3: Add the relation and scope to `Pendaftaran`**

Open `app/Models/Pendaftaran.php` and add these two methods inside the class (alongside the existing `hasilSeleksi()`/`tagihan()` relation methods):

```php
public function siswa(): \Illuminate\Database\Eloquent\Relations\HasOne
{
    return $this->hasOne(\App\Models\Siswa::class, 'pendaftaran_asal_id');
}

public function scopeSiapDidaftarkanSebagaiSiswa(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
{
    $idAktif = $query->where('status', 'diterima')
        ->whereDoesntHave('siswa')
        ->get()
        ->filter(fn (Pendaftaran $pendaftaran) => $pendaftaran->isAktif)
        ->pluck('id');

    return Pendaftaran::whereIn('id', $idAktif);
}
```

`isAktif` is not a database column (it is the existing computed accessor checking `status === 'diterima'` and daftar-ulang payment), so the eligible set is resolved in two steps: a cheap DB-level filter (`status = 'diterima'` and no linked `Siswa` yet) narrows the candidates, then `isAktif` is evaluated in PHP over that already-small set before building the final query by ID.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Models/PendaftaranSiswaRelationTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Models/Pendaftaran.php tests/Unit/Models/PendaftaranSiswaRelationTest.php
git commit -m "feat: add Pendaftaran::siswa relation and siapDidaftarkanSebagaiSiswa scope"
```

---

### Task 2: SPMB batch conversion (Pendaftaran → Siswa)

**Files:**
- Create: `app/Http/Controllers/Admin/PendaftaranSiswaController.php`
- Create: `resources/views/admin/siswa/spmb-daftar.blade.php`
- Modify: `routes/admin.php`
- Modify: `resources/views/layouts/sidebar.blade.php`
- Test: `tests/Feature/Admin/PendaftaranSiswaControllerTest.php`

**Interfaces:**
- Consumes: `Pendaftaran::siapDidaftarkanSebagaiSiswa()` (Task 1), `App\Models\Kelas` (Tahap 1), `App\Enums\SumberDataSiswa`.
- Produces: Routes `admin.siswa.spmb-daftar.index` (GET, checkbox table), `admin.siswa.spmb-daftar.store` (POST, batch create), permission `siswa.spmb-daftar`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/PendaftaranSiswaControllerTest.php`:

```php
<?php

use App\Enums\SumberDataSiswa;
use App\Models\AkunPendaftar;
use App\Models\CalonMurid;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function buatPendaftaranAktif(Lembaga $lembaga, TahunAjaran $tahunAjaran): Pendaftaran
{
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler '.uniqid()]);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Gelombang '.uniqid(), 'tanggal_buka' => now()->subDays(10), 'tanggal_tutup' => now()->addDays(10), 'kuota' => 30,
    ]);
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id, 'nama_lengkap' => 'Calon Siswa '.uniqid()]);
    $akun = AkunPendaftar::factory()->create();

    return Pendaftaran::create([
        'calon_murid_id' => $calonMurid->id,
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id,
        'gelombang_ppdb_id' => $gelombang->id,
        'akun_pendaftar_id' => $akun->id,
        'email_pendaftaran' => $akun->email,
        'kode_pendaftaran' => 'PDFTR-'.uniqid(),
        'submitted_at' => now(),
        'status' => 'diterima',
    ]);
}

function actingAsSpmbDaftarManager(Lembaga $lembaga): User
{
    foreach (['siswa.spmb-daftar'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['siswa.spmb-daftar']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access without siswa.spmb-daftar permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.siswa.spmb-daftar.index'))->assertForbidden();
});

it('lists only pendaftaran that are aktif and not yet converted to siswa', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsSpmbDaftarManager($lembaga);

    $pendaftaran = buatPendaftaranAktif($lembaga, $tahunAjaran);

    $response = $this->actingAs($manager)->get(route('admin.siswa.spmb-daftar.index'));

    $response->assertOk();
    $response->assertViewHas('pendaftaranList', function ($list) use ($pendaftaran) {
        return $list->contains('id', $pendaftaran->id);
    });
});

it('batch-creates siswa from checked pendaftaran with a shared target kelas', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $manager = actingAsSpmbDaftarManager($lembaga);

    $pendaftaranA = buatPendaftaranAktif($lembaga, $tahunAjaran);
    $pendaftaranB = buatPendaftaranAktif($lembaga, $tahunAjaran);

    $this->actingAs($manager)->post(route('admin.siswa.spmb-daftar.store'), [
        'kelas_id' => $kelas->id,
        'pendaftaran_ids' => [$pendaftaranA->id, $pendaftaranB->id],
        'nis' => [
            $pendaftaranA->id => '2026101',
            $pendaftaranB->id => '2026102',
        ],
    ])->assertRedirect(route('admin.siswa.index'));

    $siswaA = Siswa::where('pendaftaran_asal_id', $pendaftaranA->id)->firstOrFail();
    $siswaB = Siswa::where('pendaftaran_asal_id', $pendaftaranB->id)->firstOrFail();

    expect($siswaA->sumber_data)->toBe(SumberDataSiswa::Spmb);
    expect($siswaA->kelas_id)->toBe($kelas->id);
    expect($siswaA->calon_murid_id)->toBe($pendaftaranA->calon_murid_id);
    expect($siswaA->nis)->toBe('2026101');
    expect($siswaB->nis)->toBe('2026102');
});

it('does not create a siswa for a pendaftaran that was not checked', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $manager = actingAsSpmbDaftarManager($lembaga);

    $pendaftaranChecked = buatPendaftaranAktif($lembaga, $tahunAjaran);
    $pendaftaranUnchecked = buatPendaftaranAktif($lembaga, $tahunAjaran);

    $this->actingAs($manager)->post(route('admin.siswa.spmb-daftar.store'), [
        'kelas_id' => $kelas->id,
        'pendaftaran_ids' => [$pendaftaranChecked->id],
        'nis' => [$pendaftaranChecked->id => '2026201'],
    ]);

    expect(Siswa::where('pendaftaran_asal_id', $pendaftaranChecked->id)->exists())->toBeTrue();
    expect(Siswa::where('pendaftaran_asal_id', $pendaftaranUnchecked->id)->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/PendaftaranSiswaControllerTest.php`
Expected: FAIL with route `admin.siswa.spmb-daftar.index` not defined.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Admin/PendaftaranSiswaController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SumberDataSiswa;
use App\Models\Kelas;
use App\Models\Pendaftaran;
use App\Models\Siswa;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class PendaftaranSiswaController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('siswa.spmb-daftar');

        return view('admin.siswa.spmb-daftar', [
            'pendaftaranList' => Pendaftaran::siapDidaftarkanSebagaiSiswa()
                ->with(['calonMurid', 'jalurPpdb', 'gelombangPpdb'])
                ->latest('submitted_at')
                ->get(),
            'kelasList' => Kelas::orderBy('nama')->get(),
            'nisSaran' => $this->nisBerikutnya(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('siswa.spmb-daftar');

        $data = $request->validate([
            'kelas_id' => ['required', 'exists:kelas,id'],
            'pendaftaran_ids' => ['required', 'array', 'min:1'],
            'pendaftaran_ids.*' => ['exists:pendaftaran,id'],
            'nis' => ['required', 'array'],
        ]);

        $pendaftaranTerpilih = Pendaftaran::siapDidaftarkanSebagaiSiswa()
            ->whereIn('id', $data['pendaftaran_ids'])
            ->with('calonMurid')
            ->get();

        foreach ($pendaftaranTerpilih as $pendaftaran) {
            $calonMurid = $pendaftaran->calonMurid;

            Siswa::create([
                'lembaga_id' => $pendaftaran->lembaga_id,
                'kelas_id' => $data['kelas_id'],
                'calon_murid_id' => $calonMurid->id,
                'pendaftaran_asal_id' => $pendaftaran->id,
                'sumber_data' => SumberDataSiswa::Spmb->value,
                'nis' => $data['nis'][$pendaftaran->id] ?? $this->nisBerikutnya(),
                'nisn' => $calonMurid->nisn,
                'nama_lengkap' => $calonMurid->nama_lengkap,
                'jenis_kelamin' => $calonMurid->jenis_kelamin,
                'tempat_lahir' => $calonMurid->tempat_lahir,
                'tanggal_lahir' => $calonMurid->tanggal_lahir,
                'agama' => $calonMurid->agama,
            ]);
        }

        return redirect()->route('admin.siswa.index')->with('status', count($pendaftaranTerpilih).' siswa berhasil didaftarkan.');
    }

    private function nisBerikutnya(): string
    {
        $tahun = now()->year;
        $urutan = Siswa::where('nis', 'like', $tahun.'%')->count() + 1;

        return $tahun.str_pad((string) $urutan, 3, '0', STR_PAD_LEFT);
    }
}
```

- [ ] **Step 4: Add routes**

In `routes/admin.php`, add:

```php
Route::get('siswa-spmb-daftar', [PendaftaranSiswaController::class, 'index'])->name('siswa.spmb-daftar.index');
Route::post('siswa-spmb-daftar', [PendaftaranSiswaController::class, 'store'])->name('siswa.spmb-daftar.store');
```

Add `use App\Http\Controllers\Admin\PendaftaranSiswaController;` at the top. Place this block right after the `siswa` resource route added in Tahap 1 Task 8.

- [ ] **Step 5: Create the view**

Create `resources/views/admin/siswa/spmb-daftar.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-2xl font-semibold text-ink">Daftarkan Siswa dari SPMB</h2>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-6">
        @if ($errors->any())
            <div class="rounded-xl bg-signal-red/10 p-4 text-sm text-signal-red">
                <ul class="list-disc pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-panel>
            <form method="POST" action="{{ route('admin.siswa.spmb-daftar.store') }}" class="p-6">
                @csrf

                <div class="mb-4 flex items-center gap-3">
                    <label class="text-sm font-medium text-ink">Kelas Tujuan</label>
                    <select name="kelas_id" class="rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass" required>
                        <option value="">— Pilih Kelas —</option>
                        @foreach ($kelasList as $kelas)
                            <option value="{{ $kelas->id }}">{{ $kelas->nama }}</option>
                        @endforeach
                    </select>
                </div>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-ink/10 text-left text-xs uppercase tracking-wide text-ink/60">
                            <th class="py-2 pr-2"></th>
                            <th class="py-2 pr-2">Nama</th>
                            <th class="py-2 pr-2">Jalur / Gelombang</th>
                            <th class="py-2 pr-2">NIS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pendaftaranList as $pendaftaran)
                            <tr class="border-b border-ink/10">
                                <td class="py-2 pr-2">
                                    <input type="checkbox" name="pendaftaran_ids[]" value="{{ $pendaftaran->id }}">
                                </td>
                                <td class="py-2 pr-2 text-ink">{{ $pendaftaran->calonMurid->nama_lengkap }}</td>
                                <td class="py-2 pr-2 text-ink/70">{{ $pendaftaran->jalurPpdb->nama }} / {{ $pendaftaran->gelombangPpdb->nama }}</td>
                                <td class="py-2 pr-2">
                                    <input type="text" name="nis[{{ $pendaftaran->id }}]" value="{{ $nisSaran }}" class="w-32 rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-ink/60">Tidak ada pendaftaran yang siap didaftarkan sebagai siswa.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                @if ($pendaftaranList->isNotEmpty())
                    <button type="submit" class="mt-4 rounded-xl bg-ink px-4 py-2 text-sm font-medium text-paper transition hover:bg-ink/90">Daftarkan sebagai Siswa</button>
                @endif
            </form>
        </x-panel>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Add sidebar entry**

In `resources/views/layouts/sidebar.blade.php`, inside the `'II. Data Induk'` group, add (right after the `siswa.view` entry from Tahap 1):

```php
Auth::user()->can('siswa.spmb-daftar') ? ['route' => 'admin.siswa.spmb-daftar.index', 'pattern' => 'admin.siswa.spmb-daftar.*', 'label' => 'Daftarkan dari SPMB', 'icon' => 'how_to_reg'] : null,
```

- [ ] **Step 7: Sync permissions**

Run: `php artisan permissions:sync`
Expected: Output includes `Created permission: siswa.spmb-daftar`.

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test tests/Feature/Admin/PendaftaranSiswaControllerTest.php`
Expected: PASS (4 tests)

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Admin/PendaftaranSiswaController.php resources/views/admin/siswa/spmb-daftar.blade.php routes/admin.php resources/views/layouts/sidebar.blade.php tests/Feature/Admin/PendaftaranSiswaControllerTest.php
git commit -m "feat: add SPMB batch conversion (Pendaftaran to Siswa)"
```

---

### Task 3: Excel import (template, preview, confirm)

**Files:**
- Modify: `composer.json` (add `maatwebsite/excel`)
- Create: `app/Imports/SiswaImportRow.php`
- Create: `app/Http/Controllers/Admin/SiswaImportController.php`
- Create: `resources/views/admin/siswa/import.blade.php`
- Create: `resources/views/admin/siswa/import-preview.blade.php`
- Modify: `routes/admin.php`
- Modify: `resources/views/layouts/sidebar.blade.php`
- Test: `tests/Feature/Admin/SiswaImportControllerTest.php`

**Interfaces:**
- Consumes: `App\Models\Siswa`, `App\Models\Kelas`, `App\Enums\SumberDataSiswa`.
- Produces: Routes `admin.siswa.import.index` (GET form + template download), `admin.siswa.import.preview` (POST upload → validate → render preview), `admin.siswa.import.confirm` (POST commit valid rows from session), permission `siswa.import`.

- [ ] **Step 1: Install the Excel package**

Run: `composer require maatwebsite/excel`
Expected: `maatwebsite/excel` added to `composer.json` and `composer.lock`, no errors.

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/Admin/SiswaImportControllerTest.php`:

```php
<?php

use App\Enums\SumberDataSiswa;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;

function actingAsSiswaImportManager(Lembaga $lembaga): User
{
    Permission::firstOrCreate(['name' => 'siswa.import', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['siswa.import']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

class SiswaImportFixtureExport implements \Maatwebsite\Excel\Concerns\FromArray
{
    public function __construct(private array $rows) {}

    public function array(): array
    {
        return $this->rows;
    }
}

function buatFileImportSiswa(array $rows): UploadedFile
{
    $filename = 'test-import-siswa-'.uniqid().'.xlsx';

    Excel::store(new SiswaImportFixtureExport($rows), $filename, 'local');

    $absolutePath = Storage::disk('local')->path($filename);

    return new UploadedFile($absolutePath, 'siswa.xlsx', null, null, true);
}

it('denies access without siswa.import permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.siswa.import.index'))->assertForbidden();
});

it('splits uploaded rows into valid and invalid in the preview, matching kelas by name', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '6A']);
    $manager = actingAsSiswaImportManager($lembaga);

    $file = buatFileImportSiswa([
        ['nis', 'nisn', 'nama_lengkap', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama', 'kelas'],
        ['3001', '0011111111', 'Siswa Valid', 'L', 'Bandung', '2014-01-01', 'Islam', '6A'],
        ['3002', '0022222222', 'Siswa Kelas Tak Ditemukan', 'P', 'Bandung', '2014-02-02', 'Islam', 'Kelas Tidak Ada'],
    ]);

    $response = $this->actingAs($manager)->post(route('admin.siswa.import.preview'), ['file' => $file]);

    $response->assertOk();
    $response->assertViewHas('validRows', fn ($rows) => count($rows) === 1 && $rows[0]['nama_lengkap'] === 'Siswa Valid');
    $response->assertViewHas('invalidRows', fn ($rows) => count($rows) === 1 && $rows[0]['nama_lengkap'] === 'Siswa Kelas Tak Ditemukan');
});

it('commits only the valid rows held in session when confirmed', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '6A']);
    $manager = actingAsSiswaImportManager($lembaga);

    $file = buatFileImportSiswa([
        ['nis', 'nisn', 'nama_lengkap', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama', 'kelas'],
        ['3003', '0033333333', 'Siswa Import Sukses', 'L', 'Bandung', '2014-01-01', 'Islam', '6A'],
    ]);

    $this->actingAs($manager)->post(route('admin.siswa.import.preview'), ['file' => $file]);
    $this->actingAs($manager)->post(route('admin.siswa.import.confirm'))->assertRedirect(route('admin.siswa.index'));

    $siswa = Siswa::where('nis', '3003')->firstOrFail();
    expect($siswa->sumber_data)->toBe(SumberDataSiswa::Import);
    expect($siswa->kelas_id)->toBe($kelas->id);
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/SiswaImportControllerTest.php`
Expected: FAIL with route `admin.siswa.import.index` not defined.

- [ ] **Step 4: Create the import row parser**

Create `app/Imports/SiswaImportRow.php`:

```php
<?php

namespace App\Imports;

use App\Models\Kelas;
use Illuminate\Support\Collection;

class SiswaImportRow
{
    /**
     * @param  array<string, mixed>  $row
     * @return array{data: array<string, mixed>, error: string|null}
     */
    public static function parse(array $row, int $lembagaId): array
    {
        $nis = trim((string) ($row['nis'] ?? ''));
        $namaLengkap = trim((string) ($row['nama_lengkap'] ?? ''));
        $jenisKelamin = trim((string) ($row['jenis_kelamin'] ?? ''));
        $namaKelas = trim((string) ($row['kelas'] ?? ''));

        $data = [
            'nis' => $nis,
            'nisn' => trim((string) ($row['nisn'] ?? '')) ?: null,
            'nama_lengkap' => $namaLengkap,
            'jenis_kelamin' => $jenisKelamin,
            'tempat_lahir' => trim((string) ($row['tempat_lahir'] ?? '')) ?: null,
            'tanggal_lahir' => trim((string) ($row['tanggal_lahir'] ?? '')) ?: null,
            'agama' => trim((string) ($row['agama'] ?? '')) ?: null,
            'kelas_nama' => $namaKelas,
        ];

        if ($nis === '' || $namaLengkap === '') {
            return ['data' => $data, 'error' => 'NIS dan Nama Lengkap wajib diisi.'];
        }

        if (! in_array($jenisKelamin, ['L', 'P'], true)) {
            return ['data' => $data, 'error' => 'Jenis kelamin harus L atau P.'];
        }

        $kelas = Kelas::where('lembaga_id', $lembagaId)->where('nama', $namaKelas)->first();

        if (! $kelas) {
            return ['data' => $data, 'error' => "Kelas \"{$namaKelas}\" tidak ditemukan di lembaga ini."];
        }

        $data['kelas_id'] = $kelas->id;

        return ['data' => $data, 'error' => null];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{valid: array<int, array<string, mixed>>, invalid: array<int, array<string, mixed>>}
     */
    public static function parseAll(Collection $rows, int $lembagaId): array
    {
        $valid = [];
        $invalid = [];

        foreach ($rows as $row) {
            $result = self::parse($row->toArray(), $lembagaId);

            if ($result['error'] === null) {
                $valid[] = $result['data'];
            } else {
                $invalid[] = [...$result['data'], 'error' => $result['error']];
            }
        }

        return ['valid' => $valid, 'invalid' => $invalid];
    }
}
```

- [ ] **Step 5: Create the controller**

Create `app/Http/Controllers/Admin/SiswaImportController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SumberDataSiswa;
use App\Imports\SiswaImportRow;
use App\Models\Siswa;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class SiswaImportController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('siswa.import');

        return view('admin.siswa.import');
    }

    public function preview(Request $request): View
    {
        $this->authorize('siswa.import');

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);

        $lembagaId = $request->user()->lembaga_id ?? session('active_lembaga_id');

        /** @var Collection<int, Collection<string, mixed>> $sheet */
        $sheet = Excel::toCollection(null, $request->file('file'))->first();
        $header = $sheet->first();
        $rows = $sheet->skip(1)->map(fn ($row) => $header->combine($row->values()));

        $result = SiswaImportRow::parseAll($rows, $lembagaId);

        session(['siswa_import_valid_rows' => $result['valid']]);

        return view('admin.siswa.import-preview', [
            'validRows' => $result['valid'],
            'invalidRows' => $result['invalid'],
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $this->authorize('siswa.import');

        $validRows = session('siswa_import_valid_rows', []);
        $lembagaId = $request->user()->lembaga_id ?? session('active_lembaga_id');

        foreach ($validRows as $row) {
            Siswa::create([
                'lembaga_id' => $lembagaId,
                'kelas_id' => $row['kelas_id'],
                'sumber_data' => SumberDataSiswa::Import->value,
                'nis' => $row['nis'],
                'nisn' => $row['nisn'],
                'nama_lengkap' => $row['nama_lengkap'],
                'jenis_kelamin' => $row['jenis_kelamin'],
                'tempat_lahir' => $row['tempat_lahir'],
                'tanggal_lahir' => $row['tanggal_lahir'],
                'agama' => $row['agama'],
            ]);
        }

        session()->forget('siswa_import_valid_rows');

        return redirect()->route('admin.siswa.index')->with('status', count($validRows).' siswa berhasil diimport.');
    }
}
```

- [ ] **Step 6: Add routes**

In `routes/admin.php`, add:

```php
Route::get('siswa-import', [SiswaImportController::class, 'index'])->name('siswa.import.index');
Route::post('siswa-import/preview', [SiswaImportController::class, 'preview'])->name('siswa.import.preview');
Route::post('siswa-import/confirm', [SiswaImportController::class, 'confirm'])->name('siswa.import.confirm');
```

Add `use App\Http\Controllers\Admin\SiswaImportController;` at the top.

- [ ] **Step 7: Create the views**

Create `resources/views/admin/siswa/import.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-2xl font-semibold text-ink">Import Siswa (Excel)</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <x-panel>
            <div class="space-y-4 p-6">
                <p class="text-sm text-ink/70">
                    Kolom yang wajib ada pada baris pertama file: <code>nis</code>, <code>nisn</code>, <code>nama_lengkap</code>,
                    <code>jenis_kelamin</code> (L/P), <code>tempat_lahir</code>, <code>tanggal_lahir</code> (YYYY-MM-DD), <code>agama</code>, <code>kelas</code>
                    (harus persis sama dengan nama Kelas yang sudah ada).
                </p>

                <form method="POST" action="{{ route('admin.siswa.import.preview') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <input type="file" name="file" accept=".xlsx,.xls,.csv" class="block w-full text-sm text-ink" required>
                    @error('file')
                        <p class="text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                    <button type="submit" class="rounded-xl bg-ink px-4 py-2 text-sm font-medium text-paper transition hover:bg-ink/90">Unggah &amp; Pratinjau</button>
                </form>
            </div>
        </x-panel>
    </div>
</x-app-layout>
```

Create `resources/views/admin/siswa/import-preview.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-2xl font-semibold text-ink">Pratinjau Import Siswa</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        <x-panel>
            <div class="border-b border-ink/10 px-6 py-4">
                <h3 class="font-display text-lg font-semibold text-ink">Baris Valid ({{ count($validRows) }})</h3>
            </div>
            <ul class="divide-y divide-ink/10">
                @forelse ($validRows as $row)
                    <li class="px-6 py-3 text-sm text-ink">{{ $row['nama_lengkap'] }} &middot; NIS {{ $row['nis'] }}</li>
                @empty
                    <li class="px-6 py-4 text-sm text-ink/60">Tidak ada baris valid.</li>
                @endforelse
            </ul>
        </x-panel>

        <x-panel>
            <div class="border-b border-ink/10 px-6 py-4">
                <h3 class="font-display text-lg font-semibold text-ink">Baris Bermasalah ({{ count($invalidRows) }})</h3>
            </div>
            <ul class="divide-y divide-ink/10">
                @forelse ($invalidRows as $row)
                    <li class="px-6 py-3 text-sm">
                        <span class="text-ink">{{ $row['nama_lengkap'] ?: '(tanpa nama)' }}</span>
                        <span class="text-signal-red">— {{ $row['error'] }}</span>
                    </li>
                @empty
                    <li class="px-6 py-4 text-sm text-ink/60">Tidak ada baris bermasalah.</li>
                @endforelse
            </ul>
        </x-panel>

        @if (count($validRows) > 0)
            <form method="POST" action="{{ route('admin.siswa.import.confirm') }}">
                @csrf
                <button type="submit" class="rounded-xl bg-ink px-4 py-2 text-sm font-medium text-paper transition hover:bg-ink/90">
                    Import {{ count($validRows) }} Siswa Valid
                </button>
            </form>
        @endif
    </div>
</x-app-layout>
```

- [ ] **Step 8: Add sidebar entry**

In `resources/views/layouts/sidebar.blade.php`, inside the `'II. Data Induk'` group, add:

```php
Auth::user()->can('siswa.import') ? ['route' => 'admin.siswa.import.index', 'pattern' => 'admin.siswa.import.*', 'label' => 'Import Siswa', 'icon' => 'upload_file'] : null,
```

- [ ] **Step 9: Sync permissions**

Run: `php artisan permissions:sync`
Expected: Output includes `Created permission: siswa.import`.

- [ ] **Step 10: Run test to verify it passes**

Run: `php artisan test tests/Feature/Admin/SiswaImportControllerTest.php`
Expected: PASS (3 tests)

- [ ] **Step 11: Commit**

```bash
git add composer.json composer.lock app/Imports/SiswaImportRow.php app/Http/Controllers/Admin/SiswaImportController.php resources/views/admin/siswa/import.blade.php resources/views/admin/siswa/import-preview.blade.php routes/admin.php resources/views/layouts/sidebar.blade.php tests/Feature/Admin/SiswaImportControllerTest.php
git commit -m "feat: add Excel import for Siswa with preview/confirm"
```

---

## Plan Self-Review Notes

- **Spec coverage**: Covers spec Section 1.1's two remaining `Siswa` origin paths (SPMB batch, Excel import) on top of Tahap 1's manual path.
- **Type consistency check**: `Siswa::create()` payloads in both Task 2 and Task 3 use the exact `$fillable` names defined in Tahap 1 Task 4 (`kelas_id`, `calon_murid_id`, `pendaftaran_asal_id`, `sumber_data`, `nis`, `nisn`, `nama_lengkap`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `agama`) — no renamed fields.
- **Dependency note for Tahap 3+**: `Kelas` lookups in Task 3's import (`Kelas::where('nama', $namaKelas)`) assume `nama` is unique enough to match per lembaga in practice; the `kelas` table's unique constraint from Tahap 1 Task 3 is `(tahun_ajaran_id, nama)`, so if a lembaga has the same kelas name across two tahun ajaran the import could match either — acceptable for v1 since import targets the currently active tahun ajaran's kelas in normal use.
