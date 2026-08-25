<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6 pt-2">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- Main Content Area (8 Cols) --}}
            <div class="space-y-6 lg:col-span-7 xl:col-span-8">
                
                {{-- 1. Modern Gradient Hero Banner --}}
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-blue-600 via-indigo-600 to-sky-500 p-8 text-white shadow-xl shadow-blue-500/10">
                    <div class="relative z-10 max-w-xl">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3.5 py-1 text-xs font-semibold backdrop-blur-md text-white">
                            <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            Portal Orang Tua Active
                        </span>
                        <h1 class="mt-4 flex items-center gap-2.5 font-display text-2xl font-bold tracking-tight sm:text-3xl">
                            <span>Selamat datang, {{ Auth::user()->name }}!</span>
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 backdrop-blur-md">
                                <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                        </h1>
                        <p class="mt-2 text-sm leading-relaxed text-blue-100">
                            Pantau perkembangan akademik, keaktifan presensi, dan informasi kewajiban tagihan sekolah anak Anda secara realtime dalam satu portal.
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
                    {{-- Tagihan Tile --}}
                    <x-stat-tile
                        label="Tagihan Belum Lunas"
                        value="Rp {{ number_format($tagihanBelumLunas, 0, ',', '.') }}"
                        hint="Total dari seluruh anak Anda"
                        icon="payments"
                    />

                    {{-- Anak Terdaftar Tile --}}
                    <x-stat-tile
                        label="Anak Terdaftar"
                        :value="$anakList->count() . ' Siswa'"
                        :hint="$anakList->pluck('nama_lengkap')->join(', ')"
                        icon="family_restroom"
                    />
                </div>

                {{-- 3. Daftar Anak Terdaftar (Card Avatar List) --}}
                <x-panel class="p-6">
                    <div class="flex items-center justify-between pb-4 border-b border-ink/10">
                        <div>
                            <h3 class="font-display font-bold text-lg text-ink">Daftar Anak &amp; Kelas</h3>
                            <p class="text-xs text-slate">Informasi profil anak yang terhubung dengan akun Anda</p>
                        </div>
                    </div>
                    @if ($anakList->isEmpty())
                        <p class="py-8 text-center text-sm text-slate">Belum ada data siswa yang terhubung.</p>
                    @else
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            @foreach ($anakList as $index => $anak)
                                @php
                                    $bgColors = ['bg-amber-500', 'bg-blue-600', 'bg-emerald-500', 'bg-purple-600', 'bg-rose-500'];
                                    $bgColor = $bgColors[$index % count($bgColors)];
                                    $initials = collect(explode(' ', $anak->nama_lengkap))->map(fn($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('');
                                @endphp
                                <div class="flex items-center gap-4 rounded-2xl border border-ink/10 bg-paper/40 p-4 transition hover:border-blue-300 hover:shadow-sm">
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl {{ $bgColor }} font-display font-bold text-white shadow-md">
                                        {{ $initials }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h4 class="truncate font-display font-bold text-sm text-ink">{{ $anak->nama_lengkap }}</h4>
                                        <p class="text-xs text-slate mt-0.5">{{ $anak->kelas?->nama ?? 'Belum Ditentukan' }}</p>
                                        @if($anak->nisn)
                                            <span class="mt-1.5 inline-block rounded-full bg-blue-50 px-2.5 py-0.5 text-[10px] font-semibold text-blue-600">NISN: {{ $anak->nisn }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-panel>

                {{-- 4. Kasus Pendampingan --}}
                <x-panel class="p-6">
                    <div class="flex items-center justify-between pb-4 border-b border-ink/10">
                        <div>
                            <h3 class="font-display font-bold text-lg text-ink">Kasus Pendampingan</h3>
                            <p class="text-xs text-slate">Catatan dan status pendampingan konseling anak</p>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-6">
                        <div class="rounded-xl bg-paper/80 p-2.5 text-center border border-ink/5">
                            <p class="text-[10px] font-bold uppercase text-slate">Diajukan</p>
                            <p class="mt-1 text-base font-bold text-ink">{{ $kasusStats['diajukan'] }}</p>
                        </div>
                        <div class="rounded-xl bg-paper/80 p-2.5 text-center border border-ink/5">
                            <p class="text-[10px] font-bold uppercase text-slate">Consent</p>
                            <p class="mt-1 text-base font-bold text-amber-600">{{ $kasusStats['menunggu_consent'] }}</p>
                        </div>
                        <div class="rounded-xl bg-paper/80 p-2.5 text-center border border-ink/5">
                            <p class="text-[10px] font-bold uppercase text-slate">Ditugaskan</p>
                            <p class="mt-1 text-base font-bold text-blue-600">{{ $kasusStats['ditugaskan'] }}</p>
                        </div>
                        <div class="rounded-xl bg-paper/80 p-2.5 text-center border border-ink/5">
                            <p class="text-[10px] font-bold uppercase text-slate">Berjalan</p>
                            <p class="mt-1 text-base font-bold text-indigo-600">{{ $kasusStats['berjalan'] }}</p>
                        </div>
                        <div class="rounded-xl bg-paper/80 p-2.5 text-center border border-ink/5">
                            <p class="text-[10px] font-bold uppercase text-slate">Eskalasi</p>
                            <p class="mt-1 text-base font-bold text-rose-600">{{ $kasusStats['eskalasi'] }}</p>
                        </div>
                        <div class="rounded-xl bg-paper/80 p-2.5 text-center border border-ink/5">
                            <p class="text-[10px] font-bold uppercase text-slate">Selesai</p>
                            <p class="mt-1 text-base font-bold text-emerald-600">{{ $kasusStats['selesai'] }}</p>
                        </div>
                    </div>

                    @if ($kasusList->isEmpty())
                        <p class="mt-4 py-4 text-center text-xs text-slate">Tidak ada kasus pendampingan untuk anak Anda.</p>
                    @else
                        <ul class="mt-4 divide-y divide-ink/10">
                            @foreach ($kasusList as $kasus)
                                <li class="py-3">
                                    @if (in_array($kasus->id, $kontakUtamaKasusIds, true))
                                        <a href="{{ route('kasus.show', $kasus) }}" class="flex items-center justify-between hover:text-brass transition">
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

            {{-- Right Sidebar Area (4 Cols) --}}
            <div class="space-y-6 lg:col-span-5 xl:col-span-4">
                
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

                {{-- 2. Timeline Jadwal Pelajaran Anak Hari Ini (Reference Card Style) --}}
                <x-panel class="p-6">
                    <div class="flex items-center justify-between pb-4 border-b border-ink/10">
                        <div>
                            <h3 class="font-display font-bold text-base text-ink">Jadwal Pelajaran Anak Hari Ini</h3>
                            <p class="text-xs text-slate">{{ now()->translatedFormat('l, d F Y') }}</p>
                        </div>
                    </div>

                    @if ($jadwalAnakHariIni->isEmpty())
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
                                    ['bg' => 'bg-blue-500', 'cardBg' => 'bg-blue-50/50 hover:bg-blue-50 border-blue-100'],
                                    ['bg' => 'bg-pink-500', 'cardBg' => 'bg-pink-50/50 hover:bg-pink-50 border-pink-100'],
                                    ['bg' => 'bg-emerald-500', 'cardBg' => 'bg-emerald-50/50 hover:bg-emerald-50 border-emerald-100'],
                                    ['bg' => 'bg-orange-500', 'cardBg' => 'bg-orange-50/50 hover:bg-orange-50 border-orange-100'],
                                    ['bg' => 'bg-purple-500', 'cardBg' => 'bg-purple-50/50 hover:bg-purple-50 border-purple-100'],
                                ];
                            @endphp
                            @foreach ($jadwalAnakHariIni as $index => $jadwal)
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
                                                {{ $jadwal->jamPelajaran?->jam_mulai }} - {{ $jadwal->jamPelajaran?->jam_selesai }} &middot; <span class="font-medium text-ink/70">{{ $jadwal->kelas?->nama }}</span>
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
