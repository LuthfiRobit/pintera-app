# M3 SPMB Verifikasi & Keputusan — Admin UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the admin-facing UI for M3 on top of the already-merged data layer (Plan 1 of 2): a server-side-datatable index of pendaftaran, a detail page for document verification + score entry + final decisions, a mass score-entry page, batch SK (surat keputusan) PDF generation, integration into M2's public bukti-pendaftaran PDF, and — closing the loop for manual testing — realistic dummy data across the existing demo SMP/SMA lembaga.

**Architecture:** Mirrors the already-shipped Roles page exactly (server-side datatable via `fetch`/Alpine, AJAX actions with toast notifications, no full-page reloads for state changes) — this is an authenticated internal admin surface, not a public wizard, so it reuses `x-app-layout` and the admin panel's existing design tokens directly, not M2's public-portal blue theme.

**Tech Stack:** Laravel 12, Blade, Alpine.js, Tailwind CSS, Spatie Laravel Permission (via `$this->authorize('modul.aksi')`, exactly like every other admin controller), `barryvdh/laravel-dompdf` (already installed since M2), Pest PHP.

## Global Constraints

- Every controller action checks its own specific permission via `$this->authorize('spmb-pendaftaran.xxx')` (Laravel's ability-name convention, auto-registered by Spatie Permission — confirmed this is the exact pattern `GelombangPpdbController` already uses, not a bespoke Policy class). Never check a role name directly anywhere in this plan's code.
- All queries scope explicitly to the acting user's `lembaga_id` (`Pendaftaran` does NOT have `BelongsToTenant` — established in the data-layer plan — so every query in this plan must add `->where('lembaga_id', $request->user()->lembaga_id)` or equivalent explicitly; `SkPpdb` DOES have `BelongsToTenant` so it auto-scopes).
- Document verification, nilai entry, and keputusan are all non-blocking of each other (per the approved design spec): a decision can be set with incomplete documents/scores, an SK can be issued with some pendaftaran still `menunggu_verifikasi` (with a warning, not a hard block).
- Reuse existing generic Blade components (`x-panel`, `x-badge`, `x-input-label`, `x-text-input`, `x-input-error`, `x-primary-button`, `x-link-button`, `x-secondary-button`) exactly as they exist — this is admin UI, so no new "spmb-" prefixed component variants like M2's public wizard needed (those were specifically for the public portal's separate blue theme).
- Every AJAX interaction follows the established `role-form.js`/`roles-table.js` pattern exactly: `fetch()` with `Accept: application/json` + CSRF header, `Alpine.store('toast').push(...)` for success/error feedback, no full page reloads for state-changing actions.
- `catatan_verifikasi` is required by server-side validation when rejecting a document (`status_verifikasi = ditolak`), optional otherwise — per the approved spec.
- Nilai entry always uses `HasilSeleksi::updateOrCreate(['pendaftaran_id' => ..., 'seleksi_ppdb_id' => ...], [...])` — never a bare `create()` — so both the detail-page entry point and the mass-entry page write to the exact same row without ever duplicating.

---

### Task 1: Index page — server-side datatable

**Files:**
- Create: `app/Http/Controllers/Admin/PendaftaranAdminController.php`
- Create: `resources/views/admin/spmb-pendaftaran/index.blade.php`
- Create: `resources/js/pendaftaran-table.js`
- Modify: `resources/js/app.js`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/PendaftaranAdminIndexTest.php`

**Interfaces:**
- Consumes: `Pendaftaran` (with `calonMurid`, `jalurPpdb`, `gelombangPpdb`, `dokumen` relations, all already exist), `spmb-pendaftaran.view` permission (Plan 1).
- Produces: `PendaftaranAdminController::index()` (renders the page) and `::data()` (JSON, paginated/searched/filtered/sorted — same response shape as `RoleController::data()`). Route names `admin.spmb-pendaftaran.index`, `admin.spmb-pendaftaran.data`, `admin.spmb-pendaftaran.show` (the last one is wired here but implemented in Task 2).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/PendaftaranAdminIndexTest.php`:

```php
<?php

use App\Models\CalonMurid;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder())->run();
});

function buatPendaftaranUntukAdmin(?Lembaga $lembaga = null, string $namaCalon = 'Ahmad Fauzan', string $status = 'menunggu_verifikasi'): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = $lembaga ?? Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now()->subDay(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 40,
    ]);
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id, 'nama_lengkap' => $namaCalon]);
    $pendaftaran = Pendaftaran::create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-'.random_int(10000, 99999), 'email_pendaftaran' => 'wali@example.test',
        'status' => $status, 'submitted_at' => now(),
    ]);

    return [$lembaga, $jalur, $gelombang, $pendaftaran];
}

it('denies access to the index page without the spmb-pendaftaran.view permission', function () {
    [$lembaga] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->get(route('admin.spmb-pendaftaran.index'))->assertForbidden();
});

it('shows the index page with the view permission', function () {
    [$lembaga] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $this->actingAs($user)->get(route('admin.spmb-pendaftaran.index'))->assertOk();
});

it('returns only pendaftaran belonging to the acting user lembaga, searchable and paginated', function () {
    [$lembagaA] = buatPendaftaranUntukAdmin(namaCalon: 'Ahmad Fauzan');
    [$lembagaB] = buatPendaftaranUntukAdmin(namaCalon: 'Budi Santoso');
    $user = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $user->assignRole('admin_administrasi');

    $response = $this->actingAs($user)->getJson(route('admin.spmb-pendaftaran.data'));

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('nama_calon_murid');
    expect($names)->toContain('Ahmad Fauzan');
    expect($names)->not->toContain('Budi Santoso');

    $searchResponse = $this->actingAs($user)->getJson(route('admin.spmb-pendaftaran.data', ['search' => 'Ahmad']));
    expect(collect($searchResponse->json('data'))->pluck('nama_calon_murid'))->toContain('Ahmad Fauzan');

    $missResponse = $this->actingAs($user)->getJson(route('admin.spmb-pendaftaran.data', ['search' => 'Zzz Tidak Ada']));
    expect($missResponse->json('data'))->toBeEmpty();
});

it('filters by status', function () {
    [$lembaga, , , $diterima] = buatPendaftaranUntukAdmin(namaCalon: 'Sudah Diterima', status: 'diterima');
    buatPendaftaranUntukAdmin($lembaga, 'Masih Menunggu', 'menunggu_verifikasi');
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $response = $this->actingAs($user)->getJson(route('admin.spmb-pendaftaran.data', ['status' => 'diterima']));

    $names = collect($response->json('data'))->pluck('nama_calon_murid');
    expect($names)->toContain('Sudah Diterima');
    expect($names)->not->toContain('Masih Menunggu');
});

it('includes a dokumen progress count per row', function () {
    [$lembaga, $jalur, , $pendaftaran] = buatPendaftaranUntukAdmin();
    $syarat = \App\Models\DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    \App\Models\DokumenPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id, 'dokumen_syarat_ppdb_id' => $syarat->id,
        'file_path' => 'x.pdf', 'nama_file_asli' => 'x.pdf', 'mime_type' => 'application/pdf', 'ukuran_bytes' => 10,
        'status_verifikasi' => 'diterima',
    ]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $response = $this->actingAs($user)->getJson(route('admin.spmb-pendaftaran.data'));

    $row = collect($response->json('data'))->firstWhere('id', $pendaftaran->id);
    expect($row['dokumen_terverifikasi'])->toBe(1);
    expect($row['dokumen_total'])->toBe(1);
});
```

- [ ] **Step 2: Run the tests to confirm they fail**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/Admin/PendaftaranAdminIndexTest.php`
Expected: FAIL — route/controller/view don't exist yet.

- [ ] **Step 3: Add routes**

Modify `routes/admin.php` — add the import and a new route block after the `Route::post('spmb-konfigurasi/duplikasi', ...)` line:

```php
use App\Http\Controllers\Admin\PendaftaranAdminController;
```

```php
    Route::get('spmb-pendaftaran', [PendaftaranAdminController::class, 'index'])->name('spmb-pendaftaran.index');
    Route::get('spmb-pendaftaran/data', [PendaftaranAdminController::class, 'data'])->name('spmb-pendaftaran.data');
    Route::get('spmb-pendaftaran/{pendaftaran}', [PendaftaranAdminController::class, 'show'])->name('spmb-pendaftaran.show');
