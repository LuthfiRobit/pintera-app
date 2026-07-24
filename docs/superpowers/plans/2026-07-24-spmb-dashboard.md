# Sub-project 3a — Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn `/portal/dashboard` into the real entry point of the account-first SPMB flow — auto-redirect straight into the wizard when the account has an in-progress lembaga/jalur choice, show a navy-restyled "Riwayat Pendaftaran" list when the account already has registrations, and show an in-page empty-state (not a redirect) inviting the account to pick a lembaga/jalur when it has neither.

**Architecture:** Two independently-testable slices: (1) `Portal\DashboardController::index()` gains branching logic that decides redirect-vs-render, driven purely by `AkunPendaftar::pendaftaran()` and `session('spmb_pilihan.*')` — no view changes needed to test this. (2) `resources/views/portal/dashboard.blade.php` is rewritten against the navy design system, wrapped in a new `<x-layouts.portal-dashboard>` layout that reuses the existing `<x-portal-authenticated-navbar>`/`<x-portal-footer>` components from Sub-project 3b/1.

**Tech Stack:** Laravel 12, Blade, Tailwind CSS (navy `portal-*`/`gray-*`/semantic `success-*`/`warning-*`/`error-*` tokens), Pest 4.

## Global Constraints

- Palette: `portal-50`/`portal-500`/`portal-600`, `gray-*` (Untitled-UI scale), semantic `success-*`/`warning-*`/`error-*` — never the old `spmb-primary`/`slate`/`ink` tokens.
- Font is already `Outfit` app-wide — no font-loading code in new views.
- Navbar mobile breakpoint: `min-[721px]:` (matches `<x-portal-authenticated-navbar>`'s existing breakpoint) — never Tailwind's default `md:` (768px).
- Reuse existing components as-is, do not recreate: `<x-portal-authenticated-navbar active="dashboard|riwayat">`, `<x-portal-footer :yayasan="...">`.
- `Portal\DashboardController` and `resources/views/portal/dashboard.blade.php` are modified in place — same class/route/view name, no new controller or route.
- `session('spmb_pilihan.*')` validity is checked by looking the IDs up (`Lembaga::find()`/`JalurPpdb::find()`), not by `isset()` — a session pointing at a deleted/nonexistent row must behave exactly like an empty session (render State 3), never throw.
- Status badge must cover all 5 real `pendaftaran.status` enum values (`menunggu_verifikasi`, `diterima`, `ditolak`, `daftar_ulang`, `aktif`) with distinct label + color + icon (never rely on color alone, per this codebase's established accessibility pattern already used on `spmb/welcome.blade.php`'s Dibuka/Ditutup badges).
- **Test fixture ordering:** any tenant-scoped model (`Lembaga`, `JalurPpdb`, `TahunAjaran`, `GelombangPpdb`, `Pendaftaran`) must be created BEFORE calling `$this->actingAs($akun, 'portal')` / `loginAkunDenganPilihanSpmb()` in a test — creating one afterward throws `BadMethodCallException: Call to undefined method App\Models\AkunPendaftar::widestScopeLevel()` (a known issue from Sub-project 3b: `actingAs($akun, 'portal')` changes Laravel's default auth guard, and `BelongsToTenant`'s global scope calls `auth()->user()->widestScopeLevel()` against whatever the default guard resolves to).

---

### Task 1: Dashboard branching logic (controller)

**Files:**
- Modify: `app/Http/Controllers/Portal/DashboardController.php`
- Test: `tests/Feature/Portal/DashboardTest.php` (existing file — add new `it()` blocks, do not remove the 5 existing ones)

**Interfaces:**
- Consumes: `AkunPendaftar::pendaftaran(): HasMany` (existing, in `app/Models/AkunPendaftar.php`), `Pendaftaran::$lembaga_id`/`$jalur_ppdb_id` (existing fillable columns), `session('spmb_pilihan.lembaga_id'/'jalur_id')` (existing session keys, written by `Spmb\PortalController::daftarJalur()` from Sub-project 1), `route('portal.wizard.data-diri')` (existing route, Sub-project 3b).
- Produces: `DashboardController::index(): View|RedirectResponse` — return type changes from `View` to `View|RedirectResponse` (Task 2's view work consumes the same `$pendaftaranList` variable name, unchanged).

The full current file:

```php
<?php

namespace App\Http\Controllers\Portal;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends BaseController
{
    public function index(): View
    {
        $pendaftaranList = Auth::guard('portal')->user()
            ->pendaftaran()
            ->with(['calonMurid', 'lembaga', 'jalurPpdb', 'gelombangPpdb'])
            ->latest('submitted_at')
            ->get();

        return view('portal.dashboard', ['pendaftaranList' => $pendaftaranList]);
    }
}
```

- [ ] **Step 1: Write the failing tests**

Open `tests/Feature/Portal/DashboardTest.php` and add these `use` statements at the top (alongside the existing ones) and these `it()` blocks at the end of the file (after the last existing test):

```php
use App\Models\JalurPpdb;
```

```php
it('redirects to the wizard when there is no pendaftaran and the session has a valid lembaga+jalur choice', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    $akun = loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->get(route('portal.dashboard'))
        ->assertRedirect(route('portal.wizard.data-diri'));
});

it('does not redirect to the wizard when the session points to a lembaga or jalur that no longer exists', function () {
    $akun = AkunPendaftar::factory()->create();
    session(['spmb_pilihan.lembaga_id' => 999999, 'spmb_pilihan.jalur_id' => 999999]);

    $this->actingAs($akun, 'portal')->get(route('portal.dashboard'))
        ->assertOk();
});

it('redirects to the wizard when the account has a pendaftaran but the session points to a different, not-yet-registered jalur', function () {
    [$lembaga, $tahunAjaran, $jalurA, $gelombang] = buatLembagaDenganGelombangBuka();
    $jalurB = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi']);
    $akun = AkunPendaftar::factory()->create();
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id,
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalurA->id,
        'gelombang_ppdb_id' => $gelombang->id,
        'akun_pendaftar_id' => $akun->id,
        'email_pendaftaran' => $akun->email,
    ]);
    session(['spmb_pilihan.lembaga_id' => $lembaga->id, 'spmb_pilihan.jalur_id' => $jalurB->id]);

    $this->actingAs($akun, 'portal')->get(route('portal.dashboard'))
        ->assertRedirect(route('portal.wizard.data-diri'));
});

it('does not redirect when the session choice exactly matches a jalur the account already registered', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    $akun = AkunPendaftar::factory()->create();
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id,
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id,
        'gelombang_ppdb_id' => $gelombang->id,
        'akun_pendaftar_id' => $akun->id,
        'email_pendaftaran' => $akun->email,
    ]);
    session(['spmb_pilihan.lembaga_id' => $lembaga->id, 'spmb_pilihan.jalur_id' => $jalur->id]);

    $this->actingAs($akun, 'portal')->get(route('portal.dashboard'))
        ->assertOk();
});

it('redirects a guest to login when accessing the dashboard', function () {
    $this->get(route('portal.dashboard'))
        ->assertRedirect(route('portal.login'));
});
```

- [ ] **Step 2: Run tests to verify the new ones fail**

Run: `vendor/bin/pest tests/Feature/Portal/DashboardTest.php`
Expected: the 5 new tests FAIL (the first two redirect-expecting tests get a 200 instead of a redirect, since the controller has no branching logic yet — the guest-redirect test should already PASS since that's existing middleware behavior, that's fine).

- [ ] **Step 3: Implement the branching logic**

Replace the full contents of `app/Http/Controllers/Portal/DashboardController.php`:

```php
<?php

namespace App\Http\Controllers\Portal;

use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends BaseController
{
    public function index(): View|RedirectResponse
    {
        $akun = Auth::guard('portal')->user();

        $pendaftaranList = $akun->pendaftaran()
            ->with(['calonMurid', 'lembaga', 'jalurPpdb', 'gelombangPpdb'])
            ->latest('submitted_at')
            ->get();

        $lembaga = session('spmb_pilihan.lembaga_id')
            ? Lembaga::find(session('spmb_pilihan.lembaga_id'))
            : null;
        $jalur = session('spmb_pilihan.jalur_id')
            ? JalurPpdb::find(session('spmb_pilihan.jalur_id'))
            : null;

        if ($lembaga && $jalur && ! $this->sudahDidaftarkan($pendaftaranList, $lembaga, $jalur)) {
            return redirect()->route('portal.wizard.data-diri');
        }

        return view('portal.dashboard', ['pendaftaranList' => $pendaftaranList]);
    }

    private function sudahDidaftarkan($pendaftaranList, Lembaga $lembaga, JalurPpdb $jalur): bool
    {
        return $pendaftaranList->contains(
            fn (Pendaftaran $p) => $p->lembaga_id === $lembaga->id && $p->jalur_ppdb_id === $jalur->id
        );
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Portal/DashboardTest.php`
Expected: all tests PASS (5 new + 5 pre-existing).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Portal/DashboardController.php tests/Feature/Portal/DashboardTest.php
git commit -m "feat: add dashboard branching logic (auto-redirect into wizard vs. render riwayat)"
```

---

### Task 2: Dashboard navy redesign (layout, view, icons)

**Files:**
- Create: `resources/views/components/layouts/portal-dashboard.blade.php`
- Modify: `resources/views/portal/dashboard.blade.php`
- Modify: `resources/views/components/icon.blade.php` (add `autorenew` and `add` icons)
- Test: `tests/Feature/Portal/DashboardTest.php` (extend `buatPendaftaranUntukAkun()` with an optional `$status` param, add new `it()` blocks)

**Interfaces:**
- Consumes: `DashboardController::index()`'s `$pendaftaranList` variable (Task 1, unchanged name/shape — a `Collection<Pendaftaran>` with `calonMurid`/`lembaga`/`jalurPpdb`/`gelombangPpdb` eager-loaded), `<x-portal-authenticated-navbar active="...">` and `<x-portal-footer :yayasan="...">` (existing components, Sub-project 1/3b), `route('spmb.welcome')` (existing, Sub-project 1), `route('portal.pendaftaran.bukti', $pendaftaran)` (existing).
- Produces: `<x-layouts.portal-dashboard title="...">{{ slot }}</x-layouts.portal-dashboard>` — a new reusable layout any future portal page needing navbar+footer-but-no-wizard-shell can use.

- [ ] **Step 1: Write the failing tests**

In `tests/Feature/Portal/DashboardTest.php`, replace the existing `buatPendaftaranUntukAkun()` helper (top of file) with a version that accepts an optional status:

```php
function buatPendaftaranUntukAkun(AkunPendaftar $akun, string $nama = 'Ahmad Fauzan', ?string $status = null): Pendaftaran
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id, 'nama_lengkap' => $nama]);

    return Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id,
        'lembaga_id' => $lembaga->id,
        'akun_pendaftar_id' => $akun->id,
        'email_pendaftaran' => $akun->email,
        'status' => $status ?? 'menunggu_verifikasi',
    ]);
}
```

Then add these `it()` blocks at the end of the file:

```php
it('shows the correct label for every pendaftaran status', function () {
    $akun = AkunPendaftar::factory()->create();
    buatPendaftaranUntukAkun($akun, 'Calon Menunggu', 'menunggu_verifikasi');
    buatPendaftaranUntukAkun($akun, 'Calon Diterima', 'diterima');
    buatPendaftaranUntukAkun($akun, 'Calon Ditolak', 'ditolak');
    buatPendaftaranUntukAkun($akun, 'Calon Daftar Ulang', 'daftar_ulang');
    buatPendaftaranUntukAkun($akun, 'Calon Aktif', 'aktif');

    $response = $this->actingAs($akun, 'portal')->get(route('portal.dashboard'));

    $response->assertOk();
    $response->assertSee('Menunggu Verifikasi');
    $response->assertSee('Diterima');
    $response->assertSee('Ditolak');
    $response->assertSee('Daftar Ulang');
    $response->assertSee('Aktif');
});

