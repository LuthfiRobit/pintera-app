# SPMB Welcome & Katalog Lembaga/Jalur Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the two public, no-login entry pages of the new account-first SPMB flow — a global Welcome page listing every lembaga, and a per-lembaga Detail page listing its jalur — replacing `Spmb\PortalController::index`'s current single-page behavior.

**Architecture:** A new shared Blade layout (`layouts.portal-public`) plus two reusable components (`x-portal-navbar`, `x-portal-footer`) establish the navy visual chrome using the project's already-configured `portal-*`/`gray-*`/`success-*`/`warning-*` Tailwind tokens (from the separately-approved TailAdmin redesign spec) — entirely separate from the existing `layouts.spmb-public` shell, which stays untouched because other not-yet-migrated `spmb.*` views still depend on it. `Spmb\WelcomeController` is a new controller for `GET /spmb`; `Spmb\PortalController::index()` is rewritten in place (same route, same view name) for `GET /spmb/{lembagaSlug}`.

**Tech Stack:** Laravel 12, Blade, Tailwind CSS (existing `portal-*` token set), Alpine.js (existing app-wide setup, used for the mobile nav toggle), Pest PHP.

## Global Constraints

- Visual tokens: use the Tailwind utility classes already configured in `tailwind.config.js` — `portal-50`/`portal-500`/`portal-600` (navy), `gray-50`...`gray-900` (Untitled-UI scale), `success-50`/`success-700`, `warning-50`/`warning-700` (semantic badges — shared with admin, never tied to the portal primary color). Font is already `Outfit` app-wide via `fontFamily.sans`/`display` — no font-loading markup needed. Do NOT touch or remove the old tokens (`spmb-primary`, `spmb-accent`, `spmb-bg`, `spmb-tint`, `ink`, `slate`, etc.) — other, not-yet-migrated `spmb.*`/`portal.*` views still use them.
- Do not modify `resources/views/layouts/spmb-public.blade.php`, `resources/views/components/spmb-public-layout.blade.php`, or any other existing `spmb.*`/`portal.*` Blade file outside the two named in this plan — they belong to out-of-scope pages (data-diri, formulir-tambahan, dokumen, review, verifikasi-email, verifikasi-otp, berhasil, status-hasil, cek-status) that keep their current look until a later sub-project touches them.
- Every page must be responsive at all viewport widths: navbar collapses to a hamburger menu at ≤720px exactly (the spec's stated breakpoint, not Tailwind's default 768px `md:` — use the arbitrary-value variant `min-[721px]:` for the desktop nav/hide-hamburger classes), card grids collapse to fewer columns on narrow screens, nothing causes horizontal page scroll.
- Status badges (Dibuka/Ditutup, Gratis/Menunggu Konfirmasi Admin) always pair an icon with text — never color alone.
- `Spmb\PortalController::cariGelombangAktif(Lembaga $lembaga): ?GelombangPpdb` (existing public static method) must be reused as-is for finding the currently-open gelombang — do not reimplement this logic.
- Gelombang-jalur restriction (existing behavior, must be preserved exactly): if the currently-open gelombang has any rows in the `gelombang_jalur` pivot (`$gelombang->jalur()->exists()`), only jalur connected to that gelombang are shown (`JalurPpdb::whereHas('gelombang', fn ($q) => $q->whereKey($gelombang->id))`); otherwise (no pivot rows, or no gelombang open at all) show all active `JalurPpdb` for the lembaga's active tahun ajaran, unfiltered.
- The "Daftar Jalur Ini" action is only enabled when `cariGelombangAktif()` is non-null for that lembaga — when null, the button renders disabled with the label "Belum Dibuka", never hidden.
- PHP is not on PATH — use `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe` for all `artisan`/test commands.

---

### Task 1: Shared Portal Shell + Welcome Page

**Files:**
- Modify: `resources/views/components/icon.blade.php` (add 3 new icon cases)
- Create: `resources/views/components/portal-navbar.blade.php`
- Create: `resources/views/components/portal-footer.blade.php`
- Create: `resources/views/layouts/portal-public.blade.php`
- Create: `app/Http/Controllers/Spmb/WelcomeController.php`
- Create: `resources/views/spmb/welcome.blade.php`
- Modify: `routes/spmb.php`
- Test: `tests/Feature/Spmb/WelcomeControllerTest.php`

**Interfaces:**
- Consumes: `App\Models\Lembaga` (`nama`, `slug`, `bentuk_pendidikan`, `kecamatan`, `kabupaten_kota`), `App\Models\Yayasan` (`nama`, `telepon`, `email`, `alamat` — via `Lembaga::yayasan()`), `App\Models\TahunAjaran`, `App\Models\JalurPpdb`, `App\Models\GelombangPpdb`, `App\Models\NominalTagihanJalur`, `App\Models\JenisTagihan`, `Spmb\PortalController::cariGelombangAktif()`.
- Produces: `<x-portal-navbar active="beranda|lembaga" />` and `<x-portal-footer />` components, `layouts.portal-public` Blade layout (slot-based, `@extends`/`@section` NOT used — this app's convention is component-slot layouts, matching `x-spmb-public-layout`'s own pattern), route `spmb.welcome` at `GET /spmb`. Task 2 reuses `x-portal-navbar`, `x-portal-footer`, and `layouts.portal-public` for the Detail Lembaga page.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Spmb/WelcomeControllerTest.php

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

it('lists every lembaga with a jenjang badge and status badge', function () {
    [$lembagaBuka] = buatLembagaDenganGelombangBuka();

    $yayasan = Yayasan::factory()->create();
    $lembagaTutup = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMA Contoh Tertutup', 'bentuk_pendidikan' => 'SMA']);

    $response = $this->get('/spmb');

    $response->assertOk();
    $response->assertSee($lembagaBuka->nama);
    $response->assertSee('Dibuka');
    $response->assertSee($lembagaTutup->nama);
    $response->assertSee('Ditutup');
});

it('does not hide a lembaga with no open gelombang', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMP Tanpa Gelombang']);

    $this->get('/spmb')->assertOk()->assertSee('SMP Tanpa Gelombang');
});

