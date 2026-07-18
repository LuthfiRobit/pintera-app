# Redesign & Keamanan Halaman Jalur PPDB Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close a silent-data-loss bug where deactivating a jalur used by a gelombang could wipe that gelombang's restriction, give admins visibility into which gelombang use a jalur, and bring the Jalur PPDB pages (index/create/edit + its 3 partials) up to the same TailAdmin visual pattern already used by Lembaga and Gelombang PPDB.

**Architecture:** `JalurPpdbController::update()` blocks the `status_aktif: true → false` transition whenever `$jalurPpdb->gelombang()->exists()` (the relation already exists from the gelombang-jalur restriction feature) — this makes the silent-wipe scenario structurally impossible rather than working around it. The index and edit views surface that same relation as visibility (badge counts, inline gelombang names). All Blade changes are a token/component re-skin onto the existing `rounded-2xl border-gray-200 bg-white shadow-card` / `x-icon` / `x-badge` / `x-table-actions` pattern — no controller changes beyond Task 1.

## Global Constraints

- Only the `status_aktif: true → false` transition is guarded. Reactivating (`false → true`) is never blocked, regardless of any pivot rows.
- `nama`/`deskripsi` changes in the same submit that attempts to deactivate still validate independently — a rejected deactivation must not also lose unrelated field edits the admin typed (achieved via `->withInput()`).
- No changes to `FormulirField`, `DokumenSyaratPpdb`, or `SeleksiPpdb` controllers, routes, or validation — those 3 partials get a visual re-skin only, their existing test files (`FormulirFieldTest.php`, `DokumenSyaratTest.php`, `SeleksiTest.php`) must stay green untouched.
- No changes to `PortalController` or the public SPMB flow.
- No `destroy` route exists for `JalurPpdb` and none is added here.
- Every task ends with a separate commit.

---

## Task 1: Backend — block deactivation while in use, tahun ajaran filter, gelombang visibility data

**Files:**
- Modify: `app/Http/Controllers/Admin/JalurPpdbController.php`
- Test: `tests/Feature/Admin/JalurPpdbTest.php`

**Interfaces:**
- Consumes: `JalurPpdb::gelombang(): BelongsToMany` (already exists, created for the gelombang-jalur restriction feature).
- Produces: `index()` now accepts `tahun_ajaran` and `cari` query params (same names/semantics as `GelombangPpdbController::index()`) and passes `tahunAjaranOptions`/`tahunAjaranTerpilih` to the view; `JalurPpdb` rows in `jalurList` carry a `gelombang_count` attribute via `withCount('gelombang')`. `edit()` passes a new `gelombangPemakai` (collection of gelombang names) to the view. Tasks 2 and 4 consume these.

- [ ] **Step 1: Write the failing tests**

Add this import near the top of `tests/Feature/Admin/JalurPpdbTest.php` (alongside the existing `use` statements):

```php
use App\Models\GelombangPpdb;
```

Then append these tests to the end of the file:

```php
it('rejects deactivating a jalur that is still used by a gelombang', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();

    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler', 'status_aktif' => true]);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 10,
    ]);
    $gelombang->jalur()->attach($jalur->id);

    // This is exactly the scenario that previously wiped the gelombang's
    // pivot silently: the only jalur it uses gets deactivated, then any
    // save on the gelombang cleared the restriction with no warning.
    $this->actingAs($user)->put(route('admin.jalur-ppdb.update', $jalur), [
        'nama' => 'Reguler',
        'deskripsi' => null,
        'status_aktif' => 0,
    ])->assertSessionHasErrors('status_aktif');

    expect($jalur->fresh()->status_aktif)->toBeTrue();
    expect($gelombang->jalur()->count())->toBe(1);
});

it('names the affected gelombang in the deactivation error message', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();

    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler', 'status_aktif' => true]);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 10,
    ]);
    $gelombang->jalur()->attach($jalur->id);

    $this->actingAs($user)->put(route('admin.jalur-ppdb.update', $jalur), [
        'nama' => 'Reguler',
        'deskripsi' => null,
        'status_aktif' => 0,
    ]);

    expect(session('errors')->get('status_aktif')[0])->toContain('Gelombang 1');
});

it('allows deactivating a jalur that is not used by any gelombang', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();

    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler', 'status_aktif' => true]);

    $this->actingAs($user)->put(route('admin.jalur-ppdb.update', $jalur), [
        'nama' => 'Reguler',
        'deskripsi' => null,
        'status_aktif' => 0,
    ])->assertRedirect(route('admin.jalur-ppdb.edit', $jalur));

    expect($jalur->fresh()->status_aktif)->toBeFalse();
});

it('allows reactivating a jalur without any restriction check', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();

    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler', 'status_aktif' => false]);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 10,
    ]);
    // A pivot row referencing an already-inactive jalur can only exist from
    // data created before this safeguard — reactivating must never be
    // blocked regardless, only the true -> false transition is guarded.
    $gelombang->jalur()->attach($jalur->id);

    $this->actingAs($user)->put(route('admin.jalur-ppdb.update', $jalur), [
        'nama' => 'Reguler',
        'deskripsi' => null,
        'status_aktif' => 1,
    ])->assertRedirect(route('admin.jalur-ppdb.edit', $jalur));

    expect($jalur->fresh()->status_aktif)->toBeTrue();
});

it('lets the tahun_ajaran filter browse a past year instead of only the active one', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();
    JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Jalur Baru']);

    $tahunLama = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2025/2026',
        'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'status_aktif' => false,
    ]);
    JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id, 'nama' => 'Jalur Lama']);

    $this->actingAs($user)->get(route('admin.jalur-ppdb.index', ['tahun_ajaran' => $tahunLama->id]))
        ->assertOk()
        ->assertSee('Jalur Lama')
        ->assertDontSee('Jalur Baru');
});

it('filters the index by nama when cari is given', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();
    JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi']);

    $this->actingAs($user)->get(route('admin.jalur-ppdb.index', ['cari' => 'Reg']))
        ->assertOk()
        ->assertSee('Reguler')
        ->assertDontSee('Prestasi');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=JalurPpdbTest`
