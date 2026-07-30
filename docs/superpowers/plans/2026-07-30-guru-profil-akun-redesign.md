# Perbaikan Modul Data Guru (Profil + Pembuatan Akun) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Modernize `admin/guru` to the current design system (index filter+table, grouped form, toast, confirm dialog) and merge account creation into the Guru form — one submit creates both `User` (email = username, NIP = password) and `Guru`.

**Architecture:** `GuruController` rewritten so `store()`/`update()` share one private validation method for the full profile field set, `store()` creates `User`+`Guru` together inside `DB::transaction()`, and a new `updateStatus()` action replaces the removed `destroy` route. Views follow the `admin/lembaga` shared-partial pattern (`_form.blade.php` included by both `create.blade.php` and `edit.blade.php`) and the `admin/kelas`/`admin/siswa` index pattern (filter card + sticky-Aksi-column table).

**Tech Stack:** Laravel 11 (existing app), Blade + Alpine.js (existing `x-table-actions`/`x-dropdown-link`/`confirmDialog` components — no new JS).

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-30-guru-profil-akun-redesign-design.md` — follow its field grouping, account-creation rules, and out-of-scope list exactly.
- **Email = username, NIP = password.** `User::create()`'s `password` is `Hash::make($data['nip'])`. Editing NIP later must NOT change the existing `User`'s password — `update()` never touches the `password` column.
- **Editing email must sync to `users.email`** (the guru's one login identity), not just `guru.email`.
- **`status_aktif` is never in the create/edit form.** It defaults to `aktif` on create and can only change via the new `PATCH admin/guru/{guru}/status` action (4 values: `aktif`, `non_aktif`, `mutasi`, `pensiun`).
- **No new permission.** `updateStatus()` reuses `guru.edit` (already seeded).
- **No import UI, no Riwayat Pendidikan/Sertifikasi/Jabatan Tambahan UI** — out of scope per spec.
- **Yayasan-scoped actor guard**: when `$request->user()->widestScopeLevel() === 'yayasan'`, resolve `lembaga_id` from `session('active_lembaga_id')` and reject with `back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah data guru.'])->withInput()` if null — copy this exact pattern from `app/Http/Controllers/Admin/KelasController.php:81-89`, don't reinvent it.
- Existing NIK validation behavior (16 digits, uniqueness via `nik_hash`, excluding the current row on update) must be preserved exactly as-is.
- All new/changed Blade markup uses the current design tokens (`rounded-2xl border border-gray-200 bg-white shadow-card`, `x-icon`, `x-badge`, `x-table-actions`/`x-dropdown-link`, `bg-success-50 text-success-700` toast blocks) — never the old `<x-panel>`/`text-ink`/`bg-paper`/`text-brass` tokens still present in the current `admin/guru/*.blade.php`.
- Run `php artisan test` (full suite) after implementation and confirm no regressions, in particular `tests/Feature/CrossTenantAuthorizationTest.php`'s two guru-related tests (index tenant-scoping, edit 404 cross-lembaga) which must keep passing unmodified.

---

## File Structure

```
app/Http/Controllers/Admin/GuruController.php   (rewrite: index/create/store/edit/update, + new updateStatus)
routes/admin.php                                 (add PATCH guru/{guru}/status route)
resources/views/admin/guru/_form.blade.php       (new — shared by create & edit)
resources/views/admin/guru/create.blade.php      (rewrite)
resources/views/admin/guru/edit.blade.php        (rewrite)
resources/views/admin/guru/index.blade.php       (rewrite)
tests/Feature/Admin/GuruCrudTest.php             (rewrite — old tests assert the removed eligibleUsers flow)
```

Confirmed route names (`routes/admin.php`, `Route::resource('guru', GuruController::class)->except(['show', 'destroy'])`, prefix `admin.`, so): `admin.guru.index`, `admin.guru.create`, `admin.guru.store`, `admin.guru.edit`, `admin.guru.update`. New: `admin.guru.update-status`.

Confirmed `guru` table columns (fillable on `App\Models\Guru`): `user_id, lembaga_id, nik, nuptk, nip, nama, jenis_kelamin, tempat_lahir, tanggal_lahir, agama, kewarganegaraan, alamat_jalan, rt, rw, desa_kelurahan, kecamatan, kabupaten_kota, provinsi, kode_pos, no_hp, email, jenis_ptk, status_kepegawaian, golongan_pangkat, tmt_tugas, tmt_pns, status_aktif`. `status_aktif` is a DB enum: `aktif|non_aktif|mutasi|pensiun`, default `aktif`. `nik` has an `encrypted` cast (model auto-decrypts on read, no special handling needed in Blade).

---

### Task 1: Rewrite GuruController, routes, and views for the new create/edit/status-change flow

