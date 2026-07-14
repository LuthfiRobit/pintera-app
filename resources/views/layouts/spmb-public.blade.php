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
    <body class="min-h-full bg-spmb-bg font-sans text-ink antialiased">
        <div class="mx-auto flex min-h-screen max-w-2xl flex-col px-4 py-6 sm:py-10">
            <header class="mb-8 flex items-center gap-3 rounded-2xl border border-slate/10 bg-white px-5 py-4 shadow-card">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-spmb-primary text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.44 60.44 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443" />
                    </svg>
                </span>
                <div>
                    <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-spmb-accent">Pendaftaran SPMB</p>
                    <h1 class="font-display text-lg font-bold text-spmb-primary">{{ $lembaga->nama ?? '' }}</h1>
                </div>
            </header>

            <main class="flex-1">
                @isset($langkah)
                    <x-spmb-stepper :langkah="$langkah" />
                @endisset

                {{ $slot }}
            </main>

            <footer class="mt-10 text-center text-xs text-slate">
                &copy; {{ now()->year }} {{ $lembaga->nama ?? config('app.name') }}
            </footer>
        </div>
    </body>
</html>
