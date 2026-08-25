<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Karyawan &middot; Ringkasan</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Dashboard Karyawan</h2>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-8">
        <x-hero-banner
            eyebrow="Portal Karyawan"
            :title="'Selamat datang, ' . Auth::user()->name . '!'"
            :subtitle="$karyawan ? 'Ringkasan singkat status kepegawaian Anda hari ini.' : 'Profil karyawan Anda belum tertaut. Hubungi admin yayasan/sekolah untuk informasi lebih lanjut.'"
        />

        @if ($karyawan)
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
                <x-stat-tile
                    label="Presensi Hari Ini"
                    :value="$presensiHariIni?->status?->label() ?? 'Belum Absen'"
                    :hint="$karyawan->jenisKaryawan?->nama"
                    icon="badge"
                />
                <x-stat-tile label="Izin/Cuti Menunggu Persetujuan" :value="$izinCutiPending" icon="hourglass_empty" />
                <x-stat-tile label="Unit/Lembaga" :value="$karyawan->lembaga?->nama ?? 'Pool Yayasan'" icon="apartment" />
            </div>
        @endif

        @if ($kasusDitangani->isNotEmpty())
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
                <x-stat-tile label="Diajukan" :value="$kasusDitanganiStats['diajukan']" icon="assignment_late" />
                <x-stat-tile label="Menunggu Consent" :value="$kasusDitanganiStats['menunggu_consent']" icon="hourglass_empty" />
                <x-stat-tile label="Ditugaskan" :value="$kasusDitanganiStats['ditugaskan']" icon="assignment" />
                <x-stat-tile label="Berjalan" :value="$kasusDitanganiStats['berjalan']" icon="pending_actions" />
                <x-stat-tile label="Eskalasi" :value="$kasusDitanganiStats['eskalasi']" icon="priority_high" />
                <x-stat-tile label="Selesai" :value="$kasusDitanganiStats['selesai']" icon="check_circle" />
            </div>
            <x-panel>
                <div class="border-b border-ink/10 px-6 py-4">
                    <h3 class="font-display font-semibold text-ink">Kasus Pendampingan yang Saya Tangani</h3>
                </div>
                <ul class="divide-y divide-ink/10">
                    @foreach ($kasusDitangani as $kasus)
                        <li class="px-6 py-3">
                            <a href="{{ route('kasus.show', $kasus) }}" class="flex items-center justify-between hover:text-brass">
                                <div>
                                    <p class="text-sm font-semibold text-ink">{{ $kasus->siswa->nama_lengkap }}</p>
                                    <p class="text-xs text-slate">{{ $kasus->kategori_masalah }}</p>
                                </div>
                                <x-badge tone="{{ $kasus->status->badgeTone() }}">{{ $kasus->status->label() }}</x-badge>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-panel>
        @endif
    </div>
</x-app-layout>