Expected: the 6 new tests FAIL — the controller doesn't block deactivation, doesn't accept `tahun_ajaran`/`cari` query params, and doesn't eager-load `gelombang` counts yet. The pre-existing tests in this file still pass.

- [ ] **Step 3: Update `index()`**

In `app/Http/Controllers/Admin/JalurPpdbController.php`, replace the `index()` method:

```php
    public function index(Request $request): View
    {
        $this->authorize('jalur-ppdb.view');

        $tahunAjaranAktif = TahunAjaran::where('status_aktif', true)->first();
        $tahunAjaranOptions = TahunAjaran::orderByDesc('tanggal_mulai')->get();

        // The "tahun_ajaran" filter lets an admin browse a past year's jalur
        // instead of only ever seeing the currently-active one. No filter
        // given falls back to the active year, matching the page's original
        // behaviour.
        $tahunAjaranTerpilih = $request->filled('tahun_ajaran')
            ? $tahunAjaranOptions->firstWhere('id', (int) $request->query('tahun_ajaran'))
            : $tahunAjaranAktif;

        $tahunAjaranSebelumnya = $tahunAjaranAktif
            ? TahunAjaran::where('id', '!=', $tahunAjaranAktif->id)
                ->where('tanggal_mulai', '<', $tahunAjaranAktif->tanggal_mulai)
                ->orderByDesc('tanggal_mulai')
                ->first()
            : null;

        // Only offer the "copy from previous year" callout if that candidate
        // year actually has Gelombang or Jalur data to copy — otherwise the
        // button would silently succeed while copying zero rows.
        if ($tahunAjaranSebelumnya
            && ! GelombangPpdb::where('tahun_ajaran_id', $tahunAjaranSebelumnya->id)->exists()
            && ! JalurPpdb::where('tahun_ajaran_id', $tahunAjaranSebelumnya->id)->exists()) {
            $tahunAjaranSebelumnya = null;
        }

        $query = $tahunAjaranTerpilih
            ? JalurPpdb::withCount('gelombang')->where('tahun_ajaran_id', $tahunAjaranTerpilih->id)
            : JalurPpdb::whereRaw('1 = 0');

        $query->when($request->filled('cari'), fn ($q) => $q->where('nama', 'like', '%'.$request->query('cari').'%'));

        return view('admin.jalur-ppdb.index', [
            'tahunAjaranAktif' => $tahunAjaranAktif,
            'tahunAjaranOptions' => $tahunAjaranOptions,
            'tahunAjaranTerpilih' => $tahunAjaranTerpilih,
            'jalurList' => $query->orderBy('nama')->get(),
            'tahunAjaranSebelumnya' => $tahunAjaranSebelumnya,
        ]);
    }
```

- [ ] **Step 4: Update `edit()`**

Replace the `edit()` method:

```php
    public function edit(JalurPpdb $jalurPpdb): View
    {
        $this->authorize('jalur-ppdb.edit');

        $jalurPpdb->load(['formulirField', 'dokumenSyarat', 'seleksi.gelombangPpdb', 'seleksi.jenisTesMaster']);

        return view('admin.jalur-ppdb.edit', [
            'jalur' => $jalurPpdb,
            'gelombangList' => GelombangPpdb::where('tahun_ajaran_id', $jalurPpdb->tahun_ajaran_id)->orderBy('nama')->get(),
            'jenisTesList' => JenisTesMaster::orderBy('nama')->get(),
            'gelombangPemakai' => $jalurPpdb->gelombang()->orderBy('nama')->pluck('nama'),
        ]);
    }
```

- [ ] **Step 5: Block deactivation in `update()`**

Replace the `update()` method:

```php
    public function update(Request $request, JalurPpdb $jalurPpdb): RedirectResponse
    {
        $this->authorize('jalur-ppdb.edit');

        $data = $request->validate([
            'nama' => [
                'required',
                'string',
                'max:255',
                Rule::unique('jalur_ppdb', 'nama')
                    ->where(fn ($query) => $query->where('tahun_ajaran_id', $jalurPpdb->tahun_ajaran_id))
                    ->ignore($jalurPpdb->id),
            ],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'status_aktif' => ['required', 'boolean'],
        ]);

        if ($jalurPpdb->status_aktif && ! $data['status_aktif'] && $jalurPpdb->gelombang()->exists()) {
            $namaGelombang = $jalurPpdb->gelombang()->orderBy('nama')->pluck('gelombang_ppdb.nama')->implode(', ');

            return back()->withErrors([
                'status_aktif' => "Tidak bisa menonaktifkan jalur ini karena masih dipakai di gelombang: {$namaGelombang}. Hapus centang jalur ini dari gelombang tersebut terlebih dahulu.",
            ])->withInput();
        }

        $jalurPpdb->update($data);

        return redirect()->route('admin.jalur-ppdb.edit', $jalurPpdb)->with('status', 'Jalur berhasil diperbarui.');
    }
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter=JalurPpdbTest`
Expected: `13 passed` (7 pre-existing + 6 new).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/JalurPpdbController.php tests/Feature/Admin/JalurPpdbTest.php
git commit -m "feat: block deactivating a jalur still used by a gelombang, add tahun ajaran filter"
```

---

## Task 2: Redesign — Jalur PPDB index

**Files:**
- Modify: `resources/views/admin/jalur-ppdb/index.blade.php`
- Test: `tests/Feature/Admin/JalurPpdbTest.php` (append)

**Interfaces:**
- Consumes: `tahunAjaranOptions`/`tahunAjaranTerpilih`/`jalurList` (rows carrying `gelombang_count`) from Task 1's `index()`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Admin/JalurPpdbTest.php`:

```php
it('shows a "Dipakai di N Gelombang" badge on the index for a jalur in use', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();

    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 10,
    ]);
    $gelombang->jalur()->attach($jalur->id);

    $this->actingAs($user)->get(route('admin.jalur-ppdb.index'))
        ->assertOk()
        ->assertSee('Dipakai di 1 Gelombang');
});

it('shows a "Tidak Dipakai" badge on the index for a jalur not used by any gelombang', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();

    JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);

    $this->actingAs($user)->get(route('admin.jalur-ppdb.index'))
        ->assertOk()
        ->assertSee('Tidak Dipakai');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=JalurPpdbTest`
Expected: the 2 new tests FAIL — the index table has no "Dipakai di Gelombang" column yet.

- [ ] **Step 3: Replace `index.blade.php`**

Replace the full content of `resources/views/admin/jalur-ppdb/index.blade.php`:

```blade
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">
                Jalur PPDB
                @if ($tahunAjaranTerpilih)
                    <span class="text-sm font-normal text-gray-500">&mdash; {{ $tahunAjaranTerpilih->nama }}</span>
                @endif
            </h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Jalur PPDB</b>
            </p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="filter" class="h-[15px] w-[15px] text-gray-400" />
                    Filter Data
                </p>
                @if ($tahunAjaranAktif)
                    <x-link-button href="{{ route('admin.jalur-ppdb.create') }}">
                        <span class="text-base leading-none">+</span> Tambah Jalur
                    </x-link-button>
                @endif
            </div>

            <form method="GET" action="{{ route('admin.jalur-ppdb.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="cari" class="mb-1.5 block text-xs font-semibold text-gray-500">Cari</label>
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                        <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                        <input
                            type="text" name="cari" id="cari" value="{{ request('cari') }}"
                            placeholder="Nama jalur"
                            @input.debounce.500ms="$el.form.submit()"
                            class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0"
                        >
                    </div>
                </div>

                <div>
                    <label for="tahun_ajaran" class="mb-1.5 block text-xs font-semibold text-gray-500">Tahun Ajaran</label>
                    <select name="tahun_ajaran" id="tahun_ajaran" @change="$el.form.submit()" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($tahunAjaranOptions as $option)
                            <option value="{{ $option->id }}" @selected($tahunAjaranTerpilih?->id === $option->id)>
                                {{ $option->nama }} @if ($option->status_aktif) (Aktif) @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end">
                    @if (request()->filled('cari') || (request()->filled('tahun_ajaran') && (int) request('tahun_ajaran') !== $tahunAjaranAktif?->id))
                        <a href="{{ route('admin.jalur-ppdb.index') }}" class="flex h-[42px] w-full items-center justify-center rounded-lg border border-gray-200 px-3 text-sm text-gray-500 transition hover:bg-gray-50">Reset Filter</a>
                    @endif
                </div>
            </form>
        </div>

        @if (! $tahunAjaranTerpilih)
            <div class="rounded-2xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500 shadow-card">
                Aktifkan tahun ajaran terlebih dahulu di menu
                <a href="{{ route('admin.tahun-ajaran.index') }}" class="font-semibold text-brand-600 hover:underline">Tahun Ajaran</a>
                sebelum mengatur jalur PPDB.
            </div>
        @elseif ($jalurList->isEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
                <p class="text-sm text-gray-500">Belum ada konfigurasi SPMB untuk {{ $tahunAjaranTerpilih->nama }}.</p>
                @if ($tahunAjaranSebelumnya && $tahunAjaranTerpilih->id === $tahunAjaranAktif?->id)
                    <form method="POST" action="{{ route('admin.spmb-konfigurasi.duplikasi') }}" class="mt-3">
                        @csrf
                        <input type="hidden" name="tahun_ajaran_sumber_id" value="{{ $tahunAjaranSebelumnya->id }}">
                        <button type="submit" class="rounded-lg bg-brand-50 px-4 py-2 text-sm font-semibold text-brand-600 transition hover:bg-brand-100">
                            Salin dari {{ $tahunAjaranSebelumnya->nama }}
                        </button>
                    </form>
                @endif
            </div>
        @else
            <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
                <div class="border-b border-gray-200 px-5 py-4">
                    <p class="font-display text-sm font-bold text-gray-900">Daftar Jalur</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
                                <th class="px-5 py-3">Nama</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3">Dipakai di Gelombang</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($jalurList as $jalur)
                                <tr class="transition hover:bg-gray-50">
                                    <td class="sticky left-0 z-10 bg-white px-5 py-3">
                                        <x-table-actions>
                                            <x-dropdown-link :href="route('admin.jalur-ppdb.edit', $jalur)">
                                                <span class="inline-flex items-center gap-2.5">
                                                    <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                                    Kelola Jalur
                                                </span>
                                            </x-dropdown-link>
                                        </x-table-actions>
                                    </td>
                                    <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $jalur->nama }}</td>
                                    <td class="px-5 py-3.5">
                                        @if ($jalur->status_aktif)
                                            <x-badge tone="brass">Aktif</x-badge>
                                        @else
                                            <x-badge tone="slate">Nonaktif</x-badge>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5">
                                        @if ($jalur->gelombang_count > 0)
                                            <x-badge tone="brass">Dipakai di {{ $jalur->gelombang_count }} Gelombang</x-badge>
                                        @else
                                            <x-badge tone="slate">Tidak Dipakai</x-badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
```

