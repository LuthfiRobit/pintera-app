{{-- resources/views/components/portal-authenticated-navbar.blade.php --}}
@props(['active' => null])

<nav x-data="{ mobileOpen: false, userOpen: false }" class="sticky top-0 z-20 border-b border-gray-200 bg-white">
    <div class="mx-auto flex max-w-7xl items-center gap-6 px-4 py-4 sm:px-6 lg:px-10">
        <a href="{{ route('portal.dashboard') }}" class="mr-auto flex items-center gap-2.5">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-portal-500 to-portal-600 text-white">
                <x-icon name="school" class="h-5 w-5" />
            </span>
            <span class="leading-tight">
                <span class="block text-[15px] font-bold text-gray-900">Pintera</span>
                <span class="block text-[11px] font-semibold uppercase tracking-wide text-gray-400">Portal Calon Siswa</span>
            </span>
        </a>

        <div class="hidden items-center gap-6 text-[13.5px] font-medium text-gray-500 min-[721px]:flex">
            <a href="{{ route('portal.dashboard') }}" class="{{ $active === 'dashboard' ? 'font-bold text-portal-500' : '' }}">Dashboard</a>
            <a href="{{ route('portal.dashboard') }}" class="{{ $active === 'riwayat' ? 'font-bold text-portal-500' : '' }}">Riwayat</a>
            <a href="#">Bantuan</a>
        </div>

        <div class="relative hidden min-[721px]:block">
            <button type="button" @click="userOpen = !userOpen" @click.outside="userOpen = false" class="flex items-center gap-2.5">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-portal-500 text-[12px] font-bold text-white">
                    {{ Str::of(auth('portal')->user()->nama)->explode(' ')->map(fn ($kata) => Str::substr($kata, 0, 1))->take(2)->implode('') }}
                </span>
                <span class="text-[13px] font-semibold text-gray-900">{{ Str::of(auth('portal')->user()->nama)->before(' ') }}</span>
                <x-icon name="expand_more" class="h-3.5 w-3.5 text-gray-400" />
            </button>
            <div x-show="userOpen" x-cloak x-transition class="absolute right-0 top-full mt-2 w-44 rounded-xl border border-gray-200 bg-white py-1.5 shadow-[0_20px_44px_rgba(30,58,95,0.10)]">
                <form method="POST" action="{{ route('portal.logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-[13px] font-semibold text-gray-600 hover:bg-gray-50">
                        <x-icon name="logout" class="h-4 w-4" />
                        Keluar
                    </button>
                </form>
            </div>
        </div>

        <button type="button" @click="mobileOpen = !mobileOpen" class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-portal-500 min-[721px]:hidden" aria-label="Buka menu navigasi">
            <x-icon name="menu" class="h-5 w-5" />
        </button>
    </div>

    <div x-show="mobileOpen" x-cloak x-transition class="border-t border-gray-200 bg-white px-4 pb-4 pt-2 min-[721px]:hidden">
        <div class="flex flex-col gap-1 text-[13.5px] font-medium text-gray-600">
            <a href="{{ route('portal.dashboard') }}" class="py-2">Dashboard</a>
            <a href="{{ route('portal.dashboard') }}" class="py-2">Riwayat</a>
            <a href="#" class="py-2">Bantuan</a>
        </div>
        <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-3">
            <span class="text-[13px] font-semibold text-gray-900">{{ auth('portal')->user()->nama }}</span>
            <form method="POST" action="{{ route('portal.logout') }}">
                @csrf
                <button type="submit" class="text-[13px] font-semibold text-portal-500">Keluar</button>
            </form>
        </div>
    </div>
</nav>
