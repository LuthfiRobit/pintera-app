@if ($kelasId && $semesterId)
    <div class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-gray-200 shadow-xs">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="font-display text-base font-bold text-gray-900">Jadwal Pelajaran Kelas</h2>
                    <span class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-0.5 text-[11px] font-bold text-brand-700 border border-brand-200/60">
                        Total {{ $jadwalList->count() }} Sesi
                    </span>
                </div>
                <p class="text-xs text-gray-500 mt-0.5">Jadwal kegiatan belajar mengajar mingguan untuk kelas dan semester yang terpilih.</p>
            </div>

            <div class="inline-flex rounded-xl bg-gray-100 p-1 shrink-0 border border-gray-200/60">
                <button
                    type="button"
                    @click="viewMode = 'matrix'"
                    :class="viewMode === 'matrix' ? 'bg-white text-gray-900 shadow-xs font-bold' : 'text-gray-500 hover:text-gray-800 font-medium'"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs transition-all duration-200"
                >
                    <x-icon name="grid_on" class="h-4 w-4 text-brand-500" />
                    <span>Matriks Roster</span>
                </button>
                <button
                    type="button"
                    @click="viewMode = 'list'"
                    :class="viewMode === 'list' ? 'bg-white text-gray-900 shadow-xs font-bold' : 'text-gray-500 hover:text-gray-800 font-medium'"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs transition-all duration-200"
                >
                    <x-icon name="format_list_bulleted" class="h-4 w-4 text-gray-500" />
                    <span>Tampilan Daftar</span>
                </button>
            </div>
        </div>

        {{-- Tampilan Matriks Mingguan --}}
        <div x-show="viewMode === 'matrix'" x-transition:enter="transition ease-out duration-200 opacity-0 transform translate-y-1" x-transition:enter-end="opacity-100 transform translate-y-0">
            @include('admin.jadwal-pelajaran._matrix-roster')
        </div>

        {{-- Tampilan Daftar Klasik --}}
        <div x-show="viewMode === 'list'" x-cloak style="display: none;" x-transition:enter="transition ease-out duration-200 opacity-0 transform translate-y-1" x-transition:enter-end="opacity-100 transform translate-y-0">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 bg-white px-6 py-4">
                    <div>
                        <h3 class="font-display text-base font-bold text-gray-900">Daftar Jadwal Pelajaran</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Daftar sesi belajar mengajar berurut per hari kerja aktif.</p>
                    </div>
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

                                                    {{-- Mata Pelajaran & Guru & Ruangan --}}
                                                    <div class="flex flex-wrap items-center gap-2 md:gap-3">
                                                        <span class="text-sm font-bold text-gray-900">
                                                            {{ $jadwal->mataPelajaran?->nama ?? '(tanpa mapel)' }}
                                                        </span>
                                                        <span class="inline-flex items-center gap-1.5 text-xs text-gray-600 sm:border-l sm:border-gray-200 sm:pl-3">
                                                            <x-icon name="person" class="h-3.5 w-3.5 text-gray-400" />
                                                            <span>Guru: <strong class="font-semibold text-gray-800">{{ $jadwal->guru->nama }}</strong></span>
                                                        </span>
                                                        @if ($jadwal->ruangan)
                                                            <span class="inline-flex items-center gap-1 rounded-md bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-700 border border-brand-200/60">
                                                                <x-icon name="meeting_room" class="h-3 w-3 text-brand-500" />
                                                                <span>{{ $jadwal->ruangan->nama_ruangan }}</span>
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>

                                                @can('jadwal-pelajaran.kelola')
                                                    <div class="flex items-center gap-4">
                                                        <a href="{{ route('admin.jadwal-pelajaran.edit', $jadwal) }}" @click.prevent="openEditModal({ id: {{ $jadwal->id }}, jam_id: {{ $jadwal->jam_pelajaran_id }}, mapel_id: {{ $jadwal->mata_pelajaran_id ?? 'null' }}, guru_id: {{ $jadwal->guru_id }}, url: '{{ route('admin.jadwal-pelajaran.update', $jadwal) }}' })" class="text-xs font-semibold text-gray-500 hover:text-gray-900 transition-colors">Edit</a>
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
        </div>

        @include('admin.jadwal-pelajaran._modal-form')
        @include('admin.jadwal-pelajaran._modal-duplicate')
    </div>
@else
    <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
        <x-icon name="filter_alt" class="mx-auto h-10 w-10 text-gray-300 mb-2" />
        <p class="text-sm font-semibold text-gray-700">Lengkapi Filter Terlebih Dahulu</p>
        <p class="text-xs text-gray-500 mt-1">Pilih Tahun Ajaran, Semester, dan Kelas untuk menampilkan jadwal pelajaran.</p>
    </div>
@endif
