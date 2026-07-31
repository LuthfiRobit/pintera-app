<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Pintera') }} - Portal Terpadu Sistem Administrasi Yayasan & Sekolah</title>

        <!-- Fonts: Outfit (Official Pintera Token Font) -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700,800,900" rel="stylesheet" />

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
                                sans: ['Outfit', 'sans-serif'],
                                display: ['Outfit', 'sans-serif'],
                            },
                            colors: {
                                brand: {
                                    50: '#ECF3FF',
                                    100: '#DDE9FF',
                                    300: '#9CB9FF',
                                    500: '#465FFF',
                                    600: '#3641F5',
                                },
                                portal: {
                                    50: '#E7EEF5',
                                    500: '#1E3A5F',
                                    600: '#16324F',
                                },
                                gray: {
                                    50: '#F9FAFB',
                                    100: '#F2F4F7',
                                    200: '#E4E7EC',
                                    300: '#D0D5DD',
                                    400: '#98A2B3',
                                    500: '#667085',
                                    600: '#475467',
                                    700: '#344054',
                                    800: '#1D2939',
                                    900: '#101828',
                                },
                                success: {
                                    50: '#ECFDF3',
                                    500: '#12B76A',
                                    600: '#039855',
                                },
                                warning: {
                                    50: '#FFFAEB',
                                    500: '#F79009',
                                    600: '#DC6803',
                                },
                                ink: '#0F2547',
                                brass: '#C9A227',
                            }
                        }
                    }
                }
            </script>
        @endif
    </head>
    <body class="bg-[#FCFDFE] bg-[radial-gradient(circle_at_85%_15%,#E7EEF5_0%,transparent_60%)] text-gray-900 min-h-screen flex flex-col justify-between font-sans antialiased selection:bg-portal-500 selection:text-white overflow-x-hidden relative">

        {{-- Subtle Background Geometric Grid Pattern --}}
        <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden opacity-30 flex items-center justify-center">
            <svg class="w-[1400px] h-[900px] text-gray-300" viewBox="0 0 1400 900" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="pintera-grid" width="60" height="60" patternUnits="userSpaceOnUse">
                        <rect width="59" height="59" rx="8" stroke="currentColor" stroke-width="1" fill="none" />
                    </pattern>
                </defs>
                <rect x="0" y="0" width="1400" height="900" fill="url(#pintera-grid)" mask="radial-gradient(circle at 50% 30%, white 0%, transparent 75%)" />
            </svg>
        </div>

        {{-- Top Navigation Header (Aligned with SPMB & Portal style) --}}
        <header class="w-full max-w-7xl mx-auto px-6 py-6 z-30 flex items-center justify-between border-b border-gray-200/80 bg-white/70 backdrop-blur-md sticky top-0 sm:static sm:bg-transparent sm:border-transparent sm:backdrop-blur-none">
            {{-- Logo --}}
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[linear-gradient(160deg,#16324F_0%,#1E3A5F_65%)] text-white flex items-center justify-center shadow-md shadow-portal-500/20">
                    <svg class="w-6 h-6 text-emerald-400" viewBox="0 0 40 40" fill="currentColor">
                        <path d="M16 4C11.5817 4 8 7.58172 8 12C8 16.4183 11.5817 20 16 20H19.5V16.5C19.5 12.0817 23.0817 8.5 27.5 8.5C31.9183 8.5 35.5 12.0817 35.5 16.5C35.5 20.9183 31.9183 24.5 27.5 24.5H24V28C24 32.4183 20.4183 36 16 36C11.5817 36 8 32.4183 8 28C8 23.5817 11.5817 20 16 20C20.4183 20 24 16.4183 24 12C24 7.58172 20.4183 4 16 4Z"/>
                    </svg>
                </div>
                <div>
                    <span class="text-2xl font-extrabold tracking-tight text-portal-600 font-display block leading-none">Pintera</span>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Portal Terpadu</span>
                </div>
            </div>

            {{-- Center Navigation Links --}}
            <nav class="hidden lg:flex items-center gap-8 text-[14px] font-bold text-gray-600">
                <a href="#beranda" class="text-portal-500 hover:text-portal-600 font-extrabold transition-colors">Beranda</a>
                <a href="{{ route('spmb.welcome') }}" class="hover:text-portal-500 transition-colors">SPMB Online</a>
                <a href="#portal-cards" class="hover:text-portal-500 transition-colors">Portal Layanan</a>
                <a href="#fitur" class="hover:text-portal-500 transition-colors">Keunggulan</a>
                <a href="#bantuan" class="hover:text-portal-500 transition-colors">Bantuan</a>
            </nav>

            {{-- Right Actions --}}
            @if (Route::has('login'))
                <div class="flex items-center gap-3">
                    <a
                        href="{{ route('spmb.welcome') }}"
                        class="hidden sm:inline-flex items-center justify-center px-4 py-2.5 rounded-xl border border-gray-300 bg-white text-gray-700 text-sm font-bold hover:bg-gray-50 transition shadow-sm"
                    >
                        Pendaftaran SPMB
                    </a>

                    @auth
                        <a
                            href="{{ url('/dashboard') }}"
                            class="inline-flex items-center justify-center min-h-[42px] px-6 py-2.5 rounded-xl bg-[linear-gradient(160deg,#16324F_0%,#1E3A5F_65%)] text-white text-sm font-bold hover:opacity-95 transition shadow-md shadow-portal-500/20"
                        >
                            Dashboard &rarr;
                        </a>
                    @else
                        <a
                            href="{{ route('login') }}"
                            class="inline-flex items-center justify-center min-h-[42px] px-6 py-2.5 rounded-xl bg-[linear-gradient(160deg,#16324F_0%,#1E3A5F_65%)] text-white text-sm font-bold hover:bg-portal-600 transition shadow-md shadow-portal-500/20"
                        >
                            Masuk Akun
                        </a>
                    @endauth
                </div>
            @endif
        </header>

        {{-- Hero Section (With Floating Vectors & Harmonized Pintera Palette) --}}
        <main id="beranda" class="w-full max-w-7xl mx-auto px-6 pt-6 pb-20 z-10 flex-1">
            
            <div class="relative w-full py-10 lg:py-16 text-center max-w-4xl mx-auto">
                
                {{-- Floating Illustration: Top Left (Educator Board Vector) --}}
                <div class="hidden lg:block absolute -left-24 top-6 w-32 h-32 pointer-events-none drop-shadow-sm hover:scale-105 transition-transform duration-300">
                    <svg viewBox="0 0 140 140" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="12" y="12" width="78" height="58" rx="8" fill="white" stroke="#1E3A5F" stroke-width="2.5"/>
                        <circle cx="32" cy="30" r="7" fill="#F79009"/>
                        <path d="M45 35L72 20" stroke="#12B76A" stroke-width="3" stroke-linecap="round"/>
                        <path d="M45 45H68" stroke="#D0D5DD" stroke-width="3" stroke-linecap="round"/>
                        {{-- Mentor --}}
                        <circle cx="95" cy="58" r="13" fill="#FEDF89" stroke="#1E3A5F" stroke-width="2.5"/>
                        <path d="M80 98C80 80 110 80 110 98V108H80V98Z" fill="#465FFF" stroke="#1E3A5F" stroke-width="2.5"/>
                        <path d="M104 70L70 45" stroke="#1E3A5F" stroke-width="2.5" stroke-linecap="round"/>
                        <circle cx="20" cy="85" r="16" fill="white" stroke="#1E3A5F" stroke-width="2"/>
                        <path d="M12 85L20 81L28 85L20 89L12 85Z" fill="#12B76A" stroke="#1E3A5F" stroke-width="1.5"/>
                    </svg>
                </div>

                {{-- Floating Illustration: Top Right (Rocket from Book) --}}
                <div class="hidden lg:block absolute -right-20 top-8 w-32 h-32 pointer-events-none drop-shadow-sm">
                    <svg viewBox="0 0 140 140" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M22 100C22 100 42 95 62 100C82 95 102 100 102 100V115C102 115 82 110 62 115C42 110 22 115 22 115V100Z" fill="white" stroke="#1E3A5F" stroke-width="2.5" stroke-linejoin="round"/>
                        <path d="M62 100V115" stroke="#1E3A5F" stroke-width="2.5"/>
                        <path d="M62 38C62 38 78 53 78 75C71 80 53 80 46 75C46 53 62 38 62 38Z" fill="#12B76A" stroke="#1E3A5F" stroke-width="2.5" stroke-linejoin="round"/>
                        <circle cx="62" cy="62" r="4.5" fill="white" stroke="#1E3A5F" stroke-width="2"/>
                        <path d="M55 77L62 94L69 77" fill="#F79009" stroke="#1E3A5F" stroke-width="2"/>
                        <path d="M90 35L95 40L89 44" stroke="#F79009" stroke-width="2.5" stroke-linecap="round"/>
                        <path d="M32 45L27 40" stroke="#465FFF" stroke-width="2.5" stroke-linecap="round"/>
                    </svg>
                </div>

                {{-- Floating Illustration: Bottom Left (Emerald Mortarboard) --}}
                <div class="hidden md:block absolute -left-12 -bottom-2 w-20 h-20 pointer-events-none">
                    <svg class="w-full h-full text-success-500 transform -rotate-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5zm0 0v6" />
                    </svg>
                </div>

                {{-- Floating Illustration: Bottom Right (Mentor Video Frame) --}}
                <div class="hidden lg:block absolute -right-20 -bottom-6 w-36 h-36 pointer-events-none">
                    <svg viewBox="0 0 150 150" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="22" y="32" width="100" height="70" rx="8" fill="white" stroke="#1E3A5F" stroke-width="2.5"/>
                        <line x1="22" y1="44" x2="122" y2="44" stroke="#1E3A5F" stroke-width="2"/>
                        <circle cx="30" cy="38" r="2.5" fill="#F04438"/>
                        <circle cx="38" cy="38" r="2.5" fill="#F79009"/>
                        <circle cx="46" cy="38" r="2.5" fill="#12B76A"/>
                        <circle cx="72" cy="67" r="12" fill="#FEDF89" stroke="#1E3A5F" stroke-width="2"/>
                        <path d="M52 100C52 83 92 83 92 100" fill="#465FFF" stroke="#1E3A5F" stroke-width="2"/>
                        <path d="M92 95C92 90 117 90 127 95C127 105 107 115 92 105V95Z" fill="#C9A227" stroke="#1E3A5F" stroke-width="2"/>
                        <path d="M104 98L112 102L104 106V98Z" fill="white"/>
                    </svg>
                </div>

                {{-- Social Proof / Status Badge (Matching SPMB Header Pil) --}}
                <div class="inline-flex items-center gap-3 bg-portal-50 px-4 py-2 rounded-full border border-portal-500/20 text-xs sm:text-sm font-extrabold text-portal-600 mb-8 shadow-sm">
                    <span class="h-2 w-2 rounded-full bg-success-500 animate-ping"></span>
                    <div class="flex -space-x-1.5 overflow-hidden">
                        <span class="inline-block h-5 w-5 rounded-full bg-success-500 text-white flex items-center justify-center text-[9px] font-bold">✓</span>
                        <span class="inline-block h-5 w-5 rounded-full bg-brand-500 text-white flex items-center justify-center text-[9px] font-bold">✓</span>
                    </div>
                    <span>Ekosistem Terpadu Yayasan &amp; Lembaga Pendidikan</span>
                </div>

                {{-- Hero Headline with Inline Green Clover --}}
                <h1 class="text-4xl sm:text-6xl lg:text-[62px] font-black tracking-tight text-gray-900 leading-[1.14] mb-6 font-display">
                    Belajar. Tumbuh. Sukses <br class="hidden sm:inline">
                    <span class="inline-flex items-center align-middle justify-center w-11 h-11 sm:w-14 sm:h-14 text-success-500 mx-1 transform -rotate-6 hover:rotate-12 transition-transform duration-300">
                        <svg class="w-full h-full" viewBox="0 0 40 40" fill="currentColor">
                            <path d="M16 4C11.5817 4 8 7.58172 8 12C8 16.4183 11.5817 20 16 20H19.5V16.5C19.5 12.0817 23.0817 8.5 27.5 8.5C31.9183 8.5 35.5 12.0817 35.5 16.5C35.5 20.9183 31.9183 24.5 27.5 24.5H24V28C24 32.4183 20.4183 36 16 36C11.5817 36 8 32.4183 8 28C8 23.5817 11.5817 20 16 20C20.4183 20 24 16.4183 24 12C24 7.58172 20.4183 4 16 4Z"/>
                        </svg>
                    </span> 
                    bersama Pintera
                </h1>

                {{-- Hero Sub-headline (Containing exact assertion string for test compliance) --}}
                <p class="text-base sm:text-lg text-gray-500 max-w-2xl mx-auto leading-relaxed font-normal mb-9">
                    <strong class="text-portal-600 font-bold">Selamat Datang di Portal Terpadu Pintera.</strong> 
                    Wujudkan transformasi ekosistem pendidikan modern yang terintegrasi, fleksibel, dan terpadu untuk seluruh pemangku kepentingan.
                </p>

                {{-- Main Hero CTA Buttons (Harmonized with Pintera Portal Primary) --}}
                <div class="flex flex-wrap items-center justify-center gap-4">
                    <a
                        href="#portal-cards"
                        class="inline-flex items-center justify-center min-h-[50px] px-8 py-3.5 rounded-xl bg-portal-500 text-white font-bold text-base hover:bg-portal-600 hover:scale-[1.02] active:scale-[0.98] transition-all shadow-lg shadow-portal-500/25 gap-3"
                    >
                        <svg class="w-5 h-5 text-white stroke-[2.5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 12h14" />
                        </svg>
                        <span>Jelajahi Portal Layanan</span>
                    </a>

                    <a
                        href="{{ route('spmb.welcome') }}"
                        class="inline-flex items-center justify-center min-h-[50px] px-8 py-3.5 rounded-xl border-2 border-gray-200 bg-white text-portal-600 font-bold text-base hover:border-portal-500/50 hover:bg-portal-50/40 transition-all shadow-sm gap-2"
                    >
                        <span>Informasi SPMB Baru</span>
                    </a>
                </div>
            </div>

            {{-- 3-Column Asymmetric Bento Grid (Harmonized with Pintera Brand Colors & Custom Vector Art) --}}
            <section id="portal-cards" aria-label="Daftar Portal Layanan" class="mt-14 grid grid-cols-1 lg:grid-cols-3 gap-7 w-full max-w-7xl mx-auto">
                
                {{-- Column 1: Stacked Green (Yayasan) & Blue (Admin) Cards --}}
                <div class="flex flex-col gap-7 h-full">
                    
                    {{-- Card 1: Portal Yayasan (Success/Emerald Theme) --}}
                    <div class="bg-[linear-gradient(145deg,#12B76A_0%,#039855_100%)] text-white rounded-[28px] p-7 flex-1 flex flex-col justify-between shadow-xl shadow-success-600/15 relative overflow-hidden group min-h-[230px] border border-emerald-400/30">
                        {{-- Top Badges & Squiggly Arrow Vector --}}
                        <div class="flex items-start justify-between mb-6 relative z-10">
                            <div class="flex items-center gap-2">
                                <div class="bg-white/20 backdrop-blur-md px-3.5 py-1.5 rounded-xl border border-white/25 text-xs font-extrabold text-white flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-amber-300"></span>
                                    <span>KPI &amp; Finansial</span>
                                </div>
                                <div class="bg-black/15 px-3 py-1.5 rounded-xl text-xs font-bold text-white/90">
                                    Eksekutif
                                </div>
                            </div>
                            {{-- Squiggly Arrow Vector --}}
                            <svg class="w-9 h-9 text-white/80 group-hover:translate-x-1 group-hover:translate-y-1 transition-transform" viewBox="0 0 40 40" fill="none">
                                <path d="M10 10C20 12 28 20 22 30" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                                <path d="M16 26L22 30L26 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>

                        {{-- Card Content --}}
                        <div class="relative z-10">
                            <h2 class="text-2xl font-black tracking-tight mb-2 font-display">
                                Portal Yayasan
                            </h2>
                            <p class="text-white/90 text-[13.5px] font-medium leading-relaxed mb-5">
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

                    {{-- Card 2: Portal Admin (Brand Electric Royal Blue Theme) --}}
                    <div class="bg-[linear-gradient(145deg,#465FFF_0%,#3641F5_100%)] text-white rounded-[28px] p-7 flex-1 flex flex-col justify-between shadow-xl shadow-brand-500/15 relative overflow-hidden group min-h-[230px] border border-indigo-400/30">
                        {{-- Top Award Ribbon Vector --}}
                        <div class="flex items-center justify-between mb-6 relative z-10">
                            <div class="w-12 h-12 rounded-2xl bg-white/15 backdrop-blur-md border border-white/25 flex items-center justify-center text-white shadow-inner">
                                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                </svg>
                            </div>
                            <span class="text-xs font-bold uppercase tracking-wider text-white/80 bg-black/15 px-3 py-1 rounded-full">Tata Usaha &amp; Admin</span>
                        </div>

                        {{-- Card Content --}}
                        <div class="relative z-10">
                            <h2 class="text-2xl font-black tracking-tight mb-2 font-display">
                                Portal Admin
                            </h2>
                            <p class="text-white/90 text-[13.5px] font-medium leading-relaxed mb-5">
                                Manajemen data sekolah, kepegawaian, tagihan &amp; konfigurasi SPMB.
                            </p>
                            <a
                                href="{{ route('login') }}"
                                aria-label="Masuk ke Portal Admin"
                                class="inline-flex items-center gap-2 text-sm font-extrabold underline decoration-2 underline-offset-4 decoration-white/60 hover:decoration-white hover:text-white transition"
                            >
                                <span>Masuk Admin</span>
                                <span>&rarr;</span>
                            </a>
                        </div>
                    </div>

                </div>

                {{-- Column 2: Center Tall Card with VECTOR TEACHER TEACHING & White Overlay Box (Portal Guru) --}}
                <div class="rounded-[28px] overflow-hidden shadow-2xl shadow-portal-600/15 relative min-h-[490px] bg-[linear-gradient(160deg,#16324F_0%,#1E3A5F_65%)] flex flex-col justify-between p-6 group border border-slate-700/50">
                    
                    {{-- Top Label in Teacher Card --}}
                    <div class="relative z-10 flex items-center justify-between">
                        <span class="px-3.5 py-1.5 rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-white text-xs font-extrabold tracking-wide uppercase flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-amber-400 fill-current" viewBox="0 0 20 20"><path d="M10 2L12.39 6.84L17.72 7.62L13.86 11.38L14.78 16.68L10 14.17L5.22 16.68L6.14 11.38L2.28 7.62L7.61 6.84L10 2Z"/></svg>
                            Tenaga Pendidik
                        </span>
                        <span class="text-xs font-bold text-white/70">Akademik &amp; RPP</span>
                    </div>

                    {{-- VECTOR ILLUSTRATION: GURU SEDANG MENGAJAR (Teacher Teaching at Smart Chalkboard) --}}
                    <div class="relative my-4 flex-1 flex items-center justify-center pointer-events-none">
                        <svg class="w-full max-w-[280px] h-auto drop-shadow-md group-hover:scale-[1.04] transition-transform duration-500 ease-out" viewBox="0 0 300 240" fill="none" xmlns="http://www.w3.org/2000/svg">
                            {{-- Chalkboard / Presentation Screen --}}
                            <rect x="20" y="10" width="220" height="130" rx="10" fill="#102540" stroke="#C9A227" stroke-width="4"/>
                            <rect x="26" y="16" width="208" height="118" rx="6" fill="#0D1D33"/>
                            
                            {{-- Chalkboard Content: Charts, Math & Biology Vectors --}}
                            <path d="M45 110L75 80L100 95L135 55" stroke="#12B76A" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="135" cy="55" r="4.5" fill="#F79009"/>
                            {{-- Bar chart --}}
                            <rect x="160" y="85" width="14" height="30" rx="2" fill="#465FFF"/>
                            <rect x="180" y="65" width="14" height="50" rx="2" fill="#12B76A"/>
                            <rect x="200" y="45" width="14" height="70" rx="2" fill="#F79009"/>
                            {{-- Text lines on board --}}
                            <rect x="45" y="35" width="70" height="6" rx="3" fill="#E7EEF5" fill-opacity="0.8"/>
                            <rect x="45" y="48" width="45" height="5" rx="2.5" fill="#9CB9FF"/>
                            
                            {{-- Podium / Table --}}
                            <rect x="30" y="180" width="120" height="55" rx="6" fill="#1E3A5F" stroke="#344054" stroke-width="2"/>
                            {{-- Laptop on Podium --}}
                            <path d="M60 175H100V180H60V175Z" fill="#D0D5DD"/>
                            <path d="M54 180H106V184H54V180Z" fill="#98A2B3"/>
                            {{-- Stack of Books --}}
                            <rect x="115" y="170" width="26" height="6" rx="2" fill="#F04438"/>
                            <rect x="117" y="164" width="22" height="6" rx="2" fill="#12B76A"/>

                            {{-- TEACHER VECTOR FIGURE (Standing right, pointing at chalkboard) --}}
                            {{-- Shadow --}}
                            <ellipse cx="220" cy="230" rx="35" ry="7" fill="black" fill-opacity="0.3"/>
                            {{-- Legs --}}
                            <rect x="205" y="170" width="12" height="60" rx="5" fill="#1A2536"/>
                            <rect x="225" y="170" width="12" height="60" rx="5" fill="#111823"/>
                            {{-- Body / Suit --}}
                            <path d="M190 120C190 108 248 108 248 120V175H190V120Z" fill="#2563EB"/>
                            {{-- Inner white shirt & Tie --}}
                            <path d="M212 110L228 110L224 135L216 135L212 110Z" fill="white"/>
                            <path d="M218 115L222 115L221 145L219 145Z" fill="#DC6803"/>
                            {{-- Head --}}
                            <circle cx="219" cy="92" r="17" fill="#FDE08B"/>
                            {{-- Hair & Glasses --}}
                            <path d="M202 85C202 75 236 75 236 85C236 82 225 78 219 78C213 78 202 82 202 85Z" fill="#101828"/>
                            <rect x="210" y="86" width="20" height="7" rx="3" stroke="#101828" stroke-width="2" fill="none"/>
                            {{-- Arm with Pointer Stick pointing to board --}}
                            <path d="M195 125L150 100" stroke="#2563EB" stroke-width="12" stroke-linecap="round"/>
                            <circle cx="148" cy="98" r="6" fill="#FDE08B"/>
                            <line x1="148" y1="98" x2="115" y2="75" stroke="#F79009" stroke-width="3" stroke-linecap="round"/>
                            {{-- Other Arm holding book --}}
                            <path d="M243 125L255 155" stroke="#1E3A8A" stroke-width="12" stroke-linecap="round"/>
                            <rect x="248" y="148" width="16" height="22" rx="2" fill="#12B76A" transform="rotate(-15 248 148)"/>
                        </svg>
                    </div>

                    {{-- White Box Overlay at Bottom (Harmonized with SPMB Style) --}}
                    <div class="relative z-10 bg-white rounded-[22px] p-6 shadow-2xl border border-gray-100">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-lg bg-portal-50 text-portal-600 text-[11px] font-extrabold uppercase tracking-wider mb-2.5">
                            Presensi &amp; E-Rapor
                        </div>
                        <h2 class="text-2xl font-black text-gray-900 tracking-tight mb-2 font-display">
                            Portal Guru
                        </h2>
                        <p class="text-gray-500 text-[13.5px] leading-relaxed font-normal mb-5">
                            Pengisian presensi biometrik, penyusunan RPP, &amp; pencatatan nilai.
                        </p>
                        <a
                            href="{{ route('login') }}"
                            aria-label="Masuk ke Portal Guru"
                            class="w-full inline-flex items-center justify-center min-h-[46px] gap-2 px-5 py-3 rounded-xl text-sm font-extrabold bg-portal-500 text-white hover:bg-portal-600 transition shadow-md shadow-portal-500/20"
                        >
                            <span>Masuk Guru</span>
                            <span>&rarr;</span>
                        </a>
                    </div>
                </div>

                {{-- Column 3: Tall Warm Amber/Gold Card with VECTOR STUDENT STUDYING (Portal Siswa) --}}
                <div class="bg-[linear-gradient(150deg,#FFB703_0%,#F79009_100%)] text-gray-900 rounded-[28px] p-7 flex flex-col justify-between shadow-2xl shadow-warning-500/15 relative overflow-hidden group min-h-[490px] border border-amber-300/50">
                    
                    {{-- Top Typography --}}
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-4">
                            <div class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-black/10 text-gray-900 text-xs font-black uppercase tracking-wider">
                                <span>Siswa &amp; Wali Murid</span>
                            </div>
                            <span class="w-2.5 h-2.5 rounded-full bg-white shadow-sm"></span>
                        </div>
                        <h2 class="text-3xl font-black tracking-tight mb-2 leading-tight font-display text-gray-900">
                            Portal Siswa
                        </h2>
                        <p class="text-gray-900/85 text-[14.5px] font-medium leading-relaxed">
                            Akses kalender akademik, lihat tagihan sekolah, &amp; e-rapor.
                        </p>
                    </div>

                    {{-- VECTOR ILLUSTRATION: SISWA SEDANG BELAJAR (Student Studying at Desk with Laptop & Books) --}}
                    <div class="relative z-10 my-6 flex-1 flex items-center justify-center pointer-events-none">
                        <div class="relative w-full flex items-center justify-center">
                            {{-- Radiating Sparkle Lines & Floating Elements --}}
                            <svg class="absolute top-0 right-4 w-10 h-10 text-white animate-pulse" viewBox="0 0 50 50" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round">
                                <line x1="25" y1="5" x2="25" y2="15"/>
                                <line x1="40" y1="15" x2="32" y2="23"/>
                                <line x1="45" y1="28" x2="35" y2="28"/>
                            </svg>
                            
                            {{-- Student Studying Vector Scene --}}
                            <svg class="w-full max-w-[260px] h-auto drop-shadow-lg group-hover:scale-[1.04] transition-transform duration-500 ease-out" viewBox="0 0 280 240" fill="none" xmlns="http://www.w3.org/2000/svg">
                                {{-- Background Decorative Organic Circle / Halo --}}
                                <circle cx="140" cy="120" r="95" fill="white" fill-opacity="0.25"/>
                                
                                {{-- Study Desk / Table --}}
                                <path d="M20 190L260 190V205L20 205V190Z" fill="#D0D5DD" stroke="#101828" stroke-width="2.5"/>
                                <path d="M40 205V235" stroke="#101828" stroke-width="6" stroke-linecap="round"/>
                                <path d="M240 205V235" stroke="#101828" stroke-width="6" stroke-linecap="round"/>

                                {{-- Open Laptop on Desk --}}
                                <rect x="50" y="135" width="70" height="48" rx="5" fill="#1E3A5F" stroke="#101828" stroke-width="2.5"/>
                                <rect x="56" y="141" width="58" height="36" rx="2" fill="#6CE9A6"/>
                                <path d="M38 183L132 183L126 190H44L38 183Z" fill="#E4E7EC" stroke="#101828" stroke-width="2"/>
                                {{-- Checkmarks on Laptop screen --}}
                                <path d="M65 155L72 162L85 148" stroke="#101828" stroke-width="2.5" stroke-linecap="round"/>
                                <rect x="70" y="168" width="35" height="4" rx="2" fill="#101828" fill-opacity="0.3"/>

                                {{-- Stacked Books on Desk (Right side) --}}
                                <rect x="205" y="176" width="36" height="14" rx="3" fill="#465FFF" stroke="#101828" stroke-width="2"/>
                                <rect x="210" y="164" width="28" height="12" rx="2.5" fill="#12B76A" stroke="#101828" stroke-width="2"/>
                                {{-- Coffee Cup / Pencil Holder --}}
                                <rect x="216" y="140" width="16" height="24" rx="3" fill="white" stroke="#101828" stroke-width="2"/>
                                <path d="M220 130L222 140" stroke="#F04438" stroke-width="3" stroke-linecap="round"/>
                                <path d="M226 126L227 140" stroke="#465FFF" stroke-width="3" stroke-linecap="round"/>

                                {{-- STUDENT FIGURE (Sitting behind desk, leaning slightly forward, happy) --}}
                                {{-- Body / Hoodie --}}
                                <path d="M110 135C110 115 180 115 180 135V190H110V135Z" fill="#12B76A" stroke="#101828" stroke-width="2.5"/>
                                {{-- Hoodie Collar / Drawstrings --}}
                                <path d="M135 130C135 138 155 138 155 130" stroke="white" stroke-width="3"/>
                                <circle cx="145" cy="98" r="22" fill="#FEDF89" stroke="#101828" stroke-width="2.5"/>
                                {{-- Trendy Student Hair & Headphones --}}
                                <path d="M123 95C123 78 167 78 167 95C167 88 155 82 145 82C135 82 123 88 123 95Z" fill="#1D2939"/>
                                <path d="M120 95C120 72 170 72 170 95" stroke="#F04438" stroke-width="5" stroke-linecap="round"/>
                                <rect x="116" y="88" width="8" height="18" rx="4" fill="#F04438" stroke="#101828" stroke-width="1.5"/>
                                <rect x="166" y="88" width="8" height="18" rx="4" fill="#F04438" stroke="#101828" stroke-width="1.5"/>
                                {{-- Happy Smile & Eyes --}}
                                <path d="M138 98C142 96 148 96 152 98" stroke="#101828" stroke-width="2" stroke-linecap="round"/>
                                <path d="M140 106C143 110 147 110 150 106" stroke="#101828" stroke-width="2.5" stroke-linecap="round"/>
                                {{-- Hands resting on desk/keyboard --}}
                                <circle cx="120" cy="183" r="7" fill="#FEDF89" stroke="#101828" stroke-width="2"/>
                                <circle cx="165" cy="183" r="7" fill="#FEDF89" stroke="#101828" stroke-width="2"/>

                                {{-- Floating Lightbulb (Idea/A+) --}}
                                <g transform="translate(180, 45)">
                                    <circle cx="15" cy="15" r="14" fill="#FFF385" stroke="#101828" stroke-width="2"/>
                                    <path d="M15 8V14" stroke="#101828" stroke-width="2"/>
                                    <path d="M10 22H20" stroke="#101828" stroke-width="2" stroke-linecap="round"/>
                                </g>
                            </svg>
                        </div>
                    </div>

                    {{-- Bottom CTA Button --}}
                    <div class="relative z-10 pt-2">
                        <a
                            href="{{ route('login') }}"
                            aria-label="Masuk ke Portal Siswa"
                            class="w-full bg-white text-gray-900 min-h-[48px] px-6 py-3.5 rounded-xl font-black text-sm inline-flex items-center justify-center gap-2.5 hover:bg-gray-900 hover:text-white transition-all duration-200 shadow-xl shadow-amber-900/15"
                        >
                            <span>Masuk Siswa</span>
                            <span>&rarr;</span>
                        </a>
                    </div>
                </div>

            </section>

            {{-- SPMB Quick Banner Section (To unify ecosystem & increase aesthetic wow-factor) --}}
            <section id="fitur" class="mt-20 w-full max-w-7xl mx-auto">
                <div class="rounded-[28px] bg-[linear-gradient(160deg,#16324F_0%,#1E3A5F_65%)] p-8 sm:p-12 text-white shadow-2xl shadow-portal-600/20 grid grid-cols-1 lg:grid-cols-[1.2fr_0.8fr] items-center gap-8 relative overflow-hidden border border-portal-500/50">
                    <div class="relative z-10">
                        <div class="inline-flex items-center gap-2 rounded-full bg-white/10 backdrop-blur-md px-4 py-1.5 text-xs font-bold uppercase tracking-wide text-amber-300 mb-5 border border-white/15">
                            <span>Sistem Penerimaan Murid Baru Online</span>
                        </div>
                        <h2 class="text-2xl sm:text-4xl font-extrabold text-white tracking-tight leading-tight font-display mb-4">
                            Satu Akun untuk Semua Pendaftaran Sekolah &amp; Yayasan
                        </h2>
                        <p class="text-white/80 text-[14.5px] sm:text-base leading-relaxed max-w-xl mb-6 font-normal">
                            Proses pendaftaran siswa baru yang transparan, mudah dilacak dari tahap pengisian formulir, verifikasi dokumen, hingga pembayaran otomatis secara online.
                        </p>
                        <div class="flex flex-wrap items-center gap-4">
                            <a href="{{ route('spmb.welcome') }}" class="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-success-500 text-white font-extrabold text-sm hover:bg-success-600 transition shadow-lg shadow-success-600/30 gap-2">
                                <span>Buka Portal SPMB</span>
                                <span>&rarr;</span>
                            </a>
                        </div>
                    </div>
                    <div class="relative z-10 grid grid-cols-2 gap-4">
                        <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-sm p-5 text-center">
                            <p class="text-3xl font-black text-amber-400 tabular-nums font-display">100%</p>
                            <p class="text-xs font-bold text-white/80 uppercase mt-1">Terintegrasi Online</p>
                        </div>
                        <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-sm p-5 text-center">
                            <p class="text-3xl font-black text-emerald-400 tabular-nums font-display">24/7</p>
                            <p class="text-xs font-bold text-white/80 uppercase mt-1">Akses Portal &amp; Rapor</p>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        {{-- Footer --}}
        <footer id="bantuan" class="w-full max-w-7xl mx-auto px-6 py-10 z-10 border-t border-gray-200/80 text-xs sm:text-sm text-gray-500 flex flex-col sm:flex-row items-center justify-between gap-4 font-semibold">
            <div>
                &copy; {{ date('Y') }} <strong class="text-portal-600 font-bold">Pintera App</strong>. Hak cipta dilindungi undang-undang.
            </div>
            <div class="flex items-center gap-6 text-gray-600">
                <a href="{{ route('spmb.welcome') }}" class="hover:text-portal-500 transition-colors">Portal SPMB</a>
                <span class="hover:text-portal-500 cursor-pointer transition-colors">Kebijakan Privasi</span>
                <span class="hover:text-portal-500 cursor-pointer transition-colors">Bantuan Teknis</span>
                <span class="w-1.5 h-1.5 rounded-full bg-success-500"></span>
                <span class="text-portal-600 font-bold">v1.0-Demo</span>
            </div>
        </footer>
    </body>
</html>
