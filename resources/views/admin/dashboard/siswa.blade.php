<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6 pt-2">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- Main Content Area (8 Cols) --}}
            <div class="space-y-6 lg:col-span-7 xl:col-span-8">
                
                {{-- 1. Modern Gradient Hero Banner --}}
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-sky-500 via-blue-600 to-indigo-600 p-8 text-white shadow-xl shadow-blue-500/10">
                    <div class="relative z-10 max-w-xl">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3.5 py-1 text-xs font-semibold backdrop-blur-md text-white">
                            <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            Dashboard Siswa Active
                        </span>
                        <h1 class="mt-4 flex items-center gap-2.5 font-display text-2xl font-bold tracking-tight sm:text-3xl">
                            <span>Selamat datang, {{ Auth::user()->name }}!</span>
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 backdrop-blur-md">
                                <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                </svg>
                            </span>
                        </h1>
                        <p class="mt-2 text-sm leading-relaxed text-blue-100">
                            Pantau jadwal pelajaran harian, info kelas aktif, dan status kewajiban tagihan sekolahmu secara realtime.
                        </p>
                    </div>

                    {{-- Subtle Right Illustration Overlay --}}
                    <div class="absolute -right-4 -bottom-8 pointer-events-none hidden sm:block opacity-20">
                        <div class="h-48 w-48 rounded-full border-8 border-white/30"></div>
                        <div class="absolute -bottom-10 -right-10 h-64 w-64 rounded-full border-8 border-white/20"></div>
                    </div>
                </div>

                {{-- 2. Stat Tiles Row --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    {{-- Kelas Saya Tile --}}
                    <x-stat-tile
                        label="Kelas Saya"
                        :value="$siswa?->kelas?->nama ?? 'Belum Ada Kelas'"
                        :hint="$siswa?->nisn ? 'NISN: ' . $siswa->nisn : 'Status: Siswa Aktif'"
                        icon="school"
                    />

                    {{-- Tagihan Tile --}}
                    <x-stat-tile
                        label="Tagihan Belum Lunas"
                        value="Rp {{ number_format($tagihanBelumLunas, 0, ',', '.') }}"
                        hint="Status tagihan sekolah"
                        icon="payments"
                    />
                </div>

                {{-- 2b. Rekap Presensi Bulan Ini --}}
                <x-panel class="p-6">
                    <p class="mb-3 text-sm font-medium text-ink">Rekap Presensi Bulan Ini</p>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                        <x-stat-tile label="Hadir" :value="$presensiBulanIni['hadir']" icon="check_circle" />
                        <x-stat-tile label="Izin" :value="$presensiBulanIni['izin']" icon="hourglass_empty" />
                        <x-stat-tile label="Sakit" :value="$presensiBulanIni['sakit']" icon="local_hospital" />
                        <x-stat-tile label="Alpa" :value="$presensiBulanIni['alpa']" icon="cancel" />
                        <x-stat-tile label="Terlambat" :value="$presensiBulanIni['terlambat']" icon="schedule" />
                    </div>
                </x-panel>

                {{-- 3. Profil Siswa Card --}}
                <x-panel class="p-6">
                    <div class="flex items-center justify-between pb-4 border-b border-ink/10">
                        <div>
                            <h3 class="font-display font-bold text-lg text-ink">Profil Akademik Siswa</h3>
                            <p class="text-xs text-slate">Detail informasi akun siswa terdaftar</p>
                        </div>
                    </div>
                    
                    @php
                        $name = Auth::user()->name;
                        $initials = collect(explode(' ', $name))->map(fn($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('');
                    @endphp
                    <div class="mt-4 flex items-center gap-5 rounded-2xl border border-ink/10 bg-paper/40 p-5">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-600 font-display font-bold text-white text-xl shadow-md">
                            {{ $initials }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <h4 class="font-display font-bold text-lg text-ink">{{ $name }}</h4>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                <span class="rounded-full bg-blue-50 px-3 py-1 font-semibold text-blue-600">
                                    Kelas: {{ $siswa?->kelas?->nama ?? 'Belum Ada Kelas' }}
                                </span>
                                @if($siswa?->nisn)
                                    <span class="rounded-full bg-paper border border-ink/10 px-3 py-1 font-medium text-slate">
                                        NISN: {{ $siswa->nisn }}
                                    </span>
                                @endif
                                @if($siswa?->nis)
                                    <span class="rounded-full bg-paper border border-ink/10 px-3 py-1 font-medium text-slate">
                                        NIS: {{ $siswa->nis }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </x-panel>

                {{-- 3b. Nilai Terbaru --}}
                @if ($nilaiTerbaru->isNotEmpty())
                    <x-panel class="p-6">
                        <div class="flex items-center justify-between pb-4 border-b border-ink/10">
                            <div>
                                <h3 class="font-display font-bold text-lg text-ink">Nilai Terbaru</h3>
                                <p class="text-xs text-slate">5 nilai terakhir yang tercatat</p>
                            </div>
                        </div>
                        <ul class="mt-4 divide-y divide-ink/10">
                            @foreach ($nilaiTerbaru as $nilai)
                                <li class="flex items-center justify-between py-2.5 text-sm">
                                    <span class="text-ink">{{ $nilai->komponenPenilaian?->mataPelajaran?->nama ?? $nilai->asesmen?->mataPelajaran?->nama ?? '-' }}</span>
                                    <x-badge tone="brass">{{ $nilai->nilai_angka }}</x-badge>
                                </li>
                            @endforeach
                        </ul>
                    </x-panel>
                @endif
            </div>

            {{-- Right Sidebar Area (4 Cols) --}}
            <div class="space-y-6 lg:col-span-5 xl:col-span-4 lg:sticky lg:top-6 lg:self-start">
                
                {{-- 1. Widget Mini Kalender Minggu Ini --}}
                <x-panel class="p-6">
                    <div class="flex items-center justify-between pb-3 border-b border-ink/10">
                        <h3 class="font-display font-bold text-sm uppercase tracking-wider text-slate">Jadwal Minggu Ini</h3>
                        <span class="text-xs font-bold text-blue-600 uppercase">{{ now()->translatedFormat('F Y') }}</span>
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
                                <span class="h-8 w-8 flex items-center justify-center rounded-full text-xs font-bold transition {{ $isToday ? 'bg-blue-600 text-white shadow-md shadow-blue-500/30' : 'text-ink hover:bg-paper' }}">
                                    {{ $date->format('j') }}
                                </span>
                            </div>
                        @endfor
                    </div>
                </x-panel>

                {{-- 2. Timeline Jadwal Pelajaran Hari Ini --}}
                <x-panel class="p-6">
                    <div class="flex items-center justify-between pb-4 border-b border-ink/10">
                        <div>
                            <h3 class="font-display font-bold text-base text-ink">Jadwal Pelajaran Hari Ini</h3>
                            <p class="text-xs text-slate">{{ now()->translatedFormat('l, d F Y') }}</p>
                        </div>
                    </div>

                    @if ($jadwalHariIni->isEmpty())
                        <div class="mt-6 py-8 text-center">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <p class="mt-3 text-xs font-medium text-slate">Tidak ada jadwal pelajaran hari ini.</p>
                        </div>
                    @else
                        <div class="mt-4 space-y-3">
                            @php
                                $badgeStyles = [
                                    ['bg' => 'bg-sky-500', 'cardBg' => 'bg-sky-50/50 hover:bg-sky-50 border-sky-100'],
                                    ['bg' => 'bg-blue-500', 'cardBg' => 'bg-blue-50/50 hover:bg-blue-50 border-blue-100'],
                                    ['bg' => 'bg-indigo-500', 'cardBg' => 'bg-indigo-50/50 hover:bg-indigo-50 border-indigo-100'],
                                    ['bg' => 'bg-purple-500', 'cardBg' => 'bg-purple-50/50 hover:bg-purple-50 border-purple-100'],
                                    ['bg' => 'bg-emerald-500', 'cardBg' => 'bg-emerald-50/50 hover:bg-emerald-50 border-emerald-100'],
                                ];
                            @endphp
                            @foreach ($jadwalHariIni as $index => $jadwal)
                                @php
                                    $style = $badgeStyles[$index % count($badgeStyles)];
                                @endphp
                                <div class="flex items-center justify-between rounded-2xl border p-3.5 transition {{ $style['cardBg'] }}">
                                    <div class="flex items-center gap-3.5 min-w-0">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $style['bg'] }} font-display font-bold text-white text-xs shadow-sm">
                                            {{ $index + 1 }}
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <h4 class="truncate font-display font-bold text-xs text-ink">
                                                {{ $jadwal->mataPelajaran?->nama }}
                                            </h4>
                                            <p class="text-[11px] text-slate/80 mt-0.5">
                                                {{ $jadwal->jamPelajaran?->jam_mulai }} - {{ $jadwal->jamPelajaran?->jam_selesai }}
                                            </p>
                                        </div>
                                    </div>
                                    <svg class="h-4 w-4 shrink-0 text-slate/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-panel>
            </div>
        </div>
    </div>
</x-app-layout>