it('shows a Daftar Lagi button linking to the welcome page when the account has a pendaftaran', function () {
    $akun = AkunPendaftar::factory()->create();
    buatPendaftaranUntukAkun($akun);

    $this->actingAs($akun, 'portal')->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee('Daftar Lagi')
        ->assertSee(route('spmb.welcome'), false);
});

it('shows an empty-state CTA linking to the welcome page when the account has no pendaftaran', function () {
    $akun = AkunPendaftar::factory()->create();

    $this->actingAs($akun, 'portal')->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee('Pilih Lembaga & Jalur')
        ->assertSee(route('spmb.welcome'), false);
});

it('renders the authenticated navbar on the dashboard', function () {
    $akun = AkunPendaftar::factory()->create();

    $this->actingAs($akun, 'portal')->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Riwayat')
        ->assertSee('Bantuan');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Portal/DashboardTest.php`
Expected: the 4 new tests FAIL — status labels/CTA text/button not present yet in the old view (`Menunggu Verifikasi`/`Diterima`/`Ditolak` already appear from the OLD badge logic so those specific `assertSee` calls may already pass, but `Daftar Ulang`/`Aktif` and the two CTA tests fail).

- [ ] **Step 3: Add the two new icons**

In `resources/views/components/icon.blade.php`, add these two `@case` blocks (following the exact pattern of the existing ones — insert after the `@case('description')` block, before `@endswitch`):

```blade
    @case('autorenew')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/></svg>
        @break

    @case('add')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M12 5v14M5 12h14"/></svg>
        @break
```

- [ ] **Step 4: Create the dashboard layout**

Create `resources/views/components/layouts/portal-dashboard.blade.php`:

```blade
{{-- resources/views/components/layouts/portal-dashboard.blade.php --}}
@props(['title' => null])

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? 'Dashboard' }} — Pintera</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-50 font-sans text-gray-900 antialiased">
        <x-portal-authenticated-navbar active="dashboard" />

        <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-10">
            {{ $slot }}
        </main>

        <x-portal-footer :yayasan="null" />
    </body>
