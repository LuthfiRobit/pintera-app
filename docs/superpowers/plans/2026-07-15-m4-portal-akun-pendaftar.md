# Portal Akun Pendaftar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a standalone, session-based public account system (`AkunPendaftar`) for SPMB candidates, with automatic linking to their existing `Pendaftaran` records and a minimal authenticated dashboard — the foundation sub-project 1 of 3 for the Keuangan initiative.

**Architecture:** A brand-new Authenticatable model (`AkunPendaftar`) and auth guard (`portal`), fully isolated from the admin `User`/`web` guard. Registration reuses M2's existing `OtpService` for email verification. Linking to `Pendaftaran` happens automatically by email match, in both directions (account-verified-after-registration, and registration-submitted-after-account-exists) — never a manual "claim" step. The existing M2 public wizard is untouched except one additive line at the end of `ReviewSubmitController::submit()`.

**Tech Stack:** Laravel 12, Blade, Alpine.js (reuses the existing `otp-input.js` component), Pest PHP.

## Global Constraints

- `AkunPendaftar` does NOT use `BelongsToTenant`/`TenantScope` — it is not lembaga-scoped (a candidate can register for any lembaga in the yayasan).
- New guard `portal` with its own provider `akun_pendaftar` — a **plain** `EloquentUserProvider` (NOT the `tenant-aware` driver used for the `web` guard's `User` provider). Guard `portal` and guard `web` must never be able to authenticate each other's model.
- `OtpService` and `VerifikasiEmailOtp` (from M2) are reused exactly as-is — no modification to either file in this plan.
- M2's already-shipped wizard code is untouched in this plan except for one additive block at the end of `ReviewSubmitController::submit()` (Task 4) — no existing line in that file is changed or removed.
- Pre-login portal pages (register, login, forgot-password, reset-password, verifikasi-otp) reuse the existing `<x-spmb-public-layout>` component (centered card) — they do NOT use the new portal dashboard layout.
- The post-login portal dashboard uses a **new** `layouts/portal.blade.php` (sidebar + navbar + footer, matching the *structure* of the admin layout) styled with the existing `spmb-*` blue design tokens (NOT ink/brass/paper).
- All portal routes are prefixed `/portal` and named `portal.*`.
- Auto-linking a `Pendaftaran` to an `AkunPendaftar` only ever targets accounts where `email_verified_at` is not null — an unverified account can never be used as an auto-link target.
- The default password-reset broker (`auth.defaults.passwords` = `users`, used by the existing admin `PasswordResetLinkController`/`NewPasswordController`) must remain unchanged. Portal password-reset controllers explicitly use `Password::broker('akun_pendaftar')`.
- PHP is not on PATH in this shell — use the full path `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe` for all `artisan`/test commands.

---

### Task 1: Data Layer — `AkunPendaftar` model, `portal` guard, migrations

**Files:**
- Create: `database/migrations/2026_07_15_100000_create_akun_pendaftar_table.php`
- Create: `database/migrations/2026_07_15_100100_add_akun_pendaftar_id_to_pendaftaran_table.php`
- Create: `database/migrations/2026_07_15_100200_create_akun_pendaftar_password_reset_tokens_table.php`
- Create: `app/Models/AkunPendaftar.php`
- Create: `database/factories/AkunPendaftarFactory.php`
- Modify: `config/auth.php`
- Modify: `app/Models/Pendaftaran.php`
- Test: `tests/Unit/AkunPendaftarTest.php`

**Interfaces:**
- Produces: `App\Models\AkunPendaftar` (fillable `nama,email,password`; `email_verified_at` datetime cast, `password` hashed cast), relation `AkunPendaftar::pendaftaran(): HasMany`.
- Produces: `Pendaftaran::akunPendaftar(): BelongsTo`, new fillable `akun_pendaftar_id`.
- Produces: guard `portal` (session driver, provider `akun_pendaftar`), usable via `Auth::guard('portal')` / `auth('portal')` / route middleware `auth:portal`, `guest:portal`.
- Produces: password broker `akun_pendaftar` (table `akun_pendaftar_password_reset_tokens`), usable via `Password::broker('akun_pendaftar')`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/AkunPendaftarTest.php

use App\Models\AkunPendaftar;
use App\Models\CalonMurid;
use App\Models\Pendaftaran;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates an akun pendaftar with a hashed password and datetime-cast email_verified_at', function () {
    $akun = AkunPendaftar::factory()->create([
        'email' => 'siswa@example.test',
        'password' => 'rahasia123',
    ]);

    expect($akun->email)->toBe('siswa@example.test');
    expect($akun->password)->not->toBe('rahasia123');
    expect(\Illuminate\Support\Facades\Hash::check('rahasia123', $akun->password))->toBeTrue();
    expect($akun->email_verified_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('exposes a pendaftaran relation and a matching inverse on Pendaftaran', function () {
    $akun = AkunPendaftar::factory()->create();
    $calonMurid = CalonMurid::factory()->create();
    $pendaftaran = Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id,
        'akun_pendaftar_id' => $akun->id,
    ]);

    expect($akun->pendaftaran)->toHaveCount(1);
    expect($akun->pendaftaran->first()->id)->toBe($pendaftaran->id);
    expect($pendaftaran->akunPendaftar->id)->toBe($akun->id);
});

it('resolves a working portal guard backed by the akun_pendaftar provider', function () {
    $akun = AkunPendaftar::factory()->create();

    auth('portal')->login($akun);

    expect(auth('portal')->check())->toBeTrue();
    expect(auth('portal')->id())->toBe($akun->id);
    expect(auth('web')->check())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/AkunPendaftarTest.php`
Expected: FAIL — `Class "App\Models\AkunPendaftar" not found` (model, migrations, factory, and guard config don't exist yet).

- [ ] **Step 3: Write the migrations**

```php
<?php
// database/migrations/2026_07_15_100000_create_akun_pendaftar_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('akun_pendaftar', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('akun_pendaftar');
    }
};
```

```php
<?php
// database/migrations/2026_07_15_100100_add_akun_pendaftar_id_to_pendaftaran_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pendaftaran', function (Blueprint $table) {
            $table->foreignId('akun_pendaftar_id')->nullable()->after('sk_ppdb_id')
                ->constrained('akun_pendaftar')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pendaftaran', function (Blueprint $table) {
            $table->dropConstrainedForeignId('akun_pendaftar_id');
        });
    }
};
```

```php
<?php
// database/migrations/2026_07_15_100200_create_akun_pendaftar_password_reset_tokens_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('akun_pendaftar_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('akun_pendaftar_password_reset_tokens');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php
// app/Models/AkunPendaftar.php

