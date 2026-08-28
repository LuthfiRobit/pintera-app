# Bottom Navigation Bar (Mobile/Tablet) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun komponen Bottom Navigation Bar melayang (*Icon-First Floating Pill*) untuk perangkat mobile dan tablet (< 1024px) bagi akun Guru, Orang Tua, dan Siswa, terintegrasi langsung dengan Sidebar tanpa duplikasi drawer.

**Architecture:** Komponen Blade `resources/views/layouts/bottom-nav.blade.php` di-include dalam layout `resources/views/layouts/app.blade.php`, merender navigasi 5-slot berbasis konteks personal pengguna dengan trigger Alpine.js `@click="sidebarOpen = true"` untuk Slot 5 (Menu).

**Tech Stack:** Laravel Blade, Tailwind CSS, Alpine.js, Lucide Icons, Pest PHP Feature Tests.

## Global Constraints

- Sidebar tetap menjadi *Single Source of Truth* untuk hierarki RBAC dan navigasi lengkap.
- Gaya visual adalah **Icon-First Minimalist Floating Pill** (`rounded-full`, `max-w-3xl`, `h-16`, `bg-white/95 backdrop-blur-sm`, `border-gray-200`, `shadow-elevated`).
- Ikon seragam 24px (`w-6 h-6`, stroke 2px), tanpa teks label visual; aksesibilitas dijamin via `aria-label` dan `<span class="sr-only">`.
- Guru adalah satu-satunya peran dengan circular FAB melayang (48–52px) di Slot 3; Orang Tua dan Siswa 100% Flat.
- Active state membedakan query parameter `fitur` pada rute `dalam-pengembangan` agar tidak terjadi multi-active indicator bersamaan.
- Responsive cutoff adalah `< 1024px` (`lg:hidden`).

---

### Task 1: Test Suite Scaffolding (`tests/Feature/BottomNavTest.php`)

**Files:**
- Create: `tests/Feature/BottomNavTest.php`

**Interfaces:**
- Consumes: Models `User`, `Guru`, `OrangTua`, `Siswa`, `Lembaga`, `Role`, `Permission`.
- Produces: Test assertions for role-based bottom nav rendering, slot routes, FAB geometry classes, aria-labels, active states, and exclusion of non-personal roles.

- [x] **Step 1: Write the failing tests**

