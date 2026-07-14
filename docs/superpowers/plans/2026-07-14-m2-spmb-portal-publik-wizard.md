# M2 — SPMB Portal Publik: Public Wizard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the public-facing, unauthenticated registration wizard for M2 SPMB Portal Publik — pilih jalur, verifikasi email (OTP), data diri (with NIK-based reuse), formulir tambahan, upload dokumen, review & submit, plus a separate cek-status page with a downloadable bukti pendaftaran PDF — all on top of the data layer already merged (Plan 1 of 2).

**Architecture:** Traditional server-rendered Blade forms (GET shows a step, POST validates and advances to the next step) — no AJAX/SPA treatment, since this is a linear, document-filling flow, not an interactive admin panel. Wizard-in-progress data (before the final atomic submit) lives in the PHP session, keyed per lembaga+jalur, via a small `PendaftaranWizardSession` helper — never written to the database until the final "Review & Submit" step, per the design's "no draft" decision. Visual design matches `docs/superpowers/m2-frontend-design-reference.md` (same Tailwind tokens as the admin panel, no admin chrome).

**Tech Stack:** Laravel 12, Blade, `barryvdh/laravel-dompdf` (already installed), Laravel Mail (`MAIL_MAILER=log`), Pest PHP.

## Global Constraints

- No admin sidebar/topbar — public pages use a dedicated `layouts.spmb-public` layout (centered card, lembaga identity header, no auth chrome).
- Reuse existing generic Blade components (`x-panel`, `x-text-input`, `x-input-label`, `x-input-error`, `x-primary-button`, `x-link-button`) exactly as they already exist — do not create parallel public-only versions of these.
- No wizard step writes to the database until the final "Review & Submit" step. All prior steps write only to the PHP session.
- Uploaded files during the wizard (before final submit) are stored under `storage/app/public/pendaftaran-tmp/{session id}/{dokumen_syarat_ppdb_id}.{ext}` and moved to `storage/app/public/pendaftaran/{pendaftaran id}/{dokumen_syarat_ppdb_id}.{ext}` only at final submit.
- File upload validation: `mimes:pdf,jpg,jpeg,png`, max 2048 KB, enforced server-side via Laravel validation rules (not just client-side `accept` attributes).
- Every route in this plan lives under `routes/spmb.php`, loaded from `routes/web.php` via `require __DIR__.'/spmb.php';` (mirroring how `admin.php`/`auth.php` are already loaded) — no `bootstrap/app.php` change needed.
- Rate limiting (Laravel `throttle` middleware) on the OTP-send and final-submit routes, since both are public and unauthenticated.
- NIK reuse (Task 2) must enforce the email-match safeguard from the design spec §2.3: if a submitted NIK matches an existing `CalonMurid` but the submitted email does NOT match any of that `CalonMurid`'s prior `Pendaftaran.email_pendaftaran` values, the flow is **blocked** with an explanatory message — never silently treated as a fresh/blank entry (that would let the submission overwrite the real owner's data, since `nik_hash` is globally unique).
- Reuses these Plan-1 building blocks exactly as built: `CalonMurid::findByNik()`, `OtpService::kirim()`/`verifikasi()`, `KodePendaftaranGenerator::generate()`, and the 9 Plan-1 models.
- Every controller action that receives both `{lembagaSlug}` and `{jalur}` route parameters MUST use the `App\Http\Controllers\Spmb\Concerns\ResolvesSpmbTenant` trait (added in Task 1) — call `$this->resolveLembaga($lembagaSlug)` instead of an ad-hoc `Lembaga::where('slug', ...)->firstOrFail()`, and `$this->assertJalurBelongsToLembaga($lembaga, $jalur)` (or `$this->resolveGelombangAktifUntukJalur($lembaga, $jalur)` where the step must also confirm a gelombang is currently open) so a `{jalur}` id from a different lembaga is always rejected with 404. This closes a cross-tenant data leak: without it, a route model bound `{jalur}` is resolved by ID alone, so a guessed/stale URL could pair one lembaga's slug with another lembaga's (or another yayasan's) `jalur_ppdb_id`.
- Shared Pest test helpers used by more than one test file in this plan (`buatLembagaDenganGelombangBuka()`, and any later-added equivalents) live in `tests/Pest.php`'s "Functions" section, not redefined or left dependent on file-execution-order inside a single Feature test file — Pest's global functions are only safe to share this way when declared where every test file loads them.

---

### Task 1: Public layout, wizard session helper, entry point, and email verification

**Files:**
- Create: `resources/views/layouts/spmb-public.blade.php`
- Create: `app/Services/PendaftaranWizardSession.php`
- Create: `app/Http/Controllers/Spmb/PortalController.php`
- Create: `app/Http/Controllers/Spmb/VerifikasiEmailController.php`
- Create: `resources/views/spmb/pilih-jalur.blade.php`
- Create: `resources/views/spmb/tertutup.blade.php`
- Create: `resources/views/spmb/verifikasi-email.blade.php`
- Create: `resources/views/spmb/verifikasi-otp.blade.php`
- Create: `routes/spmb.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Spmb/PortalEntryTest.php`
- Test: `tests/Feature/Spmb/VerifikasiEmailTest.php`

**Interfaces:**
- Consumes: `Lembaga` (route-resolved by slug, manual lookup — no `getRouteKeyName()` change to the shared model), `JalurPpdb`/`GelombangPpdb`/`TahunAjaran` (Plan 1's existing PPDB config models), `OtpService::kirim()`/`verifikasi()` (Plan 1).
- Produces: `PendaftaranWizardSession` with `key(Lembaga, JalurPpdb): string`, `get(Lembaga, JalurPpdb): array`, `put(Lembaga, JalurPpdb, array): void`, `clear(Lembaga, JalurPpdb): void` — consumed by every later task in this plan. Session data after this task's steps includes `email_pendaftaran` (set only once OTP is verified).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Spmb/PortalEntryTest.php`:

```php
<?php

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

function buatLembagaDenganGelombangBuka(): array
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

    return [$lembaga, $tahunAjaran, $jalur, $gelombang];
}

it('shows the jalur list for a lembaga with an open gelombang', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();

    $this->get("/spmb/{$lembaga->slug}")
        ->assertOk()
        ->assertSee($jalur->nama)
        ->assertDontSee('belum dibuka');
});

it('shows a closed-registration page when no gelombang is currently open', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang Lalu',
        'tanggal_buka' => now()->subMonths(2), 'tanggal_tutup' => now()->subMonth(), 'kuota' => 40,
    ]);

    $this->get("/spmb/{$lembaga->slug}")->assertOk()->assertSee('belum dibuka', false);
});

it('picks the gelombang with the earliest tanggal_buka when two overlap', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang Lebih Awal',
        'tanggal_buka' => now()->subWeek(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 20,
    ]);

    $response = $this->get("/spmb/{$lembaga->slug}/{$jalur->id}/mulai");

    $response->assertOk();
});

it('404s for an unknown lembaga slug', function () {
    $this->get('/spmb/sekolah-tidak-ada')->assertNotFound();
});
```

Create `tests/Feature/Spmb/VerifikasiEmailTest.php`:

```php
<?php

use App\Models\VerifikasiEmailOtp;
use Illuminate\Support\Facades\Mail;

it('sends an otp and advances to the otp-input step', function () {
    Mail::fake();
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();

    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/mulai", ['email' => 'wali@example.test'])
        ->assertRedirect("/spmb/{$lembaga->slug}/{$jalur->id}/verifikasi-otp");

    expect(VerifikasiEmailOtp::where('email', 'wali@example.test')->exists())->toBeTrue();
});

it('rejects an invalid email format without sending an otp', function () {
    Mail::fake();
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();

    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/mulai", ['email' => 'bukan-email'])
        ->assertSessionHasErrors('email');

    expect(VerifikasiEmailOtp::count())->toBe(0);
});

it('verifies a correct otp and stores email_pendaftaran in the wizard session', function () {
    Mail::fake();
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/mulai", ['email' => 'wali@example.test']);
    $kode = VerifikasiEmailOtp::where('email', 'wali@example.test')->first()->kode_otp;

    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/verifikasi-otp", ['kode_otp' => $kode])
        ->assertRedirect("/spmb/{$lembaga->slug}/{$jalur->id}/data-diri");

    $session = (new App\Services\PendaftaranWizardSession())->get($lembaga, $jalur);
    expect($session['email_pendaftaran'])->toBe('wali@example.test');
});

it('rejects a wrong otp and stays on the otp step', function () {
    Mail::fake();
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/mulai", ['email' => 'wali@example.test']);

    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/verifikasi-otp", ['kode_otp' => '000000'])
        ->assertSessionHasErrors('kode_otp');

    $session = (new App\Services\PendaftaranWizardSession())->get($lembaga, $jalur);
    expect($session['email_pendaftaran'] ?? null)->toBeNull();
});
```

- [ ] **Step 2: Run the tests to confirm they fail**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/Spmb/PortalEntryTest.php tests/Feature/Spmb/VerifikasiEmailTest.php`
Expected: FAIL — routes/controllers/views don't exist yet.

- [ ] **Step 3: Create the wizard session helper**

Create `app/Services/PendaftaranWizardSession.php`:

```php
<?php

namespace App\Services;

use App\Models\JalurPpdb;
use App\Models\Lembaga;

class PendaftaranWizardSession
{
    public function key(Lembaga $lembaga, JalurPpdb $jalur): string
    {
        return "spmb_wizard.{$lembaga->id}.{$jalur->id}";
    }

    public function get(Lembaga $lembaga, JalurPpdb $jalur): array
    {
        return session($this->key($lembaga, $jalur), []);
    }

    public function put(Lembaga $lembaga, JalurPpdb $jalur, array $data): void
    {
        $existing = $this->get($lembaga, $jalur);
        session([$this->key($lembaga, $jalur) => array_merge($existing, $data)]);
    }

    public function clear(Lembaga $lembaga, JalurPpdb $jalur): void
    {
        session()->forget($this->key($lembaga, $jalur));
    }
}
```

- [ ] **Step 4: Create the public layout**

Create `resources/views/layouts/spmb-public.blade.php`:

```blade
<!DOCTYPE html>
<html lang="id" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'Pendaftaran SPMB' }} — {{ $lembaga->nama ?? config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:500,600,700,800|inter:400,500,600,700|ibm-plex-mono:500&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-full bg-paper font-sans text-ink antialiased">
        <div class="mx-auto flex min-h-screen max-w-2xl flex-col px-4 py-10">
            <header class="mb-8 text-center">
                <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Pendaftaran SPMB</p>
                <h1 class="mt-1 font-display text-xl font-bold text-ink">{{ $lembaga->nama ?? '' }}</h1>
            </header>

            <main class="flex-1">
                {{ $slot }}
            </main>

            <footer class="mt-10 text-center text-xs text-slate">
                &copy; {{ now()->year }} {{ $lembaga->nama ?? config('app.name') }}
            </footer>
        </div>
    </body>
</html>
```

- [ ] **Step 5: Create the routes**

Create `routes/spmb.php`:

```php
<?php

use App\Http\Controllers\Spmb\PortalController;
use App\Http\Controllers\Spmb\VerifikasiEmailController;
use Illuminate\Support\Facades\Route;

Route::prefix('spmb')->name('spmb.')->group(function () {
    Route::get('{lembagaSlug}', [PortalController::class, 'index'])->name('index');
    Route::get('{lembagaSlug}/{jalur}/mulai', [VerifikasiEmailController::class, 'create'])->name('mulai');
    Route::post('{lembagaSlug}/{jalur}/mulai', [VerifikasiEmailController::class, 'store'])
        ->middleware('throttle:6,1')->name('mulai.store');
    Route::get('{lembagaSlug}/{jalur}/verifikasi-otp', [VerifikasiEmailController::class, 'edit'])->name('verifikasi-otp');
    Route::post('{lembagaSlug}/{jalur}/verifikasi-otp', [VerifikasiEmailController::class, 'update'])->name('verifikasi-otp.store');
});
```

- [ ] **Step 6: Modify `routes/web.php`**

Add one line after the existing `require __DIR__.'/admin.php';` line:

```php
require __DIR__.'/admin.php';
require __DIR__.'/spmb.php';
```

- [ ] **Step 7: Create `PortalController`**

Create `app/Http/Controllers/Spmb/PortalController.php`:

```php
<?php

namespace App\Http\Controllers\Spmb;

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class PortalController extends BaseController
{
    public function index(Request $request, string $lembagaSlug): View
    {
        $lembaga = Lembaga::where('slug', $lembagaSlug)->firstOrFail();
        $gelombang = $this->cariGelombangAktif($lembaga);

        if (! $gelombang) {
            return view('spmb.tertutup', ['lembaga' => $lembaga]);
        }

        $jalurList = JalurPpdb::where('tahun_ajaran_id', $gelombang->tahun_ajaran_id)
            ->where('status_aktif', true)
            ->orderBy('nama')
            ->get();

        return view('spmb.pilih-jalur', ['lembaga' => $lembaga, 'jalurList' => $jalurList, 'gelombang' => $gelombang]);
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

- [ ] **Step 8: Create `pilih-jalur.blade.php` and `tertutup.blade.php`**

Create `resources/views/spmb/pilih-jalur.blade.php`:

```blade
<x-spmb-public-layout :lembaga="$lembaga" title="Pilih Jalur">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-ink">Pilih Jalur Pendaftaran</h2>
        <p class="mt-1 text-sm text-slate">Gelombang: {{ $gelombang->nama }} &middot; Ditutup {{ $gelombang->tanggal_tutup->translatedFormat('d F Y') }}</p>

        <div class="mt-6 space-y-3">
            @foreach ($jalurList as $jalur)
                <a
                    href="{{ route('spmb.mulai', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]) }}"
                    class="block rounded-xl border border-ink/10 p-4 transition hover:border-brass hover:bg-brass/5"
                >
                    <p class="font-display font-semibold text-ink">{{ $jalur->nama }}</p>
                    @if ($jalur->deskripsi)
                        <p class="mt-1 text-sm text-slate">{{ $jalur->deskripsi }}</p>
                    @endif
                </a>
            @endforeach
        </div>
    </x-panel>

    <p class="mt-4 text-center text-sm">
        <a href="{{ route('spmb.status.form', ['lembagaSlug' => $lembaga->slug]) }}" class="text-slate hover:text-ink">Sudah mendaftar? Cek status di sini</a>
    </p>
</x-spmb-public-layout>
```

Create `resources/views/spmb/tertutup.blade.php`:

```blade
<x-spmb-public-layout :lembaga="$lembaga" title="Pendaftaran Ditutup">
    <x-panel class="p-6 text-center">
        <p class="font-display text-lg font-bold text-ink">Pendaftaran belum dibuka</p>
        <p class="mt-2 text-sm text-slate">Saat ini tidak ada gelombang pendaftaran yang sedang berlangsung untuk {{ $lembaga->nama }}. Silakan cek kembali nanti.</p>
    </x-panel>
</x-spmb-public-layout>
```

Note: `<x-spmb-public-layout>` requires a Blade anonymous component wrapper. Create `resources/views/components/spmb-public-layout.blade.php`:

```blade
@props(['lembaga', 'title' => null])

<x-layouts.spmb-public :lembaga="$lembaga" :title="$title">
    {{ $slot }}
</x-layouts.spmb-public>
```

- [ ] **Step 9: Create `VerifikasiEmailController`**

Create `app/Http/Controllers/Spmb/VerifikasiEmailController.php`:

```php
<?php

namespace App\Http\Controllers\Spmb;

use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Services\OtpService;
use App\Services\PendaftaranWizardSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class VerifikasiEmailController extends BaseController
{
    public function create(string $lembagaSlug, JalurPpdb $jalur): View
    {
        $lembaga = Lembaga::where('slug', $lembagaSlug)->firstOrFail();

        return view('spmb.verifikasi-email', ['lembaga' => $lembaga, 'jalur' => $jalur]);
    }

    public function store(Request $request, string $lembagaSlug, JalurPpdb $jalur, OtpService $otpService): RedirectResponse
    {
        $lembaga = Lembaga::where('slug', $lembagaSlug)->firstOrFail();

        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $otpService->kirim($data['email']);

        session(['spmb_email_pending.'.$lembaga->id.'.'.$jalur->id => $data['email']]);

        return redirect()->route('spmb.verifikasi-otp', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]);
    }

    public function edit(string $lembagaSlug, JalurPpdb $jalur): View
    {
        $lembaga = Lembaga::where('slug', $lembagaSlug)->firstOrFail();

        return view('spmb.verifikasi-otp', ['lembaga' => $lembaga, 'jalur' => $jalur]);
    }

    public function update(
        Request $request,
        string $lembagaSlug,
        JalurPpdb $jalur,
        OtpService $otpService,
        PendaftaranWizardSession $wizardSession
    ): RedirectResponse {
        $lembaga = Lembaga::where('slug', $lembagaSlug)->firstOrFail();

        $data = $request->validate([
            'kode_otp' => ['required', 'string'],
        ]);

        $email = session('spmb_email_pending.'.$lembaga->id.'.'.$jalur->id);

        if (! $email || ! $otpService->verifikasi($email, $data['kode_otp'])) {
            return back()->withErrors(['kode_otp' => 'Kode salah, kedaluwarsa, atau sudah dipakai.']);
        }

        $wizardSession->put($lembaga, $jalur, ['email_pendaftaran' => $email]);

        return redirect()->route('spmb.data-diri', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]);
    }
}
```

- [ ] **Step 10: Create `verifikasi-email.blade.php` and `verifikasi-otp.blade.php`**

Create `resources/views/spmb/verifikasi-email.blade.php`:

```blade
<x-spmb-public-layout :lembaga="$lembaga" title="Verifikasi Email">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-ink">Verifikasi Email</h2>
        <p class="mt-1 text-sm text-slate">Masukkan email aktif Anda. Kami akan kirim kode verifikasi 6 digit.</p>

        <form method="POST" action="{{ route('spmb.mulai.store', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]) }}" class="mt-5 space-y-4">
            @csrf
            <div>
                <x-input-label value="Email" />
                <x-text-input type="email" name="email" value="{{ old('email') }}" class="mt-1.5" required autofocus />
                <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
            </div>
            <x-primary-button>Kirim Kode</x-primary-button>
        </form>
    </x-panel>
</x-spmb-public-layout>
```

Create `resources/views/spmb/verifikasi-otp.blade.php`:

```blade
<x-spmb-public-layout :lembaga="$lembaga" title="Masukkan Kode">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-ink">Masukkan Kode Verifikasi</h2>
        <p class="mt-1 text-sm text-slate">Kode 6 digit sudah dikirim ke email Anda. Berlaku 10 menit.</p>

        <form method="POST" action="{{ route('spmb.verifikasi-otp.store', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]) }}" class="mt-5 space-y-4">
            @csrf
            <div>
                <x-input-label value="Kode Verifikasi" />
                <x-text-input
                    type="text"
                    name="kode_otp"
                    inputmode="numeric"
                    maxlength="6"
                    class="mt-1.5 text-center font-mono text-xl tracking-[0.5em]"
                    required
                    autofocus
                />
                <x-input-error :messages="$errors->get('kode_otp')" class="mt-1.5" />
            </div>
            <x-primary-button>Verifikasi</x-primary-button>
        </form>
    </x-panel>
</x-spmb-public-layout>
```

- [ ] **Step 11: Run the tests to confirm they pass**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/Spmb/PortalEntryTest.php tests/Feature/Spmb/VerifikasiEmailTest.php`
Expected: PASS (8 tests).

- [ ] **Step 12: Commit**

```bash
git add app/Services/PendaftaranWizardSession.php app/Http/Controllers/Spmb/PortalController.php app/Http/Controllers/Spmb/VerifikasiEmailController.php resources/views/layouts/spmb-public.blade.php resources/views/components/spmb-public-layout.blade.php resources/views/spmb/pilih-jalur.blade.php resources/views/spmb/tertutup.blade.php resources/views/spmb/verifikasi-email.blade.php resources/views/spmb/verifikasi-otp.blade.php routes/spmb.php routes/web.php tests/Feature/Spmb/PortalEntryTest.php tests/Feature/Spmb/VerifikasiEmailTest.php
git commit -m "feat: add public SPMB entry point, jalur selection, and email OTP verification"
```

---

### Task 2: NIK + Data Diri step (with reuse-via-NIK safeguard)

**Files:**
- Create: `app/Http/Controllers/Spmb/DataDiriController.php`
- Create: `resources/views/spmb/data-diri.blade.php`
- Modify: `routes/spmb.php`
- Modify: `tests/Pest.php` (adds the shared `siapkanEmailTerverifikasi()` helper)
- Test: `tests/Feature/Spmb/DataDiriTest.php`

**Interfaces:**
- Consumes: `PendaftaranWizardSession` (Task 1), `CalonMurid::findByNik()` (Plan 1), `App\Http\Controllers\Spmb\Concerns\ResolvesSpmbTenant` (Task 1).
- Produces: wizard session now additionally holds `nik`, `data_pribadi` (array: nama_lengkap, nisn, no_kk, jenis_kelamin, tempat_lahir, tanggal_lahir, agama, golongan_darah, no_telepon), `alamat` (array), `keluarga` (array of arrays, one per ayah/ibu/wali).

- [ ] **Step 1: Add the shared `siapkanEmailTerverifikasi()` helper to `tests/Pest.php`**

`Task 3` (`FormulirTambahanTest.php`, `UploadDokumenTest.php`) also calls this helper, so — per this plan's Global Constraints — it must live in `tests/Pest.php`'s "Functions" section (loaded by every test file), not be defined locally inside `DataDiriTest.php`. Add this function to `tests/Pest.php`, alongside the existing `buatLembagaDenganGelombangBuka()` that Task 1 already placed there:

```php
function siapkanEmailTerverifikasi($lembaga, $jalur, string $email): void
{
    (new \App\Services\PendaftaranWizardSession())->put($lembaga, $jalur, ['email_pendaftaran' => $email]);
}
```

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/Spmb/DataDiriTest.php`:

```php
<?php

use App\Models\CalonMurid;
use App\Models\Pendaftaran;
use App\Services\PendaftaranWizardSession;

it('shows the data diri form for a new nik', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    siapkanEmailTerverifikasi($lembaga, $jalur, 'wali@example.test');

    $this->get("/spmb/{$lembaga->slug}/{$jalur->id}/data-diri")->assertOk();
});

it('stores data diri in the wizard session and advances to formulir tambahan', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    siapkanEmailTerverifikasi($lembaga, $jalur, 'wali@example.test');

    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/data-diri", [
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
    ])->assertRedirect("/spmb/{$lembaga->slug}/{$jalur->id}/formulir-tambahan");

    $session = (new PendaftaranWizardSession())->get($lembaga, $jalur);
    expect($session['data_pribadi']['nama_lengkap'])->toBe('Ahmad Fauzan');
    expect($session['keluarga'])->toHaveCount(2);
});

it('pre-fills data diri from an existing calon murid when nik and email both match a prior pendaftaran', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    $calonMurid = CalonMurid::factory()->create([
        'yayasan_id' => $lembaga->yayasan_id,
        'nik' => '3201234567890999',
        'nama_lengkap' => 'Nama Lama',
    ]);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'email_pendaftaran' => 'wali-lama@example.test',
    ]);
    siapkanEmailTerverifikasi($lembaga, $jalur, 'wali-lama@example.test');

    $response = $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/data-diri/cek-nik", ['nik' => '3201234567890999']);

    $response->assertOk();
    $response->assertSee('Nama Lama');
});

it('blocks the flow when nik matches but email does not match any prior pendaftaran for that calon murid', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    $calonMurid = CalonMurid::factory()->create([
        'yayasan_id' => $lembaga->yayasan_id,
        'nik' => '3201234567890999',
        'nama_lengkap' => 'Nama Lama',
    ]);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'email_pendaftaran' => 'wali-asli@example.test',
    ]);
    siapkanEmailTerverifikasi($lembaga, $jalur, 'wali-beda@example.test');

    $response = $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/data-diri/cek-nik", ['nik' => '3201234567890999']);

    $response->assertStatus(422);
    $response->assertDontSee('Nama Lama');
});
```

- [ ] **Step 3: Run the test to confirm it fails**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/Spmb/DataDiriTest.php`
Expected: FAIL — route/controller/view don't exist yet.

- [ ] **Step 4: Add routes**

Task 1 registered a placeholder for `spmb.data-diri` (`Route::get('{lembagaSlug}/{jalur}/data-diri', fn () => abort(404))->name('data-diri');`, with a preceding comment block) so its own views/redirects could resolve the route name before this task existed. **Remove that placeholder line** (and its comment, unless the comment also still covers `spmb.status.form` — in that case only remove the `data-diri` line and leave the comment covering the remaining placeholder) and replace it with the real routes below, inside the `spmb.` group, after the `verifikasi-otp.store` route:

```php
    Route::get('{lembagaSlug}/{jalur}/data-diri', [DataDiriController::class, 'create'])->name('data-diri');
    Route::post('{lembagaSlug}/{jalur}/data-diri/cek-nik', [DataDiriController::class, 'cekNik'])->name('data-diri.cek-nik');
    Route::post('{lembagaSlug}/{jalur}/data-diri', [DataDiriController::class, 'store'])->name('data-diri.store');
```

Add the import at the top of `routes/spmb.php`:

```php
use App\Http\Controllers\Spmb\DataDiriController;
```

- [ ] **Step 5: Create `DataDiriController`**

Create `app/Http/Controllers/Spmb/DataDiriController.php`:

```php
<?php

namespace App\Http\Controllers\Spmb;

use App\Http\Controllers\Spmb\Concerns\ResolvesSpmbTenant;
use App\Models\CalonMurid;
use App\Models\JalurPpdb;
use App\Models\Pendaftaran;
use App\Services\PendaftaranWizardSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class DataDiriController extends BaseController
{
    use ResolvesSpmbTenant;

    public function create(string $lembagaSlug, JalurPpdb $jalur): View
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $this->assertJalurBelongsToLembaga($lembaga, $jalur);

        return view('spmb.data-diri', ['lembaga' => $lembaga, 'jalur' => $jalur]);
    }

    public function cekNik(Request $request, string $lembagaSlug, JalurPpdb $jalur, PendaftaranWizardSession $wizardSession): JsonResponse
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $this->assertJalurBelongsToLembaga($lembaga, $jalur);

        $data = $request->validate(['nik' => ['required', 'digits:16']]);

        $calonMurid = CalonMurid::findByNik($data['nik']);

        if (! $calonMurid) {
            return response()->json(['ditemukan' => false]);
        }

        $emailSesi = $wizardSession->get($lembaga, $jalur)['email_pendaftaran'] ?? null;
        $emailCocok = Pendaftaran::where('calon_murid_id', $calonMurid->id)
            ->where('email_pendaftaran', $emailSesi)
            ->exists();

        if (! $emailCocok) {
            return response()->json([
                'ditemukan' => true,
                'diblokir' => true,
                'pesan' => 'NIK ini sudah pernah terdaftar. Gunakan email yang sama dengan pendaftaran sebelumnya, atau hubungi admin sekolah untuk bantuan.',
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

    public function store(Request $request, string $lembagaSlug, JalurPpdb $jalur, PendaftaranWizardSession $wizardSession): RedirectResponse
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $this->assertJalurBelongsToLembaga($lembaga, $jalur);

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

        return redirect()->route('spmb.formulir-tambahan', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]);
    }
}
```

- [ ] **Step 6: Create `data-diri.blade.php`**

Create `resources/views/spmb/data-diri.blade.php`:

```blade
<x-spmb-public-layout :lembaga="$lembaga" title="Data Diri">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-ink">Data Diri Calon Murid</h2>
        <p class="mt-1 text-sm text-slate">Isi sesuai dokumen resmi (akta kelahiran, KK). Gunakan huruf kapital di setiap awal kata.</p>

        <form method="POST" action="{{ route('spmb.data-diri.store', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]) }}" class="mt-5 space-y-6">
            @csrf

            <section class="space-y-4">
                <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-slate">Data Pribadi</h3>
                <div>
                    <x-input-label value="NIK" />
                    <x-text-input type="text" name="nik" value="{{ old('nik') }}" inputmode="numeric" maxlength="16" class="mt-1.5" required />
                    <x-input-error :messages="$errors->get('nik')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label value="Nama Lengkap (sesuai akta)" />
                    <x-text-input type="text" name="nama_lengkap" value="{{ old('nama_lengkap') }}" class="mt-1.5" required />
                    <x-input-error :messages="$errors->get('nama_lengkap')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label value="NISN (opsional)" />
                    <x-text-input type="text" name="nisn" value="{{ old('nisn') }}" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label value="Jenis Kelamin" />
                    <select name="jenis_kelamin" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass" required>
                        <option value="">Pilih</option>
                        <option value="L" @selected(old('jenis_kelamin') === 'L')>Laki-laki</option>
                        <option value="P" @selected(old('jenis_kelamin') === 'P')>Perempuan</option>
                    </select>
                    <x-input-error :messages="$errors->get('jenis_kelamin')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label value="Tempat Lahir" />
                    <x-text-input type="text" name="tempat_lahir" value="{{ old('tempat_lahir') }}" class="mt-1.5" required />
                    <x-input-error :messages="$errors->get('tempat_lahir')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label value="Tanggal Lahir" />
                    <x-text-input type="date" name="tanggal_lahir" value="{{ old('tanggal_lahir') }}" class="mt-1.5" required />
                    <x-input-error :messages="$errors->get('tanggal_lahir')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label value="Agama" />
                    <x-text-input type="text" name="agama" value="{{ old('agama') }}" class="mt-1.5" required />
                    <x-input-error :messages="$errors->get('agama')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label value="No. Telepon/WA Orang Tua" />
                    <x-text-input type="text" name="no_telepon" value="{{ old('no_telepon') }}" class="mt-1.5" />
                </div>
            </section>

            <section class="space-y-4">
                <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-slate">Alamat</h3>
                <div>
                    <x-input-label value="Alamat Jalan" />
                    <x-text-input type="text" name="alamat_jalan" value="{{ old('alamat_jalan') }}" class="mt-1.5" required />
                    <x-input-error :messages="$errors->get('alamat_jalan')" class="mt-1.5" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-input-label value="RT" />
                        <x-text-input type="text" name="rt" value="{{ old('rt') }}" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label value="RW" />
                        <x-text-input type="text" name="rw" value="{{ old('rw') }}" class="mt-1.5" />
                    </div>
                </div>
                <div>
                    <x-input-label value="Desa/Kelurahan" />
                    <x-text-input type="text" name="desa_kelurahan" value="{{ old('desa_kelurahan') }}" class="mt-1.5" required />
                    <x-input-error :messages="$errors->get('desa_kelurahan')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label value="Kecamatan" />
                    <x-text-input type="text" name="kecamatan" value="{{ old('kecamatan') }}" class="mt-1.5" required />
                    <x-input-error :messages="$errors->get('kecamatan')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label value="Kabupaten/Kota" />
                    <x-text-input type="text" name="kabupaten_kota" value="{{ old('kabupaten_kota') }}" class="mt-1.5" required />
                    <x-input-error :messages="$errors->get('kabupaten_kota')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label value="Provinsi" />
                    <x-text-input type="text" name="provinsi" value="{{ old('provinsi') }}" class="mt-1.5" required />
                    <x-input-error :messages="$errors->get('provinsi')" class="mt-1.5" />
                </div>
            </section>

            <section class="space-y-4" x-data="{ jumlah: 2 }">
                <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-slate">Data Orang Tua/Wali</h3>
                <template x-for="i in jumlah" :key="i">
                    <div class="rounded-xl border border-ink/10 p-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-ink">Jenis</label>
                                <select :name="'keluarga[' + (i - 1) + '][jenis]'" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                                    <option value="ayah" x-bind:selected="i === 1">Ayah</option>
                                    <option value="ibu" x-bind:selected="i === 2">Ibu</option>
                                    <option value="wali">Wali</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-ink">Nama</label>
                                <input :name="'keluarga[' + (i - 1) + '][nama]'" type="text" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="block text-sm font-medium text-ink">Pekerjaan</label>
                            <input :name="'keluarga[' + (i - 1) + '][pekerjaan]'" type="text" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        </div>
                    </div>
                </template>
                <button type="button" @click="jumlah++" class="text-sm font-medium text-ink hover:text-brass">+ Tambah Wali</button>
                <x-input-error :messages="$errors->get('keluarga')" class="mt-1.5" />
            </section>

            <x-primary-button>Lanjut ke Formulir Tambahan</x-primary-button>
        </form>
    </x-panel>
</x-spmb-public-layout>
```

- [ ] **Step 7: Run the test to confirm it passes**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/Spmb/DataDiriTest.php`
Expected: PASS (4 tests).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Spmb/DataDiriController.php resources/views/spmb/data-diri.blade.php routes/spmb.php tests/Feature/Spmb/DataDiriTest.php tests/Pest.php
git commit -m "feat: add data diri step with NIK-reuse-with-email-verification safeguard"
```

---

### Task 3: Formulir Tambahan + Upload Dokumen steps

**Files:**
- Create: `app/Http/Controllers/Spmb/FormulirTambahanController.php`
- Create: `app/Http/Controllers/Spmb/UploadDokumenController.php`
- Create: `resources/views/spmb/formulir-tambahan.blade.php`
- Create: `resources/views/spmb/upload-dokumen.blade.php`
- Modify: `routes/spmb.php`
- Test: `tests/Feature/Spmb/FormulirTambahanTest.php`
- Test: `tests/Feature/Spmb/UploadDokumenTest.php`

**Interfaces:**
- Consumes: `PendaftaranWizardSession` (Task 1), `FormulirField`/`DokumenSyaratPpdb` (Plan 1's M1 models, queried by `jalur_ppdb_id`), `App\Http\Controllers\Spmb\Concerns\ResolvesSpmbTenant` (Task 1), `siapkanEmailTerverifikasi()` test helper (Task 2, in `tests/Pest.php`).
- Produces: wizard session now additionally holds `jawaban_formulir` (array keyed by `formulir_field_id`) and `dokumen` (array keyed by `dokumen_syarat_ppdb_id` → temp file path under `storage/app/public/pendaftaran-tmp/{session id}/`).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Spmb/FormulirTambahanTest.php`:

```php
<?php

use App\Models\FormulirField;
use App\Services\PendaftaranWizardSession;

it('shows dynamic formulir fields for the selected jalur', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Nilai Rata-rata Rapor', 'field_type' => 'number']);
    siapkanEmailTerverifikasi($lembaga, $jalur, 'wali@example.test');

    $this->get("/spmb/{$lembaga->slug}/{$jalur->id}/formulir-tambahan")
        ->assertOk()
        ->assertSee('Nilai Rata-rata Rapor');
});

it('skips straight through when the jalur has no dynamic formulir fields', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    siapkanEmailTerverifikasi($lembaga, $jalur, 'wali@example.test');

    $this->get("/spmb/{$lembaga->slug}/{$jalur->id}/formulir-tambahan")->assertOk();
});

it('stores jawaban in the wizard session and advances to upload dokumen', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    $field = FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Nilai Rata-rata Rapor', 'field_type' => 'number']);
    siapkanEmailTerverifikasi($lembaga, $jalur, 'wali@example.test');

    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/formulir-tambahan", [
        'jawaban' => [$field->id => '88.5'],
    ])->assertRedirect("/spmb/{$lembaga->slug}/{$jalur->id}/dokumen");

    $session = (new PendaftaranWizardSession())->get($lembaga, $jalur);
    expect($session['jawaban_formulir'][$field->id])->toBe('88.5');
});
```

Create `tests/Feature/Spmb/UploadDokumenTest.php`:

```php
<?php

use App\Models\DokumenSyaratPpdb;
use App\Services\PendaftaranWizardSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('shows the dokumen syarat list for the selected jalur', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    siapkanEmailTerverifikasi($lembaga, $jalur, 'wali@example.test');

    $this->get("/spmb/{$lembaga->slug}/{$jalur->id}/dokumen")
        ->assertOk()
        ->assertSee('Akta Kelahiran');
});

it('uploads a valid file and stores its temp path in the wizard session', function () {
    Storage::fake('public');
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    $syarat = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    siapkanEmailTerverifikasi($lembaga, $jalur, 'wali@example.test');

    $file = UploadedFile::fake()->create('akta.pdf', 500, 'application/pdf');

    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/dokumen", [
        'dokumen' => [$syarat->id => $file],
    ])->assertRedirect("/spmb/{$lembaga->slug}/{$jalur->id}/review");

    $session = (new PendaftaranWizardSession())->get($lembaga, $jalur);
    expect($session['dokumen'][$syarat->id]['nama_file_asli'])->toBe('akta.pdf');
    Storage::disk('public')->assertExists($session['dokumen'][$syarat->id]['file_path']);
});

it('rejects a file that is too large or the wrong type', function () {
    Storage::fake('public');
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    $syarat = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    siapkanEmailTerverifikasi($lembaga, $jalur, 'wali@example.test');

    $tooBig = UploadedFile::fake()->create('akta.pdf', 3000, 'application/pdf');

    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/dokumen", [
        'dokumen' => [$syarat->id => $tooBig],
    ])->assertSessionHasErrors("dokumen.{$syarat->id}");
});
```

- [ ] **Step 2: Run the tests to confirm they fail**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/Spmb/FormulirTambahanTest.php tests/Feature/Spmb/UploadDokumenTest.php`
Expected: FAIL — routes/controllers/views don't exist yet.

- [ ] **Step 3: Add routes**

Task 2 registered a placeholder for `spmb.formulir-tambahan` (`Route::get('{lembagaSlug}/{jalur}/formulir-tambahan', fn () => abort(404))->name('formulir-tambahan');`, with a preceding comment block that also covers `spmb.status.form`) so its own `store()` redirect could resolve the route name before this task existed. **Remove the `formulir-tambahan` placeholder line** (leave the `status.form` placeholder and its comment in place — that one is still Task 5's responsibility) and replace it with the real routes below. Add to `routes/spmb.php` (after the `data-diri.store` route), plus the two new imports at the top:

```php
use App\Http\Controllers\Spmb\FormulirTambahanController;
use App\Http\Controllers\Spmb\UploadDokumenController;
```

```php
    Route::get('{lembagaSlug}/{jalur}/formulir-tambahan', [FormulirTambahanController::class, 'create'])->name('formulir-tambahan');
    Route::post('{lembagaSlug}/{jalur}/formulir-tambahan', [FormulirTambahanController::class, 'store'])->name('formulir-tambahan.store');
    Route::get('{lembagaSlug}/{jalur}/dokumen', [UploadDokumenController::class, 'create'])->name('dokumen');
    Route::post('{lembagaSlug}/{jalur}/dokumen', [UploadDokumenController::class, 'store'])->name('dokumen.store');
```

- [ ] **Step 4: Create `FormulirTambahanController`**

Create `app/Http/Controllers/Spmb/FormulirTambahanController.php`:

```php
<?php

namespace App\Http\Controllers\Spmb;

use App\Http\Controllers\Spmb\Concerns\ResolvesSpmbTenant;
use App\Models\FormulirField;
use App\Models\JalurPpdb;
use App\Services\PendaftaranWizardSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class FormulirTambahanController extends BaseController
{
    use ResolvesSpmbTenant;

    public function create(string $lembagaSlug, JalurPpdb $jalur): View
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $this->assertJalurBelongsToLembaga($lembaga, $jalur);
        $fieldList = FormulirField::where('jalur_ppdb_id', $jalur->id)->orderBy('urutan')->get();

        return view('spmb.formulir-tambahan', ['lembaga' => $lembaga, 'jalur' => $jalur, 'fieldList' => $fieldList]);
    }

    public function store(Request $request, string $lembagaSlug, JalurPpdb $jalur, PendaftaranWizardSession $wizardSession): RedirectResponse
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $this->assertJalurBelongsToLembaga($lembaga, $jalur);

        $fieldList = FormulirField::where('jalur_ppdb_id', $jalur->id)->get();
        $rules = [];
        foreach ($fieldList as $field) {
            $rules["jawaban.{$field->id}"] = $field->is_required ? ['required'] : ['nullable'];
        }
        $data = $request->validate($rules);

        $wizardSession->put($lembaga, $jalur, ['jawaban_formulir' => $data['jawaban'] ?? []]);

        return redirect()->route('spmb.dokumen', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]);
    }
}
```

- [ ] **Step 5: Create `formulir-tambahan.blade.php`**

Create `resources/views/spmb/formulir-tambahan.blade.php`:

```blade
<x-spmb-public-layout :lembaga="$lembaga" title="Formulir Tambahan">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-ink">Formulir Tambahan</h2>

        <form method="POST" action="{{ route('spmb.formulir-tambahan.store', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]) }}" class="mt-5 space-y-4">
            @csrf

            @forelse ($fieldList as $field)
                <div>
                    <x-input-label :value="$field->label . ($field->is_required ? ' *' : '')" />
                    @if ($field->field_type === 'textarea')
                        <textarea name="jawaban[{{ $field->id }}]" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass" @required($field->is_required)></textarea>
                    @elseif ($field->field_type === 'select')
                        <select name="jawaban[{{ $field->id }}]" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass" @required($field->is_required)>
                            <option value="">Pilih</option>
                            @foreach ($field->options ?? [] as $opsi)
                                <option value="{{ $opsi }}">{{ $opsi }}</option>
                            @endforeach
                        </select>
                    @else
                        <x-text-input
                            :type="$field->field_type === 'number' ? 'number' : ($field->field_type === 'date' ? 'date' : 'text')"
                            name="jawaban[{{ $field->id }}]"
                            class="mt-1.5"
                            @required($field->is_required)
                        />
                    @endif
                    <x-input-error :messages="$errors->get('jawaban.' . $field->id)" class="mt-1.5" />
                </div>
            @empty
                <p class="text-sm text-slate">Tidak ada formulir tambahan untuk jalur ini.</p>
            @endforelse

            <x-primary-button>Lanjut ke Upload Dokumen</x-primary-button>
        </form>
    </x-panel>
</x-spmb-public-layout>
```

- [ ] **Step 6: Create `UploadDokumenController`**

Create `app/Http/Controllers/Spmb/UploadDokumenController.php`:

```php
<?php

namespace App\Http\Controllers\Spmb;

use App\Http\Controllers\Spmb\Concerns\ResolvesSpmbTenant;
use App\Models\DokumenSyaratPpdb;
use App\Models\JalurPpdb;
use App\Services\PendaftaranWizardSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class UploadDokumenController extends BaseController
{
    use ResolvesSpmbTenant;

    public function create(string $lembagaSlug, JalurPpdb $jalur): View
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $this->assertJalurBelongsToLembaga($lembaga, $jalur);
        $syaratList = DokumenSyaratPpdb::where('jalur_ppdb_id', $jalur->id)->orderBy('urutan')->get();

        return view('spmb.upload-dokumen', ['lembaga' => $lembaga, 'jalur' => $jalur, 'syaratList' => $syaratList]);
    }

    public function store(Request $request, string $lembagaSlug, JalurPpdb $jalur, PendaftaranWizardSession $wizardSession): RedirectResponse
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $this->assertJalurBelongsToLembaga($lembaga, $jalur);

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

        return redirect()->route('spmb.review', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]);
    }
}
```

- [ ] **Step 7: Create `upload-dokumen.blade.php`**

Create `resources/views/spmb/upload-dokumen.blade.php`:

```blade
<x-spmb-public-layout :lembaga="$lembaga" title="Upload Dokumen">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-ink">Upload Dokumen</h2>

        <form method="POST" action="{{ route('spmb.dokumen.store', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]) }}" enctype="multipart/form-data" class="mt-5 space-y-4">
            @csrf

            @forelse ($syaratList as $syarat)
                <div class="rounded-xl border-2 border-dashed border-slate/30 p-4">
                    <x-input-label :value="$syarat->nama_dokumen . ($syarat->wajib ? ' *' : ' (opsional)')" />
                    <p class="mt-1 text-xs text-slate">PDF/JPG/PNG, maks 2MB</p>
                    <input type="file" name="dokumen[{{ $syarat->id }}]" class="mt-2 block w-full text-sm text-slate" @required($syarat->wajib) />
                    <x-input-error :messages="$errors->get('dokumen.' . $syarat->id)" class="mt-1.5" />
                </div>
            @empty
                <p class="text-sm text-slate">Tidak ada dokumen yang perlu diupload untuk jalur ini.</p>
            @endforelse

            <x-primary-button>Lanjut ke Review</x-primary-button>
        </form>
    </x-panel>