**Files:**
- Modify: `app/Http/Controllers/Admin/GuruController.php`
- Modify: `routes/admin.php`
- Create: `resources/views/admin/guru/_form.blade.php`
- Modify: `resources/views/admin/guru/create.blade.php`
- Modify: `resources/views/admin/guru/edit.blade.php`
- Modify: `resources/views/admin/guru/index.blade.php`
- Modify: `tests/Feature/Admin/GuruCrudTest.php`

**Interfaces:**
- Produces routes: `admin.guru.index` (GET), `admin.guru.create` (GET), `admin.guru.store` (POST), `admin.guru.edit` (GET, route-model `{guru}`), `admin.guru.update` (PUT, `{guru}`), `admin.guru.update-status` (PATCH, `{guru}`).
- `GuruController::index()` passes to the view: `guruList` (Collection of `Guru` with `user` eager-loaded), `search`, `jenisPtk`, `statusAktif` (current filter values), `jenisPtkOptions` (assoc array value=>label), `statusAktifOptions` (assoc array value=>label).
- `GuruController::create()`/`edit()` pass: `jenisKelaminOptions`, `jenisPtkOptions`, `statusKepegawaianOptions` (assoc arrays), plus `edit()` also passes `guru` (the model).
- `_form.blade.php` expects an optional `$guru` variable (null on create) and the three options arrays above, matching the `admin/lembaga/_form.blade.php` convention (`$val = fn (string $field, $default = '') => old($field, $guru?->$field ?? $default);`).

- [ ] **Step 1: Write the failing feature tests (full rewrite of the file)**

Replace the entire contents of `tests/Feature/Admin/GuruCrudTest.php` — the old tests assert the removed `eligibleUsers`/`user_id` flow and must not survive:

```php
<?php

use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

function actingAsGuruManager(Lembaga $lembaga): User
{
    foreach (['guru.view', 'guru.create', 'guru.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['guru.view', 'guru.create', 'guru.edit']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

function guruFormPayload(array $overrides = []): array
{
    return array_merge([
        'nik' => '3201234567891234',
        'nip' => '198501012010011001',
        'nama' => 'Guru Baru',
        'email' => 'guru.baru@permata.sch.id',
        'jenis_kelamin' => 'P',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ], $overrides);
}

it('denies access to a user without guru.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.guru.index'))->assertForbidden();
});

it('creates both a User account and a Guru profile in one submit, with NIP as the hashed password', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembaga);
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $this->actingAs($manager)->post(route('admin.guru.store'), guruFormPayload())
        ->assertRedirect(route('admin.guru.index'));

    $guru = Guru::where('nama', 'Guru Baru')->first();
    expect($guru)->not->toBeNull();
    expect($guru->lembaga_id)->toBe($lembaga->id);
    expect($guru->status_aktif)->toBe('aktif');

    $user = $guru->user;
    expect($user)->not->toBeNull();
    expect($user->email)->toBe('guru.baru@permata.sch.id');
    expect($user->lembaga_id)->toBe($lembaga->id);
    expect(Hash::check('198501012010011001', $user->password))->toBeTrue();
    expect($user->hasRole('guru'))->toBeTrue();
});

it('rejects creating a guru without a NIP', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembaga);

    $this->actingAs($manager)->post(route('admin.guru.store'), guruFormPayload(['nip' => '']))
        ->assertSessionHasErrors('nip');

    expect(Guru::where('nama', 'Guru Baru')->exists())->toBeFalse();
});

it('rejects creating a guru with an email already used by another account', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembaga);
    User::factory()->create(['email' => 'guru.baru@permata.sch.id']);

    $this->actingAs($manager)->post(route('admin.guru.store'), guruFormPayload())
        ->assertSessionHasErrors('email');

    expect(Guru::where('nama', 'Guru Baru')->exists())->toBeFalse();
});

it('shows a friendly validation error instead of a 500 when creating a guru with a duplicate NIK', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembaga);
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $this->actingAs($manager)->post(route('admin.guru.store'), guruFormPayload())->assertRedirect();

    $this->actingAs($manager)->post(route('admin.guru.store'), guruFormPayload([
        'nama' => 'Guru Kedua',
        'email' => 'guru.kedua@permata.sch.id',
    ]))->assertSessionHasErrors('nik');

    expect(Guru::where('nama', 'Guru Kedua')->exists())->toBeFalse();
});

it('only lists guru belonging to the acting lembaga-scoped manager\'s own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembagaA);

    Guru::create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembagaA->id])->id,
        'lembaga_id' => $lembagaA->id,
        'nik' => '3201234567895555',
        'nama' => 'Guru Lembaga A',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    Guru::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembagaB->id])->id,
        'lembaga_id' => $lembagaB->id,
        'nik' => '3201234567896666',
        'nama' => 'Guru Lembaga B',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    $response = $this->actingAs($manager)->get(route('admin.guru.index'));

    $response->assertSee('Guru Lembaga A');
    $response->assertDontSee('Guru Lembaga B');
});

it('filters the index by search, jenis_ptk, and status_aktif', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembaga);

    Guru::create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id, 'nik' => '3201234567897777', 'nama' => 'Budi Santoso',
        'jenis_kelamin' => 'L', 'jenis_ptk' => 'guru_mapel', 'status_kepegawaian' => 'PNS', 'status_aktif' => 'aktif',
    ]);
    Guru::create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id, 'nik' => '3201234567898888', 'nama' => 'Siti Rahmawati',
        'jenis_kelamin' => 'P', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY', 'status_aktif' => 'non_aktif',
    ]);

    $bySearch = $this->actingAs($manager)->get(route('admin.guru.index', ['search' => 'Budi']));
    $bySearch->assertSee('Budi Santoso')->assertDontSee('Siti Rahmawati');

    $byJenisPtk = $this->actingAs($manager)->get(route('admin.guru.index', ['jenis_ptk' => 'guru_kelas']));
    $byJenisPtk->assertSee('Siti Rahmawati')->assertDontSee('Budi Santoso');

    $byStatus = $this->actingAs($manager)->get(route('admin.guru.index', ['status_aktif' => 'non_aktif']));
    $byStatus->assertSee('Siti Rahmawati')->assertDontSee('Budi Santoso');
});

it('updates guru profile fields without changing the linked User password', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembaga);

    $user = User::factory()->create(['lembaga_id' => $lembaga->id, 'email' => 'lama@permata.sch.id']);
    $originalHash = $user->password;
    $guru = Guru::create([
        'user_id' => $user->id, 'lembaga_id' => $lembaga->id, 'nik' => '3201234567898899',
        'nip' => '198001011990011001', 'nama' => 'Guru Uji Update', 'email' => 'lama@permata.sch.id',
        'jenis_kelamin' => 'L', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
    ]);

    $this->actingAs($manager)->put(route('admin.guru.update', $guru), guruFormPayload([
        'nama' => 'Guru Uji Update Baru',
        'email' => 'baru@permata.sch.id',
        'nip' => '999999999999999999',
    ]))->assertRedirect(route('admin.guru.index'));

    expect($guru->fresh()->nama)->toBe('Guru Uji Update Baru');
    expect($guru->fresh()->nip)->toBe('999999999999999999');
    expect($user->fresh()->email)->toBe('baru@permata.sch.id');
    expect($user->fresh()->password)->toBe($originalHash);
});

it('changes status_aktif via the dedicated status action, rejecting values outside the 4-state enum', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsGuruManager($lembaga);

    $guru = Guru::create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id, 'nik' => '3201234567899900', 'nama' => 'Guru Status',
        'jenis_kelamin' => 'L', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY', 'status_aktif' => 'aktif',
    ]);

    $this->actingAs($manager)->patch(route('admin.guru.update-status', $guru), ['status_aktif' => 'mutasi'])
        ->assertRedirect(route('admin.guru.index'));
    expect($guru->fresh()->status_aktif)->toBe('mutasi');

    $this->actingAs($manager)->patch(route('admin.guru.update-status', $guru), ['status_aktif' => 'not_a_real_status'])
        ->assertSessionHasErrors('status_aktif');
});

it('denies status change without guru.edit permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id, 'nik' => '3201234567899901', 'nama' => 'Guru Lain',
        'jenis_kelamin' => 'L', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
    ]);

    $this->actingAs(User::factory()->create())
        ->patch(route('admin.guru.update-status', $guru), ['status_aktif' => 'non_aktif'])
        ->assertForbidden();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/GuruCrudTest.php`
Expected: FAIL — route `admin.guru.update-status` doesn't exist yet, `store()` still expects `user_id`, etc.

- [ ] **Step 3: Add the status route**

In `routes/admin.php`, immediately after the line `Route::resource('guru', GuruController::class)->except(['show', 'destroy']);`, add:

```php
    Route::patch('guru/{guru}/status', [GuruController::class, 'updateStatus'])->name('guru.update-status');
```

- [ ] **Step 4: Rewrite `GuruController`**