namespace App\Models;

use Database\Factories\AkunPendaftarFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class AkunPendaftar extends Authenticatable
{
    /** @use HasFactory<AkunPendaftarFactory> */
    use HasFactory, Notifiable;

    protected $table = 'akun_pendaftar';

    protected $fillable = [
        'nama',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function pendaftaran(): HasMany
    {
        return $this->hasMany(Pendaftaran::class, 'akun_pendaftar_id');
    }
}
```

- [ ] **Step 5: Write the factory**

```php
<?php
// database/factories/AkunPendaftarFactory.php

namespace Database\Factories;

use App\Models\AkunPendaftar;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AkunPendaftar>
 */
class AkunPendaftarFactory extends Factory
{
    protected $model = AkunPendaftar::class;

    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password',
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
```

- [ ] **Step 6: Wire the `portal` guard into `config/auth.php`**

In `config/auth.php`, add `App\Models\AkunPendaftar` to the top `use` block, then:

```php
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'portal' => [
            'driver' => 'session',
            'provider' => 'akun_pendaftar',
        ],
    ],
```

```php
    'providers' => [
        'users' => [
            'driver' => 'tenant-aware',
            'model' => env('AUTH_MODEL', User::class),
        ],

        'akun_pendaftar' => [
            'driver' => 'eloquent',
            'model' => AkunPendaftar::class,
        ],
    ],
```

```php
    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],

        'akun_pendaftar' => [
            'provider' => 'akun_pendaftar',
            'table' => 'akun_pendaftar_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],
```

Note the `akun_pendaftar` provider driver is plain `'eloquent'`, NOT `'tenant-aware'` — `AkunPendaftar` has no `TenantScope` to bypass.

- [ ] **Step 7: Add the relation and fillable to `Pendaftaran`**

In `app/Models/Pendaftaran.php`, add `'akun_pendaftar_id'` to the `$fillable` array (after `'sk_ppdb_id'`), and add:

```php
    public function akunPendaftar(): BelongsTo
    {
        return $this->belongsTo(AkunPendaftar::class);
    }
```

(placed near the other `BelongsTo` relations; `App\Models\AkunPendaftar` needs a `use` import at the top of the file).

- [ ] **Step 8: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/AkunPendaftarTest.php`
Expected: PASS (3/3)

- [ ] **Step 9: Run the full suite to confirm no regressions**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all pre-existing tests still pass (no config/auth.php change should affect the `web` guard's behavior — `providers.users` is untouched).

- [ ] **Step 10: Commit**

```bash
git add database/migrations/2026_07_15_100000_create_akun_pendaftar_table.php \
        database/migrations/2026_07_15_100100_add_akun_pendaftar_id_to_pendaftaran_table.php \
        database/migrations/2026_07_15_100200_create_akun_pendaftar_password_reset_tokens_table.php \
        app/Models/AkunPendaftar.php database/factories/AkunPendaftarFactory.php \
        config/auth.php app/Models/Pendaftaran.php tests/Unit/AkunPendaftarTest.php
git commit -m "feat: add AkunPendaftar model, portal auth guard, and pendaftaran linkage column"
```

---

### Task 2: Registrasi & Verifikasi OTP (termasuk auto-link arah A)

**Files:**
- Create: `app/Http/Controllers/Portal/Auth/RegisteredAkunController.php`
- Create: `app/Http/Controllers/Portal/Auth/VerifikasiOtpController.php`
- Create: `resources/views/portal/auth/register.blade.php`
- Create: `resources/views/portal/auth/verifikasi-otp.blade.php`
- Create: `routes/portal.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Portal/RegistrasiAkunTest.php`

**Interfaces:**
- Consumes: `App\Models\AkunPendaftar` (Task 1), `App\Services\OtpService::kirim()`/`verifikasi()` (existing, from M2 — signatures: `kirim(string $email): void`, `verifikasi(string $email, string $kode): bool`), `App\Models\Pendaftaran` (Task 1's `akun_pendaftar_id`), the existing `otp-input.js` Alpine component (already registered in `resources/js/app.js` as `Alpine.data('otpInput', otpInput)`), the existing `<x-spmb-public-layout>`, `<x-spmb-primary-button>`, `<x-spmb-text-input>`, `<x-panel>`, `<x-input-label>`, `<x-input-error>` components.
- Produces: routes `portal.register` (GET/POST), `portal.verifikasi-otp` (GET/POST) — later tasks extend `routes/portal.php` with more routes inside the same file.
- Produces: session key `portal_register_email_pending` (string, the email awaiting OTP confirmation) — read by Task 3's login controller too, when it needs to resend OTP for an unverified account.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Portal/RegistrasiAkunTest.php

use App\Mail\KodeOtpMail;
use App\Models\AkunPendaftar;
use App\Models\CalonMurid;
use App\Models\Pendaftaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('registers a new unverified akun and sends an otp email', function () {
    Mail::fake();

    $response = $this->post(route('portal.register'), [
        'nama' => 'Ahmad Fauzan',
        'email' => 'ahmad@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
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
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertSessionHasErrors('email');
});

it('verifies the correct otp, logs the akun in, and auto-links an existing pendaftaran with the same email', function () {
    $akun = AkunPendaftar::factory()->unverified()->create(['email' => 'ahmad@example.test']);
    $calonMurid = CalonMurid::factory()->create();
    $pendaftaran = Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id,
        'email_pendaftaran' => 'ahmad@example.test',
    ]);
    app(\App\Services\OtpService::class)->kirim('ahmad@example.test');
    $kode = \App\Models\VerifikasiEmailOtp::where('email', 'ahmad@example.test')->latest('id')->first()->kode_otp;
    session(['portal_register_email_pending' => 'ahmad@example.test']);

    $response = $this->post(route('portal.verifikasi-otp.store'), ['kode_otp' => $kode]);

    $response->assertRedirect(route('portal.dashboard'));
    $this->assertAuthenticatedAs($akun->fresh(), 'portal');
    expect($akun->fresh()->email_verified_at)->not->toBeNull();
    expect($pendaftaran->fresh()->akun_pendaftar_id)->toBe($akun->id);
});

it('does not auto-link a pendaftaran with a different email', function () {
    $akun = AkunPendaftar::factory()->unverified()->create(['email' => 'ahmad@example.test']);
    $calonMurid = CalonMurid::factory()->create();
    $pendaftaranLain = Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id,
        'email_pendaftaran' => 'orang.lain@example.test',
    ]);
    app(\App\Services\OtpService::class)->kirim('ahmad@example.test');
    $kode = \App\Models\VerifikasiEmailOtp::where('email', 'ahmad@example.test')->latest('id')->first()->kode_otp;
    session(['portal_register_email_pending' => 'ahmad@example.test']);

    $this->post(route('portal.verifikasi-otp.store'), ['kode_otp' => $kode]);

    expect($pendaftaranLain->fresh()->akun_pendaftar_id)->toBeNull();
});

it('rejects a wrong otp code', function () {
    AkunPendaftar::factory()->unverified()->create(['email' => 'ahmad@example.test']);
    app(\App\Services\OtpService::class)->kirim('ahmad@example.test');
    session(['portal_register_email_pending' => 'ahmad@example.test']);

    $response = $this->post(route('portal.verifikasi-otp.store'), ['kode_otp' => '000000']);

    $response->assertSessionHasErrors('kode_otp');
    $this->assertGuest('portal');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Portal/RegistrasiAkunTest.php`
Expected: FAIL — route `portal.register` not defined.

- [ ] **Step 3: Write the routes file**

```php
<?php
// routes/portal.php

use App\Http\Controllers\Portal\Auth\RegisteredAkunController;
use App\Http\Controllers\Portal\Auth\VerifikasiOtpController;
use Illuminate\Support\Facades\Route;

Route::prefix('portal')->name('portal.')->group(function () {
    Route::middleware('guest:portal')->group(function () {
        Route::get('register', [RegisteredAkunController::class, 'create'])->name('register');
        Route::post('register', [RegisteredAkunController::class, 'store'])->middleware('throttle:6,1');
    });

    Route::get('verifikasi-otp', [VerifikasiOtpController::class, 'create'])->name('verifikasi-otp');
    Route::post('verifikasi-otp', [VerifikasiOtpController::class, 'store'])
        ->middleware('throttle:6,1')->name('verifikasi-otp.store');
});
```

In `routes/web.php`, add near the other `require` lines:

```php
require __DIR__.'/portal.php';
```

- [ ] **Step 4: Write `RegisteredAkunController`**

```php
<?php
// app/Http/Controllers/Portal/Auth/RegisteredAkunController.php

namespace App\Http\Controllers\Portal\Auth;

use App\Models\AkunPendaftar;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class RegisteredAkunController extends BaseController
{
    public function create(): View
    {
        return view('portal.auth.register');
    }

    public function store(\Illuminate\Http\Request $request, OtpService $otpService): RedirectResponse
    {
        $data = Validator::make($request->all(), [
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:akun_pendaftar,email'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ])->validate();

        AkunPendaftar::create([
            'nama' => $data['nama'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $otpService->kirim($data['email']);

        session(['portal_register_email_pending' => $data['email']]);

        return redirect()->route('portal.verifikasi-otp');
    }
}
```

- [ ] **Step 5: Write `VerifikasiOtpController`**

```php
<?php
// app/Http/Controllers/Portal/Auth/VerifikasiOtpController.php

namespace App\Http\Controllers\Portal\Auth;

use App\Models\AkunPendaftar;
use App\Models\Pendaftaran;
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
        return view('portal.auth.verifikasi-otp');
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
        // update() no-ops here: email_verified_at is intentionally not in AkunPendaftar's
        // $fillable (it must only ever be set by this verification flow, never mass-assigned
        // from user input) — forceFill() bypasses that guard for this one trusted write.
        $akun->forceFill(['email_verified_at' => now()])->save();

        Pendaftaran::where('email_pendaftaran', $email)
            ->whereNull('akun_pendaftar_id')
            ->update(['akun_pendaftar_id' => $akun->id]);

        Auth::guard('portal')->login($akun);
        session()->forget('portal_register_email_pending');

        return redirect()->route('portal.dashboard');
    }
}
```

- [ ] **Step 6: Write the views**

```blade
{{-- resources/views/portal/auth/register.blade.php --}}
<x-spmb-public-layout title="Daftar Akun">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-spmb-primary">Daftar Akun Pendaftar</h2>
        <p class="mt-1 text-sm text-slate">Buat akun untuk memantau semua pendaftaran SPMB Anda di satu tempat.</p>

        <form method="POST" action="{{ route('portal.register') }}" class="mt-5 space-y-4">
            @csrf
            <div>
                <x-input-label value="Nama Lengkap" />
                <x-spmb-text-input type="text" name="nama" class="mt-1.5" :value="old('nama')" required autofocus />
                <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Email" />
                <x-spmb-text-input type="email" name="email" class="mt-1.5" :value="old('email')" required />
                <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Password" />
                <x-spmb-text-input type="password" name="password" class="mt-1.5" required />
                <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Konfirmasi Password" />
                <x-spmb-text-input type="password" name="password_confirmation" class="mt-1.5" required />
            </div>
            <x-spmb-primary-button class="w-full justify-center">Daftar</x-spmb-primary-button>
        </form>

        <p class="mt-5 text-center text-sm text-slate">
            Sudah punya akun? <a href="{{ route('portal.login') }}" class="font-semibold text-spmb-accent hover:underline">Masuk</a>
        </p>
    </x-panel>
</x-spmb-public-layout>
```

```blade
{{-- resources/views/portal/auth/verifikasi-otp.blade.php --}}
<x-spmb-public-layout title="Verifikasi Email">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-spmb-primary">Masukkan Kode Verifikasi</h2>
        <p class="mt-1 text-sm text-slate">Kode 6 digit sudah dikirim ke email Anda. Berlaku 10 menit.</p>

        <form
            method="POST"
            action="{{ route('portal.verifikasi-otp.store') }}"
            class="mt-5 space-y-4"
            x-data="otpInput()"
            @submit="$refs.kodeTersembunyi.value = kode"
        >
            @csrf
            <div>
                <x-input-label value="Kode Verifikasi" />
                <div class="mt-1.5 flex justify-between gap-2">
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
                            class="h-14 w-12 rounded-xl border-slate/25 text-center font-mono text-xl text-ink shadow-sm focus:border-spmb-accent focus:ring-spmb-accent"
                            @if ($i === 0) autofocus @endif
                        >
                    @endfor
                </div>
                <input type="hidden" name="kode_otp" x-ref="kodeTersembunyi">
                <x-input-error :messages="$errors->get('kode_otp')" class="mt-1.5" />
            </div>
            <x-spmb-primary-button class="w-full justify-center">Verifikasi</x-spmb-primary-button>
        </form>
    </x-panel>
</x-spmb-public-layout>
```

- [ ] **Step 7: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Portal/RegistrasiAkunTest.php`
Expected: PASS (5/5)

- [ ] **Step 8: Commit**

```bash
git add routes/portal.php routes/web.php app/Http/Controllers/Portal \
        resources/views/portal/auth/register.blade.php resources/views/portal/auth/verifikasi-otp.blade.php \
        tests/Feature/Portal/RegistrasiAkunTest.php
git commit -m "feat: add portal account registration with otp verification and auto-link"
```

---

### Task 3: Login, Logout, Lupa Password, & Pemisahan Guard

**Files:**
- Create: `app/Http/Controllers/Portal/Auth/AuthenticatedSessionController.php`
- Create: `app/Http/Controllers/Portal/Auth/PasswordResetLinkController.php`
- Create: `app/Http/Controllers/Portal/Auth/NewPasswordController.php`
- Create: `resources/views/portal/auth/login.blade.php`
- Create: `resources/views/portal/auth/forgot-password.blade.php`
- Create: `resources/views/portal/auth/reset-password.blade.php`
- Modify: `routes/portal.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Portal/LoginTest.php`

**Interfaces:**
- Consumes: `AkunPendaftar` (Task 1), guard `portal` (Task 1), `OtpService::kirim()` (existing), session key `portal_register_email_pending` (Task 2, reused here for the "unverified login → resend OTP" path), password broker `akun_pendaftar` (Task 1).
- Produces: routes `portal.login` (GET/POST), `portal.logout` (POST), `portal.password.request`/`portal.password.email`/`portal.password.reset`/`portal.password.store`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Portal/LoginTest.php

use App\Models\AkunPendaftar;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('logs a verified akun in with correct credentials', function () {
    $akun = AkunPendaftar::factory()->create(['email' => 'ahmad@example.test', 'password' => 'password123']);

    $response = $this->post(route('portal.login'), ['email' => 'ahmad@example.test', 'password' => 'password123']);

    $response->assertRedirect(route('portal.dashboard'));
    $this->assertAuthenticatedAs($akun, 'portal');
});

it('rejects an incorrect password', function () {
    AkunPendaftar::factory()->create(['email' => 'ahmad@example.test', 'password' => 'password123']);

    $response = $this->post(route('portal.login'), ['email' => 'ahmad@example.test', 'password' => 'salah']);

    $response->assertSessionHasErrors();
    $this->assertGuest('portal');
});

it('blocks login for an unverified akun and resends the otp', function () {
    AkunPendaftar::factory()->unverified()->create(['email' => 'ahmad@example.test', 'password' => 'password123']);

    $response = $this->post(route('portal.login'), ['email' => 'ahmad@example.test', 'password' => 'password123']);

    $response->assertRedirect(route('portal.verifikasi-otp'));
    $this->assertGuest('portal');
    expect(\App\Models\VerifikasiEmailOtp::where('email', 'ahmad@example.test')->exists())->toBeTrue();
});

it('logs the akun out', function () {
    $akun = AkunPendaftar::factory()->create();

    $this->actingAs($akun, 'portal')->post(route('portal.logout'));

    $this->assertGuest('portal');
});

it('does not let a staff user account authenticate against the portal guard, and vice versa', function () {
    (new RolePermissionSeeder)->run();
    $staff = User::factory()->create(['password' => 'password123']);
    $akun = AkunPendaftar::factory()->create(['password' => 'password123']);

    expect(\Illuminate\Support\Facades\Auth::guard('portal')->attempt(['email' => $staff->email, 'password' => 'password123']))->toBeFalse();
    expect(\Illuminate\Support\Facades\Auth::guard('web')->attempt(['email' => $akun->email, 'password' => 'password123']))->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Portal/LoginTest.php`
Expected: FAIL — route `portal.login` not defined.

- [ ] **Step 3: Extend `routes/portal.php`**

```php
<?php
// routes/portal.php (full file, replacing the Task 2 version)

use App\Http\Controllers\Portal\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Portal\Auth\NewPasswordController;
use App\Http\Controllers\Portal\Auth\PasswordResetLinkController;
use App\Http\Controllers\Portal\Auth\RegisteredAkunController;
use App\Http\Controllers\Portal\Auth\VerifikasiOtpController;
use Illuminate\Support\Facades\Route;

Route::prefix('portal')->name('portal.')->group(function () {
    Route::middleware('guest:portal')->group(function () {
        Route::get('register', [RegisteredAkunController::class, 'create'])->name('register');
        Route::post('register', [RegisteredAkunController::class, 'store'])->middleware('throttle:6,1');

        Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1');

        Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
            ->middleware('throttle:6,1')->name('password.email');
        Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
        Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
    });

    Route::get('verifikasi-otp', [VerifikasiOtpController::class, 'create'])->name('verifikasi-otp');
    Route::post('verifikasi-otp', [VerifikasiOtpController::class, 'store'])
        ->middleware('throttle:6,1')->name('verifikasi-otp.store');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->middleware('auth:portal')->name('logout');

    // Placeholder from Task 2, carried forward as-is: Task 4 replaces this with a real
    // DashboardController + view. Needed here because AuthenticatedSessionController::store()
    // (this task) and VerifikasiOtpController::store() (Task 2) both redirect to it.
    Route::get('dashboard', fn () => response('OK'))
        ->middleware('auth:portal')->name('dashboard');
});
```

- [ ] **Step 4: Write `AuthenticatedSessionController`**

```php
<?php
// app/Http/Controllers/Portal/Auth/AuthenticatedSessionController.php

namespace App\Http\Controllers\Portal\Auth;

use App\Models\AkunPendaftar;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends BaseController
{
    public function create(): View
    {
        return view('portal.auth.login');
    }

    public function store(Request $request, OtpService $otpService): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $akun = AkunPendaftar::where('email', $credentials['email'])->first();

        if (! $akun || ! \Illuminate\Support\Facades\Hash::check($credentials['password'], $akun->password)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }

        if (! $akun->email_verified_at) {
            $otpService->kirim($akun->email);
            session(['portal_register_email_pending' => $akun->email]);

            return redirect()->route('portal.verifikasi-otp')
                ->withErrors(['email' => 'Email Anda belum diverifikasi. Kode baru sudah dikirim.']);
        }

        Auth::guard('portal')->login($akun, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('portal.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('portal')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
```

- [ ] **Step 5: Write `PasswordResetLinkController` and `NewPasswordController`**

```php
<?php
// app/Http/Controllers/Portal/Auth/PasswordResetLinkController.php

namespace App\Http\Controllers\Portal\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends BaseController
{
    public function create(): View
    {
        return view('portal.auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::broker('akun_pendaftar')->sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
```

```php
<?php
// app/Http/Controllers/Portal/Auth/NewPasswordController.php

namespace App\Http\Controllers\Portal\Auth;

use App\Models\AkunPendaftar;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class NewPasswordController extends BaseController
{
    public function create(Request $request): View
    {
        return view('portal.auth.reset-password', ['request' => $request]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::broker('akun_pendaftar')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (AkunPendaftar $akun) use ($request) {
                $akun->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($akun));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('portal.login')->with('status', __($status))
            : back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
```

- [ ] **Step 6: Write the views**

```blade
{{-- resources/views/portal/auth/login.blade.php --}}
<x-spmb-public-layout title="Masuk">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-spmb-primary">Masuk ke Akun Pendaftar</h2>

        @if (session('status'))
            <p class="mt-2 text-sm font-medium text-signal-green">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('portal.login') }}" class="mt-5 space-y-4">
            @csrf
            <div>
                <x-input-label value="Email" />
                <x-spmb-text-input type="email" name="email" class="mt-1.5" :value="old('email')" required autofocus />
                <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Password" />
                <x-spmb-text-input type="password" name="password" class="mt-1.5" required />
            </div>
            <div class="flex items-center justify-between">
                <label class="inline-flex items-center text-sm text-slate">
                    <input type="checkbox" name="remember" class="rounded border-slate/25 text-spmb-accent shadow-sm focus:ring-spmb-accent">
                    <span class="ms-2">Ingat saya</span>
                </label>
                <a href="{{ route('portal.password.request') }}" class="text-sm text-spmb-accent hover:underline">Lupa password?</a>
            </div>
            <x-spmb-primary-button class="w-full justify-center">Masuk</x-spmb-primary-button>
        </form>

        <p class="mt-5 text-center text-sm text-slate">
            Belum punya akun? <a href="{{ route('portal.register') }}" class="font-semibold text-spmb-accent hover:underline">Daftar</a>
        </p>
    </x-panel>
</x-spmb-public-layout>
```

```blade
{{-- resources/views/portal/auth/forgot-password.blade.php --}}
<x-spmb-public-layout title="Lupa Password">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-spmb-primary">Lupa Password</h2>
        <p class="mt-1 text-sm text-slate">Masukkan email Anda, kami kirimkan tautan untuk mengatur ulang password.</p>

        @if (session('status'))
            <p class="mt-2 text-sm font-medium text-signal-green">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('portal.password.email') }}" class="mt-5 space-y-4">
            @csrf
            <div>
                <x-input-label value="Email" />
                <x-spmb-text-input type="email" name="email" class="mt-1.5" :value="old('email')" required autofocus />
                <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
            </div>
            <x-spmb-primary-button class="w-full justify-center">Kirim Tautan Reset</x-spmb-primary-button>
        </form>
    </x-panel>
</x-spmb-public-layout>
```

```blade
{{-- resources/views/portal/auth/reset-password.blade.php --}}
<x-spmb-public-layout title="Atur Ulang Password">
    <x-panel class="p-6">
        <h2 class="font-display text-lg font-bold text-spmb-primary">Atur Ulang Password</h2>

        <form method="POST" action="{{ route('portal.password.store') }}" class="mt-5 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <div>
                <x-input-label value="Email" />
                <x-spmb-text-input type="email" name="email" class="mt-1.5" :value="old('email', $request->email)" required autofocus />
                <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Password Baru" />
                <x-spmb-text-input type="password" name="password" class="mt-1.5" required />
                <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
            </div>
            <div>
                <x-input-label value="Konfirmasi Password Baru" />
                <x-spmb-text-input type="password" name="password_confirmation" class="mt-1.5" required />
            </div>
            <x-spmb-primary-button class="w-full justify-center">Simpan Password Baru</x-spmb-primary-button>
        </form>
    </x-panel>
</x-spmb-public-layout>
```

- [ ] **Step 7: Register guard-aware redirects in `AppServiceProvider`**

Modify `app/Providers/AppServiceProvider.php`'s `boot()` method — the file already has `Auth::provider('tenant-aware', ...)` from a prior fix; add the following alongside it (same method, same imports block extended):

```php
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Auth\Notifications\ResetPassword;
use App\Models\AkunPendaftar;
```

```php
    public function boot(): void
    {
        Auth::provider('tenant-aware', function ($app, array $config) {
            return new TenantAwareUserProvider($app['hash'], $config['model']);
        });

        Authenticate::redirectUsing(
            fn ($request) => $request->is('portal/*') ? route('portal.login') : route('login')
        );

        RedirectIfAuthenticated::redirectUsing(
            fn ($request) => $request->is('portal/*') ? route('portal.dashboard') : route('dashboard')
        );

        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            $routeName = $notifiable instanceof AkunPendaftar ? 'portal.password.reset' : 'password.reset';

            return url(route($routeName, [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));
        });
    }
```

This is why the guard-separation test in Step 1 matters: without `Authenticate::redirectUsing()`/`RedirectIfAuthenticated::redirectUsing()` being guard-aware, an unauthenticated `/portal/dashboard` request would incorrectly redirect to the admin `/login` page, and an already-logged-in portal account visiting `/portal/login` would incorrectly redirect to the admin `/dashboard` (which requires the `web` guard and would just bounce them to `/login` again).

- [ ] **Step 8: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Portal/LoginTest.php`
Expected: PASS (5/5)

- [ ] **Step 9: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all tests pass — specifically re-check `tests/Feature/Auth/*.php` and `tests/Feature/Auth/YayasanLembagaSwitcherAuthTest.php` still pass unmodified (the `Authenticate`/`RedirectIfAuthenticated` closures must preserve the exact prior behavior for any non-`/portal/*` request).

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Portal/Auth/AuthenticatedSessionController.php \
        app/Http/Controllers/Portal/Auth/PasswordResetLinkController.php \
        app/Http/Controllers/Portal/Auth/NewPasswordController.php \
        resources/views/portal/auth/login.blade.php resources/views/portal/auth/forgot-password.blade.php \
        resources/views/portal/auth/reset-password.blade.php routes/portal.php \
        app/Providers/AppServiceProvider.php tests/Feature/Portal/LoginTest.php
git commit -m "feat: add portal login, logout, and password reset with guard-aware redirects"
```

---

### Task 4: Auto-link Arah B, Dashboard, Layout Portal, & Unduh PDF

**Files:**
- Modify: `app/Http/Controllers/Spmb/ReviewSubmitController.php`
- Create: `app/Http/Middleware/EnsureAkunPendaftarVerified.php`
- Modify: `bootstrap/app.php`
- Create: `resources/views/layouts/portal.blade.php`
- Create: `app/Http/Controllers/Portal/DashboardController.php`
- Create: `app/Http/Controllers/Portal/BuktiPendaftaranController.php`
- Create: `resources/views/portal/dashboard.blade.php`
- Modify: `routes/portal.php`
- Modify: `resources/views/layouts/spmb-public.blade.php`
- Test: `tests/Feature/Portal/AutoLinkTest.php`
- Test: `tests/Feature/Portal/DashboardTest.php`

**Interfaces:**
- Consumes: `AkunPendaftar`/guard `portal` (Task 1), `ReviewSubmitController::submit()` (existing M2 controller — the insertion point is right after the `try { ... } catch (QueryException $exception) { ... }` block that produces `$pendaftaran`, before the existing `$this->pindahkanDokumenKeLokasiFinal($pendaftaran);` line), the existing `pdf.bukti-pendaftaran` Blade view (from M2, unchanged, expects `$lembaga` and `$pendaftaran`), `Pendaftaran::lembaga()` relation (existing).
- Produces: routes `portal.dashboard` (GET), `portal.pendaftaran.bukti` (GET) — completes `routes/portal.php`.
- Produces: middleware alias `portal.verified` (`App\Http\Middleware\EnsureAkunPendaftarVerified`).

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Portal/AutoLinkTest.php

use App\Models\AkunPendaftar;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use App\Services\PendaftaranWizardSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('auto-links a new pendaftaran submitted through the m2 wizard to an already-verified akun with the same email', function () {
    Storage::fake('public');
    $akun = AkunPendaftar::factory()->create(['email' => 'ahmad@example.test']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now()->subDay(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 40,
    ]);

    $wizardSession = app(PendaftaranWizardSession::class);
    $wizardSession->put($lembaga, $jalur, [
        'email_pendaftaran' => 'ahmad@example.test',
        'nik' => '3200000000000001',
        'data_pribadi' => ['nama_lengkap' => 'Ahmad Fauzan', 'jenis_kelamin' => 'L', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '2012-01-01', 'agama' => 'Islam'],
        'alamat' => ['alamat_jalan' => 'Jl. Mawar', 'desa_kelurahan' => 'A', 'kecamatan' => 'B', 'kabupaten_kota' => 'C', 'provinsi' => 'D'],
        'keluarga' => [['jenis' => 'ayah', 'nama' => 'Bapak Ahmad']],
    ]);

    $this->post(route('spmb.submit', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]));

    $pendaftaran = \App\Models\Pendaftaran::where('email_pendaftaran', 'ahmad@example.test')->first();
    expect($pendaftaran)->not->toBeNull();
    expect($pendaftaran->akun_pendaftar_id)->toBe($akun->id);
});
```

```php
<?php
// tests/Feature/Portal/DashboardTest.php

use App\Models\AkunPendaftar;
use App\Models\CalonMurid;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function buatPendaftaranUntukAkun(AkunPendaftar $akun, string $nama = 'Ahmad Fauzan'): Pendaftaran
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id, 'nama_lengkap' => $nama]);

    return Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id,
        'lembaga_id' => $lembaga->id,
        'akun_pendaftar_id' => $akun->id,
        'email_pendaftaran' => $akun->email,
    ]);
}

it('redirects an unverified akun away from the dashboard to the otp page', function () {
    $akun = AkunPendaftar::factory()->unverified()->create();

    $this->actingAs($akun, 'portal')->get(route('portal.dashboard'))
        ->assertRedirect(route('portal.verifikasi-otp'));
});

it('shows only the pendaftaran linked to the logged-in akun', function () {
    $akunSaya = AkunPendaftar::factory()->create();
    buatPendaftaranUntukAkun($akunSaya, 'Punya Saya');
    $akunLain = AkunPendaftar::factory()->create();
    buatPendaftaranUntukAkun($akunLain, 'Punya Orang Lain');

    $response = $this->actingAs($akunSaya, 'portal')->get(route('portal.dashboard'));

    $response->assertOk();
    $response->assertSee('Punya Saya');
    $response->assertDontSee('Punya Orang Lain');
});

it('shows an empty state when no pendaftaran is linked', function () {
    $akun = AkunPendaftar::factory()->create();

    $this->actingAs($akun, 'portal')->get(route('portal.dashboard'))->assertOk();
});

it('allows downloading the bukti pendaftaran pdf for a pendaftaran the akun owns', function () {
    $akun = AkunPendaftar::factory()->create();
    $pendaftaran = buatPendaftaranUntukAkun($akun);

    $this->actingAs($akun, 'portal')
        ->get(route('portal.pendaftaran.bukti', $pendaftaran))
        ->assertOk();
});

it('404s when trying to download a pendaftaran belonging to a different akun', function () {
    $akunLain = AkunPendaftar::factory()->create();
    $pendaftaranOrangLain = buatPendaftaranUntukAkun($akunLain);
    $akunSaya = AkunPendaftar::factory()->create();

    $this->actingAs($akunSaya, 'portal')
        ->get(route('portal.pendaftaran.bukti', $pendaftaranOrangLain))
        ->assertNotFound();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Portal/AutoLinkTest.php tests/Feature/Portal/DashboardTest.php`
Expected: FAIL — `route('portal.dashboard')`/`route('portal.pendaftaran.bukti')` not defined; auto-link snippet not yet added to `ReviewSubmitController`.

- [ ] **Step 3: Add auto-link arah B to `ReviewSubmitController::submit()`**

In `app/Http/Controllers/Spmb/ReviewSubmitController.php`, add the import `use App\Models\AkunPendaftar;` at the top, then insert this block immediately after the existing `try { ... } catch (QueryException $exception) { ... }` block (i.e., right before the existing `$this->pindahkanDokumenKeLokasiFinal($pendaftaran);` line) — no existing line is changed:

```php
        $akun = AkunPendaftar::where('email', $pendaftaran->email_pendaftaran)
            ->whereNotNull('email_verified_at')
            ->first();

        if ($akun) {
            $pendaftaran->update(['akun_pendaftar_id' => $akun->id]);
        }
```

- [ ] **Step 4: Write `EnsureAkunPendaftarVerified` middleware and register its alias**

```php
<?php
// app/Http/Middleware/EnsureAkunPendaftarVerified.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureAkunPendaftarVerified
{
    public function handle(Request $request, Closure $next)
    {
        $akun = Auth::guard('portal')->user();

        if (! $akun || ! $akun->email_verified_at) {
            return redirect()->route('portal.verifikasi-otp');
        }

        return $next($request);
    }
}
```

In `bootstrap/app.php`, extend the `withMiddleware` closure to register the alias:

```php
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\ResolveTenant::class,
        ]);

        $middleware->alias([
            'portal.verified' => \App\Http\Middleware\EnsureAkunPendaftarVerified::class,
        ]);
    })
```

- [ ] **Step 5: Write the portal layout**

```blade
{{-- resources/views/layouts/portal.blade.php --}}
@props(['title' => 'Portal Pendaftar'])

<!DOCTYPE html>
<html lang="id" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title }} — {{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:500,600,700,800|inter:400,500,600,700|ibm-plex-mono:500&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full bg-spmb-bg font-sans text-ink antialiased" x-data="{ sidebarOpen: false }">
        <div
            x-show="sidebarOpen"
            x-transition.opacity
            @click="sidebarOpen = false"
            class="fixed inset-0 z-30 bg-ink/40 lg:hidden"
            style="display: none;"
        ></div>

        <div class="flex min-h-full">
            <aside
                class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-spmb-primary transition-transform duration-200 ease-out lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
                :class="{ 'translate-x-0': sidebarOpen }"
            >
                <div class="flex h-20 shrink-0 items-center gap-3 border-b border-white/10 px-6">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15 font-display text-lg font-bold text-white">
                        {{ Str::of(config('app.name', 'P'))->substr(0, 1) }}
                    </span>
                    <p class="font-display text-base font-bold text-white">Portal Pendaftar</p>
                </div>

                <nav class="flex-1 overflow-y-auto px-4 py-6">
                    <a
                        href="{{ route('portal.dashboard') }}"
                        class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold {{ request()->routeIs('portal.dashboard') ? 'bg-white/15 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' }}"
                    >
                        <span class="material-symbols-outlined" style="font-size: 20px;">dashboard</span>
                        Dashboard
                    </a>
                </nav>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-20 flex h-20 shrink-0 items-center gap-4 border-b border-slate/10 bg-white/70 px-4 backdrop-blur-md sm:px-6">
                    <button @click="sidebarOpen = true" class="text-slate hover:text-ink lg:hidden" aria-label="Buka menu">
                        <span class="material-symbols-outlined">menu</span>
                    </button>
                    <div class="min-w-0 flex-1"></div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium text-ink">{{ auth('portal')->user()->nama }}</span>
                        <form method="POST" action="{{ route('portal.logout') }}">
                            @csrf
                            <button type="submit" class="text-sm text-slate hover:text-ink">Keluar</button>
                        </form>
                    </div>
                </header>

                <main class="flex-1 px-4 py-8 sm:px-6 lg:px-10">
                    {{ $slot }}
                </main>

                <footer class="px-4 py-6 text-center text-xs text-slate sm:px-6">
                    &copy; {{ now()->year }} {{ config('app.name') }}
                </footer>
            </div>
        </div>
    </body>
</html>
```

- [ ] **Step 6: Write `DashboardController` and `BuktiPendaftaranController`**

```php
<?php
// app/Http/Controllers/Portal/DashboardController.php

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

```php
<?php
// app/Http/Controllers/Portal/BuktiPendaftaranController.php

namespace App\Http\Controllers\Portal;

use App\Models\Pendaftaran;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;

class BuktiPendaftaranController extends BaseController
{
    public function unduh(Pendaftaran $pendaftaran): Response
    {
        abort_unless($pendaftaran->akun_pendaftar_id === Auth::guard('portal')->id(), 404);

        $pdf = Pdf::loadView('pdf.bukti-pendaftaran', [
            'lembaga' => $pendaftaran->lembaga,
            'pendaftaran' => $pendaftaran,
        ]);

        return $pdf->stream('bukti-pendaftaran-'.$pendaftaran->kode_pendaftaran.'.pdf');
    }
}
```

- [ ] **Step 7: Write the dashboard view**

```blade
{{-- resources/views/portal/dashboard.blade.php --}}
<x-portal-layout title="Dashboard">
    <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-spmb-accent">Portal Pendaftar</p>
    <h1 class="mt-1 font-display text-2xl font-bold text-spmb-primary">Pendaftaran Saya</h1>

    @if ($pendaftaranList->isEmpty())
        <x-panel class="mt-6 p-8 text-center">
            <p class="text-sm text-slate">Belum ada pendaftaran yang tertaut ke akun ini.</p>
        </x-panel>
    @else
        <div class="mt-6 space-y-4">
            @foreach ($pendaftaranList as $pendaftaran)
                <x-panel class="p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-display font-semibold text-ink">{{ $pendaftaran->calonMurid->nama_lengkap }}</p>
                            <p class="mt-0.5 text-sm text-slate">{{ $pendaftaran->lembaga->nama }} &middot; {{ $pendaftaran->jalurPpdb->nama }} &middot; {{ $pendaftaran->gelombangPpdb->nama }}</p>
                            <p class="mt-0.5 font-mono text-xs text-slate">{{ $pendaftaran->kode_pendaftaran }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-badge :tone="$pendaftaran->status === 'diterima' ? 'green' : ($pendaftaran->status === 'ditolak' ? 'red' : 'amber')">
                                {{ $pendaftaran->status === 'diterima' ? 'Diterima' : ($pendaftaran->status === 'ditolak' ? 'Ditolak' : 'Menunggu Verifikasi') }}
                            </x-badge>
                            <a href="{{ route('portal.pendaftaran.bukti', $pendaftaran) }}" target="_blank" class="text-sm font-semibold text-spmb-accent hover:underline">
                                Unduh Bukti (PDF)
                            </a>
                        </div>
                    </div>
                </x-panel>
            @endforeach
        </div>
    @endif
</x-portal-layout>
```

- [ ] **Step 8: Extend `routes/portal.php` with the protected group**

**Remove** the placeholder route from Tasks 2/3 (`Route::get('dashboard', fn () => response('OK'))->middleware('auth:portal')->name('dashboard');` at the end of the `Route::prefix('portal')->name('portal.')->group(...)` closure) — its comment says Task 4 replaces it, this is that replacement. Leaving both in place would register two routes named `portal.dashboard`.

In its place, add (inside the same closure, alongside the existing groups):

```php
    Route::middleware(['auth:portal', 'portal.verified'])->group(function () {
        Route::get('dashboard', [\App\Http\Controllers\Portal\DashboardController::class, 'index'])->name('dashboard');
        Route::get('pendaftaran/{pendaftaran}/bukti', [\App\Http\Controllers\Portal\BuktiPendaftaranController::class, 'unduh'])
            ->name('pendaftaran.bukti');
    });
```

- [ ] **Step 9: Fix the empty-lembaga fallback in the shared spmb-public layout**

In `resources/views/layouts/spmb-public.blade.php`, this line currently renders blank when no `$lembaga` is passed (a branch M2's own pages never exercise, since they always pass a real `$lembaga` — this change is a pure no-op for every existing M2 page):

```blade
<h1 class="font-display text-lg font-bold text-spmb-primary">{{ $lembaga->nama ?? '' }}</h1>
```
becomes:
```blade
<h1 class="font-display text-lg font-bold text-spmb-primary">{{ $lembaga->nama ?? config('app.name') }}</h1>
```

- [ ] **Step 10: Run tests to verify they pass**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Portal/AutoLinkTest.php tests/Feature/Portal/DashboardTest.php`
Expected: PASS (6/6)

- [ ] **Step 11: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all tests pass, including every pre-existing M2 `Spmb\*` test (the `ReviewSubmitController::submit()` change is purely additive).

- [ ] **Step 12: Commit**

```bash
git add app/Http/Controllers/Spmb/ReviewSubmitController.php app/Http/Middleware/EnsureAkunPendaftarVerified.php \
        bootstrap/app.php resources/views/layouts/portal.blade.php app/Http/Controllers/Portal/DashboardController.php \
        app/Http/Controllers/Portal/BuktiPendaftaranController.php resources/views/portal/dashboard.blade.php \
        routes/portal.php resources/views/layouts/spmb-public.blade.php \
        tests/Feature/Portal/AutoLinkTest.php tests/Feature/Portal/DashboardTest.php
git commit -m "feat: add portal dashboard, bukti-pendaftaran download, and m2 auto-link hook"
```

---

## Post-Plan Note

After Task 4, sub-project 1 of the Keuangan initiative (per `docs/superpowers/specs/2026-07-15-m4-portal-akun-pendaftar-design.md`) is feature-complete: candidates can register, verify via OTP, log in, and see every `Pendaftaran` linked to their account (auto-linked from either direction) with a PDF download, inside a real portal layout ready for sub-project 3 to extend with a "Tagihan & Pembayaran" menu item. Sub-project 2 (Keuangan — Master Tagihan & Mesin Invoicing) is the next plan to write, not yet started.