</x-spmb-public-layout>
```

- [ ] **Step 8: Run the tests to confirm they pass**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/Spmb/FormulirTambahanTest.php tests/Feature/Spmb/UploadDokumenTest.php`
Expected: PASS (6 tests).

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Spmb/FormulirTambahanController.php app/Http/Controllers/Spmb/UploadDokumenController.php resources/views/spmb/formulir-tambahan.blade.php resources/views/spmb/upload-dokumen.blade.php routes/spmb.php tests/Feature/Spmb/FormulirTambahanTest.php tests/Feature/Spmb/UploadDokumenTest.php
git commit -m "feat: add formulir tambahan and upload dokumen wizard steps"
```

---

### Task 4: Review & Submit (atomic transaction)

**Files:**
- Create: `app/Http/Controllers/Spmb/ReviewSubmitController.php`
- Create: `app/Mail/PendaftaranBerhasilMail.php`
- Create: `resources/views/mail/pendaftaran-berhasil.blade.php`
- Create: `resources/views/spmb/review.blade.php`
- Create: `resources/views/spmb/berhasil.blade.php`
- Modify: `routes/spmb.php`
- Test: `tests/Feature/Spmb/ReviewSubmitTest.php`

**Interfaces:**
- Consumes: `PendaftaranWizardSession` (Task 1, fully populated by Tasks 2-3), `KodePendaftaranGenerator::generate(int $lembagaId): string` (Plan 1), all 9 Plan-1 models.
- Produces: a real `Pendaftaran` row (plus `CalonMurid`/satellites/`JawabanFormulirPendaftaran`/`DokumenPendaftaran`) created atomically, with a retry-on-collision wrapper around the unique `(lembaga_id, kode_pendaftaran)` constraint; wizard session cleared on success.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Spmb/ReviewSubmitTest.php`:

