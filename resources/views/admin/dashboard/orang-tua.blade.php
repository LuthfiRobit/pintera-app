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
    </div>
</x-app-layout>