it('computes real summary counts in the hero panel, not hardcoded numbers', function () {
    [$lembagaSatu] = buatLembagaDenganGelombangBuka();
    $yayasan = Yayasan::factory()->create();
    Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $response = $this->get('/spmb');

    $response->assertOk();
    $response->assertViewHas('jumlahLembaga', Lembaga::count());
    $response->assertViewHas('jumlahSedangBuka', 1);
});

it('renders the mobile nav toggle and both nav actions', function () {
    $this->get('/spmb')
        ->assertOk()
        ->assertSee('Masuk')
        ->assertSee('Daftar Akun');
});

it('renders a filter chip for every distinct jenjang plus a Semua chip', function () {
    [$lembagaSatu] = buatLembagaDenganGelombangBuka();
    $yayasan = Yayasan::factory()->create();
    Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SMP']);

    $response = $this->get('/spmb');

    $response->assertOk();
    $response->assertSee('Semua');
    $response->assertSee($lembagaSatu->bentuk_pendidikan);
    $response->assertSee('SMP');
});

it('shows the nearest-closing open gelombang in the hero panel', function () {
    [$lembaga, , , $gelombang] = buatLembagaDenganGelombangBuka();

    $this->get('/spmb')
        ->assertOk()
        ->assertSee($lembaga->nama)
        ->assertSee($gelombang->nama);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Spmb/WelcomeControllerTest.php`
Expected: FAIL — route `GET /spmb` doesn't exist yet (404).

- [ ] **Step 3: Write `portal-navbar` component**

The shared `x-icon` component (`resources/views/components/icon.blade.php`) takes `@props(['name'])` and switches on Material-Symbols-style names (`menu`, `school`, `check_circle`, `hourglass_empty`, etc — confirmed by reading the file). Use `name="school"` for the brand mark and `name="menu"` for the hamburger — both already registered, no changes to that file needed for this component.

The spec's "Masuk" fallback (when `portal.login` doesn't exist yet) says to send the visitor back to `#lembaga` "with the same message as the Daftar Jalur Ini button." Since this is a plain in-page anchor link (no server round-trip), there is no request/response cycle to attach a flash message to — the component below implements the navigational half (falls back to `#lembaga`) and skips a message, which is the same fallback shape used everywhere else in this page (`Route::has()` check, degrade to the lembaga list) without inventing a client-side toast mechanism that isn't used anywhere else in this codebase.

```php
<?php
// resources/views/components/portal-navbar.blade.php
```

```blade
@props(['active' => null])

<nav x-data="{ open: false }" class="sticky top-0 z-20 border-b border-gray-200 bg-white">
    <div class="mx-auto flex max-w-7xl items-center gap-6 px-4 py-4 sm:px-6 lg:px-10">
        <a href="{{ route('spmb.welcome') }}" class="mr-auto flex items-center gap-2.5">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-portal-500 to-portal-600 text-white">
                <x-icon name="school" class="h-4.5 w-4.5" />
            </span>
            <span class="leading-tight">
                <span class="block text-[15px] font-bold text-gray-900">Pintera</span>
                <span class="block text-[11px] font-semibold uppercase tracking-wide text-gray-400">Portal Calon Siswa</span>
            </span>
        </a>

        <div class="hidden items-center gap-6 text-[13.5px] font-medium text-gray-500 min-[721px]:flex">
            <a href="{{ route('spmb.welcome') }}" class="{{ $active === 'beranda' ? 'font-bold text-portal-500' : '' }}">Beranda</a>
            <a href="{{ route('spmb.welcome') }}#lembaga" class="{{ $active === 'lembaga' ? 'font-bold text-portal-500' : '' }}">Lembaga</a>
            <a href="{{ route('spmb.welcome') }}#alur">Alur Pendaftaran</a>
            <a href="#">Bantuan</a>
        </div>

        <div class="hidden items-center gap-2.5 min-[721px]:flex">
            <a
                href="{{ Route::has('portal.login') ? route('portal.login') : route('spmb.welcome') . '#lembaga' }}"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] font-semibold text-portal-500"
            >Masuk</a>
            <a href="{{ route('spmb.welcome') }}#lembaga" class="rounded-lg bg-portal-500 px-4.5 py-2.5 text-[13px] font-semibold text-white">Daftar Akun</a>
        </div>

        <button
            type="button"
            @click="open = !open"
            class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-portal-500 min-[721px]:hidden"
            aria-label="Buka menu navigasi"
        >
            <x-icon name="menu" class="h-4.5 w-4.5" />
        </button>
    </div>

    <div x-show="open" x-cloak x-transition class="border-t border-gray-200 bg-white px-4 pb-4 pt-2 min-[721px]:hidden">
        <div class="flex flex-col gap-1 text-[13.5px] font-medium text-gray-600">
            <a href="{{ route('spmb.welcome') }}" class="py-2">Beranda</a>
            <a href="{{ route('spmb.welcome') }}#lembaga" class="py-2">Lembaga</a>
            <a href="{{ route('spmb.welcome') }}#alur" class="py-2">Alur Pendaftaran</a>
            <a href="#" class="py-2">Bantuan</a>
        </div>
        <div class="mt-3 flex flex-col gap-2">
            <a href="{{ Route::has('portal.login') ? route('portal.login') : route('spmb.welcome') . '#lembaga' }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-center text-[13px] font-semibold text-portal-500">Masuk</a>
            <a href="{{ route('spmb.welcome') }}#lembaga" class="rounded-lg bg-portal-500 px-4 py-2.5 text-center text-[13px] font-semibold text-white">Daftar Akun</a>
        </div>
    </div>
</nav>
```

- [ ] **Step 4: Write `portal-footer` component**

```php
<?php
// resources/views/components/portal-footer.blade.php
```

```blade
@props(['yayasan' => null])

<footer class="bg-portal-600 px-4 pb-6 pt-11 text-gray-200 sm:px-6 lg:px-10">
    <div class="mx-auto grid max-w-7xl gap-9 border-b border-white/10 pb-7 sm:grid-cols-3">
        <div class="sm:col-span-1">
            <div class="mb-3 flex items-center gap-2.5">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 text-white">
                    <x-icon name="school" class="h-4 w-4" />
                </span>
                <span class="text-[15px] font-bold text-white">Pintera</span>
            </div>
            <p class="max-w-xs text-[12px] leading-relaxed text-gray-300">
                Portal terpadu penerimaan siswa baru {{ $yayasan?->nama ?? 'Yayasan' }} — satu akun untuk seluruh lembaga.
            </p>
        </div>
        <div>
            <h5 class="mb-3.5 text-[12px] font-bold uppercase tracking-wide text-white">Navigasi</h5>
            <ul class="flex flex-col gap-2.5 text-[12.5px]">
                <li><a href="{{ route('spmb.welcome') }}">Beranda</a></li>
                <li><a href="{{ route('spmb.welcome') }}#lembaga">Lembaga</a></li>
                <li><a href="{{ route('spmb.welcome') }}#alur">Alur Pendaftaran</a></li>
            </ul>
        </div>
        <div>
            <h5 class="mb-3.5 text-[12px] font-bold uppercase tracking-wide text-white">Kontak</h5>
            <ul class="flex flex-col gap-2.5 text-[12.5px]">
                @if ($yayasan?->alamat)
                    <li>{{ $yayasan->alamat }}</li>
                @endif
                @if ($yayasan?->email)
                    <li>{{ $yayasan->email }}</li>
                @endif
                @if ($yayasan?->telepon)
                    <li>{{ $yayasan->telepon }}</li>
                @endif
            </ul>
        </div>
    </div>
    <div class="mx-auto flex max-w-7xl flex-wrap justify-between gap-2 pt-4 text-[11.5px] text-gray-400">
        <span>&copy; {{ now()->year }} {{ $yayasan?->nama ?? config('app.name') }}</span>
        <span>Portal SPMB Pintera</span>
    </div>
</footer>
```

`App\Models\Yayasan`'s `$fillable` (confirmed by reading `app/Models/Yayasan.php`) includes `nama`, `alamat`, `email`, `telepon` exactly as used above — no adjustment needed.

- [ ] **Step 5: Write `layouts.portal-public`**

```php
<?php
// resources/views/layouts/portal-public.blade.php
```

```blade
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? 'SPMB' }} — Pintera</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-50 font-sans text-gray-900 antialiased">
        <x-portal-navbar :active="$active ?? null" />
        <main>{{ $slot }}</main>
        <x-portal-footer :yayasan="$yayasan ?? null" />
    </body>
</html>
```

- [ ] **Step 6: Write `WelcomeController`**

```php
<?php
// app/Http/Controllers/Spmb/WelcomeController.php

namespace App\Http\Controllers\Spmb;

use App\Models\Lembaga;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class WelcomeController extends BaseController
{
    public function index(): View
    {
        $lembagaList = Lembaga::with('yayasan')->orderBy('nama')->get()->map(function (Lembaga $lembaga) {
            $gelombang = PortalController::cariGelombangAktif($lembaga);

            $tahunAjaranAktif = \App\Models\TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();
            $jalurAktifCount = $tahunAjaranAktif
                ? \App\Models\JalurPpdb::where('lembaga_id', $lembaga->id)
                    ->where('tahun_ajaran_id', $tahunAjaranAktif->id)
                    ->where('status_aktif', true)
                    ->count()
                : 0;

            $biayaTermurah = null;
            if ($tahunAjaranAktif) {
                $jenisPendaftaran = \App\Models\JenisTagihan::where('lembaga_id', $lembaga->id)
                    ->where('kategori', 'pendaftaran')->first();

                if ($jenisPendaftaran) {
                    $biayaTermurah = \App\Models\NominalTagihanJalur::where('jenis_tagihan_id', $jenisPendaftaran->id)
                        ->whereHas('jalurPpdb', fn ($q) => $q->where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $tahunAjaranAktif->id))
                        ->min('nominal');
                }
            }

            return [
                'lembaga' => $lembaga,
                'gelombang' => $gelombang,
                'jalurAktifCount' => $jalurAktifCount,
                'biayaTermurah' => $biayaTermurah,
            ];
        });

        $jumlahSedangBuka = $lembagaList->filter(fn ($item) => $item['gelombang'] !== null)->count();
        $jumlahJalurAktif = $lembagaList->sum('jalurAktifCount');
        $jenjangList = $lembagaList->pluck('lembaga.bentuk_pendidikan')->filter()->unique()->sort()->values();

        $gelombangTerdekat = \App\Models\GelombangPpdb::where('tanggal_buka', '<=', now())
            ->where('tanggal_tutup', '>=', now())
            ->with('lembaga')
            ->orderBy('tanggal_tutup')
            ->first();

        $yayasan = Lembaga::with('yayasan')->first()?->yayasan;

        return view('spmb.welcome', [
            'lembagaList' => $lembagaList,
            'jumlahLembaga' => $lembagaList->count(),
            'jumlahSedangBuka' => $jumlahSedangBuka,
            'jumlahJalurAktif' => $jumlahJalurAktif,
            'jenjangList' => $jenjangList,
            'gelombangTerdekat' => $gelombangTerdekat,
            'yayasan' => $yayasan,
        ]);
    }
}
```

Confirmed by reading `app/Models/NominalTagihanJalur.php`: it has a `jalurPpdb(): BelongsTo` relation exactly as used above. `Lembaga` has no `tahunAjaran()` relation (confirmed by reading `app/Models/Lembaga.php`), which is why the controller queries `TahunAjaran` directly by `lembaga_id` instead — the same pattern the existing `PortalController` already uses.

- [ ] **Step 7: Extend the shared icon component**

The design needs three icons not yet registered in `resources/views/components/icon.blade.php`: a right-pointing arrow (card CTAs), a small chevron (breadcrumb separator), and an info glyph (tip callouts). Add these three `@case` blocks inside the existing `@switch($name)`, immediately before the closing `@endswitch` (after the last existing case, `warning`), following the file's exact existing style (Material-Symbols-style snake_case name, `viewBox="0 0 24 24"`, `stroke="currentColor"`, `{{ $attributes }}` passthrough):

```blade
    @case('arrow_forward')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        @break

    @case('chevron_right')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M9 6l6 6-6 6"/></svg>
        @break

    @case('info')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 8h.01"/></svg>
        @break
```

- [ ] **Step 8: Write `spmb.welcome` view**

```php
<?php
// resources/views/spmb/welcome.blade.php
```

```blade
<x-layouts.portal-public title="Selamat Datang" active="beranda" :yayasan="$yayasan">
    <header class="border-b border-gray-200 bg-gradient-to-br from-gray-50 via-white to-portal-50/40">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-2 lg:px-10 lg:py-16">
            <div>
                <span class="mb-5 inline-flex items-center gap-2 rounded-full bg-portal-50 px-3.5 py-1.5 text-[11.5px] font-bold uppercase tracking-wide text-portal-500">
                    <span class="h-1.5 w-1.5 rounded-full bg-success-500"></span>
                    Penerimaan Siswa Baru {{ now()->year }}/{{ now()->year + 1 }}
                </span>
                <h1 class="text-balance text-3xl font-bold leading-tight text-gray-900 sm:text-4xl">
                    Satu Akun untuk <span class="text-portal-500">Semua Pendaftaran</span> di {{ $yayasan?->nama ?? 'Yayasan' }}
                </h1>
                <p class="mt-4 max-w-md text-[14.5px] leading-relaxed text-gray-500">
                    Pilih lembaga, buat akun, dan ikuti seluruh proses pendaftaran — data diri, dokumen, hingga pembayaran — dalam satu portal yang bisa dipantau kapan saja.
                </p>
                <div class="mt-7 flex flex-wrap gap-3">
                    <a href="#lembaga" class="rounded-lg bg-portal-500 px-6 py-3.5 text-[13.5px] font-semibold text-white">Lihat Lembaga &amp; Jalur</a>
                    <a href="#lembaga" class="rounded-lg border border-gray-300 bg-white px-6 py-3.5 text-[13.5px] font-semibold text-portal-500">Cek Status Pendaftaran</a>
                </div>
            </div>

            <div class="rounded-[20px] bg-gradient-to-br from-portal-600 to-portal-500 p-7 text-white shadow-elevated">
                <p class="mb-1.5 text-[11px] font-bold uppercase tracking-wide text-portal-100">Ringkasan Penerimaan</p>
                <p class="mb-5 text-lg font-bold">Tahun Ajaran {{ now()->year }}/{{ now()->year + 1 }}</p>
                <div class="mb-5 grid grid-cols-3 gap-2.5">
                    <div class="rounded-xl border border-white/15 bg-white/10 p-3.5 text-center">
                        <p class="text-[22px] font-bold tabular-nums">{{ $jumlahLembaga }}</p>
                        <p class="text-[10.5px] uppercase text-portal-100">Lembaga</p>
                    </div>
                    <div class="rounded-xl border border-white/15 bg-white/10 p-3.5 text-center">
                        <p class="text-[22px] font-bold tabular-nums">{{ $jumlahSedangBuka }}</p>
                        <p class="text-[10.5px] uppercase text-portal-100">Sedang Buka</p>
                    </div>
                    <div class="rounded-xl border border-white/15 bg-white/10 p-3.5 text-center">
                        <p class="text-[22px] font-bold tabular-nums">{{ $jumlahJalurAktif }}</p>
                        <p class="text-[10.5px] uppercase text-portal-100">Jalur Aktif</p>
                    </div>
                </div>
                @if ($gelombangTerdekat)
                    <div class="mb-5 rounded-xl border border-white/15 bg-white/10 p-3.5 text-[11.5px] leading-relaxed text-portal-100">
                        <span class="font-bold text-white">{{ $gelombangTerdekat->lembaga->nama }}</span> — {{ $gelombangTerdekat->nama }} tutup {{ $gelombangTerdekat->tanggal_tutup->translatedFormat('d F Y') }}
                    </div>
                @endif
                <a href="#lembaga" class="flex w-full items-center justify-center gap-2 rounded-[10px] bg-white px-4 py-3.5 text-[13.5px] font-bold text-portal-500">
                    Mulai Pendaftaran
                    <x-icon name="arrow_forward" class="h-3.5 w-3.5" />
                </a>
            </div>
        </div>
    </header>

    <section id="lembaga" x-data="{ jenjang: 'semua' }" class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-10">
        <div class="mx-auto mb-8 max-w-xl text-center">
            <p class="mb-2.5 text-[11.5px] font-bold uppercase tracking-wide text-portal-500">Lembaga Pendidikan</p>
            <h2 class="text-2xl font-bold text-gray-900">Pilih Lembaga Tujuanmu</h2>
            <p class="mt-2 text-[13.5px] text-gray-500">Setiap lembaga punya jalur, biaya, dan jadwal seleksi masing-masing.</p>
        </div>

        <div class="mb-7 flex flex-wrap justify-center gap-2">
            <button
                type="button"
                @click="jenjang = 'semua'"
                :class="jenjang === 'semua' ? 'bg-portal-500 text-white' : 'border border-gray-200 bg-white text-gray-500'"
                class="rounded-full px-4 py-2 text-[12.5px] font-semibold transition"
            >Semua</button>
            @foreach ($jenjangList as $jenjangItem)
                <button
                    type="button"
                    @click="jenjang = '{{ $jenjangItem }}'"
                    :class="jenjang === '{{ $jenjangItem }}' ? 'bg-portal-500 text-white' : 'border border-gray-200 bg-white text-gray-500'"
                    class="rounded-full px-4 py-2 text-[12.5px] font-semibold transition"
                >{{ $jenjangItem }}</button>
            @endforeach
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($lembagaList as $item)
                @php $lembaga = $item['lembaga']; @endphp
                <a
                    x-show="jenjang === 'semua' || jenjang === '{{ $lembaga->bentuk_pendidikan }}'"
                    href="{{ route('spmb.index', ['lembagaSlug' => $lembaga->slug]) }}"
                    class="flex flex-col gap-3.5 rounded-2xl border border-gray-200 bg-white p-5 shadow-card transition hover:-translate-y-0.5 hover:shadow-elevated {{ $item['gelombang'] ? '' : 'opacity-70' }}"
                >
                    <div class="flex items-start justify-between gap-2.5">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-portal-50 text-[13px] font-extrabold text-portal-500">
                            {{ $lembaga->bentuk_pendidikan }}
                        </span>
                        @if ($item['gelombang'])
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-success-50 px-2.5 py-1 text-[11.5px] font-bold text-success-700">
                                <x-icon name="check_circle" class="h-2.5 w-2.5" /> Dibuka
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-[11.5px] font-bold text-gray-500">
                                <x-icon name="hourglass_empty" class="h-2.5 w-2.5" /> Ditutup
                            </span>
                        @endif
                    </div>
                    <div>
                        <p class="text-[16px] font-bold text-gray-900">{{ $lembaga->nama }}</p>
                        <p class="text-[12px] text-gray-400">{{ $lembaga->kecamatan }}, {{ $lembaga->kabupaten_kota }}</p>
                    </div>
                    <div class="flex gap-4 border-y border-dashed border-gray-200 py-3">
                        <div class="flex-1">
                            <p class="text-[13px] font-bold text-portal-500">{{ $item['jalurAktifCount'] }}</p>
                            <p class="text-[10.5px] uppercase text-gray-400">Jalur</p>
                        </div>
                        <div class="flex-1">
                            <p class="text-[13px] font-bold text-portal-500">
                                {{ $item['biayaTermurah'] !== null ? 'Rp'.number_format($item['biayaTermurah'], 0, ',', '.') : '—' }}
                            </p>
                            <p class="text-[10.5px] uppercase text-gray-400">Biaya Daftar</p>
                        </div>
                        <div class="flex-1">
                            <p class="text-[13px] font-bold text-portal-500">{{ $item['gelombang']?->tanggal_tutup->translatedFormat('d M') ?? '—' }}</p>
                            <p class="text-[10.5px] uppercase text-gray-400">Tutup</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-[13px] font-bold text-portal-500">
                        Lihat Jalur <x-icon name="arrow_forward" class="h-3.5 w-3.5" />
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    <section id="alur" class="bg-gray-100 px-4 py-12 sm:px-6 lg:px-10">
        <div class="mx-auto mb-8 max-w-xl text-center">
            <p class="mb-2.5 text-[11.5px] font-bold uppercase tracking-wide text-portal-500">Cara Kerja</p>
            <h2 class="text-2xl font-bold text-gray-900">Alur Pendaftaran</h2>
            <p class="mt-2 text-[13.5px] text-gray-500">Empat langkah dari daftar akun sampai menunggu hasil seleksi.</p>
        </div>
        <div class="mx-auto grid max-w-7xl gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Daftar Akun', 'Buat akun dengan email & password, verifikasi lewat kode yang dikirim.'],
                ['Isi Data & Dokumen', 'Lengkapi data diri, formulir jalur, dan unggah berkas yang disyaratkan.'],
                ['Bayar Biaya Daftar', 'Bayar biaya pendaftaran dan unggah bukti transfer.'],
                ['Ikuti Seleksi', 'Pantau jadwal tes dan hasil seleksi langsung dari dashboard.'],
            ] as $index => [$judul, $deskripsi])
                <div class="rounded-2xl border border-gray-200 bg-white p-5">
                    <span class="mb-3.5 flex h-8 w-8 items-center justify-center rounded-lg bg-portal-500 text-[13px] font-bold text-white">{{ $index + 1 }}</span>
                    <h4 class="mb-1.5 text-[14px] font-bold text-gray-900">{{ $judul }}</h4>
                    <p class="text-[12px] leading-relaxed text-gray-400">{{ $deskripsi }}</p>
                </div>
            @endforeach
        </div>
    </section>
</x-layouts.portal-public>
```

- [ ] **Step 9: Add the `spmb.welcome` route**

`GET /spmb` (no extra path segment) and `GET /spmb/{lembagaSlug}` (exactly one extra segment) are different path shapes, so there's no route-matching ambiguity between them. Add this line as the FIRST route inside the `Route::prefix('spmb')->name('spmb.')->group(...)` block, before the `{lembagaSlug}` route, purely as the clearest reading order (most specific/least-nested route first):

```php
use App\Http\Controllers\Spmb\WelcomeController;
```

(add to the `use` statements at the top of the file, alongside the existing ones)

```php
Route::get('/', [WelcomeController::class, 'index'])->name('welcome');
```

(add as the first line inside the `Route::prefix('spmb')->name('spmb.')->group(function () { ... })` closure)

- [ ] **Step 10: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Spmb/WelcomeControllerTest.php`
Expected: PASS (6/6)

- [ ] **Step 11: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all pre-existing tests still pass (this task only adds a new route/controller/views/component — it does not modify `PortalController` or any existing route, so nothing pre-existing should break).

- [ ] **Step 12: Commit**

```bash
git add resources/views/components/icon.blade.php resources/views/components/portal-navbar.blade.php resources/views/components/portal-footer.blade.php resources/views/layouts/portal-public.blade.php app/Http/Controllers/Spmb/WelcomeController.php resources/views/spmb/welcome.blade.php routes/spmb.php tests/Feature/Spmb/WelcomeControllerTest.php
git commit -m "feat: add SPMB welcome page listing all lembaga, with the new navy portal shell"
```

---

### Task 2: Detail Lembaga & Jalur Page

**Files:**
- Modify: `app/Http/Controllers/Spmb/PortalController.php`
- Modify: `resources/views/spmb/pilih-jalur.blade.php`
- Modify: `tests/Feature/Spmb/PortalEntryTest.php`
- Test: `tests/Feature/Spmb/JalurDaftarActionTest.php`

**Interfaces:**
- Consumes: `x-portal-navbar`, `x-portal-footer`, `layouts.portal-public` (Task 1), `PortalController::cariGelombangAktif()` (existing, unchanged), `ResolvesSpmbTenant` trait (existing, unchanged).
- Produces: `spmb.index` route (existing name, existing URL, rewritten behavior — no longer renders `spmb.tertutup` for the closed case, always renders `spmb.pilih-jalur` with an open/closed state). A new route `spmb.jalur.daftar` (`POST`) handling the "Daftar Jalur Ini" session-write action, writing session keys `spmb_pilihan.lembaga_id`/`spmb_pilihan.jalur_id` — Sub-project 2 will read these two session keys.

- [ ] **Step 1: Write the failing tests**

Replace the entire contents of `tests/Feature/Spmb/PortalEntryTest.php` (the old assertions about `spmb.tertutup`/"belum dibuka" as a full-page state no longer apply — the page always renders now, just with a disabled action when closed):

```php
<?php
// tests/Feature/Spmb/PortalEntryTest.php

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

it('shows the jalur list and the active tahun ajaran for a lembaga with an open gelombang, with an enabled daftar action', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();

    $response = $this->get("/spmb/{$lembaga->slug}");

    $response->assertOk();
    $response->assertSee($jalur->nama);
    $response->assertSee($tahunAjaran->nama);
    $response->assertDontSee('Belum Dibuka');
});

it('shows jalur informationally with a disabled daftar action when no gelombang is currently open', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler', 'status_aktif' => true]);
    GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang Lalu',
        'tanggal_buka' => now()->subMonths(2), 'tanggal_tutup' => now()->subMonth(), 'kuota' => 40,
    ]);

    $response = $this->get("/spmb/{$lembaga->slug}");

    $response->assertOk();
    $response->assertSee($jalur->nama);
    $response->assertSee('Belum Dibuka');
});

