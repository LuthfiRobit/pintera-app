<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Siswa &middot; Ringkasan</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Dashboard Siswa</h2>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-8">
        <x-hero-banner
            eyebrow="Portal Siswa"
            :title="'Halo, ' . Auth::user()->name . '!'"
            subtitle="Ringkasan jadwal pelajaran dan status tagihan sekolahmu hari ini."
        />

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <x-stat-tile
                label="Kelas Saya"
                :value="$siswa?->kelas?->nama ?? 'Belum Ada Kelas'"
                :hint="$siswa?->nisn ? 'NISN: ' . $siswa->nisn : ''"
                icon="school"
            />
            <x-stat-tile
                label="Tagihan Belum Lunas"
                value="Rp {{ number_format($tagihanBelumLunas, 0, ',', '.') }}"
                icon="payments"
            />
        </div>

        <x-panel>
            <div class="border-b border-ink/10 px-6 py-4">
                <h3 class="font-display font-semibold text-ink">Jadwal Pelajaran Hari Ini</h3>
            </div>
            @if ($jadwalHariIni->isEmpty())
                <p class="px-6 py-8 text-center text-sm text-slate">Tidak ada jadwal pelajaran hari ini.</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-ink/10 bg-paper/60 text-left text-xs uppercase tracking-wide text-slate">
                            <th class="px-6 py-3 font-display font-semibold">Jam</th>
                            <th class="px-6 py-3 font-display font-semibold">Mata Pelajaran</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink/10">
                        @foreach ($jadwalHariIni as $jadwal)
                            <tr>
                                <td class="px-6 py-3.5">{{ $jadwal->jamPelajaran?->jam_mulai }} - {{ $jadwal->jamPelajaran?->jam_selesai }}</td>
                                <td class="px-6 py-3.5 font-medium text-ink">{{ $jadwal->mataPelajaran?->nama }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-panel>
    </div>
</x-app-layout>
