<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6 pt-2">
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
            <form method="GET" action="{{ route('admin.jadwal-pelajaran-saya.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label value="Pilih Semester" class="font-medium text-xs text-slate uppercase tracking-wider" />
                    <select id="semester_id" name="semester_id" onchange="this.form.submit()" class="mt-1.5 block w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm transition focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($semesterList as $sem)
                            <option value="{{ $sem->id }}" @selected($sem->id == $semesterId)>{{ $sem->nama }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
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
            <div class="space-y-6">
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

                        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @foreach ($jadwalHari as $jadwal)
                                <div class="flex flex-col justify-between rounded-2xl border border-ink/10 bg-paper/40 p-4 transition hover:border-brand-200 hover:bg-white hover:shadow-card">
                                    <div>
                                        <div class="flex items-start justify-between gap-2">
                                            <h4 class="font-display text-sm font-bold text-ink">
                                                {{ $jadwal->mataPelajaran?->nama ?? 'Tematik' }}
                                            </h4>
                                            @if ($jadwal->ruang)
                                                <x-badge tone="slate" class="shrink-0 text-[10px]">
                                                    {{ $jadwal->ruang->nama }}
                                                </x-badge>
                                            @endif
                                        </div>
                                        <p class="mt-1 flex items-center gap-1.5 text-xs text-slate">
                                            <x-icon name="person" class="h-3.5 w-3.5 text-slate/70" />
                                            <span>{{ $jadwal->guru?->nama ?? 'Guru Pengampu' }}</span>
                                        </p>
                                    </div>

                                    <div class="mt-3 pt-3 border-t border-ink/5 flex items-center justify-between">
                                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-ink/10 bg-white px-2.5 py-1 text-xs font-mono font-medium text-gray-700 shadow-sm">
                                            <x-icon name="schedule" class="h-3.5 w-3.5 text-brand-500" />
                                            <span>{{ $jadwal->jamPelajaran?->jam_mulai }} - {{ $jadwal->jamPelajaran?->jam_selesai }}</span>
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-panel>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
