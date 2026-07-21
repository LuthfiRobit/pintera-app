# SPMB Registrasi Akun Baru + Verifikasi Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the gaps between the already-working `Portal\Auth\*` registration/OTP/login/password-reset system and the account-first SPMB redesign — a `no_hp_wa` column, a strong-password rule, a `spmb.register` route, an explicit OTP resend action, session-carried lembaga/jalur context on the register page, and a navy-portal redesign of all 5 auth pages — without rebuilding any of the working backend logic.

**Architecture:** All 5 existing `Portal\Auth\*` controllers are modified in place (not replaced); route names (`portal.*`) stay as they are, with exactly one new route added (`spmb.register`, same controller action) so Sub-project 1's `Route::has('spmb.register')` check activates. A new shared layout (`layouts.portal-auth`) and top-bar component (`x-portal-auth-top`) — distinct from Sub-project 1's `layouts.portal-public`/`x-portal-navbar`, matching the mockup's simplified auth-page chrome — wrap all 5 redesigned views, reusing Sub-project 1's `<x-portal-footer>` and `<x-icon>` components (extended with 4 new icons this plan adds).

**Tech Stack:** Laravel 12, Blade, Tailwind CSS (existing `portal-*`/`gray-*`/`success-*`/`warning-*`/`error-*` token set), Alpine.js (existing app-wide setup; reuses the existing `otpInput()` component, adds one new `passwordStrength()` component), Pest PHP.

## Global Constraints

- Visual tokens: use only the Tailwind utility classes already configured in `tailwind.config.js` — `portal-50/500/600`, `gray-50...900`, `success-50/500/700`, `warning-50/500/700`, `error-50/500/700`. Font is already `Outfit` app-wide. Where the mockup specifies an exact px/rem/rgba value with no matching named Tailwind token, use an arbitrary-value class (`text-[13.5px]`, `shadow-[0_20px_44px_rgba(30,58,95,0.10)]`, etc.) rather than approximating with the nearest named utility — Sub-project 1 shipped with several "close enough" approximations (wrong shadow size, wrong gradient angle, missing icons) that needed a dedicated follow-up fidelity pass after the user flagged them; get the exact values right the first time here.
- Do not modify `resources/views/layouts/spmb-public.blade.php`, `resources/views/components/spmb-public-layout.blade.php`, `resources/views/layouts/portal-public.blade.php` (component), `resources/views/components/portal-navbar.blade.php`, or any Sub-project 1 file outside of reading `<x-portal-footer>`/`<x-icon>` for reuse.
- `App\Services\OtpService` and `App\Models\VerifikasiEmailOtp` are reused as-is — do not change their structure or internal logic (only add new call sites).
- `resources/js/otp-input.js`'s `otpInput()` Alpine component is reused as-is for the redesigned OTP page — do not rewrite its digit-entry logic, only restyle the markup that uses it.
- Every page must be responsive at all viewport widths, following the same conventions as Sub-project 1 (arbitrary breakpoints where the mockup specifies one, e.g. `≤860px` for the register page's 2-column collapse, `≤480px` for the email/phone field pair).
- `portal.register`'s existing route name, `RegisteredAkunController`, `VerifikasiOtpController`, `AuthenticatedSessionController`, `PasswordResetLinkController`, `NewPasswordController` class names, and the `password_reset_tokens`/`verifikasi_email_otp` table structures are not renamed or restructured — only their view/validation/route surface is extended.
- PHP is not on PATH — use `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe` for all `artisan`/test commands.

---

### Task 1: Schema, Strong Password Rule, `spmb.register` Route

**Files:**
- Create: `database/migrations/2026_07_21_000000_add_no_hp_wa_to_akun_pendaftar_table.php`
- Modify: `app/Models/AkunPendaftar.php`
- Modify: `database/factories/AkunPendaftarFactory.php`
- Modify: `app/Http/Controllers/Portal/Auth/RegisteredAkunController.php`
- Modify: `app/Http/Controllers/Portal/Auth/NewPasswordController.php`
- Modify: `routes/spmb.php`
- Modify: `tests/Feature/Portal/RegistrasiAkunTest.php`
- Test: `tests/Feature/Spmb/RegisterRouteTest.php`

**Interfaces:**
- Consumes: existing `AkunPendaftar` model, `RegisteredAkunController::{create,store}`, `routes/portal.php`'s existing `guest:portal` middleware group pattern.
- Produces: `akun_pendaftar.no_hp_wa` column (nullable at DB level), `Password::min(8)->letters()->mixedCase()->numbers()` applied in both `RegisteredAkunController::store()` and `NewPasswordController::store()`, route `spmb.register` (same controller actions as `portal.register`). Task 2 will add the `no_hp_wa` *validation* (required) and view field — this task only adds the column and leaves the field optional at the validation layer, so the still-unredesigned view from before Task 2 keeps working without submitting a field it doesn't have yet.

- [ ] **Step 1: Write the failing test for the new route**

```php
<?php
// tests/Feature/Spmb/RegisterRouteTest.php

it('exposes a spmb.register route pointing at the same controller as portal.register', function () {
    expect(Route::has('spmb.register'))->toBeTrue();

    $response = $this->get(route('spmb.register'));

    $response->assertOk();
    $response->assertViewIs('portal.auth.register');
});

it('redirects the SPMB daftar-jalur action to spmb.register now that the route exists', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();

    $response = $this->post(route('spmb.jalur.daftar', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]));

    $response->assertRedirect(route('spmb.register'));
    $response->assertSessionHas('spmb_pilihan.lembaga_id', $lembaga->id);
    $response->assertSessionHas('spmb_pilihan.jalur_id', $jalur->id);
});
```

Add `use Illuminate\Support\Facades\Route;` at the top of the file if not already present via Pest's global imports (check `tests/Pest.php` for existing global `use` statements before adding — if `Route` isn't globally available, add the `use` statement inside this test file).

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Spmb/RegisterRouteTest.php`
Expected: FAIL — `Route::has('spmb.register')` is `false`, both assertions fail (first test: route doesn't exist so `route('spmb.register')` throws; second test: still redirects to `spmb.index` with the "coming soon" flash instead).

- [ ] **Step 3: Write the migration**

```php
<?php
// database/migrations/2026_07_21_000000_add_no_hp_wa_to_akun_pendaftar_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('akun_pendaftar', function (Blueprint $table) {
            $table->string('no_hp_wa')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('akun_pendaftar', function (Blueprint $table) {
            $table->dropColumn('no_hp_wa');
        });
    }
};
```

- [ ] **Step 4: Update the model and factory**

In `app/Models/AkunPendaftar.php`, add `'no_hp_wa'` to `$fillable`:

```php
    protected $fillable = [
        'nama',
        'email',
        'no_hp_wa',
        'password',
        'email_verified_at',
    ];
```

In `database/factories/AkunPendaftarFactory.php`, add a `no_hp_wa` value to `definition()`:

```php
    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'no_hp_wa' => fake()->numerify('08##########'),
            'email_verified_at' => now(),
            'password' => 'password',
            'remember_token' => Str::random(10),
        ];
    }
