<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? 'SPMB' }} — Pintera</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-50 font-sans text-gray-900 antialiased">
        <x-portal-auth-top :link-label="$linkLabel" :link-text="$linkText" :link-route="$linkRoute" />
        <main>
            @if (session('status'))
                <div class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-10">
                    <div class="rounded-xl bg-success-50 px-4 py-3 text-[13px] font-semibold text-success-700">
                        {{ session('status') }}
                    </div>
                </div>
            @endif
            {{ $slot }}
        </main>
        <x-portal-footer :yayasan="$yayasan ?? null" />
    </body>
</html>
