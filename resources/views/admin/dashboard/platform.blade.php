<x-app-layout>
    <h1 class="sr-only">Dashboard Platform</h1>

    <div class="mx-auto max-w-7xl space-y-6 pt-2">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- Main Content Area (8 Cols) --}}
            <div class="space-y-6 lg:col-span-7 xl:col-span-8">
                
                {{-- 1. Modern Gradient Hero Banner --}}
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-violet-700 via-purple-700 to-indigo-800 p-8 text-white shadow-xl shadow-purple-900/10">
                    <div class="relative z-10 max-w-xl">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3.5 py-1 text-xs font-semibold backdrop-blur-md text-purple-200">
                            <span class="h-2 w-2 rounded-full bg-purple-300 animate-pulse"></span>
                            Platform &middot; Pengawasan Lintas Yayasan
                        </span>
                        <h1 class="mt-4 flex items-center gap-2.5 font-display text-2xl font-bold tracking-tight sm:text-3xl">
                            <span>Halo, {{ Auth::user()->name }}!</span>
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 backdrop-blur-md">
                                <svg class="h-5 w-5 text-purple-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                            </span>
                        </h1>
                        <p class="mt-2 text-sm leading-relaxed text-purple-100">
                            Ringkasan seluruh yayasan, unit lembaga pendidikan, total guru, dan akun aktif terdaftar di platform ini.
                        </p>
                    </div>

                    {{-- Subtle Right Illustration Overlay --}}
                    <div class="absolute -right-4 -bottom-8 pointer-events-none hidden sm:block opacity-20">
                        <div class="h-48 w-48 rounded-full border-8 border-white/30"></div>
                        <div class="absolute -bottom-10 -right-10 h-64 w-64 rounded-full border-8 border-white/20"></div>
                    </div>
                </div>

                {{-- 2. Stat Tiles Responsive Grid --}}
                <div class="grid grid-cols-2 gap-3.5 sm:grid-cols-4">
                    <x-stat-tile label="Yayasan" :value="$stats['yayasan']" hint="Total tenant terdaftar" icon="domain" />
                    <x-stat-tile label="Lembaga" :value="$stats['lembaga']" hint="Lintas semua yayasan" icon="apartment" />
                    <x-stat-tile label="Guru" :value="$stats['guru']" hint="Lintas semua yayasan" icon="school" />
                    <x-stat-tile label="Pengguna" :value="$stats['pengguna']" hint="Akun aktif sistem" icon="group" />
                </div>

                {{-- 3. Tren Pertumbuhan Yayasan (Area Line Chart) --}}
                <x-panel class="p-6">
                    <div class="flex items-center justify-between pb-4 border-b border-ink/10">
                        <div>
                            <h3 class="font-display font-bold text-lg text-ink">Tren Pertumbuhan Yayasan</h3>
                            <p class="text-xs text-slate">Statistik pendaftaran tenant baru 6 bulan terakhir</p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="relative h-60 w-full" x-data="trenTenantChart(@js($trenTenant['labels']), @js($trenTenant['data']))">
                            <canvas x-ref="canvas"></canvas>
                        </div>
                    </div>
                </x-panel>

                {{-- 4. Ringkasan per Yayasan Table --}}
                <x-panel class="p-6">
                    <div class="flex items-center justify-between pb-4 border-b border-ink/10">
                        <div>
                            <h3 class="font-display font-bold text-lg text-ink">Ringkasan per Yayasan</h3>
                            <p class="text-xs text-slate">Data agregat setiap yayasan yang terdaftar di platform.</p>
                        </div>
                    </div>
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-ink/10 bg-paper/60 text-left text-xs uppercase tracking-wide text-slate">
                                    <th class="px-4 py-3 font-display font-semibold">Yayasan</th>
                                    <th class="px-4 py-3 font-display font-semibold">Lembaga</th>
                                    <th class="px-4 py-3 font-display font-semibold">Guru</th>
                                    <th class="px-4 py-3 font-display font-semibold">Pengguna</th>
                                    <th class="px-4 py-3 font-display font-semibold">TA Aktif?</th>
                                    <th class="px-4 py-3 font-display font-semibold">Akun Nonaktif</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink/10">
                                @php
                                    $badgeColors = ['bg-purple-100 text-purple-700', 'bg-indigo-100 text-indigo-700', 'bg-blue-100 text-blue-700', 'bg-emerald-100 text-emerald-700', 'bg-amber-100 text-amber-700'];
                                @endphp
                                @foreach ($ringkasanPerYayasan as $index => $ringkasan)
                                    @php
                                        $badgeColor = $badgeColors[$index % count($badgeColors)];
                                    @endphp
                                    <tr class="hover:bg-paper/80 transition">
                                        <td class="px-4 py-3.5 font-display font-medium text-ink flex items-center gap-2.5">
                                            <span class="flex h-8 w-8 items-center justify-center rounded-xl {{ $badgeColor }} font-bold text-xs shadow-sm">
                                                {{ strtoupper(substr($ringkasan['yayasan']->nama, 0, 1)) }}
                                            </span>
                                            <span class="font-semibold">{{ $ringkasan['yayasan']->nama }}</span>
                                        </td>
                                        <td class="px-4 py-3.5 font-semibold text-ink">{{ $ringkasan['lembaga'] }}</td>
                                        <td class="px-4 py-3.5 text-slate">{{ $ringkasan['guru'] }}</td>
                                        <td class="px-4 py-3.5 text-slate">{{ $ringkasan['pengguna'] }}</td>
                                        <td class="px-4 py-3.5">
                                            <x-badge tone="{{ $ringkasan['adaTahunAjaranAktif'] ? 'green' : 'amber' }}">{{ $ringkasan['adaTahunAjaranAktif'] ? 'Ya' : 'Tidak' }}</x-badge>
                                        </td>
                                        <td class="px-4 py-3.5 font-semibold {{ $ringkasan['akunNonaktif'] > 0 ? 'text-amber-600' : 'text-slate' }}">{{ $ringkasan['akunNonaktif'] }}</td>
                                    </tr>
                                @endforeach
                                @if ($ringkasanPerYayasan->isEmpty())
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-slate">Belum ada yayasan terdaftar.</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </x-panel>
            </div>

            {{-- Right Sidebar Area (4 Cols) --}}
            <div class="space-y-6 lg:col-span-5 xl:col-span-4 lg:sticky lg:top-6 lg:self-start">
                
                {{-- 1. Ringkasan Kesehatan Tenant Widget --}}
                @php
                    $yayasanTanpaTaAktif = $ringkasanPerYayasan->filter(fn ($r) => ! $r['adaTahunAjaranAktif'])->count();
                    $totalAkunNonaktif = $ringkasanPerYayasan->sum('akunNonaktif');
                @endphp
                <x-panel class="p-6">
                    <div class="flex items-center justify-between pb-3 border-b border-ink/10">
                        <h3 class="font-display font-bold text-sm uppercase tracking-wider text-slate">Ringkasan Kesehatan Tenant</h3>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div class="rounded-2xl border {{ $yayasanTanpaTaAktif > 0 ? 'border-amber-100 bg-amber-50/50' : 'border-emerald-100 bg-emerald-50/50' }} p-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $yayasanTanpaTaAktif > 0 ? 'bg-amber-500' : 'bg-emerald-600' }} text-white shadow-sm">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-display font-bold text-sm text-ink">Tahun Ajaran Aktif</h4>
                                    <p class="text-xs text-slate mt-0.5">
                                        {{ $yayasanTanpaTaAktif > 0 ? "{$yayasanTanpaTaAktif} yayasan belum punya TA aktif" : 'Semua yayasan punya TA aktif' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-2xl border {{ $totalAkunNonaktif > 0 ? 'border-amber-100 bg-amber-50/50' : 'border-emerald-100 bg-emerald-50/50' }} p-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $totalAkunNonaktif > 0 ? 'bg-amber-500' : 'bg-emerald-600' }} text-white shadow-sm">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-display font-bold text-sm text-ink">Akun Nonaktif</h4>
                                    <p class="text-xs text-slate mt-0.5">{{ $totalAkunNonaktif }} akun nonaktif lintas semua yayasan</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-panel>

                {{-- 2. Widget Mini Kalender Minggu Ini --}}
                <x-panel class="p-6">
                    <div class="flex items-center justify-between pb-3 border-b border-ink/10">
                        <h3 class="font-display font-bold text-sm uppercase tracking-wider text-slate">Jadwal Minggu Ini</h3>
                        <span class="text-xs font-bold text-purple-600 uppercase">{{ now()->translatedFormat('F Y') }}</span>
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
                                <span class="h-8 w-8 flex items-center justify-center rounded-full text-xs font-bold transition {{ $isToday ? 'bg-purple-600 text-white shadow-md shadow-purple-500/30' : 'text-ink hover:bg-paper' }}">
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
