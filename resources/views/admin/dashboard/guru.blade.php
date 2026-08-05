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

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat-tile label="Diajukan: Ditugaskan" :value="$kasusDiajukanStats['ditugaskan']" icon="assignment" />
            <x-stat-tile label="Diajukan: Berjalan" :value="$kasusDiajukanStats['berjalan']" icon="pending_actions" />
            <x-stat-tile label="Diajukan: Eskalasi" :value="$kasusDiajukanStats['eskalasi']" icon="priority_high" />
            <x-stat-tile label="Diajukan: Selesai" :value="$kasusDiajukanStats['selesai']" icon="check_circle" />
        </div>

        <x-panel>
            <div class="border-b border-ink/10 px-6 py-4">
                <h3 class="font-display font-semibold text-ink">Kasus Pendampingan yang Saya Ajukan</h3>
            </div>
            @if ($kasusDiajukan->isEmpty())
                <p class="px-6 py-8 text-center text-sm text-slate">Belum ada kasus yang Anda ajukan.</p>
            @else
                <ul class="divide-y divide-ink/10">
                    @foreach ($kasusDiajukan as $kasus)
                        <li class="px-6 py-3">
                            <a href="{{ route('kasus.show', $kasus) }}" class="flex items-center justify-between hover:text-brass">
                                <div>
                                    <p class="text-sm font-semibold text-ink">{{ $kasus->siswa->nama_lengkap }}</p>
                                    <p class="text-xs text-slate">{{ $kasus->kategori_masalah }} &middot; {{ $kasus->created_at->format('d M Y') }}</p>
                                </div>
                                <x-badge tone="brass">{{ $kasus->status->label() }}</x-badge>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-panel>

        @if ($kasusDitangani->isNotEmpty())
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <x-stat-tile label="Ditangani: Ditugaskan" :value="$kasusDitanganiStats['ditugaskan']" icon="assignment" />
                <x-stat-tile label="Ditangani: Berjalan" :value="$kasusDitanganiStats['berjalan']" icon="pending_actions" />
                <x-stat-tile label="Ditangani: Eskalasi" :value="$kasusDitanganiStats['eskalasi']" icon="priority_high" />
                <x-stat-tile label="Ditangani: Selesai" :value="$kasusDitanganiStats['selesai']" icon="check_circle" />
            </div>
            <x-panel>
                <div class="border-b border-ink/10 px-6 py-4">
                    <h3 class="font-display font-semibold text-ink">Kasus yang Saya Tangani (Konselor)</h3>
                </div>
                <ul class="divide-y divide-ink/10">
                    @foreach ($kasusDitangani as $kasus)
                        <li class="px-6 py-3">
                            <a href="{{ route('kasus.show', $kasus) }}" class="flex items-center justify-between hover:text-brass">
                                <div>
                                    <p class="text-sm font-semibold text-ink">{{ $kasus->siswa->nama_lengkap }}</p>
                                    <p class="text-xs text-slate">{{ $kasus->kategori_masalah }}</p>
                                </div>
                                <x-badge tone="brass">{{ $kasus->status->label() }}</x-badge>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-panel>
        @endif
    </div>
</x-app-layout>