- [ ] **Step 4: Verify Blade compiles**

Run: `php artisan view:cache && php artisan view:clear`
Expected: `Blade templates cached successfully.` then `Compiled views cleared successfully.` — no syntax errors.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter=JalurPpdbTest`
Expected: `15 passed`.

- [ ] **Step 6: Commit**

```bash
git add resources/views/admin/jalur-ppdb/index.blade.php tests/Feature/Admin/JalurPpdbTest.php
git commit -m "feat: redesign Jalur PPDB index to the TailAdmin pattern with gelombang-usage badge"
```

---

## Task 3: Redesign — Jalur PPDB create form

**Files:**
- Modify: `resources/views/admin/jalur-ppdb/create.blade.php`

**Interfaces:**
- Consumes: `tahunAjaranAktif` (unchanged, already passed by `create()`).

- [ ] **Step 1: Replace `create.blade.php`**

Replace the full content of `resources/views/admin/jalur-ppdb/create.blade.php`:

```blade
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Tambah Jalur &mdash; {{ $tahunAjaranAktif->nama }}</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.jalur-ppdb.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Jalur PPDB</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Tambah</b>
            </p>
        </div>

        <form method="POST" action="{{ route('admin.jalur-ppdb.store') }}">
            @csrf

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="signpost" class="h-[15px] w-[15px] text-gray-400" />
                    Detail Jalur
                </p>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-input-label value="Nama Jalur" />
                        <x-text-input type="text" name="nama" value="{{ old('nama') }}" placeholder="Contoh: Reguler, Prestasi, Afirmasi" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
                    </div>

                    <div class="sm:col-span-2">
                        <x-input-label value="Deskripsi (Opsional)" />
                        <textarea name="deskripsi" rows="3" placeholder="Jelaskan kriteria atau ketentuan jalur ini" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm focus:border-brand-500 focus:ring-brand-500">{{ old('deskripsi') }}</textarea>
                        <x-input-error :messages="$errors->get('deskripsi')" class="mt-1.5" />
                    </div>
                </div>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <x-primary-button type="submit">Simpan &amp; Lanjutkan</x-primary-button>
                <a href="{{ route('admin.jalur-ppdb.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Batal</a>
            </div>
        </form>
    </div>
</x-app-layout>
```

- [ ] **Step 2: Verify Blade compiles**

Run: `php artisan view:cache && php artisan view:clear`
Expected: no syntax errors.

- [ ] **Step 3: Run the existing regression tests**

Run: `php artisan test --filter=JalurPpdbTest`
Expected: `15 passed` — this task adds no new tests (pure visual change to a page whose functional behavior is already covered), but the existing `'creates a jalur scoped to the active tahun ajaran'` test exercises this exact form and must stay green.

- [ ] **Step 4: Commit**

```bash
git add resources/views/admin/jalur-ppdb/create.blade.php
git commit -m "feat: redesign Jalur PPDB create form to full-width TailAdmin pattern"
```

---

## Task 4: Redesign — Jalur PPDB edit form (main card)

**Files:**
- Modify: `resources/views/admin/jalur-ppdb/edit.blade.php`
- Test: `tests/Feature/Admin/JalurPpdbTest.php` (append)

**Interfaces:**
- Consumes: `jalur`, `gelombangPemakai` from Task 1's `edit()`. `gelombangList`/`jenisTesList` (unchanged, consumed by the `seleksi` partial, untouched by this task).
- Produces: nothing new — the 3 `@include`s below the main card keep their existing variable names, consumed by Tasks 5-7.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Admin/JalurPpdbTest.php`:

