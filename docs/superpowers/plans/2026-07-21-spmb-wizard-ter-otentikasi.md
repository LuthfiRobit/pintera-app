# SPMB Wizard Ter-otentikasi (Sub-project 3b) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the 4-stage SPMB wizard (Data Diri → Formulir Tambahan → Dokumen → Review/Submit) from an anonymous, session-only flow under `/spmb/{lembagaSlug}/{jalur}/*` to an authenticated flow under `/portal/wizard/*`, linking every `Pendaftaran` directly to the logged-in `AkunPendaftar` at creation time instead of via a post-hoc email lookup, and redesigning all 4 stages to the navy portal visual system.

**Architecture:** Wizard context (which `Lembaga`/`JalurPpdb` is being registered for) moves from URL route-model-binding to a new `ResolvesWizardContext` trait that reads `session('spmb_pilihan.*')` (already written by Sub-project 1's `PortalController::daftarJalur()`). The 4 existing wizard controllers (`DataDiriController`, `FormulirTambahanController`, `UploadDokumenController`, `ReviewSubmitController`) stay in their current `App\Http\Controllers\Spmb` namespace and keep using `PendaftaranWizardSession` unchanged — only how they resolve `$lembaga`/`$jalur` and how `Pendaftaran` gets linked to an account changes. New Blade components (`<x-layouts.portal-wizard>`, `<x-portal-wizard-stepper>`, `<x-portal-wizard-sidebar>`, `<x-portal-authenticated-navbar>`) provide the shared shell. `VerifikasiEmailController` (wizard's own OTP step) is deleted outright since the user is already verified+logged-in via Sub-project 2 before reaching the wizard.

**Tech Stack:** Laravel 12, Blade, Tailwind CSS (navy `portal-*`/`gray-*`/`success-*`/`warning-*`/`error-*` tokens, Outfit font), Alpine.js, Pest PHP.

## Global Constraints

- Route names: new wizard routes are nested inside the existing `Route::prefix('portal')->name('portal.')->group(...)` in `routes/portal.php`, under `Route::prefix('wizard')->name('wizard.')->middleware(['auth:portal', 'portal.verified'])`, producing names `portal.wizard.data-diri`, `portal.wizard.data-diri.cek-nik`, `portal.wizard.data-diri.store`, `portal.wizard.formulir-tambahan`(+`.store`), `portal.wizard.dokumen`(+`.store`), `portal.wizard.review`, `portal.wizard.submit`, `portal.wizard.berhasil`.
- Old routes in `routes/spmb.php` (`spmb.mulai`, `.store`, `spmb.verifikasi-otp`, `.store`, `spmb.data-diri`(+`.cek-nik`/`.store`), `spmb.formulir-tambahan`(+`.store`), `spmb.dokumen`(+`.store`), `spmb.review`, `spmb.submit`, `spmb.berhasil`) are deleted entirely, not left as dead code.
- `VerifikasiEmailController` and its 2 views (`resources/views/spmb/verifikasi-email.blade.php`, `resources/views/spmb/verifikasi-otp.blade.php` — the OLD wizard-OTP ones, NOT `resources/views/portal/auth/verifikasi-otp.blade.php` which belongs to Sub-project 2 and must not be touched) are deleted.
- `PendaftaranWizardSession` (`app/Services/PendaftaranWizardSession.php`) is NOT modified — reused exactly as-is.
- `Pendaftaran::create()` in `submit()` sets `'akun_pendaftar_id' => $akun->id` and `'email_pendaftaran' => $akun->email` directly (where `$akun = Auth::guard('portal')->user()`) — the post-hoc `AkunPendaftar::where('email', ...)->whereNotNull('email_verified_at')->first()` lookup block is deleted.
- NIK anti-hijack check compares `Pendaftaran.akun_pendaftar_id` against the logged-in account's id, not email strings.
- Gelombang-closed-mid-wizard behavior (submit fails 404 when `resolveGelombangAktifUntukJalur` finds no active gelombang) is preserved exactly as-is — not touched.
- Visual tokens: `portal-50`/`portal-500`/`portal-600`, `gray-50`…`gray-900`, `success-50/500/600/700`, `warning-50/500/600/700`, `error-50/500/600/700`, font Outfit (`font-sans`/`font-display`). Two-column wizard layout (main content + sidebar) collapses to 1 column at `≤900px` (confirmed against the mockup artifact's `.pt-wiz-main` CSS: `grid-template-columns: 1.5fr 1fr` with `@media (max-width: 900px) { grid-template-columns: 1fr; }`). Stepper switches from `justify-content: safe center` to `justify-content: flex-start` at `≤560px` (confirmed against the mockup's `.pt-stepper`/`@media (max-width: 560px)` rules).
- No "locked field" UI (mockup's read-only Nama Lengkap/No. HP pulled from the account) — replaced by a neutral "Mendaftar sebagai: [nama] · [email]" identity strip. Do not prefill or lock any Data Diri field from `AkunPendaftar`.
- Existing shared components `x-panel`, `x-input-label`, `x-input-error`, `x-spmb-text-input`, `x-spmb-primary-button`, `x-spmb-public-layout` (used by `CekStatusController`'s still-unmigrated pages) are **not** touched or deleted.

---

## Task 1: `ResolvesWizardContext` trait

**Files:**
- Create: `app/Http/Controllers/Spmb/Concerns/ResolvesWizardContext.php`
- Test: `tests/Feature/Spmb/ResolvesWizardContextTest.php`

**Interfaces:**
- Produces: `ResolvesWizardContext::resolveWizardContext(): array` returning `[Lembaga $lembaga, JalurPpdb $jalur]`, throwing `Illuminate\Http\Exceptions\HttpResponseException` wrapping a redirect to `route('portal.dashboard')` when `session('spmb_pilihan.lembaga_id')`/`session('spmb_pilihan.jalur_id')` are missing or don't resolve to real models; aborts 404 (via the composed `ResolvesSpmbTenant::assertJalurBelongsToLembaga`) when the jalur in session doesn't belong to the lembaga in session.
- Produces: `ResolvesWizardContext::resolveNominalPendaftaran(Lembaga $lembaga, JalurPpdb $jalur): ?NominalTagihanJalur` — looks up the "pendaftaran" category `JenisTagihan` for the lembaga, then the matching `NominalTagihanJalur` for the jalur; returns `null` if either is missing.
- Consumes: `App\Http\Controllers\Spmb\Concerns\ResolvesSpmbTenant::assertJalurBelongsToLembaga()` (existing, `app/Http/Controllers/Spmb/Concerns/ResolvesSpmbTenant.php`).

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Spmb/ResolvesWizardContextTest.php

use App\Http\Controllers\Spmb\Concerns\ResolvesWizardContext;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function subjekResolvesWizardContext(): object
{
    return new class
    {
        use ResolvesWizardContext;

        public function jalankan(): array
        {
            return $this->resolveWizardContext();
        }

        public function nominal($lembaga, $jalur)
        {
            return $this->resolveNominalPendaftaran($lembaga, $jalur);
        }
    };
}

it('resolves lembaga and jalur from the spmb_pilihan session', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    session(['spmb_pilihan.lembaga_id' => $lembaga->id, 'spmb_pilihan.jalur_id' => $jalur->id]);

    [$resolvedLembaga, $resolvedJalur] = subjekResolvesWizardContext()->jalankan();

    expect($resolvedLembaga->id)->toBe($lembaga->id);
    expect($resolvedJalur->id)->toBe($jalur->id);
});

it('redirects to the dashboard when the spmb_pilihan session is empty', function () {
    try {
        subjekResolvesWizardContext()->jalankan();
        $this->fail('Expected HttpResponseException to be thrown.');
    } catch (HttpResponseException $e) {
        expect($e->getResponse()->headers->get('Location'))->toBe(route('portal.dashboard'));
    }
});

it('redirects to the dashboard when the session ids do not resolve to real records', function () {
    session(['spmb_pilihan.lembaga_id' => 999999, 'spmb_pilihan.jalur_id' => 999999]);

    try {
        subjekResolvesWizardContext()->jalankan();
        $this->fail('Expected HttpResponseException to be thrown.');
    } catch (HttpResponseException $e) {
        expect($e->getResponse()->headers->get('Location'))->toBe(route('portal.dashboard'));
    }
});

it('404s when the jalur in session does not belong to the lembaga in session', function () {
    [$lembaga] = buatLembagaDenganGelombangBuka();
    [, , $jalurLain] = buatLembagaDenganGelombangBuka();
    session(['spmb_pilihan.lembaga_id' => $lembaga->id, 'spmb_pilihan.jalur_id' => $jalurLain->id]);

    subjekResolvesWizardContext()->jalankan();
})->throws(NotFoundHttpException::class);

it('resolves the nominal pendaftaran for the jalur when a jenis tagihan pendaftaran exists', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    $jenis = App\Models\JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran']);
    App\Models\NominalTagihanJalur::create(['jenis_tagihan_id' => $jenis->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 150000]);

    $nominal = subjekResolvesWizardContext()->nominal($lembaga, $jalur);

    expect((float) $nominal->nominal)->toBe(150000.0);
});

