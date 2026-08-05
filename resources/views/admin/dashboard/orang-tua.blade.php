<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Orang Tua &middot; Ringkasan</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Dashboard Orang Tua</h2>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-8">
        <x-hero-banner
            eyebrow="Portal Orang Tua"
            :title="'Selamat datang, ' . Auth::user()->name . '!'"
            subtitle="Portal orang tua (nilai anak, presensi, tagihan) belum tersedia. Hubungi admin sekolah untuk informasi lebih lanjut."
        />

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat-tile label="Ditugaskan" :value="$kasusStats['ditugaskan']" icon="assignment" />
            <x-stat-tile label="Berjalan" :value="$kasusStats['berjalan']" icon="pending_actions" />
            <x-stat-tile label="Eskalasi" :value="$kasusStats['eskalasi']" icon="priority_high" />
            <x-stat-tile label="Selesai" :value="$kasusStats['selesai']" icon="check_circle" />
        </div>

        <x-panel>
            <div class="border-b border-ink/10 px-6 py-4">
                <h3 class="font-display font-semibold text-ink">Kasus Pendampingan</h3>
            </div>
            @if ($kasusList->isEmpty())
                <p class="px-6 py-8 text-center text-sm text-slate">Tidak ada kasus pendampingan untuk anak Anda.</p>
            @else
                <ul class="divide-y divide-ink/10">
                    @foreach ($kasusList as $kasus)
                        <li class="px-6 py-3">
                            @if (in_array($kasus->id, $kontakUtamaKasusIds, true))
                                <a href="{{ route('kasus.show', $kasus) }}" class="flex items-center justify-between hover:text-brass">
                                    <div>
                                        <p class="text-sm font-semibold text-ink">{{ $kasus->siswa->nama_lengkap }}</p>
                                        <p class="text-xs text-slate">{{ $kasus->kategori_masalah }}</p>
                                    </div>
                                    <x-badge tone="brass">{{ $kasus->status->label() }}</x-badge>
                                </a>
                            @else
                                <div class="flex items-center justify-between text-slate">
                                    <div>
                                        <p class="text-sm font-semibold text-ink">{{ $kasus->siswa->nama_lengkap }}</p>
                                        <p class="text-xs text-slate">{{ $kasus->kategori_masalah }}</p>
                                    </div>
                                    <x-badge tone="brass">{{ $kasus->status->label() }}</x-badge>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-panel>
    </div>
</x-app-layout>
