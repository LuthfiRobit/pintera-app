# Validasi Scope Backend & Kejujuran Wording — Menu Mata Pelajaran Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tutup celah IDOR nyata di `store()` (baca `session('active_lembaga_id')` mentah tanpa validasi ulang kepemilikan yayasan), tambah guard `create()`, perbaiki sumber `$isPaud`, dan tambah badge scope + kolom Lembaga di tabel — menu "Mata Pelajaran" belum pernah pakai `ResolveLembagaScopeTrait` sama sekali sebelum plan ini.

**Architecture:** Perubahan murni di `MataPelajaranController` (adopsi `ResolveLembagaScopeTrait` + helper `scopeHeaderData()`, pola identik Kelas/TahunAjaran/Karyawan/Guru sesi ini) dan 3 view file (`index.blade.php`, `create.blade.php`, `edit.blade.php`, `_daftar.blade.php`). `UpdateMataPelajaranAction`/`update()` TIDAK disentuh (sudah aman — `lembaga_id` immutable).

**Tech Stack:** Laravel 12, Blade, Pest (function-style test).

## Global Constraints

- `UpdateMataPelajaranAction`/`update()` TIDAK BOLEH diubah — sudah aman.
- `store()` (A.1) WAJIB pakai `resolveActiveLembagaId($request->user())` dari `ResolveLembagaScopeTrait`, BUKAN baca `session('active_lembaga_id')` langsung — method ini melakukan validasi ulang kepemilikan yayasan secara built-in, mengembalikan `null` (bukan nilai stale) kalau lembaga session sudah tidak lagi milik yayasan aktor.
- `create()` (A.2) guard-nya SEBELUM render form, wording error: `'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah mata pelajaran.'` — format kalimat konsisten dengan `KelasController::create()`.
- `$isPaud` (A.3) WAJIB dihitung dari `resolveActiveLembagaId()` + `Lembaga::find()`, BUKAN dari `auth()->user()->lembaga` — harus benar untuk KEDUA jenis aktor (lembaga-scope dan yayasan-scope), TIDAK boleh reuse `activeLembaga` dari `scopeHeaderData()` (yang gated `$isYayasan`, jadi `null` untuk lembaga-scope walau mereka punya lembaga aktif).
- Badge di halaman **create** (B.1) SELALU brand color dengan nama lembaga — TIDAK PERNAH varian ungu "Semua Lembaga" (guard A.2 menjamin `$activeLembaga` tidak null di titik itu).
- Badge di halaman **edit** (B.1) pakai `$mataPelajaran->lembaga->nama`, BUKAN `$activeLembaga`.
- Kolom "Lembaga" (B.2) HANYA muncul saat `($isYayasan ?? false) && ! ($activeLembaga ?? null)` (mode "Semua Lembaga").
- `colSpan` baris empty-state tabel (B.2) WAJIB dihitung dinamis (7 atau 8), tidak boleh di-hardcode.

---

## Konteks File yang Sudah Ada (baca sebelum mulai)

- `app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php` — BELUM PERNAH pakai `ResolveLembagaScopeTrait` sama sekali (beda dari Kelas/TahunAjaran). BELUM `use App\Models\Lembaga;`, BELUM `use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;`.
- `tests/Feature/Admin/MataPelajaranCrudTest.php` — Pest function-style, sudah punya helper `actingAsMataPelajaranManager(Lembaga $lembaga): User` (LEMBAGA-scope actor). Sudah ADA 2 test PAUD (baris 158-180) — TAPI KEDUANYA cuma menguji aktor LEMBAGA-scope, TIDAK PERNAH menguji yayasan-scope (yang justru kasus bug A.3). Test baru plan ini pakai setup MANUAL untuk aktor yayasan-scope — ikuti pola test "menolak actor yayasan dengan active_lembaga_id stale..." di `tests/Feature/Admin/KelasCrudTest.php` (file LAIN, referensi gaya saja, BUKAN ditambah ke situ) sebagai referensi.
- `resources/views/portals/lembaga/akademik/mata-pelajaran/_form.blade.php` — TIDAK punya field dropdown lintas-model (semua field `kode`/`nama`/`no_urut`/enum) — TIDAK disentuh plan ini sama sekali.
- Route: nama route tetap `admin.mata-pelajaran.*` walau controller ada di namespace `App\Http\Controllers\Lembaga\Akademik` — ini konvensi existing, JANGAN diubah.

---

### Task 1: Controller — Adopsi `ResolveLembagaScopeTrait`, Fix `store()` (A.1), Guard `create()` (A.2)

**Files:**
- Modify: `app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php`
- Test: `tests/Feature/Admin/MataPelajaranCrudTest.php`

**Interfaces:**
- Produces: `MataPelajaranController::scopeHeaderData(Request $request): array` → `['isYayasan' => bool, 'activeLembaga' => ?Lembaga]`, dipakai Task 2 dan Task 3.
- Produces: `create()` signature berubah jadi `create(Request $request): View|RedirectResponse`.