it('returns null nominal when there is no jenis tagihan pendaftaran for the lembaga', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();

    $nominal = subjekResolvesWizardContext()->nominal($lembaga, $jalur);

    expect($nominal)->toBeNull();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Spmb/ResolvesWizardContextTest.php`
Expected: FAIL — `ResolvesWizardContext` trait not found / `Trait "App\Http\Controllers\Spmb\Concerns\ResolvesWizardContext" not found`.

- [ ] **Step 3: Create the trait**

```php
<?php

namespace App\Http\Controllers\Spmb\Concerns;

use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use Illuminate\Http\Exceptions\HttpResponseException;

trait ResolvesWizardContext
{
    use ResolvesSpmbTenant;

    /**
     * @return array{0: Lembaga, 1: JalurPpdb}
     */
    protected function resolveWizardContext(): array
    {
        $lembagaId = session('spmb_pilihan.lembaga_id');
        $jalurId = session('spmb_pilihan.jalur_id');

        $lembaga = $lembagaId ? Lembaga::find($lembagaId) : null;
        $jalur = $jalurId ? JalurPpdb::find($jalurId) : null;

        if (! $lembaga || ! $jalur) {
            throw new HttpResponseException(redirect()->route('portal.dashboard'));
        }

        $this->assertJalurBelongsToLembaga($lembaga, $jalur);

        return [$lembaga, $jalur];
    }

    protected function resolveNominalPendaftaran(Lembaga $lembaga, JalurPpdb $jalur): ?NominalTagihanJalur
    {
        $jenisPendaftaran = JenisTagihan::where('lembaga_id', $lembaga->id)->where('kategori', 'pendaftaran')->first();

        if (! $jenisPendaftaran) {
            return null;
        }

        return NominalTagihanJalur::where('jenis_tagihan_id', $jenisPendaftaran->id)
            ->where('jalur_ppdb_id', $jalur->id)
            ->first();
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Spmb/ResolvesWizardContextTest.php`
Expected: PASS (6 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Spmb/Concerns/ResolvesWizardContext.php tests/Feature/Spmb/ResolvesWizardContextTest.php
git commit -m "feat: add ResolvesWizardContext trait for session-based wizard context"
```

---

## Task 2: Wizard shell UI components

**Files:**
- Modify: `resources/views/components/icon.blade.php` (add `logout` case)
- Create: `resources/views/components/portal-authenticated-navbar.blade.php`
- Create: `resources/views/components/layouts/portal-wizard.blade.php`
- Create: `resources/views/components/portal-wizard-stepper.blade.php`
- Create: `resources/views/components/portal-wizard-sidebar.blade.php`
- Test: `tests/Feature/Spmb/WizardShellComponentsTest.php`

**Interfaces:**
- Produces: `<x-portal-authenticated-navbar active="dashboard|riwayat" />` — reads `auth('portal')->user()`.
- Produces: `<x-layouts.portal-wizard :title="..." :current="data-diri|formulir-tambahan|dokumen|review" :lembaga="$lembaga" :jalur="$jalur" :nominal="$nominal">{{ $slot }}</x-layouts.portal-wizard>` — full HTML page, renders navbar, identity strip, wizard header, `<x-portal-wizard-stepper>`, a 2-column `<main>` (slot + `<x-portal-wizard-sidebar>`), and `<x-portal-footer>`.
- Produces: `<x-portal-wizard-stepper :current="..." />`.
- Produces: `<x-portal-wizard-sidebar :lembaga="$lembaga" :jalur="$jalur" :nominal="$nominal" />`.
- Consumes: `<x-icon name="...">` (existing, `resources/views/components/icon.blade.php`), `<x-portal-footer :yayasan="...">` (existing).

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Spmb/WizardShellComponentsTest.php

use App\Models\AkunPendaftar;
use Illuminate\Support\Facades\Blade;

it('renders the authenticated navbar with the logged-in akun name, nav links, and a logout form', function () {
    $akun = AkunPendaftar::factory()->create(['nama' => 'Aditya Pratama']);
    $this->actingAs($akun, 'portal');

    $html = Blade::render('<x-portal-authenticated-navbar />');

    expect($html)->toContain('Aditya')
        ->toContain(route('portal.dashboard'))
        ->toContain(route('portal.logout'))
        ->toContain('Riwayat')
        ->toContain('Bantuan');
});

it('renders the wizard stepper with all 4 stages and marks the current one active', function () {
    $html = Blade::render('<x-portal-wizard-stepper current="dokumen" />');

    expect($html)->toContain('Data Diri')
        ->toContain('Formulir Tambahan')
        ->toContain('Dokumen')
        ->toContain('Review');
});

it('renders the wizard sidebar with the jalur name, lembaga name, and a pending-confirmation biaya when nominal is null', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();

    $html = Blade::render(
        '<x-portal-wizard-sidebar :lembaga="$lembaga" :jalur="$jalur" :nominal="$nominal" />',
        ['lembaga' => $lembaga, 'jalur' => $jalur, 'nominal' => null]
    );

    expect($html)->toContain($jalur->nama)
        ->toContain($lembaga->nama)
        ->toContain('Menunggu Konfirmasi');
});

it('renders the wizard layout with the identity strip, stepper, and sidebar', function () {
    $akun = AkunPendaftar::factory()->create(['nama' => 'Aditya Pratama', 'email' => 'aditya@example.test']);
    $this->actingAs($akun, 'portal');
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();

    $html = Blade::render(
        '<x-layouts.portal-wizard title="Data Diri" current="data-diri" :lembaga="$lembaga" :jalur="$jalur" :nominal="null">Konten Uji</x-layouts.portal-wizard>',
        ['lembaga' => $lembaga, 'jalur' => $jalur]
    );

    expect($html)->toContain('Mendaftar sebagai')
        ->toContain('Aditya Pratama')
        ->toContain('aditya@example.test')
        ->toContain('Konten Uji')
        ->toContain($jalur->nama);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Spmb/WizardShellComponentsTest.php`
Expected: FAIL — components `portal-authenticated-navbar`/`portal-wizard-stepper`/`portal-wizard-sidebar`/`layouts.portal-wizard` not found.

- [ ] **Step 3: Add the `logout` icon**

In `resources/views/components/icon.blade.php`, insert this new `@case` block immediately before the final `@endswitch` on line 144 (i.e. right after the existing `@case('lock')` block ending on line 143):

```blade
    @case('logout')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
        @break
```

- [ ] **Step 4: Create the authenticated navbar component**

```blade
{{-- resources/views/components/portal-authenticated-navbar.blade.php --}}
@props(['active' => null])

<nav x-data="{ mobileOpen: false, userOpen: false }" class="sticky top-0 z-20 border-b border-gray-200 bg-white">
    <div class="mx-auto flex max-w-7xl items-center gap-6 px-4 py-4 sm:px-6 lg:px-10">
        <a href="{{ route('portal.dashboard') }}" class="mr-auto flex items-center gap-2.5">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-portal-500 to-portal-600 text-white">
                <x-icon name="school" class="h-5 w-5" />
            </span>
            <span class="leading-tight">
                <span class="block text-[15px] font-bold text-gray-900">Pintera</span>
                <span class="block text-[11px] font-semibold uppercase tracking-wide text-gray-400">Portal Calon Siswa</span>
            </span>
        </a>

        <div class="hidden items-center gap-6 text-[13.5px] font-medium text-gray-500 min-[721px]:flex">
            <a href="{{ route('portal.dashboard') }}" class="{{ $active === 'dashboard' ? 'font-bold text-portal-500' : '' }}">Dashboard</a>
            <a href="{{ route('portal.dashboard') }}" class="{{ $active === 'riwayat' ? 'font-bold text-portal-500' : '' }}">Riwayat</a>
            <a href="#">Bantuan</a>
        </div>

        <div class="relative hidden min-[721px]:block">
            <button type="button" @click="userOpen = !userOpen" @click.outside="userOpen = false" class="flex items-center gap-2.5">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-portal-500 text-[12px] font-bold text-white">
                    {{ Str::of(auth('portal')->user()->nama)->explode(' ')->map(fn ($kata) => Str::substr($kata, 0, 1))->take(2)->implode('') }}
                </span>
                <span class="text-[13px] font-semibold text-gray-900">{{ Str::of(auth('portal')->user()->nama)->before(' ') }}</span>
                <x-icon name="expand_more" class="h-3.5 w-3.5 text-gray-400" />
            </button>
            <div x-show="userOpen" x-cloak x-transition class="absolute right-0 top-full mt-2 w-44 rounded-xl border border-gray-200 bg-white py-1.5 shadow-[0_20px_44px_rgba(30,58,95,0.10)]">
                <form method="POST" action="{{ route('portal.logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-[13px] font-semibold text-gray-600 hover:bg-gray-50">
                        <x-icon name="logout" class="h-4 w-4" />
                        Keluar
                    </button>
                </form>
            </div>
        </div>

        <button type="button" @click="mobileOpen = !mobileOpen" class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-portal-500 min-[721px]:hidden" aria-label="Buka menu navigasi">
            <x-icon name="menu" class="h-5 w-5" />
        </button>
    </div>

    <div x-show="mobileOpen" x-cloak x-transition class="border-t border-gray-200 bg-white px-4 pb-4 pt-2 min-[721px]:hidden">
        <div class="flex flex-col gap-1 text-[13.5px] font-medium text-gray-600">
            <a href="{{ route('portal.dashboard') }}" class="py-2">Dashboard</a>
            <a href="{{ route('portal.dashboard') }}" class="py-2">Riwayat</a>
            <a href="#" class="py-2">Bantuan</a>
        </div>
        <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-3">
            <span class="text-[13px] font-semibold text-gray-900">{{ auth('portal')->user()->nama }}</span>
            <form method="POST" action="{{ route('portal.logout') }}">
                @csrf
                <button type="submit" class="text-[13px] font-semibold text-portal-500">Keluar</button>
            </form>
        </div>
    </div>
</nav>
```

- [ ] **Step 5: Create the wizard stepper component**

```blade
{{-- resources/views/components/portal-wizard-stepper.blade.php --}}
@props(['current'])

@php
    $stages = [
        'data-diri' => 'Data Diri',
        'formulir-tambahan' => 'Formulir Tambahan',
        'dokumen' => 'Dokumen',
        'review' => 'Review',
    ];
    $keys = array_keys($stages);
    $currentIndex = array_search($current, $keys, true);
@endphp

<div class="mx-auto mt-5 max-w-7xl px-4 sm:px-6 lg:px-10">
    <div class="flex items-center gap-3 overflow-x-auto rounded-2xl border border-gray-200 bg-white px-4 py-4 justify-[safe_center] max-[560px]:justify-start sm:px-6">
        @foreach ($stages as $key => $label)
            @php
                $index = array_search($key, $keys, true);
                $state = $index < $currentIndex ? 'done' : ($index === $currentIndex ? 'active' : 'upcoming');
            @endphp
            <div class="flex shrink-0 items-center gap-2.5">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[12px] font-bold {{ $state === 'done' ? 'bg-success-500 text-white' : ($state === 'active' ? 'bg-portal-500 text-white' : 'bg-gray-100 text-gray-400') }}">
                    @if ($state === 'done')
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    @else
                        {{ $index + 1 }}
                    @endif
                </span>
                <span class="whitespace-nowrap text-[12.5px] font-semibold {{ $state === 'active' ? 'text-portal-500' : ($state === 'done' ? 'text-gray-900' : 'text-gray-400') }}">
                    {{ $label }}
                </span>
            </div>
            @if (! $loop->last)
                <span class="h-0.5 w-8 shrink-0 {{ $index < $currentIndex ? 'bg-success-500' : 'bg-gray-200' }}"></span>
            @endif
        @endforeach
    </div>
</div>
```

- [ ] **Step 6: Create the wizard sidebar component**

```blade
{{-- resources/views/components/portal-wizard-sidebar.blade.php --}}
@props(['lembaga', 'jalur', 'nominal' => null])

<aside>
    <div class="rounded-2xl border border-gray-200 bg-white p-5">
        <p class="mb-4 text-[11px] font-bold uppercase tracking-wide text-gray-400">Pilihan Jalur</p>
        <div class="mb-4 flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-portal-50 text-portal-500">
                <x-icon name="school" class="h-5 w-5" />
            </span>
            <div class="min-w-0">
                <p class="truncate text-[14px] font-bold text-gray-900">Jalur {{ $jalur->nama }}</p>
                <p class="truncate text-[11px] text-gray-400">{{ $lembaga->nama }}</p>
            </div>
        </div>
        <div class="flex items-center justify-between border-t border-dashed border-gray-200 py-2.5 text-[12.5px]">
            <span class="text-gray-400">Biaya Pendaftaran</span>
            @if ($nominal === null)
                <span class="font-bold text-warning-700">Menunggu Konfirmasi</span>
            @elseif ((float) $nominal->nominal === 0.0)
                <span class="font-bold text-success-700">Gratis</span>
            @else
                <span class="font-bold text-gray-900">Rp{{ number_format($nominal->nominal, 0, ',', '.') }}</span>
            @endif
        </div>
    </div>

    <div class="mt-4 rounded-2xl border border-gray-200 bg-white p-5">
        <p class="mb-3 text-[11px] font-bold uppercase tracking-wide text-gray-400">Butuh Bantuan?</p>
        <p class="text-[12px] leading-relaxed text-gray-500">Data yang sudah kamu simpan otomatis tersimpan sebagai draf — kamu bisa lanjutkan kapan saja sebelum gelombang ditutup.</p>
        <a href="{{ route('portal.dashboard') }}" class="mt-3 inline-flex items-center gap-1 text-[12.5px] font-bold text-portal-500">
            Kembali ke Dashboard
            <x-icon name="arrow_forward" class="h-3 w-3" />
        </a>
    </div>
</aside>
```

- [ ] **Step 7: Create the wizard layout component**

```blade
{{-- resources/views/components/layouts/portal-wizard.blade.php --}}
@props(['title' => null, 'current' => null, 'lembaga', 'jalur', 'nominal' => null])

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? 'Formulir Pendaftaran' }} — Pintera</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-50 font-sans text-gray-900 antialiased">
        <x-portal-authenticated-navbar active="dashboard" />

        <div class="border-b border-gray-200 bg-portal-50 px-4 py-3 sm:px-6 lg:px-10">
            <div class="mx-auto flex max-w-7xl items-center gap-2 text-[12.5px] text-portal-500">
                <x-icon name="person" class="h-3.5 w-3.5" />
                <span>Mendaftar sebagai: <span class="font-bold">{{ auth('portal')->user()->nama }}</span> &middot; {{ auth('portal')->user()->email }}</span>
            </div>
        </div>

        <div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-10">
            <p class="text-[11px] font-bold uppercase tracking-wide text-portal-500">{{ $lembaga->nama }} &middot; Jalur {{ $jalur->nama }}</p>
            <h1 class="mt-1 text-xl font-bold text-gray-900">Formulir Pendaftaran</h1>
            <p class="mt-1 text-[12.5px] text-gray-500">Lengkapi setiap langkah untuk menyelesaikan pendaftaranmu.</p>
        </div>

        <x-portal-wizard-stepper :current="$current" />

        <main class="mx-auto grid max-w-7xl gap-5 px-4 py-6 sm:px-6 min-[901px]:grid-cols-[1.5fr_1fr] lg:px-10">
            <div>{{ $slot }}</div>
            <x-portal-wizard-sidebar :lembaga="$lembaga" :jalur="$jalur" :nominal="$nominal" />
        </main>

        <x-portal-footer :yayasan="$lembaga->yayasan ?? null" />
    </body>
</html>
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Spmb/WizardShellComponentsTest.php`
Expected: PASS (4 tests)

- [ ] **Step 9: Commit**

```bash
git add resources/views/components/icon.blade.php resources/views/components/portal-authenticated-navbar.blade.php resources/views/components/layouts/portal-wizard.blade.php resources/views/components/portal-wizard-stepper.blade.php resources/views/components/portal-wizard-sidebar.blade.php tests/Feature/Spmb/WizardShellComponentsTest.php
git commit -m "feat: add authenticated wizard shell components (navbar, layout, stepper, sidebar)"
```

---

## Task 3: Data Diri — route, controller, view

**Files:**
- Modify: `routes/portal.php` (add full `portal.wizard.*` route group)
- Modify: `routes/spmb.php` (remove `spmb.data-diri`+`.cek-nik`+`.store`)
- Modify: `tests/Pest.php` (add `loginAkunDenganPilihanSpmb()` helper)
- Modify: `app/Http/Controllers/Spmb/DataDiriController.php` (full rewrite)
- Modify: `resources/views/spmb/data-diri.blade.php` (full rewrite)
- Modify: `tests/Feature/Spmb/DataDiriTest.php` (full rewrite)

**Interfaces:**
- Consumes: `ResolvesWizardContext::resolveWizardContext()`/`resolveNominalPendaftaran()` (Task 1), `<x-layouts.portal-wizard>` (Task 2), `PendaftaranWizardSession` (existing, unchanged).
- Produces: routes `portal.wizard.data-diri` (GET), `portal.wizard.data-diri.cek-nik` (POST), `portal.wizard.data-diri.store` (POST) — the full route group also registers `portal.wizard.formulir-tambahan`(+`.store`), `portal.wizard.dokumen`(+`.store`), `portal.wizard.review`, `portal.wizard.submit`, `portal.wizard.berhasil` ahead of Tasks 4-7 implementing their controllers (these later routes are safe to register now since nothing in this task's tests visits them directly — only `assertRedirect(route(...))` against their names).

- [ ] **Step 1: Register the full wizard route group**

In `routes/portal.php`, add these imports after the existing `use` block (after line 11, before line 12's `use Illuminate\Support\Facades\Route;`):

```php
use App\Http\Controllers\Spmb\DataDiriController;
use App\Http\Controllers\Spmb\FormulirTambahanController;
use App\Http\Controllers\Spmb\ReviewSubmitController;
use App\Http\Controllers\Spmb\UploadDokumenController;
```

Then, inside the existing `Route::middleware(['auth:portal', 'portal.verified'])->group(function () { ... })` block (currently lines 38-47), add the new `wizard` sub-group right after the `tagihan.bayar-cicilan` route (after line 46, before the closing `});` on line 47):

```php
        Route::prefix('wizard')->name('wizard.')->group(function () {
            Route::get('data-diri', [DataDiriController::class, 'create'])->name('data-diri');
            Route::post('data-diri/cek-nik', [DataDiriController::class, 'cekNik'])->name('data-diri.cek-nik');
            Route::post('data-diri', [DataDiriController::class, 'store'])->name('data-diri.store');
            Route::get('formulir-tambahan', [FormulirTambahanController::class, 'create'])->name('formulir-tambahan');
            Route::post('formulir-tambahan', [FormulirTambahanController::class, 'store'])->name('formulir-tambahan.store');
            Route::get('dokumen', [UploadDokumenController::class, 'create'])->name('dokumen');
            Route::post('dokumen', [UploadDokumenController::class, 'store'])->name('dokumen.store');
            Route::get('review', [ReviewSubmitController::class, 'show'])->name('review');
            Route::post('submit', [ReviewSubmitController::class, 'submit'])->middleware('throttle:10,1')->name('submit');
            Route::get('berhasil/{pendaftaran}', [ReviewSubmitController::class, 'berhasil'])->name('berhasil');
        });