```

- [ ] **Step 5: Apply the strong-password rule**

In `app/Http/Controllers/Portal/Auth/RegisteredAkunController.php`, replace the password rule:

```php
    public function store(\Illuminate\Http\Request $request, OtpService $otpService): RedirectResponse
    {
        $data = Validator::make($request->all(), [
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:akun_pendaftar,email'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)->letters()->mixedCase()->numbers()],
        ])->validate();
```

In `app/Http/Controllers/Portal/Auth/NewPasswordController.php`, replace the password rule the same way:

```php
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::min(8)->letters()->mixedCase()->numbers()],
        ]);
```

- [ ] **Step 6: Add the `spmb.register` route**

In `routes/spmb.php`, add to the `use` statements at the top:

```php
use App\Http\Controllers\Portal\Auth\RegisteredAkunController;
```

Add inside the `Route::prefix('spmb')->name('spmb.')->group(function () { ... })` closure, after the `welcome` route:

```php
    Route::middleware('guest:portal')->group(function () {
        Route::get('register', [RegisteredAkunController::class, 'create'])->name('register');
        Route::post('register', [RegisteredAkunController::class, 'store'])->middleware('throttle:6,1');
    });
```

- [ ] **Step 7: Update the existing registration test for the new password rule**

The two `it(...)` blocks in `tests/Feature/Portal/RegistrasiAkunTest.php` that POST to `route('portal.register')` use `'password123'` (all lowercase — no longer valid under the new `mixedCase()` requirement). Update both occurrences:

```php
it('registers a new unverified akun and sends an otp email', function () {
    Mail::fake();

    $response = $this->post(route('portal.register'), [
        'nama' => 'Ahmad Fauzan',
        'email' => 'ahmad@example.test',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
    ]);

    $response->assertRedirect(route('portal.verifikasi-otp'));
    $akun = AkunPendaftar::where('email', 'ahmad@example.test')->first();
    expect($akun)->not->toBeNull();
    expect($akun->email_verified_at)->toBeNull();
    Mail::assertSent(KodeOtpMail::class);
});

