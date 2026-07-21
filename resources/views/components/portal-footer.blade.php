@props(['yayasan' => null])

<footer class="bg-portal-600 px-4 pb-6 pt-11 text-gray-200 sm:px-6 lg:px-10">
    <div class="mx-auto grid max-w-7xl gap-10 border-b border-white/10 pb-7 sm:grid-cols-[1.6fr_1fr_1fr]">
        <div class="sm:col-span-1">
            <div class="mb-3 flex items-center gap-2.5">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 text-white">
                    <x-icon name="school" class="h-4 w-4" />
                </span>
                <span class="text-[15px] font-bold text-white">Pintera</span>
            </div>
            <p class="max-w-xs text-[12px] leading-relaxed text-gray-300">
                Portal terpadu penerimaan siswa baru {{ $yayasan?->nama ?? 'Yayasan' }} — satu akun untuk seluruh lembaga.
            </p>
        </div>
        <div>
            <h5 class="mb-3.5 text-[12px] font-bold uppercase tracking-wide text-white">Navigasi</h5>
            <ul class="flex flex-col gap-2.5 text-[12.5px]">
                <li><a href="{{ route('spmb.welcome') }}">Beranda</a></li>
                <li><a href="{{ route('spmb.welcome') }}#lembaga">Lembaga</a></li>
                <li><a href="{{ route('spmb.welcome') }}#alur">Alur Pendaftaran</a></li>
            </ul>
        </div>
        <div>
            <h5 class="mb-3.5 text-[12px] font-bold uppercase tracking-wide text-white">Kontak</h5>
            <ul class="flex flex-col gap-2.5 text-[12.5px]">
                @if ($yayasan?->alamat)
                    <li>{{ $yayasan->alamat }}</li>
                @endif
                @if ($yayasan?->email)
                    <li>{{ $yayasan->email }}</li>
                @endif
                @if ($yayasan?->telepon)
                    <li>{{ $yayasan->telepon }}</li>
                @endif
            </ul>
        </div>
    </div>
    <div class="mx-auto flex max-w-7xl flex-wrap justify-between gap-2 pt-4 text-[11.5px] text-gray-400">
        <span>&copy; {{ now()->year }} {{ $yayasan?->nama ?? config('app.name') }}</span>
        <span>Portal SPMB Pintera</span>
    </div>
</footer>
