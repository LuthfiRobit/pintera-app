<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700|ibm-plex-mono:500&display=swap" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@300..600,0..1&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full font-sans antialiased text-ink">
        <div x-data="{ sidebarOpen: false }" class="min-h-full bg-paper lg:flex">
            <x-toast />

            @include('layouts.sidebar')

            <div class="flex min-w-0 flex-1 flex-col">
                @include('layouts.topbar')

                @isset($header)
                    <header class="border-b border-ink/10 bg-white/60">
                        <div class="px-4 py-6 sm:px-6 lg:px-10">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main class="flex-1 px-4 py-6 sm:px-6 lg:px-10">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
