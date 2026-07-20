<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Keuangan</p>
        <div class="mt-1 flex items-center justify-between">
            <h2 class="font-display text-2xl font-semibold text-ink">Jenis Tagihan</h2>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl">
        <x-panel>
            <ul class="divide-y divide-ink/10">
                @forelse ($jenisTagihanList as $jenisTagihan)
                    <li class="flex items-center justify-between gap-3 px-6 py-4">
                        <div>
                            <p class="font-medium text-ink">{{ $jenisTagihan->nama }}</p>
                            <p class="text-xs text-slate">{{ ucfirst(str_replace('_', ' ', $jenisTagihan->kategori)) }} &middot; {{ $jenisTagihan->bisa_dicicil ? 'Bisa dicicil maks '.$jenisTagihan->maks_cicilan.'x' : 'Tidak bisa dicicil' }}</p>
                        </div>
                    </li>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-slate">Belum ada jenis tagihan.</li>
                @endforelse
            </ul>
        </x-panel>
    </div>
</x-app-layout>
