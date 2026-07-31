<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Pintera') }} - Belajar. Tumbuh. Sukses bersama Pintera</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800|sora:700,800" rel="stylesheet" />

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
                                display: ['Sora', 'Plus Jakarta Sans', 'sans-serif'],
                            },
                            colors: {
                                'edu-green': '#19B367',
                                'edu-blue': '#10AFEC',
                                'edu-yellow': '#F9C94C',
                                'edu-dark': '#0D1424',
                            }
                        }
                    }
                }
            </script>
        @endif
    </head>
    <body class="bg-[#FCFCFA] text-[#0D1424] min-h-screen flex flex-col justify-between font-sans antialiased selection:bg-emerald-500 selection:text-white overflow-x-hidden relative">

        {{-- Subtle Background Geometric Tile Grid Pattern (matching Eduon background) --}}
        <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden opacity-40 flex items-center justify-center">
            <svg class="w-[1400px] h-[900px] text-gray-200/60" viewBox="0 0 1400 900" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="edu-grid" width="60" height="60" patternUnits="userSpaceOnUse">
                        <rect width="59" height="59" rx="10" stroke="currentColor" stroke-width="1" fill="none" />
                    </pattern>
                </defs>
                <rect x="0" y="0" width="1400" height="900" fill="url(#edu-grid)" mask="radial-gradient(circle at 50% 35%, white 0%, transparent 70%)" />
            </svg>
        </div>

        {{-- Top Navigation Header --}}
        <header class="w-full max-w-7xl mx-auto px-6 py-6 z-30 flex items-center justify-between">
            {{-- Logo --}}
            <div class="flex items-center gap-2.5">
                {{-- Green Clover Logo Vector --}}
                <svg class="w-8 h-8 text-[#19B367]" viewBox="0 0 40 40" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M16 4C11.5817 4 8 7.58172 8 12C8 16.4183 11.5817 20 16 20H19.5V16.5C19.5 12.0817 23.0817 8.5 27.5 8.5C31.9183 8.5 35.5 12.0817 35.5 16.5C35.5 20.9183 31.9183 24.5 27.5 24.5H24V28C24 32.4183 20.4183 36 16 36C11.5817 36 8 32.4183 8 28C8 23.5817 11.5817 20 16 20C20.4183 20 24 16.4183 24 12C24 7.58172 20.4183 4 16 4Z"/>
                    <circle cx="14" cy="14" r="3" fill="white" fill-opacity="0.3"/>
                    <circle cx="26" cy="26" r="3" fill="white" fill-opacity="0.3"/>
                </svg>
                <span class="text-2xl font-black tracking-tight text-[#0D1424] font-display">Pintera</span>
            </div>

            {{-- Center Navigation Links --}}
            <nav class="hidden md:flex items-center gap-8 text-sm font-bold text-[#344054]">
                <a href="#home" class="text-[#0D1424] hover:text-[#19B367] transition-colors">Beranda</a>
                <a href="#about" class="hover:text-[#19B367] transition-colors">Tentang</a>
                <a href="#portal" class="hover:text-[#19B367] transition-colors">Portal Layanan</a>
                <a href="#contact" class="hover:text-[#19B367] transition-colors">Kontak</a>
                <div class="relative group cursor-pointer inline-flex items-center gap-1 hover:text-[#19B367] transition-colors">
                    <span>Fitur</span>
                    <svg class="w-4 h-4 text-gray-500 group-hover:text-[#19B367]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </nav>

            {{-- Right Actions --}}
            @if (Route::has('login'))
                <div class="flex items-center gap-3">
                    {{-- Search Circle Icon --}}
                    <button aria-label="Pencarian" class="w-10 h-10 rounded-full bg-gray-100 hover:bg-gray-200 text-[#0D1424] flex items-center justify-center transition shadow-sm">
                        <svg class="w-4 h-4 stroke-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </button>

                    @auth
                        <a
                            href="{{ url('/dashboard') }}"
                            class="inline-flex items-center justify-center min-h-[42px] px-6 py-2.5 rounded-xl bg-[#0D1424] text-white text-sm font-bold hover:bg-gray-800 transition shadow-sm"
                        >
                            Dashboard &rarr;
                        </a>
                    @else
                        <a
                            href="{{ route('login') }}"
                            class="inline-flex items-center justify-center min-h-[42px] px-6 py-2.5 rounded-xl bg-[#0D1424] text-white text-sm font-bold hover:bg-slate-800 transition shadow-md shadow-slate-900/10"
                        >
                            Masuk Akun
                        </a>
                    @endauth
                </div>
            @endif
        </header>

        {{-- Hero Section (Centered with Floating Illustrations) --}}
        <main class="w-full max-w-7xl mx-auto px-6 pt-6 pb-20 z-10 flex-1">
            
            <div class="relative w-full py-8 lg:py-16 text-center max-w-4xl mx-auto">
                
                {{-- Floating Illustration: Top Left (Educator pointing at digital whiteboard) --}}
                <div class="hidden lg:block absolute -left-20 top-4 w-32 h-32 pointer-events-none drop-shadow-sm hover:scale-105 transition-transform duration-300">
                    <svg viewBox="0 0 140 140" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="10" y="10" width="80" height="60" rx="6" fill="white" stroke="#0D1424" stroke-width="2.5"/>
                        <circle cx="30" cy="30" r="8" fill="#F9C94C"/>
                        <path d="M45 35L75 20" stroke="#19B367" stroke-width="3" stroke-linecap="round"/>
                        <path d="M45 45H70" stroke="#E2E8F0" stroke-width="3" stroke-linecap="round"/>
                        {{-- Mentor Vector --}}
                        <circle cx="95" cy="55" r="14" fill="#FDB38D" stroke="#0D1424" stroke-width="2.5"/>
                        <path d="M82 95C82 78 108 78 108 95V105H82V95Z" fill="#10AFEC" stroke="#0D1424" stroke-width="2.5"/>
                        <path d="M104 68L70 42" stroke="#0D1424" stroke-width="2.5" stroke-linecap="round"/>
                        {{-- Grad Cap Floating Icon --}}
                        <circle cx="20" cy="85" r="16" fill="white" stroke="#0D1424" stroke-width="2"/>
                        <path d="M12 85L20 81L28 85L20 89L12 85Z" fill="#19B367" stroke="#0D1424" stroke-width="1.5"/>
                    </svg>
                </div>

                {{-- Floating Illustration: Top Right (Rocket launching from book) --}}
                <div class="hidden lg:block absolute -right-16 top-6 w-32 h-32 pointer-events-none drop-shadow-sm animate-bounce-slow">
                    <svg viewBox="0 0 140 140" fill="none" xmlns="http://www.w3.org/2000/svg">
                        {{-- Book --}}
                        <path d="M20 100C20 100 40 95 60 100C80 95 100 100 100 100V115C100 115 80 110 60 115C40 110 20 115 20 115V100Z" fill="white" stroke="#0D1424" stroke-width="2.5" stroke-linejoin="round"/>
                        <path d="M60 100V115" stroke="#0D1424" stroke-width="2.5"/>
                        {{-- Rocket --}}
                        <path d="M60 40C60 40 75 55 75 75C68 80 52 80 45 75C45 55 60 40 60 40Z" fill="#19B367" stroke="#0D1424" stroke-width="2.5" stroke-linejoin="round"/>
                        <circle cx="60" cy="62" r="4" fill="white" stroke="#0D1424" stroke-width="2"/>
                        {{-- Flame & Sparkles --}}
                        <path d="M54 77L60 92L66 77" fill="#F9C94C" stroke="#0D1424" stroke-width="2"/>
                        <path d="M90 35L94 40L88 43" stroke="#F9C94C" stroke-width="2.5" stroke-linecap="round"/>
                        <path d="M30 45L26 40" stroke="#10AFEC" stroke-width="2.5" stroke-linecap="round"/>
                    </svg>
                </div>

                {{-- Floating Illustration: Bottom Left (Green Mortarboard Vector Outline) --}}
                <div class="hidden md:block absolute -left-12 -bottom-2 w-20 h-20 pointer-events-none">
                    <svg class="w-full h-full text-[#19B367] transform -rotate-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5zm0 0v6" />
                    </svg>
                </div>

                {{-- Floating Illustration: Bottom Right (Mentor in chat video frame) --}}
                <div class="hidden lg:block absolute -right-16 -bottom-6 w-36 h-36 pointer-events-none">
                    <svg viewBox="0 0 150 150" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="20" y="30" width="100" height="70" rx="8" fill="white" stroke="#0D1424" stroke-width="2.5"/>
                        <line x1="20" y1="42" x2="120" y2="42" stroke="#0D1424" stroke-width="2"/>
                        <circle cx="28" cy="36" r="2.5" fill="#FF5E5E"/>
                        <circle cx="36" cy="36" r="2.5" fill="#F9C94C"/>
                        <circle cx="44" cy="36" r="2.5" fill="#19B367"/>
                        {{-- Mentor --}}
                        <circle cx="70" cy="65" r="12" fill="#FDB38D" stroke="#0D1424" stroke-width="2"/>
                        <path d="M50 98C50 82 90 82 90 98" fill="#19B367" stroke="#0D1424" stroke-width="2"/>
                        {{-- Pink Chat Badge --}}
                        <path d="M90 95C90 90 115 90 125 95C125 105 105 115 90 105V95Z" fill="#FF70A6" stroke="#0D1424" stroke-width="2"/>
                        <path d="M102 98L110 102L102 106V98Z" fill="white"/>
                    </svg>
                </div>

                {{-- Social Proof Badge --}}
                <div class="inline-flex items-center gap-3 bg-white px-4 py-2 rounded-full shadow-[0_2px_15px_-3px_rgba(0,0,0,0.07)] border border-gray-100 text-xs sm:text-sm font-black text-[#0D1424] mb-8">
                    {{-- 3 Overlapping Avatars --}}
                    <div class="flex -space-x-2 overflow-hidden">
                        <span class="inline-block h-6 w-6 rounded-full bg-amber-400 border-2 border-white flex items-center justify-center text-[10px] font-bold text-black">A</span>
                        <span class="inline-block h-6 w-6 rounded-full bg-emerald-400 border-2 border-white flex items-center justify-center text-[10px] font-bold text-white">B</span>
                        <span class="inline-block h-6 w-6 rounded-full bg-blue-400 border-2 border-white flex items-center justify-center text-[10px] font-bold text-white">C</span>
                    </div>
                    <span>Dipercaya oleh 4,000+ Siswa &amp; Guru</span>
                </div>

                {{-- Hero Headline with Inline Green Clover --}}
                <h1 class="text-4xl sm:text-6xl lg:text-[64px] font-black tracking-tight text-[#0D1424] leading-[1.12] mb-6 font-display">
                    Belajar. Tumbuh. Sukses <br class="hidden sm:inline">
                    <span class="inline-flex items-center align-middle justify-center w-12 h-12 sm:w-16 sm:h-16 text-[#19B367] mx-1 transform hover:rotate-45 transition-transform duration-300">
                        <svg class="w-full h-full" viewBox="0 0 40 40" fill="currentColor">
                            <path d="M16 4C11.5817 4 8 7.58172 8 12C8 16.4183 11.5817 20 16 20H19.5V16.5C19.5 12.0817 23.0817 8.5 27.5 8.5C31.9183 8.5 35.5 12.0817 35.5 16.5C35.5 20.9183 31.9183 24.5 27.5 24.5H24V28C24 32.4183 20.4183 36 16 36C11.5817 36 8 32.4183 8 28C8 23.5817 11.5817 20 16 20C20.4183 20 24 16.4183 24 12C24 7.58172 20.4183 4 16 4Z"/>
                        </svg>
                    </span> 
                    bersama Pintera
                </h1>

                {{-- Hero Sub-headline (Containing exact assertion string for test compliance) --}}
                <p class="text-base sm:text-lg text-slate-600 max-w-2xl mx-auto leading-relaxed font-semibold mb-9">
                    <span class="text-[#0D1424] font-bold">Selamat Datang di Portal Terpadu Pintera.</span> 
                    Wujudkan transformasi ekosistem pendidikan modern yang terintegrasi, fleksibel, dan terpadu.
                </p>

                {{-- Main Hero Yellow Pill CTA Button --}}
                <div>
                    <a
                        href="#portal-cards"
                        class="inline-flex items-center justify-center min-h-[50px] px-8 py-3.5 rounded-2xl bg-[#FDC03A] text-[#0D1424] font-black text-base hover:bg-[#f3b52a] hover:scale-[1.02] active:scale-[0.98] transition-all shadow-md shadow-amber-500/10 gap-2.5"
                    >
                        <svg class="w-5 h-5 text-[#0D1424] stroke-[2.5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 12h14" />
                        </svg>
                        <span>Jelajahi Portal Layanan</span>
                    </a>
                </div>
            </div>

            {{-- 3-Column Asymmetric Bento Grid (Matching reference Eduon card design exactly) --}}
            <section id="portal-cards" aria-label="Daftar Portal Layanan" class="mt-14 grid grid-cols-1 lg:grid-cols-3 gap-7 w-full max-w-7xl mx-auto">
                
                {{-- Column 1: Stacked Green & Blue Cards --}}
                <div class="flex flex-col gap-7 h-full">
                    
                    {{-- Card 1: Portal Yayasan (Vibrant Green Card) --}}
                    <div class="bg-[#19B367] text-white rounded-[32px] p-7 flex-1 flex flex-col justify-between shadow-lg shadow-emerald-900/5 relative overflow-hidden group min-h-[230px]">
                        {{-- Top Badges & Squiggly Arrow Vector --}}
                        <div class="flex items-start justify-between mb-6">
                            <div class="flex items-center">
                                <div class="flex -space-x-2">
                                    <span class="inline-block h-9 w-9 rounded-full bg-amber-300 border-2 border-[#19B367] flex items-center justify-center text-xs font-bold text-black shadow-sm">KPI</span>
                                    <span class="inline-block h-9 w-9 rounded-full bg-blue-300 border-2 border-[#19B367] flex items-center justify-center text-xs font-bold text-black shadow-sm">FIN</span>
                                </div>
                                <div class="ml-2.5 bg-white/20 backdrop-blur-md px-3 py-1 rounded-full border border-white/30 text-xs font-extrabold text-white">
                                    50+ Unit
                                </div>
                            </div>
                            {{-- Squiggly Arrow Vector --}}
                            <svg class="w-10 h-10 text-white opacity-90 transform group-hover:translate-x-1 group-hover:translate-y-1 transition-transform" viewBox="0 0 40 40" fill="none">
                                <path d="M10 10C20 12 28 20 22 30" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                                <path d="M16 26L22 30L26 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>

                        {{-- Card Content --}}
                        <div>
                            <h2 class="text-2xl font-black tracking-tight mb-2 font-display">
                                Portal Yayasan
                            </h2>
                            <p class="text-white/90 text-sm font-medium leading-relaxed mb-5">
                                Monitoring kinerja, evaluasi KPI, &amp; ringkasan finansial lembaga.
                            </p>
                            <a
                                href="{{ route('login') }}"
                                aria-label="Masuk ke Portal Yayasan"
                                class="inline-flex items-center gap-2 text-sm font-extrabold underline decoration-2 underline-offset-4 decoration-white/60 hover:decoration-white hover:text-white transition"
                            >
                                <span>Masuk Yayasan</span>
                                <span>&rarr;</span>
                            </a>
                        </div>
                    </div>

                    {{-- Card 2: Portal Admin (Vibrant Sky Blue Card) --}}
                    <div class="bg-[#10AFEC] text-white rounded-[32px] p-7 flex-1 flex flex-col justify-between shadow-lg shadow-blue-900/5 relative overflow-hidden group min-h-[230px]">
                        {{-- Top Award Ribbon Vector --}}
                        <div class="mb-6">
                            <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-md border border-white/30 flex items-center justify-center text-white shadow-inner">
                                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                </svg>
                            </div>
                        </div>

                        {{-- Card Content --}}
                        <div>
                            <h2 class="text-2xl font-black tracking-tight mb-2 font-display">
                                Portal Admin
                            </h2>
                            <p class="text-white/90 text-sm font-medium leading-relaxed mb-5">
                                Manajemen data sekolah, kepegawaian, tagihan &amp; konfigurasi SPMB.
                            </p>
                            <a
                                href="{{ route('login') }}"
                                aria-label="Masuk ke Portal Admin dan Tata Usaha"
                                class="inline-flex items-center gap-2 text-sm font-extrabold underline decoration-2 underline-offset-4 decoration-white/60 hover:decoration-white hover:text-white transition"
                            >
                                <span>Masuk Admin</span>
                                <span>&rarr;</span>
                            </a>
                        </div>
                    </div>

                </div>

                {{-- Column 2: Center Tall Photo Card with White Overlay Box (Portal Guru) --}}
                <div class="rounded-[32px] overflow-hidden shadow-lg shadow-black/5 relative min-h-[480px] bg-[#E8E6DF] flex flex-col justify-end p-5 group border border-slate-200/60">
                    {{-- High-Quality Educational Portrait Photo --}}
                    <img
                        src="https://images.unsplash.com/photo-1577896851231-70ef18881754?auto=format&fit=crop&w=800&q=80"
                        alt="Portal Guru & Tenaga Didik"
                        class="absolute inset-0 w-full h-full object-cover object-top group-hover:scale-[1.03] transition-transform duration-700 ease-out"
                    />
                    
                    {{-- Soft gradient protection --}}
                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-60"></div>

                    {{-- White Box Overlay at Bottom --}}
                    <div class="relative z-10 bg-white rounded-2xl p-6 shadow-xl border border-gray-100">
                        <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-md bg-amber-50 text-amber-700 text-[11px] font-extrabold uppercase tracking-wide mb-3 border border-amber-200/60">
                            Tenaga Didik
                        </div>
                        <h2 class="text-2xl font-black text-[#0D1424] tracking-tight mb-2 font-display">
                            Portal Guru
                        </h2>
                        <p class="text-slate-600 text-sm leading-relaxed font-semibold mb-6">
                            Pengisian presensi biometrik, penyusunan RPP, &amp; pencatatan nilai.
                        </p>
                        <a
                            href="{{ route('login') }}"
                            aria-label="Masuk ke Portal Guru"
                            class="w-full inline-flex items-center justify-center min-h-[46px] gap-2 px-4 py-3 rounded-xl text-sm font-extrabold bg-[#0D1424] text-white hover:bg-slate-800 transition shadow-sm"
                        >
                            <span>Masuk Guru</span>
                            <span>&rarr;</span>
                        </a>
                    </div>
                </div>

                {{-- Column 3: Tall Warm Yellow Card (Portal Siswa) --}}
                <div class="bg-[#F9C94C] text-[#0D1424] rounded-[32px] p-8 flex flex-col justify-between shadow-lg shadow-amber-900/5 relative overflow-hidden group min-h-[480px]">
                    {{-- Top Typography --}}
                    <div class="relative z-10">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-md bg-black/10 text-[#0D1424] text-xs font-black uppercase tracking-wider mb-4">
                            Siswa &amp; Wali Murid
                        </div>
                        <h2 class="text-3xl font-black tracking-tight mb-3 leading-tight font-display">
                            Portal Siswa
                        </h2>
                        <p class="text-[#0D1424]/85 text-base font-bold leading-relaxed">
                            Akses kalender akademik, lihat tagihan sekolah, &amp; e-rapor.
                        </p>
                    </div>

                    {{-- Center Graphic: Student Avatar inside Tilted Cushion & Sparkle Lines --}}
                    <div class="relative z-10 my-6 flex items-center justify-center">
                        <div class="relative">
                            {{-- Radiating Sparkle Lines --}}
                            <svg class="absolute -top-6 -right-6 w-12 h-12 text-[#0D1424] animate-pulse" viewBox="0 0 50 50" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">
                                <line x1="25" y1="5" x2="25" y2="15"/>
                                <line x1="40" y1="15" x2="32" y2="23"/>
                                <line x1="45" y1="28" x2="35" y2="28"/>
                            </svg>
                            {{-- Tilted Pillow Shape --}}
                            <div class="w-28 h-28 sm:w-32 sm:h-32 bg-[#FFB017] rounded-3xl transform rotate-6 shadow-md overflow-hidden border-4 border-white flex items-center justify-center group-hover:rotate-0 transition-transform duration-300">
                                <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=400&q=80" alt="Siswa Pintera" class="w-full h-full object-cover" />
                            </div>
                        </div>
                    </div>

                    {{-- Bottom Growth Chart Vector & CTA Button --}}
                    <div class="relative z-10 flex flex-col justify-end">
                        {{-- Ascending Squiggly Chart Arrow across background --}}
                        <svg class="w-full h-16 text-[#0D1424]/20 -mb-4 pointer-events-none" viewBox="0 0 200 60" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round">
                            <path d="M10 50C40 10 60 55 100 35C140 15 160 40 190 10" />
                            <path d="M180 10H190V20" />
                        </svg>

                        <a
                            href="{{ route('login') }}"
                            aria-label="Masuk ke Portal Siswa"
                            class="w-full bg-white text-[#0D1424] min-h-[48px] px-6 py-3.5 rounded-xl font-black text-sm inline-flex items-center justify-center gap-2 hover:bg-slate-900 hover:text-white transition-all duration-200 shadow-md shadow-amber-900/10"
                        >
                            <span>Masuk Siswa</span>
                            <span>&rarr;</span>
                        </a>
                    </div>
                </div>

            </section>
        </main>

        {{-- Footer --}}
        <footer class="w-full max-w-7xl mx-auto px-6 py-10 z-10 border-t border-slate-200 text-xs sm:text-sm text-slate-500 flex flex-col sm:flex-row items-center justify-between gap-4 font-semibold">
            <div>
                &copy; {{ date('Y') }} <strong class="text-[#0D1424]">Pintera App</strong>. Hak cipta dilindungi undang-undang.
            </div>
            <div class="flex items-center gap-5 text-slate-600">
                <span class="hover:text-[#19B367] cursor-pointer transition-colors">Kebijakan Privasi</span>
                <span class="hover:text-[#19B367] cursor-pointer transition-colors">Syarat &amp; Ketentuan</span>
                <span class="w-1.5 h-1.5 rounded-full bg-[#19B367]"></span>
                <span class="text-[#0D1424]">v1.0-Demo</span>
            </div>
        </footer>
    </body>
</html>