```php
it('shows the Gelombang kelengkapan badge and gelombang names on the edit page', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();

    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 10,
    ]);
    $gelombang->jalur()->attach($jalur->id);

    $this->actingAs($user)->get(route('admin.jalur-ppdb.edit', $jalur))
        ->assertOk()
        ->assertSee('Gelombang (1)')
        ->assertSee('Dipakai di gelombang: Gelombang 1');
});

it('shows a "tidak dipakai" message near the status toggle when no gelombang uses the jalur', function () {
    [$lembaga, $user, $tahunAjaran] = buatAdminJalur();

    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);

    $this->actingAs($user)->get(route('admin.jalur-ppdb.edit', $jalur))
        ->assertOk()
        ->assertSee('Gelombang (0)')
        ->assertSee('Tidak dipakai di gelombang manapun saat ini.');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=JalurPpdbTest`
Expected: the 2 new tests FAIL — the edit page doesn't render a Gelombang badge or usage text yet.

- [ ] **Step 3: Replace the top of `edit.blade.php`**

Replace the full content of `resources/views/admin/jalur-ppdb/edit.blade.php` from the start through the closing `</form>` of the main card (i.e. everything before the three `@include` lines). The file currently reads:

```blade
<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">SPMB</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Jalur: {{ $jalur->nama }}</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl bg-signal-green/10 p-4 text-sm text-signal-green">{{ session('status') }}</div>
        @endif
        @error('seleksi')
            <div class="rounded-xl bg-signal-red/10 p-4 text-sm text-signal-red">{{ $message }}</div>
        @enderror

        <x-panel>
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-ink/10 px-6 py-4">
                <h3 class="font-display font-semibold text-ink">Kelengkapan</h3>
                <div class="flex flex-wrap gap-2">
                    <x-badge :tone="$jalur->formulirField->count() > 0 ? 'brass' : 'slate'">Formulir ({{ $jalur->formulirField->count() }})</x-badge>
                    <x-badge :tone="$jalur->dokumenSyarat->count() > 0 ? 'brass' : 'slate'">Dokumen ({{ $jalur->dokumenSyarat->count() }})</x-badge>
                    <x-badge :tone="$jalur->seleksi->count() > 0 ? 'brass' : 'slate'">Seleksi ({{ $jalur->seleksi->count() }})</x-badge>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.jalur-ppdb.update', $jalur) }}" class="space-y-5 p-6">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label value="Nama Jalur" />
                    <x-text-input type="text" name="nama" value="{{ old('nama', $jalur->nama) }}" class="mt-1.5" />
                    <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label value="Deskripsi" />
                    <textarea name="deskripsi" rows="3" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">{{ old('deskripsi', $jalur->deskripsi) }}</textarea>
                </div>

                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="hidden" name="status_aktif" value="0">
                    <input type="checkbox" name="status_aktif" value="1" class="rounded border-ink/25 text-brass focus:ring-brass" @checked($jalur->status_aktif)>
                    Jalur aktif (bisa dipilih calon murid saat portal pendaftaran dibuka)
                </label>

                <x-primary-button>Simpan Perubahan</x-primary-button>
            </form>
        </x-panel>

        @include('admin.jalur-ppdb.partials.formulir-field')
        @include('admin.jalur-ppdb.partials.dokumen-syarat')
        @include('admin.jalur-ppdb.partials.seleksi')
    </div>
</x-app-layout>
```

Change it to:

