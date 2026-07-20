@props(['active' => null])

<nav x-data="{ open: false }" class="sticky top-0 z-20 border-b border-gray-200 bg-white">
    <div class="mx-auto flex max-w-7xl items-center gap-6 px-4 py-4 sm:px-6 lg:px-10">
        <a href="{{ route('spmb.welcome') }}" class="mr-auto flex items-center gap-2.5">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-portal-500 to-portal-600 text-white">
                <x-icon name="school" class="h-5 w-5" />
            </span>
            <span class="leading-tight">
                <span class="block text-[15px] font-bold text-gray-900">Pintera</span>
                <span class="block text-[11px] font-semibold uppercase tracking-wide text-gray-400">Portal Calon Siswa</span>
            </span>
        </a>

        <div class="hidden items-center gap-6 text-[13.5px] font-medium text-gray-500 min-[721px]:flex">
            <a href="{{ route('spmb.welcome') }}" class="{{ $active === 'beranda' ? 'font-bold text-portal-500' : '' }}">Beranda</a>
            <a href="{{ route('spmb.welcome') }}#lembaga" class="{{ $active === 'lembaga' ? 'font-bold text-portal-500' : '' }}">Lembaga</a>
            <a href="{{ route('spmb.welcome') }}#alur">Alur Pendaftaran</a>
            <a href="#">Bantuan</a>
        </div>

        <div class="hidden items-center gap-2.5 min-[721px]:flex">
            <a
                href="{{ Route::has('portal.login') ? route('portal.login') : route('spmb.welcome') . '#lembaga' }}"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] font-semibold text-portal-500"
            >Masuk</a>
            <a href="{{ route('spmb.welcome') }}#lembaga" class="rounded-lg bg-portal-500 px-4 py-2.5 text-[13px] font-semibold text-white">Daftar Akun</a>
        </div>

        <button
            type="button"
            @click="open = !open"
            class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-portal-500 min-[721px]:hidden"
            aria-label="Buka menu navigasi"
        >
            <x-icon name="menu" class="h-5 w-5" />
        </button>
    </div>

    <div x-show="open" x-cloak x-transition class="border-t border-gray-200 bg-white px-4 pb-4 pt-2 min-[721px]:hidden">
        <div class="flex flex-col gap-1 text-[13.5px] font-medium text-gray-600">
            <a href="{{ route('spmb.welcome') }}" class="py-2">Beranda</a>
            <a href="{{ route('spmb.welcome') }}#lembaga" class="py-2">Lembaga</a>
            <a href="{{ route('spmb.welcome') }}#alur" class="py-2">Alur Pendaftaran</a>
            <a href="#" class="py-2">Bantuan</a>
        </div>
        <div class="mt-3 flex flex-col gap-2">
            <a href="{{ Route::has('portal.login') ? route('portal.login') : route('spmb.welcome') . '#lembaga' }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-center text-[13px] font-semibold text-portal-500">Masuk</a>
            <a href="{{ route('spmb.welcome') }}#lembaga" class="rounded-lg bg-portal-500 px-4 py-2.5 text-center text-[13px] font-semibold text-white">Daftar Akun</a>
        </div>
    </div>
</nav>
