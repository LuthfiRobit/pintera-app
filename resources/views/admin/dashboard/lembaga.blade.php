<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6 pt-2">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- Main Content Area (8 Cols) --}}
            <div class="space-y-6 lg:col-span-7 xl:col-span-8">
                
                {{-- 1. Modern Gradient Hero Banner --}}
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-600 p-8 text-white shadow-xl shadow-emerald-500/10">
                    <div class="relative z-10 max-w-xl">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3.5 py-1 text-xs font-semibold backdrop-blur-md text-white">
                                <span class="h-2 w-2 rounded-full bg-emerald-300 animate-pulse"></span>
                                Panel Administrasi Lembaga
                            </span>
                            @if (Auth::user()->widestScopeLevel() === 'yayasan')
                                <a href="{{ route('dashboard', ['switch_lembaga' => 'all']) }}" class="inline-flex items-center gap-1 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-white transition hover:bg-white/20">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                    </svg>
                                    Kembali ke Yayasan
                                </a>
                            @endif
                        </div>
                        <h1 class="mt-4 flex items-center gap-2.5 font-display text-2xl font-bold tracking-tight sm:text-3xl">
                            <span>Selamat datang, {{ Auth::user()->name }}!</span>
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-white/20 backdrop-blur-md">
                                <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0h4m-4 0V5" />
                                </svg>
                            </span>
                        </h1>
                        <p class="mt-2 text-sm leading-relaxed text-emerald-100">
                            Ringkasan performa lembaga, data guru, pengguna aktif, keuangan, dan status kehadiran SDM secara realtime.
                        </p>
                    </div>

                    {{-- Subtle Right Illustration Overlay --}}
                    <div class="absolute -right-4 -bottom-8 pointer-events-none hidden sm:block opacity-20">
                        <div class="h-48 w-48 rounded-full border-8 border-white/30"></div>
                        <div class="absolute -bottom-10 -right-10 h-64 w-64 rounded-full border-8 border-white/20"></div>
                    </div>
                </div>

                {{-- 2. Responsive Stat Tiles Row --}}
                <div class="grid grid-cols-2 gap-3.5 sm:grid-cols-3">
                    <x-stat-tile label="Guru" :value="$stats['guru']" hint="Terdaftar di lembaga" icon="school" />
                    <x-stat-tile label="Pengguna" :value="$stats['pengguna']" hint="Akun aktif sistem" icon="group" />
                    <x-stat-tile label="Tahun Ajaran Aktif" :value="$stats['tahunAjaranAktif']" :hint="$tahunAjaranAktif->nama ?? 'Belum aktif'" icon="calendar_month" />
                </div>

                {{-- 3. SPMB Section --}}
                @if ($spmbStats)
                    <x-panel class="p-6">
                        <div class="flex items-center justify-between pb-4 border-b border-ink/10">
                            <div>
                                <h3 class="font-display font-bold text-lg text-ink">SPMB (Penerimaan Siswa Baru)</h3>
                                <p class="text-xs text-slate">Statistik pendaftaran dan verifikasi calon siswa</p>
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <x-stat-tile label="Total Pendaftar" :value="$spmbStats['total']" icon="groups" />
                            <x-stat-tile label="Menunggu Verifikasi" :value="$spmbStats['menunggu_verifikasi']" icon="hourglass_empty" />
                            <x-stat-tile label="Diterima" :value="$spmbStats['diterima']" icon="check_circle" />
                            <x-stat-tile label="Ditolak" :value="$spmbStats['ditolak']" icon="cancel" />
                        </div>
                        <div class="mt-6 border-t border-ink/10 pt-4">
                            <p class="mb-3 text-xs font-bold uppercase tracking-wider text-slate">Tren Pendaftaran (30 Hari Terakhir)</p>
                            <div class="h-56 w-full" x-data="trenPendaftaranChart(@js($tren['labels']), @js($tren['data']))">
                                <canvas x-ref="canvas"></canvas>
                            </div>
                        </div>
                    </x-panel>
                @endif

                {{-- 4. Keuangan Section (Side-by-Side Donut & Legend - Gambar 3 & 5 Fix) --}}
                @if ($keuanganStats)
                    <x-panel class="p-6">
                        <div class="flex items-center justify-between pb-4 border-b border-ink/10">
                            <div>
                                <h3 class="font-display font-bold text-lg text-ink">Ringkasan Keuangan</h3>
                                <p class="text-xs text-slate">Arus pembayaran dan komposisi status tagihan</p>
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-1 gap-3.5 sm:grid-cols-3">
                            <x-stat-tile label="Rp Terkumpul" value="Rp {{ number_format($keuanganStats['rpTerkumpul'], 0, ',', '.') }}" icon="payments" />
                            <x-stat-tile label="Rp Belum Lunas" value="Rp {{ number_format($keuanganStats['rpBelumLunas'], 0, ',', '.') }}" icon="pending_actions" />
                            <a href="{{ route('admin.pembayaran.index') }}">
                                <x-stat-tile label="Pembayaran Menunggu Verifikasi" :value="$keuanganStats['pembayaranMenungguVerifikasi']" icon="fact_check" />
                            </a>
                        </div>
                        
                        <div class="mt-6 border-t border-ink/10 pt-5">
                            <p class="mb-4 text-xs font-bold uppercase tracking-wider text-slate">Komposisi Status Tagihan</p>
                            
                            @php
                                $totalTagihanCount = array_sum($keuanganStats['donut']);
                                $persenBelumBayar = $totalTagihanCount > 0 ? round(($keuanganStats['donut']['belum_bayar'] / $totalTagihanCount) * 100) : 0;
                                $persenDicicil = $totalTagihanCount > 0 ? round(($keuanganStats['donut']['dicicil'] / $totalTagihanCount) * 100) : 0;
                                $persenLunas = $totalTagihanCount > 0 ? round(($keuanganStats['donut']['lunas'] / $totalTagihanCount) * 100) : 0;
                            @endphp
                            
                            <div class="flex flex-col items-center gap-6 sm:flex-row sm:items-center">
                                {{-- Donut Canvas on Left --}}
                                <div class="relative h-44 w-44 shrink-0 mx-auto sm:mx-0"
                                    x-data="donutTagihanChart(
                                        ['Belum Bayar', 'Dicicil', 'Lunas'],
                                        @js([$keuanganStats['donut']['belum_bayar'], $keuanganStats['donut']['dicicil'], $keuanganStats['donut']['lunas']])
                                    )"
                                >
                                    <canvas x-ref="canvas"></canvas>
                                </div>

                                {{-- Modern Legend Breakdown on Right (Gambar 3 Style) --}}
                                <div class="w-full flex-1 space-y-2.5">
                                    <div class="flex items-center justify-between rounded-xl border border-amber-100 bg-amber-50/50 p-3">
                                        <div class="flex items-center gap-2.5">
                                            <span class="h-3 w-3 rounded-full bg-amber-500"></span>
                                            <span class="text-xs font-bold text-ink">Belum Bayar</span>
                                        </div>
                                        <div class="text-right">
                                            <span class="font-display font-bold text-sm text-ink">{{ $persenBelumBayar }}%</span>
                                            <span class="ml-1.5 text-xs text-slate">({{ $keuanganStats['donut']['belum_bayar'] }})</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between rounded-xl border border-blue-100 bg-blue-50/50 p-3">
                                        <div class="flex items-center gap-2.5">
                                            <span class="h-3 w-3 rounded-full bg-blue-500"></span>
                                            <span class="text-xs font-bold text-ink">Dicicil</span>
                                        </div>
                                        <div class="text-right">
                                            <span class="font-display font-bold text-sm text-ink">{{ $persenDicicil }}%</span>
                                            <span class="ml-1.5 text-xs text-slate">({{ $keuanganStats['donut']['dicicil'] }})</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between rounded-xl border border-emerald-100 bg-emerald-50/50 p-3">
                                        <div class="flex items-center gap-2.5">
                                            <span class="h-3 w-3 rounded-full bg-emerald-500"></span>
                                            <span class="text-xs font-bold text-ink">Lunas</span>
                                        </div>
                                        <div class="text-right">
                                            <span class="font-display font-bold text-sm text-ink">{{ $persenLunas }}%</span>
                                            <span class="ml-1.5 text-xs text-slate">({{ $keuanganStats['donut']['lunas'] }})</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-panel>
                @endif

                @unless ($spmbStats || $keuanganStats)
                    <x-panel class="p-6 text-sm text-slate">
                        Modul Akademik, E-Sarpra, E-HRD, dan E-BK menyusul di fase berikutnya.
                    </x-panel>
                @endunless

                {{-- 5. Progress Pengumpulan Nilai per Kelas (Filter TA & Live Search) --}}
                @if ($progressRaporPerKelas !== null)
                    <x-panel class="p-6" x-data="{ search: '', selectedTA: '{{ $tahunAjaranAktif?->id ?? '' }}' }">
                        <div class="flex flex-col gap-3 pb-4 border-b border-ink/10 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="font-display font-bold text-lg text-ink">Progress Pengumpulan Nilai per Kelas</h3>
                                <p class="text-xs text-slate">Rekapitulasi pengisian rapor kelas per tahun ajaran</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                {{-- Filter Tahun Ajaran Dropdown --}}
                                <select x-model="selectedTA" class="rounded-xl border border-ink/10 bg-paper px-2.5 py-1.5 text-xs font-medium text-ink focus:border-emerald-500 focus:outline-none">
                                    <option value="">Semua TA</option>
                                    @foreach ($tahunAjaranList as $ta)
                                        <option value="{{ $ta->id }}">{{ $ta->nama }} {{ $ta->status_aktif ? '(Aktif)' : '' }}</option>
                                    @endforeach
                                </select>
                                {{-- Search Input --}}
                                <input
                                    type="text"
                                    x-model="search"
                                    placeholder="Cari kelas..."
                                    class="w-32 sm:w-40 rounded-xl border border-ink/10 bg-paper px-2.5 py-1.5 text-xs text-ink placeholder-slate/50 focus:border-emerald-500 focus:outline-none"
                                >
                            </div>
                        </div>
                        
                        <div class="mt-4 max-h-72 overflow-y-auto">
                            <table class="w-full text-sm">
                                <thead class="sticky top-0 bg-paper/95 backdrop-blur-sm shadow-sm">
                                    <tr class="border-b border-ink/10 text-left text-xs uppercase tracking-wide text-slate">
                                        <th class="px-4 py-3 font-display font-semibold">Kelas</th>
                                        <th class="px-4 py-3 font-display font-semibold">TA</th>
                                        <th class="px-4 py-3 font-display font-semibold">Terisi</th>
                                        <th class="px-4 py-3 font-display font-semibold">Progress</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-ink/10">
                                    @foreach ($progressRaporPerKelas as $item)
                                        <tr x-show="(!search || '{{ strtolower($item['kelas']->nama) }}'.includes(search.toLowerCase())) && (!selectedTA || '{{ $item['kelas']->tahun_ajaran_id }}' == selectedTA)">
                                            <td class="px-4 py-3 font-display font-medium text-ink">{{ $item['kelas']->nama }}</td>
                                            <td class="px-4 py-3 text-xs text-slate">{{ $item['kelas']->tahunAjaran?->nama ?? '-' }}</td>
                                            <td class="px-4 py-3 text-xs text-slate">{{ $item['progress']['terisi'] }} / {{ $item['progress']['total'] }}</td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-2">
                                                    <div class="h-2 w-24 overflow-hidden rounded-full bg-paper">
                                                        <div class="h-full bg-emerald-500" style="width: {{ min(100, max(0, $item['progress']['persen'])) }}%"></div>
                                                    </div>
                                                    <span class="font-semibold text-xs text-ink">{{ $item['progress']['persen'] }}%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                    @if ($progressRaporPerKelas->isEmpty())
                                        <tr>
                                            <td colspan="4" class="px-4 py-8 text-center text-slate">Belum ada kelas di lembaga ini.</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </x-panel>
                @endif

                {{-- 6. Kasus Pendampingan Restyled (Side-by-Side Cards Left + List Right) --}}
                @if ($kasusList !== null)
                    <x-panel class="p-6">
                        <div class="flex items-center justify-between pb-4 border-b border-ink/10">
                            <div>
                                <h3 class="font-display font-bold text-lg text-ink">Kasus Pendampingan</h3>
                                <p class="text-xs text-slate">Ringkasan kasus BK dan konseling siswa lembaga</p>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-12">
                            {{-- Left Side: Status Breakdown Cards --}}
                            <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:col-span-5">
                                <div class="rounded-2xl border border-ink/10 bg-paper/50 p-2.5 text-center">
                                    <p class="text-[10px] font-bold uppercase text-slate">Diajukan</p>
                                    <p class="mt-1 text-base font-bold text-ink">{{ $kasusStats['diajukan'] }}</p>
                                </div>
                                <div class="rounded-2xl border border-amber-100 bg-amber-50/50 p-2.5 text-center">
                                    <p class="text-[10px] font-bold uppercase text-amber-700">Consent</p>
                                    <p class="mt-1 text-base font-bold text-amber-600">{{ $kasusStats['menunggu_consent'] }}</p>
                                </div>
                                <div class="rounded-2xl border border-blue-100 bg-blue-50/50 p-2.5 text-center">
                                    <p class="text-[10px] font-bold uppercase text-blue-700">Ditugaskan</p>
                                    <p class="mt-1 text-base font-bold text-blue-600">{{ $kasusStats['ditugaskan'] }}</p>
                                </div>
                                <div class="rounded-2xl border border-indigo-100 bg-indigo-50/50 p-2.5 text-center">
                                    <p class="text-[10px] font-bold uppercase text-indigo-700">Berjalan</p>
                                    <p class="mt-1 text-base font-bold text-indigo-600">{{ $kasusStats['berjalan'] }}</p>
                                </div>
                                <div class="rounded-2xl border border-rose-100 bg-rose-50/50 p-2.5 text-center">
                                    <p class="text-[10px] font-bold uppercase text-rose-700">Eskalasi</p>
                                    <p class="mt-1 text-base font-bold text-rose-600">{{ $kasusStats['eskalasi'] }}</p>
                                </div>
                                <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-2.5 text-center">
                                    <p class="text-[10px] font-bold uppercase text-emerald-700">Selesai</p>
                                    <p class="mt-1 text-base font-bold text-emerald-600">{{ $kasusStats['selesai'] }}</p>
                                </div>
                            </div>

                            {{-- Right Side: Active Case List --}}
                            <div class="lg:col-span-7">
                                @if ($kasusList->isEmpty())
                                    <div class="flex h-full items-center justify-center rounded-2xl border border-dashed border-ink/10 p-6 text-center">
                                        <p class="text-xs text-slate">Belum ada kasus pendampingan di lembaga ini.</p>
                                    </div>
                                @else
                                    <div class="max-h-64 overflow-y-auto pr-1">
                                        <ul class="divide-y divide-ink/10">
                                            @foreach ($kasusList as $kasus)
                                                <li class="py-2.5">
                                                    <a href="{{ route('kasus.show', $kasus) }}" class="flex items-center justify-between hover:text-brass transition">
                                                        <div class="min-w-0 flex-1 pr-3">
                                                            <p class="truncate text-sm font-semibold text-ink">{{ $kasus->siswa->nama_lengkap }}</p>
                                                            <p class="text-xs text-slate">{{ $kasus->kategori_masalah }}</p>
                                                        </div>
                                                        <x-badge tone="{{ $kasus->status->badgeTone() }}">{{ $kasus->status->label() }}</x-badge>
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </x-panel>
                @endif
            </div>

            {{-- Right Sidebar Area (4 Cols) --}}
            <div class="space-y-6 lg:col-span-5 xl:col-span-4">
                
                {{-- 1. Widget Kehadiran SDM Hari Ini --}}
                <x-panel class="p-6">
                    <div class="flex items-center justify-between pb-3 border-b border-ink/10">
                        <h3 class="font-display font-bold text-sm uppercase tracking-wider text-slate">Kehadiran SDM Hari Ini</h3>
                        <span class="text-xs font-bold text-emerald-600 uppercase">{{ now()->translatedFormat('d F Y') }}</span>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-2">
                        <x-stat-tile label="Hadir" :value="$presensiSdmHariIni['hadir']" icon="check_circle" />
                        <x-stat-tile label="Izin" :value="$presensiSdmHariIni['izin']" icon="hourglass_empty" />
                        <x-stat-tile label="Sakit" :value="$presensiSdmHariIni['sakit']" icon="local_hospital" />
                        <x-stat-tile label="Alpa" :value="$presensiSdmHariIni['alpa']" icon="cancel" />
                        <x-stat-tile label="Cuti" :value="$presensiSdmHariIni['cuti']" icon="beach_access" />
                    </div>
                </x-panel>

                {{-- 2. Pengajuan Izin/Cuti Pending --}}
                <x-panel class="p-6">
                    <div class="flex items-center justify-between pb-3 border-b border-ink/10">
                        <h3 class="font-display font-bold text-sm uppercase tracking-wider text-slate">Persetujuan Cuti SDM</h3>
                    </div>
                    <div class="mt-4">
                        <x-stat-tile label="Pending Persetujuan" :value="$izinCutiPendingCount" icon="pending_actions" />
                    </div>
                </x-panel>

                {{-- 3. Widget Mini Kalender Minggu Ini --}}
                <x-panel class="p-6">
                    <div class="flex items-center justify-between pb-3 border-b border-ink/10">
                        <h3 class="font-display font-bold text-sm uppercase tracking-wider text-slate">Jadwal Minggu Ini</h3>
                        <span class="text-xs font-bold text-emerald-600 uppercase">{{ now()->translatedFormat('F Y') }}</span>
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
                                <span class="h-8 w-8 flex items-center justify-center rounded-full text-xs font-bold transition {{ $isToday ? 'bg-emerald-600 text-white shadow-md shadow-emerald-500/30' : 'text-ink hover:bg-paper' }}">
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
