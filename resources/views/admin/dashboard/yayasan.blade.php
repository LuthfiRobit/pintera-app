<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Yayasan &middot; Ringkasan Konsolidasi</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Dashboard Yayasan</h2>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-8">
        <x-hero-banner
            eyebrow="Yayasan &middot; Pengawasan Lintas Lembaga"
            :title="'Halo, ' . Auth::user()->name . '!'"
            subtitle="Pantau seluruh lembaga di bawah yayasan dari satu tempat — data, staf, dan tahun ajaran yang sedang berjalan."
        />

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat-tile label="Lembaga" :value="$stats['lembaga']" hint="Total unit pendidikan" icon="apartment" />
            <x-stat-tile label="Guru" :value="$stats['guru']" hint="Terdaftar lintas lembaga" icon="school" />
            <x-stat-tile label="Pengguna" :value="$stats['pengguna']" hint="Akun aktif sistem" icon="group" />
            <x-stat-tile label="Tahun Ajaran Aktif" :value="$stats['tahunAjaranAktif']" hint="Berjalan saat ini" icon="calendar_month" />
        </div>

        <x-panel>
            <div class="border-b border-ink/10 px-6 py-4">
                <h3 class="font-display font-semibold text-ink">Tinjau sebagai lembaga tertentu</h3>
                <p class="mt-0.5 text-sm text-slate">Pilih satu lembaga untuk menyaring seluruh data di sistem, atau kembali melihat semua lembaga sekaligus.</p>
            </div>

            <div class="flex flex-wrap gap-3 px-6 py-5">
                <a
                    href="{{ route('dashboard', ['switch_lembaga' => 'all']) }}"
                    class="inline-flex items-center rounded-xl border border-ink/15 px-4 py-2 text-sm text-ink transition hover:bg-paper"
                >
                    Semua Lembaga
                </a>
                @foreach ($lembagaList as $lembaga)
                    <a
                        href="{{ route('dashboard', ['switch_lembaga' => $lembaga->id]) }}"
                        class="inline-flex items-center rounded-xl bg-brass/10 px-4 py-2 text-sm text-ink transition hover:bg-brass/20"
                    >
                        <span class="font-display font-medium">{{ $lembaga->nama }}</span>
                    </a>
                @endforeach
            </div>
        </x-panel>
    </div>
</x-app-layout>
