{{-- resources/views/components/layouts/portal-dashboard.blade.php --}}
@props(['title' => null])

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? 'Dashboard' }} — Pintera</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-50 font-sans text-gray-900 antialiased">
        <x-portal-authenticated-navbar active="dashboard" />

        @if (session('status'))
            <div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-10">
                <div class="rounded-xl bg-success-50 px-4 py-3 text-[13px] font-semibold text-success-700">
                    {{ session('status') }}
                </div>
            </div>
        @endif
        @if (session('error'))
            <div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-10">
                <div class="rounded-xl bg-error-50 px-4 py-3 text-[13px] font-semibold text-error-700">
                    {{ session('error') }}
                </div>
            </div>
        @endif

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-10">
            {{ $slot }}
        </main>

        <x-portal-footer :yayasan="null" />
    </body>
</html>