```

Note the `wizard.*` group deliberately has no `->middleware(['auth:portal', 'portal.verified'])` of its own — it inherits both from the enclosing group, avoiding duplicate middleware registration.

- [ ] **Step 2: Remove the old data-diri routes from `routes/spmb.php`**

In `routes/spmb.php`, delete these 3 lines (currently lines 29-31):

```php
    Route::get('{lembagaSlug}/{jalur}/data-diri', [DataDiriController::class, 'create'])->name('data-diri');
    Route::post('{lembagaSlug}/{jalur}/data-diri/cek-nik', [DataDiriController::class, 'cekNik'])->name('data-diri.cek-nik');
    Route::post('{lembagaSlug}/{jalur}/data-diri', [DataDiriController::class, 'store'])->name('data-diri.store');
```

Also remove the now-unused import on line 5:

```php
use App\Http\Controllers\Spmb\DataDiriController;
```

- [ ] **Step 3: Add the `loginAkunDenganPilihanSpmb()` test helper to `tests/Pest.php`**

This helper is shared by every wizard test file from this task onward (Tasks 3-7), so it belongs in `tests/Pest.php` alongside the existing `buatLembagaDenganGelombangBuka()`/`siapkanEmailTerverifikasi()` global helpers — not declared inside a single test file, since Pest does not guarantee one test file's functions are loaded before another's.

In `tests/Pest.php`, add this function after the existing `siapkanEmailTerverifikasi()` function (after line 79, before `buatPendaftaranUntukAdmin()` on line 81):

```php
function loginAkunDenganPilihanSpmb(Lembaga $lembaga, JalurPpdb $jalur): \App\Models\AkunPendaftar
{
    $akun = \App\Models\AkunPendaftar::factory()->create();
    session(['spmb_pilihan.lembaga_id' => $lembaga->id, 'spmb_pilihan.jalur_id' => $jalur->id]);
    test()->actingAs($akun, 'portal');

    return $akun;
}
```

- [ ] **Step 4: Write the failing tests**

```php
<?php
// tests/Feature/Spmb/DataDiriTest.php

use App\Models\AkunPendaftar;
use App\Models\CalonMurid;
use App\Models\Pendaftaran;
use App\Services\PendaftaranWizardSession;

it('shows the data diri form for a logged-in akun with a jalur in session', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->get(route('portal.wizard.data-diri'))->assertOk();
});

it('redirects to the dashboard when there is no jalur selected in session', function () {
    $akun = AkunPendaftar::factory()->create();

    $this->actingAs($akun, 'portal')->get(route('portal.wizard.data-diri'))
        ->assertRedirect(route('portal.dashboard'));
});

it('stores data diri in the wizard session and advances to formulir tambahan', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->post(route('portal.wizard.data-diri.store'), [
        'nik' => '3201234567890123',
        'nama_lengkap' => 'Ahmad Fauzan',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2015-03-10',
        'agama' => 'Islam',
        'alamat_jalan' => 'Jl. Merdeka 10',
        'desa_kelurahan' => 'Sukamaju',
        'kecamatan' => 'Cibeunying',
        'kabupaten_kota' => 'Bandung',
        'provinsi' => 'Jawa Barat',
        'keluarga' => [
            ['jenis' => 'ayah', 'nama' => 'Budi Santoso'],
            ['jenis' => 'ibu', 'nama' => 'Siti Aminah'],
        ],
    ])->assertRedirect(route('portal.wizard.formulir-tambahan'));

    $session = (new PendaftaranWizardSession())->get($lembaga, $jalur);
    expect($session['data_pribadi']['nama_lengkap'])->toBe('Ahmad Fauzan');
    expect($session['keluarga'])->toHaveCount(2);
});

it('pre-fills data diri from an existing calon murid with no prior pendaftaran at all', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);
    CalonMurid::factory()->create([
        'yayasan_id' => $lembaga->yayasan_id, 'nik' => '3201234567890999', 'nama_lengkap' => 'Nama Lama',
    ]);

    $response = $this->postJson(route('portal.wizard.data-diri.cek-nik'), ['nik' => '3201234567890999']);

    $response->assertOk();
    $response->assertSee('Nama Lama');
});

it('pre-fills data diri when nik matches a calon murid whose prior pendaftaran belongs to the same akun', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    $akun = loginAkunDenganPilihanSpmb($lembaga, $jalur);
    $calonMurid = CalonMurid::factory()->create([
        'yayasan_id' => $lembaga->yayasan_id, 'nik' => '3201234567890999', 'nama_lengkap' => 'Nama Lama',
    ]);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'akun_pendaftar_id' => $akun->id, 'email_pendaftaran' => $akun->email,
    ]);

    $response = $this->postJson(route('portal.wizard.data-diri.cek-nik'), ['nik' => '3201234567890999']);

    $response->assertOk();
    $response->assertSee('Nama Lama');
});

it('blocks the flow when nik matches a calon murid whose prior pendaftaran belongs to a different akun', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);
    $akunLain = AkunPendaftar::factory()->create();
    $calonMurid = CalonMurid::factory()->create([
        'yayasan_id' => $lembaga->yayasan_id, 'nik' => '3201234567890999', 'nama_lengkap' => 'Nama Lama',
    ]);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'akun_pendaftar_id' => $akunLain->id, 'email_pendaftaran' => $akunLain->email,
    ]);

    $response = $this->postJson(route('portal.wizard.data-diri.cek-nik'), ['nik' => '3201234567890999']);

    $response->assertStatus(422);
    $response->assertDontSee('Nama Lama');
});

it('blocks store() from writing to the session when nik matches a calon murid owned by a different akun', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);
    $akunLain = AkunPendaftar::factory()->create();
    $calonMurid = CalonMurid::factory()->create([
        'yayasan_id' => $lembaga->yayasan_id, 'nik' => '3201234567890999', 'nama_lengkap' => 'Nama Lama',
    ]);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'akun_pendaftar_id' => $akunLain->id, 'email_pendaftaran' => $akunLain->email,
    ]);

    $response = $this->post(route('portal.wizard.data-diri.store'), [
        'nik' => '3201234567890999',
        'nama_lengkap' => 'Percobaan Curi Data',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2015-03-10',
        'agama' => 'Islam',
        'alamat_jalan' => 'Jl. Merdeka 10',
        'desa_kelurahan' => 'Sukamaju',
        'kecamatan' => 'Cibeunying',
        'kabupaten_kota' => 'Bandung',
        'provinsi' => 'Jawa Barat',
        'keluarga' => [['jenis' => 'ayah', 'nama' => 'Percobaan']],
    ]);

    $response->assertSessionHasErrors('nik');

    $session = (new PendaftaranWizardSession())->get($lembaga, $jalur);
    expect($session['nik'] ?? null)->toBeNull();
    $calonMurid->refresh();
    expect($calonMurid->nama_lengkap)->toBe('Nama Lama');
});
```

- [ ] **Step 5: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Spmb/DataDiriTest.php`
Expected: FAIL — `route('portal.wizard.data-diri.store')` etc. resolve, but `DataDiriController` still expects `$lembagaSlug`/`$jalur` route params it no longer receives, so responses won't match the new session-driven behavior (e.g. old NIK-check logic still compares against session `email_pendaftaran`, which is never set).

- [ ] **Step 6: Rewrite `DataDiriController`**

```php
<?php

namespace App\Http\Controllers\Spmb;

use App\Http\Controllers\Spmb\Concerns\ResolvesWizardContext;
use App\Models\CalonMurid;
use App\Models\Pendaftaran;
use App\Services\PendaftaranWizardSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DataDiriController extends BaseController
{
    use ResolvesWizardContext;

    private const PESAN_NIK_DIBLOKIR = 'NIK ini sudah pernah terdaftar oleh akun lain. Hubungi admin sekolah untuk bantuan.';

    public function create(): View
    {
        [$lembaga, $jalur] = $this->resolveWizardContext();
        $nominal = $this->resolveNominalPendaftaran($lembaga, $jalur);

        return view('spmb.data-diri', ['lembaga' => $lembaga, 'jalur' => $jalur, 'nominal' => $nominal]);
    }

    public function cekNik(Request $request): JsonResponse
    {
        $this->resolveWizardContext();

        $data = $request->validate(['nik' => ['required', 'digits:16']]);

        $calonMurid = CalonMurid::findByNik($data['nik']);

        if (! $calonMurid) {
            return response()->json(['ditemukan' => false]);
        }

        if (! $this->calonMuridBolehDiaksesOlehAkunIni($calonMurid)) {
            return response()->json([
                'ditemukan' => true,
                'diblokir' => true,
                'pesan' => self::PESAN_NIK_DIBLOKIR,
            ], 422);
        }

        return response()->json([
            'ditemukan' => true,
            'diblokir' => false,
            'data_pribadi' => [
                'nama_lengkap' => $calonMurid->nama_lengkap,
                'nisn' => $calonMurid->nisn,
                'jenis_kelamin' => $calonMurid->jenis_kelamin,
                'tempat_lahir' => $calonMurid->tempat_lahir,
                'tanggal_lahir' => $calonMurid->tanggal_lahir->format('Y-m-d'),
                'agama' => $calonMurid->agama,
                'golongan_darah' => $calonMurid->golongan_darah,
                'no_telepon' => $calonMurid->no_telepon,
            ],
            'alamat' => $calonMurid->alamat ? $calonMurid->alamat->only([
                'alamat_jalan', 'rt', 'rw', 'dusun', 'desa_kelurahan', 'kecamatan', 'kabupaten_kota', 'provinsi', 'kode_pos',
            ]) : null,
            'keluarga' => $calonMurid->keluarga->map->only(['jenis', 'nama', 'tahun_lahir', 'pendidikan_terakhir', 'pekerjaan', 'penghasilan']),
        ]);
    }

    public function store(Request $request, PendaftaranWizardSession $wizardSession): RedirectResponse
    {
        [$lembaga, $jalur] = $this->resolveWizardContext();

        $data = $request->validate([
            'nik' => ['required', 'digits:16'],
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nisn' => ['nullable', 'string', 'max:20'],
            'no_kk' => ['nullable', 'digits:16'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'tempat_lahir' => ['required', 'string', 'max:255'],
            'tanggal_lahir' => ['required', 'date'],
            'agama' => ['required', 'string', 'max:50'],
            'golongan_darah' => ['nullable', 'string', 'max:5'],
            'no_telepon' => ['nullable', 'string', 'max:20'],
            'alamat_jalan' => ['required', 'string', 'max:255'],
            'rt' => ['nullable', 'string', 'max:5'],
            'rw' => ['nullable', 'string', 'max:5'],
            'dusun' => ['nullable', 'string', 'max:255'],
            'desa_kelurahan' => ['required', 'string', 'max:255'],
            'kecamatan' => ['required', 'string', 'max:255'],
            'kabupaten_kota' => ['required', 'string', 'max:255'],
            'provinsi' => ['required', 'string', 'max:255'],
            'kode_pos' => ['nullable', 'string', 'max:10'],
            'keluarga' => ['required', 'array', 'min:1'],
            'keluarga.*.jenis' => ['required', 'in:ayah,ibu,wali'],
            'keluarga.*.nama' => ['required', 'string', 'max:255'],
            'keluarga.*.tahun_lahir' => ['nullable', 'integer'],
            'keluarga.*.pendidikan_terakhir' => ['nullable', 'string', 'max:255'],
            'keluarga.*.pekerjaan' => ['nullable', 'string', 'max:255'],
            'keluarga.*.penghasilan' => ['nullable', 'string', 'max:255'],
            'data_periodik' => ['nullable', 'array'],
            'data_khusus' => ['nullable', 'array'],
        ]);

        $calonMuridLama = CalonMurid::findByNik($data['nik']);

        if ($calonMuridLama && ! $this->calonMuridBolehDiaksesOlehAkunIni($calonMuridLama)) {
            return back()->withErrors(['nik' => self::PESAN_NIK_DIBLOKIR])->withInput();
        }

        $wizardSession->put($lembaga, $jalur, [
            'nik' => $data['nik'],
            'data_pribadi' => collect($data)->only([
                'nama_lengkap', 'nisn', 'no_kk', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama', 'golongan_darah', 'no_telepon',
            ])->all(),
            'alamat' => collect($data)->only([
                'alamat_jalan', 'rt', 'rw', 'dusun', 'desa_kelurahan', 'kecamatan', 'kabupaten_kota', 'provinsi', 'kode_pos',
            ])->all(),
            'keluarga' => $data['keluarga'],
            'data_periodik' => $data['data_periodik'] ?? null,
            'data_khusus' => $data['data_khusus'] ?? null,
        ]);

        return redirect()->route('portal.wizard.formulir-tambahan');
    }

    private function calonMuridBolehDiaksesOlehAkunIni(CalonMurid $calonMurid): bool
    {
        $adaPendaftaranSebelumnya = Pendaftaran::where('calon_murid_id', $calonMurid->id)->exists();

        if (! $adaPendaftaranSebelumnya) {
            return true;
        }

        return Pendaftaran::where('calon_murid_id', $calonMurid->id)
            ->where('akun_pendaftar_id', Auth::guard('portal')->user()->id)
            ->exists();
    }
}
```

