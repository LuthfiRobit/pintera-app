<x-app-layout>
    <h1 class="sr-only">Dashboard Yayasan</h1>

    <div class="mx-auto max-w-7xl space-y-6 pt-2">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- Main Content Area (8 Cols) --}}
            <div class="space-y-6 lg:col-span-7 xl:col-span-8">
                
                {{-- 1. Modern Gradient Hero Banner --}}
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-blue-900 p-8 text-white shadow-xl shadow-slate-900/10">
                    <div class="relative z-10 max-w-xl">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3.5 py-1 text-xs font-semibold backdrop-blur-md text-amber-300">
                            <span class="h-2 w-2 rounded-full bg-amber-400 animate-pulse"></span>
                            Yayasan &middot; Pengawasan Lintas Lembaga
                        </span>
                        <h1 class="mt-4 flex items-center gap-2.5 font-display text-2xl font-bold tracking-tight sm:text-3xl">
                            <span>Selamat datang, {{ Auth::user()->name }}!</span>
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-white/10 backdrop-blur-md">
                                <svg class="h-5 w-5 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0h4m-4 0V5" />
                                </svg>
                            </span>
                        </h1>
                        <p class="mt-2 text-sm leading-relaxed text-slate-300">
                            Pantau seluruh lembaga pendidikan di bawah yayasan dari satu portal terpadu — konsolidasi data, SDM, dan keuangan berjalan.
                        </p>
                    </div>

                    {{-- Subtle Right Illustration Overlay --}}
                    <div class="absolute -right-4 -bottom-8 pointer-events-none hidden sm:block opacity-20">
                        <div class="h-48 w-48 rounded-full border-8 border-white/30"></div>
                        <div class="absolute -bottom-10 -right-10 h-64 w-64 rounded-full border-8 border-white/20"></div>
                    </div>
                </div>

                {{-- 2. Stat Tiles Row --}}
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <x-stat-tile label="Lembaga" :value="$stats['lembaga']" hint="Total unit pendidikan" icon="apartment" />
                    <x-stat-tile label="Guru" :value="$stats['guru']" hint="Terdaftar lintas lembaga" icon="school" />
                    <x-stat-tile label="Pengguna" :value="$stats['pengguna']" hint="Akun aktif sistem" icon="group" />
                    <x-stat-tile label="Tahun Ajaran Aktif" :value="$stats['tahunAjaranAktif']" hint="Berjalan saat ini" icon="calendar_month" />
                </div>

                {{-- 3. Konsolidasi SPMB & Keuangan --}}
                @if (isset($ringkasanPerLembaga))
                    <x-panel class="p-6">
                        <div class="flex items-center justify-between pb-4 border-b border-ink/10">
                            <div>
                                <h3 class="font-display font-bold text-lg text-ink">Konsolidasi SPMB &amp; Keuangan</h3>
                                <p class="text-xs text-slate">Ringkasan pendaftaran dan penerimaan dana tahun ajaran aktif seluruh lembaga</p>
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <x-stat-tile label="Total Pendaftar" :value="$totalPendaftar" icon="groups" />
                            <x-stat-tile label="Total Diterima" :value="$totalDiterima" icon="check_circle" />
                            <x-stat-tile label="Total Rp Terkumpul" value="Rp {{ number_format($totalRpTerkumpul, 0, ',', '.') }}" icon="payments" />
                        </div>

                        @if ($ringkasanPerLembaga->isNotEmpty())
                            <div class="mt-6 border-t border-ink/10 pt-4">
                                <p class="mb-3 text-xs font-bold uppercase tracking-wider text-slate">Pendaftar per Lembaga</p>
                                <div
                                    x-data="perLembagaBarChart(
                                        @js($ringkasanPerLembaga->pluck('lembaga.nama')),
                                        @js($ringkasanPerLembaga->map(fn ($r) => $r['spmb']['total']))
                                    )"
                                >
                                    <canvas x-ref="canvas" height="90"></canvas>
                                </div>
                            </div>
                        @endif
                    </x-panel>

                    {{-- 4. Tabel Tinjau Per Lembaga --}}
                    <x-panel class="p-6">
                        <div class="flex items-center justify-between pb-4 border-b border-ink/10">
                            <div>
                                <h3 class="font-display font-bold text-lg text-ink">Tinjau Per Lembaga</h3>
                                <p class="text-xs text-slate">Klik salah satu lembaga untuk menyaring seluruh data di sistem ke lembaga tersebut</p>
                            </div>
                        </div>
                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-ink/10 bg-paper/60 text-left text-xs uppercase tracking-wide text-slate">
                                        <th class="px-4 py-3 font-display font-semibold">Lembaga</th>
                                        <th class="px-4 py-3 font-display font-semibold">Pendaftar</th>
                                        <th class="px-4 py-3 font-display font-semibold">Diterima</th>
                                        <th class="px-4 py-3 font-display font-semibold">Terkumpul</th>
                                        <th class="px-4 py-3 font-display font-semibold text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-ink/10">
                                    @foreach ($ringkasanPerLembaga as $ringkasan)
                                        <tr class="cursor-pointer hover:bg-paper/60 transition" onclick="window.location='{{ route('dashboard', ['switch_lembaga' => $ringkasan['lembaga']->id]) }}'">
                                            <td class="px-4 py-3.5 font-display font-medium text-ink flex items-center gap-2">
                                                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-50 text-blue-600 font-bold text-xs">
                                                    {{ strtoupper(substr($ringkasan['lembaga']->nama, 0, 1)) }}
                                                </span>
                                                {{ $ringkasan['lembaga']->nama }}
                                            </td>
                                            <td class="px-4 py-3.5">{{ $ringkasan['spmb']['total'] }}</td>
                                            <td class="px-4 py-3.5">{{ $ringkasan['spmb']['diterima'] }}</td>
                                            <td class="px-4 py-3.5 font-medium text-ink">Rp {{ number_format($ringkasan['keuangan']['rpTerkumpul'], 0, ',', '.') }}</td>
                                            <td class="px-4 py-3.5 text-right text-xs font-semibold text-blue-600">
                                                Filter &rarr;
                                            </td>
                                        </tr>
                                    @endforeach
                                    @if ($ringkasanPerLembaga->isEmpty())
                                        <tr>
                                            <td colspan="5" class="px-4 py-8 text-center text-slate">Belum ada lembaga di bawah yayasan ini.</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </x-panel>
                @endif
            </div>

            {{-- Right Sidebar Area (4 Cols) --}}
            <div class="space-y-6 lg:col-span-5 xl:col-span-4">
                
                @if (isset($presensiSdmHariIni))
                    {{-- 1. Widget Kehadiran SDM Hari Ini --}}
                    <x-panel class="p-6">
                        <div class="flex items-center justify-between pb-3 border-b border-ink/10">
                            <h3 class="font-display font-bold text-sm uppercase tracking-wider text-slate">Kehadiran SDM Hari Ini</h3>
                            <span class="text-xs font-bold text-indigo-600 uppercase">{{ now()->translatedFormat('d F Y') }}</span>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5 lg:grid-cols-2">
                            <x-stat-tile label="Hadir" :value="$presensiSdmHariIni['hadir']" icon="check_circle" />
                            <x-stat-tile label="Izin" :value="$presensiSdmHariIni['izin']" icon="hourglass_empty" />
                            <x-stat-tile label="Sakit" :value="$presensiSdmHariIni['sakit']" icon="local_hospital" />
                            <x-stat-tile label="Alpa" :value="$presensiSdmHariIni['alpa']" icon="cancel" />
                            <x-stat-tile label="Cuti" :value="$presensiSdmHariIni['cuti']" icon="beach_access" />
                        </div>
                    </x-panel>

                    {{-- 2. Kasus Eskalasi Unassigned --}}
                    <x-panel class="p-6">
                        <div class="flex items-center justify-between pb-3 border-b border-ink/10">
                            <h3 class="font-display font-bold text-sm uppercase tracking-wider text-slate">Eskalasi Belum Ditangani</h3>
                        </div>
                        <div class="mt-4">
                            <x-stat-tile label="Menunggu Konselor" :value="$kasusEskalasiUnassigned" icon="priority_high" hint="Lintas semua lembaga di yayasan ini" />
                        </div>
                    </x-panel>
                @endif

                {{-- 3. Widget Mini Kalender Minggu Ini --}}
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
            </div>
        </div>
    </div>
</x-app-layout>
