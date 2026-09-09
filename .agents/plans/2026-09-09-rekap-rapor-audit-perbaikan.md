# Audit & Perbaikan Menu Rekap Rapor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Perbaiki ambiguitas default filter, tambahkan badge scope, konteks lembaga, dan kelengkapan dokumen PDF di menu Rekap Rapor (`admin.rapor.index`), plus adopsi komponen `<x-select>` dan reorder dropdown filter.

**Architecture:** Perubahan terkonsentrasi di `RaporController` + 3 view (`rapor/index.blade.php`, `rapor/_hasil.blade.php`, `pdf/rekap-rapor.blade.php`) + 1 komponen bersama (`components/select.blade.php`). Fondasi teknis: `ResolveLembagaScopeTrait::resolveActiveLembagaId()` dan `TenantScope` (sama seperti perbaikan TP sebelumnya).

**Tech Stack:** Laravel 12, Pest, Blade, Eloquent, Alpine.js + TomSelect (tanpa Livewire/Inertia).

## Global Constraints

- Item A dan Item B (guard mode agregat + badge scope) HARUS diimplementasikan dalam **1 task yang sama** — kode Item A memanggil `scopeHeaderData()` yang baru didefinisikan Item B. Memisahnya jadi 2 task akan menghasilkan kode yang gagal di tengah jalan.
- Item C dan Item D SAMA-SAMA butuh Task 1 (Item A+B) selesai lebih dulu — keduanya memakai variabel `$isYayasan`/`$activeLembaga` dari `scopeHeaderData()`. Eager-load `with('lembaga')` pada `$kelasList` SUDAH digabung ke kode Task 1 — Task Item D (Task 3) TIDAK BOLEH mengubah controller lagi, murni perubahan view.
- Item H butuh Item C (Task 2) selesai lebih dulu — markup dropdown Tahun Ajaran yang dimigrasikan ke `<x-select>` adalah versi HASIL Item C (dengan placeholder kosong + suffix lembaga tervalidasi), bukan versi asli sebelum spec ini.
- Item I butuh Item H selesai lebih dulu — reorder blok `<x-select>` hasil Item H, bukan blok `<select>` asli.
- Item H mengubah `resources/views/components/select.blade.php` yang JUGA dipakai 9 file lain di luar Rekap Rapor (Karyawan, Roles, Siswa, Users, Kasus) — ini DAMPAK DISENGAJA, sudah direkam di `.ai/rules/components.md`, BUKAN efek samping tak terduga. Implementer harus memverifikasi 1-2 dari 9 file itu tetap render benar setelah perubahan.
- Item G (update baris basi di `.agents/logs/2026-09-07-audit-scope-yayasan-lembaga-sidebar.md`) WAJIB dikerjakan PALING TERAKHIR (Task 7), setelah SEMUA item lain terverifikasi lulus test — bukan janji, tapi fakta terverifikasi.
- Test file SATU-SATUNYA untuk seluruh plan ini: `tests/Feature/Admin/RaporControllerTest.php` (23 test existing SUDAH ADA sebelum plan ini). Pakai HANYA helper existing `actingAsRaporViewer(Lembaga $lembaga): User` (lembaga-scope) — untuk skenario yayasan-scope, ikuti pola INLINE yang sudah dipakai test existing baris 329-333 (`Role::firstOrCreate([...], ['scope_level' => 'yayasan'])` dst), JANGAN buat helper baru.
- **3 test existing WAJIB tetap lulus TANPA perubahan assertion sama sekali** (regresi murni, jangan disentuh):
  - `it('defaults to the active tahun ajaran, first kelas, and latest semester when none is selected', ...)` (baris 79-95) — lembaga-scope, tidak terpengaruh guard Item A.
  - `it('does not mix a kelas and semester from different lembaga within the same yayasan on the index recap', ...)` (baris 318-342) — SUDAH menguji deep-link `kelas_id`+`semester_id` lintas-lembaga dari aktor yayasan mode agregat, SUDAH cukup untuk membuktikan Item A tidak merusak jalur deep-link. JANGAN tulis test baru yang menduplikasi skenario ini.
  - `it('labels tahun ajaran options with lembaga name when yayasan scope has no active lembaga selected', ...)` (baris 376-395) dan `it('does not add a lembaga label to tahun ajaran options for a lembaga-scoped viewer', ...)` (baris 397-409) — SUDAH menguji skenario "session kosong sama sekali" untuk Item C. Test BARU Item C HANYA perlu skenario session BERISI tapi INVALID (gap nyata yang belum tercakup).
- Backend inti (`RaporCalculationService`) TIDAK disentuh sama sekali di plan ini.
- Tidak pakai worktree, tidak pindah branch, TIDAK ADA migrasi database.

---

### Task 1: 🔴 Guard Mode Agregat (Item A) + Badge Scope (Item B)

**Files:**
- Modify: `app/Http/Controllers/Admin/RaporController.php`
- Modify: `resources/views/portals/lembaga/akademik/rapor/index.blade.php`
- Test: `tests/Feature/Admin/RaporControllerTest.php`

**Interfaces:**
- Produces: `RaporController::scopeHeaderData(Request $request): array` (private method baru, pola PERSIS sama seperti `KomponenPenilaianController`/`RppController`) — mengembalikan `['isYayasan' => bool, 'activeLembaga' => ?Lembaga]`. Dipakai Task 2 dan Task 3.
- Produces: view `index` dan `_hasil` (cabang ajax) SEKARANG menerima `isYayasan`/`activeLembaga`.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke akhir `tests/Feature/Admin/RaporControllerTest.php`:

