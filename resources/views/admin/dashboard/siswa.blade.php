<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Siswa &middot; Ringkasan</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Dashboard Siswa</h2>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-8">
        <x-hero-banner
            eyebrow="Portal Siswa"
            :title="'Halo, ' . Auth::user()->name . '!'"
            subtitle="Portal siswa (nilai, presensi, jadwal) belum tersedia. Hubungi admin sekolah untuk informasi akademikmu."
        />
    </div>
</x-app-layout>