</html>
```

- [ ] **Step 5: Rewrite the dashboard view**

Replace the full contents of `resources/views/portal/dashboard.blade.php`:

```blade
{{-- resources/views/portal/dashboard.blade.php --}}
<x-layouts.portal-dashboard title="Dashboard">
    <p class="text-[11px] font-bold uppercase tracking-wide text-portal-500">Portal Calon Siswa</p>
    <h1 class="mt-1 text-2xl font-bold text-gray-900">Halo, {{ Str::of(auth('portal')->user()->nama)->before(' ') }}</h1>
    <p class="mt-1 text-[13px] text-gray-500">Pantau status pendaftaranmu di sini.</p>

    @if ($pendaftaranList->isEmpty())
        <div class="mt-8 rounded-2xl border border-dashed border-gray-200 p-10 text-center">
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-portal-50 text-portal-300">
                <x-icon name="school" class="h-8 w-8" />
            </span>
            <h2 class="mt-4 text-[15px] font-bold text-gray-900">Kamu belum memilih lembaga & jalur</h2>
            <p class="mx-auto mt-1.5 max-w-sm text-[13px] text-gray-500">Pilih lembaga dan jalur pendaftaran untuk mulai mengisi formulir.</p>
            <a href="{{ route('spmb.welcome') }}" class="mt-5 inline-flex items-center gap-2 rounded-[10px] bg-portal-500 px-5 py-2.5 text-[13.5px] font-bold text-white transition hover:bg-portal-600">
                Pilih Lembaga & Jalur
                <x-icon name="arrow_forward" class="h-3.5 w-3.5" />
            </a>
        </div>
    @else
        <div class="mt-6 flex items-center justify-between">
            <h2 class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Riwayat Pendaftaran</h2>
            <a href="{{ route('spmb.welcome') }}" class="inline-flex items-center gap-2 rounded-[10px] bg-portal-500 px-4 py-2 text-[12.5px] font-bold text-white transition hover:bg-portal-600">
                <x-icon name="add" class="h-3.5 w-3.5" />
                Daftar Lagi
            </a>
        </div>

        <div class="mt-3 space-y-3">
            @foreach ($pendaftaranList as $pendaftaran)
                @php
                    $badge = match ($pendaftaran->status) {
                        'diterima' => ['success', 'check_circle', 'Diterima'],
                        'ditolak' => ['error', 'cancel', 'Ditolak'],
                        'daftar_ulang' => ['portal', 'autorenew', 'Daftar Ulang'],
                        'aktif' => ['success', 'check_circle', 'Aktif'],
                        default => ['warning', 'hourglass_empty', 'Menunggu Verifikasi'],
                    };
                    [$tone, $icon, $label] = $badge;
                @endphp
                <div class="rounded-xl border border-gray-200 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold text-gray-900">{{ $pendaftaran->calonMurid->nama_lengkap }}</p>
                            <p class="mt-0.5 text-[13px] text-gray-500">{{ $pendaftaran->lembaga->nama }} &middot; {{ $pendaftaran->jalurPpdb->nama }} &middot; {{ $pendaftaran->gelombangPpdb->nama }}</p>
                            <p class="mt-0.5 font-mono text-[12px] text-gray-400">{{ $pendaftaran->kode_pendaftaran }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-{{ $tone }}-50 px-2.5 py-1 text-[11.5px] font-bold text-{{ $tone }}-{{ $tone === 'portal' ? '500' : '700' }}">
                                <x-icon name="{{ $icon }}" class="h-2.5 w-2.5" />
                                {{ $label }}
                            </span>
                            <a href="{{ route('portal.pendaftaran.bukti', $pendaftaran) }}" target="_blank" class="text-[12.5px] font-bold text-portal-500 hover:underline">
                                Unduh Bukti (PDF)
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.portal-dashboard>
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Portal/DashboardTest.php`
Expected: all tests PASS (9 new across both tasks + 5 pre-existing = 14 total in this file).

- [ ] **Step 7: Run the full suite to check for regressions**

Run: `vendor/bin/pest`
Expected: all tests pass (no other file references the old `x-portal-layout`-based dashboard markup).

- [ ] **Step 8: Build frontend assets**

Run: `npm run build`
Expected: build succeeds (no new JS/CSS was added, this view only uses existing Tailwind classes/icons already compiled by the existing build pipeline).

- [ ] **Step 9: Commit**

```bash
git add resources/views/components/layouts/portal-dashboard.blade.php resources/views/portal/dashboard.blade.php resources/views/components/icon.blade.php tests/Feature/Portal/DashboardTest.php
git commit -m "feat: redesign dashboard with navy tokens, 5-state status badges, and empty-state CTA"
```

---

## Post-implementation note (not a task — informational only)

After this plan merges, `Lembaga.slug` and `spmb.welcome` are unaffected — the flow described in `[[project_spmb_account_first_redesign]]`'s "Next step when resuming" note (Sub-project 3a is the last of the 4 sub-projects) is now complete except Sub-project 4 (Pembayaran Biaya Pendaftaran & Info Jadwal Tes), which was already scoped as a separate, lighter follow-up.