it('picks the gelombang with the earliest tanggal_buka when two overlap', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombangAwal] = buatLembagaDenganGelombangBuka();
    GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang Lebih Awal',
        'tanggal_buka' => now()->subWeek(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 20,
    ]);

    $response = $this->get("/spmb/{$lembaga->slug}");

    $response->assertOk();
    $response->assertSee('Gelombang Lebih Awal');
});

it('404s for an unknown lembaga slug', function () {
    $this->get('/spmb/sekolah-tidak-ada')->assertNotFound();
});

it('only shows jalur connected to the active gelombang when that gelombang is restricted', function () {
    [$lembaga, $tahunAjaran, $jalurTerhubung, $gelombang] = buatLembagaDenganGelombangBuka();
    $jalurLain = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi', 'status_aktif' => true]);
    $gelombang->jalur()->attach($jalurTerhubung->id);

    $response = $this->get("/spmb/{$lembaga->slug}");

    $response->assertOk();
    $response->assertSee($jalurTerhubung->nama);
    $response->assertDontSee($jalurLain->nama);
});

it('shows all active jalur when the open gelombang has no restriction rows', function () {
    [$lembaga, $tahunAjaran, $jalurSatu] = buatLembagaDenganGelombangBuka();
    $jalurDua = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi', 'status_aktif' => true]);

    $response = $this->get("/spmb/{$lembaga->slug}");

    $response->assertOk();
    $response->assertSee($jalurSatu->nama);
    $response->assertSee($jalurDua->nama);
});