Replace the entire contents of `app/Http/Controllers/Admin/GuruController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\Guru;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GuruController extends BaseController
{
    use AuthorizesRequests;

    private const JENIS_KELAMIN_OPTIONS = ['L' => 'Laki-laki', 'P' => 'Perempuan'];

    private const JENIS_PTK_OPTIONS = [
        'guru_kelas' => 'Guru Kelas',
        'guru_mapel' => 'Guru Mapel',
        'kepala_sekolah' => 'Kepala Sekolah',
        'tenaga_administrasi' => 'Tenaga Administrasi',
    ];

    private const STATUS_KEPEGAWAIAN_OPTIONS = [
        'PNS' => 'PNS', 'PPPK' => 'PPPK', 'GTY' => 'GTY', 'PTY' => 'PTY', 'Honorer' => 'Honorer',
    ];

    private const STATUS_AKTIF_OPTIONS = [
        'aktif' => 'Aktif', 'non_aktif' => 'Non Aktif', 'mutasi' => 'Mutasi', 'pensiun' => 'Pensiun',
    ];

    public function index(Request $request): View
    {
        $this->authorize('guru.view');

        $search = $request->query('search');
        $jenisPtk = $request->query('jenis_ptk');
        $statusAktif = $request->query('status_aktif');

        $guruList = Guru::with('user')
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2->where('nama', 'like', "%{$search}%")->orWhere('nip', 'like', "%{$search}%")))
            ->when($jenisPtk, fn ($q) => $q->where('jenis_ptk', $jenisPtk))
            ->when($statusAktif, fn ($q) => $q->where('status_aktif', $statusAktif))
            ->orderBy('nama')
            ->get();

        return view('admin.guru.index', [
            'guruList' => $guruList,
            'search' => $search,
            'jenisPtk' => $jenisPtk,
            'statusAktif' => $statusAktif,
            'jenisPtkOptions' => self::JENIS_PTK_OPTIONS,
            'statusAktifOptions' => self::STATUS_AKTIF_OPTIONS,
        ]);
    }

    public function create(): View
    {
        $this->authorize('guru.create');

        return view('admin.guru.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('guru.create');

        $data = $this->validateProfil($request);

        $lembagaId = $this->resolveLembagaId($request);
        if ($lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah data guru.'])->withInput();
        }

        DB::transaction(function () use ($data, $lembagaId) {
            $user = User::create([
                'name' => $data['nama'],
                'email' => $data['email'],
                'password' => Hash::make($data['nip']),
                'lembaga_id' => $lembagaId,
                'email_verified_at' => now(),
                'is_active' => true,
            ]);
            $user->assignRole('guru');

            Guru::create([
                ...$data,
                'user_id' => $user->id,
                'lembaga_id' => $lembagaId,
            ]);
        });

        return redirect()->route('admin.guru.index')->with('status', 'Data guru & akun berhasil dibuat.');
    }

    public function edit(Guru $guru): View
    {
        $this->authorize('guru.edit');

        return view('admin.guru.edit', [
            'guru' => $guru,
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, Guru $guru): RedirectResponse
    {
        $this->authorize('guru.edit');

        $data = $this->validateProfil($request, $guru);

        DB::transaction(function () use ($data, $guru) {
            $guru->user()->update([
                'name' => $data['nama'],
                'email' => $data['email'],
            ]);

            $guru->update($data);
        });

        return redirect()->route('admin.guru.index')->with('status', 'Data guru berhasil diperbarui.');
    }

    public function updateStatus(Request $request, Guru $guru): RedirectResponse
    {
        $this->authorize('guru.edit');

        $data = $request->validate([
            'status_aktif' => ['required', 'in:aktif,non_aktif,mutasi,pensiun'],
        ]);

        $guru->update(['status_aktif' => $data['status_aktif']]);

        return redirect()->route('admin.guru.index')->with('status', 'Status guru berhasil diperbarui.');
    }

    private function formOptions(): array
    {
        return [
            'jenisKelaminOptions' => self::JENIS_KELAMIN_OPTIONS,
            'jenisPtkOptions' => self::JENIS_PTK_OPTIONS,
            'statusKepegawaianOptions' => self::STATUS_KEPEGAWAIAN_OPTIONS,
        ];
    }

    private function resolveLembagaId(Request $request): ?int
    {
        if ($request->user()->widestScopeLevel() === 'yayasan') {
            return session('active_lembaga_id');
        }

        return $request->user()->lembaga_id;
    }

    private function validateProfil(Request $request, ?Guru $guru = null): array
    {
        $data = $request->validate([
            'nik' => ['required', 'digits:16', function ($attribute, $value, $fail) use ($guru) {
                $query = Guru::withoutGlobalScopes()->where('nik_hash', hash('sha256', $value));
                if ($guru) {
                    $query->where('id', '!=', $guru->id);
                }
                if ($query->exists()) {
                    $fail('NIK sudah terdaftar untuk guru lain.');
                }
            }],
            'nip' => ['required', 'string', 'max:30'],
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($guru?->user_id)],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'jenis_ptk' => ['required', 'in:guru_kelas,guru_mapel,kepala_sekolah,tenaga_administrasi'],
            'status_kepegawaian' => ['required', 'in:PNS,PPPK,GTY,PTY,Honorer'],
            'nuptk' => ['nullable', 'string', 'max:30'],
            'tempat_lahir' => ['nullable', 'string', 'max:255'],
            'tanggal_lahir' => ['nullable', 'date'],
            'agama' => ['nullable', 'string', 'max:50'],
            'kewarganegaraan' => ['nullable', 'string', 'max:50'],
            'no_hp' => ['nullable', 'string', 'max:20'],
            'alamat_jalan' => ['nullable', 'string'],
            'rt' => ['nullable', 'string', 'max:5'],
            'rw' => ['nullable', 'string', 'max:5'],
            'desa_kelurahan' => ['nullable', 'string', 'max:255'],
            'kecamatan' => ['nullable', 'string', 'max:255'],
            'kabupaten_kota' => ['nullable', 'string', 'max:255'],
            'provinsi' => ['nullable', 'string', 'max:255'],
            'kode_pos' => ['nullable', 'string', 'max:10'],
            'golongan_pangkat' => ['nullable', 'string', 'max:50'],
            'tmt_tugas' => ['nullable', 'date'],
            'tmt_pns' => ['nullable', 'date'],
        ]);

        $data['kewarganegaraan'] = $data['kewarganegaraan'] ?: 'WNI';

        return $data;
    }
}
```