```php
it('does not auto-select any tahun ajaran, kelas, or semester for a yayasan actor in aggregate mode with no query string', function () {
    Permission::firstOrCreate(['name' => 'rapor.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin_rapor_aggregate_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['rapor.view']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranAktif = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaranAktif->id]);
    Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranAktif->id]);

    $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $user->assignRole($role);

    $response = $this->actingAs($user)->get(route('admin.rapor.index'));

    $response->assertOk();
    $response->assertViewHas('tahunAjaranId', null);
    $response->assertViewHas('selectedKelas', null);
    $response->assertViewHas('selectedSemester', null);
    $response->assertSee('Silakan Pilih Tahun Ajaran, Kelas, dan Semester');
});

it('still auto-selects tahun ajaran, kelas, and semester for a yayasan actor who has switched into a lembaga', function () {
    Permission::firstOrCreate(['name' => 'rapor.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin_rapor_switched_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['rapor.view']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranAktif = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaranAktif->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranAktif->id]);

    $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $user->assignRole($role);
    session(['active_lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($user)->get(route('admin.rapor.index'));

    $response->assertOk();
    $response->assertViewHas('tahunAjaranId', $tahunAjaranAktif->id);
    $response->assertViewHas('selectedKelas', fn ($k) => $k->id === $kelas->id);
    $response->assertViewHas('selectedSemester', fn ($s) => $s->id === $semester->id);
});

it('shows the scope badge for a yayasan actor, purple in aggregate mode and brand-colored once switched', function () {
    Permission::firstOrCreate(['name' => 'rapor.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin_rapor_badge_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['rapor.view']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMA Badge Test']);
    $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $user->assignRole($role);

    $this->actingAs($user)->get(route('admin.rapor.index'))
        ->assertSee('Semua Lembaga')
        ->assertSee('border-purple-200 bg-purple-50 text-purple-700', false);

    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->get(route('admin.rapor.index'))
        ->assertSee('SMA Badge Test')
        ->assertSee('border-brand-200 bg-brand-50 text-brand-700', false);
});

it('does not show the scope badge for a lembaga-scoped viewer', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $viewer = actingAsRaporViewer($lembaga);

    $this->actingAs($viewer)->get(route('admin.rapor.index'))
        ->assertDontSee('Semua Lembaga');
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="does not auto-select any tahun ajaran|still auto-selects tahun ajaran, kelas, and semester for a yayasan actor who has switched|shows the scope badge for a yayasan actor|does not show the scope badge for a lembaga-scoped viewer" --compact`
Expected: FAIL semua 4 test (guard belum ada, badge belum ada, wording empty-state belum diubah).

- [ ] **Step 3: Implementasi controller**

Di `app/Http/Controllers/Admin/RaporController.php` — ganti (baris 1-20, seluruh blok `use` + deklarasi class):

```php
use App\Domains\Akademik\Services\RaporCalculationService;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\TahunAjaran;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RaporController extends BaseController
{
    use AuthorizesRequests;
```

menjadi:

```php
use App\Domains\Akademik\Services\RaporCalculationService;
use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\TahunAjaran;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RaporController extends BaseController
{
    use AuthorizesRequests;
    use ResolveLembagaScopeTrait;
```

Tambahkan method `scopeHeaderData()` SEBELUM method `index()`:

```php
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

Ganti seluruh method `index()`:

```php
    public function index(Request $request): View|string
    {
        $this->authorize('rapor.view');

        $tahunAjaranId = is_scalar($request->query('tahun_ajaran_id')) ? $request->query('tahun_ajaran_id') : null;
        $kelasIdParam = is_scalar($request->query('kelas_id')) ? $request->query('kelas_id') : null;
        if (! $tahunAjaranId && $kelasIdParam) {
            // Deep link with kelas_id but no tahun_ajaran_id (e.g. a bookmarked/shared URL):
            // derive it from the kelas itself instead of falling back to the active tahun
            // ajaran, which may not be the one the kelas actually belongs to.
            $tahunAjaranId = Kelas::find($kelasIdParam)?->tahun_ajaran_id;
        }
        if (! $tahunAjaranId) {
            $tahunAjaranId = TahunAjaran::where('status_aktif', true)->value('id');
        }

        $kelasList = $tahunAjaranId ? Kelas::where('tahun_ajaran_id', $tahunAjaranId)->orderBy('nama')->get() : collect();
        $semesterList = $tahunAjaranId ? Semester::where('tahun_ajaran_id', $tahunAjaranId)->orderByDesc('id')->get() : collect();

        $kelasId = $kelasIdParam;
        if (! $kelasId || ! $kelasList->contains('id', (int) $kelasId)) {
            $kelasId = $kelasList->first()?->id;
        }
        $semesterId = is_scalar($request->query('semester_id')) ? $request->query('semester_id') : null;
        if (! $semesterId || ! $semesterList->contains('id', (int) $semesterId)) {
            $semesterId = $semesterList->first()?->id;
        }

        $selectedKelas = $kelasId ? Kelas::find($kelasId) : null;
        $selectedSemester = $semesterId ? Semester::find($semesterId) : null;

        $rekap = ($selectedKelas && $selectedSemester)
            ? $this->raporCalculationService->hitungRekapKelas($selectedKelas, $selectedSemester)
            : $this->rekapKosong();

        if ($request->ajax()) {
            return view('portals.lembaga.akademik.rapor._hasil', array_merge([
                'selectedKelas' => $selectedKelas,
                'selectedSemester' => $selectedSemester,
            ], $rekap))->render();
        }

        return view('portals.lembaga.akademik.rapor.index', array_merge([
            'tahunAjaranList' => TahunAjaran::with('lembaga')->orderByDesc('id')->get(),
            'tahunAjaranId' => $tahunAjaranId,
            'kelasList' => $kelasList,
            'semesterList' => $semesterList,
            'selectedKelas' => $selectedKelas,
            'selectedSemester' => $selectedSemester,
        ], $rekap));
    }