it('shows the real nominal, Gratis, or Menunggu Konfirmasi Admin for each biaya pendaftaran state', function () {
    [$lembaga, $tahunAjaran, $jalurBerbayar] = buatLembagaDenganGelombangBuka();
    $jalurGratis = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Afirmasi', 'status_aktif' => true]);
    $jalurBelumDikonfigurasi = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi', 'status_aktif' => true]);

    $jenisPendaftaran = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisPendaftaran->id, 'jalur_ppdb_id' => $jalurBerbayar->id, 'nominal' => 150000]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisPendaftaran->id, 'jalur_ppdb_id' => $jalurGratis->id, 'nominal' => 0]);

    $response = $this->get("/spmb/{$lembaga->slug}");

    $response->assertOk();
    $response->assertSee('Rp150.000');
    $response->assertSee('Gratis');
    $response->assertSee('Menunggu Konfirmasi Admin');
});

it('links to the existing CekStatusController status form for this lembaga', function () {
    [$lembaga] = buatLembagaDenganGelombangBuka();

    $this->get("/spmb/{$lembaga->slug}")
        ->assertOk()
        ->assertSee(route('spmb.status.form', ['lembagaSlug' => $lembaga->slug]), false);
});

it('does not show jalur belonging to an inactive tahun ajaran', function () {
    [$lembaga, , $jalurAktif] = buatLembagaDenganGelombangBuka();
    $tahunAjaranLama = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2024/2025',
        'tanggal_mulai' => '2024-07-01', 'tanggal_selesai' => '2025-06-30', 'status_aktif' => false,
    ]);
    $jalurLama = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaranLama->id, 'nama' => 'Jalur Tahun Lalu', 'status_aktif' => true]);

    $response = $this->get("/spmb/{$lembaga->slug}");

    $response->assertOk();
    $response->assertSee($jalurAktif->nama);
    $response->assertDontSee($jalurLama->nama);
});
```

```php
<?php
// tests/Feature/Spmb/JalurDaftarActionTest.php

