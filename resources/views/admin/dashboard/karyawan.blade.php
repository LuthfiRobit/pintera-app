<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Karyawan &middot; Ringkasan</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Dashboard Karyawan</h2>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-8">
        <x-hero-banner
            eyebrow="Portal Karyawan"
            :title="'Selamat datang, ' . Auth::user()->name . '!'"
            subtitle="Dashboard khusus untuk peran ini belum tersedia. Hubungi admin yayasan/sekolah untuk informasi lebih lanjut."
        />
    </div>
</x-app-layout>
