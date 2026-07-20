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
        <x-portal-navbar :active="$active ?? null" />
        <main>{{ $slot }}</main>
        <x-portal-footer :yayasan="$yayasan ?? null" />
    </body>
</html>