```php
<?php

use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function siapkanUserPersonal(string $roleName): User
{
    $permissions = [
        'guru' => ['presensi.isi', 'asesmen.kelola', 'kehadiran-sdm.lihat-qr-sendiri'],
        'orang_tua' => ['keuangan.akses', 'kasus.view'],
        'siswa' => ['kasus.view'],
    ];

    foreach ($permissions[$roleName] ?? [] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    if (! empty($permissions[$roleName])) {
        $role->givePermissionTo($permissions[$roleName]);
    }

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($roleName);

    if ($roleName === 'guru') {
        Guru::factory()->create(['user_id' => $user->id, 'lembaga_id' => $lembaga->id]);
    } elseif ($roleName === 'orang_tua') {
        OrangTua::factory()->create(['user_id' => $user->id]);
    } elseif ($roleName === 'siswa') {
        Siswa::factory()->create(['user_id' => $user->id, 'lembaga_id' => $lembaga->id]);
    }

    return $user;
}

it('renders Guru bottom nav with 5 slots including QR Saya FAB for guru account', function () {
    $guru = siapkanUserPersonal('guru');

    $response = $this->actingAs($guru)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('aria-label="Beranda"', false);
    $response->assertSee('aria-label="Jurnal"', false);
    $response->assertSee('aria-label="QR Saya"', false);
    $response->assertSee('aria-label="Nilai"', false);
    $response->assertSee('aria-label="Buka menu"', false);
    $response->assertSee(route('guru.jurnal-kbm.index'));
    $response->assertSee(route('sdm.qr-saya'));
    $response->assertSee(route('guru.asesmen.index'));
    $response->assertSee('sidebarOpen = true', false);
});

it('renders Orang Tua bottom nav with 5 flat slots for orang tua account', function () {
    $ortu = siapkanUserPersonal('orang_tua');

    $response = $this->actingAs($ortu)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('aria-label="Beranda"', false);
    $response->assertSee('aria-label="Nilai Anak"', false);
    $response->assertSee('aria-label="Tagihan"', false);
    $response->assertSee('aria-label="Presensi Anak"', false);
    $response->assertSee('aria-label="Buka menu"', false);
    $response->assertSee(route('keuangan.dashboard'));
    $response->assertSee(route('dalam-pengembangan', ['fitur' => 'nilai-anak']));
    $response->assertSee(route('dalam-pengembangan', ['fitur' => 'riwayat-izin-sakit-anak']));
});

it('renders Siswa bottom nav with 5 flat slots for siswa account', function () {
    $siswa = siapkanUserPersonal('siswa');

    $response = $this->actingAs($siswa)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('aria-label="Beranda"', false);
    $response->assertSee('aria-label="Jadwal Pelajaran"', false);
    $response->assertSee('aria-label="Presensi Saya"', false);
    $response->assertSee('aria-label="Nilai &amp; Rapor"', false);
    $response->assertSee('aria-label="Buka menu"', false);
    $response->assertSee(route('dalam-pengembangan', ['fitur' => 'jadwal-pelajaran']));
    $response->assertSee(route('dalam-pengembangan', ['fitur' => 'presensi-saya']));
    $response->assertSee(route('dalam-pengembangan', ['fitur' => 'nilai-rapor']));
});

it('does not render bottom nav for non-personal accounts (admin, staff, yayasan)', function () {
    $admin = User::factory()->create();
    $adminRole = Role::firstOrCreate(['name' => 'platform_super_admin', 'guard_name' => 'web'], ['scope_level' => 'platform']);
    $admin->assignRole($adminRole);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('id="bottom-nav"', false);
    $response->assertDontSee('aria-label="Buka menu"', false);
});

it('correctly matches active state for placeholder routes based on fitur query parameter', function () {
    $siswa = siapkanUserPersonal('siswa');

    $response = $this->actingAs($siswa)->get(route('dalam-pengembangan', ['fitur' => 'jadwal-pelajaran']));

    $response->assertOk();
    // Verify the response contains the active dot indicator for Jadwal Pelajaran
    $response->assertSee('data-active="jadwal-pelajaran"', false);
    $response->assertDontSee('data-active="presensi-saya"', false);
    $response->assertDontSee('data-active="nilai-rapor"', false);
});
```

- [x] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/BottomNavTest.php`
Expected: FAIL (Bottom nav markup does not exist yet).

---

### Task 2: Implementasi Komponen Blade `resources/views/layouts/bottom-nav.blade.php`

**Files:**
- Create: `resources/views/layouts/bottom-nav.blade.php`

**Interfaces:**
- Consumes: `Auth::user()`, route helpers, query helper `request()->query('fitur')`.
- Produces: Accessible 5-slot `<nav id="bottom-nav">` with Icon-First Floating Pill design.

- [x] **Step 1: Write the Blade component template**

```blade
@php
    $user = Auth::user();
    if (! $user) {
        return;
    }

    $isGuru = $user->hasRole('guru');
    $isSiswa = ! $isGuru && $user->hasRole('siswa');
    $isOrangTua = ! $isGuru && ! $isSiswa && $user->orangTua !== null;

    if (! $isGuru && ! $isSiswa && ! $isOrangTua) {
        return;
    }

    // Active state checks
    $isBerandaActive = request()->routeIs('dashboard');
    $isJurnalActive = request()->routeIs('guru.jurnal-kbm.*');
    $isQrActive = request()->routeIs('sdm.qr-saya*');
    $isNilaiGuruActive = request()->routeIs('guru.asesmen.*');
    $isTagihanActive = request()->routeIs('keuangan.*');

    $fiturParam = request()->query('fitur');
    $isNilaiOrtuActive = request()->routeIs('dalam-pengembangan') && $fiturParam === 'nilai-anak';
    $isPresensiOrtuActive = request()->routeIs('dalam-pengembangan') && $fiturParam === 'riwayat-izin-sakit-anak';
    $isJadwalSiswaActive = request()->routeIs('dalam-pengembangan') && $fiturParam === 'jadwal-pelajaran';
    $isPresensiSiswaActive = request()->routeIs('dalam-pengembangan') && $fiturParam === 'presensi-saya';
    $isNilaiSiswaActive = request()->routeIs('dalam-pengembangan') && $fiturParam === 'nilai-rapor';
