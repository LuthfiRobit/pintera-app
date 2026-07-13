<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Guru &middot; Ringkasan</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Dashboard Guru</h2>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-8">
        <x-hero-banner
            eyebrow="Portal Guru"
            :title="'Halo, ' . Auth::user()->name . '!'"
            subtitle="Fitur akademik (jadwal mengajar, jurnal, nilai) menyusul di fase berikutnya."
        />

        @if ($jabatanTambahan->isNotEmpty())
            <x-panel>
                <div class="border-b border-ink/10 px-6 py-4">
                    <h3 class="font-display font-semibold text-ink">Jabatan Tugas Tambahan</h3>
                </div>
                <ul class="divide-y divide-ink/10">
                    @foreach ($jabatanTambahan as $jabatan)
                        <li class="flex items-center justify-between px-6 py-3">
                            <span class="text-sm text-ink">{{ $jabatan->nama }}</span>
                            <x-badge tone="brass">{{ $jabatan->kelompok }}</x-badge>
                        </li>
                    @endforeach
                </ul>
            </x-panel>
        @endif
    </div>
</x-app-layout>