```

(The `show` route is wired here so `index.blade.php`'s row links resolve; `PendaftaranAdminController::show()` itself is implemented in Task 2 — until then it doesn't exist as a method, which is fine since no test in this task calls it.)

- [ ] **Step 4: Create `PendaftaranAdminController`**

Create `app/Http/Controllers/Admin/PendaftaranAdminController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\Pendaftaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class PendaftaranAdminController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('spmb-pendaftaran.view');

        return view('admin.spmb-pendaftaran.index');
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('spmb-pendaftaran.view');

        $query = Pendaftaran::where('lembaga_id', $request->user()->lembaga_id)
            ->with(['calonMurid', 'jalurPpdb', 'gelombangPpdb'])
            ->withCount([
                'dokumen as dokumen_total',
                'dokumen as dokumen_terverifikasi_count' => fn ($q) => $q->where('status_verifikasi', 'diterima'),
            ]);

        if ($search = trim((string) $request->string('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('kode_pendaftaran', 'like', '%'.$search.'%')
                    ->orWhereHas('calonMurid', fn ($cm) => $cm->where('nama_lengkap', 'like', '%'.$search.'%'));
            });
        }

        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }

        if ($gelombangId = $request->integer('gelombang_ppdb_id')) {
            $query->where('gelombang_ppdb_id', $gelombangId);
        }

        if ($jalurId = $request->integer('jalur_ppdb_id')) {
            $query->where('jalur_ppdb_id', $jalurId);
        }

        $sortable = ['submitted_at', 'status'];
        $sort = in_array($request->string('sort')->value(), $sortable, true) ? $request->string('sort')->value() : 'submitted_at';
        $direction = $request->string('direction')->value() === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $direction);

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->getCollection()->map(fn (Pendaftaran $pendaftaran) => [
                'id' => $pendaftaran->id,
                'kode_pendaftaran' => $pendaftaran->kode_pendaftaran,
                'nama_calon_murid' => $pendaftaran->calonMurid->nama_lengkap,
                'jalur' => $pendaftaran->jalurPpdb->nama,
                'gelombang' => $pendaftaran->gelombangPpdb->nama,
                'status' => $pendaftaran->status,
                'dokumen_total' => $pendaftaran->dokumen_total,
                'dokumen_terverifikasi' => $pendaftaran->dokumen_terverifikasi_count,
                'submitted_at' => $pendaftaran->submitted_at->format('d M Y H:i'),
            ])->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }
}
```

- [ ] **Step 5: Create the JS datatable driver**

Create `resources/js/pendaftaran-table.js`:

```js
export function pendaftaranTable(config) {
    return {
        rows: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
        search: '',
        status: '',
        page: 1,
        loading: false,
        searchTimeout: null,
        dataUrl: config.dataUrl,
        showUrlTemplate: config.showUrlTemplate,

        init() {
            this.fetchData();
        },

        onSearchInput() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.page = 1;
                this.fetchData();
            }, 350);
        },

        onStatusChange() {
            this.page = 1;
            this.fetchData();
        },

        goToPage(page) {
            if (page < 1 || page > this.meta.last_page) {
                return;
            }
            this.page = page;
            this.fetchData();
        },

        showUrl(row) {
            return this.showUrlTemplate.replace('__ID__', row.id);
        },

        async fetchData() {
            this.loading = true;
            const params = new URLSearchParams({
                search: this.search,
                status: this.status,
                page: this.page,
            });

            try {
                const response = await fetch(`${this.dataUrl}?${params}`, {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) {
                    throw new Error('request failed');
                }

                const json = await response.json();
                this.rows = json.data;
                this.meta = json.meta;
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memuat data pendaftaran.');
            } finally {
                this.loading = false;
            }
        },
    };
}
```

Modify `resources/js/app.js` — add the import and registration alongside the existing ones:

```js
import { pendaftaranTable } from './pendaftaran-table';
```

```js
Alpine.data('pendaftaranTable', pendaftaranTable);
```

- [ ] **Step 6: Create the index view**

Create `resources/views/admin/spmb-pendaftaran/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">SPMB</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Verifikasi &amp; Keputusan Pendaftaran</h2>
    </x-slot>

    <div
        class="mx-auto max-w-6xl space-y-6"
        x-data="pendaftaranTable({
            dataUrl: @js(route('admin.spmb-pendaftaran.data')),
            showUrlTemplate: @js(route('admin.spmb-pendaftaran.show', ['pendaftaran' => '__ID__'])),
        })"
    >
        <x-panel>
            <div class="flex flex-wrap items-center gap-3 border-b border-ink/10 p-4">
                <input
                    type="search"
                    x-model="search"
                    @input="onSearchInput()"
                    placeholder="Cari nama atau kode pendaftaran..."
                    class="w-full max-w-xs rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass"
                >
                <select
                    x-model="status"
                    @change="onStatusChange()"
                    class="rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass"
                >
                    <option value="">Semua Status</option>
                    <option value="menunggu_verifikasi">Menunggu Verifikasi</option>
                    <option value="diterima">Diterima</option>
                    <option value="ditolak">Ditolak</option>
                </select>
                <button
                    type="button"
                    @click="fetchData()"
                    class="ml-auto inline-flex items-center gap-2 rounded-xl border border-ink/15 px-3 py-2 text-sm font-medium text-ink hover:bg-paper"
                >
                    <span x-show="loading" class="inline-block h-3 w-3 animate-spin rounded-full border-2 border-ink/30 border-t-ink"></span>
                    Refresh
                </button>
            </div>

            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink/10 bg-paper/60 text-left text-xs uppercase tracking-wide text-slate">
                        <th class="px-5 py-3 font-display font-semibold">Calon Murid</th>
                        <th class="px-5 py-3 font-display font-semibold">Jalur / Gelombang</th>
                        <th class="px-5 py-3 font-display font-semibold">Dokumen</th>
                        <th class="px-5 py-3 font-display font-semibold">Status</th>
                        <th class="px-5 py-3 font-display font-semibold">Tanggal Submit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink/10">
                    <template x-for="row in rows" :key="row.id">
                        <tr class="cursor-pointer transition hover:bg-paper/50" @click="window.location.href = showUrl(row)">
                            <td class="px-5 py-3.5">
                                <p class="font-medium text-ink" x-text="row.nama_calon_murid"></p>
                                <p class="font-mono text-xs text-slate" x-text="row.kode_pendaftaran"></p>
                            </td>
                            <td class="px-5 py-3.5 text-ink">
                                <span x-text="row.jalur"></span> &middot; <span class="text-slate" x-text="row.gelombang"></span>
                            </td>
                            <td class="px-5 py-3.5 text-slate">
                                <span x-text="row.dokumen_terverifikasi"></span>/<span x-text="row.dokumen_total"></span> terverifikasi
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold"
                                    :class="{
                                        'bg-signal-amber/10 text-signal-amber': row.status === 'menunggu_verifikasi',
                                        'bg-signal-green/10 text-signal-green': row.status === 'diterima',
                                        'bg-signal-red/10 text-signal-red': row.status === 'ditolak',
                                    }"
                                    x-text="row.status === 'menunggu_verifikasi' ? 'Menunggu Verifikasi' : (row.status === 'diterima' ? 'Diterima' : 'Ditolak')"
                                ></span>
                            </td>
                            <td class="px-5 py-3.5 text-slate" x-text="row.submitted_at"></td>
                        </tr>
                    </template>
                    <tr x-show="!loading && rows.length === 0">
                        <td colspan="5" class="px-5 py-10 text-center text-slate">Tidak ada pendaftaran yang cocok.</td>
                    </tr>
                </tbody>
            </table>

            <div class="flex items-center justify-between border-t border-ink/10 p-4 text-sm text-slate">
                <p>Halaman <span x-text="meta.current_page"></span> dari <span x-text="meta.last_page"></span> &middot; <span x-text="meta.total"></span> pendaftaran</p>
                <div class="flex items-center gap-2">
                    <button type="button" @click="goToPage(meta.current_page - 1)" :disabled="meta.current_page <= 1" class="rounded-lg border border-ink/15 px-3 py-1.5 disabled:opacity-40">Sebelumnya</button>
                    <button type="button" @click="goToPage(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page" class="rounded-lg border border-ink/15 px-3 py-1.5 disabled:opacity-40">Berikutnya</button>
                </div>
            </div>
        </x-panel>
    </div>
</x-app-layout>
```

- [ ] **Step 7: Run the tests to confirm they pass**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/Admin/PendaftaranAdminIndexTest.php`
Expected: PASS (5 tests).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/PendaftaranAdminController.php resources/views/admin/spmb-pendaftaran/index.blade.php resources/js/pendaftaran-table.js resources/js/app.js routes/admin.php tests/Feature/Admin/PendaftaranAdminIndexTest.php
git commit -m "feat: add M3 admin index page for pendaftaran verification (server-side datatable)"
```

---

### Task 2: Detail page — dokumen verification, nilai entry, keputusan

**Files:**
- Modify: `app/Http/Controllers/Admin/PendaftaranAdminController.php`
- Create: `resources/views/admin/spmb-pendaftaran/show.blade.php`
- Create: `resources/js/pendaftaran-detail.js`
- Modify: `resources/js/app.js`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/PendaftaranAdminDetailTest.php`

**Interfaces:**
- Consumes: `Pendaftaran`, `DokumenPendaftaran`, `HasilSeleksi`, `SeleksiPpdb`, `CalonMurid` (+ `alamat`/`keluarga`/`dataPeriodik`/`dataKhusus` relations), `JawabanFormulirPendaftaran`, permissions `spmb-pendaftaran.verifikasi-dokumen`, `.nilai-seleksi`, `.tetapkan-keputusan` (each checked independently per action).
- Produces: `PendaftaranAdminController::show()`, `::verifikasiDokumen()`, `::simpanNilai()`, `::tetapkanKeputusan()`. Route names `admin.spmb-pendaftaran.verifikasi-dokumen`, `.nilai`, `.keputusan`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/PendaftaranAdminDetailTest.php`:

```php
<?php

use App\Models\CalonMurid;
use App\Models\DokumenPendaftaran;
use App\Models\DokumenSyaratPpdb;
use App\Models\HasilSeleksi;
use App\Models\JenisTesMaster;
use App\Models\Pendaftaran;
use App\Models\SeleksiPpdb;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder())->run();
});

it('shows the detail page with calon murid data, dokumen, and nilai panels', function () {
    [$lembaga, $jalur, , $pendaftaran] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $this->actingAs($user)->get(route('admin.spmb-pendaftaran.show', $pendaftaran))
        ->assertOk()
        ->assertSee($pendaftaran->calonMurid->nama_lengkap);
});

it('404s the detail page for a pendaftaran belonging to a different lembaga', function () {
    [$lembagaA] = buatPendaftaranUntukAdmin();
    [, , , $pendaftaranB] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $user->assignRole('admin_administrasi');

    $this->actingAs($user)->get(route('admin.spmb-pendaftaran.show', $pendaftaranB))->assertNotFound();
});

it('verifies a dokumen with a required catatan when rejecting', function () {
    [$lembaga, $jalur, , $pendaftaran] = buatPendaftaranUntukAdmin();
    $syarat = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    $dokumen = DokumenPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id, 'dokumen_syarat_ppdb_id' => $syarat->id,
        'file_path' => 'x.pdf', 'nama_file_asli' => 'x.pdf', 'mime_type' => 'application/pdf', 'ukuran_bytes' => 10,
    ]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $rejectWithoutCatatan = $this->actingAs($user)->postJson(
        route('admin.spmb-pendaftaran.verifikasi-dokumen', [$pendaftaran, $dokumen]),
        ['status_verifikasi' => 'ditolak']
    );
    $rejectWithoutCatatan->assertStatus(422);

    $reject = $this->actingAs($user)->postJson(
        route('admin.spmb-pendaftaran.verifikasi-dokumen', [$pendaftaran, $dokumen]),
        ['status_verifikasi' => 'ditolak', 'catatan_verifikasi' => 'Foto buram.']
    );
    $reject->assertOk();

    $dokumen->refresh();
    expect($dokumen->status_verifikasi)->toBe('ditolak');
    expect($dokumen->catatan_verifikasi)->toBe('Foto buram.');
    expect($dokumen->diverifikasi_oleh_user_id)->toBe($user->id);
});

it('denies dokumen verification without the verifikasi-dokumen permission', function () {
    [$lembaga, $jalur, , $pendaftaran] = buatPendaftaranUntukAdmin();
    $syarat = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    $dokumen = DokumenPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id, 'dokumen_syarat_ppdb_id' => $syarat->id,
        'file_path' => 'x.pdf', 'nama_file_asli' => 'x.pdf', 'mime_type' => 'application/pdf', 'ukuran_bytes' => 10,
    ]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->postJson(
        route('admin.spmb-pendaftaran.verifikasi-dokumen', [$pendaftaran, $dokumen]),
        ['status_verifikasi' => 'diterima']
    )->assertForbidden();
});

it('saves nilai via updateOrCreate, never duplicating for the same seleksi_ppdb', function () {
    [$lembaga, $jalur, $gelombang, $pendaftaran] = buatPendaftaranUntukAdmin();
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id, 'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => now()->addWeek(), 'kriteria_kelulusan' => 'Nilai minimal 65', 'bobot' => 60,
    ]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $this->actingAs($user)->postJson(
        route('admin.spmb-pendaftaran.nilai', $pendaftaran),
        ['seleksi_ppdb_id' => $seleksi->id, 'nilai' => 80, 'catatan' => 'Baik']
    )->assertOk();

    $this->actingAs($user)->postJson(
        route('admin.spmb-pendaftaran.nilai', $pendaftaran),
        ['seleksi_ppdb_id' => $seleksi->id, 'nilai' => 90]
    )->assertOk();

    expect(HasilSeleksi::where('pendaftaran_id', $pendaftaran->id)->count())->toBe(1);
    expect((float) HasilSeleksi::first()->nilai)->toBe(90.0);
});