@endphp

<nav
    id="bottom-nav"
    class="fixed bottom-3 inset-x-3 z-20 mx-auto h-16 w-[calc(100%-24px)] max-w-3xl rounded-full border border-gray-200 bg-white/95 shadow-elevated backdrop-blur-sm lg:hidden"
    style="bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px));"
    aria-label="Navigasi Bawah"
>
    <div class="grid h-full grid-cols-5 items-center">
        @if ($isGuru)
            {{-- Slot 1: Beranda --}}
            <a
                href="{{ route('dashboard') }}"
                class="flex flex-col items-center justify-center p-2 transition duration-150 ease-out active:scale-[0.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 rounded-full"
                aria-label="Beranda"
                @if($isBerandaActive) data-active="beranda" @endif
            >
                <x-dynamic-component :component="'lucide-layout-dashboard'" class="h-6 w-6 {{ $isBerandaActive ? 'text-brand-600' : 'text-gray-500' }}" />
                <span class="sr-only">Beranda</span>
                @if ($isBerandaActive)
                    <span class="mt-1 h-1 w-1 rounded-full bg-brand-600"></span>
                @endif
            </a>

            {{-- Slot 2: Jurnal --}}
            <a
                href="{{ route('guru.jurnal-kbm.index') }}"
                class="flex flex-col items-center justify-center p-2 transition duration-150 ease-out active:scale-[0.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 rounded-full"
                aria-label="Jurnal"
                @if($isJurnalActive) data-active="jurnal" @endif
            >
                <x-dynamic-component :component="'lucide-file-pen'" class="h-6 w-6 {{ $isJurnalActive ? 'text-brand-600' : 'text-gray-500' }}" />
                <span class="sr-only">Jurnal</span>
                @if ($isJurnalActive)
                    <span class="mt-1 h-1 w-1 rounded-full bg-brand-600"></span>
                @endif
            </a>

            {{-- Slot 3: QR Saya (FAB) --}}
            <div class="flex items-center justify-center">
                <a
                    href="{{ route('sdm.qr-saya') }}"
                    class="relative -translate-y-2 flex h-12 w-12 items-center justify-center rounded-full bg-brand-500 text-white shadow-md transition duration-150 ease-out hover:bg-brand-600 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30"
                    aria-label="QR Saya"
                    @if($isQrActive) data-active="qr-saya" @endif
                >
                    <x-dynamic-component :component="'lucide-qr-code'" class="h-6 w-6 text-white" />
                    <span class="sr-only">QR Saya</span>
                </a>
            </div>

            {{-- Slot 4: Nilai --}}
            <a
                href="{{ route('guru.asesmen.index') }}"
                class="flex flex-col items-center justify-center p-2 transition duration-150 ease-out active:scale-[0.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 rounded-full"
                aria-label="Nilai"
                @if($isNilaiGuruActive) data-active="nilai" @endif
            >
                <x-dynamic-component :component="'lucide-award'" class="h-6 w-6 {{ $isNilaiGuruActive ? 'text-brand-600' : 'text-gray-500' }}" />
                <span class="sr-only">Nilai</span>
                @if ($isNilaiGuruActive)
                    <span class="mt-1 h-1 w-1 rounded-full bg-brand-600"></span>
                @endif
            </a>
        @elseif ($isOrangTua)
            {{-- Slot 1: Beranda --}}
            <a
                href="{{ route('dashboard') }}"
                class="flex flex-col items-center justify-center p-2 transition duration-150 ease-out active:scale-[0.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 rounded-full"
                aria-label="Beranda"
                @if($isBerandaActive) data-active="beranda" @endif
            >
                <x-dynamic-component :component="'lucide-layout-dashboard'" class="h-6 w-6 {{ $isBerandaActive ? 'text-brand-600' : 'text-gray-500' }}" />
                <span class="sr-only">Beranda</span>
                @if ($isBerandaActive)
                    <span class="mt-1 h-1 w-1 rounded-full bg-brand-600"></span>
                @endif
            </a>

            {{-- Slot 2: Nilai Anak --}}
            <a
                href="{{ route('dalam-pengembangan', ['fitur' => 'nilai-anak']) }}"
                class="flex flex-col items-center justify-center p-2 transition duration-150 ease-out active:scale-[0.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 rounded-full"
                aria-label="Nilai Anak"
                @if($isNilaiOrtuActive) data-active="nilai-anak" @endif
            >
                <x-dynamic-component :component="'lucide-award'" class="h-6 w-6 {{ $isNilaiOrtuActive ? 'text-brand-600' : 'text-gray-500' }}" />
                <span class="sr-only">Nilai Anak</span>
                @if ($isNilaiOrtuActive)
                    <span class="mt-1 h-1 w-1 rounded-full bg-brand-600"></span>
                @endif
            </a>

            {{-- Slot 3: Tagihan (Flat) --}}
            <a
                href="{{ route('keuangan.dashboard') }}"
                class="flex flex-col items-center justify-center p-2 transition duration-150 ease-out active:scale-[0.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 rounded-full"
                aria-label="Tagihan"
                @if($isTagihanActive) data-active="tagihan" @endif
            >
                <x-dynamic-component :component="'lucide-wallet'" class="h-6 w-6 {{ $isTagihanActive ? 'text-brand-600' : 'text-gray-500' }}" />
                <span class="sr-only">Tagihan</span>
                @if ($isTagihanActive)
                    <span class="mt-1 h-1 w-1 rounded-full bg-brand-600"></span>
                @endif
            </a>

            {{-- Slot 4: Presensi Anak --}}
            <a
                href="{{ route('dalam-pengembangan', ['fitur' => 'riwayat-izin-sakit-anak']) }}"
                class="flex flex-col items-center justify-center p-2 transition duration-150 ease-out active:scale-[0.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 rounded-full"
                aria-label="Presensi Anak"
                @if($isPresensiOrtuActive) data-active="riwayat-izin-sakit-anak" @endif
            >
                <x-dynamic-component :component="'lucide-clipboard-check'" class="h-6 w-6 {{ $isPresensiOrtuActive ? 'text-brand-600' : 'text-gray-500' }}" />
                <span class="sr-only">Presensi Anak</span>
                @if ($isPresensiOrtuActive)
                    <span class="mt-1 h-1 w-1 rounded-full bg-brand-600"></span>
                @endif
            </a>
        @elseif ($isSiswa)
            {{-- Slot 1: Beranda --}}
            <a
                href="{{ route('dashboard') }}"
                class="flex flex-col items-center justify-center p-2 transition duration-150 ease-out active:scale-[0.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 rounded-full"
                aria-label="Beranda"
                @if($isBerandaActive) data-active="beranda" @endif
            >
                <x-dynamic-component :component="'lucide-layout-dashboard'" class="h-6 w-6 {{ $isBerandaActive ? 'text-brand-600' : 'text-gray-500' }}" />
                <span class="sr-only">Beranda</span>
                @if ($isBerandaActive)
                    <span class="mt-1 h-1 w-1 rounded-full bg-brand-600"></span>
                @endif
            </a>

            {{-- Slot 2: Jadwal Pelajaran --}}
            <a
                href="{{ route('dalam-pengembangan', ['fitur' => 'jadwal-pelajaran']) }}"
                class="flex flex-col items-center justify-center p-2 transition duration-150 ease-out active:scale-[0.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 rounded-full"
                aria-label="Jadwal Pelajaran"
                @if($isJadwalSiswaActive) data-active="jadwal-pelajaran" @endif
            >
                <x-dynamic-component :component="'lucide-calendar-clock'" class="h-6 w-6 {{ $isJadwalSiswaActive ? 'text-brand-600' : 'text-gray-500' }}" />
                <span class="sr-only">Jadwal Pelajaran</span>
                @if ($isJadwalSiswaActive)
                    <span class="mt-1 h-1 w-1 rounded-full bg-brand-600"></span>
                @endif
            </a>

            {{-- Slot 3: Presensi Saya (Flat) --}}
            <a
                href="{{ route('dalam-pengembangan', ['fitur' => 'presensi-saya']) }}"
                class="flex flex-col items-center justify-center p-2 transition duration-150 ease-out active:scale-[0.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 rounded-full"
                aria-label="Presensi Saya"
                @if($isPresensiSiswaActive) data-active="presensi-saya" @endif
            >
                <x-dynamic-component :component="'lucide-clipboard-check'" class="h-6 w-6 {{ $isPresensiSiswaActive ? 'text-brand-600' : 'text-gray-500' }}" />
                <span class="sr-only">Presensi Saya</span>
                @if ($isPresensiSiswaActive)
                    <span class="mt-1 h-1 w-1 rounded-full bg-brand-600"></span>
                @endif
            </a>

            {{-- Slot 4: Nilai & Rapor --}}
            <a
                href="{{ route('dalam-pengembangan', ['fitur' => 'nilai-rapor']) }}"
                class="flex flex-col items-center justify-center p-2 transition duration-150 ease-out active:scale-[0.96] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 rounded-full"
                aria-label="Nilai &amp; Rapor"
                @if($isNilaiSiswaActive) data-active="nilai-rapor" @endif
            >
                <x-dynamic-component :component="'lucide-award'" class="h-6 w-6 {{ $isNilaiSiswaActive ? 'text-brand-600' : 'text-gray-500' }}" />
                <span class="sr-only">Nilai &amp; Rapor</span>
                @if ($isNilaiSiswaActive)
                    <span class="mt-1 h-1 w-1 rounded-full bg-brand-600"></span>
                @endif
            </a>
        @endif

        {{-- Slot 5: Menu (Buka Sidebar Off-Canvas) --}}
        <button
            type="button"
            @click="sidebarOpen = true"
            class="flex flex-col items-center justify-center p-2 text-gray-500 transition duration-150 ease-out active:scale-[0.96] hover:text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 rounded-full"
            aria-label="Buka menu"
        >
            <x-dynamic-component :component="'lucide-menu'" class="h-6 w-6 text-gray-500" />
            <span class="sr-only">Buka menu</span>
        </button>
    </div>