- [ ] **Step 5: Run the tests again — expect route/controller-level tests to pass, view-rendering GET assertions to still fail**

Run: `php artisan test tests/Feature/Admin/GuruCrudTest.php`
Expected: the POST/PUT/PATCH-only tests (create, duplicate NIK, update, status change, missing NIP, duplicate email) now PASS. The two GET-based tests (`denies access...index`, `only lists guru...`, `filters the index...`) will FAIL or ERROR because `admin.guru.index`/`create`/`edit` views still reference removed variables (`$guru` instead of `$guruList`, `$eligibleUsers`). This is expected — views are rewritten next.

- [ ] **Step 6: Create the shared form partial**

Create `resources/views/admin/guru/_form.blade.php`:

```blade
@php
    $guru = $guru ?? null;
    $val = fn (string $field, $default = '') => old($field, $guru?->$field ?? $default);
    $inputClass = 'mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500';
    $selectClass = 'mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500';
@endphp

<div class="space-y-4">
    {{-- Akun & Identitas --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
        <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
            <x-icon name="person" class="h-[15px] w-[15px] text-gray-400" />
            Akun &amp; Identitas
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <x-input-label value="Nama Lengkap *" />
                <input type="text" name="nama" value="{{ $val('nama') }}" class="{{ $inputClass }}">
                <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="NIK *" />
                <input type="text" name="nik" value="{{ $val('nik') }}" class="{{ $inputClass }} font-mono" maxlength="16">
                <x-input-error :messages="$errors->get('nik')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="NIP *" />
                <input type="text" name="nip" value="{{ $val('nip') }}" class="{{ $inputClass }} font-mono">
                <p class="mt-1 text-xs text-gray-400">NIP ini otomatis menjadi password login guru.</p>
                <x-input-error :messages="$errors->get('nip')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Email *" />
                <input type="email" name="email" value="{{ $val('email') }}" class="{{ $inputClass }}">
                <p class="mt-1 text-xs text-gray-400">Email ini menjadi username login guru.</p>
                <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Jenis Kelamin *" />
                <select name="jenis_kelamin" class="{{ $selectClass }}">
                    @foreach ($jenisKelaminOptions as $value => $label)
                        <option value="{{ $value }}" @selected($val('jenis_kelamin') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('jenis_kelamin')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Jenis PTK *" />
                <select name="jenis_ptk" class="{{ $selectClass }}">
                    @foreach ($jenisPtkOptions as $value => $label)
                        <option value="{{ $value }}" @selected($val('jenis_ptk') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('jenis_ptk')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label value="Status Kepegawaian *" />
                <select name="status_kepegawaian" class="{{ $selectClass }}">
                    @foreach ($statusKepegawaianOptions as $value => $label)
                        <option value="{{ $value }}" @selected($val('status_kepegawaian') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('status_kepegawaian')" class="mt-1.5" />
            </div>
        </div>
    </div>

    {{-- Data Pribadi --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
        <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
            <x-icon name="description" class="h-[15px] w-[15px] text-gray-400" />
            Data Pribadi (Opsional)
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <x-input-label value="NUPTK" />
                <input type="text" name="nuptk" value="{{ $val('nuptk') }}" class="{{ $inputClass }} font-mono">
                <x-input-error :messages="$errors->get('nuptk')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Tempat Lahir" />
                <input type="text" name="tempat_lahir" value="{{ $val('tempat_lahir') }}" class="{{ $inputClass }}">
            </div>
            <div>
                <x-input-label value="Tanggal Lahir" />
                <input type="date" name="tanggal_lahir" value="{{ $val('tanggal_lahir') ? \Illuminate\Support\Carbon::parse($val('tanggal_lahir'))->format('Y-m-d') : '' }}" class="{{ $inputClass }}">
            </div>
            <div>
                <x-input-label value="Agama" />
                <input type="text" name="agama" value="{{ $val('agama') }}" class="{{ $inputClass }}">
            </div>
            <div>
                <x-input-label value="Kewarganegaraan" />
                <input type="text" name="kewarganegaraan" value="{{ $val('kewarganegaraan', 'WNI') }}" class="{{ $inputClass }}">
            </div>
            <div>
                <x-input-label value="No. HP" />
                <input type="text" name="no_hp" value="{{ $val('no_hp') }}" class="{{ $inputClass }}">
            </div>
        </div>
    </div>

    {{-- Alamat --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
        <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
            <x-icon name="location_on" class="h-[15px] w-[15px] text-gray-400" />
            Alamat (Opsional)
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="sm:col-span-2 lg:col-span-3">
                <x-input-label value="Alamat Jalan" />
                <input type="text" name="alamat_jalan" value="{{ $val('alamat_jalan') }}" class="{{ $inputClass }}">
            </div>
            <div>
                <x-input-label value="RT" />
                <input type="text" name="rt" value="{{ $val('rt') }}" class="{{ $inputClass }}">
            </div>
            <div>
                <x-input-label value="RW" />
                <input type="text" name="rw" value="{{ $val('rw') }}" class="{{ $inputClass }}">
            </div>
            <div>
                <x-input-label value="Kode Pos" />
                <input type="text" name="kode_pos" value="{{ $val('kode_pos') }}" class="{{ $inputClass }}">
            </div>
            <div>
                <x-input-label value="Desa/Kelurahan" />
                <input type="text" name="desa_kelurahan" value="{{ $val('desa_kelurahan') }}" class="{{ $inputClass }}">
            </div>
            <div>
                <x-input-label value="Kecamatan" />
                <input type="text" name="kecamatan" value="{{ $val('kecamatan') }}" class="{{ $inputClass }}">
            </div>
            <div>
                <x-input-label value="Kabupaten/Kota" />
                <input type="text" name="kabupaten_kota" value="{{ $val('kabupaten_kota') }}" class="{{ $inputClass }}">
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <x-input-label value="Provinsi" />
                <input type="text" name="provinsi" value="{{ $val('provinsi') }}" class="{{ $inputClass }}">
            </div>
        </div>
    </div>

    {{-- Kepegawaian --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
        <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
            <x-icon name="checklist" class="h-[15px] w-[15px] text-gray-400" />
            Kepegawaian (Opsional)
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <x-input-label value="Golongan/Pangkat" />
                <input type="text" name="golongan_pangkat" value="{{ $val('golongan_pangkat') }}" class="{{ $inputClass }}">
            </div>
            <div>
                <x-input-label value="TMT Tugas" />
                <input type="date" name="tmt_tugas" value="{{ $val('tmt_tugas') ? \Illuminate\Support\Carbon::parse($val('tmt_tugas'))->format('Y-m-d') : '' }}" class="{{ $inputClass }}">
            </div>
            <div>
                <x-input-label value="TMT PNS" />
                <input type="date" name="tmt_pns" value="{{ $val('tmt_pns') ? \Illuminate\Support\Carbon::parse($val('tmt_pns'))->format('Y-m-d') : '' }}" class="{{ $inputClass }}">
            </div>
        </div>
    </div>
</div>
```

