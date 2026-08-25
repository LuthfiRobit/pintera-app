<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6 pt-2">
        
        {{-- 1. Modern Gradient Hero Banner (Full Width) --}}
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-600 p-8 text-white shadow-xl shadow-emerald-500/10">
            <div class="relative z-10 max-w-2xl">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3.5 py-1 text-xs font-semibold backdrop-blur-md text-white">
                        <span class="h-2 w-2 rounded-full bg-emerald-300 animate-pulse"></span>
                        Panel Administrasi Lembaga
                    </span>
                    @if (Auth::user()->widestScopeLevel() === 'yayasan')
                        <a href="{{ route('dashboard', ['switch_lembaga' => 'all']) }}" class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3.5 py-1 text-xs font-semibold text-white backdrop-blur-md transition hover:bg-white/30">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Kembali ke Ringkasan Yayasan
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
                    Kelola data guru, pengguna aktif, pendaftaran siswa baru, penerimaan keuangan, dan kehadiran SDM secara realtime.
                </p>
            </div>

            <div class="absolute -right-4 -bottom-8 pointer-events-none hidden sm:block opacity-20">
                <div class="h-48 w-48 rounded-full border-8 border-white/30"></div>
                <div class="absolute -bottom-10 -right-10 h-64 w-64 rounded-full border-8 border-white/20"></div>
            </div>
        </div>

        {{-- 2. Stat Tiles Row (Full Width - 3 Spacious Columns) --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-stat-tile label="Guru" :value="$stats['guru']" hint="Terdaftar di lembaga Anda" icon="school" />
            <x-stat-tile label="Pengguna" :value="$stats['pengguna']" hint="Akun aktif di lembaga Anda" icon="group" />
            <x-stat-tile label="Tahun Ajaran Aktif" :value="$stats['tahunAjaranAktif']" :hint="$tahunAjaranAktif->nama ?? 'Belum diaktifkan'" icon="calendar_month" />
        </div>

        {{-- 3. SPMB Section (Full Width Panel) --}}
        @if ($spmbStats)
            <x-panel class="p-6">
                <div class="flex items-center justify-between pb-4 border-b border-ink/10">
                    <div>
                        <h3 class="font-display font-bold text-lg text-ink">SPMB (Penerimaan Siswa Baru)</h3>
                        <p class="text-xs text-slate">Statistik pendaftaran dan verifikasi calon siswa</p>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-stat-tile label="Total Pendaftar" :value="$spmbStats['total']" icon="groups" />
                    <x-stat-tile label="Menunggu Verifikasi" :value="$spmbStats['menunggu_verifikasi']" icon="hourglass_empty" />
                    <x-stat-tile label="Diterima" :value="$spmbStats['diterima']" icon="check_circle" />
                    <x-stat-tile label="Ditolak" :value="$spmbStats['ditolak']" icon="cancel" />
                </div>
                <div class="mt-6 border-t border-ink/10 pt-4">
                    <p class="mb-3 text-xs font-bold uppercase tracking-wider text-slate">Tren Pendaftaran (30 Hari Terakhir)</p>
                    <div class="h-64 w-full" x-data="trenPendaftaranChart(@js($tren['labels']), @js($tren['data']))">
                        <canvas x-ref="canvas"></canvas>
                    </div>
                </div>
            </x-panel>
        @endif

        {{-- 4. Ringkasan Keuangan (Full Width Panel - Donut Left, Legend Right - Gambar 3 & 5 Fix) --}}
        @if ($keuanganStats)
            <x-panel class="p-6">
                <div class="flex items-center justify-between pb-4 border-b border-ink/10">
                    <div>
                        <h3 class="font-display font-bold text-lg text-ink">Ringkasan Keuangan</h3>
                        <p class="text-xs text-slate">Arus pembayaran dan komposisi status tagihan</p>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
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
                    
                    <div class="flex flex-col items-center gap-8 sm:flex-row sm:items-center">
                        {{-- Donut Canvas on Left --}}
                        <div class="relative h-56 w-56 shrink-0"
                            x-data="donutTagihanChart(
                                ['Belum Bayar', 'Dicicil', 'Lunas'],
                                @js([$keuanganStats['donut']['belum_bayar'], $keuanganStats['donut']['dicicil'], $keuanganStats['donut']['lunas']])
                            )"
                        >
                            <canvas x-ref="canvas"></canvas>
                        </div>

                        {{-- Modern Legend Breakdown on Right (Gambar 3 Style) --}}
                        <div class="w-full flex-1 space-y-3.5">
                            <div class="flex items-center justify-between rounded-2xl border border-amber-100 bg-amber-50/50 p-4">
                                <div class="flex items-center gap-3">
                                    <span class="h-3.5 w-3.5 rounded-full bg-amber-500 shadow-sm"></span>
                                    <span class="text-sm font-bold text-ink">Belum Bayar</span>
                                </div>
                                <div class="text-right">
                                    <span class="font-display font-bold text-base text-ink">{{ $persenBelumBayar }}%</span>
                                    <span class="ml-2 text-xs text-slate">({{ $keuanganStats['donut']['belum_bayar'] }} Tagihan)</span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between rounded-2xl border border-blue-100 bg-blue-50/50 p-4">
                                <div class="flex items-center gap-3">
                                    <span class="h-3.5 w-3.5 rounded-full bg-blue-500 shadow-sm"></span>
                                    <span class="text-sm font-bold text-ink">Dicicil</span>
                                </div>
                                <div class="text-right">
                                    <span class="font-display font-bold text-base text-ink">{{ $persenDicicil }}%</span>
                                    <span class="ml-2 text-xs text-slate">({{ $keuanganStats['donut']['dicicil'] }} Tagihan)</span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4">
                                <div class="flex items-center gap-3">
                                    <span class="h-3.5 w-3.5 rounded-full bg-emerald-500 shadow-sm"></span>
                                    <span class="text-sm font-bold text-ink">Lunas</span>
                                </div>
                                <div class="text-right">
                                    <span class="font-display font-bold text-base text-ink">{{ $persenLunas }}%</span>
                                    <span class="ml-2 text-xs text-slate">({{ $keuanganStats['donut']['lunas'] }} Tagihan)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </x-panel>
        @endif

        {{-- 5. Kehadiran SDM & Pengajuan Cuti (Full Width 2-Col Grid) --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <x-panel class="p-6 lg:col-span-8">
                <div class="flex items-center justify-between pb-3 border-b border-ink/10">
                    <h3 class="font-display font-bold text-base text-ink">Kehadiran SDM Hari Ini</h3>
                    <span class="text-xs font-bold text-emerald-600 uppercase">{{ now()->translatedFormat('d F Y') }}</span>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <x-stat-tile label="Hadir" :value="$presensiSdmHariIni['hadir']" icon="check_circle" />
                    <x-stat-tile label="Izin" :value="$presensiSdmHariIni['izin']" icon="hourglass_empty" />
                    <x-stat-tile label="Sakit" :value="$presensiSdmHariIni['sakit']" icon="local_hospital" />
                    <x-stat-tile label="Alpa" :value="$presensiSdmHariIni['alpa']" icon="cancel" />
                    <x-stat-tile label="Cuti" :value="$presensiSdmHariIni['cuti']" icon="beach_access" />
                </div>
            </x-panel>

            <x-panel class="p-6 lg:col-span-4">
                <div class="flex items-center justify-between pb-3 border-b border-ink/10">
                    <h3 class="font-display font-bold text-base text-ink">Persetujuan Cuti SDM</h3>
                </div>
                <div class="mt-4">
                    <x-stat-tile label="Pending Persetujuan" :value="$izinCutiPendingCount" icon="pending_actions" />
                </div>
            </x-panel>
        </div>

        {{-- 6. Progress Pengumpulan Nilai per Kelas (Filter TA & Search - Full Width) --}}
        @if ($progressRaporPerKelas !== null)
            <x-panel class="p-6" x-data="{ search: '', selectedTA: '{{ $tahunAjaranAktif?->id ?? '' }}' }">
                <div class="flex flex-col gap-4 pb-4 border-b border-ink/10 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="font-display font-bold text-lg text-ink">Progress Pengumpulan Nilai per Kelas</h3>
                        <p class="text-xs text-slate">Rekapitulasi pengisian rapor kelas per tahun ajaran</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        {{-- Filter Tahun Ajaran Dropdown --}}
                        <select x-model="selectedTA" class="rounded-xl border border-ink/10 bg-paper px-3 py-1.5 text-xs font-medium text-ink focus:border-emerald-500 focus:outline-none">
                            <option value="">Semua Tahun Ajaran</option>
                            @foreach ($tahunAjaranList as $ta)
                                <option value="{{ $ta->id }}">{{ $ta->nama }} {{ $ta->status_aktif ? '(Aktif)' : '' }}</option>
                            @endforeach
                        </select>
                        {{-- Search Input --}}
                        <input
                            type="text"
                            x-model="search"
                            placeholder="Cari kelas..."
                            class="rounded-xl border border-ink/10 bg-paper px-3 py-1.5 text-xs text-ink placeholder-slate/50 focus:border-emerald-500 focus:outline-none"
                        >
                    </div>
                </div>
                
                <div class="mt-4 max-h-80 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-paper/95 backdrop-blur-sm shadow-sm">
                            <tr class="border-b border-ink/10 text-left text-xs uppercase tracking-wide text-slate">
                                <th class="px-4 py-3 font-display font-semibold">Kelas</th>
                                <th class="px-4 py-3 font-display font-semibold">Tahun Ajaran</th>
                                <th class="px-4 py-3 font-display font-semibold">Terisi</th>
                                <th class="px-4 py-3 font-display font-semibold">Progress</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink/10">
                            @foreach ($progressRaporPerKelas as $item)
                                <tr x-show="(!search || '{{ strtolower($item['kelas']->nama) }}'.includes(search.toLowerCase())) && (!selectedTA || '{{ $item['kelas']->tahun_ajaran_id }}' == selectedTA)">
                                    <td class="px-4 py-3 font-display font-medium text-ink">{{ $item['kelas']->nama }}</td>
                                    <td class="px-4 py-3 text-xs text-slate">{{ $item['kelas']->tahunAjaran?->nama ?? '-' }}</td>
                                    <td class="px-4 py-3 text-xs text-slate">{{ $item['progress']['terisi'] }} / {{ $item['progress']['total'] }} slot</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2.5">
                                            <div class="h-2 w-36 overflow-hidden rounded-full bg-paper">
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

        {{-- 7. Kasus Pendampingan (Full Width Panel) --}}
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
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:col-span-5">
                        <div class="rounded-2xl border border-ink/10 bg-paper/50 p-3.5 text-center">
                            <p class="text-[10px] font-bold uppercase text-slate">Diajukan</p>
                            <p class="mt-1 text-xl font-bold text-ink">{{ $kasusStats['diajukan'] }}</p>
                        </div>
                        <div class="rounded-2xl border border-amber-100 bg-amber-50/50 p-3.5 text-center">
                            <p class="text-[10px] font-bold uppercase text-amber-700">Consent</p>
                            <p class="mt-1 text-xl font-bold text-amber-600">{{ $kasusStats['menunggu_consent'] }}</p>
                        </div>
                        <div class="rounded-2xl border border-blue-100 bg-blue-50/50 p-3.5 text-center">
                            <p class="text-[10px] font-bold uppercase text-blue-700">Ditugaskan</p>
                            <p class="mt-1 text-xl font-bold text-blue-600">{{ $kasusStats['ditugaskan'] }}</p>
                        </div>
                        <div class="rounded-2xl border border-indigo-100 bg-indigo-50/50 p-3.5 text-center">
                            <p class="text-[10px] font-bold uppercase text-indigo-700">Berjalan</p>
                            <p class="mt-1 text-xl font-bold text-indigo-600">{{ $kasusStats['berjalan'] }}</p>
                        </div>
                        <div class="rounded-2xl border border-rose-100 bg-rose-50/50 p-3.5 text-center">
                            <p class="text-[10px] font-bold uppercase text-rose-700">Eskalasi</p>
                            <p class="mt-1 text-xl font-bold text-rose-600">{{ $kasusStats['eskalasi'] }}</p>
                        </div>
                        <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-3.5 text-center">
                            <p class="text-[10px] font-bold uppercase text-emerald-700">Selesai</p>
                            <p class="mt-1 text-xl font-bold text-emerald-600">{{ $kasusStats['selesai'] }}</p>
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
                                        <li class="py-3">
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
</x-app-layout>