```blade
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Jalur: {{ $jalur->nama }}</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.jalur-ppdb.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Jalur PPDB</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Edit</b>
            </p>
        </div>

        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700">{{ $errors->first() }}</div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
                <p class="font-display text-sm font-bold text-gray-900">Kelengkapan</p>
                <div class="flex flex-wrap gap-2">
                    <x-badge :tone="$jalur->formulirField->count() > 0 ? 'brass' : 'slate'">Formulir ({{ $jalur->formulirField->count() }})</x-badge>
                    <x-badge :tone="$jalur->dokumenSyarat->count() > 0 ? 'brass' : 'slate'">Dokumen ({{ $jalur->dokumenSyarat->count() }})</x-badge>
                    <x-badge :tone="$jalur->seleksi->count() > 0 ? 'brass' : 'slate'">Seleksi ({{ $jalur->seleksi->count() }})</x-badge>
                    <x-badge :tone="$gelombangPemakai->isNotEmpty() ? 'brass' : 'slate'">Gelombang ({{ $gelombangPemakai->count() }})</x-badge>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.jalur-ppdb.update', $jalur) }}" class="p-5">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-input-label value="Nama Jalur" />
                        <x-text-input type="text" name="nama" value="{{ old('nama', $jalur->nama) }}" placeholder="Contoh: Reguler, Prestasi, Afirmasi" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
                    </div>

                    <div class="sm:col-span-2">
                        <x-input-label value="Deskripsi (Opsional)" />
                        <textarea name="deskripsi" rows="3" placeholder="Jelaskan kriteria atau ketentuan jalur ini" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm focus:border-brand-500 focus:ring-brand-500">{{ old('deskripsi', $jalur->deskripsi) }}</textarea>
                        <x-input-error :messages="$errors->get('deskripsi')" class="mt-1.5" />
                    </div>

                    <div class="sm:col-span-2">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="hidden" name="status_aktif" value="0">
                            <input type="checkbox" name="status_aktif" value="1" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500" @checked(old('status_aktif', $jalur->status_aktif))>
                            Jalur aktif (bisa dipilih calon murid saat portal pendaftaran dibuka)
                        </label>
                        <p class="mt-1.5 text-xs text-gray-500">
                            @if ($gelombangPemakai->isNotEmpty())
                                Dipakai di gelombang: {{ $gelombangPemakai->implode(', ') }}. Jalur tidak bisa dinonaktifkan selama masih dipakai.
                            @else
                                Tidak dipakai di gelombang manapun saat ini.
                            @endif
                        </p>
                        <x-input-error :messages="$errors->get('status_aktif')" class="mt-1.5" />
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <x-primary-button type="submit">Simpan Perubahan</x-primary-button>
                </div>
            </form>
        </div>

        @include('admin.jalur-ppdb.partials.formulir-field')
        @include('admin.jalur-ppdb.partials.dokumen-syarat')
        @include('admin.jalur-ppdb.partials.seleksi')
    </div>
</x-app-layout>
```

Note the dropped `@error('seleksi')` block: no controller in this codebase ever sets a `'seleksi'`-keyed error (`SeleksiController` uses `'gelombang_ppdb_id'`), so that block was dead markup that never rendered — the new general `@if ($errors->any())` banner replaces it and actually surfaces every validation error on this page, including the new `status_aktif` block from Task 1.

- [ ] **Step 4: Verify Blade compiles**