```php
<?php

use App\Models\CalonMurid;
use App\Models\Pendaftaran;
use App\Services\PendaftaranWizardSession;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function isiWizardLengkap($lembaga, $jalur, string $email): void
{
    $wizardSession = new PendaftaranWizardSession();
    $wizardSession->put($lembaga, $jalur, [
        'email_pendaftaran' => $email,
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
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    isiWizardLengkap($lembaga, $jalur, 'wali@example.test');

    $this->get("/spmb/{$lembaga->slug}/{$jalur->id}/review")
        ->assertOk()
        ->assertSee('Ahmad Fauzan');
});

it('submits the full pendaftaran atomically and clears the wizard session', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    isiWizardLengkap($lembaga, $jalur, 'wali@example.test');

    $response = $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/submit");

    $pendaftaran = Pendaftaran::first();
    $response->assertRedirect("/spmb/{$lembaga->slug}/berhasil/{$pendaftaran->kode_pendaftaran}");

    expect($pendaftaran->status)->toBe('menunggu_verifikasi');
    expect($pendaftaran->email_pendaftaran)->toBe('wali@example.test');
    expect($pendaftaran->calonMurid->nama_lengkap)->toBe('Ahmad Fauzan');
    expect($pendaftaran->calonMurid->alamat->kabupaten_kota)->toBe('Bandung');
    expect($pendaftaran->calonMurid->keluarga)->toHaveCount(1);

    $session = (new PendaftaranWizardSession())->get($lembaga, $jalur);
    expect($session)->toBe([]);
});

it('reuses the existing calon murid record when the nik already exists for this yayasan', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    $existing = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id, 'nik' => '3201234567890123']);
    isiWizardLengkap($lembaga, $jalur, 'wali@example.test');

    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/submit");

    expect(CalonMurid::count())->toBe(1);
    expect(Pendaftaran::first()->calon_murid_id)->toBe($existing->id);
});

it('sends a confirmation email containing the kode pendaftaran', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    isiWizardLengkap($lembaga, $jalur, 'wali@example.test');

    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/submit");

    Mail::assertSent(App\Mail\PendaftaranBerhasilMail::class, function ($mail) {
        return $mail->hasTo('wali@example.test');
    });
});

it('retries with a fresh kode when the generated kode collides with a race-condition duplicate at insert time', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    isiWizardLengkap($lembaga, $jalur, 'wali@example.test');

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

    $response = $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/submit");

    $response->assertRedirect("/spmb/{$lembaga->slug}/berhasil/REG-2026-00002");
    expect(Pendaftaran::where('kode_pendaftaran', 'REG-2026-00002')->exists())->toBeTrue();
});

afterEach(function () {
    Mockery::close();
});

it('shows the success page with the kode pendaftaran', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    isiWizardLengkap($lembaga, $jalur, 'wali@example.test');
    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/submit");
    $kode = Pendaftaran::first()->kode_pendaftaran;

    $this->get("/spmb/{$lembaga->slug}/berhasil/{$kode}")->assertOk()->assertSee($kode);
});
```