</nav>
```

---

### Task 3: Integrasi Layout di `resources/views/layouts/app.blade.php`

**Files:**
- Modify: `resources/views/layouts/app.blade.php:17-46`

**Interfaces:**
- Consumes: Component `layouts.bottom-nav`.
- Produces: Conditional clearance `pb-28 lg:pb-6` on `<main>` and `@include('layouts.bottom-nav')` inside root Alpine context.

- [x] **Step 1: Update `resources/views/layouts/app.blade.php`**

```blade
            <div class="flex min-w-0 flex-1 flex-col">
                @include('layouts.topbar')

                @isset($header)
                    <header class="border-b border-gray-200 bg-white/70">
                        <div class="px-4 py-6 sm:px-6 lg:px-10">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                @php
                    $hasBottomNav = Auth::check() && (
                        Auth::user()->hasRole('guru') || 
                        Auth::user()->hasRole('siswa') || 
                        Auth::user()->orangTua !== null
                    );
                @endphp

                <main class="flex-1 px-4 py-6 sm:px-6 lg:px-10 {{ $hasBottomNav ? 'pb-28 lg:pb-6' : '' }}">
                    {{ $slot }}
                </main>
            </div>

            @include('layouts.bottom-nav')
```

- [x] **Step 2: Run test suite to verify tests pass**

Run: `php artisan test tests/Feature/BottomNavTest.php`
Expected: PASS (all 5 tests green).

---

### Task 4: Full Regression Suite, Code Formatting & Handoff

**Files:**
- Test: All Feature and Unit tests (`php artisan test --compact`)

- [x] **Step 1: Run complete test suite**

Run: `php artisan test --compact`
Expected: 2437+ passed, 0 failed.

- [x] **Step 2: Run code formatting**

Run: `vendor/bin/pint --dirty --format agent`
Expected: Clean / Formatted.

- [x] **Step 3: Commit changes**

```bash
git add resources/views/layouts/bottom-nav.blade.php resources/views/layouts/app.blade.php tests/Feature/BottomNavTest.php
git commit -m "feat(ui): tambah Bottom Navigation Bar (Icon-First Floating Pill) untuk Guru, Orang Tua, dan Siswa"
```