- [ ] **Step 7: Rewrite the Data Diri view**

```blade
{{-- resources/views/spmb/data-diri.blade.php --}}
<x-layouts.portal-wizard title="Data Diri" current="data-diri" :lembaga="$lembaga" :jalur="$jalur" :nominal="$nominal">
    <div
        x-data="dataDiriForm({
            cekNikUrl: '{{ route('portal.wizard.data-diri.cek-nik') }}',
            old: {
                nama_lengkap: @js(old('nama_lengkap', '')),
                nisn: @js(old('nisn', '')),
                jenis_kelamin: @js(old('jenis_kelamin', '')),
                tempat_lahir: @js(old('tempat_lahir', '')),
                tanggal_lahir: @js(old('tanggal_lahir', '')),
                agama: @js(old('agama', '')),
                golongan_darah: @js(old('golongan_darah', '')),
                no_telepon: @js(old('no_telepon', '')),
                alamat_jalan: @js(old('alamat_jalan', '')),
                rt: @js(old('rt', '')),
                rw: @js(old('rw', '')),
                dusun: @js(old('dusun', '')),
                desa_kelurahan: @js(old('desa_kelurahan', '')),
                kecamatan: @js(old('kecamatan', '')),
                kabupaten_kota: @js(old('kabupaten_kota', '')),
                provinsi: @js(old('provinsi', '')),
                kode_pos: @js(old('kode_pos', '')),
                keluarga: @js(old('keluarga', [])),
            },
        })"
        class="rounded-2xl border border-gray-200 bg-white p-6"
    >
        <div class="flex items-center gap-2.5">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-portal-50 text-portal-500">
                <x-icon name="person" class="h-4 w-4" />
            </span>
            <h2 class="text-[15.5px] font-bold text-gray-900">Data Diri &amp; Alamat</h2>
        </div>
        <div class="my-4 h-px bg-gray-200"></div>

        <form method="POST" action="{{ route('portal.wizard.data-diri.store') }}" class="space-y-6">
            @csrf

            <div x-show="pesanBlokir" x-cloak class="rounded-xl border border-error-500/30 bg-error-50 p-4 text-[13px] text-error-700" x-text="pesanBlokir"></div>
            @error('nik')
                <p class="text-[12px] text-error-700">{{ $message }}</p>
            @enderror

            <section class="space-y-4">
                <h3 class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Data Pribadi</h3>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">NIK</label>
                    <input type="text" name="nik" value="{{ old('nik') }}" inputmode="numeric" maxlength="16" required @blur="cekNik($event.target.value)"
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    <p x-show="checking" x-cloak class="mt-1.5 text-[11px] text-gray-400">Memeriksa NIK...</p>
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Nama Lengkap (sesuai akta)</label>
                    <input type="text" name="nama_lengkap" x-model="form.nama_lengkap" required
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    @error('nama_lengkap') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">NISN (opsional)</label>
                    <input type="text" name="nisn" x-model="form.nisn"
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Jenis Kelamin</label>
                    <select name="jenis_kelamin" x-model="form.jenis_kelamin" required
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                        <option value="">Pilih</option>
                        <option value="L">Laki-laki</option>
                        <option value="P">Perempuan</option>
                    </select>
                    @error('jenis_kelamin') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Tempat Lahir</label>
                    <input type="text" name="tempat_lahir" x-model="form.tempat_lahir" required
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    @error('tempat_lahir') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" x-model="form.tanggal_lahir" required
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    @error('tanggal_lahir') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Agama</label>
                    <input type="text" name="agama" x-model="form.agama" required
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    @error('agama') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Golongan Darah (opsional)</label>
                    <input type="text" name="golongan_darah" x-model="form.golongan_darah"
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">No. Telepon/WA Orang Tua</label>
                    <input type="text" name="no_telepon" x-model="form.no_telepon"
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                </div>
            </section>

            <section class="space-y-4">
                <h3 class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Alamat</h3>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Alamat Jalan</label>
                    <input type="text" name="alamat_jalan" x-model="form.alamat_jalan" required
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    @error('alamat_jalan') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">RT</label>
                        <input type="text" name="rt" x-model="form.rt"
                            class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    </div>
                    <div>
                        <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">RW</label>
                        <input type="text" name="rw" x-model="form.rw"
                            class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    </div>
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Dusun (opsional)</label>
                    <input type="text" name="dusun" x-model="form.dusun"
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Desa/Kelurahan</label>
                    <input type="text" name="desa_kelurahan" x-model="form.desa_kelurahan" required
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    @error('desa_kelurahan') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Kecamatan</label>
                    <input type="text" name="kecamatan" x-model="form.kecamatan" required
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    @error('kecamatan') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Kabupaten/Kota</label>
                    <input type="text" name="kabupaten_kota" x-model="form.kabupaten_kota" required
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    @error('kabupaten_kota') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Provinsi</label>
                    <input type="text" name="provinsi" x-model="form.provinsi" required
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    @error('provinsi') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Kode Pos (opsional)</label>
                    <input type="text" name="kode_pos" x-model="form.kode_pos"
                        class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                </div>
            </section>

            <section class="space-y-4">
                <h3 class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Data Orang Tua/Wali</h3>
                <template x-for="(anggota, index) in keluarga" :key="index">
                    <div class="rounded-xl border border-gray-200 p-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Jenis</label>
                                <select :name="'keluarga[' + index + '][jenis]'" x-model="anggota.jenis"
                                    class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                                    <option value="ayah">Ayah</option>
                                    <option value="ibu">Ibu</option>
                                    <option value="wali">Wali</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Nama</label>
                                <input :name="'keluarga[' + index + '][nama]'" type="text" x-model="anggota.nama"
                                    class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Pekerjaan</label>
                            <input :name="'keluarga[' + index + '][pekerjaan]'" type="text" x-model="anggota.pekerjaan"
                                class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                        </div>
                    </div>
                </template>
                <button type="button" @click="tambahWali()" class="text-[12.5px] font-bold text-portal-500">+ Tambah Wali</button>
                @error('keluarga') <p class="text-[11px] text-error-700">{{ $message }}</p> @enderror
            </section>

            <div class="flex justify-end border-t border-dashed border-gray-200 pt-5">
                <button type="submit" class="flex items-center justify-center gap-2 rounded-[10px] bg-portal-500 px-6 py-3 text-[13.5px] font-bold text-white transition hover:bg-portal-600">
                    Simpan &amp; Lanjutkan
                    <x-icon name="arrow_forward" class="h-3.5 w-3.5" />
                </button>
            </div>
        </form>
    </div>
</x-layouts.portal-wizard>
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Spmb/DataDiriTest.php`
Expected: PASS (7 tests)

- [ ] **Step 9: Commit**

```bash
git add routes/portal.php routes/spmb.php tests/Pest.php app/Http/Controllers/Spmb/DataDiriController.php resources/views/spmb/data-diri.blade.php tests/Feature/Spmb/DataDiriTest.php
git commit -m "feat: migrate Data Diri wizard step to authenticated portal.wizard.* routes"
```

---

## Task 4: Formulir Tambahan — route, controller, view

**Files:**
- Modify: `routes/spmb.php` (remove `spmb.formulir-tambahan`+`.store`)
- Modify: `app/Http/Controllers/Spmb/FormulirTambahanController.php` (full rewrite)
- Modify: `resources/views/spmb/formulir-tambahan.blade.php` (full rewrite)
- Modify: `tests/Feature/Spmb/FormulirTambahanTest.php` (full rewrite)

**Interfaces:**
- Consumes: `ResolvesWizardContext` (Task 1), `<x-layouts.portal-wizard>` (Task 2), route `portal.wizard.formulir-tambahan`(+`.store`) (already registered in Task 3), `loginAkunDenganPilihanSpmb()` helper (added to `tests/Pest.php` in Task 3, globally available to every Feature test).

- [ ] **Step 1: Remove the old formulir-tambahan routes from `routes/spmb.php`**

Delete these 2 lines:

```php
    Route::get('{lembagaSlug}/{jalur}/formulir-tambahan', [FormulirTambahanController::class, 'create'])->name('formulir-tambahan');
    Route::post('{lembagaSlug}/{jalur}/formulir-tambahan', [FormulirTambahanController::class, 'store'])->name('formulir-tambahan.store');
```

Also remove the now-unused import:

```php
use App\Http\Controllers\Spmb\FormulirTambahanController;
```

- [ ] **Step 2: Write the failing tests**

```php
<?php
// tests/Feature/Spmb/FormulirTambahanTest.php

use App\Models\AkunPendaftar;
use App\Models\FormulirField;
use App\Services\PendaftaranWizardSession;

it('shows dynamic formulir fields for the selected jalur', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Nilai Rata-rata Rapor', 'field_type' => 'number']);
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->get(route('portal.wizard.formulir-tambahan'))
        ->assertOk()
        ->assertSee('Nilai Rata-rata Rapor');
});

it('skips straight through when the jalur has no dynamic formulir fields', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->get(route('portal.wizard.formulir-tambahan'))->assertOk();
});

it('stores jawaban in the wizard session and advances to upload dokumen', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    $field = FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Nilai Rata-rata Rapor', 'field_type' => 'number']);
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->post(route('portal.wizard.formulir-tambahan.store'), [
        'jawaban' => [$field->id => '88.5'],
    ])->assertRedirect(route('portal.wizard.dokumen'));

    $session = (new PendaftaranWizardSession())->get($lembaga, $jalur);
    expect($session['jawaban_formulir'][$field->id])->toBe('88.5');
});

it('redirects to the dashboard when there is no jalur selected in session', function () {
    $akun = AkunPendaftar::factory()->create();

    $this->actingAs($akun, 'portal')->get(route('portal.wizard.formulir-tambahan'))
        ->assertRedirect(route('portal.dashboard'));
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Spmb/FormulirTambahanTest.php`
Expected: FAIL — routes exist (from Task 3) but the controller still requires `$lembagaSlug`/`$jalur`, so nothing here yet resolves via session.

- [ ] **Step 4: Rewrite `FormulirTambahanController`**

```php
<?php

namespace App\Http\Controllers\Spmb;

use App\Http\Controllers\Spmb\Concerns\ResolvesWizardContext;
use App\Models\FormulirField;
use App\Services\PendaftaranWizardSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class FormulirTambahanController extends BaseController
{
    use ResolvesWizardContext;

    public function create(): View
    {
        [$lembaga, $jalur] = $this->resolveWizardContext();
        $fieldList = FormulirField::where('jalur_ppdb_id', $jalur->id)->orderBy('urutan')->get();
        $nominal = $this->resolveNominalPendaftaran($lembaga, $jalur);

        return view('spmb.formulir-tambahan', ['lembaga' => $lembaga, 'jalur' => $jalur, 'fieldList' => $fieldList, 'nominal' => $nominal]);
    }

    public function store(Request $request, PendaftaranWizardSession $wizardSession): RedirectResponse
    {
        [$lembaga, $jalur] = $this->resolveWizardContext();

        $fieldList = FormulirField::where('jalur_ppdb_id', $jalur->id)->get();
        $rules = [];
        foreach ($fieldList as $field) {
            $rules["jawaban.{$field->id}"] = $field->is_required ? ['required'] : ['nullable'];
        }
        $data = $request->validate($rules);

        $wizardSession->put($lembaga, $jalur, ['jawaban_formulir' => $data['jawaban'] ?? []]);

        return redirect()->route('portal.wizard.dokumen');
    }
}
```

