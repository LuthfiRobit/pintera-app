<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6 pt-2" x-data="{ viewMode: 'list' }">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-2xl font-bold tracking-tight text-ink">Jadwal Pelajaran Saya</h1>
                <p class="mt-1 text-sm text-slate">Jadwal kegiatan pembelajaran mingguan kelas Anda pada semester aktif.</p>
            </div>
            @if ($siswa)
                <div class="inline-flex items-center gap-2 rounded-xl bg-paper px-3.5 py-1.5 border border-ink/10 text-xs font-medium text-slate">
                    <x-icon name="school" class="h-4 w-4 text-brand-500" />
                    <span>{{ $siswa->nama_lengkap }}</span>
                    <span class="text-ink/20">&middot;</span>
                    <span class="font-semibold text-ink">{{ $siswa->kelas?->nama ?? 'Belum Ada Kelas' }}</span>
                </div>
            @endif
        </div>

        <x-panel class="p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <form method="GET" action="{{ route('admin.jadwal-pelajaran-saya.index') }}" class="w-full sm:max-w-xs">
                    <x-input-label value="Pilih Semester" class="font-medium text-xs text-slate uppercase tracking-wider" />
                    <select id="semester_id" name="semester_id" onchange="this.form.submit()" class="mt-1.5 block w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm transition focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($semesterList as $sem)
                            <option value="{{ $sem->id }}" @selected($sem->id == $semesterId)>{{ $sem->nama }}</option>
                        @endforeach
                    </select>
                </form>

                @if (!$jadwalList->isEmpty())
                    <div class="inline-flex rounded-xl bg-paper p-1 shrink-0 border border-ink/10 self-start sm:self-end">
                        <button
                            type="button"
                            @click="viewMode = 'list'"
                            :class="viewMode === 'list' ? 'bg-white text-ink shadow-sm font-bold' : 'text-slate hover:text-ink font-medium'"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs transition"
                        >
                            <x-icon name="checklist" class="h-3.5 w-3.5 text-brand-500" />
                            <span>Tampilan Daftar</span>
                        </button>
                        <button
                            type="button"
                            @click="viewMode = 'matrix'"
                            :class="viewMode === 'matrix' ? 'bg-white text-ink shadow-sm font-bold' : 'text-slate hover:text-ink font-medium'"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs transition"
                        >
                            <x-icon name="dashboard" class="h-3.5 w-3.5 text-brand-500" />
                            <span>Matriks Mingguan</span>
                        </button>
                    </div>
                @endif
            </div>
        </x-panel>

        @if ($jadwalList->isEmpty())
            <x-panel class="p-12 text-center">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-paper text-slate">
                    <x-icon name="event" class="h-7 w-7" />
                </span>
                <h4 class="mt-3 font-display text-sm font-semibold text-ink">Belum Ada Jadwal Pelajaran</h4>
                <p class="mt-1 text-xs text-slate">Tidak ada jadwal pelajaran yang tercatat untuk kelas dan semester ini.</p>
            </x-panel>
        @else
            {{-- 1. Tampilan Daftar (List Mode) --}}
            <div x-show="viewMode === 'list'" class="space-y-6">
                @foreach ($jadwalList as $hari => $jadwalHari)
                    <x-panel class="p-6">
                        <div class="flex items-center justify-between border-b border-ink/10 pb-4">
                            <span class="inline-flex items-center gap-2 rounded-xl bg-brand-50 px-3 py-1.5 font-display text-xs font-bold uppercase tracking-wider text-brand-600">
                                <x-icon name="calendar_month" class="h-4 w-4" />
                                <span>{{ ucfirst($hari) }}</span>
                            </span>
                            <span class="text-xs font-medium text-slate">
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
                                <li class="flex items-center justify-between gap-4 rounded-2xl border border-ink/10 bg-paper/40 p-3.5 transition hover:border-brand-200 hover:bg-white hover:shadow-card">
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
                    <div class="border-b border-ink/10 pb-4 mb-5">
                        <h3 class="font-display text-base font-bold text-ink">Matriks Roster Mingguan</h3>
                        <p class="text-xs text-slate mt-0.5">Gambaran jadwal mingguan kelas Anda yang terorganisir per hari.</p>
                    </div>

                    <div class="overflow-x-auto pb-2">
                        <div class="inline-flex min-w-full gap-4 items-start">
                            @foreach ($jadwalList as $hari => $jadwalHari)
                                <div class="w-64 shrink-0 rounded-2xl border border-ink/10 bg-paper/40 p-4 flex flex-col">
                                    <div class="flex items-center justify-between pb-3 border-b border-ink/10 mb-3">
                                        <span class="inline-flex items-center gap-1.5 font-display text-xs font-bold uppercase tracking-wider text-brand-700 bg-brand-50 px-2.5 py-1 rounded-lg">
                                            <x-icon name="calendar_month" class="h-3.5 w-3.5 text-brand-500" />
                                            <span>{{ ucfirst($hari) }}</span>
                                        </span>
                                        <span class="text-[11px] font-medium text-slate">
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
    </div>
</x-app-layout>