- [ ] **Step 2: Run the test to confirm it fails**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/Spmb/ReviewSubmitTest.php`
Expected: FAIL — route/controller/views don't exist yet.

- [ ] **Step 3: Add routes**

Add to `routes/spmb.php` (after the `dokumen.store` route), plus the import at the top:

```php
use App\Http\Controllers\Spmb\ReviewSubmitController;
```

```php
    Route::get('{lembagaSlug}/{jalur}/review', [ReviewSubmitController::class, 'show'])->name('review');
    Route::post('{lembagaSlug}/{jalur}/submit', [ReviewSubmitController::class, 'submit'])
        ->middleware('throttle:10,1')->name('submit');
    Route::get('{lembagaSlug}/berhasil/{kodePendaftaran}', [ReviewSubmitController::class, 'berhasil'])->name('berhasil');
```

- [ ] **Step 4: Create the Mailable and its view**

Create `app/Mail/PendaftaranBerhasilMail.php`:

```php
<?php

namespace App\Mail;

use App\Models\Pendaftaran;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PendaftaranBerhasilMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Pendaftaran $pendaftaran)
    {
    }

    public function build(): self
    {
        return $this->subject('Pendaftaran SPMB Berhasil — '.$this->pendaftaran->kode_pendaftaran)
            ->view('mail.pendaftaran-berhasil')
            ->with(['pendaftaran' => $this->pendaftaran]);
    }
}
```

Create `resources/views/mail/pendaftaran-berhasil.blade.php`:

```blade
<p>Pendaftaran SPMB atas nama <strong>{{ $pendaftaran->calonMurid->nama_lengkap }}</strong> berhasil kami terima.</p>