```

menjadi:

```php
    public function index(Request $request): View|string
    {
        $this->authorize('rapor.view');

        $isYayasanAggregate = $request->user()->widestScopeLevel() === 'yayasan' && $this->resolveActiveLembagaId($request->user()) === null;

        $tahunAjaranId = is_scalar($request->query('tahun_ajaran_id')) ? $request->query('tahun_ajaran_id') : null;
        $kelasIdParam = is_scalar($request->query('kelas_id')) ? $request->query('kelas_id') : null;
        if (! $tahunAjaranId && $kelasIdParam) {
            // Deep link with kelas_id but no tahun_ajaran_id (e.g. a bookmarked/shared URL):
            // derive it from the kelas itself instead of falling back to the active tahun
            // ajaran, which may not be the one the kelas actually belongs to.
            $tahunAjaranId = Kelas::find($kelasIdParam)?->tahun_ajaran_id;
        }
        if (! $tahunAjaranId && ! $isYayasanAggregate) {
            $tahunAjaranId = TahunAjaran::where('status_aktif', true)->value('id');
        }

        $kelasList = $tahunAjaranId ? Kelas::with('lembaga')->where('tahun_ajaran_id', $tahunAjaranId)->orderBy('nama')->get() : collect();
        $semesterList = $tahunAjaranId ? Semester::where('tahun_ajaran_id', $tahunAjaranId)->orderByDesc('id')->get() : collect();

        $kelasId = $kelasIdParam;
        if (! $kelasId || ! $kelasList->contains('id', (int) $kelasId)) {
            $kelasId = $kelasList->first()?->id;
        }
        $semesterId = is_scalar($request->query('semester_id')) ? $request->query('semester_id') : null;
        if (! $semesterId || ! $semesterList->contains('id', (int) $semesterId)) {
            $semesterId = $semesterList->first()?->id;
        }

        $selectedKelas = $kelasId ? $kelasList->firstWhere('id', (int) $kelasId) : null;
        $selectedSemester = $semesterId ? $semesterList->firstWhere('id', (int) $semesterId) : null;

        $rekap = ($selectedKelas && $selectedSemester)
            ? $this->raporCalculationService->hitungRekapKelas($selectedKelas, $selectedSemester)
            : $this->rekapKosong();

        if ($request->ajax()) {
            return view('portals.lembaga.akademik.rapor._hasil', array_merge([
                'selectedKelas' => $selectedKelas,
                'selectedSemester' => $selectedSemester,
            ], $rekap, $this->scopeHeaderData($request)))->render();
        }

        return view('portals.lembaga.akademik.rapor.index', array_merge([
            'tahunAjaranList' => TahunAjaran::with('lembaga')->orderByDesc('id')->get(),
            'tahunAjaranId' => $tahunAjaranId,
            'kelasList' => $kelasList,
            'semesterList' => $semesterList,
            'selectedKelas' => $selectedKelas,
            'selectedSemester' => $selectedSemester,
        ], $rekap, $this->scopeHeaderData($request)));
    }
```

- [ ] **Step 4: Implementasi view header (badge)**

Di `resources/views/portals/lembaga/akademik/rapor/index.blade.php` — ganti (baris 11-17):

```blade
        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Rekapitulasi Nilai Rapor</h1>
            <p class="text-sm text-gray-500">
                Akademik <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Rekap Rapor</b>
            </p>
        </div>