it('tetapkan keputusan sets status, catatan, and audit fields, even with unverified documents', function () {
    [$lembaga, $jalur, , $pendaftaran] = buatPendaftaranUntukAdmin();
    DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('kepala_sekolah');

    $response = $this->actingAs($user)->postJson(
        route('admin.spmb-pendaftaran.keputusan', $pendaftaran),
        ['status' => 'diterima', 'catatan_keputusan' => 'Nilai memenuhi syarat.']
    );

    $response->assertOk();
    $pendaftaran->refresh();
    expect($pendaftaran->status)->toBe('diterima');
    expect($pendaftaran->catatan_keputusan)->toBe('Nilai memenuhi syarat.');
    expect($pendaftaran->ditetapkan_oleh_user_id)->toBe($user->id);
    expect($pendaftaran->ditetapkan_pada)->not->toBeNull();
});

it('denies tetapkan keputusan without the tetapkan-keputusan permission', function () {
    [$lembaga, , , $pendaftaran] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $this->actingAs($user)->postJson(
        route('admin.spmb-pendaftaran.keputusan', $pendaftaran),
        ['status' => 'diterima']
    )->assertForbidden();
});
```

- [ ] **Step 2: Run the tests to confirm they fail**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/Admin/PendaftaranAdminDetailTest.php`
Expected: FAIL — routes/methods/view don't exist yet.

- [ ] **Step 3: Add routes**

Modify `routes/admin.php` — replace the placeholder `show` route added in Task 1 with the full detail-page route block:

```php
    Route::get('spmb-pendaftaran', [PendaftaranAdminController::class, 'index'])->name('spmb-pendaftaran.index');
    Route::get('spmb-pendaftaran/data', [PendaftaranAdminController::class, 'data'])->name('spmb-pendaftaran.data');
    Route::get('spmb-pendaftaran/{pendaftaran}', [PendaftaranAdminController::class, 'show'])->name('spmb-pendaftaran.show');
    Route::post('spmb-pendaftaran/{pendaftaran}/dokumen/{dokumen}', [PendaftaranAdminController::class, 'verifikasiDokumen'])->name('spmb-pendaftaran.verifikasi-dokumen');
    Route::post('spmb-pendaftaran/{pendaftaran}/nilai', [PendaftaranAdminController::class, 'simpanNilai'])->name('spmb-pendaftaran.nilai');
    Route::post('spmb-pendaftaran/{pendaftaran}/keputusan', [PendaftaranAdminController::class, 'tetapkanKeputusan'])->name('spmb-pendaftaran.keputusan');
```

- [ ] **Step 4: Update `PendaftaranAdminController`**

Modify `app/Http/Controllers/Admin/PendaftaranAdminController.php` — add these imports at the top:

```php
use App\Models\DokumenPendaftaran;
use App\Models\HasilSeleksi;
use Illuminate\Http\RedirectResponse;
```

Add these methods to the class (after `data()`):

```php
    public function show(Request $request, Pendaftaran $pendaftaran): View
    {
        $this->authorize('spmb-pendaftaran.view');
        abort_unless($pendaftaran->lembaga_id === $request->user()->lembaga_id, 404);

        $pendaftaran->load([
            'calonMurid.alamat', 'calonMurid.keluarga', 'calonMurid.dataPeriodik', 'calonMurid.dataKhusus',
            'jalurPpdb', 'gelombangPpdb',
            'dokumen.dokumenSyaratPpdb',
            'jawabanFormulir.formulirField',
            'hasilSeleksi.seleksiPpdb.jenisTesMaster',
        ]);

        $seleksiTersedia = \App\Models\SeleksiPpdb::where('jalur_ppdb_id', $pendaftaran->jalur_ppdb_id)
            ->where('gelombang_ppdb_id', $pendaftaran->gelombang_ppdb_id)
            ->with('jenisTesMaster')
            ->get();

        return view('admin.spmb-pendaftaran.show', [
            'pendaftaran' => $pendaftaran,
            'seleksiTersedia' => $seleksiTersedia,
        ]);
    }

    public function verifikasiDokumen(Request $request, Pendaftaran $pendaftaran, DokumenPendaftaran $dokumen): JsonResponse
    {
        $this->authorize('spmb-pendaftaran.verifikasi-dokumen');
        abort_unless($pendaftaran->lembaga_id === $request->user()->lembaga_id, 404);
        abort_unless($dokumen->pendaftaran_id === $pendaftaran->id, 404);

        $data = $request->validate([
            'status_verifikasi' => ['required', 'in:diterima,ditolak'],
            'catatan_verifikasi' => ['required_if:status_verifikasi,ditolak', 'nullable', 'string', 'max:1000'],
        ]);

        $dokumen->update([
            'status_verifikasi' => $data['status_verifikasi'],
            'catatan_verifikasi' => $data['catatan_verifikasi'] ?? null,
            'diverifikasi_oleh_user_id' => $request->user()->id,
            'diverifikasi_pada' => now(),
        ]);

        return response()->json(['message' => 'Dokumen berhasil diverifikasi.']);
    }

    public function simpanNilai(Request $request, Pendaftaran $pendaftaran): JsonResponse
    {
        $this->authorize('spmb-pendaftaran.nilai-seleksi');
        abort_unless($pendaftaran->lembaga_id === $request->user()->lembaga_id, 404);

        $data = $request->validate([
            'seleksi_ppdb_id' => ['required', 'integer', 'exists:seleksi_ppdb,id'],
            'nilai' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        HasilSeleksi::updateOrCreate(
            ['pendaftaran_id' => $pendaftaran->id, 'seleksi_ppdb_id' => $data['seleksi_ppdb_id']],
            [
                'nilai' => $data['nilai'] ?? null,
                'catatan' => $data['catatan'] ?? null,
                'dinilai_oleh_user_id' => $request->user()->id,
                'dinilai_pada' => now(),
            ]
        );

        return response()->json(['message' => 'Nilai berhasil disimpan.']);
    }

    public function tetapkanKeputusan(Request $request, Pendaftaran $pendaftaran): JsonResponse
    {
        $this->authorize('spmb-pendaftaran.tetapkan-keputusan');
        abort_unless($pendaftaran->lembaga_id === $request->user()->lembaga_id, 404);

        $data = $request->validate([
            'status' => ['required', 'in:diterima,ditolak'],
            'catatan_keputusan' => ['nullable', 'string', 'max:1000'],
        ]);

        $pendaftaran->update([
            'status' => $data['status'],
            'catatan_keputusan' => $data['catatan_keputusan'] ?? null,
            'ditetapkan_oleh_user_id' => $request->user()->id,
            'ditetapkan_pada' => now(),
        ]);

        return response()->json(['message' => 'Keputusan berhasil ditetapkan.']);
    }
```

Note: `SeleksiPpdb` needs a `jenisTesMaster(): BelongsTo` relation for the `with('jenisTesMaster')` calls above to work — check `app/Models/SeleksiPpdb.php` first; it already has `jenisTesMaster(): BelongsTo` (confirmed present from the M1 data layer), so no model change needed here.

- [ ] **Step 5: Create the detail-page JS driver**

Create `resources/js/pendaftaran-detail.js`:

```js
export function pendaftaranDetail(config) {
    return {
        canVerifikasiDokumen: config.canVerifikasiDokumen,
        canNilaiSeleksi: config.canNilaiSeleksi,
        canTetapkanKeputusan: config.canTetapkanKeputusan,
        verifikasiDokumenUrlTemplate: config.verifikasiDokumenUrlTemplate,
        nilaiUrl: config.nilaiUrl,
        keputusanUrl: config.keputusanUrl,
        catatanTolak: {},
        savingDokumen: {},
        savingNilai: {},
        savingKeputusan: false,
        keputusanStatus: config.initialStatus,
        catatanKeputusan: config.initialCatatanKeputusan ?? '',

        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]').content;
        },

        async verifikasiDokumen(dokumenId, status) {
            if (status === 'ditolak' && !this.catatanTolak[dokumenId]) {
                Alpine.store('toast').push('error', 'Catatan wajib diisi saat menolak dokumen.');
                return;
            }

            this.savingDokumen[dokumenId] = true;
            try {
                const response = await fetch(this.verifikasiDokumenUrlTemplate.replace('__ID__', dokumenId), {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                    },
                    body: JSON.stringify({
                        status_verifikasi: status,
                        catatan_verifikasi: this.catatanTolak[dokumenId] ?? null,
                    }),
                });

                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal memverifikasi dokumen.');
                    return;
                }

                Alpine.store('toast').push('success', json.message ?? 'Dokumen berhasil diverifikasi.');
                setTimeout(() => window.location.reload(), 600);
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal memverifikasi dokumen.');
            } finally {
                this.savingDokumen[dokumenId] = false;
            }
        },

        async simpanNilai(seleksiPpdbId, nilai, catatan) {
            this.savingNilai[seleksiPpdbId] = true;
            try {
                const response = await fetch(this.nilaiUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                    },
                    body: JSON.stringify({ seleksi_ppdb_id: seleksiPpdbId, nilai, catatan }),
                });

                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menyimpan nilai.');
                    return;
                }

                Alpine.store('toast').push('success', json.message ?? 'Nilai berhasil disimpan.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menyimpan nilai.');
            } finally {
                this.savingNilai[seleksiPpdbId] = false;
            }
        },

        async tetapkanKeputusan() {
            this.savingKeputusan = true;
            try {
                const response = await fetch(this.keputusanUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                    },
                    body: JSON.stringify({ status: this.keputusanStatus, catatan_keputusan: this.catatanKeputusan }),
                });

                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menetapkan keputusan.');
                    return;
                }

                Alpine.store('toast').push('success', json.message ?? 'Keputusan berhasil ditetapkan.');
                setTimeout(() => window.location.reload(), 600);
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menetapkan keputusan.');
            } finally {
                this.savingKeputusan = false;
            }
        },
    };
}
```

Modify `resources/js/app.js` — add:

```js
import { pendaftaranDetail } from './pendaftaran-detail';
```

```js
Alpine.data('pendaftaranDetail', pendaftaranDetail);
```

- [ ] **Step 6: Create the detail view**