- [ ] **Step 7: Rewrite `create.blade.php`**

Replace `resources/views/admin/guru/create.blade.php`:

```blade
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Tambah Data Guru</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.guru.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Guru</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Tambah</b>
            </p>
        </div>

        <form method="POST" action="{{ route('admin.guru.store') }}">
            @csrf

            @include('admin.guru._form', [
                'jenisKelaminOptions' => $jenisKelaminOptions,
                'jenisPtkOptions' => $jenisPtkOptions,
                'statusKepegawaianOptions' => $statusKepegawaianOptions,
            ])

            <div class="mt-4 flex items-center gap-3">
                <x-primary-button type="submit">Simpan Data Guru</x-primary-button>
                <a href="{{ route('admin.guru.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Batal</a>
            </div>
        </form>
    </div>
</x-app-layout>
```

- [ ] **Step 8: Rewrite `edit.blade.php`**

Replace `resources/views/admin/guru/edit.blade.php`:

```blade
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Edit Data Guru: {{ $guru->nama }}</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.guru.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Guru</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Edit</b>
            </p>
        </div>

        <form method="POST" action="{{ route('admin.guru.update', $guru) }}">
            @csrf
            @method('PUT')

            @include('admin.guru._form', [
                'guru' => $guru,
                'jenisKelaminOptions' => $jenisKelaminOptions,
                'jenisPtkOptions' => $jenisPtkOptions,
                'statusKepegawaianOptions' => $statusKepegawaianOptions,
            ])

            <div class="mt-4 flex items-center gap-3">
                <x-primary-button type="submit">Simpan Perubahan</x-primary-button>
                <a href="{{ route('admin.guru.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Batal</a>
            </div>
        </form>
    </div>
</x-app-layout>
```