it('stores the chosen lembaga and jalur in session and redirects back with a coming-soon message when spmb.register does not exist yet', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();

    $response = $this->post(route('spmb.jalur.daftar', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]));

    $response->assertRedirect(route('spmb.index', ['lembagaSlug' => $lembaga->slug]));
    $response->assertSessionHas('spmb_pilihan.lembaga_id', $lembaga->id);
    $response->assertSessionHas('spmb_pilihan.jalur_id', $jalur->id);
    $response->assertSessionHas('status');
});

it('404s if the jalur does not belong to the lembaga in the URL', function () {
    [$lembagaSatu] = buatLembagaDenganGelombangBuka();
    [$lembagaDua, , $jalurDua] = buatLembagaDenganGelombangBuka();

    $this->post(route('spmb.jalur.daftar', ['lembagaSlug' => $lembagaSatu->slug, 'jalur' => $jalurDua->id]))
        ->assertNotFound();
});

it('404s if the lembaga has no currently-open gelombang', function () {
    $yayasan = \App\Models\Yayasan::factory()->create();
    $lembaga = \App\Models\Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = \App\Models\TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = \App\Models\JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler', 'status_aktif' => true]);

    $this->post(route('spmb.jalur.daftar', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]))
        ->assertNotFound();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Spmb/PortalEntryTest.php tests/Feature/Spmb/JalurDaftarActionTest.php`
Expected: FAIL — `PortalEntryTest` fails because the current controller/view don't match the new assertions (e.g. still shows `spmb.tertutup` instead of a disabled button); `JalurDaftarActionTest` fails because route `spmb.jalur.daftar` doesn't exist (404 on POST, or a route-not-found exception).

- [ ] **Step 3: Rewrite `PortalController`**

```php
<?php
// app/Http/Controllers/Spmb/PortalController.php

namespace App\Http\Controllers\Spmb;

use App\Http\Controllers\Spmb\Concerns\ResolvesSpmbTenant;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use App\Models\SeleksiPpdb;
use App\Models\TahunAjaran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class PortalController extends BaseController
{
    use ResolvesSpmbTenant;

    public function index(Request $request, string $lembagaSlug): View
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $tahunAjaranAktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();
        $gelombang = static::cariGelombangAktif($lembaga);

        if (! $tahunAjaranAktif) {
            $jalurList = collect();
        } else {
            $jalurQuery = JalurPpdb::where('status_aktif', true)->where('tahun_ajaran_id', $tahunAjaranAktif->id);

            if ($gelombang && $gelombang->jalur()->exists()) {
                $jalurQuery->whereHas('gelombang', fn ($q) => $q->whereKey($gelombang->id));
            }

            $jalurList = $jalurQuery->orderBy('id')->get()->map(function (JalurPpdb $jalur) use ($lembaga, $gelombang) {
                $jenisPendaftaran = JenisTagihan::where('lembaga_id', $lembaga->id)->where('kategori', 'pendaftaran')->first();
                $nominal = $jenisPendaftaran
                    ? NominalTagihanJalur::where('jenis_tagihan_id', $jenisPendaftaran->id)->where('jalur_ppdb_id', $jalur->id)->first()
                    : null;

                $tesList = $gelombang
                    ? SeleksiPpdb::where('jalur_ppdb_id', $jalur->id)->where('gelombang_ppdb_id', $gelombang->id)->with('jenisTesMaster')->get()
                    : collect();

                return [
                    'jalur' => $jalur,
                    'featured' => $jalur->nama === 'Reguler',
                    'nominal' => $nominal,
                    'tesList' => $tesList,
                    'kuota' => $gelombang?->kuota,
                ];
            });
        }

        return view('spmb.pilih-jalur', [
            'lembaga' => $lembaga,
            'tahunAjaranAktif' => $tahunAjaranAktif,
            'gelombang' => $gelombang,
            'jalurList' => $jalurList,
        ]);
    }

    public function daftarJalur(Request $request, string $lembagaSlug, JalurPpdb $jalur): RedirectResponse
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $this->assertJalurBelongsToLembaga($lembaga, $jalur);

        $gelombang = static::cariGelombangAktif($lembaga);
        abort_if(! $gelombang, 404);

        $request->session()->put('spmb_pilihan.lembaga_id', $lembaga->id);
        $request->session()->put('spmb_pilihan.jalur_id', $jalur->id);

        if (Route::has('spmb.register')) {
            return redirect()->route('spmb.register');
        }

        return redirect()
            ->route('spmb.index', ['lembagaSlug' => $lembaga->slug])
            ->with('status', 'Fitur pendaftaran akan segera hadir.');
    }

    public static function cariGelombangAktif(Lembaga $lembaga): ?GelombangPpdb
    {
        $tahunAjaranAktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();

        if (! $tahunAjaranAktif) {
            return null;
        }

        return GelombangPpdb::where('tahun_ajaran_id', $tahunAjaranAktif->id)
            ->where('tanggal_buka', '<=', now())
            ->where('tanggal_tutup', '>=', now())
            ->orderBy('tanggal_buka')
            ->first();
    }
}
```

Confirmed by reading `app/Models/SeleksiPpdb.php`: it has a `jenisTesMaster(): BelongsTo` relation exactly as used above (`belongsTo(JenisTesMaster::class, 'jenis_tes_master_id')`).

- [ ] **Step 4: Add the `spmb.jalur.daftar` route**

In `routes/spmb.php`, add this line right after the existing `Route::get('{lembagaSlug}', [PortalController::class, 'index'])->name('index');` line:

```php
Route::post('{lembagaSlug}/jalur/{jalur}/daftar', [PortalController::class, 'daftarJalur'])->name('jalur.daftar');
```

- [ ] **Step 5: Rewrite `spmb.pilih-jalur` view**

```php
<?php
// resources/views/spmb/pilih-jalur.blade.php
```

```blade
<x-layouts.portal-public title="{{ $lembaga->nama }}" active="lembaga" :yayasan="$lembaga->yayasan">
    <div class="mx-auto flex max-w-7xl items-center gap-2 px-4 pt-4 text-[12.5px] text-gray-400 sm:px-6 lg:px-10">
        <a href="{{ route('spmb.welcome') }}">Beranda</a>
        <x-icon name="chevron_right" class="h-2.5 w-2.5" />
        <span class="font-semibold text-portal-500">{{ $lembaga->nama }}</span>
    </div>

    <div class="mx-auto mt-5 grid max-w-7xl gap-5 rounded-[20px] bg-gradient-to-br from-portal-600 to-portal-500 p-6 text-white sm:grid-cols-[auto,1fr,auto] sm:items-center sm:p-7 mx-4 sm:mx-6 lg:mx-10">
        <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10 text-[15px] font-extrabold">
            {{ $lembaga->bentuk_pendidikan }}
        </span>
        <div>
            <h1 class="text-xl font-bold">{{ $lembaga->nama }}</h1>
            <div class="mt-1.5 flex flex-wrap gap-4 text-[12.5px] text-portal-100">
                <span>{{ $lembaga->kecamatan }}, {{ $lembaga->kabupaten_kota }}</span>
                @if ($lembaga->akreditasi)
                    <span>Akreditasi {{ $lembaga->akreditasi }}</span>
                @endif
                @if ($tahunAjaranAktif)
                    <span>Tahun Ajaran {{ $tahunAjaranAktif->nama }}</span>
                @endif
            </div>
        </div>
        <div class="text-left sm:text-right">
            @if ($gelombang)
                <span class="mb-2 inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-[11.5px] font-bold">
                    <x-icon name="check_circle" class="h-2.5 w-2.5" /> {{ $gelombang->nama }} Dibuka
                </span>
                <p class="text-[11.5px] text-portal-100">Tutup {{ $gelombang->tanggal_tutup->translatedFormat('d F Y') }}</p>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-[11.5px] font-bold">
                    <x-icon name="hourglass_empty" class="h-2.5 w-2.5" /> Ditutup
                </span>
            @endif
        </div>
    </div>

    <p class="mx-4 mt-3 max-w-7xl text-center text-[12px] sm:mx-6 lg:mx-10">
        <a href="{{ route('spmb.status.form', ['lembagaSlug' => $lembaga->slug]) }}" class="text-gray-500 underline">Cek status pendaftaran di lembaga ini</a>
    </p>

    <div class="mx-4 mt-5 flex gap-2.5 rounded-2xl bg-portal-50 p-3.5 text-[12px] leading-relaxed text-portal-500 sm:mx-6 lg:mx-10">
        <x-icon name="info" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
        <span>Jalur yang kamu pilih di sini otomatis tersimpan sampai pendaftaranmu berhasil dikirim.</span>
    </div>

    <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-10">
        <div class="mx-auto mb-8 max-w-xl text-center">
            <p class="mb-2.5 text-[11.5px] font-bold uppercase tracking-wide text-portal-500">Jalur Pendaftaran</p>
            <h2 class="text-2xl font-bold text-gray-900">Pilih Jalur yang Sesuai</h2>
            <p class="mt-2 text-[13.5px] text-gray-500">Setiap jalur punya syarat tes dan biaya pendaftaran yang berbeda.</p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($jalurList as $item)
                @php $jalur = $item['jalur']; @endphp
                <div class="relative flex flex-col gap-4 rounded-[18px] border p-6 {{ $item['featured'] ? 'border-portal-500 shadow-elevated' : 'border-gray-200 shadow-card' }}">
                    @if ($item['featured'])
                        <span class="absolute -top-2.5 left-6 rounded-full bg-portal-500 px-3 py-1 text-[10.5px] font-bold uppercase tracking-wide text-white">Paling Umum</span>
                    @endif

                    <div>
                        <h3 class="text-[17px] font-bold text-gray-900">{{ $jalur->nama }}</h3>
                        @if ($jalur->deskripsi)
                            <p class="mt-1.5 text-[12.5px] leading-relaxed text-gray-500">{{ $jalur->deskripsi }}</p>
                        @endif
                    </div>

                    <div class="flex items-center justify-between border-t border-dashed border-gray-200 pt-2.5 text-[12.5px]">
                        <span class="text-gray-400">Kuota</span>
                        <span class="font-bold text-gray-900">{{ $item['kuota'] !== null ? $item['kuota'].' siswa' : 'Belum ada gelombang buka' }}</span>
                    </div>

                    <div class="flex items-center justify-between border-t border-dashed border-gray-200 pt-2.5 text-[12.5px]">
                        <span class="text-gray-400">Tahap Seleksi</span>
                        @if ($item['tesList']->isNotEmpty())
                            <span class="flex flex-wrap justify-end gap-1.5">
                                @foreach ($item['tesList'] as $seleksi)
                                    <span class="rounded-full bg-portal-50 px-2.5 py-1 text-[11px] font-semibold text-portal-500">{{ $seleksi->jenisTesMaster->nama }}</span>
                                @endforeach
                            </span>
                        @else
                            <span class="font-semibold text-gray-400">Tanpa tes tambahan</span>
                        @endif
                    </div>

                    @php $nominal = $item['nominal']; @endphp
                    <div class="rounded-xl bg-gray-50 p-3.5 text-center">
                        <p class="mb-1 text-[10.5px] uppercase tracking-wide text-gray-400">Biaya Pendaftaran</p>
                        @if ($nominal === null)
                            <p class="text-[13px] font-bold text-warning-700">Menunggu Konfirmasi Admin</p>
                        @elseif ((float) $nominal->nominal === 0.0)
                            <p class="text-[20px] font-bold text-success-700">Gratis</p>
                        @else
                            <p class="text-[20px] font-bold tabular-nums text-portal-500">Rp{{ number_format($nominal->nominal, 0, ',', '.') }}</p>
                        @endif
                    </div>

                    @if ($gelombang)
                        <form method="POST" action="{{ route('spmb.jalur.daftar', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]) }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-[10px] bg-portal-500 px-4 py-3 text-[13px] font-bold text-white">
                                Daftar Jalur Ini
                                <x-icon name="arrow_forward" class="h-3.5 w-3.5" />
                            </button>
                        </form>
                    @else
                        <button type="button" disabled class="flex w-full cursor-not-allowed items-center justify-center gap-2 rounded-[10px] bg-gray-200 px-4 py-3 text-[13px] font-bold text-gray-500">
                            Belum Dibuka
                        </button>
                    @endif
                </div>
            @empty
                <p class="col-span-full text-center text-[13px] text-gray-400">Belum ada jalur pendaftaran untuk lembaga ini.</p>
            @endforelse
        </div>
    </section>
