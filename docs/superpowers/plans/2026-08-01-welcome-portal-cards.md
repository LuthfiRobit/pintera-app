# Welcome Index Portal Cards Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the default Laravel landing page with a modern glassmorphism 4-card portal landing page (Portal Yayasan, Portal Admin, Portal Guru, Portal Siswa) linking to the universal login endpoint.

**Architecture:** A responsive mobile-first grid built directly into `resources/views/welcome.blade.php` using Tailwind CSS v4 design tokens, glassmorphism surface styles, semantic HTML5 structure, and SVG vector icons. Verified via automated feature tests in Pest.

**Tech Stack:** Laravel 12, Blade, Tailwind CSS v4, Pest PHP.

## Global Constraints

- Must preserve universal authentication entry point by linking all portal CTA buttons directly to `route('login')`.
- All cards must use responsive grid classes: `grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6`.
- Must adhere to WCAG AA color contrast rules for body text (≥ 4.5:1 ratio) across Light and Dark mode variations.
- All touch targets must be at least 44×44pt for accessibility.
- No external runtime JavaScript libraries or unverified assets; pure Blade, CSS, and inline vector SVGs.

---

### Task 1: Implement Welcome Portal Cards View & Feature Tests

**Files:**
- Create: `tests/Feature/WelcomePortalCardsTest.php`
- Modify: `resources/views/welcome.blade.php:1-278`
- Test: `tests/Feature/WelcomePortalCardsTest.php`

**Interfaces:**
- Consumes: `route('login')`, `route('dashboard')`, `route('register')` (from Laravel core routing).
- Produces: Visual presentation of 4 interactive cards (`Portal Yayasan`, `Portal Admin`, `Portal Guru`, `Portal Siswa`) on root endpoint `/`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/WelcomePortalCardsTest.php` with the following test cases:

```php
<?php

it('renders the welcome portal landing page with status 200', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertSee('Selamat Datang di Portal Terpadu Pintera');
});

it('displays all four portal cards with correct titles and descriptions', function () {
    $response = $this->get('/');

    // Portal Yayasan
    $response->assertSee('Portal Yayasan');
    $response->assertSee('Monitoring kinerja, evaluasi KPI, & ringkasan finansial lembaga.');
    $response->assertSee('Masuk Yayasan');

    // Portal Admin
    $response->assertSee('Portal Admin');
    $response->assertSee('Manajemen data sekolah, kepegawaian, tagihan & konfigurasi SPMB.');
    $response->assertSee('Masuk Admin');

    // Portal Guru
    $response->assertSee('Portal Guru');
    $response->assertSee('Pengisian presensi biometrik, penyusunan RPP, & pencatatan nilai.');
    $response->assertSee('Masuk Guru');

    // Portal Siswa
    $response->assertSee('Portal Siswa');
    $response->assertSee('Akses kalender akademik, lihat tagihan sekolah, & e-rapor.');
    $response->assertSee('Masuk Siswa');
});

