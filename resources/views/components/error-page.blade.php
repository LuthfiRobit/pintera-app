@props(['code', 'icon', 'title', 'message'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $code }} — {{ $title }} | {{ config('app.name', 'Pintera') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:600,700,800|inter:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-ink antialiased">
        <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-gradient-to-br from-ink via-[#123363] to-ink px-4 py-10">
            <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-white/10 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-16 -left-16 h-72 w-72 rounded-full bg-brass/20 blur-3xl"></div>

            <a href="/" class="relative z-10 flex items-center gap-3">
                <span class="flex h-14 w-14 items-center justify-center rounded-xl border border-brass/50 bg-white/5 font-display text-2xl font-bold text-brass">
                    {{ Str::of(config('app.name', 'Y'))->substr(0, 1) }}
                </span>
                <span class="leading-tight text-paper">
                    <span class="block font-display text-lg font-bold">{{ config('app.name', 'Yayasan') }}</span>
                    <span class="block text-[11px] uppercase tracking-[0.14em] text-paper/40">Sistem Administrasi</span>
                </span>
            </a>

            <div class="relative z-10 mt-8 w-full overflow-hidden rounded-2xl border border-white/10 bg-white shadow-elevated sm:max-w-md">
                <div class="flex flex-col items-center gap-4 px-8 py-10 text-center">
                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-brass/10 text-brass">
                        <x-icon :name="$icon" class="h-8 w-8" />
                    </span>

                    <p class="font-display text-5xl font-bold text-ink">{{ $code }}</p>

                    <h1 class="font-display text-xl font-bold text-ink">{{ $title }}</h1>

                    <p class="max-w-xs text-sm text-gray-500">{{ $message }}</p>

                    @auth
                        <a href="{{ route('dashboard') }}" class="mt-2 inline-flex items-center gap-2 rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600 active:scale-[0.98]">
                            Kembali ke Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="mt-2 inline-flex items-center gap-2 rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600 active:scale-[0.98]">
                            Ke Halaman Login
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </body>
</html>