- [ ] **Step 9: Rewrite `index.blade.php`**

Replace `resources/views/admin/guru/index.blade.php`:

```blade
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Guru</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola data induk guru dan akun login masing-masing.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Guru</b>
            </p>
        </div>

        {{-- Filter Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                    Filter Data
                </p>
                <x-link-button href="{{ route('admin.guru.create') }}">
                    <span class="text-base leading-none">+</span> Tambah Data Guru
                </x-link-button>
            </div>

            <form method="GET" action="{{ route('admin.guru.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="search" class="mb-1.5 block text-xs font-semibold text-gray-500">Cari</label>
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                        <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                        <input
                            type="text" name="search" id="search"
                            value="{{ $search }}"
                            placeholder="Nama atau NIP"
                            @input.debounce.500ms="$el.form.submit()"
                            class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0"
                        >
                    </div>
                </div>

                <div>
                    <label for="jenis_ptk" class="mb-1.5 block text-xs font-semibold text-gray-500">Jenis PTK</label>
                    <select name="jenis_ptk" id="jenis_ptk" @change="$el.form.submit()" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Semua Jenis PTK</option>
                        @foreach ($jenisPtkOptions as $value => $label)
                            <option value="{{ $value }}" @selected($jenisPtk === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="status_aktif" class="mb-1.5 block text-xs font-semibold text-gray-500">Status Aktif</label>
                    <select name="status_aktif" id="status_aktif" @change="$el.form.submit()" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Semua Status</option>
                        @foreach ($statusAktifOptions as $value => $label)
                            <option value="{{ $value }}" @selected($statusAktif === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end">
                    @if (request()->anyFilled(['search', 'jenis_ptk', 'status_aktif']))
                        <a href="{{ route('admin.guru.index') }}" class="flex h-[42px] w-full items-center justify-center rounded-lg border border-gray-200 px-3 text-sm text-gray-500 transition hover:bg-gray-50">
                            Reset Filter
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Table Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
                <p class="font-display text-sm font-bold text-gray-900">Daftar Guru</p>
                <x-badge tone="brass" class="text-xs font-semibold px-2.5 py-0.5">{{ $guruList->count() }} Data</x-badge>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
                            <th class="px-5 py-3">Nama</th>
                            <th class="px-5 py-3">NIP</th>
                            <th class="px-5 py-3">Jenis PTK</th>
                            <th class="px-5 py-3">Status Kepegawaian</th>
                            <th class="px-5 py-3">Status Aktif</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($guruList as $item)
                            <tr class="transition hover:bg-gray-50">
                                <td class="sticky left-0 z-10 bg-white px-5 py-3">
                                    <x-table-actions>
                                        <x-dropdown-link :href="route('admin.guru.edit', $item)">
                                            <span class="inline-flex items-center gap-2.5">
                                                <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                                Edit Guru
                                            </span>
                                        </x-dropdown-link>
                                        @foreach ($statusAktifOptions as $value => $label)
                                            @if ($value !== $item->status_aktif)
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.guru.update-status', $item) }}"
                                                    x-data
                                                    @submit.prevent="confirmDialog('Ubah Status Guru?', @js('Ubah status \"' . $item->nama . '\" menjadi \"' . $label . '\"?'), { confirmLabel: 'Ya, Ubah' }).then(confirmed => { if (confirmed) $el.submit() })"
                                                >
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status_aktif" value="{{ $value }}">
                                                    <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                                        <x-icon name="autorenew" class="h-4 w-4 text-gray-500" />
                                                        Jadikan {{ $label }}
                                                    </button>
                                                </form>
                                            @endif
                                        @endforeach
                                    </x-table-actions>
                                </td>
                                <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $item->nama }}</td>
                                <td class="px-5 py-3.5 font-mono text-xs text-gray-600">{{ $item->nip ?: '—' }}</td>
                                <td class="px-5 py-3.5 text-gray-600">{{ $jenisPtkOptions[$item->jenis_ptk] ?? $item->jenis_ptk }}</td>
                                <td class="px-5 py-3.5">
                                    @if (in_array($item->status_kepegawaian, ['PNS', 'PPPK']))
                                        <x-badge tone="brass">{{ $item->status_kepegawaian }}</x-badge>
                                    @else
                                        <x-badge tone="slate">{{ $item->status_kepegawaian }}</x-badge>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5">
                                    <x-badge tone="{{ $item->status_aktif === 'aktif' ? 'green' : 'amber' }}">
                                        {{ $statusAktifOptions[$item->status_aktif] ?? $item->status_aktif }}
                                    </x-badge>
                                </td>
                            </tr>
                        @endforeach

                        @if ($guruList->isEmpty())
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-gray-400">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                        <x-icon name="school" class="h-7 w-7" />
                                    </div>
                                    <p class="mt-3 text-sm font-semibold text-gray-700">Belum Ada Data Guru</p>
                                    <p class="mx-auto mt-0.5 max-w-sm text-xs text-gray-400">Tambahkan data guru pertama untuk lembaga ini.</p>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 10: Run the full test file and confirm all pass**

Run: `php artisan test tests/Feature/Admin/GuruCrudTest.php -v`
Expected: all tests PASS. If any fails, read the actual failure — do not guess; common causes at this point are a Blade variable name mismatch or the `x-icon` names used (`person`, `location_on`, `checklist`, `description`, `filter`, `search`, `edit`, `autorenew`, `school` — all of these already exist in `resources/views/components/icon.blade.php`, confirmed present as of this plan being written; if any 500s with an icon-related error, check that component file directly).

- [ ] **Step 11: Run the two cross-tenant guru tests plus the full suite**

Run: `php artisan test tests/Feature/CrossTenantAuthorizationTest.php`
Expected: PASS unchanged (these test `admin.guru.edit` 404-on-foreign-lembaga and `admin.guru.index` tenant-scoping via the switcher — neither depends on the removed `user_id` flow).

Run: `php artisan test`
Expected: full suite passes, no regressions elsewhere (in particular seeders/tests that create `Guru` rows directly via `Guru::create()` bypassing the controller are unaffected, since the `guru` table schema itself did not change).

- [ ] **Step 12: Manually verify in the browser**

Start the app (`php artisan serve` or your local vhost), log in as `akademik@sistem.test` — wait, `admin_akademik` does not hold `guru.*` permissions by default (only `yayasan_super_admin` does, per `RoleSeeder`) — log in as `superadmin@sistem.test` instead. Visit `/admin/guru`, click "+ Tambah Data Guru", fill the form with a fresh NIK/NIP/email, submit, confirm the toast reads "Data guru & akun berhasil dibuat.", confirm the new row appears with a green "Aktif" badge, open the row's Aksi dropdown and change status to "Mutasi" (confirm dialog appears, confirm it, badge updates to amber "Mutasi"), then log out and confirm the newly created guru account can log in with the email as username and the NIP as password.

- [ ] **Step 13: Commit**

```bash
git add app/Http/Controllers/Admin/GuruController.php routes/admin.php resources/views/admin/guru/_form.blade.php resources/views/admin/guru/create.blade.php resources/views/admin/guru/edit.blade.php resources/views/admin/guru/index.blade.php tests/Feature/Admin/GuruCrudTest.php
git commit -m "feat: redesign admin/guru to current design system, merge account creation into the form"
```

---

## Self-Review Notes

- **Spec coverage:** every section of the design spec has a corresponding piece of this task — account-creation flow (Step 4's `store()`), field grouping (Step 6's `_form.blade.php` four cards), index filter+status-change (Step 9), no-password-reset-on-edit (Step 4's `update()` never touches `password`, tested in Step 1's "updates guru profile...without changing password" test), yayasan-scope guard (Step 4's `resolveLembagaId()`, copied from `KelasController`).
- **Placeholder scan:** no TBD/TODO. The one judgment call flagged explicitly in Step 12 (which seeded account actually holds `guru.*` permissions) is resolved with a concrete answer (`superadmin@sistem.test`), not left open.
- **Type/interface consistency:** `_form.blade.php`'s expected variables (`$guru`, `$jenisKelaminOptions`, `$jenisPtkOptions`, `$statusKepegawaianOptions`) match exactly what `create()`/`edit()`/`formOptions()` pass in Step 4, and what `create.blade.php`/`edit.blade.php` forward via `@include` in Steps 7-8 — verified by re-reading all four files together.

## Execution

Plan complete and saved to `docs/superpowers/plans/2026-07-30-guru-profil-akun-redesign.md`.
