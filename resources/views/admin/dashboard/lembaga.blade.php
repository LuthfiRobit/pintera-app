<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Lembaga &middot; Ringkasan</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Dashboard</h2>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-8">
        <x-hero-banner
            eyebrow="Panel Administrasi Lembaga"
            :title="'Halo, ' . Auth::user()->name . '!'"
            subtitle="Kelola data guru, pengguna, dan tahun ajaran di lembaga Anda."
        />

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
            <x-stat-tile label="Guru" :value="$stats['guru']" hint="Terdaftar di lembaga Anda" icon="school" />
            <x-stat-tile label="Pengguna" :value="$stats['pengguna']" hint="Akun aktif di lembaga Anda" icon="group" />
            <x-stat-tile label="Tahun Ajaran Aktif" :value="$stats['tahunAjaranAktif']" :hint="$tahunAjaranAktif->nama ?? 'Belum diaktifkan'" icon="calendar_month" />
        </div>

        <x-panel class="p-6 text-sm text-slate">
            Modul Akademik, E-Sarpra, E-HRD, dan E-BK menyusul di fase berikutnya.
        </x-panel>
    </div>
</x-app-layout>
