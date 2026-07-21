{{-- resources/views/components/layouts/portal-wizard.blade.php --}}
@props(['title' => null, 'current' => null, 'lembaga', 'jalur', 'nominal' => null])

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? 'Formulir Pendaftaran' }} — Pintera</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-50 font-sans text-gray-900 antialiased">
        <x-portal-authenticated-navbar active="dashboard" />

        <div class="border-b border-gray-200 bg-portal-50 px-4 py-3 sm:px-6 lg:px-10">
            <div class="mx-auto flex max-w-7xl items-center gap-2 text-[12.5px] text-portal-500">
                <x-icon name="person" class="h-3.5 w-3.5" />
                <span>Mendaftar sebagai: <span class="font-bold">{{ auth('portal')->user()->nama }}</span> &middot; {{ auth('portal')->user()->email }}</span>
            </div>
        </div>

        <div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-10">
            <p class="text-[11px] font-bold uppercase tracking-wide text-portal-500">{{ $lembaga->nama }} &middot; Jalur {{ $jalur->nama }}</p>
            <h1 class="mt-1 text-xl font-bold text-gray-900">Formulir Pendaftaran</h1>
            <p class="mt-1 text-[12.5px] text-gray-500">Lengkapi setiap langkah untuk menyelesaikan pendaftaranmu.</p>
        </div>

        <x-portal-wizard-stepper :current="$current" />

        <main class="mx-auto grid max-w-7xl gap-5 px-4 py-6 sm:px-6 min-[901px]:grid-cols-[1.5fr_1fr] lg:px-10">
            <div>{{ $slot }}</div>
            <x-portal-wizard-sidebar :lembaga="$lembaga" :jalur="$jalur" :nominal="$nominal" />
        </main>

        <x-portal-footer :yayasan="$lembaga->yayasan ?? null" />
    </body>
</html>