it('rejects registration with a duplicate email', function () {
    AkunPendaftar::factory()->create(['email' => 'sudah@example.test']);

    $response = $this->post(route('portal.register'), [
        'nama' => 'Duplikat',
        'email' => 'sudah@example.test',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
    ]);

    $response->assertSessionHasErrors('email');
});
```

Add one new test to the same file proving the weak-password case is now rejected:

```php
it('rejects a password without mixed case even if it is long enough', function () {
    $response = $this->post(route('portal.register'), [
        'nama' => 'Lemah',
        'email' => 'lemah@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertSessionHasErrors('password');
});
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Spmb/RegisterRouteTest.php tests/Feature/Portal/RegistrasiAkunTest.php`
Expected: PASS (7/7 — 2 new in `RegisterRouteTest`, 5 in `RegistrasiAkunTest` including the new weak-password test)

- [ ] **Step 9: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all tests pass. The migration must run cleanly against the test DB (Laravel runs migrations automatically for `RefreshDatabase`-using tests) — if any pre-existing test seeds `akun_pendaftar` rows without going through the factory and breaks, investigate before proceeding (should not happen, since the new column is nullable).

- [ ] **Step 10: Commit**

```bash
git add database/migrations/2026_07_21_000000_add_no_hp_wa_to_akun_pendaftar_table.php app/Models/AkunPendaftar.php database/factories/AkunPendaftarFactory.php app/Http/Controllers/Portal/Auth/RegisteredAkunController.php app/Http/Controllers/Portal/Auth/NewPasswordController.php routes/spmb.php tests/Feature/Portal/RegistrasiAkunTest.php tests/Feature/Spmb/RegisterRouteTest.php
git commit -m "feat: add no_hp_wa column, strong password rule, and spmb.register route"
```

---

### Task 2: Register Page — Navy Redesign, Context Chip, Jalur Sidebar

**Files:**
- Modify: `resources/views/components/icon.blade.php` (add 4 new icon cases)
- Create: `resources/views/components/portal-auth-top.blade.php`
- Create: `resources/views/components/layouts/portal-auth.blade.php`
- Create: `resources/js/password-strength.js`
- Modify: `resources/js/app.js`
- Modify: `app/Http/Controllers/Portal/Auth/RegisteredAkunController.php`
- Modify: `resources/views/portal/auth/register.blade.php`
- Modify: `routes/spmb.php`
- Modify: `tests/Feature/Portal/RegistrasiAkunTest.php`
- Test: `tests/Feature/Portal/RegisterContextTest.php`

**Interfaces:**
- Consumes: `<x-portal-footer>`, `<x-icon>` (Task 1's icons plus this task's 4 new ones), `session('spmb_pilihan.lembaga_id')`/`session('spmb_pilihan.jalur_id')` (written by Sub-project 1's `PortalController::daftarJalur()`), `App\Models\{Lembaga,JalurPpdb,JenisTagihan,NominalTagihanJalur,TahunAjaran}`.
- Produces: `<x-portal-auth-top>` and `<x-layouts.portal-auth>` components (Task 3 and Task 4 reuse both, unchanged), `resources/js/password-strength.js`'s `passwordStrength()` Alpine component (Task 4's reset-password page reuses it), route `spmb.register.ganti-jalur` (`POST`).

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Portal/RegisterContextTest.php

use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

it('shows no context chip or sidebar when no jalur is selected in session', function () {
    $this->get(route('portal.register'))
        ->assertOk()
        ->assertDontSee('Mendaftar untuk')
        ->assertDontSee('Jalur Lain yang Tersedia');
});

it('shows the context chip and sidebar when a jalur is selected in session', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    $jalurLain = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi', 'status_aktif' => true]);

    $this->withSession(['spmb_pilihan.lembaga_id' => $lembaga->id, 'spmb_pilihan.jalur_id' => $jalur->id])
        ->get(route('portal.register'))
        ->assertOk()
        ->assertSee('Mendaftar untuk')
        ->assertSee($lembaga->nama)
        ->assertSee($jalur->nama)
        ->assertSee('Jalur Lain yang Tersedia')
        ->assertSee($jalurLain->nama);
});

it('excludes the currently selected jalur from the sidebar list', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();

    $response = $this->withSession(['spmb_pilihan.lembaga_id' => $lembaga->id, 'spmb_pilihan.jalur_id' => $jalur->id])
        ->get(route('portal.register'));

    $response->assertOk();
    $response->assertSee('Dipilih');
});

it('shows the three biaya pendaftaran states in the sidebar', function () {
    [$lembaga, $tahunAjaran, $jalurTerpilih] = buatLembagaDenganGelombangBuka();
    $jalurGratis = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Afirmasi', 'status_aktif' => true]);
    $jalurBelumDikonfigurasi = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi', 'status_aktif' => true]);

    $jenisPendaftaran = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisPendaftaran->id, 'jalur_ppdb_id' => $jalurGratis->id, 'nominal' => 0]);

    $response = $this->withSession(['spmb_pilihan.lembaga_id' => $lembaga->id, 'spmb_pilihan.jalur_id' => $jalurTerpilih->id])
        ->get(route('portal.register'));

    $response->assertOk();
    $response->assertSee('Gratis');
    $response->assertSee('Menunggu Konfirmasi');
});

it('switches the selected jalur in session and redirects back to register', function () {
    [$lembaga, $tahunAjaran, $jalurAwal] = buatLembagaDenganGelombangBuka();
    $jalurBaru = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Prestasi', 'status_aktif' => true]);

    $response = $this->withSession(['spmb_pilihan.lembaga_id' => $lembaga->id, 'spmb_pilihan.jalur_id' => $jalurAwal->id])
        ->post(route('spmb.register.ganti-jalur', ['jalur' => $jalurBaru->id]));

    $response->assertRedirect(route('spmb.register'));
    $response->assertSessionHas('spmb_pilihan.jalur_id', $jalurBaru->id);
    $response->assertSessionHas('spmb_pilihan.lembaga_id', $lembaga->id);
});

it('404s ganti-jalur when the jalur does not belong to the lembaga in session', function () {
    [$lembagaSatu] = buatLembagaDenganGelombangBuka();
    [, , $jalurLembagaDua] = buatLembagaDenganGelombangBuka();

    $response = $this->withSession(['spmb_pilihan.lembaga_id' => $lembagaSatu->id, 'spmb_pilihan.jalur_id' => 1])
        ->post(route('spmb.register.ganti-jalur', ['jalur' => $jalurLembagaDua->id]));

    $response->assertNotFound();
});

it('requires no_hp_wa and terms acceptance on registration', function () {
    $response = $this->post(route('portal.register'), [
        'nama' => 'Tanpa Data',
        'email' => 'tanpadata@example.test',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
    ]);

    $response->assertSessionHasErrors(['no_hp_wa', 'terms']);
});

it('saves no_hp_wa when registering with full data', function () {
    $response = $this->post(route('portal.register'), [
        'nama' => 'Lengkap',
        'email' => 'lengkap@example.test',
        'no_hp_wa' => '081234567890',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'terms' => '1',
    ]);

    $response->assertRedirect(route('portal.verifikasi-otp'));
    expect(\App\Models\AkunPendaftar::where('email', 'lengkap@example.test')->first()->no_hp_wa)->toBe('081234567890');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Portal/RegisterContextTest.php`
Expected: FAIL — route `spmb.register.ganti-jalur` doesn't exist, view doesn't show chip/sidebar/`no_hp_wa`/`terms` validation, `no_hp_wa` isn't saved.

- [ ] **Step 3: Add the 4 new icons**

In `resources/views/components/icon.blade.php`, add before the closing `@endswitch` (after the `schedule` case added in Sub-project 1):

```blade
    @case('person')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg>
        @break

    @case('mail')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
        @break

    @case('phone')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.99.36 1.96.68 2.9a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.18-1.25a2 2 0 0 1 2.11-.45c.94.32 1.91.55 2.9.68A2 2 0 0 1 22 16.92z"/></svg>
        @break

    @case('lock')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
        @break
```

- [ ] **Step 4: Write `portal-auth-top` component**

```php
<?php
// resources/views/components/portal-auth-top.blade.php
```

```blade
@props(['linkLabel', 'linkText', 'linkRoute'])

<div class="border-b border-gray-200 bg-white px-4 py-4 sm:px-6 lg:px-10">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2.5">
        <a href="{{ route('spmb.welcome') }}" class="flex items-center gap-2.5">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-portal-500 to-portal-600 text-white">
                <x-icon name="school" class="h-5 w-5" />
            </span>
            <span class="leading-tight">
                <span class="block text-[15px] font-bold text-gray-900">Pintera</span>
                <span class="block text-[11px] font-semibold uppercase tracking-wide text-gray-400">Portal Calon Siswa</span>
            </span>
        </a>
        <div class="text-[13px] text-gray-500">
            {{ $linkLabel }} <a href="{{ route($linkRoute) }}" class="font-bold text-portal-500">{{ $linkText }}</a>
        </div>
    </div>
</div>
```

- [ ] **Step 5: Write `layouts.portal-auth`**

```php
<?php
// resources/views/components/layouts/portal-auth.blade.php
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
        <x-portal-auth-top :link-label="$linkLabel" :link-text="$linkText" :link-route="$linkRoute" />
        <main>
            @if (session('status'))
                <div class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-10">
                    <div class="rounded-xl bg-success-50 px-4 py-3 text-[13px] font-semibold text-success-700">
                        {{ session('status') }}
                    </div>
                </div>
            @endif
            {{ $slot }}
        </main>
        <x-portal-footer :yayasan="$yayasan ?? null" />
    </body>
</html>
```

- [ ] **Step 6: Write the password-strength Alpine component**

```js
// resources/js/password-strength.js

export function passwordStrength() {
    return {
        value: '',

        get score() {
            let s = 0;
            if (this.value.length >= 8) s++;
            if (/[a-z]/.test(this.value) && /[A-Z]/.test(this.value)) s++;
            if (/[0-9]/.test(this.value)) s++;
            return s;
        },

        get tier() {
            if (!this.value) return 'empty';
            if (this.score >= 3) return 'strong';
            if (this.score >= 1) return 'mid';
            return 'weak';
        },
    };
}
```

In `resources/js/app.js`, add the import near the other component imports:

```js
import { passwordStrength } from './password-strength';
```

And register it near the other `Alpine.data(...)` calls:

```js
Alpine.data('passwordStrength', passwordStrength);
```

- [ ] **Step 7: Modify `RegisteredAkunController`**

```php
<?php
// app/Http/Controllers/Portal/Auth/RegisteredAkunController.php

namespace App\Http\Controllers\Portal\Auth;

use App\Models\AkunPendaftar;
use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use App\Models\TahunAjaran;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class RegisteredAkunController extends BaseController
{
    public function create(): View
    {
        $lembagaId = session('spmb_pilihan.lembaga_id');
        $jalurId = session('spmb_pilihan.jalur_id');

        $lembaga = null;
        $jalurTerpilih = null;
        $jalurLain = collect();

        if ($lembagaId && $jalurId) {
            $lembaga = Lembaga::find($lembagaId);
            $jalurTerpilih = JalurPpdb::find($jalurId);

            if ($lembaga && $jalurTerpilih) {
                $tahunAjaranAktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();
                $jenisPendaftaran = JenisTagihan::where('lembaga_id', $lembaga->id)->where('kategori', 'pendaftaran')->first();

                $jalurLain = $tahunAjaranAktif
                    ? JalurPpdb::where('lembaga_id', $lembaga->id)
                        ->where('tahun_ajaran_id', $tahunAjaranAktif->id)
                        ->where('status_aktif', true)
                        ->orderBy('id')
                        ->get()
                        ->map(function (JalurPpdb $jalur) use ($jalurTerpilih, $jenisPendaftaran) {
                            $nominal = $jenisPendaftaran
                                ? NominalTagihanJalur::where('jenis_tagihan_id', $jenisPendaftaran->id)->where('jalur_ppdb_id', $jalur->id)->first()
                                : null;

                            return [
                                'jalur' => $jalur,
                                'selected' => $jalur->id === $jalurTerpilih->id,
                                'nominal' => $nominal,
                            ];
                        })
                    : collect();
            } else {
                $lembaga = null;
                $jalurTerpilih = null;
            }
        }

        return view('portal.auth.register', [
            'lembaga' => $lembaga,
            'jalurTerpilih' => $jalurTerpilih,
            'jalurLain' => $jalurLain,
        ]);
    }

    public function store(Request $request, OtpService $otpService): RedirectResponse
    {
        $data = Validator::make($request->all(), [
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:akun_pendaftar,email'],
            'no_hp_wa' => ['required', 'string', 'max:20'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)->letters()->mixedCase()->numbers()],
            'terms' => ['required', 'accepted'],
        ])->validate();

        AkunPendaftar::create([
            'nama' => $data['nama'],
            'email' => $data['email'],
            'no_hp_wa' => $data['no_hp_wa'],
            'password' => $data['password'],
        ]);

        $otpService->kirim($data['email']);

        session(['portal_register_email_pending' => $data['email']]);

        return redirect()->route('portal.verifikasi-otp');
    }

    public function gantiJalur(Request $request, JalurPpdb $jalur): RedirectResponse
    {
        abort_unless((int) $jalur->lembaga_id === (int) session('spmb_pilihan.lembaga_id'), 404);

        $request->session()->put('spmb_pilihan.jalur_id', $jalur->id);

        return redirect()->route('spmb.register');
    }
}
```

- [ ] **Step 8: Add the `spmb.register.ganti-jalur` route**

In `routes/spmb.php`, inside the same `Route::middleware('guest:portal')->group(...)` block added in Task 1, right after the `register` POST line:

```php
        Route::post('register/ganti-jalur/{jalur}', [RegisteredAkunController::class, 'gantiJalur'])->name('register.ganti-jalur');
```

- [ ] **Step 9: Rewrite the register view**

```php
<?php
// resources/views/portal/auth/register.blade.php
```

```blade
<x-layouts.portal-auth
    title="Daftar Akun"
    link-label="Sudah punya akun?"
    link-text="Masuk"
    link-route="portal.login"
>
    <div class="mx-auto grid max-w-7xl gap-7 px-4 py-8 sm:px-6 min-[861px]:grid-cols-[1.15fr_0.85fr] min-[861px]:items-start lg:px-10 lg:py-12">
        <div class="mx-auto w-full max-w-[480px] rounded-[20px] border border-gray-200 bg-white p-8 shadow-[0_20px_44px_rgba(30,58,95,0.10)]">
            <div class="mb-[22px] text-center">
                <h1 class="text-[21px] font-bold text-gray-900">Buat Akun Pendaftar</h1>
                <p class="mt-1.5 text-[12.5px] text-gray-500">Satu akun untuk mendaftar dan memantau seluruh proses seleksi.</p>
            </div>

            @if ($lembaga && $jalurTerpilih)
                <div class="mb-[22px] flex items-center gap-2.5 rounded-xl bg-portal-50 px-3.5 py-3">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-portal-500 text-white">
                        <x-icon name="school" class="h-4 w-4" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[10px] font-bold uppercase tracking-wide text-portal-500/75">Mendaftar untuk</span>
                        <span class="block truncate text-[12.5px] font-bold text-portal-500">{{ $lembaga->nama }} — Jalur {{ $jalurTerpilih->nama }}</span>
                    </span>
                    <a href="{{ route('spmb.index', ['lembagaSlug' => $lembaga->slug]) }}" class="shrink-0 text-[11.5px] font-bold text-portal-500 underline">Ganti</a>
                </div>
            @endif

            <form method="POST" action="{{ route('portal.register') }}">
                @csrf
                <div class="mb-4">
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Nama Lengkap</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-[13px] top-1/2 -translate-y-1/2 text-gray-400">
                            <x-icon name="person" class="h-[15px] w-[15px]" />
                        </span>
                        <input type="text" name="nama" value="{{ old('nama') }}" placeholder="Sesuai nama di KTP/KK orang tua" required autofocus
                            class="w-full rounded-[10px] border border-gray-200 py-[11px] pl-[38px] pr-3.5 text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    </div>
                    @error('nama') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>

                <div class="mb-4 grid grid-cols-2 gap-3.5 max-[480px]:grid-cols-1">
                    <div>
                        <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Alamat Email</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-[13px] top-1/2 -translate-y-1/2 text-gray-400">
                                <x-icon name="mail" class="h-[15px] w-[15px]" />
                            </span>
                            <input type="email" name="email" value="{{ old('email') }}" placeholder="nama@email.com" required
                                class="w-full rounded-[10px] border border-gray-200 py-[11px] pl-[38px] pr-3.5 text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                        </div>
                        @error('email') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">No. HP/WhatsApp</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-[13px] top-1/2 -translate-y-1/2 text-gray-400">
                                <x-icon name="phone" class="h-[15px] w-[15px]" />
                            </span>
                            <input type="text" name="no_hp_wa" value="{{ old('no_hp_wa') }}" placeholder="08xx-xxxx-xxxx" required
                                class="w-full rounded-[10px] border border-gray-200 py-[11px] pl-[38px] pr-3.5 text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                        </div>
                        @error('no_hp_wa') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mb-4" x-data="passwordStrength()">
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Kata Sandi</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-[13px] top-1/2 -translate-y-1/2 text-gray-400">
                            <x-icon name="lock" class="h-[15px] w-[15px]" />
                        </span>
                        <input type="password" name="password" placeholder="Minimal 8 karakter" required
                            x-model="value"
                            class="w-full rounded-[10px] border border-gray-200 py-[11px] pl-[38px] pr-3.5 text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    </div>
                    <div class="mt-2 flex gap-1" x-show="value.length > 0" x-cloak>
                        <template x-for="n in 4" :key="n">
                            <i class="h-1 flex-1 rounded-full" :class="{
                                'bg-success-500': tier === 'strong',
                                'bg-warning-500': tier === 'mid' && n <= 2,
                                'bg-gray-200': tier === 'weak' || (tier === 'mid' && n > 2),
                            }"></i>
                        </template>
                    </div>
                    <p class="mt-1.5 text-[11px] font-semibold" x-show="value.length > 0" x-cloak
                        x-text="tier === 'strong' ? 'Kuat — sudah memenuhi huruf besar, kecil, dan angka' : (tier === 'mid' ? 'Sedang — tambahkan huruf besar/kecil dan angka' : 'Lemah — minimal 8 karakter')"
                        :class="tier === 'strong' ? 'text-success-700' : (tier === 'mid' ? 'text-warning-700' : 'text-gray-400')"></p>
                    @error('password') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>

                <div class="mb-4">
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Konfirmasi Kata Sandi</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-[13px] top-1/2 -translate-y-1/2 text-gray-400">
                            <x-icon name="lock" class="h-[15px] w-[15px]" />
                        </span>
                        <input type="password" name="password_confirmation" placeholder="Ulangi kata sandi" required
                            class="w-full rounded-[10px] border border-gray-200 py-[11px] pl-[38px] pr-3.5 text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    </div>
                </div>

                <div class="mb-[22px] mt-[18px] flex items-start gap-[9px] text-[12px] leading-[1.5] text-gray-500">
                    <input type="checkbox" name="terms" value="1" required class="mt-[3px] h-[17px] w-[17px] shrink-0 rounded-[5px] border-gray-300 text-portal-500 focus:ring-portal-500/20">
                    <span>Saya menyetujui <a href="#" class="font-semibold text-portal-500">Syarat &amp; Ketentuan</a> serta <a href="#" class="font-semibold text-portal-500">Kebijakan Privasi</a> Pintera.</span>
                </div>
                @error('terms') <p class="-mt-4 mb-4 text-[11px] text-error-700">{{ $message }}</p> @enderror

                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-[10px] bg-portal-500 py-[13px] text-[13.5px] font-bold text-white transition hover:bg-portal-600">
                    Buat Akun
                    <x-icon name="arrow_forward" class="h-3.5 w-3.5" />
                </button>
            </form>

            <p class="mt-[18px] text-center text-[12.5px] text-gray-500">Sudah punya akun? <a href="{{ route('portal.login') }}" class="font-bold text-portal-500">Masuk di sini</a></p>
        </div>

        @if ($lembaga && $jalurTerpilih && $jalurLain->isNotEmpty())
            <aside class="mx-auto w-full max-w-[480px] min-[861px]:sticky min-[861px]:top-[90px] min-[861px]:max-w-[380px]">
                <div class="mb-3.5">
                    <p class="mb-1 text-[11px] font-bold uppercase tracking-wide text-portal-500">{{ $lembaga->nama }}</p>
                    <h3 class="text-[15.5px] font-bold text-gray-900">Jalur Lain yang Tersedia</h3>
                    <p class="mt-1 text-[12px] text-gray-500">Belum yakin dengan Jalur {{ $jalurTerpilih->nama }}? Bandingkan dulu dengan jalur lain di lembaga ini.</p>
                </div>

                @foreach ($jalurLain as $item)
                    @php $jalur = $item['jalur']; $nominal = $item['nominal']; @endphp
                    <div class="mb-3 rounded-[14px] border-[1.5px] p-4 {{ $item['selected'] ? 'border-portal-500 bg-portal-50' : 'border-gray-200 bg-white' }}">
                        <div class="mb-1.5 flex items-center justify-between">
                            <h4 class="text-[14px] font-bold text-gray-900">{{ $jalur->nama }}</h4>
                            @if ($item['selected'])
                                <span class="flex items-center gap-1 text-[10.5px] font-bold text-portal-500">
                                    <x-icon name="check_circle" class="h-3 w-3" /> Dipilih
                                </span>
                            @endif
                        </div>
                        @if ($jalur->deskripsi)
                            <p class="mb-3 text-[11.5px] leading-[1.5] text-gray-500">{{ $jalur->deskripsi }}</p>
                        @endif
                        <div class="flex items-center justify-between">
                            @if ($nominal === null)
                                <span class="text-[10.5px] font-semibold text-warning-700">Menunggu Konfirmasi</span>
                            @elseif ((float) $nominal->nominal === 0.0)
                                <span class="text-[12.5px] font-bold text-success-700">Gratis</span>
                            @else
                                <span class="text-[12.5px] font-bold text-portal-500">Rp{{ number_format($nominal->nominal, 0, ',', '.') }}</span>
                            @endif
                            @unless ($item['selected'])
                                <form method="POST" action="{{ route('spmb.register.ganti-jalur', ['jalur' => $jalur->id]) }}">
                                    @csrf
                                    <button type="submit" class="text-[11.5px] font-bold text-portal-500 underline">Pilih Jalur Ini</button>
                                </form>
                            @endunless
                        </div>
                    </div>
                @endforeach
            </aside>
        @endif
    </div>
</x-layouts.portal-auth>
```

- [ ] **Step 10: Run tests to verify they pass**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Portal/RegisterContextTest.php tests/Feature/Portal/RegistrasiAkunTest.php`
Expected: PASS (13/13 — 7 new in `RegisterContextTest`, 6 in `RegistrasiAkunTest`)

- [ ] **Step 11: Rebuild assets and run the full suite**

Run: `npm run build`
Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all tests pass, output pristine.

- [ ] **Step 12: Commit**

```bash
git add resources/views/components/icon.blade.php resources/views/components/portal-auth-top.blade.php resources/views/components/layouts/portal-auth.blade.php resources/js/password-strength.js resources/js/app.js app/Http/Controllers/Portal/Auth/RegisteredAkunController.php resources/views/portal/auth/register.blade.php routes/spmb.php tests/Feature/Portal/RegistrasiAkunTest.php tests/Feature/Portal/RegisterContextTest.php
git commit -m "feat: redesign register page to navy portal shell, add lembaga/jalur context and jalur-comparison sidebar"
```

---

### Task 3: Verifikasi OTP — Navy Redesign + Resend Action

**Files:**
- Modify: `app/Http/Controllers/Portal/Auth/VerifikasiOtpController.php`
- Modify: `resources/views/portal/auth/verifikasi-otp.blade.php`
- Modify: `routes/portal.php`
- Test: `tests/Feature/Portal/ResendOtpTest.php`

**Interfaces:**
- Consumes: `App\Services\OtpService::kirim()` (unchanged), `App\Models\VerifikasiEmailOtp` (unchanged), `resources/js/otp-input.js`'s `otpInput()` (unchanged), `<x-portal-auth-top>`/`<x-layouts.portal-auth>` (Task 2, unchanged).
- Produces: route `portal.verifikasi-otp.kirim-ulang` (`POST`).

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Portal/ResendOtpTest.php

use App\Models\AkunPendaftar;
use App\Models\VerifikasiEmailOtp;

it('sends a new otp and deletes the old unverified one when resend is requested', function () {
    AkunPendaftar::factory()->unverified()->create(['email' => 'ahmad@example.test']);
    app(\App\Services\OtpService::class)->kirim('ahmad@example.test');
    $kodeLama = VerifikasiEmailOtp::where('email', 'ahmad@example.test')->latest('id')->first()->kode_otp;

    $response = $this->withSession(['portal_register_email_pending' => 'ahmad@example.test'])
        ->post(route('portal.verifikasi-otp.kirim-ulang'));

    $response->assertRedirect(route('portal.verifikasi-otp'));
    $otpBaru = VerifikasiEmailOtp::where('email', 'ahmad@example.test')->latest('id')->first();
    expect($otpBaru->kode_otp)->not->toBe($kodeLama);
    expect(VerifikasiEmailOtp::where('email', 'ahmad@example.test')->whereNull('verified_at')->count())->toBe(1);
});

it('does nothing and redirects if there is no pending email in session', function () {
    $response = $this->post(route('portal.verifikasi-otp.kirim-ulang'));

    $response->assertRedirect(route('portal.verifikasi-otp'));
    expect(VerifikasiEmailOtp::count())->toBe(0);
});

it('shows a countdown-driven resend affordance on the otp page', function () {
    AkunPendaftar::factory()->unverified()->create(['email' => 'ahmad@example.test']);
    app(\App\Services\OtpService::class)->kirim('ahmad@example.test');

    $response = $this->withSession(['portal_register_email_pending' => 'ahmad@example.test'])
        ->get(route('portal.verifikasi-otp'));

    $response->assertOk();
    $response->assertSee('ahmad@example.test');
    $response->assertSee('kirim-ulang', false);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Portal/ResendOtpTest.php`
Expected: FAIL — route `portal.verifikasi-otp.kirim-ulang` doesn't exist.

- [ ] **Step 3: Add `kirimUlang()` to `VerifikasiOtpController`**

```php
<?php
// app/Http/Controllers/Portal/Auth/VerifikasiOtpController.php

namespace App\Http\Controllers\Portal\Auth;

use App\Models\AkunPendaftar;
use App\Models\Pendaftaran;
use App\Models\VerifikasiEmailOtp;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class VerifikasiOtpController extends BaseController
{
    public function create(): View
    {
        $email = session('portal_register_email_pending');

        $detikTersisa = 0;
        if ($email) {
            $otpTerakhir = VerifikasiEmailOtp::where('email', $email)->whereNull('verified_at')->latest('id')->first();

            if ($otpTerakhir) {
                $detikTersisa = max(0, 60 - now()->diffInSeconds($otpTerakhir->created_at));
            }
        }

        return view('portal.auth.verifikasi-otp', [
            'email' => $email,
            'detikTersisa' => $detikTersisa,
        ]);
    }

    public function store(Request $request, OtpService $otpService): RedirectResponse
    {
        $data = $request->validate([
            'kode_otp' => ['required', 'string'],
        ]);

        $email = session('portal_register_email_pending');

        if (! $email || ! $otpService->verifikasi($email, $data['kode_otp'])) {
            return back()->withErrors(['kode_otp' => 'Kode salah, kedaluwarsa, atau sudah dipakai.']);
        }

        $akun = AkunPendaftar::where('email', $email)->firstOrFail();
        $akun->forceFill(['email_verified_at' => now()])->save();

        Pendaftaran::where('email_pendaftaran', $email)
            ->whereNull('akun_pendaftar_id')
            ->update(['akun_pendaftar_id' => $akun->id]);

        Auth::guard('portal')->login($akun);
        session()->forget('portal_register_email_pending');

        return redirect()->route('portal.dashboard');
    }

    public function kirimUlang(OtpService $otpService): RedirectResponse
    {
        $email = session('portal_register_email_pending');

        if ($email) {
            $otpService->kirim($email);
        }

        return redirect()->route('portal.verifikasi-otp')->with('status', 'Kode verifikasi baru sudah dikirim.');
    }
}
```

- [ ] **Step 4: Add the resend route**

In `routes/portal.php`, add right after the existing `verifikasi-otp.store` route:

```php
    Route::post('verifikasi-otp/kirim-ulang', [VerifikasiOtpController::class, 'kirimUlang'])
        ->middleware('throttle:3,1')->name('verifikasi-otp.kirim-ulang');
```

- [ ] **Step 5: Rewrite the verifikasi-otp view**

```php
<?php
// resources/views/portal/auth/verifikasi-otp.blade.php
```

```blade
<x-layouts.portal-auth
    title="Verifikasi Email"
    link-label="Salah data?"
    link-text="Kembali ke form daftar"
    link-route="portal.register"
>
    <div class="mx-auto flex min-h-[460px] max-w-7xl items-center justify-center px-4 py-10 sm:px-6 lg:px-10">
        <div
            class="w-full max-w-[420px] rounded-[20px] border border-gray-200 bg-white p-8 shadow-[0_20px_44px_rgba(30,58,95,0.10)]"
            x-data="{ sisa: {{ $detikTersisa }} }"
            x-init="if (sisa > 0) { const t = setInterval(() => { sisa--; if (sisa <= 0) clearInterval(t); }, 1000); }"
        >
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-portal-50 text-portal-500">
                <x-icon name="mail" class="h-6 w-6" />
            </div>
            <div class="mb-6 text-center">
                <h1 class="text-[21px] font-bold text-gray-900">Verifikasi Email Kamu</h1>
                <p class="mt-1.5 text-[12.5px] text-gray-500">Kami mengirim kode 6 digit ke <span class="font-bold text-gray-900">{{ $email }}</span></p>
            </div>

            <form
                method="POST"
                action="{{ route('portal.verifikasi-otp.store') }}"
                x-data="otpInput()"
                @submit="$refs.kodeTersembunyi.value = kode"
            >
                @csrf
                <div class="mb-2 flex justify-center gap-2 max-[380px]:gap-1.5">
                    @for ($i = 0; $i < 6; $i++)
                        <input
                            type="text"
                            inputmode="numeric"
                            maxlength="1"
                            x-ref="kotak{{ $i }}"
                            :value="digit[{{ $i }}]"
                            @input="isiKotak({{ $i }}, $event)"
                            @keydown="tekanBackspace({{ $i }}, $event)"
                            @paste.prevent="tempel($event)"
                            class="h-[54px] w-[46px] rounded-[11px] border-[1.5px] border-gray-200 text-center text-[20px] font-bold tabular-nums text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20 max-[380px]:h-[48px] max-[380px]:w-[38px] max-[380px]:text-[17px]"
                            @if ($i === 0) autofocus @endif
                        >
                    @endfor
                </div>
                <input type="hidden" name="kode_otp" x-ref="kodeTersembunyi">
                @error('kode_otp') <p class="mb-2 text-center text-[11px] text-error-700">{{ $message }}</p> @enderror

                <p class="mb-[22px] mt-1 text-center text-[12.5px] text-gray-400">
                    <span x-show="sisa > 0" x-cloak>Belum menerima kode? Kirim ulang dalam <b class="text-gray-600 tabular-nums" x-text="String(Math.floor(sisa / 60)).padStart(2, '0') + ':' + String(sisa % 60).padStart(2, '0')"></b></span>
                    <span x-show="sisa <= 0" x-cloak>
                        Belum menerima kode?
                        <button type="submit" form="form-kirim-ulang" class="font-bold text-portal-500 underline">Kirim ulang kode</button>
                    </span>
                </p>

                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-[10px] bg-portal-500 py-[13px] text-[13.5px] font-bold text-white transition hover:bg-portal-600">
                    Verifikasi &amp; Masuk
                    <x-icon name="arrow_forward" class="h-3.5 w-3.5" />
                </button>
            </form>

            <form id="form-kirim-ulang" method="POST" action="{{ route('portal.verifikasi-otp.kirim-ulang') }}">
                @csrf
            </form>

            <p class="mt-[18px] text-center text-[12.5px] text-gray-500">Salah alamat email? <a href="{{ route('portal.register') }}" class="font-bold text-portal-500">Ubah di sini</a></p>
        </div>
    </div>
</x-layouts.portal-auth>
```

Note: the resend button lives inside the OTP-digit `<form>` (for placement/styling next to the countdown text) but submits a *different* form (`form-kirim-ulang`) via the HTML5 `form="..."` attribute — this avoids nesting one `<form>` inside another (invalid HTML) while keeping the resend action visually inline with the countdown text.

- [ ] **Step 6: Run tests to verify they pass**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Portal/ResendOtpTest.php tests/Feature/Portal/RegistrasiAkunTest.php`
Expected: PASS (9/9 — 3 new in `ResendOtpTest`, 6 in `RegistrasiAkunTest`, confirming the OTP verify flow itself still works after the view rewrite)

- [ ] **Step 7: Rebuild assets and run the full suite**

Run: `npm run build`
Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all tests pass, output pristine.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Portal/Auth/VerifikasiOtpController.php resources/views/portal/auth/verifikasi-otp.blade.php routes/portal.php tests/Feature/Portal/ResendOtpTest.php
git commit -m "feat: redesign verifikasi OTP page to navy portal shell, add explicit resend action"
```

---

### Task 4: Masuk, Lupa Password, Reset Password — Navy Redesign

**Files:**
- Modify: `resources/views/portal/auth/login.blade.php`
- Modify: `resources/views/portal/auth/forgot-password.blade.php`
- Modify: `resources/views/portal/auth/reset-password.blade.php`
- Modify: `tests/Feature/Portal/PortalAuthPagesRenderTest.php`
- Test: `tests/Feature/Portal/ResetPasswordRenderTest.php`

**Interfaces:**
- Consumes: `<x-portal-auth-top>`/`<x-layouts.portal-auth>` (Task 2, unchanged), `resources/js/password-strength.js`'s `passwordStrength()` (Task 2, unchanged), existing `AuthenticatedSessionController`/`PasswordResetLinkController`/`NewPasswordController` (unchanged in this task — only their views change).
- Produces: nothing new consumed by later work — this is the last task in the sub-project.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Portal/ResetPasswordRenderTest.php

use App\Models\AkunPendaftar;
use Illuminate\Support\Facades\Password;

it('renders the reset-password page with a valid token', function () {
    $akun = AkunPendaftar::factory()->create(['email' => 'ahmad@example.test']);
    $token = Password::broker('akun_pendaftar')->createToken($akun);

    $response = $this->get(route('portal.password.reset', ['token' => $token]) . '?email=' . urlencode('ahmad@example.test'));

    $response->assertOk();
    $response->assertSee('Kata Sandi Baru');
});

it('resets the password with a valid token and a strong new password', function () {
    $akun = AkunPendaftar::factory()->create(['email' => 'ahmad@example.test', 'password' => 'OldPassword1']);
    $token = Password::broker('akun_pendaftar')->createToken($akun);

    $response = $this->post(route('portal.password.store'), [
        'token' => $token,
        'email' => 'ahmad@example.test',
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ]);

    $response->assertRedirect(route('portal.login'));
    $response->assertSessionHas('status');
    expect(\Illuminate\Support\Facades\Hash::check('NewPassword1', $akun->fresh()->password))->toBeTrue();
});

it('rejects a reset password that does not meet the strong-password rule', function () {
    $akun = AkunPendaftar::factory()->create(['email' => 'ahmad@example.test']);
    $token = Password::broker('akun_pendaftar')->createToken($akun);

    $response = $this->post(route('portal.password.store'), [
        'token' => $token,
        'email' => 'ahmad@example.test',
        'password' => 'lowercaseonly1',
        'password_confirmation' => 'lowercaseonly1',
    ]);

    $response->assertSessionHasErrors('password');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Portal/ResetPasswordRenderTest.php`
Expected: FAIL — the render test fails because the current view says "Password Baru", not "Kata Sandi Baru" (written against the new copy the redesign introduces). The two submit tests actually already pass against the current code (Task 1 already applied the strong-password rule to `NewPasswordController`, and the reset logic itself is untouched) — confirm this by checking their output shows PASS even before Step 3's view changes; they're included here for completeness of coverage on this controller (which had no dedicated test before this plan), not because they're expected to fail.

- [ ] **Step 3: Rewrite the login view**

```php
<?php
// resources/views/portal/auth/login.blade.php
```

```blade
<x-layouts.portal-auth
    title="Masuk"
    link-label="Belum punya akun?"
    link-text="Daftar Akun"
    link-route="spmb.welcome"
>
    <div class="mx-auto flex min-h-[460px] max-w-7xl items-center justify-center px-4 py-10 sm:px-6 lg:px-10">
        <div class="w-full max-w-[420px] rounded-[20px] border border-gray-200 bg-white p-8 shadow-[0_20px_44px_rgba(30,58,95,0.10)]">
            <div class="mb-6 text-center">
                <h1 class="text-[21px] font-bold text-gray-900">Masuk ke Akunmu</h1>
                <p class="mt-1.5 text-[12.5px] text-gray-500">Pantau status pendaftaran, dokumen, dan tagihanmu di sini.</p>
            </div>

            <form method="POST" action="{{ route('portal.login') }}">
                @csrf
                <div class="mb-4">
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Alamat Email</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-[13px] top-1/2 -translate-y-1/2 text-gray-400">
                            <x-icon name="mail" class="h-[15px] w-[15px]" />
                        </span>
                        <input type="email" name="email" value="{{ old('email') }}" placeholder="nama@email.com" required autofocus
                            class="w-full rounded-[10px] border border-gray-200 py-[11px] pl-[38px] pr-3.5 text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    </div>
                    @error('email') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>

                <div class="mb-4">
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Kata Sandi</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-[13px] top-1/2 -translate-y-1/2 text-gray-400">
                            <x-icon name="lock" class="h-[15px] w-[15px]" />
                        </span>
                        <input type="password" name="password" placeholder="Kata sandi" required
                            class="w-full rounded-[10px] border border-gray-200 py-[11px] pl-[38px] pr-3.5 text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    </div>
                </div>

                <div class="-mt-0.5 mb-[22px] flex flex-wrap items-center justify-between gap-2 text-[12.5px]">
                    <label class="flex items-center gap-2 text-gray-500">
                        <input type="checkbox" name="remember" class="h-4 w-4 rounded-[5px] border-gray-300 text-portal-500 focus:ring-portal-500/20">
                        Ingat saya
                    </label>
                    <a href="{{ route('portal.password.request') }}" class="font-semibold text-portal-500">Lupa kata sandi?</a>
                </div>

                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-[10px] bg-portal-500 py-[13px] text-[13.5px] font-bold text-white transition hover:bg-portal-600">
                    Masuk
                    <x-icon name="arrow_forward" class="h-3.5 w-3.5" />
                </button>
            </form>

            <p class="mt-[18px] text-center text-[12.5px] text-gray-500">Belum punya akun? <a href="{{ route('spmb.welcome') }}" class="font-bold text-portal-500">Daftar di sini</a></p>
        </div>
    </div>
</x-layouts.portal-auth>
```

- [ ] **Step 4: Rewrite the forgot-password view**

```php
<?php
// resources/views/portal/auth/forgot-password.blade.php
```

```blade
<x-layouts.portal-auth
    title="Lupa Kata Sandi"
    link-label="Ingat kata sandimu?"
    link-text="Masuk"
    link-route="portal.login"
>
    <div class="mx-auto flex min-h-[460px] max-w-7xl items-center justify-center px-4 py-10 sm:px-6 lg:px-10">
        <div class="w-full max-w-[420px] rounded-[20px] border border-gray-200 bg-white p-8 shadow-[0_20px_44px_rgba(30,58,95,0.10)]">
            <div class="mb-6 text-center">
                <h1 class="text-[21px] font-bold text-gray-900">Lupa Kata Sandi</h1>
                <p class="mt-1.5 text-[12.5px] text-gray-500">Masukkan emailmu, kami kirimkan tautan untuk mengatur ulang kata sandi.</p>
            </div>

            <form method="POST" action="{{ route('portal.password.email') }}">
                @csrf
                <div class="mb-[22px]">
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Alamat Email</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-[13px] top-1/2 -translate-y-1/2 text-gray-400">
                            <x-icon name="mail" class="h-[15px] w-[15px]" />
                        </span>
                        <input type="email" name="email" value="{{ old('email') }}" placeholder="nama@email.com" required autofocus
                            class="w-full rounded-[10px] border border-gray-200 py-[11px] pl-[38px] pr-3.5 text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    </div>
                    @error('email') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-[10px] bg-portal-500 py-[13px] text-[13.5px] font-bold text-white transition hover:bg-portal-600">
                    Kirim Tautan Reset
                </button>
            </form>
        </div>
    </div>
</x-layouts.portal-auth>
```

- [ ] **Step 5: Rewrite the reset-password view**

```php
<?php
// resources/views/portal/auth/reset-password.blade.php
```

```blade
<x-layouts.portal-auth
    title="Atur Ulang Kata Sandi"
    link-label="Ingat kata sandimu?"
    link-text="Masuk"
    link-route="portal.login"
>
    <div class="mx-auto flex min-h-[460px] max-w-7xl items-center justify-center px-4 py-10 sm:px-6 lg:px-10">
        <div class="w-full max-w-[420px] rounded-[20px] border border-gray-200 bg-white p-8 shadow-[0_20px_44px_rgba(30,58,95,0.10)]">
            <div class="mb-6 text-center">
                <h1 class="text-[21px] font-bold text-gray-900">Kata Sandi Baru</h1>
                <p class="mt-1.5 text-[12.5px] text-gray-500">Buat kata sandi baru untuk akunmu.</p>
            </div>

            <form method="POST" action="{{ route('portal.password.store') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <div class="mb-4">
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Alamat Email</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-[13px] top-1/2 -translate-y-1/2 text-gray-400">
                            <x-icon name="mail" class="h-[15px] w-[15px]" />
                        </span>
                        <input type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus
                            class="w-full rounded-[10px] border border-gray-200 py-[11px] pl-[38px] pr-3.5 text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    </div>
                    @error('email') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>

                <div class="mb-4" x-data="passwordStrength()">
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Kata Sandi Baru</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-[13px] top-1/2 -translate-y-1/2 text-gray-400">
                            <x-icon name="lock" class="h-[15px] w-[15px]" />
                        </span>
                        <input type="password" name="password" placeholder="Minimal 8 karakter" required
                            x-model="value"
                            class="w-full rounded-[10px] border border-gray-200 py-[11px] pl-[38px] pr-3.5 text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    </div>
                    <div class="mt-2 flex gap-1" x-show="value.length > 0" x-cloak>
                        <template x-for="n in 4" :key="n">
                            <i class="h-1 flex-1 rounded-full" :class="{
                                'bg-success-500': tier === 'strong',
                                'bg-warning-500': tier === 'mid' && n <= 2,
                                'bg-gray-200': tier === 'weak' || (tier === 'mid' && n > 2),
                            }"></i>
                        </template>
                    </div>
                    <p class="mt-1.5 text-[11px] font-semibold" x-show="value.length > 0" x-cloak
                        x-text="tier === 'strong' ? 'Kuat — sudah memenuhi huruf besar, kecil, dan angka' : (tier === 'mid' ? 'Sedang — tambahkan huruf besar/kecil dan angka' : 'Lemah — minimal 8 karakter')"
                        :class="tier === 'strong' ? 'text-success-700' : (tier === 'mid' ? 'text-warning-700' : 'text-gray-400')"></p>
                    @error('password') <p class="mt-1.5 text-[11px] text-error-700">{{ $message }}</p> @enderror
                </div>

                <div class="mb-[22px]">
                    <label class="mb-[7px] block text-[12.5px] font-semibold text-gray-900">Konfirmasi Kata Sandi Baru</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-[13px] top-1/2 -translate-y-1/2 text-gray-400">
                            <x-icon name="lock" class="h-[15px] w-[15px]" />
                        </span>
                        <input type="password" name="password_confirmation" placeholder="Ulangi kata sandi baru" required
                            class="w-full rounded-[10px] border border-gray-200 py-[11px] pl-[38px] pr-3.5 text-[13.5px] text-gray-900 focus:border-portal-500 focus:outline-none focus:ring-2 focus:ring-portal-500/20">
                    </div>
                </div>

                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-[10px] bg-portal-500 py-[13px] text-[13.5px] font-bold text-white transition hover:bg-portal-600">
                    Simpan Kata Sandi Baru
                </button>
            </form>
        </div>
    </div>
</x-layouts.portal-auth>
```

- [ ] **Step 6: Update the existing render-smoke test**

`tests/Feature/Portal/PortalAuthPagesRenderTest.php` already covers `/portal/register`, `/portal/login`, `/portal/forgot-password`, `/portal/verifikasi-otp` generically — no change needed there, since none of those routes changed shape. No action for this step; confirming coverage stays valid is verified by Step 8's full run.

- [ ] **Step 7: Run tests to verify they pass**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Portal/ResetPasswordRenderTest.php tests/Feature/Portal/PortalAuthPagesRenderTest.php tests/Feature/Portal/LoginTest.php`
Expected: PASS (12/12 — 3 in `ResetPasswordRenderTest`, 4 in `PortalAuthPagesRenderTest`, 5 in `LoginTest`)

- [ ] **Step 8: Rebuild assets and run the full suite**

Run: `npm run build`
Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all tests pass, output pristine.

- [ ] **Step 9: Commit**

```bash
git add resources/views/portal/auth/login.blade.php resources/views/portal/auth/forgot-password.blade.php resources/views/portal/auth/reset-password.blade.php tests/Feature/Portal/ResetPasswordRenderTest.php
git commit -m "feat: redesign masuk, lupa password, and reset password pages to navy portal shell"
```

---

## Post-Plan Note

After Task 4, all 5 auth pages (Register, Verifikasi OTP, Masuk, Lupa Password, Reset Password) run on the navy portal design, with `no_hp_wa` collected, a strong-password rule enforced everywhere a password is set, an explicit OTP-resend action, and the register page reading `spmb_pilihan.*` to show context + a jalur-comparison sidebar. `Route::has('spmb.register')` now resolves true, so Sub-project 1's "Daftar Jalur Ini" button redirects here instead of showing "coming soon." Sub-project 3 (Dashboard & Wizard Ter-otentikasi) and Sub-project 4 (Pembayaran & Jadwal Tes) remain separate, not-yet-started plans.

**Before considering this sub-project fully done, repeat the rigorous re-fetch-and-diff pass against the mockup artifact** (https://claude.ai/code/artifact/a1987ae5-0050-440d-af88-08cfe01415af, screens 3–5) that Sub-project 1 needed as a post-ship follow-up — this plan's Blade code was written directly from the mockup's actual saved HTML/CSS (not from memory), but a fresh visual comparison after implementation is still the way to catch anything a code-level review can't (rendering differences, spacing that reads wrong in practice, etc.).
