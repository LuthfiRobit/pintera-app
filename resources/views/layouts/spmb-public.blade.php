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