Create `resources/views/admin/spmb-pendaftaran/show.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">SPMB</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">{{ $pendaftaran->calonMurid->nama_lengkap }}</h2>
        <p class="mt-1 font-mono text-sm text-slate">{{ $pendaftaran->kode_pendaftaran }}</p>
    </x-slot>

    <div
        class="mx-auto max-w-5xl space-y-6"
        x-data="pendaftaranDetail({
            canVerifikasiDokumen: @js($pendaftaran instanceof \App\Models\Pendaftaran && auth()->user()->can('spmb-pendaftaran.verifikasi-dokumen')),
            canNilaiSeleksi: @js(auth()->user()->can('spmb-pendaftaran.nilai-seleksi')),
            canTetapkanKeputusan: @js(auth()->user()->can('spmb-pendaftaran.tetapkan-keputusan')),
            verifikasiDokumenUrlTemplate: @js(route('admin.spmb-pendaftaran.verifikasi-dokumen', [$pendaftaran, '__ID__'])),
            nilaiUrl: @js(route('admin.spmb-pendaftaran.nilai', $pendaftaran)),
            keputusanUrl: @js(route('admin.spmb-pendaftaran.keputusan', $pendaftaran)),
            initialStatus: @js($pendaftaran->status === 'menunggu_verifikasi' ? 'diterima' : $pendaftaran->status),
            initialCatatanKeputusan: @js($pendaftaran->catatan_keputusan),
        })"
    >
        <x-panel>
            <div class="border-b border-ink/10 px-6 py-4">
                <h3 class="font-display font-semibold text-ink">Data Calon Murid</h3>
            </div>
            <div class="grid grid-cols-2 gap-4 p-6 text-sm">
                <div><p class="text-slate">NIK</p><p class="font-mono text-ink">{{ $pendaftaran->calonMurid->nik }}</p></div>
                <div><p class="text-slate">Jenis Kelamin</p><p class="text-ink">{{ $pendaftaran->calonMurid->jenis_kelamin }}</p></div>
                <div><p class="text-slate">Tempat, Tanggal Lahir</p><p class="text-ink">{{ $pendaftaran->calonMurid->tempat_lahir }}, {{ $pendaftaran->calonMurid->tanggal_lahir->translatedFormat('d F Y') }}</p></div>
                <div><p class="text-slate">Jalur / Gelombang</p><p class="text-ink">{{ $pendaftaran->jalurPpdb->nama }} / {{ $pendaftaran->gelombangPpdb->nama }}</p></div>
                @if ($pendaftaran->calonMurid->alamat)
                    <div class="col-span-2">
                        <p class="text-slate">Alamat</p>
                        <p class="text-ink">{{ $pendaftaran->calonMurid->alamat->alamat_jalan }}, {{ $pendaftaran->calonMurid->alamat->desa_kelurahan }}, {{ $pendaftaran->calonMurid->alamat->kecamatan }}, {{ $pendaftaran->calonMurid->alamat->kabupaten_kota }}, {{ $pendaftaran->calonMurid->alamat->provinsi }}</p>
                    </div>
                @endif
                @if ($pendaftaran->calonMurid->keluarga->isNotEmpty())
                    <div class="col-span-2">
                        <p class="text-slate">Orang Tua / Wali</p>
                        <ul class="mt-1 space-y-1 text-ink">
                            @foreach ($pendaftaran->calonMurid->keluarga as $anggota)
                                <li>{{ ucfirst($anggota->jenis) }}: {{ $anggota->nama }} @if ($anggota->pekerjaan) &middot; {{ $anggota->pekerjaan }} @endif</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if ($pendaftaran->jawabanFormulir->isNotEmpty())
                    <div class="col-span-2">
                        <p class="text-slate">Formulir Tambahan</p>
                        <ul class="mt-1 space-y-1 text-ink">
                            @foreach ($pendaftaran->jawabanFormulir as $jawaban)
                                <li>{{ $jawaban->formulirField->label }}: {{ $jawaban->nilai }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </x-panel>

        <x-panel>
            <div class="border-b border-ink/10 px-6 py-4">
                <h3 class="font-display font-semibold text-ink">Dokumen</h3>
                <p class="mt-0.5 text-sm text-slate">
                    {{ $pendaftaran->dokumen->where('status_verifikasi', 'diterima')->count() }} dari {{ $pendaftaran->dokumen->count() }} dokumen terverifikasi
                </p>
            </div>
            <ul class="divide-y divide-ink/10 px-6">
                @foreach ($pendaftaran->dokumen as $dokumen)
                    <li class="py-4" x-data="{ menolak: false }">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-medium text-ink">{{ $dokumen->dokumenSyaratPpdb->nama_dokumen }}</p>
                                <a href="{{ Storage::url($dokumen->file_path) }}" target="_blank" class="text-xs text-slate hover:text-ink">Lihat berkas: {{ $dokumen->nama_file_asli }}</a>
                                @if ($dokumen->catatan_verifikasi)
                                    <p class="mt-1 text-xs text-signal-red">Catatan: {{ $dokumen->catatan_verifikasi }}</p>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                <x-badge :tone="$dokumen->status_verifikasi === 'diterima' ? 'green' : ($dokumen->status_verifikasi === 'ditolak' ? 'red' : 'slate')">
                                    {{ $dokumen->status_verifikasi === 'diterima' ? 'Diterima' : ($dokumen->status_verifikasi === 'ditolak' ? 'Ditolak' : 'Belum Diverifikasi') }}
                                </x-badge>
                                <template x-if="canVerifikasiDokumen">
                                    <div class="flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            :disabled="savingDokumen[{{ $dokumen->id }}]"
                                            @click="verifikasiDokumen({{ $dokumen->id }}, 'diterima')"
                                            class="rounded-lg border border-signal-green/30 px-2.5 py-1 text-xs font-bold text-signal-green hover:bg-signal-green/5 disabled:opacity-40"
                                        >Terima</button>
                                        <button
                                            type="button"
                                            @click="menolak = !menolak"
                                            class="rounded-lg border border-signal-red/30 px-2.5 py-1 text-xs font-bold text-signal-red hover:bg-signal-red/5"
                                        >Tolak</button>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div x-show="menolak" x-cloak class="mt-3 flex items-center gap-2">
                            <input
                                type="text"
                                x-model="catatanTolak[{{ $dokumen->id }}]"
                                placeholder="Alasan penolakan (wajib)"
                                class="w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass"
                            >
                            <button
                                type="button"
                                :disabled="savingDokumen[{{ $dokumen->id }}]"
                                @click="verifikasiDokumen({{ $dokumen->id }}, 'ditolak')"
                                class="shrink-0 rounded-xl bg-signal-red px-3 py-2 text-xs font-bold text-white disabled:opacity-40"
                            >Kirim Penolakan</button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-panel>

        <x-panel>
            <div class="border-b border-ink/10 px-6 py-4">
                <h3 class="font-display font-semibold text-ink">Penilaian &amp; Keputusan</h3>
            </div>
            <div class="p-6">
                @if ($seleksiTersedia->isEmpty())
                    <p class="text-sm text-slate">Tidak ada jadwal seleksi untuk jalur ini.</p>
                @else
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-slate">
                                <th class="pb-2">Jenis Tes</th>
                                <th class="pb-2">Bobot</th>
                                <th class="pb-2">Nilai</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink/10">
                            @foreach ($seleksiTersedia as $seleksi)
                                @php $hasil = $pendaftaran->hasilSeleksi->firstWhere('seleksi_ppdb_id', $seleksi->id); @endphp
                                <tr x-data="{ nilai: {{ $hasil?->nilai ?? 'null' }}, catatan: @js($hasil?->catatan) }">
                                    <td class="py-3 text-ink">{{ $seleksi->jenisTesMaster->nama }}</td>
                                    <td class="py-3 text-slate">{{ $seleksi->bobot }}%</td>
                                    <td class="py-3">
                                        <template x-if="canNilaiSeleksi">
                                            <div class="flex items-center gap-2">
                                                <input type="number" min="0" max="100" step="0.01" x-model="nilai" class="w-24 rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                                                <button
                                                    type="button"
                                                    :disabled="savingNilai[{{ $seleksi->id }}]"
                                                    @click="simpanNilai({{ $seleksi->id }}, nilai, catatan)"
                                                    class="rounded-lg border border-ink/15 px-2.5 py-1.5 text-xs font-bold text-ink hover:bg-paper disabled:opacity-40"
                                                >Simpan</button>
                                            </div>
                                        </template>
                                        <template x-if="!canNilaiSeleksi">
                                            <span x-text="nilai ?? '-'"></span>
                                        </template>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                <div class="mt-6 border-t border-ink/10 pt-6">
                    <p class="text-sm font-medium text-ink">Status saat ini:
                        <x-badge :tone="$pendaftaran->status === 'diterima' ? 'green' : ($pendaftaran->status === 'ditolak' ? 'red' : 'amber')">
                            {{ $pendaftaran->status === 'diterima' ? 'Diterima' : ($pendaftaran->status === 'ditolak' ? 'Ditolak' : 'Menunggu Verifikasi') }}
                        </x-badge>
                    </p>

                    <template x-if="canTetapkanKeputusan">
                        <div class="mt-4 space-y-3">
                            <div>
                                <x-input-label value="Keputusan" />
                                <select x-model="keputusanStatus" class="mt-1.5 w-full max-w-xs rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                                    <option value="diterima">Diterima</option>
                                    <option value="ditolak">Ditolak</option>
                                </select>
                            </div>
                            <div>
                                <x-input-label value="Catatan (opsional)" />
                                <x-text-input type="text" x-model="catatanKeputusan" class="mt-1.5" />
                            </div>
                            <button
                                type="button"
                                :disabled="savingKeputusan"
                                @click="tetapkanKeputusan()"
                                class="inline-flex items-center gap-2 rounded-xl bg-ink px-4 py-2.5 text-sm font-bold text-paper shadow-sm transition hover:bg-ink/90 disabled:opacity-60"
                            >
                                <span x-text="savingKeputusan ? 'Menyimpan...' : 'Tetapkan Keputusan'"></span>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </x-panel>
    </div>
</x-app-layout>
```

- [ ] **Step 7: Run the tests to confirm they pass**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/Admin/PendaftaranAdminDetailTest.php`
Expected: PASS (7 tests).

- [ ] **Step 8: Run the full suite and commit**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test`
Expected: 235 (M3 Plan 1 baseline) + 5 (Task 1) + 7 (Task 2) = 247 passed, 0 failures.

```bash
git add app/Http/Controllers/Admin/PendaftaranAdminController.php resources/views/admin/spmb-pendaftaran/show.blade.php resources/js/pendaftaran-detail.js resources/js/app.js routes/admin.php tests/Feature/Admin/PendaftaranAdminDetailTest.php
git commit -m "feat: add M3 detail page — dokumen verification, nilai entry, keputusan"
```

---

### Task 3: Mass nilai-entry page

**Files:**
- Modify: `app/Http/Controllers/Admin/PendaftaranAdminController.php`
- Create: `resources/views/admin/spmb-pendaftaran/nilai-massal.blade.php`
- Create: `resources/js/nilai-massal.js`
- Modify: `resources/js/app.js`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/NilaiMassalTest.php`

**Interfaces:**
- Consumes: `SeleksiPpdb`, `Pendaftaran`, `HasilSeleksi` (Plan 1 + Task 2's `updateOrCreate` pattern, reused identically here).
- Produces: `PendaftaranAdminController::nilaiMassal()` (GET, picker + grid) and `::simpanNilaiMassal()` (POST, bulk `updateOrCreate`). Route names `admin.spmb-pendaftaran.nilai-massal`, `.nilai-massal.store`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/NilaiMassalTest.php`:

```php
<?php

use App\Models\HasilSeleksi;
use App\Models\JenisTesMaster;
use App\Models\SeleksiPpdb;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder())->run();
});

it('shows all peserta for a chosen seleksi_ppdb with their existing nilai prefilled', function () {
    [$lembaga, $jalur, $gelombang, $pendaftaranA] = buatPendaftaranUntukAdmin(namaCalon: 'Peserta A');
    [, , , $pendaftaranB] = buatPendaftaranUntukAdmin($lembaga, 'Peserta B');
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id, 'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => now()->addWeek(), 'kriteria_kelulusan' => 'Nilai minimal 65', 'bobot' => 60,
    ]);
    // Peserta B is in a different jalur/gelombang pairing's pendaftaran created independently — re-tie it to the same jalur/gelombang as A for this test.
    $pendaftaranB->update(['jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id]);
    HasilSeleksi::create(['pendaftaran_id' => $pendaftaranA->id, 'seleksi_ppdb_id' => $seleksi->id, 'nilai' => 88]);

    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $response = $this->actingAs($user)->get(route('admin.spmb-pendaftaran.nilai-massal', ['seleksi_ppdb_id' => $seleksi->id]));

    $response->assertOk()->assertSee('Peserta A')->assertSee('Peserta B')->assertSee('88');
});

it('bulk-saves nilai for multiple pendaftaran without duplicating existing hasil_seleksi rows', function () {
    [$lembaga, $jalur, $gelombang, $pendaftaranA] = buatPendaftaranUntukAdmin(namaCalon: 'Peserta A');
    [, , , $pendaftaranB] = buatPendaftaranUntukAdmin($lembaga, 'Peserta B');
    $pendaftaranB->update(['jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id]);
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id, 'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => now()->addWeek(), 'kriteria_kelulusan' => 'Nilai minimal 65', 'bobot' => 60,
    ]);
    HasilSeleksi::create(['pendaftaran_id' => $pendaftaranA->id, 'seleksi_ppdb_id' => $seleksi->id, 'nilai' => 70]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $response = $this->actingAs($user)->postJson(route('admin.spmb-pendaftaran.nilai-massal.store'), [
        'seleksi_ppdb_id' => $seleksi->id,
        'nilai' => [
            $pendaftaranA->id => 95,
            $pendaftaranB->id => 82,
        ],
    ]);

    $response->assertOk();
    expect(HasilSeleksi::where('seleksi_ppdb_id', $seleksi->id)->count())->toBe(2);
    expect((float) HasilSeleksi::where('pendaftaran_id', $pendaftaranA->id)->first()->nilai)->toBe(95.0);
    expect((float) HasilSeleksi::where('pendaftaran_id', $pendaftaranB->id)->first()->nilai)->toBe(82.0);
});

it('denies mass nilai entry without the nilai-seleksi permission', function () {
    [$lembaga, $jalur, $gelombang] = buatPendaftaranUntukAdmin();
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id, 'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => now()->addWeek(), 'kriteria_kelulusan' => 'Nilai minimal 65', 'bobot' => 60,
    ]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->postJson(route('admin.spmb-pendaftaran.nilai-massal.store'), [
        'seleksi_ppdb_id' => $seleksi->id,
        'nilai' => [],
    ])->assertForbidden();
});
```

- [ ] **Step 2: Run the tests to confirm they fail**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/Admin/NilaiMassalTest.php`
Expected: FAIL — routes/methods/view don't exist yet.

- [ ] **Step 3: Add routes**

Modify `routes/admin.php` — add after the `keputusan` route:

```php
    Route::get('spmb-pendaftaran-nilai-massal', [PendaftaranAdminController::class, 'nilaiMassal'])->name('spmb-pendaftaran.nilai-massal');
    Route::post('spmb-pendaftaran-nilai-massal', [PendaftaranAdminController::class, 'simpanNilaiMassal'])->name('spmb-pendaftaran.nilai-massal.store');
```

- [ ] **Step 4: Update `PendaftaranAdminController`**

Modify `app/Http/Controllers/Admin/PendaftaranAdminController.php` — add this import:

```php
use App\Models\SeleksiPpdb;
```

Add these two methods:

```php
    public function nilaiMassal(Request $request): View
    {
        $this->authorize('spmb-pendaftaran.nilai-seleksi');

        $daftarSeleksi = SeleksiPpdb::where('lembaga_id', $request->user()->lembaga_id)
            ->with(['jenisTesMaster', 'jalurPpdb', 'gelombangPpdb'])
            ->get();

        $seleksiTerpilih = null;
        $pesertaList = collect();

        if ($seleksiId = $request->integer('seleksi_ppdb_id')) {
            $seleksiTerpilih = SeleksiPpdb::where('lembaga_id', $request->user()->lembaga_id)->find($seleksiId);

            if ($seleksiTerpilih) {
                $pesertaList = Pendaftaran::where('lembaga_id', $request->user()->lembaga_id)
                    ->where('jalur_ppdb_id', $seleksiTerpilih->jalur_ppdb_id)
                    ->where('gelombang_ppdb_id', $seleksiTerpilih->gelombang_ppdb_id)
                    ->with(['calonMurid', 'hasilSeleksi' => fn ($q) => $q->where('seleksi_ppdb_id', $seleksiId)])
                    ->get();
            }
        }

        return view('admin.spmb-pendaftaran.nilai-massal', [
            'daftarSeleksi' => $daftarSeleksi,
            'seleksiTerpilih' => $seleksiTerpilih,
            'pesertaList' => $pesertaList,
        ]);
    }

    public function simpanNilaiMassal(Request $request): JsonResponse
    {
        $this->authorize('spmb-pendaftaran.nilai-seleksi');

        $data = $request->validate([
            'seleksi_ppdb_id' => ['required', 'integer', 'exists:seleksi_ppdb,id'],
            'nilai' => ['required', 'array'],
            'nilai.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $seleksi = SeleksiPpdb::where('lembaga_id', $request->user()->lembaga_id)->findOrFail($data['seleksi_ppdb_id']);

        $pendaftaranIds = Pendaftaran::where('lembaga_id', $request->user()->lembaga_id)
            ->where('jalur_ppdb_id', $seleksi->jalur_ppdb_id)
            ->where('gelombang_ppdb_id', $seleksi->gelombang_ppdb_id)
            ->pluck('id');

        foreach ($data['nilai'] as $pendaftaranId => $nilai) {
            if (! $pendaftaranIds->contains((int) $pendaftaranId)) {
                continue;
            }

            HasilSeleksi::updateOrCreate(
                ['pendaftaran_id' => $pendaftaranId, 'seleksi_ppdb_id' => $seleksi->id],
                ['nilai' => $nilai, 'dinilai_oleh_user_id' => $request->user()->id, 'dinilai_pada' => now()]
            );
        }

        return response()->json(['message' => 'Nilai berhasil disimpan.']);
    }
```

- [ ] **Step 5: Create the JS driver**

Create `resources/js/nilai-massal.js`:

```js
export function nilaiMassal(config) {
    return {
        seleksiPpdbId: config.seleksiPpdbId,
        nilai: config.initialNilai,
        saving: false,

        async simpan() {
            this.saving = true;
            try {
                const response = await fetch(config.storeUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ seleksi_ppdb_id: this.seleksiPpdbId, nilai: this.nilai }),
                });

                const json = await response.json();

                if (!response.ok) {
                    Alpine.store('toast').push('error', json.message ?? 'Gagal menyimpan nilai massal.');
                    return;
                }

                Alpine.store('toast').push('success', json.message ?? 'Nilai berhasil disimpan.');
            } catch (error) {
                Alpine.store('toast').push('error', 'Gagal menyimpan nilai massal.');
            } finally {
                this.saving = false;
            }
        },
    };
}
```

Modify `resources/js/app.js` — add:

```js
import { nilaiMassal } from './nilai-massal';
```

```js
Alpine.data('nilaiMassal', nilaiMassal);
```

- [ ] **Step 6: Create the view**

Create `resources/views/admin/spmb-pendaftaran/nilai-massal.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">SPMB</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Input Nilai Massal</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        <x-panel class="p-6">
            <form method="GET" action="{{ route('admin.spmb-pendaftaran.nilai-massal') }}" class="flex items-end gap-3">
                <div class="flex-1">
                    <x-input-label value="Pilih Jenis Tes / Gelombang" />
                    <select name="seleksi_ppdb_id" onchange="this.form.submit()" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        <option value="">Pilih...</option>
                        @foreach ($daftarSeleksi as $seleksi)
                            <option value="{{ $seleksi->id }}" @selected($seleksiTerpilih?->id === $seleksi->id)>
                                {{ $seleksi->jenisTesMaster->nama }} — {{ $seleksi->jalurPpdb->nama }} / {{ $seleksi->gelombangPpdb->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </x-panel>

        @if ($seleksiTerpilih)
            <x-panel
                x-data="nilaiMassal({
                    seleksiPpdbId: {{ $seleksiTerpilih->id }},
                    storeUrl: @js(route('admin.spmb-pendaftaran.nilai-massal.store')),
                    initialNilai: @js($pesertaList->mapWithKeys(fn ($p) => [$p->id => $p->hasilSeleksi->first()?->nilai])),
                })"
            >
                <div class="border-b border-ink/10 px-6 py-4">
                    <h3 class="font-display font-semibold text-ink">{{ $seleksiTerpilih->jenisTesMaster->nama }}</h3>
                    <p class="mt-0.5 text-sm text-slate">{{ $pesertaList->count() }} peserta</p>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-ink/10 bg-paper/60 text-left text-xs uppercase tracking-wide text-slate">
                            <th class="px-6 py-3 font-display font-semibold">Calon Murid</th>
                            <th class="px-6 py-3 font-display font-semibold">Nilai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink/10">
                        @foreach ($pesertaList as $peserta)
                            <tr>
                                <td class="px-6 py-3 text-ink">{{ $peserta->calonMurid->nama_lengkap }}</td>
                                <td class="px-6 py-3">
                                    <input
                                        type="number" min="0" max="100" step="0.01"
                                        x-model="nilai[{{ $peserta->id }}]"
                                        class="w-28 rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass"
                                    >
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="border-t border-ink/10 p-4">
                    <button
                        type="button"
                        :disabled="saving"
                        @click="simpan()"
                        class="inline-flex items-center gap-2 rounded-xl bg-ink px-4 py-2.5 text-sm font-bold text-paper shadow-sm transition hover:bg-ink/90 disabled:opacity-60"
                    >
                        <span x-text="saving ? 'Menyimpan...' : 'Simpan Semua Nilai'"></span>
                    </button>
                </div>
            </x-panel>
        @endif
    </div>
</x-app-layout>
```

- [ ] **Step 7: Run the tests to confirm they pass**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/Admin/NilaiMassalTest.php`
Expected: PASS (3 tests).