<p>Kode Pendaftaran Anda: <strong style="font-size: 20px; letter-spacing: 2px;">{{ $pendaftaran->kode_pendaftaran }}</strong></p>

<p>Simpan kode ini untuk mengecek status pendaftaran kapan saja di halaman cek status sekolah.</p>

<p>Status saat ini: Menunggu Verifikasi</p>
```

- [ ] **Step 5: Create `ReviewSubmitController`**

Create `app/Http/Controllers/Spmb/ReviewSubmitController.php`:

```php
<?php

namespace App\Http\Controllers\Spmb;

use App\Http\Controllers\Spmb\Concerns\ResolvesSpmbTenant;
use App\Mail\PendaftaranBerhasilMail;
use App\Models\AlamatCalonMurid;
use App\Models\CalonMurid;
use App\Models\DataKhususCalonMurid;
use App\Models\DataPeriodikCalonMurid;
use App\Models\DokumenPendaftaran;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\JawabanFormulirPendaftaran;
use App\Models\KeluargaCalonMurid;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\TahunAjaran;
use App\Services\KodePendaftaranGenerator;
use App\Services\PendaftaranWizardSession;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class ReviewSubmitController extends BaseController
{
    use ResolvesSpmbTenant;

    private const MAKS_PERCOBAAN_KODE = 5;

    public function show(string $lembagaSlug, JalurPpdb $jalur, PendaftaranWizardSession $wizardSession): View
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $this->assertJalurBelongsToLembaga($lembaga, $jalur);
        $session = $wizardSession->get($lembaga, $jalur);

        return view('spmb.review', ['lembaga' => $lembaga, 'jalur' => $jalur, 'session' => $session]);
    }

    public function submit(
        string $lembagaSlug,
        JalurPpdb $jalur,
        PendaftaranWizardSession $wizardSession,
        KodePendaftaranGenerator $kodeGenerator
    ): RedirectResponse {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $this->assertJalurBelongsToLembaga($lembaga, $jalur);
        $session = $wizardSession->get($lembaga, $jalur);
        $tahunAjaranAktif = PortalController::cariGelombangAktif($lembaga)?->tahunAjaran;

        $pendaftaran = DB::transaction(function () use ($lembaga, $jalur, $session, $kodeGenerator, $tahunAjaranAktif) {
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

            $gelombang = PortalController::cariGelombangAktif($lembaga);

            $pendaftaran = $this->buatPendaftaranDenganRetryKode(
                $calonMurid, $lembaga, $tahunAjaranAktif, $jalur, $gelombang, $session, $kodeGenerator
            );

            foreach ($session['jawaban_formulir'] ?? [] as $fieldId => $nilai) {
                JawabanFormulirPendaftaran::create([
                    'pendaftaran_id' => $pendaftaran->id, 'formulir_field_id' => $fieldId, 'nilai' => $nilai,
                ]);
            }

            foreach ($session['dokumen'] ?? [] as $syaratId => $berkas) {
                $tujuan = 'pendaftaran/'.$pendaftaran->id.'/'.basename($berkas['file_path']);
                Storage::disk('public')->move($berkas['file_path'], $tujuan);

                DokumenPendaftaran::create([
                    'pendaftaran_id' => $pendaftaran->id,
                    'dokumen_syarat_ppdb_id' => $syaratId,
                    'file_path' => $tujuan,
                    'nama_file_asli' => $berkas['nama_file_asli'],
                    'mime_type' => $berkas['mime_type'],
                    'ukuran_bytes' => $berkas['ukuran_bytes'],
                ]);
            }

            return $pendaftaran;
        });

        Mail::to($pendaftaran->email_pendaftaran)->send(new PendaftaranBerhasilMail($pendaftaran));

        $wizardSession->clear($lembaga, $jalur);

        return redirect()->route('spmb.berhasil', ['lembagaSlug' => $lembaga->slug, 'kodePendaftaran' => $pendaftaran->kode_pendaftaran]);
    }

    /**
     * KodePendaftaranGenerator::generate() checks for an existing row and returns a
     * candidate code, but a second request can insert the same code in the gap between
     * that check and this create() call. Retry with a freshly generated code when the
     * (lembaga_id, kode_pendaftaran) unique constraint is the one that failed — any other
     * constraint violation is a real data problem and must propagate.
     */
    private function buatPendaftaranDenganRetryKode(
        CalonMurid $calonMurid,
        Lembaga $lembaga,
        TahunAjaran $tahunAjaran,
        JalurPpdb $jalur,
        GelombangPpdb $gelombang,
        array $session,
        KodePendaftaranGenerator $kodeGenerator
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
                    'email_pendaftaran' => $session['email_pendaftaran'],
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

    public function berhasil(string $lembagaSlug, string $kodePendaftaran): View
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $pendaftaran = Pendaftaran::where('lembaga_id', $lembaga->id)
            ->where('kode_pendaftaran', $kodePendaftaran)
            ->firstOrFail();

        return view('spmb.berhasil', ['lembaga' => $lembaga, 'pendaftaran' => $pendaftaran]);
    }
}
```

Note on the unique index name: Laravel's default naming for `$table->unique(['lembaga_id', 'kode_pendaftaran'])` on the `pendaftaran` table is `pendaftaran_lembaga_id_kode_pendaftaran_unique` — confirm this matches by running `SHOW CREATE TABLE pendaftaran;` (via `php artisan tinker` or a DB client) before relying on the string match if the constraint was ever renamed.

- [ ] **Step 6: Create `review.blade.php` and `berhasil.blade.php`**

Create `resources/views/spmb/review.blade.php`:

```blade
<x-spmb-public-layout :lembaga="$lembaga" title="Review">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-ink">Review Data</h2>
        <p class="mt-1 text-sm text-slate">Periksa kembali sebelum mengirim.</p>

        <dl class="mt-5 divide-y divide-ink/10 text-sm">
            <div class="flex justify-between py-2"><dt class="text-slate">Nama Lengkap</dt><dd class="font-medium text-ink">{{ $session['data_pribadi']['nama_lengkap'] ?? '-' }}</dd></div>
            <div class="flex justify-between py-2"><dt class="text-slate">NIK</dt><dd class="font-mono text-ink">{{ $session['nik'] ?? '-' }}</dd></div>
            <div class="flex justify-between py-2"><dt class="text-slate">Email</dt><dd class="text-ink">{{ $session['email_pendaftaran'] ?? '-' }}</dd></div>
        </dl>

        <form method="POST" action="{{ route('spmb.submit', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]) }}" class="mt-6">
            @csrf
            <x-primary-button>Kirim Pendaftaran</x-primary-button>
        </form>
    </x-panel>