- [ ] **Step 1: Tulis test yang gagal — celah A.1 (session stale)**

Tambahkan ke `tests/Feature/Admin/MataPelajaranCrudTest.php` (di akhir file):

```php
it('rejects storing a mata pelajaran when the yayasan-scoped actor\'s active_lembaga_id session is stale (belongs to a different yayasan)', function () {
    Permission::firstOrCreate(['name' => 'mata-pelajaran.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_mapel_stale_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['mata-pelajaran.create']);

    $yayasanSaya = Yayasan::factory()->create();
    $yayasanLain = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasanSaya->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembagaLain->id]);

    $response = $this->actingAs($manager)->post(route('admin.mata-pelajaran.store'), [
        'kode' => 'STALE-01',
        'nama' => 'Mapel Uji Stale',
        'no_urut' => 1,
        'tipe' => TipeMataPelajaran::Mapel->value,
        'status' => StatusMataPelajaran::Aktif->value,
    ]);

    $response->assertSessionHasErrors('lembaga_id');
    expect(MataPelajaran::withoutGlobalScopes()->where('kode', 'STALE-01')->exists())->toBeFalse();
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="active_lembaga_id session is stale" --compact`
Expected: FAIL — `store()` saat ini menerima `$lembagaLain->id` mentah dari session tanpa validasi, mata pelajaran BERHASIL tersimpan di lembaga milik yayasan LAIN (assertion `toBeFalse()` gagal karena row itu ADA).

- [ ] **Step 3: Tulis test yang gagal — guard A.2 create()**

Tambahkan:

```php
it('redirects back with an error when a yayasan-scoped actor opens create without an active lembaga', function () {
    Permission::firstOrCreate(['name' => 'mata-pelajaran.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['mata-pelajaran.create']);

    $yayasan = Yayasan::factory()->create();
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->get(route('admin.mata-pelajaran.create'))
        ->assertRedirect(route('admin.mata-pelajaran.index'))
        ->assertSessionHasErrors('lembaga_id');
});

it('shows the create form when a yayasan-scoped actor has switched into a lembaga', function () {
    Permission::firstOrCreate(['name' => 'mata-pelajaran.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['mata-pelajaran.create']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->get(route('admin.mata-pelajaran.create'))->assertOk();
});
```