```

menjadi:

```blade
        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="font-display text-lg font-bold text-gray-900">Rekapitulasi Nilai Rapor</h1>
                @if ($isYayasan ?? false)
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
                        <x-icon name="apartment" class="h-3.5 w-3.5" />
                        {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
                    </span>
                @endif
            </div>
            <p class="text-sm text-gray-500">
                Akademik <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Rekap Rapor</b>
            </p>
        </div>
```

- [ ] **Step 5: Sesuaikan wording empty-state (prasyarat test Step 1 lulus)**

Di `resources/views/portals/lembaga/akademik/rapor/_hasil.blade.php` — ganti blok `@else` (baris 141-150):

```blade
    @else
        <div class="rounded-2xl border border-dashed border-gray-300 p-12 text-center text-gray-400 space-y-3 bg-white">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                <x-icon name="assessment" class="h-7 w-7" />
            </div>
            <div>
                <p class="text-base font-semibold text-gray-700">Silakan Pilih Kelas dan Semester</p>
                <p class="text-xs text-gray-400 max-w-sm mx-auto mt-0.5">Pilih parameter kelas di bagian atas untuk menampilkan rekapitulasi nilai rapor peserta didik.</p>
            </div>
        </div>
    @endif
```

menjadi:

```blade
    @else
        <div class="rounded-2xl border border-dashed border-gray-300 p-12 text-center text-gray-400 space-y-3 bg-white">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                <x-icon name="assessment" class="h-7 w-7" />
            </div>
            <div>
                <p class="text-base font-semibold text-gray-700">Silakan Pilih Tahun Ajaran, Kelas, dan Semester</p>
                <p class="text-xs text-gray-400 max-w-sm mx-auto mt-0.5">Pilih parameter di bagian atas untuk menampilkan rekapitulasi nilai rapor peserta didik.</p>
            </div>
        </div>
    @endif
```

- [ ] **Step 6: Jalankan test Step 1 lagi, pastikan lulus**

Run: `php artisan test --filter="does not auto-select any tahun ajaran|still auto-selects tahun ajaran, kelas, and semester for a yayasan actor who has switched|shows the scope badge for a yayasan actor|does not show the scope badge for a lembaga-scoped viewer" --compact`
Expected: PASS semua 4 test.

- [ ] **Step 7: Jalankan SELURUH test file sebagai regresi**

Run: `php artisan test tests/Feature/Admin/RaporControllerTest.php --compact`
Expected: PASS semua (termasuk 23 test existing + 4 test baru = 27 test). **STOP TOTAL kalau ada satupun yang gagal** — ini task paling sensitif di plan ini (mengubah `index()` sepenuhnya).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/RaporController.php resources/views/portals/lembaga/akademik/rapor/index.blade.php resources/views/portals/lembaga/akademik/rapor/_hasil.blade.php tests/Feature/Admin/RaporControllerTest.php
git commit -m "fix(rapor): guard default filter mode agregat + tambah badge scope isYayasan/activeLembaga"
```

---

### Task 2: 🟡 Perbaiki Dropdown Tahun Ajaran & Wording (Item C)

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/rapor/index.blade.php`
- Test: `tests/Feature/Admin/RaporControllerTest.php`

**Interfaces:**
- Consumes: `$isYayasan`/`$activeLembaga` dari Task 1.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('shows the lembaga suffix even when session active_lembaga_id is stale (belongs to a different yayasan)', function () {
    Permission::firstOrCreate(['name' => 'rapor.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin_rapor_stale_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['rapor.view']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMP Stale Session Test']);
    TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2027/2028']);

    $yayasanLain = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);

    $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $user->assignRole($role);
    // Session berisi lembaga milik YAYASAN LAIN -- basi/tidak valid untuk aktor ini.
    session(['active_lembaga_id' => $lembagaLain->id]);

    $response = $this->actingAs($user)->get(route('admin.rapor.index'));

    $response->assertOk();
    $response->assertSee('2027/2028 — SMP Stale Session Test');
});

it('shows a blank placeholder option in the tahun ajaran dropdown when nothing is selected', function () {
    Permission::firstOrCreate(['name' => 'rapor.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin_rapor_placeholder_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['rapor.view']);

    $yayasan = Yayasan::factory()->create();
    $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $user->assignRole($role);

    $this->actingAs($user)->get(route('admin.rapor.index'))
        ->assertSee('— Pilih Tahun Ajaran —');
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="shows the lembaga suffix even when session active_lembaga_id is stale|shows a blank placeholder option" --compact`
Expected: FAIL keduanya (raw session check belum diganti, placeholder belum ada).

- [ ] **Step 3: Implementasi**

Di `resources/views/portals/lembaga/akademik/rapor/index.blade.php` — ganti dropdown Tahun Ajaran (baris 34-38):

```blade
                        <select x-ref="tahunAjaranSelect" x-init="initTahunAjaranSelect($refs.tahunAjaranSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-bold text-gray-900 transition focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($tahunAjaranList as $tahunAjaran)
                                <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}@if (Auth::user()->widestScopeLevel() === 'yayasan' && ! session('active_lembaga_id')) — {{ $tahunAjaran->lembaga->nama }}@endif</option>
                            @endforeach
                        </select>
```

menjadi:

```blade
                        <select x-ref="tahunAjaranSelect" x-init="initTahunAjaranSelect($refs.tahunAjaranSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-bold text-gray-900 transition focus:border-brand-500 focus:ring-brand-500">
                            <option value="">— Pilih Tahun Ajaran —</option>
                            @foreach ($tahunAjaranList as $tahunAjaran)
                                <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}{{ ($isYayasan ?? false) && ! ($activeLembaga ?? null) ? ' — '.($tahunAjaran->lembaga->nama ?? '-') : '' }}</option>
                            @endforeach
                        </select>
```

- [ ] **Step 4: Jalankan test Step 1 lagi, pastikan lulus**

Run: `php artisan test --filter="shows the lembaga suffix even when session active_lembaga_id is stale|shows a blank placeholder option" --compact`
Expected: PASS keduanya.

- [ ] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/RaporControllerTest.php --compact`
Expected: PASS semua (termasuk `it('labels tahun ajaran options with lembaga name when yayasan scope has no active lembaga selected', ...)` dan `it('does not add a lembaga label to tahun ajaran options for a lembaga-scoped viewer', ...)` — 2 test existing yang HARUS tetap lulus tanpa perubahan assertion).

- [ ] **Step 6: Commit**

```bash
git add resources/views/portals/lembaga/akademik/rapor/index.blade.php tests/Feature/Admin/RaporControllerTest.php
git commit -m "fix(rapor): dropdown Tahun Ajaran pakai activeLembaga tervalidasi (bukan session mentah) + placeholder kosong"
```

---

### Task 3: 🟡 Konteks Kelas/Lembaga di Hasil Rekap (Item D)

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/rapor/_hasil.blade.php`
- Test: `tests/Feature/Admin/RaporControllerTest.php`

**Interfaces:**
- Consumes: `$isYayasan`/`$activeLembaga` dari Task 1, `$selectedKelas->lembaga` (eager-loaded di Task 1, TIDAK PERLU query tambahan).

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('shows a context header with kelas, semester, and lembaga badge for a yayasan actor in aggregate mode', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Konteks Rekap']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil']);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '5B']);

    Permission::firstOrCreate(['name' => 'rapor.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin_rapor_context_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['rapor.view']);
    $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $user->assignRole($role);

    $response = $this->actingAs($user)->get(route('admin.rapor.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertSee('5B');
    $response->assertSee('SD Konteks Rekap');
});

it('does not show a lembaga badge in the context header for a lembaga-scoped viewer (regresi)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Konteks Lembaga Scope']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '6A']);
    $viewer = actingAsRaporViewer($lembaga);

    $response = $this->actingAs($viewer)->get(route('admin.rapor.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertSee('6A');
    $response->assertDontSee('SD Konteks Lembaga Scope');
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal (test kedua kemungkinan sudah lulus sebagian)**

Run: `php artisan test --filter="shows a context header with kelas, semester, and lembaga badge|does not show a lembaga badge in the context header" --compact`
Expected: test pertama FAIL (judul konteks belum ada). Test kedua BISA lulus sebagian (nama kelas mungkin sudah kebetulan tampil di tempat lain), pastikan setelah Step 3 keduanya PASS dengan makna yang benar.

- [ ] **Step 3: Implementasi**

Di `resources/views/portals/lembaga/akademik/rapor/_hasil.blade.php` — ganti (baris 1-4):

```blade
<div class="space-y-4">
    @if ($selectedKelas && $selectedSemester)
        <!-- Class Stat Summary -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
```

menjadi:

```blade
<div class="space-y-4">
    @if ($selectedKelas && $selectedSemester)
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="font-display font-bold text-gray-900">{{ $selectedKelas->nama }}</span>
            <span class="text-gray-300">&bull;</span>
            <span class="text-gray-600">{{ $selectedSemester->nama }} — {{ $selectedSemester->tahunAjaran->nama }}</span>
            @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
                <span class="inline-flex items-center gap-1 rounded-full bg-purple-50 px-2.5 py-0.5 text-xs font-medium text-purple-700">
                    <x-icon name="apartment" class="h-3 w-3" />
                    {{ $selectedKelas->lembaga->nama ?? '-' }}
                </span>
            @endif
        </div>

        <!-- Class Stat Summary -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
```

- [ ] **Step 4: Jalankan test Step 1 lagi, pastikan lulus**

Run: `php artisan test --filter="shows a context header with kelas, semester, and lembaga badge|does not show a lembaga badge in the context header" --compact`
Expected: PASS keduanya.

- [ ] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/RaporControllerTest.php --compact`
Expected: PASS semua.

- [ ] **Step 6: Commit**

```bash
git add resources/views/portals/lembaga/akademik/rapor/_hasil.blade.php tests/Feature/Admin/RaporControllerTest.php
git commit -m "feat(rapor): tampilkan judul konteks kelas/semester + badge lembaga di hasil rekap saat mode agregat"
```

---

### Task 4: 🟡 Nama Lembaga di PDF Cetak (Item E)

**Files:**
- Modify: `resources/views/pdf/rekap-rapor.blade.php`
- Test: `tests/Feature/Admin/RaporControllerTest.php`

**Interfaces:**
- Tidak ada interface baru. Tidak bergantung pada task lain.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('shows the lembaga name in the printed pdf subtitle', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMK Cetak PDF Test']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $rekap = app(RaporCalculationService::class)->hitungRekapKelas($kelas, $semester);

    $html = view('pdf.rekap-rapor', array_merge(['selectedKelas' => $kelas, 'selectedSemester' => $semester], $rekap))->render();

    expect($html)->toContain('SMK Cetak PDF Test');
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="shows the lembaga name in the printed pdf subtitle" --compact`
Expected: FAIL (nama lembaga belum ada di PDF).

- [ ] **Step 3: Implementasi**

Di `resources/views/pdf/rekap-rapor.blade.php` — ganti (baris 20-21):

```blade
    <h1>Rekap Nilai Rapor — {{ $selectedKelas->nama }}</h1>
    <p class="subtitle">{{ $selectedSemester->nama }} — {{ $selectedSemester->tahunAjaran->nama }} &middot; Dicetak {{ now()->translatedFormat('d F Y H:i') }}</p>
```

menjadi:

```blade
    <h1>Rekap Nilai Rapor — {{ $selectedKelas->nama }}</h1>
    <p class="subtitle">{{ $selectedKelas->lembaga->nama ?? '-' }} &middot; {{ $selectedSemester->nama }} — {{ $selectedSemester->tahunAjaran->nama }} &middot; Dicetak {{ now()->translatedFormat('d F Y H:i') }}</p>
```

- [ ] **Step 4: Jalankan test Step 1 lagi, pastikan lulus**

Run: `php artisan test --filter="shows the lembaga name in the printed pdf subtitle" --compact`
Expected: PASS.

- [ ] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/RaporControllerTest.php --compact`
Expected: PASS semua — terutama `it('streams a pdf for the selected kelas and semester via the cetak endpoint', ...)`, `it('does not crash when streaming a pdf for a kelas that has real nilai data (RekapNilaiSel regression)', ...)`, dan `it('renders the score inside the per-mapel matrix cell of the printable pdf rekap (key-mismatch regression)', ...)` — pastikan tidak rusak oleh penambahan 1 baris ini.

- [ ] **Step 6: Commit**

```bash
git add resources/views/pdf/rekap-rapor.blade.php tests/Feature/Admin/RaporControllerTest.php
git commit -m "fix(rapor): tampilkan nama lembaga di subtitle PDF cetak rekap"
```

---

### Task 5: 🟢 Tooltip Metodologi "Rata-Rata Kelas" (Item F)

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/rapor/_hasil.blade.php`
- Test: `tests/Feature/Admin/RaporControllerTest.php`

**Interfaces:**
- Tidak ada interface baru. Tidak bergantung pada task lain.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('shows a tooltip explaining the Rata-Rata Kelas calculation methodology', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $viewer = actingAsRaporViewer($lembaga);

    $response = $this->actingAs($viewer)->get(route('admin.rapor.index', ['tahun_ajaran_id' => $tahunAjaran->id, 'kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertSee('Dihitung dari rata-rata SELURUH nilai numerik individual', false);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="shows a tooltip explaining the Rata-Rata Kelas calculation methodology" --compact`
Expected: FAIL (tooltip belum ada).

- [ ] **Step 3: Implementasi**

Di `resources/views/portals/lembaga/akademik/rapor/_hasil.blade.php` — ganti (baris 20):

```blade
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Rata-Rata Kelas</p>
```

menjadi:

```blade
                        <div class="flex items-center gap-1">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Rata-Rata Kelas</p>
                            <x-tooltip text="Dihitung dari rata-rata SELURUH nilai numerik individual (siswa x mapel), bukan rata-rata dari nilai rata-rata tiap siswa.">
                                <x-icon name="info" class="h-3 w-3 cursor-help text-gray-400" />
                            </x-tooltip>
                        </div>
```

- [ ] **Step 4: Jalankan test Step 1 lagi, pastikan lulus**

Run: `php artisan test --filter="shows a tooltip explaining the Rata-Rata Kelas calculation methodology" --compact`
Expected: PASS.

- [ ] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/RaporControllerTest.php --compact`
Expected: PASS semua.

- [ ] **Step 6: Commit**

```bash
git add resources/views/portals/lembaga/akademik/rapor/_hasil.blade.php tests/Feature/Admin/RaporControllerTest.php
git commit -m "feat(rapor): tooltip metodologi perhitungan Rata-Rata Kelas"
```

---

### Task 6: 🟢 Adopsi `<x-select>` (Item H) + Urutan Dropdown (Item I)

**Files:**
- Modify: `resources/views/components/select.blade.php`
- Modify: `resources/views/portals/lembaga/akademik/rapor/index.blade.php`
- Test: `tests/Feature/Admin/RaporControllerTest.php`

**Interfaces:**
- Consumes: markup dropdown Tahun Ajaran HASIL Task 2 (BUKAN versi asli).

**PENTING sebelum mulai**: Task ini mengubah `resources/views/components/select.blade.php` yang JUGA dipakai 9 file lain di luar Rekap Rapor (`admin/karyawan/_form.blade.php`, `admin/roles/create.blade.php`, `admin/roles/edit.blade.php`, `admin/siswa/_form.blade.php`, `admin/siswa/_orang_tua.blade.php`, `admin/users/_form.blade.php`, `portals/kasus/partials/_tab-evaluasi.blade.php`, `portals/kasus/partials/_tab-sesi.blade.php`, `portals/kasus/partials/_tab-tugas.blade.php`). Ini DAMPAK DISENGAJA (sudah direkam di `.ai/rules/components.md` — "Align `<x-select>` styling to the Komponen Penilaian index look, then adopt it everywhere"), BUKAN efek samping tak terduga. Setelah Step 3, buka SALAH SATU dari 9 file itu (mis. `admin/karyawan/_form.blade.php`) dan pastikan tetap render wajar (ring fokus lebih tipis, TIDAK ada perubahan lain yang aneh) — kalau terlihat rusak (bukan cuma beda tipis), STOP dan laporkan.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('still loads and submits the tahun ajaran, semester, and kelas filters correctly after migrating to x-select', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '4C']);
    $viewer = actingAsRaporViewer($lembaga);

    $response = $this->actingAs($viewer)->get(route('admin.rapor.index', ['tahun_ajaran_id' => $tahunAjaran->id, 'kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertSee('4C');
    // Urutan visual (Item I): label "Pilih Semester" harus muncul SEBELUM "Pilih Kelas" di HTML mentah.
    $html = $response->getContent();
    expect(strpos($html, 'Pilih Semester'))->toBeLessThan(strpos($html, 'Pilih Kelas'));
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="still loads and submits the tahun ajaran, semester, and kelas filters correctly after migrating to x-select" --compact`
Expected: FAIL pada assertion urutan (`Pilih Kelas` masih muncul SEBELUM `Pilih Semester` di kode saat ini).

- [ ] **Step 3: Selaraskan style `<x-select>`**

Di `resources/views/components/select.blade.php` — ganti seluruh isi file:

```blade
@props(['disabled' => false, 'error' => false])

@php
    $baseClasses = 'block w-full rounded-lg text-sm shadow-sm transition-all focus:outline-none focus:ring-4 disabled:bg-gray-50 disabled:text-gray-500 disabled:cursor-not-allowed';
    $stateClasses = $error
        ? 'border-error-300 text-error-900 focus:border-error-500 focus:ring-error-500/20 bg-error-50/30'
        : 'border-gray-200 text-gray-900 focus:border-brand-500 focus:ring-brand-500/20 bg-white hover:border-gray-300';
@endphp

<select @disabled($disabled) {{ $attributes->merge(['class' => $baseClasses . ' ' . $stateClasses]) }}>
    {{ $slot }}
</select>
```

menjadi:

```blade
@props(['disabled' => false, 'error' => false])

@php
    $baseClasses = 'block w-full rounded-lg text-sm shadow-sm transition duration-150 disabled:bg-gray-50 disabled:text-gray-500 disabled:cursor-not-allowed';
    $stateClasses = $error
        ? 'border-error-300 text-error-900 focus:border-error-500 focus:ring-error-500'
        : 'border-gray-200 text-gray-900 focus:border-brand-500 focus:ring-brand-500 bg-white';
@endphp

<select @disabled($disabled) {{ $attributes->merge(['class' => $baseClasses . ' ' . $stateClasses]) }}>
    {{ $slot }}
</select>
```

- [ ] **Step 4: Migrasi 3 dropdown Rekap Rapor + reorder**

Di `resources/views/portals/lembaga/akademik/rapor/index.blade.php` — ganti SELURUH blok 3 dropdown (Tahun Ajaran, Kelas, Semester — kode Tahun Ajaran adalah HASIL Task 2):

```blade
                <div class="flex-1 min-w-[220px]">
                    <x-input-label value="Tahun Ajaran" />
                    <select x-ref="tahunAjaranSelect" x-init="initTahunAjaranSelect($refs.tahunAjaranSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-bold text-gray-900 transition focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Pilih Tahun Ajaran —</option>
                        @foreach ($tahunAjaranList as $tahunAjaran)
                            <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}{{ ($isYayasan ?? false) && ! ($activeLembaga ?? null) ? ' — '.($tahunAjaran->lembaga->nama ?? '-') : '' }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex-1 min-w-[220px]">
                    <x-input-label value="Pilih Kelas" />
                    <select x-ref="kelasSelect" x-init="initKelasSelect($refs.kelasSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-bold text-gray-900 transition focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($kelasList as $kelas)
                            <option value="{{ $kelas->id }}" @selected($selectedKelas && $selectedKelas->id === $kelas->id)>{{ $kelas->nama }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex-1 min-w-[220px]">
                    <x-input-label value="Pilih Semester" />
                    <select x-ref="semesterSelect" x-init="initSemesterSelect($refs.semesterSelect)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm font-bold text-gray-900 transition focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($semesterList as $semester)
                            <option value="{{ $semester->id }}" @selected($selectedSemester && $selectedSemester->id === $semester->id)>{{ $semester->nama }}</option>
                        @endforeach
                    </select>
                </div>
```

menjadi (`<x-select>` menggantikan `<select>`, DAN urutan blok Kelas/Semester ditukar):

```blade
                <div class="flex-1 min-w-[220px]">
                    <x-input-label value="Tahun Ajaran" />
                    <x-select x-ref="tahunAjaranSelect" x-init="initTahunAjaranSelect($refs.tahunAjaranSelect)" class="mt-1.5 font-bold">
                        <option value="">— Pilih Tahun Ajaran —</option>
                        @foreach ($tahunAjaranList as $tahunAjaran)
                            <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}{{ ($isYayasan ?? false) && ! ($activeLembaga ?? null) ? ' — '.($tahunAjaran->lembaga->nama ?? '-') : '' }}</option>
                        @endforeach
                    </x-select>
                </div>

                <div class="flex-1 min-w-[220px]">
                    <x-input-label value="Pilih Semester" />
                    <x-select x-ref="semesterSelect" x-init="initSemesterSelect($refs.semesterSelect)" class="mt-1.5 font-bold">
                        @foreach ($semesterList as $semester)
                            <option value="{{ $semester->id }}" @selected($selectedSemester && $selectedSemester->id === $semester->id)>{{ $semester->nama }}</option>
                        @endforeach
                    </x-select>
                </div>

                <div class="flex-1 min-w-[220px]">
                    <x-input-label value="Pilih Kelas" />
                    <x-select x-ref="kelasSelect" x-init="initKelasSelect($refs.kelasSelect)" class="mt-1.5 font-bold">
                        @foreach ($kelasList as $kelas)
                            <option value="{{ $kelas->id }}" @selected($selectedKelas && $selectedKelas->id === $kelas->id)>{{ $kelas->nama }}</option>
                        @endforeach
                    </x-select>
                </div>
```

**Catatan `x-ref`/`x-init` tetap berfungsi**: `<x-select>` pakai `$attributes->merge()` yang otomatis meneruskan atribut tak dikenal (`x-ref`, `x-init`) ke elemen `<select>` asli. TIDAK ADA perubahan di `resources/js/rapor-filter.js` — `initTahunAjaranSelect`/`initKelasSelect`/`initSemesterSelect` tetap disambungkan dengan cara yang sama persis, independen dari urutan visual.

- [ ] **Step 5: Jalankan test Step 1 lagi, pastikan lulus**

Run: `php artisan test --filter="still loads and submits the tahun ajaran, semester, and kelas filters correctly after migrating to x-select" --compact`
Expected: PASS.

- [ ] **Step 6: Verifikasi manual dampak lintas-file (WAJIB, bukan opsional)**

Baca `resources/views/admin/karyawan/_form.blade.php` (atau file lain dari 9 yang disebut di atas) dan pastikan strukturnya tetap valid Blade — tidak perlu render visual di browser (tidak selalu ada akses), TAPI pastikan tidak ada `<x-select>` yang sekarang error karena kehilangan atribut yang sebelumnya WAJIB (`error`/`disabled` props tetap didukung, sudah dicek di Step 3 — keduanya TIDAK dihapus, hanya class CSS yang berubah).

- [ ] **Step 7: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/RaporControllerTest.php --compact`
Expected: PASS semua.

- [ ] **Step 8: Commit**

```bash
git add resources/views/components/select.blade.php resources/views/portals/lembaga/akademik/rapor/index.blade.php tests/Feature/Admin/RaporControllerTest.php
git commit -m "refactor(rapor): migrasi 3 dropdown filter ke <x-select> + urutan Tahun Ajaran -> Semester -> Kelas; selaraskan style x-select ke tampilan TP"
```

---

### Task 7: Perbarui Catatan Lama yang Sudah Tidak Akurat (Item G)

**Files:**
- Modify: `.agents/logs/2026-09-07-audit-scope-yayasan-lembaga-sidebar.md`

**Interfaces:**
- Consumes: SEMUA Task 1-6 harus SELESAI dan LULUS TEST sebelum task ini dikerjakan.

- [ ] **Step 1: Pastikan seluruh task sebelumnya sudah lulus (prasyarat, bukan langkah teknis)**

Konfirmasi Task 1-6 semuanya sudah di-commit dan test filenya lulus (akan diverifikasi ulang secara menyeluruh di Task 8 — task ini HANYA update dokumentasi, jangan dikerjakan kalau Task 1-6 belum benar-benar selesai).

- [ ] **Step 2: Update baris checklist**

Di `.agents/logs/2026-09-07-audit-scope-yayasan-lembaga-sidebar.md` baris 89 — ganti:

```
| Rekap Rapor | ✅ | ✅ | Diperbaiki Task 7 (label lembaga di dropdown tahun ajaran saat mode agregat) |
```

menjadi:

```
| Rekap Rapor | ✅ | ✅ | Diperbaiki 2026-09-09 (spec `2026-09-09-rekap-rapor-audit-perbaikan.md`) — default filter tidak lagi ambigu di mode agregat, badge scope ditambahkan, konteks kelas/lembaga ditampilkan di hasil & PDF |
```

- [ ] **Step 3: Commit**

```bash
git add .agents/logs/2026-09-07-audit-scope-yayasan-lembaga-sidebar.md
git commit -m "docs(rapor): perbarui catatan checklist scope lembaga -- Rekap Rapor sudah benar-benar diperbaiki"
```

---

### Task 8: Penutup — Regresi Penuh & Pint

**Files:**
- Tidak ada file baru — task verifikasi murni.

- [ ] **Step 1: Jalankan SELURUH `tests/Feature/Admin/RaporControllerTest.php`**

Run: `php artisan test tests/Feature/Admin/RaporControllerTest.php --compact`
Expected: PASS semua (23 test existing + ~14 test baru dari Task 1-6). **0 gagal** — kalau ada yang gagal, STOP dan laporkan, JANGAN lanjut ke Task 7 kalau belum dijalankan, dan JANGAN klaim Task 8 selesai kalau ada test merah.

- [ ] **Step 2: Jalankan Pint pada file yang diubah**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}`.

- [ ] **Step 3: Verifikasi manual via browser (OPSIONAL — bukan langkah blocking)**

Kalau memungkinkan (akses browser interaktif tersedia): login sebagai yayasan-scope mode agregat, buka Rekap Rapor — pastikan badge "Semua Lembaga" muncul, dropdown Tahun Ajaran kosong (belum ada Kelas/Semester terisi), pilih Tahun Ajaran → Semester → Kelas (urutan baru) → rekap termuat dengan judul konteks + badge lembaga. Cetak PDF, pastikan nama lembaga muncul di subtitle. **Kalau TIDAK memungkinkan (tidak ada akses browser), lewati langkah ini dan JANGAN mengklaim "sudah diverifikasi" — laporkan dengan jujur bahwa langkah ini diserahkan ke user.**

- [ ] **Step 4: Laporkan hasil**

TIDAK perlu menulis file handoff log baru di task ini — permintaan terpisah kalau user menghendaki nanti.

---

## Self-Review

**1. Spec coverage** — SEMUA 9 item spec (`.agents/specs/2026-09-09-rekap-rapor-audit-perbaikan.md`) tercakup: Item A+B → Task 1 (digabung sesuai ketergantungan kode yang eksplisit di spec), Item C → Task 2, Item D → Task 3, Item E → Task 4, Item F → Task 5, Item H+I → Task 6 (digabung sesuai instruksi spec "Item I dilakukan setelah Item H"), Item G → Task 7 (paling akhir, sesuai instruksi spec). Task 8 menutup dengan regresi penuh + Pint.

**2. Placeholder scan** — tidak ada "TBD"/"TODO"/dst. Semua step berisi kode lengkap, ditranskripsi persis dari kode current-vs-fix yang sudah ada di spec (yang sendiri sudah direview 4x). Test baru semuanya berisi assertion konkret, bukan deskripsi.

**3. Type consistency** — `scopeHeaderData()` (Task 1) dipakai identik oleh Task 3 (`$selectedKelas->lembaga`, `$isYayasan`, `$activeLembaga`) tanpa redefinisi ulang. Variabel `$isYayasanAggregate` (Task 1, method `index()`) TIDAK dipakai di task lain (murni lokal ke method itu), konsisten dengan penjelasan spec bahwa efek berantainya otomatis menular ke `$kelasList`/`$semesterList` tanpa perlu variabel/guard terpisah.

**Catatan tambahan hasil self-review**:
- Task 1 SENGAJA mencakup Step 5 (wording empty-state) meski itu "milik" penjelasan Item C di ringkasan spec — pengecekannya, wording baru ini WAJIB ada SEBELUM test Task 1 Step 1 bisa lulus (`assertSee('Silakan Pilih Tahun Ajaran, Kelas, dan Semester')`), jadi harus digabung ke Task 1, bukan ditunda ke Task 2. Task 2 sendiri HANYA menangani perbaikan dropdown Tahun Ajaran (raw session read + placeholder), bukan wording empty-state (sudah selesai di Task 1).
- Ditemukan & dikonfirmasi lewat pembacaan langsung `tests/Feature/Admin/RaporControllerTest.php`: 23 test existing SANGAT relevan dan sudah disebutkan lengkap ke tiap task supaya implementer TIDAK menduplikasi test yang sudah ada (khususnya `it('does not mix a kelas and semester...')` yang SUDAH membuktikan skenario deep-link mode-agregat Item A, dan 2 test lembaga-suffix Item C).
- Urutan Task 1→7 WAJIB berurutan (bukan sekadar disarankan) — Task 2/3 butuh variabel dari Task 1, Task 6 butuh markup hasil Task 2, Task 7 butuh SEMUA task lain selesai. Task 4 dan Task 5 TIDAK punya ketergantungan ke task lain, secara teori bisa ditukar urutan dengan Task 2/3, TAPI tetap disarankan urut 1→8 karena semuanya menyentuh file yang tumpang tindih (`_hasil.blade.php` disentuh Task 1, 3, 5 — mengerjakan paralel berisiko conflict).