- [ ] **Step 8: Run the full suite and commit**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test`
Expected: 247 (after Task 2) + 3 (new) = 250 passed, 0 failures.

```bash
git add app/Http/Controllers/Admin/PendaftaranAdminController.php resources/views/admin/spmb-pendaftaran/nilai-massal.blade.php resources/js/nilai-massal.js resources/js/app.js routes/admin.php tests/Feature/Admin/NilaiMassalTest.php
git commit -m "feat: add M3 mass nilai-entry page for a chosen seleksi_ppdb"
```

---

### Task 4: Terbitkan SK (batch PDF generation)

**Files:**
- Create: `app/Http/Controllers/Admin/SkPpdbController.php`
- Create: `resources/views/pdf/sk-ppdb.blade.php`
- Create: `resources/views/admin/spmb-pendaftaran/terbitkan-sk.blade.php`
- Modify: `resources/views/admin/spmb-pendaftaran/index.blade.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/SkPpdbTest.php`

**Interfaces:**
- Consumes: `SkPpdb`, `GelombangPpdb`, `Pendaftaran` (Plan 1), `barryvdh/laravel-dompdf`.
- Produces: `SkPpdbController::create()` (form), `::store()` (generates PDF + `sk_ppdb` row + links every covered `Pendaftaran.sk_ppdb_id`). Route names `admin.sk-ppdb.create`, `.store`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/SkPpdbTest.php`:

```php
<?php

use App\Models\Pendaftaran;
use App\Models\SkPpdb;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder())->run();
});

it('generates a PDF, creates a sk_ppdb row, and links every finalized pendaftaran to it', function () {
    Storage::fake('public');
    [$lembaga, $jalur, $gelombang, $diterima] = buatPendaftaranUntukAdmin(namaCalon: 'Sudah Diterima', status: 'diterima');
    [, , , $ditolak] = buatPendaftaranUntukAdmin($lembaga, 'Sudah Ditolak', 'ditolak');
    $ditolak->update(['jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id]);
    [, , , $belumFinal] = buatPendaftaranUntukAdmin($lembaga, 'Masih Menunggu', 'menunggu_verifikasi');
    $belumFinal->update(['jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('kepala_sekolah');

    $response = $this->actingAs($user)->post(route('admin.sk-ppdb.store'), [
        'gelombang_ppdb_id' => $gelombang->id,
        'nomor_sk' => '421.3/SK-PPDB.001/2026',
        'tanggal_terbit' => now()->toDateString(),
    ]);

    $response->assertRedirect();
    $sk = SkPpdb::first();
    expect($sk)->not->toBeNull();
    expect($sk->nomor_sk)->toBe('421.3/SK-PPDB.001/2026');
    expect($sk->diterbitkan_oleh_user_id)->toBe($user->id);
    Storage::disk('public')->assertExists($sk->file_path);

    expect($diterima->fresh()->sk_ppdb_id)->toBe($sk->id);
    expect($ditolak->fresh()->sk_ppdb_id)->toBe($sk->id);
    expect($belumFinal->fresh()->sk_ppdb_id)->toBeNull();
});

it('allows issuing a second sk for pendaftaran that became final after the first sk', function () {
    Storage::fake('public');
    [$lembaga, $jalur, $gelombang, $pertama] = buatPendaftaranUntukAdmin(namaCalon: 'Batch Pertama', status: 'diterima');
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('kepala_sekolah');
    $this->actingAs($user)->post(route('admin.sk-ppdb.store'), [
        'gelombang_ppdb_id' => $gelombang->id, 'nomor_sk' => '421.3/SK-PPDB.001/2026', 'tanggal_terbit' => now()->toDateString(),
    ]);

    [, , , $susulan] = buatPendaftaranUntukAdmin($lembaga, 'Batch Susulan', 'diterima');
    $susulan->update(['jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id]);

    $this->actingAs($user)->post(route('admin.sk-ppdb.store'), [
        'gelombang_ppdb_id' => $gelombang->id, 'nomor_sk' => '421.3/SK-PPDB.002-SUSULAN/2026', 'tanggal_terbit' => now()->addWeek()->toDateString(),
    ]);

    expect(SkPpdb::count())->toBe(2);
    $skKedua = SkPpdb::where('nomor_sk', '421.3/SK-PPDB.002-SUSULAN/2026')->first();
    expect($susulan->fresh()->sk_ppdb_id)->toBe($skKedua->id);
    expect($pertama->fresh()->sk_ppdb_id)->not->toBe($skKedua->id);
});

it('rejects a duplicate nomor_sk for the same lembaga with a validation error', function () {
    Storage::fake('public');
    [$lembaga, , $gelombang] = buatPendaftaranUntukAdmin(status: 'diterima');
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('kepala_sekolah');
    $this->actingAs($user)->post(route('admin.sk-ppdb.store'), [
        'gelombang_ppdb_id' => $gelombang->id, 'nomor_sk' => '421.3/SK-PPDB.001/2026', 'tanggal_terbit' => now()->toDateString(),
    ]);

    $response = $this->actingAs($user)->post(route('admin.sk-ppdb.store'), [
        'gelombang_ppdb_id' => $gelombang->id, 'nomor_sk' => '421.3/SK-PPDB.001/2026', 'tanggal_terbit' => now()->toDateString(),
    ]);

    $response->assertSessionHasErrors('nomor_sk');
    expect(SkPpdb::count())->toBe(1);
});

it('denies terbitkan sk without the terbitkan-sk permission', function () {
    [$lembaga, , $gelombang] = buatPendaftaranUntukAdmin();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $this->actingAs($user)->post(route('admin.sk-ppdb.store'), [
        'gelombang_ppdb_id' => $gelombang->id, 'nomor_sk' => '421.3/SK-PPDB.001/2026', 'tanggal_terbit' => now()->toDateString(),
    ])->assertForbidden();
});
```

- [ ] **Step 2: Run the tests to confirm they fail**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/Admin/SkPpdbTest.php`
Expected: FAIL — route/controller/view don't exist yet.

- [ ] **Step 3: Add routes**

Modify `routes/admin.php` — add the import and route block:

```php
use App\Http\Controllers\Admin\SkPpdbController;
```

```php
    Route::get('sk-ppdb/create', [SkPpdbController::class, 'create'])->name('sk-ppdb.create');
    Route::post('sk-ppdb', [SkPpdbController::class, 'store'])->name('sk-ppdb.store');
