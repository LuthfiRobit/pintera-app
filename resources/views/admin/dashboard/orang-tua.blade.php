<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Orang Tua &middot; Ringkasan</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Dashboard Orang Tua</h2>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-8">
        <x-hero-banner
            eyebrow="Portal Orang Tua"
            :title="'Selamat datang, ' . Auth::user()->name . '!'"
            subtitle="Ringkasan tagihan, presensi, dan jadwal sekolah anak Anda."
        />

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <x-stat-tile
                label="Tagihan Belum Lunas"
                value="Rp {{ number_format($tagihanBelumLunas, 0, ',', '.') }}"
                hint="Total dari seluruh anak Anda"
                icon="payments"
            />
            <x-stat-tile
                label="Anak Terdaftar"
                :value="$anakList->count() . ' Siswa'"
                :hint="$anakList->pluck('nama_lengkap')->join(', ')"
                icon="family_restroom"
            />
        </div>

        <x-panel>
            <div class="border-b border-ink/10 px-6 py-4">
                <h3 class="font-display font-semibold text-ink">Jadwal Pelajaran Anak Hari Ini</h3>
            </div>
            @if ($jadwalAnakHariIni->isEmpty())
                <p class="px-6 py-8 text-center text-sm text-slate">Tidak ada jadwal pelajaran anak untuk hari ini.</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-ink/10 bg-paper/60 text-left text-xs uppercase tracking-wide text-slate">
                            <th class="px-6 py-3 font-display font-semibold">Jam</th>
                            <th class="px-6 py-3 font-display font-semibold">Kelas</th>
                            <th class="px-6 py-3 font-display font-semibold">Mata Pelajaran</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink/10">
                        @foreach ($jadwalAnakHariIni as $jadwal)
                            <tr>
                                <td class="px-6 py-3.5">{{ $jadwal->jamPelajaran?->jam_mulai }} - {{ $jadwal->jamPelajaran?->jam_selesai }}</td>
                                <td class="px-6 py-3.5 font-medium text-ink">{{ $jadwal->kelas?->nama }}</td>
                                <td class="px-6 py-3.5">{{ $jadwal->mataPelajaran?->nama }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-panel>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
            <x-stat-tile label="Diajukan" :value="$kasusStats['diajukan']" icon="assignment_late" />
            <x-stat-tile label="Menunggu Consent" :value="$kasusStats['menunggu_consent']" icon="hourglass_empty" />
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
                                    <x-badge tone="{{ $kasus->status->badgeTone() }}">{{ $kasus->status->label() }}</x-badge>
                                </a>
                            @else
                                <div class="flex items-center justify-between text-slate">
                                    <div>
                                        <p class="text-sm font-semibold text-ink">{{ $kasus->siswa->nama_lengkap }}</p>
                                        <p class="text-xs text-slate">{{ $kasus->kategori_masalah }}</p>
                                    </div>
                                    <x-badge tone="{{ $kasus->status->badgeTone() }}">{{ $kasus->status->label() }}</x-badge>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-panel>
    </div>
</x-app-layout>