Run: `php artisan view:cache && php artisan view:clear`
Expected: no syntax errors. (The 3 `@include`s still point at their existing partials, which Tasks 5-7 haven't restyled yet — the page will look visually mixed until those land, which is expected mid-plan.)

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter=JalurPpdbTest`
Expected: `17 passed`.

- [ ] **Step 6: Commit**

```bash
git add resources/views/admin/jalur-ppdb/edit.blade.php tests/Feature/Admin/JalurPpdbTest.php
git commit -m "feat: redesign Jalur PPDB edit main card, surface gelombang usage and status_aktif errors"
```

---

## Task 5: Redesign — Formulir Field partial

**Files:**
- Modify: `resources/views/admin/jalur-ppdb/partials/formulir-field.blade.php`

**Interfaces:**
- Consumes: `$jalur` (from Task 4's `@include`, unchanged variable name).

- [ ] **Step 1: Replace `formulir-field.blade.php`**

Replace the full content of `resources/views/admin/jalur-ppdb/partials/formulir-field.blade.php`:

```blade
<div class="rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Formulir Field</p>
        <p class="mt-0.5 text-sm text-gray-500">Field tambahan di luar data wajib Dapodik, khusus untuk jalur ini.</p>
    </div>

    <ul class="divide-y divide-gray-100 px-5">
        @forelse ($jalur->formulirField as $field)
            <li class="flex items-center justify-between py-3">
                <div>
                    <span class="text-sm font-semibold text-gray-900">{{ $field->label }}</span>
                    <span class="ml-2 text-xs uppercase text-gray-500">{{ $field->field_type }}</span>
                    @if ($field->is_required)
                        <x-badge tone="brass">Wajib</x-badge>
                    @endif
                    @if ($field->field_type === 'select' && $field->options)
                        <p class="mt-0.5 text-xs text-gray-500">Opsi: {{ implode(', ', $field->options) }}</p>
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.formulir-field.destroy', $field) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm font-semibold text-error-600 hover:text-error-700">Hapus</button>
                </form>
            </li>
        @empty
            <li class="py-6 text-center text-sm text-gray-500">Belum ada field tambahan.</li>
        @endforelse
    </ul>

    <form method="POST" action="{{ route('admin.formulir-field.store') }}" class="space-y-3 border-t border-gray-200 bg-gray-50 px-5 py-4">
        @csrf
        <input type="hidden" name="jalur_ppdb_id" value="{{ $jalur->id }}">
        <div class="flex flex-wrap items-end gap-2">
            <div class="flex-1">
                <x-input-label value="Label Field" />
                <x-text-input type="text" name="label" placeholder="Contoh: Nomor WhatsApp Orang Tua" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Tipe" />
                <select name="field_type" class="mt-1.5 rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="text">Teks</option>
                    <option value="textarea">Teks Panjang</option>
                    <option value="number">Angka</option>
                    <option value="date">Tanggal</option>
                    <option value="select">Pilihan</option>
                    <option value="file">Berkas</option>
                </select>
            </div>
            <label class="flex items-center gap-2 pb-2.5 text-sm text-gray-700">
                <input type="checkbox" name="is_required" value="1" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                Wajib
            </label>
        </div>
        <div>
            <x-input-label value="Opsi (khusus tipe Pilihan, satu per baris)" />
            <textarea name="options" rows="2" placeholder="Opsi 1&#10;Opsi 2" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
        </div>
        <x-secondary-button type="submit">Tambah Field</x-secondary-button>
    </form>
</div>
```

- [ ] **Step 2: Verify Blade compiles**

Run: `php artisan view:cache && php artisan view:clear`
Expected: no syntax errors.

- [ ] **Step 3: Run the existing regression tests**

Run: `php artisan test --filter=FormulirFieldTest`
Expected: all pre-existing tests pass unchanged — the controller and routes weren't touched.

Also run: `php artisan test --filter=JalurPpdbTest`
Expected: `17 passed` — the `'shows the kelengkapan indicator as empty when a jalur has no children yet'` test asserts `'Formulir (0)'`, which this re-skin preserves verbatim.

- [ ] **Step 4: Commit**

```bash
git add resources/views/admin/jalur-ppdb/partials/formulir-field.blade.php
git commit -m "feat: redesign Formulir Field partial to the TailAdmin token set"
```

---

## Task 6: Redesign — Dokumen Syarat partial

**Files:**
- Modify: `resources/views/admin/jalur-ppdb/partials/dokumen-syarat.blade.php`

**Interfaces:**
- Consumes: `$jalur` (from Task 4's `@include`, unchanged variable name).

- [ ] **Step 1: Replace `dokumen-syarat.blade.php`**

Replace the full content of `resources/views/admin/jalur-ppdb/partials/dokumen-syarat.blade.php`:

```blade
<div class="rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Dokumen Syarat</p>
        <p class="mt-0.5 text-sm text-gray-500">Daftar dokumen yang harus diunggah calon murid pada jalur ini.</p>
    </div>

    <ul class="divide-y divide-gray-100 px-5">
        @forelse ($jalur->dokumenSyarat as $dokumen)
            <li class="flex items-center justify-between py-3">
                <span class="flex items-center gap-2 text-sm text-gray-900">
                    {{ $dokumen->nama_dokumen }}
                    @if ($dokumen->wajib)
                        <x-badge tone="brass">Wajib</x-badge>
                    @else
                        <x-badge tone="slate">Opsional</x-badge>
                    @endif
                </span>
                <form method="POST" action="{{ route('admin.dokumen-syarat.destroy', $dokumen) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm font-semibold text-error-600 hover:text-error-700">Hapus</button>
                </form>
            </li>
        @empty
            <li class="py-6 text-center text-sm text-gray-500">Belum ada dokumen syarat.</li>
        @endforelse
    </ul>

    <form method="POST" action="{{ route('admin.dokumen-syarat.store') }}" class="flex flex-wrap items-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-4">
        @csrf
        <input type="hidden" name="jalur_ppdb_id" value="{{ $jalur->id }}">
        <div class="flex-1">
            <x-input-label value="Nama Dokumen" />
            <x-text-input type="text" name="nama_dokumen" placeholder="mis. Akta Kelahiran" class="mt-1.5" />
        </div>
        <label class="flex items-center gap-2 pb-2.5 text-sm text-gray-700">
            <input type="hidden" name="wajib" value="0">
            <input type="checkbox" name="wajib" value="1" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500" checked>
            Wajib
        </label>
        <x-secondary-button type="submit">Tambah Dokumen</x-secondary-button>
    </form>
</div>
```

- [ ] **Step 2: Verify Blade compiles**

Run: `php artisan view:cache && php artisan view:clear`
Expected: no syntax errors.

- [ ] **Step 3: Run the existing regression tests**

Run: `php artisan test --filter=DokumenSyaratTest`
Expected: all pre-existing tests pass unchanged.

Also run: `php artisan test --filter=JalurPpdbTest`
Expected: `17 passed` — `'Dokumen (0)'` assertion preserved verbatim.

- [ ] **Step 4: Commit**

```bash
git add resources/views/admin/jalur-ppdb/partials/dokumen-syarat.blade.php
git commit -m "feat: redesign Dokumen Syarat partial to the TailAdmin token set"
```

---

## Task 7: Redesign — Seleksi & Tes partial

**Files:**
- Modify: `resources/views/admin/jalur-ppdb/partials/seleksi.blade.php`

**Interfaces:**
- Consumes: `$jalur`, `$gelombangList`, `$jenisTesList` (from Task 4's `@include`, unchanged variable names — `gelombangList`/`jenisTesList` are still produced by `JalurPpdbController::edit()` exactly as before, untouched by Task 1).

- [ ] **Step 1: Replace `seleksi.blade.php`**

Replace the full content of `resources/views/admin/jalur-ppdb/partials/seleksi.blade.php`:

```blade
<div class="rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Seleksi &amp; Tes</p>
        <p class="mt-0.5 text-sm text-gray-500">Jadwal tes untuk jalur ini, per gelombang. Boleh dikosongkan jika jalur tidak memakai tes.</p>
    </div>

    <ul class="divide-y divide-gray-100 px-5">
        @forelse ($jalur->seleksi as $seleksi)
            <li class="flex items-center justify-between py-3">
                <div>
                    <span class="text-sm font-semibold text-gray-900">{{ $seleksi->jenisTesMaster->nama }}</span>
                    <span class="ml-2 text-xs text-gray-500">{{ $seleksi->gelombangPpdb->nama }} &middot; {{ $seleksi->jadwal->format('d M Y H:i') }}</span>
                    @if ($seleksi->kriteria_kelulusan)
                        <p class="mt-0.5 text-xs text-gray-500">{{ $seleksi->kriteria_kelulusan }}</p>
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.seleksi.destroy', $seleksi) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm font-semibold text-error-600 hover:text-error-700">Hapus</button>
                </form>
            </li>
        @empty
            <li class="py-6 text-center text-sm text-gray-500">Belum ada jadwal seleksi.</li>
        @endforelse
    </ul>

    <form method="POST" action="{{ route('admin.seleksi.store') }}" class="space-y-3 border-t border-gray-200 bg-gray-50 px-5 py-4">
        @csrf
        <input type="hidden" name="jalur_ppdb_id" value="{{ $jalur->id }}">
        <div class="flex flex-wrap items-end gap-2">
            <div>
                <x-input-label value="Gelombang" />
                <select name="gelombang_ppdb_id" class="mt-1.5 rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($gelombangList as $gelombang)
                        <option value="{{ $gelombang->id }}">{{ $gelombang->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label value="Jenis Tes" />
                <select name="jenis_tes_master_id" class="mt-1.5 rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($jenisTesList as $jenisTes)
                        <option value="{{ $jenisTes->id }}">{{ $jenisTes->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label value="Jadwal" />
                <x-text-input type="datetime-local" name="jadwal" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Bobot (%)" />
                <x-text-input type="number" name="bobot" class="mt-1.5 w-24" />
            </div>
        </div>
        <div>
            <x-input-label value="Kriteria Kelulusan (opsional)" />
            <x-text-input type="text" name="kriteria_kelulusan" class="mt-1.5" />
        </div>
        <x-secondary-button type="submit">Tambah Jadwal Seleksi</x-secondary-button>
    </form>
</div>
```

- [ ] **Step 2: Verify Blade compiles**

Run: `php artisan view:cache && php artisan view:clear`
Expected: no syntax errors.

- [ ] **Step 3: Run the existing regression tests**

Run: `php artisan test --filter=SeleksiTest`
Expected: all pre-existing tests pass unchanged.

Also run: `php artisan test --filter=JalurPpdbTest`
Expected: `17 passed` — `'Seleksi (0)'` assertion preserved verbatim.

- [ ] **Step 4: Commit**

```bash
git add resources/views/admin/jalur-ppdb/partials/seleksi.blade.php
git commit -m "feat: redesign Seleksi & Tes partial to the TailAdmin token set"
```

---

## Task 8: Final verification

**Files:** (no new files — pure verification)

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test`
Expected: every test passes, 0 failures — including `JalurPpdbTest` (17), `FormulirFieldTest`, `DokumenSyaratTest`, `SeleksiTest`, `GelombangPpdbTest`, `GelombangJalurRestrictionTest`, alongside the full pre-existing suite.

- [ ] **Step 2: Rebuild frontend assets**

Run: `npm run build`
Expected: clean build, no warnings about missing content.

- [ ] **Step 3: Manual verification of the full flow**

With `composer dev` running: log in as `superadmin@sistem.test` / `password`, switch to a lembaga via the topbar switcher, go to Jalur PPDB. Confirm the index shows the filter card (search + tahun ajaran), Aksi dropdown, Status badge, and "Dipakai di Gelombang"/"Tidak Dipakai" badges. Open a jalur's edit page that's currently assigned to a gelombang (e.g. SMP's seeded "Reguler" or "Prestasi" from `GelombangJalurSeeder`, both attached to SMP's Gelombang 1) — confirm the Kelengkapan row shows a "Gelombang (1)" badge and the text near the status toggle lists "Gelombang 1". Try unchecking "Jalur aktif" and saving — confirm it's rejected with a red error banner naming the gelombang, and `status_aktif` in the database is unchanged. Then open SMP's "Afirmasi" jalur (not assigned to any gelombang per the seeder) and confirm deactivating it succeeds normally. Scroll down and confirm Formulir Field / Dokumen Syarat / Seleksi & Tes all render in the new card style and their add/delete forms still work.

- [ ] **Step 4: Commit any final cleanup**

If Step 3 surfaces no issues, there's nothing to commit here — this task is verification-only. If it does surface an issue, fix it, re-run Steps 1-2, and commit the fix with a message describing what was wrong.
