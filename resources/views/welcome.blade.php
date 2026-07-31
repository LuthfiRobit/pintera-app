<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Pintera') }} - Portal Terpadu Ekosistem Pendidikan</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700" rel="stylesheet" />

        <!-- Styles & Scripts -->
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <script src="https://cdn.tailwindcss.com"></script>
            <script>
                tailwind.config = {
                    theme: {
                        extend: {
                            fontFamily: {
                                sans: ['Plus Jakarta Sans', 'sans-serif'],
                            },
                        }
                    }
                }
            </script>
        @endif
    </head>
    <body class="bg-slate-50 text-slate-700 min-h-screen flex flex-col justify-between font-sans antialiased selection:bg-emerald-500 selection:text-white">

        {{-- Clean Professional Navigation Bar --}}
        <header class="w-full bg-white border-b border-slate-200/80 sticky top-0 z-30">
            <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
                <div class="flex items-center gap-3.5">
                    <div class="h-11 w-11 rounded-xl bg-emerald-600 flex items-center justify-center text-white font-bold text-2xl shadow-sm shadow-emerald-600/20">
                        P
                    </div>
                    <div>
                        <span class="text-xl font-bold tracking-tight text-slate-900 block leading-tight">Pintera App</span>
                        <span class="text-xs font-medium text-slate-500">Sistem Ekosistem Terpadu</span>
                    </div>
                </div>

                @if (Route::has('login'))
                    <nav class="flex items-center gap-3">
                        @auth
                            <a
                                href="{{ url('/dashboard') }}"
                                class="inline-flex items-center justify-center min-h-[42px] px-5 text-sm font-semibold rounded-lg bg-slate-900 text-white hover:bg-slate-800 transition shadow-sm"
                            >
                                Ke Dashboard &rarr;
                            </a>
                        @else
                            <a
                                href="{{ route('login') }}"
                                class="inline-flex items-center justify-center min-h-[42px] px-4 text-sm font-semibold text-slate-700 hover:text-emerald-600 transition"
                            >
                                Masuk Akun
                            </a>

                            @if (Route::has('register'))
                                <a
                                    href="{{ route('register') }}"
                                    class="inline-flex items-center justify-center min-h-[42px] px-5 text-sm font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition shadow-sm shadow-emerald-600/15"
                                >
                                    Daftar SPMB
                                </a>
                            @endif
                        @endauth
                    </nav>
                @endif
            </div>
        </header>

        {{-- Main Section: Bright, Clean & Structured --}}
        <main class="w-full max-w-7xl mx-auto px-6 py-12 lg:py-16 flex-1 flex flex-col justify-center">
            
            {{-- Hero Heading --}}
            <div class="text-center max-w-3xl mx-auto mb-14">
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-600 bg-emerald-50 px-3.5 py-1.5 rounded-full border border-emerald-100 mb-4 inline-block">
                    Ekosistem Pendidikan Digital
                </span>
                <h1 class="text-3xl sm:text-5xl font-extrabold text-slate-900 tracking-tight mt-2 mb-4 leading-tight">
                    Selamat Datang di Portal Terpadu Pintera
                </h1>
                <p class="text-base sm:text-lg text-slate-600 leading-relaxed max-w-2xl mx-auto">
                    Pilih portal sesuai dengan hak akses dan kewenangan Anda dalam ekosistem sekolah untuk mulai beraktivitas.
                </p>
            </div>

            {{-- 4 Portal Cards Grid (Clean White High-Contrast UI) --}}
            <section aria-label="Daftar Portal Layanan" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 w-full">
                
                {{-- Card 1: Portal Yayasan --}}
                <div class="bg-white rounded-2xl p-7 border border-slate-200 shadow-sm hover:shadow-md hover:border-emerald-300 transition-all duration-200 flex flex-col justify-between group">
                    <div>
                        <div class="w-14 h-14 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 flex items-center justify-center mb-6 group-hover:bg-emerald-600 group-hover:text-white transition-colors duration-200">
                            {{-- Building Columns Icon --}}
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-slate-900 mb-2.5">
                            Portal Yayasan
                        </h2>
                        <p class="text-sm text-slate-600 leading-relaxed mb-8">
                            Monitoring kinerja, evaluasi KPI, &amp; ringkasan finansial lembaga.
                        </p>
                    </div>
                    <a
                        href="{{ route('login') }}"
                        aria-label="Masuk ke Portal Yayasan"
                        class="w-full inline-flex items-center justify-center min-h-[44px] gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold bg-emerald-600 text-white hover:bg-emerald-700 transition shadow-sm shadow-emerald-600/10"
                    >
                        <span>Masuk Yayasan</span>
                        <span>&rarr;</span>
                    </a>
                </div>

                {{-- Card 2: Portal Admin --}}
                <div class="bg-white rounded-2xl p-7 border border-slate-200 shadow-sm hover:shadow-md hover:border-blue-300 transition-all duration-200 flex flex-col justify-between group">
                    <div>
                        <div class="w-14 h-14 rounded-xl bg-blue-50 text-blue-600 border border-blue-100 flex items-center justify-center mb-6 group-hover:bg-blue-600 group-hover:text-white transition-colors duration-200">
                            {{-- Gear / Administration Icon --}}
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-slate-900 mb-2.5">
                            Portal Admin
                        </h2>
                        <p class="text-sm text-slate-600 leading-relaxed mb-8">
                            Manajemen data sekolah, kepegawaian, tagihan &amp; konfigurasi SPMB.
                        </p>
                    </div>
                    <a
                        href="{{ route('login') }}"
                        aria-label="Masuk ke Portal Admin dan Tata Usaha"
                        class="w-full inline-flex items-center justify-center min-h-[44px] gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold bg-blue-600 text-white hover:bg-blue-700 transition shadow-sm shadow-blue-600/10"
                    >
                        <span>Masuk Admin</span>
                        <span>&rarr;</span>
                    </a>
                </div>

                {{-- Card 3: Portal Guru --}}
                <div class="bg-white rounded-2xl p-7 border border-slate-200 shadow-sm hover:shadow-md hover:border-amber-300 transition-all duration-200 flex flex-col justify-between group">
                    <div>
                        <div class="w-14 h-14 rounded-xl bg-amber-50 text-amber-600 border border-amber-100 flex items-center justify-center mb-6 group-hover:bg-amber-500 group-hover:text-white transition-colors duration-200">
                            {{-- Educator / Academic Icon --}}
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-slate-900 mb-2.5">
                            Portal Guru
                        </h2>
                        <p class="text-sm text-slate-600 leading-relaxed mb-8">
                            Pengisian presensi biometrik, penyusunan RPP, &amp; pencatatan nilai.
                        </p>
                    </div>
                    <a
                        href="{{ route('login') }}"
                        aria-label="Masuk ke Portal Guru dan Tenaga Didik"
                        class="w-full inline-flex items-center justify-center min-h-[44px] gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold bg-amber-500 text-white hover:bg-amber-600 transition shadow-sm shadow-amber-500/10"
                    >
                        <span>Masuk Guru</span>
                        <span>&rarr;</span>
                    </a>
                </div>

                {{-- Card 4: Portal Siswa --}}
                <div class="bg-white rounded-2xl p-7 border border-slate-200 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all duration-200 flex flex-col justify-between group">
                    <div>
                        <div class="w-14 h-14 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-100 flex items-center justify-center mb-6 group-hover:bg-indigo-600 group-hover:text-white transition-colors duration-200">
                            {{-- Graduation Cap Icon --}}
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5zm0 0v6" />
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-slate-900 mb-2.5">
                            Portal Siswa
                        </h2>
                        <p class="text-sm text-slate-600 leading-relaxed mb-8">
                            Akses kalender akademik, lihat tagihan sekolah, &amp; e-rapor.
                        </p>
                    </div>
                    <a
                        href="{{ route('login') }}"
                        aria-label="Masuk ke Portal Siswa dan Wali Murid"
                        class="w-full inline-flex items-center justify-center min-h-[44px] gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold bg-indigo-600 text-white hover:bg-indigo-700 transition shadow-sm shadow-indigo-600/10"
                    >
                        <span>Masuk Siswa</span>
                        <span>&rarr;</span>
                    </a>
                </div>

            </section>
        </main>

        {{-- Clean Minimalist Footer --}}
        <footer class="w-full bg-white border-t border-slate-200/80 py-8">
            <div class="max-w-7xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 gap-4">
                <div>
                    &copy; {{ date('Y') }} <strong class="text-slate-800 font-semibold">Pintera App</strong>. Hak cipta dilindungi undang-undang.
                </div>
                <div class="flex items-center gap-4 text-slate-600">
                    <span>Sistem Manajemen Ekosistem Terpadu</span>
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    <span>v1.0-Demo</span>
                </div>
            </div>
        </footer>
    </body>
</html>
