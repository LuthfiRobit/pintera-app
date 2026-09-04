<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6 pt-2">
        <div>
            <h1 class="font-display text-2xl font-bold tracking-tight text-ink">Jadwal Anak</h1>
            <p class="mt-1 text-sm text-slate">Jadwal pelajaran mingguan anak Anda pada semester aktif.</p>
        </div>

        @if ($anakList->isEmpty())
            <x-panel class="p-8 text-center">
                <p class="text-sm text-slate">Belum ada data siswa yang terhubung dengan akun Anda.</p>
            </x-panel>
        @else
            <x-panel class="p-6">
                <form method="GET">
                    <x-input-label value="Pilih Anak" />
                    <select name="siswa_id" onchange="this.form.submit()" class="mt-1.5 block w-full rounded-lg border-ink/15 text-sm text-ink shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:max-w-sm">
                        @foreach ($anakList as $anakOpsi)
                            <option value="{{ $anakOpsi->id }}" @selected($anak?->id === $anakOpsi->id)>{{ $anakOpsi->nama_lengkap }} &middot; {{ $anakOpsi->kelas?->nama ?? 'Belum Ditentukan' }}</option>
                        @endforeach
                    </select>
                </form>
            </x-panel>

            @if ($jadwalList->isEmpty())
                <x-panel class="p-10 text-center">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-paper text-slate">
                        <x-icon name="event" class="h-6 w-6" />
                    </span>
                    <p class="mt-3 text-xs font-medium text-slate">Belum ada jadwal pelajaran untuk semester aktif.</p>
                </x-panel>
            @else
                @foreach ($jadwalList as $hari => $jadwalHari)
                    <x-panel class="p-6">
                        <h3 class="font-display font-bold text-sm uppercase tracking-wider text-slate pb-3 border-b border-ink/10">{{ ucfirst($hari) }}</h3>
                        <ul class="mt-4 space-y-3">
                            @foreach ($jadwalHari as $jadwal)
                                <li class="flex items-center justify-between rounded-2xl border border-ink/10 bg-paper/40 p-3.5">
                                    <div class="min-w-0 flex-1">
                                        <h4 class="truncate font-display font-bold text-xs text-ink">{{ $jadwal->mataPelajaran?->nama ?? 'Tematik' }}</h4>
                                        <p class="text-[11px] text-slate/80 mt-0.5">
                                            {{ $jadwal->jamPelajaran?->jam_mulai }} - {{ $jadwal->jamPelajaran?->jam_selesai }}
                                            @if ($jadwal->guru)
                                                &middot; <span class="font-medium text-ink/70">{{ $jadwal->guru->nama }}</span>
                                            @endif
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </x-panel>
                @endforeach
            @endif
        @endif
    </div>
</x-app-layout>
