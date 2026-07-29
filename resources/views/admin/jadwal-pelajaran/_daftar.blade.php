@if ($kelasId && $semesterId)
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 bg-white px-6 py-4">
            <div>
                <h2 class="font-display text-base font-bold text-gray-900">Daftar Jadwal Pelajaran</h2>
                <p class="text-xs text-gray-500 mt-0.5">Jadwal kegiatan belajar mengajar mingguan untuk kelas dan semester yang terpilih.</p>
            </div>
            <span class="inline-flex items-center rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-600 ring-1 ring-inset ring-brand-500/20">
                Total {{ $jadwalList->count() }} Sesi
            </span>
        </div>

        @if ($jadwalList->isEmpty())
            <div class="px-6 py-16 text-center">
                <x-icon name="event_busy" class="mx-auto h-10 w-10 text-gray-300 mb-2" />
                <p class="text-sm font-semibold text-gray-700">Belum Ada Jadwal Pelajaran</p>
                <p class="text-xs text-gray-500 mt-1">Belum ada slot waktu dan mata pelajaran yang diatur untuk kelas dan semester ini.</p>
            </div>
        @else
            <div class="divide-y divide-gray-100 bg-white">
                @foreach ($hariAktif as $hari)
                    @php $jadwalHariIni = $jadwalList->where('jamPelajaran.hari', $hari)->sortBy('jamPelajaran.urutan'); @endphp
                    @if ($jadwalHariIni->isNotEmpty())
                        <div>
                            {{-- Section Hari --}}
                            <div class="flex items-center justify-between bg-gray-50/75 px-6 py-3 border-y border-gray-100 mt-[-1px]">
                                <div class="flex items-center gap-2">
                                    <x-icon name="calendar_today" class="h-4 w-4 text-brand-500" />
                                    <span class="text-[12px] font-bold uppercase tracking-wider text-gray-700">{{ $hari->label() }}</span>
                                </div>
                                <span class="text-xs font-medium text-gray-500">{{ $jadwalHariIni->count() }} sesi</span>
                            </div>

                            {{-- Daftar Slot per Hari --}}
                            <ul class="divide-y divide-gray-100">
                                @foreach ($jadwalHariIni as $jadwal)
                                    <li class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 px-6 py-4 transition-colors duration-150 hover:bg-gray-50/60">
                                        <div class="flex flex-wrap items-center gap-3 md:gap-4">
                                            {{-- Badge Waktu (Format tanpa detik: H:i) --}}
                                            <div class="flex items-center gap-2 font-mono text-xs">
                                                <span class="rounded bg-brand-50 px-2.5 py-1 font-bold text-brand-600 ring-1 ring-inset ring-brand-500/20">
                                                    {{ substr($jadwal->jamPelajaran->jam_mulai, 0, 5) }}
                                                </span>
                                                <span class="text-gray-400 font-medium">&rarr;</span>
                                                <span class="rounded bg-gray-100 px-2.5 py-1 font-semibold text-gray-700 ring-1 ring-inset ring-gray-300/60">
                                                    {{ substr($jadwal->jamPelajaran->jam_selesai, 0, 5) }}
                                                </span>
                                            </div>

                                            <span class="hidden md:inline text-gray-300">&bull;</span>

                                            {{-- Badge Label Slot (Jam ke-1, Istirahat, dll) --}}
                                            <span class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 ring-1 ring-inset ring-gray-200/60">
                                                {{ $jadwal->jamPelajaran->label }}
                                            </span>

                                            <span class="hidden md:inline text-gray-300">&bull;</span>

                                            {{-- Mata Pelajaran & Guru --}}
                                            <div class="flex flex-wrap items-center gap-2 md:gap-3">
                                                <span class="text-sm font-bold text-gray-900">
                                                    {{ $jadwal->mataPelajaran?->nama ?? '(tanpa mapel)' }}
                                                </span>
                                                <span class="inline-flex items-center gap-1.5 text-xs text-gray-600 sm:border-l sm:border-gray-200 sm:pl-3">
                                                    <x-icon name="person" class="h-3.5 w-3.5 text-gray-400" />
                                                    <span>Guru: <strong class="font-semibold text-gray-800">{{ $jadwal->guru->nama }}</strong></span>
                                                </span>
                                            </div>
                                        </div>

                                        @can('jadwal-pelajaran.kelola')
                                            <div class="flex items-center gap-4">
                                                <a href="{{ route('admin.jadwal-pelajaran.edit', $jadwal) }}" class="text-xs font-semibold text-gray-500 hover:text-gray-900 transition-colors">Edit</a>
                                                <form method="POST" action="{{ route('admin.jadwal-pelajaran.destroy', $jadwal) }}" x-data @submit.prevent="confirmDialog('Hapus Jadwal?', @js('Apakah Anda yakin ingin menghapus jadwal ' . ($jadwal->mataPelajaran?->nama ?? 'ini') . ' oleh ' . $jadwal->guru->nama . '?'), { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-xs font-semibold text-error-500 hover:text-error-700 transition-colors">Hapus</button>
                                                </form>
                                            </div>
                                        @endcan
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
@else
    <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
        <x-icon name="filter_alt" class="mx-auto h-10 w-10 text-gray-300 mb-2" />
        <p class="text-sm font-semibold text-gray-700">Lengkapi Filter Terlebih Dahulu</p>
        <p class="text-xs text-gray-500 mt-1">Pilih Tahun Ajaran, Semester, dan Kelas untuk menampilkan jadwal pelajaran.</p>
    </div>
@endif