```

- [ ] **Step 4: Create `SkPpdbController`**

Create `app/Http/Controllers/Admin/SkPpdbController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\GelombangPpdb;
use App\Models\Pendaftaran;
use App\Models\SkPpdb;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class SkPpdbController extends BaseController
{
    use AuthorizesRequests;

    public function create(Request $request): View
    {
        $this->authorize('spmb-pendaftaran.terbitkan-sk');

        $gelombangList = GelombangPpdb::where('lembaga_id', $request->user()->lembaga_id)->get();
        $gelombangTerpilih = null;
        $ringkasan = null;

        if ($gelombangId = $request->integer('gelombang_ppdb_id')) {
            $gelombangTerpilih = GelombangPpdb::where('lembaga_id', $request->user()->lembaga_id)->find($gelombangId);

            if ($gelombangTerpilih) {
                $ringkasan = [
                    'total' => Pendaftaran::where('gelombang_ppdb_id', $gelombangId)->count(),
                    'final' => Pendaftaran::where('gelombang_ppdb_id', $gelombangId)->whereIn('status', ['diterima', 'ditolak'])->count(),
                    'belum_final' => Pendaftaran::where('gelombang_ppdb_id', $gelombangId)->where('status', 'menunggu_verifikasi')->count(),
                ];
            }
        }

        return view('admin.spmb-pendaftaran.terbitkan-sk', [
            'gelombangList' => $gelombangList,
            'gelombangTerpilih' => $gelombangTerpilih,
            'ringkasan' => $ringkasan,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('spmb-pendaftaran.terbitkan-sk');

        $data = $request->validate([
            'gelombang_ppdb_id' => ['required', 'integer', 'exists:gelombang_ppdb,id'],
            'nomor_sk' => [
                'required', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($request) {
                    if (SkPpdb::where('lembaga_id', $request->user()->lembaga_id)->where('nomor_sk', $value)->exists()) {
                        $fail('Nomor SK ini sudah pernah dipakai untuk lembaga Anda.');
                    }
                },
            ],
            'tanggal_terbit' => ['required', 'date'],
        ]);

        $gelombang = GelombangPpdb::where('lembaga_id', $request->user()->lembaga_id)->findOrFail($data['gelombang_ppdb_id']);

        $pendaftaranFinal = Pendaftaran::where('gelombang_ppdb_id', $gelombang->id)
            ->where('lembaga_id', $request->user()->lembaga_id)
            ->whereIn('status', ['diterima', 'ditolak'])
            ->with('calonMurid')
            ->get();

        $sk = SkPpdb::create([
            'gelombang_ppdb_id' => $gelombang->id,
            'lembaga_id' => $request->user()->lembaga_id,
            'nomor_sk' => $data['nomor_sk'],
            'tanggal_terbit' => $data['tanggal_terbit'],
            'diterbitkan_oleh_user_id' => $request->user()->id,
            'file_path' => '',
        ]);

        $pdf = Pdf::loadView('pdf.sk-ppdb', [
            'sk' => $sk,
            'gelombang' => $gelombang,
            'lembaga' => $request->user()->lembaga,
            'pendaftaranFinal' => $pendaftaranFinal,
            'diterbitkanOleh' => $request->user(),
        ]);

        $fileName = 'sk/'.$sk->id.'-sk-ppdb.pdf';
        \Illuminate\Support\Facades\Storage::disk('public')->put($fileName, $pdf->output());
        $sk->update(['file_path' => $fileName]);

        Pendaftaran::where('gelombang_ppdb_id', $gelombang->id)
            ->where('lembaga_id', $request->user()->lembaga_id)
            ->whereIn('status', ['diterima', 'ditolak'])
            ->update(['sk_ppdb_id' => $sk->id]);

        return redirect()->route('admin.spmb-pendaftaran.index')->with('status', 'SK berhasil diterbitkan.');
    }
}
```

- [ ] **Step 5: Create the PDF template**

Create `resources/views/pdf/sk-ppdb.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        h1 { font-size: 16px; text-align: center; }
        h2 { font-size: 13px; text-align: center; font-weight: normal; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; }
        .ttd { margin-top: 60px; text-align: right; }
    </style>
</head>
<body>
    <h1>SURAT KEPUTUSAN PENETAPAN HASIL PPDB</h1>
    <h2>Nomor: {{ $sk->nomor_sk }}</h2>
    <p>{{ $lembaga->nama }} &mdash; {{ $gelombang->nama }}</p>
    <p>Tanggal Terbit: {{ $sk->tanggal_terbit->translatedFormat('d F Y') }}</p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Kode Pendaftaran</th>
                <th>Nama Calon Murid</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pendaftaranFinal as $index => $pendaftaran)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $pendaftaran->kode_pendaftaran }}</td>
                    <td>{{ $pendaftaran->calonMurid->nama_lengkap }}</td>
                    <td>{{ $pendaftaran->status === 'diterima' ? 'Diterima' : 'Ditolak' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="ttd">
        <p>Ditetapkan oleh,</p>
        <p style="margin-top: 50px;"><strong>{{ $diterbitkanOleh->name }}</strong></p>
    </div>
</body>
</html>
```

- [ ] **Step 6: Create the "Terbitkan SK" form view**

Create `resources/views/admin/spmb-pendaftaran/terbitkan-sk.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">SPMB</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Terbitkan SK Penetapan Hasil</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl space-y-6">
        <x-panel class="p-6">
            <form method="GET" action="{{ route('admin.sk-ppdb.create') }}" class="flex items-end gap-3">
                <div class="flex-1">
                    <x-input-label value="Pilih Gelombang" />
                    <select name="gelombang_ppdb_id" onchange="this.form.submit()" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        <option value="">Pilih...</option>
                        @foreach ($gelombangList as $gelombang)
                            <option value="{{ $gelombang->id }}" @selected($gelombangTerpilih?->id === $gelombang->id)>{{ $gelombang->nama }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </x-panel>

        @if ($gelombangTerpilih)
            <x-panel class="p-6">
                @if ($ringkasan['belum_final'] > 0)
                    <div class="mb-4 rounded-xl bg-signal-amber/10 p-4 text-sm text-signal-amber">
                        {{ $ringkasan['belum_final'] }} dari {{ $ringkasan['total'] }} pendaftaran belum punya keputusan final dan tidak akan tercantum di SK ini. Anda tetap bisa menerbitkan SK untuk yang sudah final, lalu menerbitkan SK susulan nanti.
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.sk-ppdb.store') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="gelombang_ppdb_id" value="{{ $gelombangTerpilih->id }}">
                    <div>
                        <x-input-label value="Nomor SK" />
                        <x-text-input type="text" name="nomor_sk" value="{{ old('nomor_sk') }}" class="mt-1.5" placeholder="421.3/SK-PPDB.001/2026" required />
                        <x-input-error :messages="$errors->get('nomor_sk')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label value="Tanggal Terbit" />
                        <x-text-input type="date" name="tanggal_terbit" value="{{ old('tanggal_terbit', now()->toDateString()) }}" class="mt-1.5" required />
                        <x-input-error :messages="$errors->get('tanggal_terbit')" class="mt-1.5" />
                    </div>
                    <p class="text-sm text-slate">{{ $ringkasan['final'] }} pendaftaran akan tercantum di SK ini.</p>
                    <x-primary-button>Terbitkan SK</x-primary-button>
                </form>
            </x-panel>
        @endif
    </div>
</x-app-layout>
```

- [ ] **Step 7: Add a "Terbitkan SK" link from the index page**

Modify `resources/views/admin/spmb-pendaftaran/index.blade.php` — add a link in the header slot area, right after the `<h2>`:

```blade
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">SPMB</p>
                <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Verifikasi &amp; Keputusan Pendaftaran</h2>
            </div>
            @can('spmb-pendaftaran.terbitkan-sk')
                <x-link-button variant="ghost" href="{{ route('admin.sk-ppdb.create') }}">Terbitkan SK</x-link-button>
            @endcan
            @can('spmb-pendaftaran.nilai-seleksi')
                <x-link-button variant="ghost" href="{{ route('admin.spmb-pendaftaran.nilai-massal') }}">Input Nilai Massal</x-link-button>
            @endcan
        </div>
    </x-slot>
```

(Replace the existing single-line `<x-slot name="header">...` block from Task 1 with this — the rest of the file is unchanged.)

- [ ] **Step 8: Run the tests to confirm they pass**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/Admin/SkPpdbTest.php`
Expected: PASS (4 tests).

- [ ] **Step 9: Run the full suite and commit**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test`
Expected: 250 (after Task 3) + 4 (new) = 254 passed, 0 failures.

```bash
git add app/Http/Controllers/Admin/SkPpdbController.php resources/views/pdf/sk-ppdb.blade.php resources/views/admin/spmb-pendaftaran/terbitkan-sk.blade.php resources/views/admin/spmb-pendaftaran/index.blade.php routes/admin.php tests/Feature/Admin/SkPpdbTest.php
git commit -m "feat: add M3 batch SK PDF generation, linking every finalized pendaftaran to it"
```

---

### Task 5: Public integration + dummy data for manual testing

**Files:**
- Modify: `resources/views/pdf/bukti-pendaftaran.blade.php`
- Test: `tests/Feature/Spmb/BuktiPendaftaranSkReferenceTest.php`
- Create: `database/seeders/M3DemoDataSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Unit/M3DemoDataSeederTest.php`

**Interfaces:**
- Consumes: `Pendaftaran.sk_ppdb_id`/`skPpdb` relation (Plan 1, Task 4), the already-existing SMP/SMA demo lembaga from `DemoDataSeeder` (M2).
- Produces: an updated public PDF showing the SK reference line, and a fully-seeded, immediately-manually-testable set of M3 demo data.

- [ ] **Step 1: Write the failing test for the SK reference line**

Create `tests/Feature/Spmb/BuktiPendaftaranSkReferenceTest.php`:

```php
<?php

use App\Models\CalonMurid;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\SkPpdb;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;

function buatPendaftaranUntukBukti(string $status, bool $dengan_sk): Pendaftaran
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now()->subDay(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 40,
    ]);
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id]);
    $pendaftaran = Pendaftaran::create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-'.random_int(10000, 99999), 'email_pendaftaran' => 'wali@example.test',
        'status' => $status, 'submitted_at' => now(),
    ]);

    if ($dengan_sk) {
        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $sk = SkPpdb::create([
            'gelombang_ppdb_id' => $gelombang->id, 'lembaga_id' => $lembaga->id,
            'nomor_sk' => '421.3/SK-PPDB.001/2026', 'tanggal_terbit' => now()->toDateString(),
            'diterbitkan_oleh_user_id' => $user->id, 'file_path' => 'sk/1.pdf',
        ]);
        $pendaftaran->update(['sk_ppdb_id' => $sk->id]);
    }

    return $pendaftaran->fresh();
}

it('shows the sk reference line when the pendaftaran is linked to a sk_ppdb', function () {
    $pendaftaran = buatPendaftaranUntukBukti('diterima', dengan_sk: true);

    $html = view('pdf.bukti-pendaftaran', ['lembaga' => $pendaftaran->lembaga, 'pendaftaran' => $pendaftaran])->render();

    expect($html)->toContain('421.3/SK-PPDB.001/2026');
    expect($html)->toContain('Ditetapkan berdasarkan SK');
});

it('does not show the sk reference line when the decision is final but no sk has been issued yet', function () {
    $pendaftaran = buatPendaftaranUntukBukti('diterima', dengan_sk: false);

    $html = view('pdf.bukti-pendaftaran', ['lembaga' => $pendaftaran->lembaga, 'pendaftaran' => $pendaftaran])->render();

    expect($html)->not->toContain('Ditetapkan berdasarkan SK');
});

it('does not show the sk reference line while still menunggu_verifikasi', function () {
    $pendaftaran = buatPendaftaranUntukBukti('menunggu_verifikasi', dengan_sk: false);

    $html = view('pdf.bukti-pendaftaran', ['lembaga' => $pendaftaran->lembaga, 'pendaftaran' => $pendaftaran])->render();

    expect($html)->not->toContain('Ditetapkan berdasarkan SK');
});
```

- [ ] **Step 2: Run the test to confirm it fails**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/Spmb/BuktiPendaftaranSkReferenceTest.php`
Expected: FAIL — the first test fails (line not present yet); the other two already pass trivially (nothing to show).

- [ ] **Step 3: Update the PDF template**

Modify `resources/views/pdf/bukti-pendaftaran.blade.php` — replace the closing of the `<table>` block to add a conditional row:

```blade
        <tr><td class="label">Tanggal Submit</td><td>{{ $pendaftaran->submitted_at->format('d F Y H:i') }}</td></tr>
        <tr><td class="label">Status</td><td>{{ $pendaftaran->status }}</td></tr>
    </table>

    @if ($pendaftaran->sk_ppdb_id)
        <p style="margin-top: 16px;">Ditetapkan berdasarkan SK No. {{ $pendaftaran->skPpdb->nomor_sk }} tanggal {{ $pendaftaran->skPpdb->tanggal_terbit->translatedFormat('d F Y') }}.</p>
    @endif
</body>
```

- [ ] **Step 4: Run the test to confirm it passes**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/Spmb/BuktiPendaftaranSkReferenceTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Write the failing test for the dummy-data seeder**

Create `tests/Unit/M3DemoDataSeederTest.php`:

```php
<?php

use App\Models\DokumenPendaftaran;
use App\Models\HasilSeleksi;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\SkPpdb;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('seeds a spread of pendaftaran states across smp and sma for manual M3 testing', function () {
    $this->seed(DatabaseSeeder::class);

    $smp = Lembaga::where('npsn', '20223344')->firstOrFail();
    $sma = Lembaga::where('npsn', '20223355')->firstOrFail();

    foreach ([$smp, $sma] as $lembaga) {
        $pendaftaranLembaga = Pendaftaran::where('lembaga_id', $lembaga->id)->get();
        expect($pendaftaranLembaga->count())->toBeGreaterThanOrEqual(3);

        expect($pendaftaranLembaga->where('status', 'menunggu_verifikasi')->count())->toBeGreaterThanOrEqual(1);
        expect($pendaftaranLembaga->where('status', 'diterima')->count())->toBeGreaterThanOrEqual(1);
        expect($pendaftaranLembaga->where('status', 'ditolak')->count())->toBeGreaterThanOrEqual(1);

        $dengan_dokumen_campuran = $pendaftaranLembaga->first(function (Pendaftaran $p) {
            $statuses = DokumenPendaftaran::where('pendaftaran_id', $p->id)->pluck('status_verifikasi');

            return $statuses->contains('diterima') && $statuses->contains('ditolak');
        });
        expect($dengan_dokumen_campuran)->not->toBeNull();

        expect(HasilSeleksi::whereIn('pendaftaran_id', $pendaftaranLembaga->pluck('id'))->exists())->toBeTrue();
    }

    expect(SkPpdb::count())->toBeGreaterThanOrEqual(1);
    $pendaftaranDenganSk = Pendaftaran::whereNotNull('sk_ppdb_id')->first();
    expect($pendaftaranDenganSk)->not->toBeNull();
    expect($pendaftaranDenganSk->status)->toBeIn(['diterima', 'ditolak']);
});

it('is idempotent when the full DatabaseSeeder is run twice', function () {
    $this->seed(DatabaseSeeder::class);
    $countFirstRun = Pendaftaran::count();

    $this->seed(DatabaseSeeder::class);

    expect(Pendaftaran::count())->toBe($countFirstRun);
});
```

- [ ] **Step 6: Run the test to confirm it fails**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Unit/M3DemoDataSeederTest.php`
Expected: FAIL — `M3DemoDataSeeder` doesn't exist yet, `DatabaseSeeder` doesn't call it.

- [ ] **Step 7: Create `M3DemoDataSeeder`**

Create `database/seeders/M3DemoDataSeeder.php`. This seeds, for BOTH the SMP and SMA demo lembaga (already created by `DemoDataSeeder`), a spread of `CalonMurid`+`Pendaftaran` rows covering every M3 state a manual tester needs: at least one still `menunggu_verifikasi` with a mix of verified/rejected/unverified documents, at least one `diterima` with nilai entered and linked to an issued SK, and at least one `ditolak`. Uses each lembaga's active tahun ajaran's "Reguler" jalur (already seeded with documents/formulir fields by `DemoDataSeeder`) and its currently-open gelombang (already fixed to relative dates in an earlier M2 fix).

```php
<?php