- [ ] **Step 5: Rewrite the Formulir Tambahan view**

```blade
{{-- resources/views/spmb/formulir-tambahan.blade.php --}}
<x-layouts.portal-wizard title="Formulir Tambahan" current="formulir-tambahan" :lembaga="$lembaga" :jalur="$jalur" :nominal="$nominal">
    <div class="rounded-2xl border border-gray-200 bg-white p-6">
        <div class="flex items-center gap-2.5">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-portal-50 text-portal-500">
                <x-icon name="quiz" class="h-4 w-4" />
            </span>
            <h2 class="text-[15.5px] font-bold text-gray-900">Formulir Tambahan</h2>
        </div>
        <div class="my-4 h-px bg-gray-200"></div>

        <form method="POST" action="{{ route('portal.wizard.formulir-tambahan.store') }}" class="space-y-4">
            @csrf

            @forelse ($fieldList as $field)
                <div>
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">{{ $field->label }}{{ $field->is_required ? ' *' : '' }}</label>
                    @if ($field->field_type === 'textarea')
                        <textarea name="jawaban[{{ $field->id }}]" rows="3" @required($field->is_required)
                            class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">{{ old('jawaban.' . $field->id) }}</textarea>
                    @elseif ($field->field_type === 'select')
                        <select name="jawaban[{{ $field->id }}]" @required($field->is_required)
                            class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                            <option value="">Pilih</option>
                            @foreach ($field->options ?? [] as $opsi)
                                <option value="{{ $opsi }}">{{ $opsi }}</option>
                            @endforeach
                        </select>
                    @else
                        <input
                            type="{{ $field->field_type === 'number' ? 'number' : ($field->field_type === 'date' ? 'date' : 'text') }}"
                            name="jawaban[{{ $field->id }}]"
                            @required($field->is_required)
                            class="w-full rounded-[10px] border border-gray-200 px-3.5 py-[11px] text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    @endif
                    @error('jawaban.' . $field->id) <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>
            @empty
                <p class="text-[13px] text-gray-500">Tidak ada formulir tambahan untuk jalur ini.</p>
            @endforelse

            <div class="flex justify-end border-t border-dashed border-gray-200 pt-5">
                <button type="submit" class="flex items-center justify-center gap-2 rounded-[10px] bg-portal-500 px-6 py-3 text-[13.5px] font-bold text-white transition hover:bg-portal-600">
                    Simpan &amp; Lanjutkan
                    <x-icon name="arrow_forward" class="h-3.5 w-3.5" />
                </button>
            </div>
        </form>
    </div>
</x-layouts.portal-wizard>
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Spmb/FormulirTambahanTest.php`
Expected: PASS (4 tests)

- [ ] **Step 7: Commit**

```bash
git add routes/spmb.php app/Http/Controllers/Spmb/FormulirTambahanController.php resources/views/spmb/formulir-tambahan.blade.php tests/Feature/Spmb/FormulirTambahanTest.php
git commit -m "feat: migrate Formulir Tambahan wizard step to authenticated portal.wizard.* routes"
```

---

## Task 5: Upload Dokumen — route, controller, view

**Files:**
- Modify: `routes/spmb.php` (remove `spmb.dokumen`+`.store`)
- Modify: `app/Http/Controllers/Spmb/UploadDokumenController.php` (full rewrite)
- Modify: `resources/views/spmb/upload-dokumen.blade.php` (full rewrite)
- Modify: `tests/Feature/Spmb/UploadDokumenTest.php` (full rewrite)

**Interfaces:**
- Consumes: `ResolvesWizardContext` (Task 1), `<x-layouts.portal-wizard>` (Task 2), route `portal.wizard.dokumen`(+`.store`) (already registered in Task 3), `loginAkunDenganPilihanSpmb()` helper (added to `tests/Pest.php` in Task 3, globally available to every Feature test).

- [ ] **Step 1: Remove the old dokumen routes from `routes/spmb.php`**

Delete these 2 lines:

```php
    Route::get('{lembagaSlug}/{jalur}/dokumen', [UploadDokumenController::class, 'create'])->name('dokumen');
    Route::post('{lembagaSlug}/{jalur}/dokumen', [UploadDokumenController::class, 'store'])->name('dokumen.store');
```

Also remove the now-unused import:

```php
use App\Http\Controllers\Spmb\UploadDokumenController;
```

- [ ] **Step 2: Write the failing tests**

```php
<?php
// tests/Feature/Spmb/UploadDokumenTest.php

use App\Models\AkunPendaftar;
use App\Models\DokumenSyaratPpdb;
use App\Services\PendaftaranWizardSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('shows the dokumen syarat list for the selected jalur', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->get(route('portal.wizard.dokumen'))
        ->assertOk()
        ->assertSee('Akta Kelahiran');
});

it('uploads a valid file and stores its temp path in the wizard session', function () {
    Storage::fake('public');
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    $syarat = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $file = UploadedFile::fake()->create('akta.pdf', 500, 'application/pdf');

    $this->post(route('portal.wizard.dokumen.store'), [
        'dokumen' => [$syarat->id => $file],
    ])->assertRedirect(route('portal.wizard.review'));

    $session = (new PendaftaranWizardSession())->get($lembaga, $jalur);
    expect($session['dokumen'][$syarat->id]['nama_file_asli'])->toBe('akta.pdf');
    Storage::disk('public')->assertExists($session['dokumen'][$syarat->id]['file_path']);
});

it('rejects a file that is too large or the wrong type', function () {
    Storage::fake('public');
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    $syarat = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $tooBig = UploadedFile::fake()->create('akta.pdf', 3000, 'application/pdf');

    $this->post(route('portal.wizard.dokumen.store'), [
        'dokumen' => [$syarat->id => $tooBig],
    ])->assertSessionHasErrors("dokumen.{$syarat->id}");
});

it('redirects to the dashboard when there is no jalur selected in session', function () {
    $akun = AkunPendaftar::factory()->create();

    $this->actingAs($akun, 'portal')->get(route('portal.wizard.dokumen'))
        ->assertRedirect(route('portal.dashboard'));
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Spmb/UploadDokumenTest.php`
Expected: FAIL — same reason as Task 4 (controller still expects route params).

- [ ] **Step 4: Rewrite `UploadDokumenController`**

```php
<?php

namespace App\Http\Controllers\Spmb;

use App\Http\Controllers\Spmb\Concerns\ResolvesWizardContext;
use App\Models\DokumenSyaratPpdb;
use App\Services\PendaftaranWizardSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class UploadDokumenController extends BaseController
{
    use ResolvesWizardContext;

    public function create(): View
    {
        [$lembaga, $jalur] = $this->resolveWizardContext();
        $syaratList = DokumenSyaratPpdb::where('jalur_ppdb_id', $jalur->id)->orderBy('urutan')->get();
        $nominal = $this->resolveNominalPendaftaran($lembaga, $jalur);

        return view('spmb.upload-dokumen', ['lembaga' => $lembaga, 'jalur' => $jalur, 'syaratList' => $syaratList, 'nominal' => $nominal]);
    }

    public function store(Request $request, PendaftaranWizardSession $wizardSession): RedirectResponse
    {
        [$lembaga, $jalur] = $this->resolveWizardContext();

        $syaratList = DokumenSyaratPpdb::where('jalur_ppdb_id', $jalur->id)->get();
        $rules = [];
        foreach ($syaratList as $syarat) {
            $rules["dokumen.{$syarat->id}"] = [
                $syarat->wajib ? 'required' : 'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:2048',
            ];
        }
        $request->validate($rules);

        $disimpan = $wizardSession->get($lembaga, $jalur)['dokumen'] ?? [];

        foreach ($syaratList as $syarat) {
            $file = $request->file("dokumen.{$syarat->id}");
            if (! $file) {
                continue;
            }

            $path = $file->store('pendaftaran-tmp/'.session()->getId(), 'public');

            $disimpan[$syarat->id] = [
                'file_path' => $path,
                'nama_file_asli' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'ukuran_bytes' => $file->getSize(),
            ];
        }

        $wizardSession->put($lembaga, $jalur, ['dokumen' => $disimpan]);

        return redirect()->route('portal.wizard.review');
    }
}
```

- [ ] **Step 5: Rewrite the Upload Dokumen view**

```blade
{{-- resources/views/spmb/upload-dokumen.blade.php --}}
<x-layouts.portal-wizard title="Upload Dokumen" current="dokumen" :lembaga="$lembaga" :jalur="$jalur" :nominal="$nominal">
    <div class="rounded-2xl border border-gray-200 bg-white p-6">
        <div class="flex items-center gap-2.5">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-portal-50 text-portal-500">
                <x-icon name="receipt_long" class="h-4 w-4" />
            </span>
            <h2 class="text-[15.5px] font-bold text-gray-900">Upload Dokumen</h2>
        </div>
        <div class="my-4 h-px bg-gray-200"></div>

        <form method="POST" action="{{ route('portal.wizard.dokumen.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            @forelse ($syaratList as $syarat)
                <div
                    x-data="{ namaFile: null }"
                    class="rounded-xl border-2 border-dashed p-4 transition"
                    :class="namaFile ? 'border-portal-500/40 bg-portal-50' : 'border-gray-200'"
                >
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">{{ $syarat->nama_dokumen }}{{ $syarat->wajib ? ' *' : ' (opsional)' }}</label>
                    <p class="mb-2 text-[11px] text-gray-400">PDF/JPG/PNG, maks 2MB</p>
                    <input
                        type="file"
                        name="dokumen[{{ $syarat->id }}]"
                        x-ref="input"
                        @change="namaFile = $event.target.files[0]?.name ?? null"
                        class="block w-full text-[12.5px] text-gray-500"
                        @required($syarat->wajib)
                    />
                    <p class="mt-2 flex items-center gap-2 text-[12px]" x-show="namaFile" x-cloak>
                        <span class="font-semibold text-gray-900" x-text="namaFile"></span>
                        <button type="button" class="font-semibold text-error-700" @click="$refs.input.value = ''; namaFile = null">Hapus</button>
                    </p>
                    @error('dokumen.' . $syarat->id) <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>
            @empty
                <p class="text-[13px] text-gray-500">Tidak ada dokumen yang perlu diupload untuk jalur ini.</p>
            @endforelse

            <div class="flex justify-end border-t border-dashed border-gray-200 pt-5">
                <button type="submit" class="flex items-center justify-center gap-2 rounded-[10px] bg-portal-500 px-6 py-3 text-[13.5px] font-bold text-white transition hover:bg-portal-600">
                    Simpan &amp; Lanjutkan
                    <x-icon name="arrow_forward" class="h-3.5 w-3.5" />
                </button>
            </div>
        </form>
    </div>
</x-layouts.portal-wizard>
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Spmb/UploadDokumenTest.php`
Expected: PASS (4 tests)

- [ ] **Step 7: Commit**

```bash
git add routes/spmb.php app/Http/Controllers/Spmb/UploadDokumenController.php resources/views/spmb/upload-dokumen.blade.php tests/Feature/Spmb/UploadDokumenTest.php
git commit -m "feat: migrate Upload Dokumen wizard step to authenticated portal.wizard.* routes"
```

---

## Task 6: Review & Submit — route, controller, view

**Files:**
- Modify: `routes/spmb.php` (remove `spmb.review`, `spmb.submit`)
- Modify: `app/Http/Controllers/Spmb/ReviewSubmitController.php` (rewrite `show()`, `submit()`, `redirectJikaSesiBelumLengkap()`, `buatPendaftaranDenganRetryKode()`; `berhasil()`, `pindahkanDokumenKeLokasiFinal()` untouched in this task)
- Modify: `resources/views/spmb/review.blade.php` (full rewrite)
- Modify: `tests/Feature/Spmb/ReviewSubmitTest.php` (full rewrite — `show()`/`submit()` tests only; `berhasil()` tests are added in Task 7)

