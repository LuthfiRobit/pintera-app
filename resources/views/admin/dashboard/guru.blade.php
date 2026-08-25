<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6 pt-2">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- Main Content Area (8 Cols) --}}
            <div class="space-y-6 lg:col-span-7 xl:col-span-8">
                
                {{-- 1. Modern Gradient Hero Banner --}}
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-indigo-600 via-purple-600 to-blue-500 p-8 text-white shadow-xl shadow-indigo-500/10">
                    <div class="relative z-10 max-w-xl">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3.5 py-1 text-xs font-semibold backdrop-blur-md text-white">
                            <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            Dashboard Guru Active
                        </span>
                        <h1 class="mt-4 flex items-center gap-2.5 font-display text-2xl font-bold tracking-tight sm:text-3xl">
                            <span>Selamat datang, {{ Auth::user()->name }}!</span>
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 backdrop-blur-md">
                                <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0112 20.055a11.952 11.952 0 01-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                                </svg>
                            </span>
                        </h1>
                        <p class="mt-2 text-sm leading-relaxed text-indigo-100">
                            Ringkasan jadwal mengajar hari ini, tugas wali kelas, dan pendampingan konseling siswa dalam satu dashboard terpadu.
                        </p>
                    </div>

                    {{-- Subtle Right Illustration Overlay --}}
                    <div class="absolute -right-4 -bottom-8 pointer-events-none hidden sm:block opacity-20">
                        <div class="h-48 w-48 rounded-full border-8 border-white/30"></div>
                        <div class="absolute -bottom-10 -right-10 h-64 w-64 rounded-full border-8 border-white/20"></div>
                    </div>
                </div>

                {{-- 2. Progress Nilai Kelas Wali (If Applicable) --}}
                @if ($kelasWali && $progressKelasWali)
                    <x-panel class="p-6 border-l-4 border-l-indigo-500">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <span class="inline-block rounded-full bg-indigo-50 px-2.5 py-0.5 text-[11px] font-bold text-indigo-600">Wali Kelas: {{ $kelasWali->nama }}</span>
                                <h3 class="mt-2 font-display font-bold text-lg text-ink">Progress Pengumpulan Nilai Rapor</h3>
                                <p class="text-xs text-slate mt-0.5">
                                    {{ $progressKelasWali['terisi'] }} terisi dari total {{ $progressKelasWali['total'] }} slot komponen penilaian
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="relative flex h-16 w-16 items-center justify-center rounded-full bg-indigo-50 font-display font-bold text-indigo-600 text-lg shadow-inner">
                                    {{ $progressKelasWali['persen'] }}%
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-paper">
                            <div class="h-full bg-gradient-to-r from-indigo-500 to-blue-500 transition-all duration-500" style="width: {{ min(100, max(0, $progressKelasWali['persen'])) }}%"></div>
                        </div>
                    </x-panel>
                @endif

                {{-- 3. Jabatan Tugas Tambahan --}}
                @if ($jabatanTambahan->isNotEmpty())
                    <x-panel class="p-6">
                        <div class="flex items-center justify-between pb-4 border-b border-ink/10">
                            <div>
                                <h3 class="font-display font-bold text-lg text-ink">Jabatan Tugas Tambahan</h3>
                                <p class="text-xs text-slate">Tugas dan penugasan tambahan di sekolah</p>
                            </div>
                        </div>
                        <div class="mt-4 divide-y divide-ink/10">
                            @foreach ($jabatanTambahan as $jabatan)
                                <div class="flex items-center justify-between py-3">
                                    <span class="text-sm font-semibold text-ink">{{ $jabatan->nama }}</span>
                                    <x-badge tone="brass">{{ $jabatan->kelompok }}</x-badge>
                                </div>
                            @endforeach
                        </div>
                    </x-panel>
                @endif

                {{-- 4. Kasus Pendampingan yang Saya Ajukan --}}
                <x-panel class="p-6">
                    <div class="flex items-center justify-between pb-4 border-b border-ink/10">
                        <div>
                            <h3 class="font-display font-bold text-lg text-ink">Kasus Pendampingan yang Saya Ajukan</h3>
                            <p class="text-xs text-slate">Ringkasan status rujukan kasus siswa yang Anda ajukan</p>
                        </div>
                    </div>
                    
                    <div class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-6">
                        <div class="rounded-xl bg-paper/80 p-2.5 text-center border border-ink/5">
                            <p class="text-[10px] font-bold uppercase text-slate">Diajukan</p>
                            <p class="mt-1 text-base font-bold text-ink">{{ $kasusDiajukanStats['diajukan'] }}</p>
                        </div>
                        <div class="rounded-xl bg-paper/80 p-2.5 text-center border border-ink/5">
                            <p class="text-[10px] font-bold uppercase text-slate">Consent</p>
                            <p class="mt-1 text-base font-bold text-amber-600">{{ $kasusDiajukanStats['menunggu_consent'] }}</p>
                        </div>
                        <div class="rounded-xl bg-paper/80 p-2.5 text-center border border-ink/5">
                            <p class="text-[10px] font-bold uppercase text-slate">Ditugaskan</p>
                            <p class="mt-1 text-base font-bold text-blue-600">{{ $kasusDiajukanStats['ditugaskan'] }}</p>
                        </div>
                        <div class="rounded-xl bg-paper/80 p-2.5 text-center border border-ink/5">
                            <p class="text-[10px] font-bold uppercase text-slate">Berjalan</p>
                            <p class="mt-1 text-base font-bold text-indigo-600">{{ $kasusDiajukanStats['berjalan'] }}</p>
                        </div>
                        <div class="rounded-xl bg-paper/80 p-2.5 text-center border border-ink/5">
                            <p class="text-[10px] font-bold uppercase text-slate">Eskalasi</p>
                            <p class="mt-1 text-base font-bold text-rose-600">{{ $kasusDiajukanStats['eskalasi'] }}</p>
                        </div>
                        <div class="rounded-xl bg-paper/80 p-2.5 text-center border border-ink/5">
                            <p class="text-[10px] font-bold uppercase text-slate">Selesai</p>
                            <p class="mt-1 text-base font-bold text-emerald-600">{{ $kasusDiajukanStats['selesai'] }}</p>
                        </div>
                    </div>

                    @if ($kasusDiajukan->isEmpty())
                        <p class="mt-4 py-4 text-center text-xs text-slate">Belum ada kasus yang Anda ajukan.</p>
                    @else
                        <ul class="mt-4 divide-y divide-ink/10">
                            @foreach ($kasusDiajukan as $kasus)
                                <li class="py-3">
                                    <a href="{{ route('kasus.show', $kasus) }}" class="flex items-center justify-between hover:text-brass transition">
                                        <div>
                                            <p class="text-sm font-semibold text-ink">{{ $kasus->siswa->nama_lengkap }}</p>
                                            <p class="text-xs text-slate">{{ $kasus->kategori_masalah }} &middot; {{ $kasus->created_at->format('d M Y') }}</p>
                                        </div>
                                        <x-badge tone="{{ $kasus->status->badgeTone() }}">{{ $kasus->status->label() }}</x-badge>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-panel>

                {{-- 5. Kasus Pendampingan yang Saya Tangani (Konselor) --}}
                @if ($kasusDitangani->isNotEmpty())
                    <x-panel class="p-6">
                        <div class="flex items-center justify-between pb-4 border-b border-ink/10">
                            <div>
                                <h3 class="font-display font-bold text-lg text-ink">Kasus yang Saya Tangani (Konselor)</h3>
                                <p class="text-xs text-slate">Daftar penanganan kasus aktif Anda sebagai konselor</p>
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
            <div class="space-y-6 lg:col-span-5 xl:col-span-4">
                
                {{-- 1. Widget Mini Kalender Minggu Ini --}}
                <x-panel class="p-6">
                    <div class="flex items-center justify-between pb-3 border-b border-ink/10">
                        <h3 class="font-display font-bold text-sm uppercase tracking-wider text-slate">Jadwal Minggu Ini</h3>
                        <span class="text-xs font-bold text-indigo-600 uppercase">{{ now()->translatedFormat('F Y') }}</span>
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
                                <span class="h-8 w-8 flex items-center justify-center rounded-full text-xs font-bold transition {{ $isToday ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/30' : 'text-ink hover:bg-paper' }}">
                                    {{ $date->format('j') }}
                                </span>
                            </div>
                        @endfor
                    </div>
                </x-panel>

                {{-- 2. Timeline Jadwal Mengajar Hari Ini --}}
                <x-panel class="p-6">
                    <div class="flex items-center justify-between pb-4 border-b border-ink/10">
                        <div>
                            <h3 class="font-display font-bold text-base text-ink">Jadwal Mengajar Hari Ini</h3>
                            <p class="text-xs text-slate">{{ now()->translatedFormat('l, d F Y') }}</p>
                        </div>
                    </div>

                    @if ($jadwalHariIni->isEmpty())
                        <div class="mt-6 py-8 text-center">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                </svg>
                            </div>
                            <p class="mt-3 text-xs font-medium text-slate">Tidak ada jadwal mengajar hari ini.</p>
                        </div>
                    @else
                        <div class="mt-4 space-y-3">
                            @php
                                $badgeStyles = [
                                    ['bg' => 'bg-indigo-500', 'cardBg' => 'bg-indigo-50/50 hover:bg-indigo-50 border-indigo-100'],
                                    ['bg' => 'bg-purple-500', 'cardBg' => 'bg-purple-50/50 hover:bg-purple-50 border-purple-100'],
                                    ['bg' => 'bg-blue-500', 'cardBg' => 'bg-blue-50/50 hover:bg-blue-50 border-blue-100'],
                                    ['bg' => 'bg-emerald-500', 'cardBg' => 'bg-emerald-50/50 hover:bg-emerald-50 border-emerald-100'],
                                    ['bg' => 'bg-orange-500', 'cardBg' => 'bg-orange-50/50 hover:bg-orange-50 border-orange-100'],
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