</x-layouts.portal-public>
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Spmb/PortalEntryTest.php tests/Feature/Spmb/JalurDaftarActionTest.php`
Expected: PASS (11/11)

- [ ] **Step 7: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all tests pass. Pay special attention to any test that referenced `spmb.tertutup` view or the old "closed lembaga = separate page" behavior elsewhere in the suite (grep the test suite for `spmb.tertutup` or `'belum dibuka'` before this step if the full-suite run surfaces unexpected failures) — update any such test to match the new single-page behavior, following the same pattern as this task's own `PortalEntryTest` rewrite.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Spmb/PortalController.php resources/views/spmb/pilih-jalur.blade.php tests/Feature/Spmb/PortalEntryTest.php tests/Feature/Spmb/JalurDaftarActionTest.php
git commit -m "feat: redesign Detail Lembaga & Jalur page to the navy portal shell, add Daftar Jalur Ini session action"
```

---

## Post-Plan Note

After Task 2, the SPMB entry flow's first two screens (Welcome, Detail Lembaga & Jalur) are live on the new navy portal design, fully responsive, preserving the existing gelombang-restriction and no-open-gelombang business rules, and correctly reflecting all three `NominalTagihanJalur` states (real nominal / gratis / belum dikonfigurasi). The "Daftar Jalur Ini" action writes `spmb_pilihan.lembaga_id`/`spmb_pilihan.jalur_id` to session and gracefully no-ops (with a "coming soon" message) until Sub-project 2 defines `spmb.register`. Sub-project 2 (Registrasi Akun Baru + Verifikasi), Sub-project 3 (Dashboard & Wizard Ter-otentikasi), and Sub-project 4 (Pembayaran Biaya Pendaftaran & Info Jadwal Tes) are separate, not-yet-started plans — see the visual mockup at https://claude.ai/code/artifact/a1987ae5-0050-440d-af88-08cfe01415af (screens 3-7) for their intended direction.