</x-spmb-public-layout>
```

Create `resources/views/spmb/berhasil.blade.php`:

```blade
<x-spmb-public-layout :lembaga="$lembaga" title="Pendaftaran Berhasil">
    <x-panel class="p-6 text-center">
        <p class="font-display text-lg font-bold text-signal-green">Pendaftaran Berhasil</p>
        <p class="mt-3 text-sm text-slate">Kode Pendaftaran Anda:</p>
        <p class="mt-1 font-mono text-2xl font-bold tracking-widest text-ink">{{ $pendaftaran->kode_pendaftaran }}</p>
        <p class="mt-4 text-sm text-slate">Kode ini juga sudah dikirim ke {{ $pendaftaran->email_pendaftaran }}. Simpan untuk cek status nanti.</p>
    </x-panel>
</x-spmb-public-layout>
```

- [ ] **Step 7: Run the test to confirm it passes**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/Spmb/ReviewSubmitTest.php`
Expected: PASS (6 tests). If the retry test fails because the unique index name doesn't match, run `SHOW CREATE TABLE pendaftaran;` to get the actual generated constraint name and update the `str_contains` check in `buatPendaftaranDenganRetryKode()` accordingly — this is a mechanical name lookup, not a design change.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Spmb/ReviewSubmitController.php app/Mail/PendaftaranBerhasilMail.php resources/views/mail/pendaftaran-berhasil.blade.php resources/views/spmb/review.blade.php resources/views/spmb/berhasil.blade.php routes/spmb.php tests/Feature/Spmb/ReviewSubmitTest.php
git commit -m "feat: add review and atomic submit step, completing the registration wizard"
```

---

### Task 5: Cek Status page + bukti pendaftaran PDF

**Files:**
- Create: `app/Http/Controllers/Spmb/CekStatusController.php`
- Create: `resources/views/spmb/cek-status.blade.php`
- Create: `resources/views/spmb/status-hasil.blade.php`
- Create: `resources/views/pdf/bukti-pendaftaran.blade.php`
- Modify: `routes/spmb.php`
- Test: `tests/Feature/Spmb/CekStatusTest.php`

**Interfaces:**
- Consumes: `Pendaftaran` (Plan 1), `barryvdh/laravel-dompdf`'s `Pdf` facade.
- Produces: a public status-lookup page and a PDF download, both gated by kode+email matching (not by session — a wali murid may check status from a different device/session than the one they registered from).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Spmb/CekStatusTest.php`:

```php
<?php

use App\Models\CalonMurid;
use App\Models\Pendaftaran;

it('shows the status form', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();

    $this->get("/spmb/{$lembaga->slug}/status")->assertOk();
});

it('shows the pendaftaran summary and status when kode and email match', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id, 'nama_lengkap' => 'Ahmad Fauzan']);
    $pendaftaran = Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00001', 'email_pendaftaran' => 'wali@example.test',
    ]);

    $this->post("/spmb/{$lembaga->slug}/status", [
        'kode_pendaftaran' => 'REG-2026-00001', 'email' => 'wali@example.test',
    ])->assertOk()->assertSee('Ahmad Fauzan')->assertSee('Menunggu Verifikasi');
});

it('rejects a kode+email combination that does not match', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00001', 'email_pendaftaran' => 'wali@example.test',
    ]);

    $this->post("/spmb/{$lembaga->slug}/status", [
        'kode_pendaftaran' => 'REG-2026-00001', 'email' => 'salah@example.test',
    ])->assertSessionHasErrors('kode_pendaftaran');
});

it('downloads a pdf bukti pendaftaran when kode and email match', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00001', 'email_pendaftaran' => 'wali@example.test',
    ]);

    $response = $this->get("/spmb/{$lembaga->slug}/bukti/REG-2026-00001?email=wali@example.test");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('404s the pdf download when the email does not match', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00001', 'email_pendaftaran' => 'wali@example.test',
    ]);

    $this->get("/spmb/{$lembaga->slug}/bukti/REG-2026-00001?email=salah@example.test")->assertNotFound();
});
```

