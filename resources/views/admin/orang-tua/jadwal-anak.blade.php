@php
    $hariIniKey = strtolower(\App\Enums\Hari::fromCarbonDayOfWeek(now()->dayOfWeek)->value);
    $adaJadwalHariIni = $jadwalList->has($hariIniKey);
@endphp

<x-app-layout>
    <div
        class="mx-auto max-w-5xl space-y-6 pt-2"
        x-data="{
            viewMode: 'list',
            hariIni: @js($hariIniKey),
            scrollToToday() {
                this.$nextTick(() => {
                    const el = this.viewMode === 'list'
                        ? document.getElementById('jadwal-list-' + this.hariIni)
                        : document.getElementById('jadwal-matrix-' + this.hariIni);
                    if (el) {
                        if (this.viewMode === 'list') {
                            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        } else {
                            el.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                        }
                    }
                });
            },
            init() {
                setTimeout(() => {
                    this.scrollToToday();
                }, 300);
            }
        }"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-2xl font-bold tracking-tight text-ink">Jadwal Anak</h1>
                <p class="mt-1 text-sm text-slate">Jadwal pelajaran mingguan anak Anda pada semester aktif.</p>
            </div>
            @if ($anak)
                <div class="inline-flex items-center gap-2 rounded-xl bg-paper px-3.5 py-1.5 border border-ink/10 text-xs font-medium text-slate">
                    <x-icon name="school" class="h-4 w-4 text-brand-500" />
                    <span>{{ $anak->nama_lengkap }}</span>
                    <span class="text-ink/20">&middot;</span>
                    <span class="font-semibold text-ink">{{ $anak->kelas?->nama ?? 'Belum Ditentukan' }}</span>
                </div>
            @endif
        </div>

        @if ($anakList->isEmpty())
            <x-panel class="p-8 text-center">
                <p class="text-sm text-slate">Belum ada data siswa yang terhubung dengan akun Anda.</p>
            </x-panel>
        @else
            <x-panel class="p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <form method="GET" class="w-full sm:max-w-sm">
                        <x-input-label value="Pilih Anak" class="font-medium text-xs text-slate uppercase tracking-wider" />
                        <select name="siswa_id" onchange="this.form.submit()" class="mt-1.5 block w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm transition focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($anakList as $anakOpsi)
                                <option value="{{ $anakOpsi->id }}" @selected($anak?->id === $anakOpsi->id)>{{ $anakOpsi->nama_lengkap }} &middot; {{ $anakOpsi->kelas?->nama ?? 'Belum Ditentukan' }}</option>
                            @endforeach
                        </select>
                    </form>

                    @if (!$jadwalList->isEmpty())
                        <div class="inline-flex rounded-xl bg-paper p-1 shrink-0 border border-ink/10 self-start sm:self-end">
                            <button
                                type="button"
                                @click="viewMode = 'list'; scrollToToday()"
                                :class="viewMode === 'list' ? 'bg-white text-ink shadow-sm font-bold' : 'text-slate hover:text-ink font-medium'"
                                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs transition"
                            >
                                <x-icon name="checklist" class="h-3.5 w-3.5 text-brand-500" />
                                <span>Tampilan Daftar</span>
                            </button>
                            <button
                                type="button"
                                @click="viewMode = 'matrix'; scrollToToday()"
                                :class="viewMode === 'matrix' ? 'bg-white text-ink shadow-sm font-bold' : 'text-slate hover:text-ink font-medium'"
                                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs transition"
                            >
                                <x-icon name="dashboard" class="h-3.5 w-3.5 text-brand-500" />
                                <span>Matriks Mingguan</span>
                            </button>
                        </div>
                    @endif
                </div>

                @if ($adaJadwalHariIni)
                    <div class="mt-4 pt-3 border-t border-ink/10 flex items-center justify-between">
                        <button
                            type="button"
                            @click="scrollToToday()"
                            class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-600 hover:text-brand-700 transition active:scale-95"
                        >
                            <span class="flex h-2 w-2 rounded-full bg-brand-500 animate-pulse"></span>
                            <span>Fokus ke Jadwal Hari Ini ({{ ucfirst($hariIniKey) }})</span>
                        </button>
                    </div>
                @else
                    <div class="mt-4 pt-3 border-t border-ink/10 flex items-center gap-2 text-xs text-slate">
                        <x-icon name="info" class="h-4 w-4 text-slate/60" />
                        <span>Hari ini ({{ ucfirst($hariIniKey) }}) tidak ada agenda kegiatan belajar mengajar.</span>
                    </div>
                @endif
            </x-panel>

            @if ($jadwalList->isEmpty())
                <x-panel class="p-12 text-center">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-paper text-slate">
                        <x-icon name="event" class="h-7 w-7" />
                    </span>
                    <h4 class="mt-3 font-display text-sm font-semibold text-ink">Belum Ada Jadwal Pelajaran</h4>
                    <p class="mt-1 text-xs text-slate">Belum ada jadwal pelajaran untuk semester aktif.</p>
                </x-panel>
            @else
                {{-- 1. Tampilan Daftar (List Mode) --}}
                <div x-show="viewMode === 'list'" class="space-y-6">
                    @foreach ($jadwalList as $hari => $jadwalHari)
                        @php
                            $isHariIni = (strtolower($hari) === $hariIniKey);
                        @endphp
                        <x-panel
                            id="jadwal-list-{{ strtolower($hari) }}"
                            class="relative p-6 transition duration-200 {{ $isHariIni ? 'border-brand-300/80 ring-1 ring-brand-500/20 shadow-card' : '' }}"
                        >
                            @if ($isHariIni)
                                <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-brand-500 to-brand-600"></div>
                            @endif

                            <div class="flex items-center justify-between border-b pb-4 {{ $isHariIni ? 'border-brand-100' : 'border-ink/10' }}">
                                <div class="flex items-center gap-2.5">
                                    <span class="inline-flex items-center gap-2 rounded-xl bg-brand-50 px-3 py-1.5 font-display text-xs font-bold uppercase tracking-wider text-brand-600">
                                        <x-icon name="calendar_month" class="h-4 w-4" />
                                        <span>{{ ucfirst($hari) }}</span>
                                    </span>
                                    @if ($isHariIni)
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-200/80 bg-brand-50/80 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-brand-700">
                                            <span class="relative flex h-1.5 w-1.5">
                                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-brand-400 opacity-75"></span>
                                                <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-brand-500"></span>
                                            </span>
                                            <span>Hari Ini</span>
                                        </span>
                                    @endif
                                </div>
                                <span class="text-xs {{ $isHariIni ? 'font-semibold text-brand-700' : 'font-medium text-slate' }}">
                                    {{ count($jadwalHari) }} Sesi Pelajaran
                                </span>
                            </div>

                            <ul class="mt-4 space-y-3">
                                @foreach ($jadwalHari as $jadwal)
                                    @php
                                        $jamMulai = $jadwal->jamPelajaran?->jam_mulai ? substr($jadwal->jamPelajaran->jam_mulai, 0, 5) : '-';
                                        $jamSelesai = $jadwal->jamPelajaran?->jam_selesai ? substr($jadwal->jamPelajaran->jam_selesai, 0, 5) : '-';
                                        $ruanganNama = $jadwal->ruangan?->nama_ruangan ?? $jadwal->ruang?->nama;
                                    @endphp
                                    <li class="flex items-center justify-between gap-4 rounded-2xl border p-3.5 transition {{ $isHariIni ? 'border-brand-200/80 bg-white hover:border-brand-300 hover:shadow-xs' : 'border-ink/10 bg-paper/40 hover:border-brand-200 hover:bg-white hover:shadow-card' }}">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <h4 class="truncate font-display font-bold text-sm text-ink">
                                                    {{ $jadwal->mataPelajaran?->nama ?? 'Tematik' }}
                                                </h4>
                                                @if ($ruanganNama)
                                                    <x-badge tone="slate" class="shrink-0 text-[10px]">
                                                        {{ $ruanganNama }}
                                                    </x-badge>
                                                @endif
                                            </div>
                                            <p class="mt-1 flex items-center gap-1.5 text-xs text-slate">
                                                <x-icon name="person" class="h-3.5 w-3.5 text-slate/70" />
                                                <span>{{ $jadwal->guru?->nama ?? 'Guru Pengampu' }}</span>
                                            </p>
                                        </div>

                                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-ink/10 bg-white px-2.5 py-1 text-xs font-mono font-medium text-gray-700 shadow-sm">
                                            <x-icon name="schedule" class="h-3.5 w-3.5 text-brand-500" />
                                            <span>{{ $jamMulai }} - {{ $jamSelesai }}</span>
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </x-panel>
                    @endforeach
                </div>

                {{-- 2. Tampilan Matriks Roster Mingguan (Matrix Mode) --}}
                <div x-show="viewMode === 'matrix'" x-cloak style="display: none;">
                    <x-panel class="p-6">
                        <div class="border-b border-ink/10 pb-4 mb-5 flex items-center justify-between">
                            <div>
                                <h3 class="font-display text-base font-bold text-ink">Matriks Roster Mingguan</h3>
                                <p class="text-xs text-slate mt-0.5">Gambaran jadwal mingguan anak Anda yang terorganisir per hari.</p>
                            </div>
                            @if ($adaJadwalHariIni)
                                <button
                                    type="button"
                                    @click="scrollToToday()"
                                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-600 hover:text-brand-700 transition"
                                >
                                    <span class="flex h-2 w-2 rounded-full bg-brand-500 animate-pulse"></span>
                                    <span>Gulir ke Hari Ini</span>
                                </button>
                            @endif
                        </div>

                        <div class="overflow-x-auto pb-2 scroll-smooth">
                            <div class="inline-flex min-w-full gap-4 items-start">
                                @foreach ($jadwalList as $hari => $jadwalHari)
                                    @php
                                        $isHariIni = (strtolower($hari) === $hariIniKey);
                                    @endphp
                                    <div
                                        id="jadwal-matrix-{{ strtolower($hari) }}"
                                        class="relative w-64 shrink-0 overflow-hidden rounded-2xl border p-4 flex flex-col transition {{ $isHariIni ? 'border-brand-300/80 bg-gradient-to-b from-brand-50/40 via-white to-white ring-1 ring-brand-500/20 shadow-card' : 'border-ink/10 bg-paper/40' }}"
                                    >
                                        @if ($isHariIni)
                                            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-brand-500 to-brand-600"></div>
                                        @endif

                                        <div class="flex items-center justify-between pb-3 border-b {{ $isHariIni ? 'border-brand-100' : 'border-ink/10' }} mb-3">
                                            <div class="flex items-center gap-1.5">
                                                <span class="inline-flex items-center gap-1 font-display text-xs font-bold uppercase tracking-wider px-2.5 py-1 rounded-lg text-brand-700 bg-brand-50">
                                                    <x-icon name="calendar_month" class="h-3.5 w-3.5 text-brand-500" />
                                                    <span>{{ ucfirst($hari) }}</span>
                                                </span>
                                                @if ($isHariIni)
                                                    <span class="inline-flex items-center gap-1 rounded-full border border-brand-200/80 bg-brand-50/80 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-brand-700">
                                                        <span class="relative flex h-1.5 w-1.5">
                                                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-brand-400 opacity-75"></span>
                                                            <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-brand-500"></span>
                                                        </span>
                                                        <span>Hari Ini</span>
                                                    </span>
                                                @endif
                                            </div>
                                            <span class="text-[11px] {{ $isHariIni ? 'text-brand-700 font-semibold' : 'text-slate font-medium' }}">
                                                {{ count($jadwalHari) }} Sesi
                                            </span>
                                        </div>

                                        <div class="space-y-2.5">
                                            @foreach ($jadwalHari as $jadwal)
                                                @php
                                                    $jamMulai = $jadwal->jamPelajaran?->jam_mulai ? substr($jadwal->jamPelajaran->jam_mulai, 0, 5) : '-';
                                                    $jamSelesai = $jadwal->jamPelajaran?->jam_selesai ? substr($jadwal->jamPelajaran->jam_selesai, 0, 5) : '-';
                                                    $ruanganNama = $jadwal->ruangan?->nama_ruangan ?? $jadwal->ruang?->nama;
                                                @endphp
                                                <div class="rounded-xl border border-ink/10 bg-white p-3 hover:border-brand-200 hover:shadow-xs transition">
                                                    <span class="inline-flex items-center gap-1 text-[11px] font-mono font-medium text-brand-600 mb-1.5">
                                                        <x-icon name="schedule" class="h-3 w-3 text-brand-500" />
                                                        <span>{{ $jamMulai }} - {{ $jamSelesai }}</span>
                                                    </span>
                                                    <h5 class="font-display font-bold text-xs text-ink line-clamp-2">
                                                        {{ $jadwal->mataPelajaran?->nama ?? 'Tematik' }}
                                                    </h5>
                                                    @if ($jadwal->guru)
                                                        <p class="text-[11px] text-slate truncate mt-1 flex items-center gap-1">
                                                            <x-icon name="person" class="h-3 w-3 text-slate/70" />
                                                            <span>{{ $jadwal->guru->nama }}</span>
                                                        </p>
                                                    @endif
                                                    @if ($ruanganNama)
                                                        <div class="mt-1.5">
                                                            <x-badge tone="slate" class="text-[9px] px-1.5 py-0.5">
                                                                {{ $ruanganNama }}
                                                            </x-badge>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </x-panel>
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