namespace Database\Seeders;

use App\Models\CalonMurid;
use App\Models\DokumenPendaftaran;
use App\Models\GelombangPpdb;
use App\Models\HasilSeleksi;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\SeleksiPpdb;
use App\Models\SkPpdb;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data demo M3 (Verifikasi & Keputusan): untuk setiap lembaga demo (SMP & SMA,
 * dibuat oleh DemoDataSeeder), menambahkan sebaran pendaftaran yang mencakup
 * setiap kondisi yang perlu diuji manual: menunggu verifikasi dengan dokumen
 * campuran (sebagian terverifikasi, sebagian ditolak, sebagian belum), diterima
 * dengan nilai terisi dan SK sudah terbit, dan ditolak. Supaya M3 langsung bisa
 * diuji manual tanpa setup tambahan setelah migrate:fresh --seed.
 */
class M3DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['20223344', '20223355'] as $npsn) {
            $lembaga = Lembaga::where('npsn', $npsn)->first();

            if (! $lembaga) {
                continue;
            }

            $this->seedUntukLembaga($lembaga);
        }
    }

    private function seedUntukLembaga(Lembaga $lembaga): void
    {
        // Guards the whole method as a single idempotency check, rather than converting every
        // ::create() below to firstOrCreate/updateOrCreate individually: hasil_seleksi and
        // sk_ppdb both have real unique constraints (pendaftaran_id+seleksi_ppdb_id, and
        // lembaga_id+nomor_sk respectively), so a second unconditional ::create() call for
        // either would throw a QueryException, not silently duplicate. One early-return here
        // is simpler and safer than getting every individual sub-step's idempotency right.
        if (Pendaftaran::where('lembaga_id', $lembaga->id)->where('kode_pendaftaran', 'like', 'REG-DEMO-'.$lembaga->id.'-%')->exists()) {
            return;
        }

        $tahunAjaranAktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();

        if (! $tahunAjaranAktif) {
            return;
        }

        $jalur = JalurPpdb::where('lembaga_id', $lembaga->id)
            ->where('tahun_ajaran_id', $tahunAjaranAktif->id)
            ->where('nama', 'Reguler')
            ->first();

        $gelombang = GelombangPpdb::where('lembaga_id', $lembaga->id)
            ->where('tahun_ajaran_id', $tahunAjaranAktif->id)
            ->where('tanggal_buka', '<=', now())
            ->where('tanggal_tutup', '>=', now())
            ->first();

        if (! $jalur || ! $gelombang) {
            return;
        }

        $staf = User::where('lembaga_id', $lembaga->id)->first();
        $syaratDokumen = $jalur->dokumenSyarat;
        $seleksiList = SeleksiPpdb::where('jalur_ppdb_id', $jalur->id)->where('gelombang_ppdb_id', $gelombang->id)->get();

        // 1. Menunggu verifikasi — dokumen campuran (terverifikasi, ditolak, belum diverifikasi).
        $menunggu = $this->buatPendaftaran($lembaga, $tahunAjaranAktif, $jalur, $gelombang, 'Calon Menunggu Verifikasi', 'wali.menunggu@example.test');
        foreach ($syaratDokumen as $index => $syarat) {
            $status = match ($index % 3) {
                0 => 'diterima',
                1 => 'ditolak',
                default => 'belum_diverifikasi',
            };
            DokumenPendaftaran::create([
                'pendaftaran_id' => $menunggu->id, 'dokumen_syarat_ppdb_id' => $syarat->id,
                'file_path' => 'demo/dokumen-contoh.pdf', 'nama_file_asli' => $syarat->nama_dokumen.'.pdf',
                'mime_type' => 'application/pdf', 'ukuran_bytes' => 102400,
                'status_verifikasi' => $status,
                'catatan_verifikasi' => $status === 'ditolak' ? 'Contoh catatan: berkas kurang jelas, mohon diunggah ulang.' : null,
                'diverifikasi_oleh_user_id' => $status !== 'belum_diverifikasi' ? $staf?->id : null,
                'diverifikasi_pada' => $status !== 'belum_diverifikasi' ? now() : null,
            ]);
        }

        // 2. Diterima — dokumen lengkap, nilai terisi, akan dicakup SK di bawah.
        $diterima = $this->buatPendaftaran($lembaga, $tahunAjaranAktif, $jalur, $gelombang, 'Calon Diterima', 'wali.diterima@example.test');
        foreach ($syaratDokumen as $syarat) {
            DokumenPendaftaran::create([
                'pendaftaran_id' => $diterima->id, 'dokumen_syarat_ppdb_id' => $syarat->id,
                'file_path' => 'demo/dokumen-contoh.pdf', 'nama_file_asli' => $syarat->nama_dokumen.'.pdf',
                'mime_type' => 'application/pdf', 'ukuran_bytes' => 102400,
                'status_verifikasi' => 'diterima', 'diverifikasi_oleh_user_id' => $staf?->id, 'diverifikasi_pada' => now(),
            ]);
        }
        foreach ($seleksiList as $seleksi) {
            HasilSeleksi::create([
                'pendaftaran_id' => $diterima->id, 'seleksi_ppdb_id' => $seleksi->id,
                'nilai' => random_int(75, 95), 'dinilai_oleh_user_id' => $staf?->id, 'dinilai_pada' => now(),
            ]);
        }
        $diterima->update([
            'status' => 'diterima', 'catatan_keputusan' => 'Nilai dan kelengkapan dokumen memenuhi syarat.',
            'ditetapkan_oleh_user_id' => $staf?->id, 'ditetapkan_pada' => now(),
        ]);

        // 3. Ditolak.
        $ditolak = $this->buatPendaftaran($lembaga, $tahunAjaranAktif, $jalur, $gelombang, 'Calon Ditolak', 'wali.ditolak@example.test');
        foreach ($seleksiList as $seleksi) {
            HasilSeleksi::create([
                'pendaftaran_id' => $ditolak->id, 'seleksi_ppdb_id' => $seleksi->id,
                'nilai' => random_int(30, 55), 'dinilai_oleh_user_id' => $staf?->id, 'dinilai_pada' => now(),
            ]);
        }
        $ditolak->update([
            'status' => 'ditolak', 'catatan_keputusan' => 'Nilai belum memenuhi kriteria kelulusan minimum.',
            'ditetapkan_oleh_user_id' => $staf?->id, 'ditetapkan_pada' => now(),
        ]);

        // Terbitkan satu SK mencakup kedua pendaftaran yang sudah final (diterima + ditolak),
        // supaya "download bukti dengan referensi SK" langsung bisa diuji di halaman publik.
        if ($staf) {
            $sk = SkPpdb::create([
                'gelombang_ppdb_id' => $gelombang->id, 'lembaga_id' => $lembaga->id,
                'nomor_sk' => '421.3/SK-PPDB.DEMO-'.$lembaga->id.'/2026',
                'tanggal_terbit' => now()->toDateString(),
                'diterbitkan_oleh_user_id' => $staf->id, 'file_path' => 'demo/sk-contoh.pdf',
            ]);
            Pendaftaran::whereIn('id', [$diterima->id, $ditolak->id])->update(['sk_ppdb_id' => $sk->id]);
        }
    }

    private function buatPendaftaran(
        Lembaga $lembaga,
        TahunAjaran $tahunAjaran,
        JalurPpdb $jalur,
        GelombangPpdb $gelombang,
        string $namaCalon,
        string $email
    ): Pendaftaran {
        // The seedUntukLembaga() guard above ensures this whole method only ever runs once
        // per lembaga across any number of DatabaseSeeder runs, so plain create() here is
        // safe — no need for firstOrCreate's search-key complexity for a single-shot insert.
        $nik = (string) random_int(3200000000000000, 3299999999999999);

        $calonMurid = CalonMurid::create([
            'yayasan_id' => $lembaga->yayasan_id,
            'nik' => $nik,
            'nama_lengkap' => $namaCalon.' ('.$lembaga->nama.')',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => now()->subYears(13)->toDateString(),
            'agama' => 'Islam',
        ]);

        return Pendaftaran::create([
            'calon_murid_id' => $calonMurid->id,
            'lembaga_id' => $lembaga->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'jalur_ppdb_id' => $jalur->id,
            'gelombang_ppdb_id' => $gelombang->id,
            'kode_pendaftaran' => 'REG-DEMO-'.$lembaga->id.'-'.random_int(10000, 99999),
            'email_pendaftaran' => $email,
            'submitted_at' => now()->subDays(random_int(1, 5)),
        ]);
    }
}
```

Note: `JalurPpdb` already has this relation under the name `dokumenSyarat()` (verified in `app/Models/JalurPpdb.php`, ordered by `urutan`, alongside `formulirField()` and `seleksi()`) — no model change needed, the seeder code above already calls `$jalur->dokumenSyarat`.

- [ ] **Step 8: Wire the seeder into `DatabaseSeeder`**

Modify `database/seeders/DatabaseSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            YayasanSeeder::class,
            JabatanTambahanMasterSeeder::class,
            DemoDataSeeder::class,
            M3DemoDataSeeder::class,
        ]);
    }
}
```

- [ ] **Step 9: Run the tests to confirm they pass**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Unit/M3DemoDataSeederTest.php`
Expected: PASS (2 tests).

- [ ] **Step 10: Run the full suite, run a real `migrate:fresh --seed`, and commit**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test`
Expected: 254 (after Task 4) + 3 (Step 4) + 2 (Step 9) = 259 passed, 0 failures.

Then actually run the seeder against the real dev database (not just the test suite's in-memory/transactional runs) so it's genuinely ready for the user's next manual test session:

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan migrate:fresh --seed`
Expected: completes with no errors; confirm via `php artisan tinker --execute="echo App\Models\Pendaftaran::count();"` that a non-zero, multi-state set of pendaftaran now exists for both SMP and SMA.

```bash
git add resources/views/pdf/bukti-pendaftaran.blade.php tests/Feature/Spmb/BuktiPendaftaranSkReferenceTest.php database/seeders/M3DemoDataSeeder.php database/seeders/DatabaseSeeder.php tests/Unit/M3DemoDataSeederTest.php
git commit -m "feat: reference issued SK in the public bukti pendaftaran PDF, seed M3 demo data for manual testing"
```

---

## Post-Plan Note

After this plan, M3 is feature-complete end-to-end: verify documents → enter scores → decide → issue SK → the public status page and bukti-pendaftaran PDF both reflect the final outcome. Combined with M2, the full SPMB flow (M0–M3 per the PRD's own stated milestone) is demoable without needing Keuangan (M4–M7). No further plan is implied by this one; the next module to brainstorm is whatever the user chooses after manual testing this.