- [ ] **Step 2: Run the test to confirm it fails**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/Spmb/CekStatusTest.php`
Expected: FAIL — routes/controller/views don't exist yet.

- [ ] **Step 3: Add routes**

Task 1 registered a placeholder for `spmb.status.form` (`Route::get('{lembagaSlug}/status', fn () => abort(404))->name('status.form');`, with a preceding comment block explaining it's a stub) so its own `pilih-jalur.blade.php` link could resolve the route name before this task existed. **Remove that placeholder line entirely** (and its comment block, since by this task no other placeholder from Task 1 should remain) and replace it with the real routes below. Add to `routes/spmb.php`, plus the import at the top:

```php
use App\Http\Controllers\Spmb\CekStatusController;
```

```php
    Route::get('{lembagaSlug}/status', [CekStatusController::class, 'create'])->name('status.form');
    Route::post('{lembagaSlug}/status', [CekStatusController::class, 'show'])
        ->middleware('throttle:10,1')->name('status.show');
    Route::get('{lembagaSlug}/bukti/{kodePendaftaran}', [CekStatusController::class, 'unduhBukti'])->name('bukti');
```

- [ ] **Step 4: Create `CekStatusController`**

Create `app/Http/Controllers/Spmb/CekStatusController.php`:

```php
<?php

namespace App\Http\Controllers\Spmb;

use App\Models\Lembaga;
use App\Models\Pendaftaran;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class CekStatusController extends BaseController
{
    public function create(string $lembagaSlug): View
    {
        $lembaga = Lembaga::where('slug', $lembagaSlug)->firstOrFail();

        return view('spmb.cek-status', ['lembaga' => $lembaga]);
    }

    public function show(Request $request, string $lembagaSlug): View|RedirectResponse
    {
        $lembaga = Lembaga::where('slug', $lembagaSlug)->firstOrFail();

        $data = $request->validate([
            'kode_pendaftaran' => ['required', 'string'],
            'email' => ['required', 'email'],
        ]);

        $pendaftaran = Pendaftaran::where('lembaga_id', $lembaga->id)
            ->where('kode_pendaftaran', $data['kode_pendaftaran'])
            ->where('email_pendaftaran', $data['email'])
            ->first();

        if (! $pendaftaran) {
            return back()->withErrors(['kode_pendaftaran' => 'Kode pendaftaran dan email tidak ditemukan atau tidak cocok.']);
        }

        return view('spmb.status-hasil', ['lembaga' => $lembaga, 'pendaftaran' => $pendaftaran]);
    }

    public function unduhBukti(Request $request, string $lembagaSlug, string $kodePendaftaran): Response
    {
        $lembaga = Lembaga::where('slug', $lembagaSlug)->firstOrFail();

        $pendaftaran = Pendaftaran::where('lembaga_id', $lembaga->id)
            ->where('kode_pendaftaran', $kodePendaftaran)
            ->where('email_pendaftaran', $request->query('email'))
            ->firstOrFail();

        $pdf = Pdf::loadView('pdf.bukti-pendaftaran', ['lembaga' => $lembaga, 'pendaftaran' => $pendaftaran]);

        return $pdf->stream('bukti-pendaftaran-'.$pendaftaran->kode_pendaftaran.'.pdf');
    }
}
```

- [ ] **Step 5: Create the three views**

Create `resources/views/spmb/cek-status.blade.php`:

```blade
<x-spmb-public-layout :lembaga="$lembaga" title="Cek Status">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-ink">Cek Status Pendaftaran</h2>

        <form method="POST" action="{{ route('spmb.status.show', ['lembagaSlug' => $lembaga->slug]) }}" class="mt-5 space-y-4">
            @csrf
            <div>
                <x-input-label value="Kode Pendaftaran" />
                <x-text-input type="text" name="kode_pendaftaran" value="{{ old('kode_pendaftaran') }}" class="mt-1.5 font-mono" required />
                <x-input-error :messages="$errors->get('kode_pendaftaran')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Email" />
                <x-text-input type="email" name="email" value="{{ old('email') }}" class="mt-1.5" required />
                <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
            </div>
            <x-primary-button>Cek Status</x-primary-button>
        </form>
    </x-panel>
</x-spmb-public-layout>
```

Create `resources/views/spmb/status-hasil.blade.php`:

```blade
<x-spmb-public-layout :lembaga="$lembaga" title="Status Pendaftaran">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-ink">Status Pendaftaran</h2>

        <span class="mt-3 inline-flex items-center rounded-full bg-signal-amber/10 px-3 py-1 text-sm font-bold text-signal-amber">
            @if ($pendaftaran->status === 'menunggu_verifikasi') Menunggu Verifikasi
            @elseif ($pendaftaran->status === 'diterima') Diterima
            @elseif ($pendaftaran->status === 'ditolak') Ditolak
            @else {{ $pendaftaran->status }}
            @endif
        </span>

        <dl class="mt-5 divide-y divide-ink/10 text-sm">
            <div class="flex justify-between py-2"><dt class="text-slate">Nama</dt><dd class="font-medium text-ink">{{ $pendaftaran->calonMurid->nama_lengkap }}</dd></div>
            <div class="flex justify-between py-2"><dt class="text-slate">Jalur</dt><dd class="text-ink">{{ $pendaftaran->jalurPpdb->nama }}</dd></div>
            <div class="flex justify-between py-2"><dt class="text-slate">Kode Pendaftaran</dt><dd class="font-mono text-ink">{{ $pendaftaran->kode_pendaftaran }}</dd></div>
            <div class="flex justify-between py-2"><dt class="text-slate">Tanggal Submit</dt><dd class="text-ink">{{ $pendaftaran->submitted_at->translatedFormat('d F Y H:i') }}</dd></div>
        </dl>

        <a
            href="{{ route('spmb.bukti', ['lembagaSlug' => $lembaga->slug, 'kodePendaftaran' => $pendaftaran->kode_pendaftaran, 'email' => $pendaftaran->email_pendaftaran]) }}"
            class="mt-6 inline-flex items-center gap-2 rounded-xl border border-ink/15 px-4 py-2.5 text-sm font-bold text-ink hover:bg-paper"
        >
            Unduh Bukti Pendaftaran (PDF)
        </a>
    </x-panel>
</x-spmb-public-layout>
```

Create `resources/views/pdf/bukti-pendaftaran.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        h1 { font-size: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        td { padding: 6px 0; }
        td.label { color: #5B6478; width: 40%; }
    </style>
</head>
<body>
    <h1>Bukti Pendaftaran SPMB — {{ $lembaga->nama }}</h1>
    <p>Kode Pendaftaran: <strong>{{ $pendaftaran->kode_pendaftaran }}</strong></p>

    <table>
        <tr><td class="label">Nama Calon Murid</td><td>{{ $pendaftaran->calonMurid->nama_lengkap }}</td></tr>
        <tr><td class="label">Jalur</td><td>{{ $pendaftaran->jalurPpdb->nama }}</td></tr>
        <tr><td class="label">Gelombang</td><td>{{ $pendaftaran->gelombangPpdb->nama }}</td></tr>
        <tr><td class="label">Tanggal Submit</td><td>{{ $pendaftaran->submitted_at->format('d F Y H:i') }}</td></tr>
        <tr><td class="label">Status</td><td>{{ $pendaftaran->status }}</td></tr>
    </table>
</body>
</html>
```

- [ ] **Step 6: Run the test to confirm it passes**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test tests/Feature/Spmb/CekStatusTest.php`
Expected: PASS (5 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Spmb/CekStatusController.php resources/views/spmb/cek-status.blade.php resources/views/spmb/status-hasil.blade.php resources/views/pdf/bukti-pendaftaran.blade.php routes/spmb.php tests/Feature/Spmb/CekStatusTest.php
git commit -m "feat: add cek status page and PDF bukti pendaftaran download"
```

---

### Task 6: Final regression and manual walkthrough

**Files:** none (verification only)

- [ ] **Step 1: Run the full Pest suite**

Run: `"D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe" artisan test`
Expected: PASS, 0 failures (this plan added roughly 28 new tests on top of the 189-test baseline, so expect around 217 passed).

- [ ] **Step 2: Confirm the frontend assets build cleanly**

Run `npm run build` (Node available at `D:\laragon\bin\nodejs\node-v24.15.0-win-x64\` if not on PATH) from `D:\laragon\www\pintera-app`.
Expected: Vite build completes with no errors (this plan added no new JS, only Blade views, so this is primarily a safety check that nothing broke).

- [ ] **Step 3: Full manual walkthrough**

With the dev server running, open `/spmb/{a real lembaga slug from seeded demo data}` in a browser and walk through the entire flow end-to-end: pilih jalur → verifikasi email (check `storage/logs/laravel.log` for the logged OTP email since `MAIL_MAILER=log`) → input OTP → data diri (including the NIK-reuse path: submit once, then start a second registration with the same NIK and same email to confirm prefill, and a third attempt with the same NIK but a different email to confirm the block message appears) → formulir tambahan → upload dokumen (a real small PDF/JPG) → review → submit → confirm the success page shows a kode pendaftaran → visit `/spmb/{slug}/status` and confirm the kode+email combination shows the right status → download the PDF bukti pendaftaran and confirm it opens correctly.

- [ ] **Step 4: Commit (only if Step 1-3 required any fixes)**

If everything passes with no changes needed, there is nothing to commit for this task. If any fix was needed, commit it with a message describing what regression it addressed.