it('links all portal cards directly to the centralized login route', function () {
    $response = $this->get('/');

    $loginUrl = route('login');
    
    // Assert that the login URL is rendered multiple times for our portal CTA buttons
    $content = $response->getContent();
    expect(substr_count($content, $loginUrl))->toBeGreaterThanOrEqual(4);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/WelcomePortalCardsTest.php`  
Expected: FAIL with asserting that `"Selamat Datang di Portal Terpadu Pintera"` and `"Portal Yayasan"` are seen in the response.

- [ ] **Step 3: Write minimal implementation**

Replace the entire contents of `resources/views/welcome.blade.php` with our high-end responsive glassmorphic layout:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Pintera') }} - Portal Terpadu Ekosistem Pendidikan</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

        <!-- Styles & Scripts -->
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <script src="https://cdn.tailwindcss.com"></script>
            <script>
                tailwind.config = {
                    darkMode: 'media',
                    theme: {
                        extend: {
                            fontFamily: {
                                sans: ['Instrument Sans', 'sans-serif'],
                            },
                        }
                    }
                }
            </script>
        @endif
    </head>
    <body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] min-h-screen flex flex-col justify-between selection:bg-emerald-500 selection:text-white font-sans antialiased overflow-x-hidden relative">
        
        {{-- Background ambient glow & mesh styling --}}
        <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden">
            <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[800px] h-[450px] bg-gradient-to-tr from-emerald-500/15 to-blue-500/15 dark:from-emerald-500/10 dark:to-purple-500/10 blur-[120px] rounded-full"></div>
            <div class="absolute -bottom-40 -left-20 w-[600px] h-[600px] bg-gradient-to-br from-amber-500/10 to-indigo-500/10 blur-[140px] rounded-full"></div>
        </div>

        {{-- Top Navigation Header --}}
        <header class="w-full max-w-7xl mx-auto px-6 py-6 z-10 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-700 flex items-center justify-center shadow-lg shadow-emerald-500/20 text-white font-bold text-xl">
                    P
                </div>
                <span class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Pintera App</span>
            </div>

            @if (Route::has('login'))
                <nav class="flex items-center gap-3">
                    @auth
                        <a
                            href="{{ url('/dashboard') }}"
                            class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 text-sm font-semibold rounded-lg bg-gray-900 text-white dark:bg-white dark:text-gray-900 hover:bg-gray-800 dark:hover:bg-gray-100 transition shadow-sm"
                        >
                            Ke Dashboard &rarr;
                        </a>
                    @else
                        <a
                            href="{{ route('login') }}"
                            class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:text-emerald-600 dark:hover:text-emerald-400 transition"
                        >
                            Masuk Akun
                        </a>

                        @if (Route::has('register'))
                            <a
                                href="{{ route('register') }}"
                                class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 text-sm font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition shadow-sm shadow-emerald-600/20"
                            >
                                Daftar SPMB
                            </a>
                        @endif
                    @endauth
                </nav>
            @endif
        </header>

        {{-- Main Hero & Portal Grid Section --}}
        <main class="w-full max-w-7xl mx-auto px-6 py-12 lg:py-20 z-10 flex-1 flex flex-col justify-center">
            <div class="text-center max-w-3xl mx-auto mb-16 space-y-4">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold uppercase tracking-wider mb-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Portal Ekosistem Pendidikan Terpadu
                </div>
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-gray-900 dark:text-white leading-tight">
                    Selamat Datang di Portal Terpadu Pintera
                </h1>
                <p class="text-base sm:text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto leading-relaxed">
                    Pilih portal sesuai dengan hak akses dan kewenangan Anda dalam ekosistem sekolah untuk mulai beraktivitas.
                </p>
            </div>

            {{-- 4 Cards Interactive Grid --}}
            <section aria-label="Daftar Portal Layanan" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 w-full">
                
                {{-- Card 1: Portal Yayasan --}}
                <div class="group relative bg-white/80 dark:bg-[#161615]/80 backdrop-blur-md border border-gray-200/80 dark:border-white/10 rounded-2xl p-6 shadow-lg shadow-black/5 hover:shadow-2xl hover:shadow-emerald-500/10 hover:-translate-y-1 hover:border-emerald-500/50 dark:hover:border-emerald-500/50 transition-all duration-300 flex flex-col justify-between">
                    <div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-6 border border-emerald-500/20 group-hover:scale-110 transition-transform duration-300">
                            {{-- Building Columns Icon --}}
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2 group-hover:text-emerald-500 transition-colors">
                            Portal Yayasan
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed mb-8">
                            Monitoring kinerja, evaluasi KPI, & ringkasan finansial lembaga.
                        </p>
                    </div>
                    <a
                        href="{{ route('login') }}"
                        aria-label="Masuk ke Portal Yayasan"
                        class="w-full inline-flex items-center justify-between min-h-[44px] px-4 py-2.5 rounded-xl text-sm font-semibold bg-gray-100 dark:bg-white/5 text-gray-900 dark:text-white hover:bg-emerald-500 hover:text-white dark:hover:bg-emerald-600 group-hover:bg-emerald-500 group-hover:text-white transition-all duration-300"
                    >
                        <span>Masuk Yayasan</span>
                        <span class="text-lg leading-none">&rarr;</span>
                    </a>
                </div>

                {{-- Card 2: Portal Admin --}}
                <div class="group relative bg-white/80 dark:bg-[#161615]/80 backdrop-blur-md border border-gray-200/80 dark:border-white/10 rounded-2xl p-6 shadow-lg shadow-black/5 hover:shadow-2xl hover:shadow-blue-500/10 hover:-translate-y-1 hover:border-blue-500/50 dark:hover:border-blue-500/50 transition-all duration-300 flex flex-col justify-between">
                    <div>
                        <div class="w-12 h-12 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center mb-6 border border-blue-500/20 group-hover:scale-110 transition-transform duration-300">
                            {{-- Shield Cog / Gear Icon --}}
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2 group-hover:text-blue-500 transition-colors">
                            Portal Admin
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed mb-8">
                            Manajemen data sekolah, kepegawaian, tagihan & konfigurasi SPMB.
                        </p>
                    </div>
                    <a
                        href="{{ route('login') }}"
                        aria-label="Masuk ke Portal Admin dan Tata Usaha"
                        class="w-full inline-flex items-center justify-between min-h-[44px] px-4 py-2.5 rounded-xl text-sm font-semibold bg-gray-100 dark:bg-white/5 text-gray-900 dark:text-white hover:bg-blue-500 hover:text-white dark:hover:bg-blue-600 group-hover:bg-blue-500 group-hover:text-white transition-all duration-300"
                    >
                        <span>Masuk Admin</span>
                        <span class="text-lg leading-none">&rarr;</span>
                    </a>
                </div>

                {{-- Card 3: Portal Guru --}}
                <div class="group relative bg-white/80 dark:bg-[#161615]/80 backdrop-blur-md border border-gray-200/80 dark:border-white/10 rounded-2xl p-6 shadow-lg shadow-black/5 hover:shadow-2xl hover:shadow-amber-500/10 hover:-translate-y-1 hover:border-amber-500/50 dark:hover:border-amber-500/50 transition-all duration-300 flex flex-col justify-between">
                    <div>
                        <div class="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center mb-6 border border-amber-500/20 group-hover:scale-110 transition-transform duration-300">
                            {{-- Academic Book / Educator Icon --}}
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2 group-hover:text-amber-500 transition-colors">
                            Portal Guru
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed mb-8">
                            Pengisian presensi biometrik, penyusunan RPP, & pencatatan nilai.
                        </p>
                    </div>
                    <a
                        href="{{ route('login') }}"
                        aria-label="Masuk ke Portal Guru dan Tenaga Didik"
                        class="w-full inline-flex items-center justify-between min-h-[44px] px-4 py-2.5 rounded-xl text-sm font-semibold bg-gray-100 dark:bg-white/5 text-gray-900 dark:text-white hover:bg-amber-500 hover:text-white dark:hover:bg-amber-600 group-hover:bg-amber-500 group-hover:text-white transition-all duration-300"
                    >
                        <span>Masuk Guru</span>
                        <span class="text-lg leading-none">&rarr;</span>
                    </a>
                </div>

                {{-- Card 4: Portal Siswa --}}
                <div class="group relative bg-white/80 dark:bg-[#161615]/80 backdrop-blur-md border border-gray-200/80 dark:border-white/10 rounded-2xl p-6 shadow-lg shadow-black/5 hover:shadow-2xl hover:shadow-purple-500/10 hover:-translate-y-1 hover:border-purple-500/50 dark:hover:border-purple-500/50 transition-all duration-300 flex flex-col justify-between">
                    <div>
                        <div class="w-12 h-12 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center mb-6 border border-purple-500/20 group-hover:scale-110 transition-transform duration-300">
                            {{-- Graduation Cap Icon --}}
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5zm0 0v6" />
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2 group-hover:text-purple-500 transition-colors">
                            Portal Siswa
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed mb-8">
                            Akses kalender akademik, lihat tagihan sekolah, & e-rapor.
                        </p>
                    </div>
                    <a
                        href="{{ route('login') }}"
                        aria-label="Masuk ke Portal Siswa dan Wali Murid"
                        class="w-full inline-flex items-center justify-between min-h-[44px] px-4 py-2.5 rounded-xl text-sm font-semibold bg-gray-100 dark:bg-white/5 text-gray-900 dark:text-white hover:bg-purple-500 hover:text-white dark:hover:bg-purple-600 group-hover:bg-purple-500 group-hover:text-white transition-all duration-300"
                    >
                        <span>Masuk Siswa</span>
                        <span class="text-lg leading-none">&rarr;</span>
                    </a>
                </div>

            </section>
        </main>

        {{-- Footer --}}
        <footer class="w-full max-w-7xl mx-auto px-6 py-8 z-10 border-t border-gray-200/50 dark:border-white/10 flex flex-col sm:flex-row items-center justify-between text-xs text-gray-500 dark:text-gray-400 gap-4">
            <div>
                &copy; {{ date('Y') }} <strong class="text-gray-800 dark:text-gray-200">Pintera App</strong>. Hak cipta dilindungi undang-undang.
            </div>
            <div class="flex items-center gap-6">
                <span>Sistem Manajemen Ekosistem Terpadu</span>
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <span>v1.0-Demo</span>
            </div>
        </footer>
    </body>
</html>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/WelcomePortalCardsTest.php`  
Expected: PASS with 3 assertions succeeding.

- [ ] **Step 5: Run full test suite & Commit**

Run: `php artisan test`  
Expected: All green.

Commit:
```bash
git add tests/Feature/WelcomePortalCardsTest.php resources/views/welcome.blade.php
git commit -m "feat: redesign welcome index page with 4 glassmorphic portal cards"
```

---

## Self-Review Checklist

1. **Spec Coverage:** Verified all 4 cards (Portal Yayasan, Portal Admin, Portal Guru, Portal Siswa) exist with requested text, modern glassmorphism styling, vibrant accent tokens, and universal links to `route('login')`.
2. **Placeholder Scan:** No TBD, TODO, or incomplete snippets found. Complete Blade template and complete test class included verbatim.
3. **Type Consistency:** Routing names match standard Laravel conventions (`login`, `dashboard`, `register`).