**Interfaces:**
- Consumes: `ResolvesWizardContext` (Task 1), `<x-layouts.portal-wizard>` (Task 2), route `portal.wizard.review`/`portal.wizard.submit` (already registered in Task 3).
- Produces: `ReviewSubmitController::buatPendaftaranDenganRetryKode(CalonMurid $calonMurid, Lembaga $lembaga, TahunAjaran $tahunAjaran, JalurPpdb $jalur, GelombangPpdb $gelombang, KodePendaftaranGenerator $kodeGenerator, AkunPendaftar $akun): Pendaftaran` (drops the old unused `array $session` parameter and gains `AkunPendaftar $akun` — Task 7's `berhasil()` rewrite does not call this method, so no cross-task signature conflict).

- [ ] **Step 1: Remove the old review/submit routes from `routes/spmb.php`**

Delete these 3 lines:

```php
    Route::get('{lembagaSlug}/{jalur}/review', [ReviewSubmitController::class, 'show'])->name('review');
    Route::post('{lembagaSlug}/{jalur}/submit', [ReviewSubmitController::class, 'submit'])
        ->middleware('throttle:10,1')->name('submit');
```

Do **not** remove the `use App\Http\Controllers\Spmb\ReviewSubmitController;` import yet — the old `spmb.berhasil` route (removed in Task 7) still uses it.

- [ ] **Step 2: Write the failing tests**

```php
<?php
// tests/Feature/Spmb/ReviewSubmitTest.php

use App\Models\AkunPendaftar;
use App\Models\CalonMurid;
use App\Models\Pendaftaran;
use App\Services\PendaftaranWizardSession;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function isiWizardLengkap($lembaga, $jalur): void
{
    $wizardSession = new PendaftaranWizardSession();
    $wizardSession->put($lembaga, $jalur, [
        'nik' => '3201234567890123',
        'data_pribadi' => [
            'nama_lengkap' => 'Ahmad Fauzan', 'jenis_kelamin' => 'L', 'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '2015-03-10', 'agama' => 'Islam',
        ],
        'alamat' => [
            'alamat_jalan' => 'Jl. Merdeka 10', 'desa_kelurahan' => 'Sukamaju',
            'kecamatan' => 'Cibeunying', 'kabupaten_kota' => 'Bandung', 'provinsi' => 'Jawa Barat',
        ],
        'keluarga' => [['jenis' => 'ayah', 'nama' => 'Budi Santoso']],
        'jawaban_formulir' => [],
        'dokumen' => [],
    ]);
}

it('shows a review summary of everything entered so far', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);
    isiWizardLengkap($lembaga, $jalur);

    $this->get(route('portal.wizard.review'))
        ->assertOk()
        ->assertSee('Ahmad Fauzan');
});

it('submits the full pendaftaran atomically, links it directly to the logged-in akun, and clears the wizard session', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    $akun = loginAkunDenganPilihanSpmb($lembaga, $jalur);
    isiWizardLengkap($lembaga, $jalur);

    $response = $this->post(route('portal.wizard.submit'));

    $pendaftaran = Pendaftaran::first();
    $response->assertRedirect(route('portal.wizard.berhasil', ['pendaftaran' => $pendaftaran]));

    expect($pendaftaran->status)->toBe('menunggu_verifikasi');
    expect($pendaftaran->akun_pendaftar_id)->toBe($akun->id);
    expect($pendaftaran->email_pendaftaran)->toBe($akun->email);
    expect($pendaftaran->calonMurid->nama_lengkap)->toBe('Ahmad Fauzan');
    expect($pendaftaran->calonMurid->alamat->kabupaten_kota)->toBe('Bandung');
    expect($pendaftaran->calonMurid->keluarga)->toHaveCount(1);

    $session = (new PendaftaranWizardSession())->get($lembaga, $jalur);
    expect($session)->toBe([]);
});

it('reuses the existing calon murid record when the nik already exists for this yayasan', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);
    $existing = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id, 'nik' => '3201234567890123']);
    isiWizardLengkap($lembaga, $jalur);

    $this->post(route('portal.wizard.submit'));

    expect(CalonMurid::count())->toBe(1);
    expect(Pendaftaran::first()->calon_murid_id)->toBe($existing->id);
});

it('sends a confirmation email containing the kode pendaftaran', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    $akun = loginAkunDenganPilihanSpmb($lembaga, $jalur);
    isiWizardLengkap($lembaga, $jalur);

    $this->post(route('portal.wizard.submit'));

    Mail::assertSent(App\Mail\PendaftaranBerhasilMail::class, function ($mail) use ($akun) {
        return $mail->hasTo($akun->email);
    });
});

it('retries with a fresh kode when the generated kode collides with a race-condition duplicate at insert time', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);
    isiWizardLengkap($lembaga, $jalur);

    $kodeGenerator = Mockery::mock(App\Services\KodePendaftaranGenerator::class);
    $kodeGenerator->shouldReceive('generate')->once()->andReturn('REG-2026-00001');
    $kodeGenerator->shouldReceive('generate')->once()->andReturn('REG-2026-00002');
    $this->app->instance(App\Services\KodePendaftaranGenerator::class, $kodeGenerator);

    $lain = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $lain->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00001', 'email_pendaftaran' => 'lain@example.test',
    ]);

    $response = $this->post(route('portal.wizard.submit'));

    $pendaftaran = Pendaftaran::where('kode_pendaftaran', 'REG-2026-00002')->first();
    expect($pendaftaran)->not->toBeNull();
    $response->assertRedirect(route('portal.wizard.berhasil', ['pendaftaran' => $pendaftaran]));
});

afterEach(function () {
    Mockery::close();
});

it('rolls back the whole submission and never moves the file when a document row fails to insert', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $wizardSession = new PendaftaranWizardSession();
    $wizardSession->put($lembaga, $jalur, [
        'nik' => '3201234567890123',
        'data_pribadi' => [
            'nama_lengkap' => 'Ahmad Fauzan', 'jenis_kelamin' => 'L', 'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '2015-03-10', 'agama' => 'Islam',
        ],
        'alamat' => [
            'alamat_jalan' => 'Jl. Merdeka 10', 'desa_kelurahan' => 'Sukamaju',
            'kecamatan' => 'Cibeunying', 'kabupaten_kota' => 'Bandung', 'provinsi' => 'Jawa Barat',
        ],
        'keluarga' => [['jenis' => 'ayah', 'nama' => 'Budi Santoso']],
        'jawaban_formulir' => [],
        'dokumen' => [],
    ]);

    $tmpPath = 'pendaftaran-tmp/'.session()->getId().'/kartu-keluarga.pdf';
    Storage::disk('public')->put($tmpPath, 'isi dokumen palsu');

    $syaratIdTakDikenal = 999999;
    $wizardSession->put($lembaga, $jalur, [
        'dokumen' => [
            $syaratIdTakDikenal => [
                'file_path' => $tmpPath,
                'nama_file_asli' => 'kartu-keluarga.pdf',
                'mime_type' => 'application/pdf',
                'ukuran_bytes' => 18,
            ],
        ],
    ]);

    $this->post(route('portal.wizard.submit'));

    expect(Pendaftaran::count())->toBe(0);
    Storage::disk('public')->assertExists($tmpPath);
    expect(Storage::disk('public')->allFiles('pendaftaran'))->toBeEmpty();
});

it('redirects to review with a friendly message instead of a 500 when the calon murid already registered for this gelombang', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    $akun = loginAkunDenganPilihanSpmb($lembaga, $jalur);
    $calonMuridLama = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id, 'nik' => '3201234567890123']);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMuridLama->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00001', 'akun_pendaftar_id' => $akun->id, 'email_pendaftaran' => $akun->email,
    ]);
    isiWizardLengkap($lembaga, $jalur);

    $response = $this->post(route('portal.wizard.submit'));

    $response->assertRedirect(route('portal.wizard.review'));
    $response->assertSessionHasErrors('submit');
    expect(Pendaftaran::count())->toBe(1);
});

it('redirects to data-diri instead of crashing when submit is hit with an incomplete session', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $response = $this->post(route('portal.wizard.submit'));

    $response->assertRedirect(route('portal.wizard.data-diri'));
    $response->assertSessionHasErrors('sesi');
    expect(Pendaftaran::count())->toBe(0);
});

it('redirects to the dashboard when review is visited with no jalur selected in session', function () {
    $akun = AkunPendaftar::factory()->create();

    $this->actingAs($akun, 'portal')->get(route('portal.wizard.review'))
        ->assertRedirect(route('portal.dashboard'));
});

it('404s on submit when the gelombang has closed since the wizard was started (regression, not a new fix)', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);
    isiWizardLengkap($lembaga, $jalur);
    \App\Models\GelombangPpdb::where('lembaga_id', $lembaga->id)->update([
        'tanggal_buka' => now()->subMonth(), 'tanggal_tutup' => now()->subDay(),
    ]);

    $this->post(route('portal.wizard.submit'))->assertNotFound();

    expect(Pendaftaran::count())->toBe(0);
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Spmb/ReviewSubmitTest.php`
Expected: FAIL — controller still expects route params and still links the akun post-hoc by email instead of setting `akun_pendaftar_id` at `create()` time.

- [ ] **Step 4: Rewrite `show()`, `submit()`, `redirectJikaSesiBelumLengkap()`, `buatPendaftaranDenganRetryKode()` in `ReviewSubmitController`**

Replace the full contents of `app/Http/Controllers/Spmb/ReviewSubmitController.php` with:

```php
<?php

namespace App\Http\Controllers\Spmb;

use App\Http\Controllers\Spmb\Concerns\ResolvesWizardContext;
use App\Mail\PendaftaranBerhasilMail;
use App\Models\AkunPendaftar;
use App\Models\AlamatCalonMurid;
use App\Models\CalonMurid;
use App\Models\DataKhususCalonMurid;
use App\Models\DataPeriodikCalonMurid;
use App\Models\DokumenPendaftaran;
use App\Models\DokumenSyaratPpdb;
use App\Models\FormulirField;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\JawabanFormulirPendaftaran;
use App\Models\KeluargaCalonMurid;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\TahunAjaran;
use App\Services\KodePendaftaranGenerator;
use App\Services\PendaftaranWizardSession;
use App\Services\TagihanGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class ReviewSubmitController extends BaseController
{
    use ResolvesWizardContext;

    private const MAKS_PERCOBAAN_KODE = 5;

    public function show(PendaftaranWizardSession $wizardSession): View|RedirectResponse
    {
        [$lembaga, $jalur] = $this->resolveWizardContext();
        $session = $wizardSession->get($lembaga, $jalur);

        if ($redirect = $this->redirectJikaSesiBelumLengkap($session)) {
            return $redirect;
        }

        $formulirFieldList = FormulirField::where('jalur_ppdb_id', $jalur->id)->orderBy('urutan')->get();
        $dokumenSyaratList = DokumenSyaratPpdb::where('jalur_ppdb_id', $jalur->id)->orderBy('urutan')->get();
        $nominal = $this->resolveNominalPendaftaran($lembaga, $jalur);

        return view('spmb.review', [
            'lembaga' => $lembaga, 'jalur' => $jalur, 'session' => $session,
            'formulirFieldList' => $formulirFieldList, 'dokumenSyaratList' => $dokumenSyaratList, 'nominal' => $nominal,
        ]);
    }

    public function submit(
        PendaftaranWizardSession $wizardSession,
        KodePendaftaranGenerator $kodeGenerator,
        TagihanGenerator $tagihanGenerator
    ): RedirectResponse {
        [$lembaga, $jalur] = $this->resolveWizardContext();
        $session = $wizardSession->get($lembaga, $jalur);

        if ($redirect = $this->redirectJikaSesiBelumLengkap($session)) {
            return $redirect;
        }

        /** @var AkunPendaftar $akun */
        $akun = Auth::guard('portal')->user();

        try {
            $pendaftaran = DB::transaction(function () use ($lembaga, $jalur, $session, $kodeGenerator, $akun) {
                $gelombang = $this->resolveGelombangAktifUntukJalur($lembaga, $jalur);
                $tahunAjaran = $gelombang->tahunAjaran;

                $calonMurid = CalonMurid::updateOrCreate(
                    ['nik_hash' => hash('sha256', $session['nik'])],
                    array_merge(['yayasan_id' => $lembaga->yayasan_id, 'nik' => $session['nik']], $session['data_pribadi'])
                );

                AlamatCalonMurid::updateOrCreate(['calon_murid_id' => $calonMurid->id], $session['alamat']);

                KeluargaCalonMurid::where('calon_murid_id', $calonMurid->id)->delete();
                foreach ($session['keluarga'] as $anggota) {
                    KeluargaCalonMurid::create(array_merge(['calon_murid_id' => $calonMurid->id], $anggota));
                }

                if (! empty($session['data_periodik'])) {
                    DataPeriodikCalonMurid::updateOrCreate(['calon_murid_id' => $calonMurid->id], $session['data_periodik']);
                }
                if (! empty($session['data_khusus'])) {
                    DataKhususCalonMurid::updateOrCreate(['calon_murid_id' => $calonMurid->id], $session['data_khusus']);
                }

                $pendaftaran = $this->buatPendaftaranDenganRetryKode(
                    $calonMurid, $lembaga, $tahunAjaran, $jalur, $gelombang, $kodeGenerator, $akun
                );

                foreach ($session['jawaban_formulir'] ?? [] as $fieldId => $nilai) {
                    JawabanFormulirPendaftaran::create([
                        'pendaftaran_id' => $pendaftaran->id, 'formulir_field_id' => $fieldId, 'nilai' => $nilai,
                    ]);
                }

                foreach ($session['dokumen'] ?? [] as $syaratId => $berkas) {
                    DokumenPendaftaran::create([
                        'pendaftaran_id' => $pendaftaran->id,
                        'dokumen_syarat_ppdb_id' => $syaratId,
                        'file_path' => $berkas['file_path'],
                        'nama_file_asli' => $berkas['nama_file_asli'],
                        'mime_type' => $berkas['mime_type'],
                        'ukuran_bytes' => $berkas['ukuran_bytes'],
                    ]);
                }

                return $pendaftaran;
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'pendaftaran_calon_murid_id_gelombang_ppdb_id_unique')) {
                return redirect()->route('portal.wizard.review')
                    ->withErrors(['submit' => 'Anda sudah terdaftar untuk gelombang ini. Silakan cek status pendaftaran Anda.']);
            }

            throw $exception;
        }

        $this->pindahkanDokumenKeLokasiFinal($pendaftaran);

        $tagihanGenerator->generate($pendaftaran, 'pendaftaran');

        Mail::to($pendaftaran->email_pendaftaran)->send(new PendaftaranBerhasilMail($pendaftaran));

        $wizardSession->clear($lembaga, $jalur);

        return redirect()->route('portal.wizard.berhasil', ['pendaftaran' => $pendaftaran]);
    }

    private function redirectJikaSesiBelumLengkap(array $session): ?RedirectResponse
    {
        foreach (['nik', 'data_pribadi', 'alamat', 'keluarga'] as $kunci) {
            if (empty($session[$kunci])) {
                return redirect()->route('portal.wizard.data-diri')
                    ->withErrors(['sesi' => 'Data belum lengkap. Silakan lengkapi data diri terlebih dahulu.']);
            }
        }

        return null;
    }

    /**
     * Runs after the DB transaction commits, not inside it — Storage::move() is not
     * rollback-safe, so keeping it out of the transaction means a failed move never
     * orphans a file against a rolled-back Pendaftaran, and never leaves the wizard
     * session pointing at a tmp path that a mid-transaction failure already invalidated.
     * The Pendaftaran row itself is already durably committed by the time this runs.
     */
    private function pindahkanDokumenKeLokasiFinal(Pendaftaran $pendaftaran): void
    {
        foreach ($pendaftaran->dokumen as $dokumen) {
            if (! Storage::disk('public')->exists($dokumen->file_path)) {
                continue;
            }

            $tujuan = 'pendaftaran/'.$pendaftaran->id.'/'.basename($dokumen->file_path);
            Storage::disk('public')->move($dokumen->file_path, $tujuan);
            $dokumen->update(['file_path' => $tujuan]);
        }
    }

    /**
     * KodePendaftaranGenerator::generate() checks for an existing row and returns a
     * candidate code, but a second request can insert the same code in the gap between
     * that check and this create() call. Retry with a freshly generated code when the
     * (lembaga_id, kode_pendaftaran) unique constraint is the one that failed — any other
     * constraint violation (e.g. a genuine duplicate calon_murid+gelombang registration)
     * is a real, different condition and must propagate to the caller in submit().
     */
    private function buatPendaftaranDenganRetryKode(
        CalonMurid $calonMurid,
        Lembaga $lembaga,
        TahunAjaran $tahunAjaran,
        JalurPpdb $jalur,
        GelombangPpdb $gelombang,
        KodePendaftaranGenerator $kodeGenerator,
        AkunPendaftar $akun
    ): Pendaftaran {
        for ($percobaan = 0; $percobaan < self::MAKS_PERCOBAAN_KODE; $percobaan++) {
            $kode = $kodeGenerator->generate($lembaga->id);

            try {
                return Pendaftaran::create([
                    'calon_murid_id' => $calonMurid->id,
                    'lembaga_id' => $lembaga->id,
                    'tahun_ajaran_id' => $tahunAjaran->id,
                    'jalur_ppdb_id' => $jalur->id,
                    'gelombang_ppdb_id' => $gelombang->id,
                    'kode_pendaftaran' => $kode,
                    'akun_pendaftar_id' => $akun->id,
                    'email_pendaftaran' => $akun->email,
                    'submitted_at' => now(),
                ]);
            } catch (QueryException $exception) {
                $adalahBentrokKode = str_contains($exception->getMessage(), 'pendaftaran_lembaga_id_kode_pendaftaran_unique');

                if (! $adalahBentrokKode) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('Gagal membuat pendaftaran setelah '.self::MAKS_PERCOBAAN_KODE.' percobaan kode.');
    }

    public function berhasil(Request $request, string $lembagaSlug, string $kodePendaftaran): View
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $pendaftaran = Pendaftaran::where('lembaga_id', $lembaga->id)
            ->where('kode_pendaftaran', $kodePendaftaran)
            ->where('email_pendaftaran', $request->query('email'))
            ->firstOrFail();

        return view('spmb.berhasil', ['lembaga' => $lembaga, 'pendaftaran' => $pendaftaran]);
    }
}
```

Note: `berhasil()` is left byte-for-byte identical to its current implementation in this step — it is rewritten in Task 7 together with its route and view. Leaving it in place here means the controller class remains valid PHP throughout this task (it still references `$this->resolveLembaga()` from the composed `ResolvesSpmbTenant`, which `ResolvesWizardContext` still provides).

- [ ] **Step 5: Rewrite the Review view**

```blade
{{-- resources/views/spmb/review.blade.php --}}
<x-layouts.portal-wizard title="Review" current="review" :lembaga="$lembaga" :jalur="$jalur" :nominal="$nominal">
    <div class="rounded-2xl border border-gray-200 bg-white p-6">
        <div class="flex items-center gap-2.5">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-portal-50 text-portal-500">
                <x-icon name="fact_check" class="h-4 w-4" />
            </span>
            <h2 class="text-[15.5px] font-bold text-gray-900">Review Data</h2>
        </div>
        <p class="mt-1.5 text-[12.5px] text-gray-500">Periksa kembali sebelum mengirim.</p>
        <div class="my-4 h-px bg-gray-200"></div>

        @error('submit')
            <div class="mb-4 rounded-xl border border-error-500/30 bg-error-50 p-4 text-[13px] text-error-700">{{ $message }}</div>
        @enderror

        <section class="mb-5">
            <h3 class="mb-2 text-[11px] font-bold uppercase tracking-wide text-gray-400">Data Pribadi</h3>
            <dl class="divide-y divide-gray-100 text-[13px]">
                <div class="flex justify-between py-2"><dt class="text-gray-400">Nama Lengkap</dt><dd class="font-semibold text-gray-900">{{ $session['data_pribadi']['nama_lengkap'] ?? '-' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-gray-400">NIK</dt><dd class="font-mono text-gray-900">{{ $session['nik'] ?? '-' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-gray-400">Jenis Kelamin</dt><dd class="text-gray-900">{{ $session['data_pribadi']['jenis_kelamin'] ?? '-' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-gray-400">Tempat, Tanggal Lahir</dt><dd class="text-gray-900">{{ $session['data_pribadi']['tempat_lahir'] ?? '-' }}, {{ $session['data_pribadi']['tanggal_lahir'] ?? '-' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-gray-400">Agama</dt><dd class="text-gray-900">{{ $session['data_pribadi']['agama'] ?? '-' }}</dd></div>
            </dl>
        </section>

        <section class="mb-5">
            <h3 class="mb-2 text-[11px] font-bold uppercase tracking-wide text-gray-400">Alamat</h3>
            <p class="text-[13px] text-gray-900">
                {{ $session['alamat']['alamat_jalan'] ?? '-' }}, {{ $session['alamat']['desa_kelurahan'] ?? '-' }},
                {{ $session['alamat']['kecamatan'] ?? '-' }}, {{ $session['alamat']['kabupaten_kota'] ?? '-' }}, {{ $session['alamat']['provinsi'] ?? '-' }}
            </p>
        </section>

        <section class="mb-5">
            <h3 class="mb-2 text-[11px] font-bold uppercase tracking-wide text-gray-400">Data Orang Tua/Wali</h3>
            <dl class="divide-y divide-gray-100 text-[13px]">
                @foreach ($session['keluarga'] ?? [] as $anggota)
                    <div class="flex justify-between py-2"><dt class="text-gray-400">{{ ucfirst($anggota['jenis']) }}</dt><dd class="font-semibold text-gray-900">{{ $anggota['nama'] }}</dd></div>
                @endforeach
            </dl>
        </section>

        @if ($formulirFieldList->isNotEmpty())
            <section class="mb-5">
                <h3 class="mb-2 text-[11px] font-bold uppercase tracking-wide text-gray-400">Formulir Tambahan</h3>
                <dl class="divide-y divide-gray-100 text-[13px]">
                    @foreach ($formulirFieldList as $field)
                        <div class="flex justify-between py-2"><dt class="text-gray-400">{{ $field->label }}</dt><dd class="font-semibold text-gray-900">{{ $session['jawaban_formulir'][$field->id] ?? '-' }}</dd></div>
                    @endforeach
                </dl>
            </section>
        @endif

        @if ($dokumenSyaratList->isNotEmpty())
            <section class="mb-5">
                <h3 class="mb-2 text-[11px] font-bold uppercase tracking-wide text-gray-400">Dokumen</h3>
                <dl class="divide-y divide-gray-100 text-[13px]">
                    @foreach ($dokumenSyaratList as $syarat)
                        <div class="flex items-center justify-between py-2">
                            <dt class="text-gray-400">{{ $syarat->nama_dokumen }}</dt>
                            <dd class="font-semibold {{ isset($session['dokumen'][$syarat->id]) ? 'text-success-700' : 'text-gray-400' }}">
                                {{ $session['dokumen'][$syarat->id]['nama_file_asli'] ?? 'Belum diupload' }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif

        <form method="POST" action="{{ route('portal.wizard.submit') }}" class="flex justify-end border-t border-dashed border-gray-200 pt-5">
            @csrf
            <button type="submit" class="flex items-center justify-center gap-2 rounded-[10px] bg-portal-500 px-6 py-3 text-[13.5px] font-bold text-white transition hover:bg-portal-600">
                Kirim Pendaftaran
                <x-icon name="arrow_forward" class="h-3.5 w-3.5" />
            </button>
        </form>
    </div>
</x-layouts.portal-wizard>
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Spmb/ReviewSubmitTest.php`
Expected: PASS (10 tests)

- [ ] **Step 7: Commit**

```bash
git add routes/spmb.php app/Http/Controllers/Spmb/ReviewSubmitController.php resources/views/spmb/review.blade.php tests/Feature/Spmb/ReviewSubmitTest.php
git commit -m "feat: migrate Review & Submit wizard step, link Pendaftaran to akun directly at create time"
```

---

## Task 7: Halaman Berhasil — route, controller, view

**Files:**
- Modify: `routes/spmb.php` (remove `spmb.berhasil`, remove now-fully-unused `ReviewSubmitController` import)
- Modify: `app/Http/Controllers/Spmb/ReviewSubmitController.php` (rewrite `berhasil()` only)
- Modify: `resources/views/spmb/berhasil.blade.php` (full rewrite)
- Modify: `tests/Feature/Spmb/ReviewSubmitTest.php` (append `berhasil()` tests)

**Interfaces:**
- Consumes: `<x-layouts.portal-public>` is NOT used here — this is a post-wizard confirmation page reached while authenticated, so it uses the same navy tokens directly (no wizard shell/stepper needed since the wizard is finished); route `portal.wizard.berhasil` (already registered in Task 3, URL `/portal/wizard/berhasil/{pendaftaran}`).
- Produces: `ReviewSubmitController::berhasil(Pendaftaran $pendaftaran): View` (route-model-bound, replacing the old `berhasil(Request $request, string $lembagaSlug, string $kodePendaftaran): View` signature).

- [ ] **Step 1: Remove the old berhasil route from `routes/spmb.php`**

Delete this block:

```php
    Route::get('{lembagaSlug}/berhasil/{kodePendaftaran}', [ReviewSubmitController::class, 'berhasil'])
        ->middleware('throttle:10,1')->name('berhasil');
```

Now that no route in `routes/spmb.php` references `ReviewSubmitController` anymore, also remove its now-unused import:

```php
use App\Http\Controllers\Spmb\ReviewSubmitController;
```

- [ ] **Step 2: Write the failing tests**

Append these two tests to the end of `tests/Feature/Spmb/ReviewSubmitTest.php` (after the last `it(...)` block, before end of file):

```php
it('shows the success page with the kode pendaftaran for the akun that owns it', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);
    isiWizardLengkap($lembaga, $jalur);
    $this->post(route('portal.wizard.submit'));
    $pendaftaran = Pendaftaran::first();

    $this->get(route('portal.wizard.berhasil', ['pendaftaran' => $pendaftaran]))
        ->assertOk()
        ->assertSee($pendaftaran->kode_pendaftaran);
});

it('404s the success page when a different akun tries to view it', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);
    isiWizardLengkap($lembaga, $jalur);
    $this->post(route('portal.wizard.submit'));
    $pendaftaran = Pendaftaran::first();

    $akunLain = AkunPendaftar::factory()->create();

    $this->actingAs($akunLain, 'portal')
        ->get(route('portal.wizard.berhasil', ['pendaftaran' => $pendaftaran]))
        ->assertNotFound();
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Spmb/ReviewSubmitTest.php`
Expected: FAIL — `berhasil()` still takes `string $lembagaSlug, string $kodePendaftaran` and checks `?email=` query string, not route-model-bound ownership.

- [ ] **Step 4: Rewrite `berhasil()` in `ReviewSubmitController`**

Replace this method (currently the last method in the class, following the exact contents written in Task 6 Step 4):

```php
    public function berhasil(Request $request, string $lembagaSlug, string $kodePendaftaran): View
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $pendaftaran = Pendaftaran::where('lembaga_id', $lembaga->id)
            ->where('kode_pendaftaran', $kodePendaftaran)
            ->where('email_pendaftaran', $request->query('email'))
            ->firstOrFail();

        return view('spmb.berhasil', ['lembaga' => $lembaga, 'pendaftaran' => $pendaftaran]);
    }
```

with:

```php
    public function berhasil(Pendaftaran $pendaftaran): View
    {
        abort_unless($pendaftaran->akun_pendaftar_id === Auth::guard('portal')->user()->id, 404);

        return view('spmb.berhasil', ['pendaftaran' => $pendaftaran]);
    }
```

The `Request` import is still used elsewhere in the file (`submit()` does not need it directly, but check remaining usages); if `Illuminate\Http\Request` is no longer referenced anywhere in the class after this change, remove its `use` statement too.

- [ ] **Step 5: Rewrite the Berhasil view**

```blade
{{-- resources/views/spmb/berhasil.blade.php --}}
<x-layouts.portal-public active="dashboard">
    <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-10">
        <div class="flex items-center gap-3 rounded-2xl bg-success-50 p-4 text-success-700">
            <x-icon name="check_circle" class="h-8 w-8 shrink-0" />
            <div>
                <p class="font-bold">Pendaftaran Berhasil</p>
                <p class="text-[13px]">Data {{ $pendaftaran->calonMurid->nama_lengkap }} sudah kami terima.</p>
            </div>
        </div>

        <div class="mt-4 rounded-2xl bg-portal-500 p-6 text-center text-white">
            <p class="text-[11px] font-bold uppercase tracking-wide text-white/70">Kode Pendaftaran Anda</p>
            <p class="mt-2 font-mono text-3xl font-bold tracking-widest">{{ $pendaftaran->kode_pendaftaran }}</p>
        </div>

        <div class="mt-4 rounded-2xl border border-gray-200 bg-white p-6 text-center">
            <p class="text-[13px] text-gray-500">Kode ini juga sudah dikirim ke <span class="font-semibold text-gray-900">{{ $pendaftaran->email_pendaftaran }}</span>. Simpan untuk cek status pendaftaran nanti.</p>
        </div>

        <div class="mt-6 text-center">
            <a href="{{ route('portal.dashboard') }}" class="inline-flex items-center gap-2 rounded-[10px] bg-portal-500 px-6 py-3 text-[13.5px] font-bold text-white transition hover:bg-portal-600">
                Lihat Dashboard
                <x-icon name="arrow_forward" class="h-3.5 w-3.5" />
            </a>
        </div>
    </div>
</x-layouts.portal-public>
```

Note: `<x-layouts.portal-public>` (`resources/views/components/layouts/portal-public.blade.php`, from Sub-project 1) renders `<x-portal-navbar>` (the anonymous public navbar with "Masuk"/"Daftar Akun" buttons), which is wrong for an authenticated confirmation page. Use `<x-layouts.portal-wizard>`'s navbar instead by wrapping with the authenticated shell directly rather than the public one — replace the outer tag in the view above from `<x-layouts.portal-public active="dashboard">`/`</x-layouts.portal-public>` to a minimal authenticated wrapper:

```blade
{{-- resources/views/spmb/berhasil.blade.php --}}
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Pendaftaran Berhasil — Pintera</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-50 font-sans text-gray-900 antialiased">
        <x-portal-authenticated-navbar active="dashboard" />

        <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-10">
            <div class="flex items-center gap-3 rounded-2xl bg-success-50 p-4 text-success-700">
                <x-icon name="check_circle" class="h-8 w-8 shrink-0" />
                <div>
                    <p class="font-bold">Pendaftaran Berhasil</p>
                    <p class="text-[13px]">Data {{ $pendaftaran->calonMurid->nama_lengkap }} sudah kami terima.</p>
                </div>
            </div>

            <div class="mt-4 rounded-2xl bg-portal-500 p-6 text-center text-white">
                <p class="text-[11px] font-bold uppercase tracking-wide text-white/70">Kode Pendaftaran Anda</p>
                <p class="mt-2 font-mono text-3xl font-bold tracking-widest">{{ $pendaftaran->kode_pendaftaran }}</p>
            </div>

            <div class="mt-4 rounded-2xl border border-gray-200 bg-white p-6 text-center">
                <p class="text-[13px] text-gray-500">Kode ini juga sudah dikirim ke <span class="font-semibold text-gray-900">{{ $pendaftaran->email_pendaftaran }}</span>. Simpan untuk cek status pendaftaran nanti.</p>
            </div>

            <div class="mt-6 text-center">
                <a href="{{ route('portal.dashboard') }}" class="inline-flex items-center gap-2 rounded-[10px] bg-portal-500 px-6 py-3 text-[13.5px] font-bold text-white transition hover:bg-portal-600">
                    Lihat Dashboard
                    <x-icon name="arrow_forward" class="h-3.5 w-3.5" />
                </a>
            </div>
        </div>

        <x-portal-footer :yayasan="$pendaftaran->lembaga->yayasan ?? null" />
    </body>
</html>
```

(Use this second version as the actual file contents — it is the corrected one; the `<x-layouts.portal-public>` draft above is shown only to explain why it was rejected, and must not be written to disk.)

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Spmb/ReviewSubmitTest.php`
Expected: PASS (12 tests)

- [ ] **Step 7: Commit**

```bash
git add routes/spmb.php app/Http/Controllers/Spmb/ReviewSubmitController.php resources/views/spmb/berhasil.blade.php tests/Feature/Spmb/ReviewSubmitTest.php
git commit -m "feat: migrate wizard success page to route-model-bound ownership check"
```

---

## Task 8: Delete VerifikasiEmailController and sweep for leftover old routes

**Files:**
- Delete: `app/Http/Controllers/Spmb/VerifikasiEmailController.php`
- Delete: `resources/views/spmb/verifikasi-email.blade.php`
- Delete: `resources/views/spmb/verifikasi-otp.blade.php` (the OLD wizard-OTP view — NOT `resources/views/portal/auth/verifikasi-otp.blade.php`, which belongs to Sub-project 2 and stays untouched)
- Delete: `tests/Feature/Spmb/VerifikasiEmailTest.php`
- Modify: `routes/spmb.php` (remove remaining `spmb.mulai`+`.store`+`spmb.verifikasi-otp`+`.store`, remove `VerifikasiEmailController` import)
- Modify: `tests/Pest.php` (remove the now-dead `siapkanEmailTerverifikasi()` helper — Tasks 3-7 rewrote every test that called it, and `VerifikasiEmailTest.php`, its last remaining caller, is deleted in this task)
- Test: `tests/Feature/Spmb/OldWizardRoutesRemovedTest.php` (new)

**Interfaces:**
- None — this task only removes code and adds a negative-assertion test confirming the full route-name sweep from Tasks 3-8.

- [ ] **Step 1: Confirm what's left in `routes/spmb.php`**

Read `routes/spmb.php`. After Tasks 3-7, it should now contain only: `spmb.welcome`, the `guest:portal`-guarded `spmb.register`(+`.register.ganti-jalur`), `spmb.index`, `spmb.jalur.daftar`, `spmb.mulai`(+`.store`), `spmb.verifikasi-otp`(+`.store`), and `spmb.status.form`/`spmb.status.show`/`spmb.bukti`. Confirm this matches before editing (if any wizard route from Tasks 3-7 is still present, stop and fix that task instead of proceeding).

- [ ] **Step 2: Remove the remaining old routes from `routes/spmb.php`**

Delete these 4 lines:

```php
    Route::get('{lembagaSlug}/{jalur}/mulai', [VerifikasiEmailController::class, 'create'])->name('mulai');
    Route::post('{lembagaSlug}/{jalur}/mulai', [VerifikasiEmailController::class, 'store'])
        ->middleware('throttle:6,1')->name('mulai.store');
    Route::get('{lembagaSlug}/{jalur}/verifikasi-otp', [VerifikasiEmailController::class, 'edit'])->name('verifikasi-otp');
    Route::post('{lembagaSlug}/{jalur}/verifikasi-otp', [VerifikasiEmailController::class, 'update'])->name('verifikasi-otp.store');
```

Remove the now-unused import:

```php
use App\Http\Controllers\Spmb\VerifikasiEmailController;
```

The resulting `routes/spmb.php` should contain exactly: `spmb.welcome`, `spmb.register`(+`.register.ganti-jalur`, guest-guarded), `spmb.index`, `spmb.jalur.daftar`, `spmb.status.form`, `spmb.status.show`, `spmb.bukti`.

- [ ] **Step 3: Delete `VerifikasiEmailController` and its views/test**

```bash
git rm app/Http/Controllers/Spmb/VerifikasiEmailController.php
git rm resources/views/spmb/verifikasi-email.blade.php
git rm resources/views/spmb/verifikasi-otp.blade.php
git rm tests/Feature/Spmb/VerifikasiEmailTest.php
```

- [ ] **Step 4: Remove the now-dead `siapkanEmailTerverifikasi()` helper from `tests/Pest.php`**

Confirm first that nothing still calls it: `grep -rn "siapkanEmailTerverifikasi(" tests/` should now only show its own definition in `tests/Pest.php` (every caller was rewritten in Tasks 3-7, and `VerifikasiEmailTest.php` — its last remaining caller — was just deleted in Step 3). If any other caller is found, stop and fix that file instead of deleting the helper.

Delete this function from `tests/Pest.php`:

```php
function siapkanEmailTerverifikasi($lembaga, $jalur, string $email): void
{
    (new \App\Services\PendaftaranWizardSession())->put($lembaga, $jalur, ['email_pendaftaran' => $email]);
}
```

- [ ] **Step 5: Write the route-removal sweep test**

```php
<?php
// tests/Feature/Spmb/OldWizardRoutesRemovedTest.php

use Illuminate\Support\Facades\Route;

it('confirms every old anonymous wizard route name is gone after the account-first migration', function () {
    $oldRouteNames = [
        'spmb.mulai',
        'spmb.mulai.store',
        'spmb.verifikasi-otp',
        'spmb.verifikasi-otp.store',
        'spmb.data-diri',
        'spmb.data-diri.cek-nik',
        'spmb.data-diri.store',
        'spmb.formulir-tambahan',
        'spmb.formulir-tambahan.store',
        'spmb.dokumen',
        'spmb.dokumen.store',
        'spmb.review',
        'spmb.submit',
        'spmb.berhasil',
    ];

    foreach ($oldRouteNames as $name) {
        expect(Route::has($name))->toBeFalse("Expected route [{$name}] to no longer be registered.");
    }
});

it('confirms every new authenticated wizard route name is registered', function () {
    $newRouteNames = [
        'portal.wizard.data-diri',
        'portal.wizard.data-diri.cek-nik',
        'portal.wizard.data-diri.store',
        'portal.wizard.formulir-tambahan',
        'portal.wizard.formulir-tambahan.store',
        'portal.wizard.dokumen',
        'portal.wizard.dokumen.store',
        'portal.wizard.review',
        'portal.wizard.submit',
        'portal.wizard.berhasil',
    ];

    foreach ($newRouteNames as $name) {
        expect(Route::has($name))->toBeTrue("Expected route [{$name}] to be registered.");
    }
});
```

- [ ] **Step 6: Run the full test suite**

Run: `vendor/bin/pest`
Expected: PASS — every test in the suite, including all of `tests/Feature/Spmb/*` and the pre-existing `tests/Feature/Portal/*`, `tests/Feature/Spmb/PortalEntryTest.php`, `tests/Feature/Spmb/WelcomeControllerTest.php`, `tests/Feature/Spmb/JalurDaftarActionTest.php`, `tests/Feature/Spmb/RegisterRouteTest.php`, `tests/Feature/Spmb/CekStatusTest.php`, `tests/Feature/Spmb/BuktiPendaftaranSkReferenceTest.php`, `tests/Feature/Spmb/TagihanPendaftaranHookTest.php` (all untouched by this plan) still pass with no regressions.

- [ ] **Step 7: Commit**

```bash
git add routes/spmb.php tests/Pest.php tests/Feature/Spmb/OldWizardRoutesRemovedTest.php
git commit -m "chore: delete VerifikasiEmailController and sweep old anonymous wizard routes"
```

---

## Manual verification (not automatable in this plan)

After Task 8, manually verify in a browser (per spec §5's "Responsivitas" bullet, since Pest cannot check rendered CSS):
- The wizard stepper collapses to `justify-content: flex-start` and shrinks its numbers/labels at ≤560px viewport width.
- The 2-column wizard layout (form + sidebar) collapses to 1 column at ≤900px viewport width.
- The authenticated navbar's desktop nav links/user menu hide and the mobile hamburger menu appears at ≤720px viewport width.
