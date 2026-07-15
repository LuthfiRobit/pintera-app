{{-- resources/views/layouts/portal.blade.php --}}
@props(['title' => 'Portal Pendaftar'])

<!DOCTYPE html>
<html lang="id" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title }} — {{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:500,600,700,800|inter:400,500,600,700|ibm-plex-mono:500&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full bg-spmb-bg font-sans text-ink antialiased" x-data="{ sidebarOpen: false }">
        <div
            x-show="sidebarOpen"
            x-transition.opacity
            @click="sidebarOpen = false"
            class="fixed inset-0 z-30 bg-ink/40 lg:hidden"
            style="display: none;"
        ></div>

        <div class="flex min-h-full">
            <aside
                class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-spmb-primary transition-transform duration-200 ease-out lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
                :class="{ 'translate-x-0': sidebarOpen }"
            >
                <div class="flex h-20 shrink-0 items-center gap-3 border-b border-white/10 px-6">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15 font-display text-lg font-bold text-white">
                        {{ Str::of(config('app.name', 'P'))->substr(0, 1) }}
                    </span>
                    <p class="font-display text-base font-bold text-white">Portal Pendaftar</p>
                </div>

                <nav class="flex-1 overflow-y-auto px-4 py-6">
                    <a
                        href="{{ route('portal.dashboard') }}"
                        class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold {{ request()->routeIs('portal.dashboard') ? 'bg-white/15 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' }}"
                    >
                        <span class="material-symbols-outlined" style="font-size: 20px;">dashboard</span>
                        Dashboard
                    </a>
                </nav>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-20 flex h-20 shrink-0 items-center gap-4 border-b border-slate/10 bg-white/70 px-4 backdrop-blur-md sm:px-6">
                    <button @click="sidebarOpen = true" class="text-slate hover:text-ink lg:hidden" aria-label="Buka menu">
                        <span class="material-symbols-outlined">menu</span>
                    </button>
                    <div class="min-w-0 flex-1"></div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium text-ink">{{ auth('portal')->user()->nama }}</span>
                        <form method="POST" action="{{ route('portal.logout') }}">
                            @csrf
                            <button type="submit" class="text-sm text-slate hover:text-ink">Keluar</button>
                        </form>
                    </div>
                </header>

                <main class="flex-1 px-4 py-8 sm:px-6 lg:px-10">
                    {{ $slot }}
                </main>

                <footer class="px-4 py-6 text-center text-xs text-slate sm:px-6">
                    &copy; {{ now()->year }} {{ config('app.name') }}
                </footer>
            </div>
        </div>
    </body>
</html>