- [ ] **Step 4: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="opens create without an active lembaga|switched into a lembaga" --compact`
Expected: FAIL — test pertama gagal (`create()` saat ini SELALU `assertOk()`, tidak pernah redirect); test kedua kemungkinan sudah PASS kebetulan (normal, fokus ke test pertama).

- [ ] **Step 5: Implementasi minimal**

Di `app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php`, tambah import (urutan alfabetis):

```php
use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;
```

(sisipkan setelah `use App\Domains\Akademik\Models\MataPelajaran;`) dan:

```php
use App\Models\Lembaga;
```

(sisipkan setelah import `App\Enums\*`, sebelum `Illuminate\Foundation\...`).

Tambah `use ResolveLembagaScopeTrait;` di dalam badan class:

```php
class MataPelajaranController extends BaseController
{
    use AuthorizesRequests;
    use ResolveLembagaScopeTrait;
```

Ganti method `create()`:

```php
public function create(): View
{
    $this->authorize('mata-pelajaran.create');

    return view('portals.lembaga.akademik.mata-pelajaran.create', [
        'tipeList' => TipeMataPelajaran::cases(),
        'kelompokList' => KelompokMataPelajaran::cases(),
        'statusList' => StatusMataPelajaran::cases(),
    ]);
}
```

menjadi:

```php
public function create(Request $request): View|RedirectResponse
{
    $this->authorize('mata-pelajaran.create');

    $lembagaId = $this->resolveActiveLembagaId($request->user());
    if ($lembagaId === null) {
        return redirect()->route('admin.mata-pelajaran.index')
            ->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah mata pelajaran.']);
    }

    return view('portals.lembaga.akademik.mata-pelajaran.create', [
        'tipeList' => TipeMataPelajaran::cases(),
        'kelompokList' => KelompokMataPelajaran::cases(),
        'statusList' => StatusMataPelajaran::cases(),
        ...$this->scopeHeaderData($request),
    ]);
}

/**
 * Info scope yayasan/lembaga yang sedang aktif, ditampilkan sebagai badge di header
 * halaman (pola sama seperti admin/siswa/index.blade.php) -- HANYA relevan untuk aktor
 * berscope yayasan (punya switcher lembaga).
 *
 * @return array{isYayasan: bool, activeLembaga: ?Lembaga}
 */
private function scopeHeaderData(Request $request): array
{
    $isYayasan = $request->user()->widestScopeLevel() === 'yayasan';
    $lembagaId = $this->resolveActiveLembagaId($request->user());

    return [
        'isYayasan' => $isYayasan,
        'activeLembaga' => ($isYayasan && $lembagaId) ? Lembaga::withoutGlobalScopes()->find($lembagaId) : null,
    ];
}
```

Ganti baris `$lembagaId` di `store()`:

```php
$lembagaId = $request->user()->widestScopeLevel() === 'yayasan' ? session('active_lembaga_id') : $request->user()->lembaga_id;
```

menjadi:

```php
$lembagaId = $this->resolveActiveLembagaId($request->user());
```

(baris `if ($lembagaId === null) { return back()->withErrors(...)->withInput(); }` yang SUDAH ADA setelahnya TIDAK berubah — tetap sama, cukup sumber `$lembagaId`-nya yang diganti).

- [ ] **Step 6: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="active_lembaga_id session is stale|opens create without an active lembaga|switched into a lembaga" --compact`
Expected: PASS semua 3 test.

- [ ] **Step 7: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/MataPelajaranCrudTest.php --compact`
Expected: PASS semua — termasuk test lama "creates a mata pelajaran with full standardized educational fields" (lembaga-scope, `resolveActiveLembagaId()` mengembalikan `$actor->lembaga_id` langsung untuknya, perilaku tidak berubah).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php tests/Feature/Admin/MataPelajaranCrudTest.php
git commit -m "fix(mata-pelajaran): tutup celah IDOR store() + guard create() tanpa lembaga aktif"
```

---

### Task 2: Controller — Fix `$isPaud` (A.3) + Wiring Badge `index()` (B.1) + Eager-Load `lembaga` (B.2)

**Files:**
- Modify: `app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php`
- Test: `tests/Feature/Admin/MataPelajaranCrudTest.php`

**Interfaces:**
- Consumes: `scopeHeaderData()` dari Task 1.

- [ ] **Step 1: Tulis test yang gagal — A.3 (isPaud untuk yayasan-scope)**

Tambahkan:

```php
it('shows the PAUD note banner for a yayasan-scoped actor switched into a PAUD lembaga', function () {
    Permission::firstOrCreate(['name' => 'mata-pelajaran.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['mata-pelajaran.view']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'TK']);
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->get(route('admin.mata-pelajaran.index'))
        ->assertSee('Catatan untuk PAUD');
});

it('does not show the PAUD note banner for a yayasan-scoped actor in "Semua Lembaga" mode', function () {
    Permission::firstOrCreate(['name' => 'mata-pelajaran.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['mata-pelajaran.view']);

    $yayasan = Yayasan::factory()->create();
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->get(route('admin.mata-pelajaran.index'))
        ->assertDontSee('Catatan untuk PAUD');
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="switched into a PAUD lembaga|Semua Lembaga.*mode" --compact`
Expected: test pertama FAIL (`$isPaud` saat ini selalu `false` untuk yayasan-scope karena `auth()->user()->lembaga` selalu `null`); test kedua kemungkinan sudah PASS kebetulan (karena `isPaud` juga `false` di kondisi ini, walau alasannya salah) — normal, fokus ke test pertama.

- [ ] **Step 3: Tulis test yang gagal — B.1 badge index()**

Tambahkan:

```php
it('passes isYayasan and activeLembaga to both the full index page and the ajax partial', function () {
    Permission::firstOrCreate(['name' => 'mata-pelajaran.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['mata-pelajaran.view']);

    $yayasan = Yayasan::factory()->create();
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    $this->actingAs($manager);

    $this->get(route('admin.mata-pelajaran.index'))->assertOk()
        ->assertViewHas('isYayasan', true)
        ->assertViewHas('activeLembaga', null);

    $this->get(route('admin.mata-pelajaran.index'), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
        ->assertViewHas('isYayasan', true)
        ->assertViewHas('activeLembaga', null);
});
```

- [ ] **Step 4: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="both the full index page and the ajax partial" --compact`
Expected: FAIL — kedua cabang `index()` belum mengirim `isYayasan`/`activeLembaga`.

- [ ] **Step 5: Implementasi minimal**

Ganti method `index()`:

```php
public function index(Request $request): View
{
    $this->authorize('mata-pelajaran.view');

    $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

    $query = MataPelajaran::orderBy('no_urut')->orderBy('nama');

    if ($search = $request->input('search')) {
        $query->where(function ($q) use ($search) {
            $q->where('nama', 'like', '%'.$search.'%')
                ->orWhere('kode', 'like', '%'.$search.'%');
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
            'perPage' => $perPage,
        ]);
    }

    return view('portals.lembaga.akademik.mata-pelajaran.index', [
        'mataPelajaranList' => $paginated,
        'tipeList' => TipeMataPelajaran::cases(),
        'kelompokList' => KelompokMataPelajaran::cases(),
        'statusList' => StatusMataPelajaran::cases(),
        'perPage' => $perPage,
        'totalMapel' => MataPelajaran::count(),
        'countKurikulum' => MataPelajaran::where('tipe', TipeMataPelajaran::Mapel->value)->count(),
        'isPaud' => in_array(
            auth()->user()->lembaga?->bentuk_pendidikan,
            [
                BentukPendidikan::Kb->value,
                BentukPendidikan::Tpa->value,
                BentukPendidikan::Sps->value,
                BentukPendidikan::Tk->value,
            ],
            true
        ),
    ]);
}
```

menjadi:

```php
public function index(Request $request): View
{
    $this->authorize('mata-pelajaran.view');

    $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

    $query = MataPelajaran::with('lembaga')->orderBy('no_urut')->orderBy('nama');

    if ($search = $request->input('search')) {
        $query->where(function ($q) use ($search) {
            $q->where('nama', 'like', '%'.$search.'%')
                ->orWhere('kode', 'like', '%'.$search.'%');
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
            'perPage' => $perPage,
            ...$this->scopeHeaderData($request),
        ]);
    }

    $lembagaAktifId = $this->resolveActiveLembagaId($request->user());

    return view('portals.lembaga.akademik.mata-pelajaran.index', [
        'mataPelajaranList' => $paginated,
        'tipeList' => TipeMataPelajaran::cases(),
        'kelompokList' => KelompokMataPelajaran::cases(),
        'statusList' => StatusMataPelajaran::cases(),
        'perPage' => $perPage,
        'totalMapel' => MataPelajaran::count(),
        'countKurikulum' => MataPelajaran::where('tipe', TipeMataPelajaran::Mapel->value)->count(),
        'isPaud' => in_array(
            Lembaga::find($lembagaAktifId)?->bentuk_pendidikan,
            [
                BentukPendidikan::Kb->value,
                BentukPendidikan::Tpa->value,
                BentukPendidikan::Sps->value,
                BentukPendidikan::Tk->value,
            ],
            true
        ),
        ...$this->scopeHeaderData($request),
    ]);
}
```

- [ ] **Step 6: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="switched into a PAUD lembaga|Semua Lembaga.*mode|both the full index page and the ajax partial" --compact`
Expected: PASS semua 3 test.

- [ ] **Step 7: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/MataPelajaranCrudTest.php --compact`
Expected: PASS semua — termasuk 2 test PAUD lama (lembaga-scope) di baris 158-180 yang TETAP harus hijau (`$lembagaAktifId` untuk lembaga-scope aktor sekarang dihitung via `resolveActiveLembagaId()` yang mengembalikan `$actor->lembaga_id` langsung — hasil akhirnya SAMA seperti sebelumnya untuk kasus ini).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php tests/Feature/Admin/MataPelajaranCrudTest.php
git commit -m "fix(mata-pelajaran): isPaud dari lembaga aktif + wiring scopeHeaderData() di index()"
```

---

### Task 3: Controller — Wiring Badge `edit()` (B.1)

**Files:**
- Modify: `app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php`
- Test: `tests/Feature/Admin/MataPelajaranCrudTest.php`

**Interfaces:**
- Consumes: `scopeHeaderData()` dari Task 1.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('passes isYayasan and activeLembaga to the edit view', function () {
    Permission::firstOrCreate(['name' => 'mata-pelajaran.edit', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['mata-pelajaran.edit']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $mapel = MataPelajaran::create([
        'lembaga_id' => $lembaga->id,
        'kode' => 'EDT-01',
        'nama' => 'Mapel Edit Uji',
        'no_urut' => 1,
        'tipe' => TipeMataPelajaran::Mapel->value,
        'kelompok' => KelompokMataPelajaran::Umum->value,
        'status' => StatusMataPelajaran::Aktif->value,
    ]);

    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    // TIDAK switch lembaga -- mode "Semua Lembaga".

    $this->actingAs($manager)->get(route('admin.mata-pelajaran.edit', $mapel))->assertOk()
        ->assertViewHas('isYayasan', true);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="passes isYayasan and activeLembaga to the edit view" --compact`
Expected: FAIL — `edit()` belum mengirim `isYayasan`.

- [ ] **Step 3: Implementasi minimal**

Ganti method `edit()`:

```php
public function edit(MataPelajaran $mataPelajaran): View
{
    $this->authorize('mata-pelajaran.edit');

    return view('portals.lembaga.akademik.mata-pelajaran.edit', [
        'mataPelajaran' => $mataPelajaran,
        'tipeList' => TipeMataPelajaran::cases(),
        'kelompokList' => KelompokMataPelajaran::cases(),
        'statusList' => StatusMataPelajaran::cases(),
    ]);
}
```

menjadi:

```php
public function edit(Request $request, MataPelajaran $mataPelajaran): View
{
    $this->authorize('mata-pelajaran.edit');

    return view('portals.lembaga.akademik.mata-pelajaran.edit', [
        'mataPelajaran' => $mataPelajaran,
        'tipeList' => TipeMataPelajaran::cases(),
        'kelompokList' => KelompokMataPelajaran::cases(),
        'statusList' => StatusMataPelajaran::cases(),
        ...$this->scopeHeaderData($request),
    ]);
}
```

(`Request $request` ditambahkan SEBELUM route-model-binding `MataPelajaran $mataPelajaran` — konsisten pola `KelasController::edit()`.)

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="passes isYayasan and activeLembaga to the edit view" --compact`
Expected: PASS.

- [ ] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/MataPelajaranCrudTest.php --compact`
Expected: PASS semua.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php tests/Feature/Admin/MataPelajaranCrudTest.php
git commit -m "feat(mata-pelajaran): wiring scopeHeaderData() ke edit()"
```

---

### Task 4: View — Badge Scope di Index/Create/Edit (B.1)

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/mata-pelajaran/index.blade.php`
- Modify: `resources/views/portals/lembaga/akademik/mata-pelajaran/create.blade.php`
- Modify: `resources/views/portals/lembaga/akademik/mata-pelajaran/edit.blade.php`
- Test: `tests/Feature/Admin/MataPelajaranCrudTest.php`

**Interfaces:**
- Consumes: `$isYayasan`, `$activeLembaga` dari Task 1/2/3.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('shows the "Semua Lembaga" badge on the mata pelajaran index for a yayasan-scoped actor in aggregate mode', function () {
    Permission::firstOrCreate(['name' => 'mata-pelajaran.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['mata-pelajaran.view']);

    $yayasan = Yayasan::factory()->create();
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->get(route('admin.mata-pelajaran.index'))->assertSee('Semua Lembaga');
});

it('shows the switched lembaga name badge on the create page (never the "Semua Lembaga" variant)', function () {
    Permission::firstOrCreate(['name' => 'mata-pelajaran.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['mata-pelajaran.create']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Bina Cendekia']);
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->get(route('admin.mata-pelajaran.create'))
        ->assertSee('SD Bina Cendekia')
        ->assertDontSee('Semua Lembaga');
});

it('shows the owning lembaga name badge on the edit page even in "Semua Lembaga" mode', function () {
    Permission::firstOrCreate(['name' => 'mata-pelajaran.edit', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['mata-pelajaran.edit']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMP Bina Cendekia']);
    $mapel = MataPelajaran::create([
        'lembaga_id' => $lembaga->id,
        'kode' => 'BDG-01',
        'nama' => 'Mapel Badge Uji',
        'no_urut' => 1,
        'tipe' => TipeMataPelajaran::Mapel->value,
        'kelompok' => KelompokMataPelajaran::Umum->value,
        'status' => StatusMataPelajaran::Aktif->value,
    ]);

    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    // TIDAK switch lembaga -- mode "Semua Lembaga".

    $this->actingAs($manager)->get(route('admin.mata-pelajaran.edit', $mapel))->assertSee('SMP Bina Cendekia');
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="Semua Lembaga.*badge|switched lembaga name badge|owning lembaga name badge" --compact`
Expected: FAIL — 3 test gagal, badge belum ada di ketiga halaman.

- [ ] **Step 3: Implementasi minimal — index**

Di `resources/views/portals/lembaga/akademik/mata-pelajaran/index.blade.php`, ganti (baris ±12-19):

```blade
        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Mata Pelajaran</h1>
                <p class="mt-0.5 text-xs text-gray-500">Kelola daftar mata pelajaran untuk kurikulum lembaga.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Mata Pelajaran</b>
            </p>
        </div>
```

menjadi:

```blade
        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="font-display text-lg font-bold text-gray-900">Mata Pelajaran</h1>
                    @if ($isYayasan ?? (auth()->user()?->widestScopeLevel() === 'yayasan'))
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
                            <x-icon name="apartment" class="h-3.5 w-3.5" />
                            {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
                        </span>
                    @endif
                </div>
                <p class="mt-0.5 text-xs text-gray-500">Kelola daftar mata pelajaran untuk kurikulum lembaga.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Mata Pelajaran</b>
            </p>
        </div>
```

- [ ] **Step 4: Implementasi minimal — create**

Di `resources/views/portals/lembaga/akademik/mata-pelajaran/create.blade.php`, ganti (baris ±12-13):

```blade
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Tambah Mata Pelajaran</h1>
```

menjadi:

```blade
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="font-display text-lg font-bold text-gray-900">Tambah Mata Pelajaran</h1>
                @if ($isYayasan ?? false)
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
                        <x-icon name="apartment" class="h-3.5 w-3.5" />
                        {{ $activeLembaga->nama }}
                    </span>
                @endif
            </div>
```

- [ ] **Step 5: Implementasi minimal — edit**

Di `resources/views/portals/lembaga/akademik/mata-pelajaran/edit.blade.php`, ganti (baris ±12-13):

```blade
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Edit Mata Pelajaran: {{ $mataPelajaran->nama }}</h1>
```

menjadi:

```blade
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="font-display text-lg font-bold text-gray-900">Edit Mata Pelajaran: {{ $mataPelajaran->nama }}</h1>
                @if ($isYayasan ?? false)
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
                        <x-icon name="apartment" class="h-3.5 w-3.5" />
                        {{ $mataPelajaran->lembaga->nama }}
                    </span>
                @endif
            </div>
```

- [ ] **Step 6: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="Semua Lembaga.*badge|switched lembaga name badge|owning lembaga name badge" --compact`
Expected: PASS semua 3 test.

- [ ] **Step 7: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/MataPelajaranCrudTest.php --compact`
Expected: PASS semua.

- [ ] **Step 8: Commit**

```bash
git add resources/views/portals/lembaga/akademik/mata-pelajaran/index.blade.php resources/views/portals/lembaga/akademik/mata-pelajaran/create.blade.php resources/views/portals/lembaga/akademik/mata-pelajaran/edit.blade.php tests/Feature/Admin/MataPelajaranCrudTest.php
git commit -m "feat(mata-pelajaran): badge scope yayasan/lembaga di index, create, edit"
```

---

### Task 5: View — Kolom "Lembaga" di Tabel Daftar (B.2)

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/mata-pelajaran/_daftar.blade.php`
- Test: `tests/Feature/Admin/MataPelajaranCrudTest.php`

**Interfaces:**
- Consumes: `$isYayasan`, `$activeLembaga` (Task 2), `$mapel->lembaga` (eager-loaded Task 2).

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('shows a Lembaga column with each lembaga name in the mata pelajaran table during aggregate mode (same kode in 2 lembaga)', function () {
    Permission::firstOrCreate(['name' => 'mata-pelajaran.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['mata-pelajaran.view']);

    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Melati Satu']);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Melati Dua']);
    MataPelajaran::create([
        'lembaga_id' => $lembagaA->id, 'kode' => 'MTK-01', 'nama' => 'Matematika', 'no_urut' => 1,
        'tipe' => TipeMataPelajaran::Mapel->value, 'kelompok' => KelompokMataPelajaran::Umum->value, 'status' => StatusMataPelajaran::Aktif->value,
    ]);
    MataPelajaran::withoutGlobalScopes()->create([
        'lembaga_id' => $lembagaB->id, 'kode' => 'MTK-01', 'nama' => 'Matematika', 'no_urut' => 1,
        'tipe' => TipeMataPelajaran::Mapel->value, 'kelompok' => KelompokMataPelajaran::Umum->value, 'status' => StatusMataPelajaran::Aktif->value,
    ]);

    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $response = $this->actingAs($manager)->get(route('admin.mata-pelajaran.index'))->assertOk();
    $response->assertSee('SD Melati Satu');
    $response->assertSee('SD Melati Dua');
});

it('hides the Lembaga column once a lembaga is switched into', function () {
    Permission::firstOrCreate(['name' => 'mata-pelajaran.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['mata-pelajaran.view']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    MataPelajaran::create([
        'lembaga_id' => $lembaga->id, 'kode' => 'MTK-01', 'nama' => 'Matematika', 'no_urut' => 1,
        'tipe' => TipeMataPelajaran::Mapel->value, 'kelompok' => KelompokMataPelajaran::Umum->value, 'status' => StatusMataPelajaran::Aktif->value,
    ]);

    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->get(route('admin.mata-pelajaran.index'))->assertDontSee('>Lembaga<', false);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="Lembaga column" --compact`
Expected: FAIL — test pertama gagal (kolom belum ada), test kedua kemungkinan sudah PASS kebetulan (kolom memang belum ada sama sekali) — normal, fokus ke test pertama.

- [ ] **Step 3: Implementasi minimal**

Di `resources/views/portals/lembaga/akademik/mata-pelajaran/_daftar.blade.php`, ganti header tabel (baris 22-31):

```blade
<thead>
    <tr class="border-b border-gray-200 bg-gray-50/75 font-display text-xs font-bold uppercase tracking-wider text-gray-500">
        <th class="sticky left-0 z-10 bg-gray-50/75 px-5 py-3 w-32">Aksi</th>
        <th class="px-4 py-3 text-center w-20">No. Rapor</th>
        <th class="px-4 py-3 w-32">Kode</th>
        <th class="px-4 py-3">Nama Mata Pelajaran</th>
        <th class="px-4 py-3">Tipe</th>
        <th class="px-4 py-3">Kelompok</th>
        <th class="px-5 py-3 text-center w-28">Status</th>
    </tr>
</thead>
```

menjadi:

```blade
<thead>
    <tr class="border-b border-gray-200 bg-gray-50/75 font-display text-xs font-bold uppercase tracking-wider text-gray-500">
        <th class="sticky left-0 z-10 bg-gray-50/75 px-5 py-3 w-32">Aksi</th>
        <th class="px-4 py-3 text-center w-20">No. Rapor</th>
        <th class="px-4 py-3 w-32">Kode</th>
        <th class="px-4 py-3">Nama Mata Pelajaran</th>
        @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
            <th class="px-4 py-3">Lembaga</th>
        @endif
        <th class="px-4 py-3">Tipe</th>
        <th class="px-4 py-3">Kelompok</th>
        <th class="px-5 py-3 text-center w-28">Status</th>
    </tr>
</thead>
```

Ganti body tabel (baris 33-75):

```blade
<tbody class="divide-y divide-gray-100 font-normal">
    @forelse ($mataPelajaranList as $mapel)
        <tr class="transition-colors hover:bg-gray-50/60">
            <td class="sticky left-0 z-10 bg-white px-5 py-3">
                <x-table-actions>
                    @can('mata-pelajaran.edit')
                    <a href="{{ route('admin.mata-pelajaran.edit', $mapel) }}" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                        <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                        Edit Mata Pelajaran
                    </a>
                    @endcan
                </x-table-actions>
            </td>
            <td class="px-4 py-3.5 text-center font-mono text-xs font-bold text-gray-600">
                {{ $mapel->no_urut }}
            </td>
            <td class="px-4 py-3.5 font-mono text-xs font-semibold text-brand-600">
                {{ $mapel->kode }}
            </td>
            <td class="px-4 py-3.5 font-medium text-gray-900">
                {{ $mapel->nama }}
            </td>
            <td class="px-4 py-3.5 text-xs text-gray-600">
                {{ $mapel->tipe->label() }}
            </td>
            <td class="px-4 py-3.5 text-xs text-gray-600">
                {{ $mapel->kelompok?->label() ?? '—' }}
            </td>
            <td class="px-5 py-3.5 text-center">
                @if ($mapel->status === \App\Enums\StatusMataPelajaran::Aktif)
                    <span class="inline-flex items-center rounded-full bg-success-50 px-2.5 py-0.5 text-xs font-medium text-success-700">Aktif</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">Nonaktif</span>
                @endif
            </td>
        </tr>
    @empty
        <tr>
            <td colSpan="7" class="px-5 py-12 text-center text-gray-500">
                <p class="text-sm">Belum ada mata pelajaran yang didaftarkan.</p>
            </td>
        </tr>
    @endforelse
</tbody>
```

menjadi:

```blade
<tbody class="divide-y divide-gray-100 font-normal">
    @forelse ($mataPelajaranList as $mapel)
        <tr class="transition-colors hover:bg-gray-50/60">
            <td class="sticky left-0 z-10 bg-white px-5 py-3">
                <x-table-actions>
                    @can('mata-pelajaran.edit')
                    <a href="{{ route('admin.mata-pelajaran.edit', $mapel) }}" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                        <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                        Edit Mata Pelajaran
                    </a>
                    @endcan
                </x-table-actions>
            </td>
            <td class="px-4 py-3.5 text-center font-mono text-xs font-bold text-gray-600">
                {{ $mapel->no_urut }}
            </td>
            <td class="px-4 py-3.5 font-mono text-xs font-semibold text-brand-600">
                {{ $mapel->kode }}
            </td>
            <td class="px-4 py-3.5 font-medium text-gray-900">
                {{ $mapel->nama }}
            </td>
            @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
                <td class="px-4 py-3.5 text-xs text-gray-500">{{ $mapel->lembaga->nama ?? '-' }}</td>
            @endif
            <td class="px-4 py-3.5 text-xs text-gray-600">
                {{ $mapel->tipe->label() }}
            </td>
            <td class="px-4 py-3.5 text-xs text-gray-600">
                {{ $mapel->kelompok?->label() ?? '—' }}
            </td>
            <td class="px-5 py-3.5 text-center">
                @if ($mapel->status === \App\Enums\StatusMataPelajaran::Aktif)
                    <span class="inline-flex items-center rounded-full bg-success-50 px-2.5 py-0.5 text-xs font-medium text-success-700">Aktif</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">Nonaktif</span>
                @endif
            </td>
        </tr>
    @empty
        <tr>
            <td colSpan="{{ ($isYayasan ?? false) && ! ($activeLembaga ?? null) ? 8 : 7 }}" class="px-5 py-12 text-center text-gray-500">
                <p class="text-sm">Belum ada mata pelajaran yang didaftarkan.</p>
            </td>
        </tr>
    @endforelse
</tbody>
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="Lembaga column" --compact`
Expected: PASS semua 2 test.

- [ ] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/MataPelajaranCrudTest.php --compact`
Expected: PASS semua — termasuk test lama "only lists mata pelajaran belonging to the acting manager's own lembaga in index view" yang assert `assertDontSee('Mapel Lembaga B')` (row lembaga lain TETAP tidak boleh muncul untuk aktor lembaga-scope — TIDAK terpengaruh perubahan kolom Lembaga karena kolom itu HANYA muncul di mode "Semua Lembaga" untuk yayasan-scope).

- [ ] **Step 6: Commit**

```bash
git add resources/views/portals/lembaga/akademik/mata-pelajaran/_daftar.blade.php tests/Feature/Admin/MataPelajaranCrudTest.php
git commit -m "feat(mata-pelajaran): kolom Lembaga di tabel saat mode Semua Lembaga"
```

---

### Task 6: Penutup — Regresi Penuh & Pint

**Files:**
- Tidak ada file baru — task verifikasi murni.

- [ ] **Step 1: Jalankan seluruh test yang menyentuh Mata Pelajaran**

Run: `php artisan test --compact --filter="MataPelajaranCrudTest|MataPelajaranSeederTest|MataPelajaranTest|RemoveAspekPerkembanganFromMataPelajaranTipeTest"`
Expected: PASS semua, 0 gagal.

- [ ] **Step 2: Jalankan Pint pada file yang diubah**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}`.

- [ ] **Step 3: Verifikasi manual cepat via browser (opsional tapi disarankan)**

Login sebagai yayasan-scope dalam mode "Semua Lembaga": buka `/admin/mata-pelajaran`, cek badge "Semua Lembaga", cek kolom "Lembaga" di tabel kalau ada ≥2 mapel kode sama. Klik "Tambah Mata Pelajaran" TANPA switch lembaga dulu → redirect balik ke index dengan pesan error. Switch ke lembaga PAUD (bentuk_pendidikan KB/TPA/SPS/TK) → cek banner "Catatan untuk PAUD" MUNCUL (sebelumnya tidak pernah muncul untuk yayasan-scope). Switch ke 1 lembaga non-PAUD, klik "Tambah Mata Pelajaran" → form terbuka normal, badge selalu warna brand dengan nama lembaga.

- [ ] **Step 4: Laporkan hasil**

TIDAK perlu menulis file handoff log baru di task ini — sama seperti plan Kelas/Tahun Ajaran, kalau user menghendaki log terpisah, itu permintaan tambahan setelah plan ini selesai.

---

## Self-Review

**1. Spec coverage** — semua 5 item spec `.agents/specs/2026-09-08-mata-pelajaran-scope-wording-audit.md` tercakup: A.1+A.2 di Task 1, A.3+B.1(index) di Task 2, B.1(edit) di Task 3, B.1(markup 3 halaman) di Task 4, B.2 di Task 5. "Di Luar Scope" spec (filter dropdown tidak butuh label, empty-state wording generik, `update()`/`UpdateMataPelajaranAction`) sengaja tidak ada task-nya.

**2. Placeholder scan** — tidak ada "TBD"/dst. Semua step berisi kode lengkap.

**3. Type consistency** — `scopeHeaderData(Request $request): array` didefinisikan Task 1, dipanggil identik di Task 2 (`index()`, sudah punya `Request $request`) dan Task 3 (`edit()`, `Request $request` baru ditambahkan ke signature). Variabel `$isYayasan`/`$activeLembaga` dipakai konsisten namanya di Task 4/5's Blade.

**Catatan tambahan hasil self-review**:
- Task 1 Step 4 dan Task 2 Step 2/Task 5 Step 2 masing-masing punya 1 test yang berpotensi PASS lebih awal dari yang diharapkan (dijelaskan eksplisit di step itu sendiri) — bukan indikasi kesalahan, murni karena sebagian kondisi kebetulan sudah "benar" secara hasil akhir (walau lewat jalur/alasan yang salah, seperti kasus A.3 di mana `isPaud` tetap `false` di mode "Semua Lembaga" baik sebelum maupun sesudah fix — cuma ALASANNYA yang berubah dari "selalu false karena bug" jadi "false karena memang belum pilih lembaga"). Pelaksana TIDAK PERLU khawatir, tetap lanjut ke step implementasi berikutnya.
- Task 3 mengubah signature `edit()` jadi `edit(Request $request, MataPelajaran $mataPelajaran)` — WAJIB urutan `Request` SEBELUM model binding, konsisten pola `KelasController::edit()`/`KaryawanController::edit()`/`GuruController::edit()` sesi ini.
- Berbeda dari Kelas, Mata Pelajaran TIDAK punya task "scoping dropdown create()/edit()" (A.2/A.3 versi Kelas) karena `_form.blade.php`-nya tidak punya field dropdown lintas-model sama sekali — dikonfirmasi di spec bagian "Keputusan yang Diambil" poin 5.
