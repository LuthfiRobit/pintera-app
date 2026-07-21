@props(['linkLabel', 'linkText', 'linkRoute'])

<div class="border-b border-gray-200 bg-white px-4 py-4 sm:px-6 lg:px-10">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2.5">
        <a href="{{ route('spmb.welcome') }}" class="flex items-center gap-2.5">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-portal-500 to-portal-600 text-white">
                <x-icon name="school" class="h-5 w-5" />
            </span>
            <span class="leading-tight">
                <span class="block text-[15px] font-bold text-gray-900">Pintera</span>
                <span class="block text-[11px] font-semibold uppercase tracking-wide text-gray-400">Portal Calon Siswa</span>
            </span>
        </a>
        <div class="text-[13px] text-gray-500">
            {{ $linkLabel }} <a href="{{ route($linkRoute) }}" class="font-bold text-portal-500">{{ $linkText }}</a>
        </div>
    </div>
</div>
