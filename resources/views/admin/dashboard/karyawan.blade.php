<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6 pt-2">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- Main Content Area (8 Cols) --}}
            <div class="space-y-6 lg:col-span-7 xl:col-span-8">
                
                {{-- 1. Modern Gradient Hero Banner --}}
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-cyan-600 via-teal-600 to-blue-600 p-8 text-white shadow-xl shadow-cyan-500/10">
                    <div class="relative z-10 max-w-xl">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3.5 py-1 text-xs font-semibold backdrop-blur-md text-white">
                            <span class="h-2 w-2 rounded-full bg-emerald-300 animate-pulse"></span>
                            Portal Karyawan Active
                        </span>
                        <h1 class="mt-4 flex items-center gap-2.5 font-display text-2xl font-bold tracking-tight sm:text-3xl">
                            <span>Selamat datang, {{ Auth::user()->name }}!</span>
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 backdrop-blur-md">
                                <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 3h2" />
                                </svg>
                            </span>
                        </h1>
                        <p class="mt-2 text-sm leading-relaxed text-cyan-100">
                            {{ $karyawan ? 'Ringkasan singkat status kepegawaian, kuota cuti, dan jadwal shift kerja Anda hari ini.' : 'Profil karyawan Anda belum tertaut. Hubungi admin yayasan/sekolah untuk informasi lebih lanjut.' }}
                        </p>
                    </div>

                    {{-- Subtle Right Illustration Overlay --}}
                    <div class="absolute -right-4 -bottom-8 pointer-events-none hidden sm:block opacity-20">
                        <div class="h-48 w-48 rounded-full border-8 border-white/30"></div>
                        <div class="absolute -bottom-10 -right-10 h-64 w-64 rounded-full border-8 border-white/20"></div>
                    </div>
                </div>

                {{-- 2. Stat Tiles Row --}}
                @if ($karyawan)
                    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        <x-stat-tile
                            :tone="$presensiHariIni?->status?->badgeTone() ?? 'amber'"
                            label="Presensi Hari Ini"
                            :value="is_object($presensiHariIni) ? $presensiHariIni->status?->label() : ($presensiHariIni['status'] ?? 'Belum Absen')"
                            :hint="$karyawan->jenisKaryawan?->nama"
                            icon="badge"
                        />
                        <x-stat-tile
                            :tone="! $sisaKuotaCuti ? 'default' : ($sisaKuotaCuti['sisa'] <= 0 ? 'red' : ($sisaKuotaCuti['sisa'] <= 3 ? 'amber' : 'green'))"
                            label="Sisa Kuota Cuti"
                            :value="$sisaKuotaCuti ? $sisaKuotaCuti['sisa'] . ' Hari' : 'N/A'"
                            :hint="$sisaKuotaCuti ? 'Dari total ' . $sisaKuotaCuti['jatah'] . ' hari/tahun' : 'Belum dikonfigurasi'"
                            icon="beach_access"
                        />
                        <x-stat-tile
                            :tone="$jadwalShiftHariIni?->jenisShift ? 'blue' : 'default'"
                            label="Shift Hari Ini"
                            :value="$jadwalShiftHariIni?->jenisShift?->nama_shift ?? 'Tanpa Shift'"
                            :hint="$jadwalShiftHariIni?->jenisShift ? $jadwalShiftHariIni->jenisShift->jam_masuk . ' - ' . $jadwalShiftHariIni->jenisShift->jam_pulang : 'Libur/Rutin'"
                            icon="schedule"
                        />
                        <x-stat-tile :tone="$izinCutiPending > 0 ? 'amber' : 'green'" label="Approval Pending" :value="$izinCutiPending" icon="hourglass_empty" />
                    </div>

                    {{-- 3. Detail Profil Karyawan Card --}}
                    <x-panel class="p-6">
                        <div class="flex items-center justify-between pb-4 border-b border-ink/10">
                            <div>
                                <h3 class="font-display font-bold text-lg text-ink">Profil Kepegawaian</h3>
                                <p class="text-xs text-slate">Detail informasi akun staf karyawan terdaftar</p>
                            </div>
                        </div>
                        @php
                            $name = Auth::user()->name;
                            $initials = collect(explode(' ', $name))->map(fn($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('');
                        @endphp
                        <div class="mt-4 flex items-center gap-5 rounded-2xl border border-ink/10 bg-paper/40 p-5">
                            <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-tr from-cyan-600 to-teal-600 font-display font-bold text-white text-xl shadow-md">
                                {{ $initials }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <h4 class="font-display font-bold text-lg text-ink">{{ $name }}</h4>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                    <span class="rounded-full bg-cyan-50 px-3 py-1 font-semibold text-cyan-700">
                                        {{ $karyawan->jenisKaryawan?->nama ?? 'Staf Karyawan' }}
                                    </span>
                                    @if($karyawan->nip)
                                        <span class="rounded-full bg-paper border border-ink/10 px-3 py-1 font-medium text-slate">
                                            NIP: {{ $karyawan->nip }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </x-panel>
                @endif

                {{-- 3b. Riwayat Presensi 30 Hari Terakhir --}}
                @if ($karyawan)
                    <x-panel class="p-6">
                        <p class="mb-3 text-sm font-medium text-ink">Riwayat Presensi 30 Hari Terakhir</p>
                        <div class="relative h-56 w-full" x-data="presensiBulananChart(
                            @js($riwayatPresensi30Hari['labels']),
                            @js($riwayatPresensi30Hari['hadir']),
                            @js($riwayatPresensi30Hari['izin']),
                            @js($riwayatPresensi30Hari['sakit']),
                            @js($riwayatPresensi30Hari['alpa'])
                        )">
                            <canvas x-ref="canvas"></canvas>
                        </div>
                    </x-panel>
                @endif

                {{-- 4. Kasus Pendampingan yang Saya Tangani --}}
                @if ($kasusDitangani->isNotEmpty())
                    <x-panel class="p-6">
                        <div class="flex items-center justify-between pb-4 border-b border-ink/10">
                            <div>
                                <h3 class="font-display font-bold text-lg text-ink">Kasus Pendampingan yang Saya Tangani</h3>
                                <p class="text-xs text-slate">Daftar penanganan kasus konseling siswa aktif</p>
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-6">
                            <div class="rounded-xl bg-paper/80 p-2.5 text-center border border-ink/5">
                                <p class="text-[10px] font-bold uppercase text-slate">Diajukan</p>
                                <p class="mt-1 text-base font-bold text-ink">{{ $kasusDitanganiStats['diajukan'] }}</p>
                            </div>
                            <div class="rounded-xl bg-paper/80 p-2.5 text-center border border-ink/5">
                                <p class="text-[10px] font-bold uppercase text-slate">Consent</p>
                                <p class="mt-1 text-base font-bold text-amber-600">{{ $kasusDitanganiStats['menunggu_consent'] }}</p>
                            </div>
                            <div class="rounded-xl bg-paper/80 p-2.5 text-center border border-ink/5">
                                <p class="text-[10px] font-bold uppercase text-slate">Ditugaskan</p>
                                <p class="mt-1 text-base font-bold text-blue-600">{{ $kasusDitanganiStats['ditugaskan'] }}</p>
                            </div>
                            <div class="rounded-xl bg-paper/80 p-2.5 text-center border border-ink/5">
                                <p class="text-[10px] font-bold uppercase text-slate">Berjalan</p>
                                <p class="mt-1 text-base font-bold text-indigo-600">{{ $kasusDitanganiStats['berjalan'] }}</p>
                            </div>
                            <div class="rounded-xl bg-paper/80 p-2.5 text-center border border-ink/5">
                                <p class="text-[10px] font-bold uppercase text-slate">Eskalasi</p>
                                <p class="mt-1 text-base font-bold text-rose-600">{{ $kasusDitanganiStats['eskalasi'] }}</p>
                            </div>
                            <div class="rounded-xl bg-paper/80 p-2.5 text-center border border-ink/5">
                                <p class="text-[10px] font-bold uppercase text-slate">Selesai</p>
                                <p class="mt-1 text-base font-bold text-emerald-600">{{ $kasusDitanganiStats['selesai'] }}</p>
                            </div>
                        </div>

                        <ul class="mt-4 divide-y divide-ink/10">
                            @foreach ($kasusDitangani as $kasus)
                                <li class="py-3">
                                    <a href="{{ route('kasus.show', $kasus) }}" class="flex items-center justify-between hover:text-brass transition">
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

            {{-- Right Sidebar Area (4 Cols) --}}
            <div class="space-y-6 lg:col-span-5 xl:col-span-4 lg:sticky lg:top-6 lg:self-start">
                
                {{-- 1. Widget Information Shift Kerja --}}
                @if ($karyawan)
                    <x-panel class="p-6">
                        <div class="flex items-center justify-between pb-3 border-b border-ink/10">
                            <h3 class="font-display font-bold text-sm uppercase tracking-wider text-slate">Jadwal Shift Kerja</h3>
                            <span class="text-xs font-bold text-cyan-700 uppercase">{{ now()->translatedFormat('d F Y') }}</span>
                        </div>
                        <div class="mt-4 rounded-2xl border border-cyan-100 bg-cyan-50/50 p-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-cyan-600 text-white shadow-sm">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-display font-bold text-sm text-ink">
                                        {{ $jadwalShiftHariIni?->jenisShift?->nama_shift ?? 'Jam Kerja Rutin / Libur' }}
                                    </h4>
                                    <p class="text-xs text-slate mt-0.5">
                                        {{ $jadwalShiftHariIni?->jenisShift ? $jadwalShiftHariIni->jenisShift->jam_masuk . ' - ' . $jadwalShiftHariIni->jenisShift->jam_pulang : 'Sesuai jam kerja operasional' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </x-panel>
                @endif

                {{-- 2. Widget Mini Kalender Minggu Ini --}}
                <x-panel class="p-6">
                    <div class="flex items-center justify-between pb-3 border-b border-ink/10">
                        <h3 class="font-display font-bold text-sm uppercase tracking-wider text-slate">Jadwal Minggu Ini</h3>
                        <span class="text-xs font-bold text-cyan-700 uppercase">{{ now()->translatedFormat('F Y') }}</span>
                    </div>

                    @php
                        $startOfWeek = now()->startOfWeek();
                    @endphp
                    <div class="mt-4 grid grid-cols-7 gap-1.5 text-center">
                        @foreach (['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'] as $dayName)
                            <div class="text-[10px] font-bold uppercase text-slate/70 py-1">{{ $dayName }}</div>
                        @endforeach
                        @for ($i = 0; $i < 7; $i++)
                            @php
                                $date = $startOfWeek->copy()->addDays($i);
                                $isToday = $date->isToday();
                            @endphp
                            <div class="flex flex-col items-center py-1.5">
                                <span class="h-8 w-8 flex items-center justify-center rounded-full text-xs font-bold transition {{ $isToday ? 'bg-cyan-600 text-white shadow-md shadow-cyan-500/30' : 'text-ink hover:bg-paper' }}">
                                    {{ $date->format('j') }}
                                </span>
                            </div>
                        @endfor
                    </div>
                </x-panel>
            </div>
        </div>
    </div>
</x-app-layout>
