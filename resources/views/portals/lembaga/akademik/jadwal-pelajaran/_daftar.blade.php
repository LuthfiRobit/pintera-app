@if ($kelasId && $semesterId)
    <div class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-gray-200 shadow-xs">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="font-display text-base font-bold text-gray-900">Jadwal Pelajaran — {{ $kelas->nama ?? '' }}</h2>
                    <span class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-0.5 text-[11px] font-bold text-brand-700 border border-brand-200/60">
                        Total {{ $jadwalList->count() }} Sesi
                    </span>
                </div>
                <p class="text-xs text-gray-500 mt-0.5">Semester {{ $semesterList->firstWhere('id', $semesterId)?->nama ?? '—' }} · Jadwal kegiatan belajar mengajar mingguan untuk kelas dan semester yang terpilih.</p>
            </div>

            <div class="inline-flex rounded-xl bg-gray-100 p-1 shrink-0 border border-gray-200/60">
                <button
                    type="button"
                    @click="viewMode = 'matrix'"
                    :class="viewMode === 'matrix' ? 'bg-white text-gray-900 shadow-xs font-bold' : 'text-gray-500 hover:text-gray-800 font-medium'"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs transition-all duration-200"
                >
                    <x-icon name="data_table" class="h-4 w-4 text-brand-500" />
                    <span>Matriks Roster</span>
                </button>
                <button
                    type="button"
                    @click="viewMode = 'list'"
                    :class="viewMode === 'list' ? 'bg-white text-gray-900 shadow-xs font-bold' : 'text-gray-500 hover:text-gray-800 font-medium'"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs transition-all duration-200"
                >
                    <x-icon name="list" class="h-4 w-4 text-gray-500" />
                    <span>Tampilan Daftar</span>
                </button>
            </div>
        </div>

        {{-- Tampilan Matriks Mingguan --}}
        <div x-show="viewMode === 'matrix'" x-transition:enter="transition ease-out duration-200 opacity-0 transform translate-y-1" x-transition:enter-end="opacity-100 transform translate-y-0">
            @include('portals.lembaga.akademik.jadwal-pelajaran._matrix-roster')
        </div>

        {{-- Tampilan Daftar Harian (Style Pola Jam) --}}
        <div x-data="{ hariAktif: '{{ collect($hariAktif)->first()?->value ?? 'senin' }}' }" x-show="viewMode === 'list'" x-cloak style="display: none;" x-transition:enter="transition ease-out duration-200 opacity-0 transform translate-y-1" x-transition:enter-end="opacity-100 transform translate-y-0">
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
                    {{-- Tab Filter Hari Horizontal --}}
                    <div class="flex border-b border-gray-100 bg-gray-50/40 px-6 gap-2 overflow-x-auto">
                        @foreach ($hariAktif as $tabHari)
                            @php
                                $jumlahSesiHari = $jadwalList->where('jamPelajaran.hari', $tabHari)->count();
                            @endphp
                            <button type="button"
                                    @click="hariAktif = '{{ $tabHari->value }}'"
                                    :class="hariAktif === '{{ $tabHari->value }}' ? 'border-brand-500 text-brand-600 font-bold bg-white' : 'border-transparent text-gray-500 hover:text-gray-700 font-medium'"
                                    class="py-2.5 px-3 border-b-2 text-xs transition flex items-center gap-1.5 whitespace-nowrap">
                                <span>{{ $tabHari->label() }}</span>
                                <span class="rounded-full bg-gray-100 px-1.5 py-0.2 text-[10px] text-gray-600 font-semibold"
                                      :class="hariAktif === '{{ $tabHari->value }}' ? 'bg-brand-50 text-brand-700' : ''">
                                    {{ $jumlahSesiHari }}
                                </span>
                            </button>
                        @endforeach
                    </div>

                    {{-- Konten List Per Hari --}}
                    @foreach ($hariAktif as $daftarHari)
                        <div x-show="hariAktif === '{{ $daftarHari->value }}'" class="divide-y divide-gray-100">
                            @php
                                $jadwalHariIni = $jadwalList->where('jamPelajaran.hari', $daftarHari)->sortBy('jamPelajaran.urutan');
                            @endphp

                            @if ($jadwalHariIni->isEmpty())
                                <div class="px-6 py-8 text-center text-xs text-gray-400 italic">
                                    Belum ada jadwal pelajaran untuk hari {{ $daftarHari->label() }}.
                                </div>
                            @else
                                <ul class="divide-y divide-gray-100">
                                    @foreach ($jadwalHariIni as $jadwal)
                                        @php
                                            $jamMulai = substr($jadwal->jamPelajaran->jam_mulai, 0, 5);
                                            $jamSelesai = substr($jadwal->jamPelajaran->jam_selesai, 0, 5);
                                            $durasi = \Carbon\Carbon::parse($jadwal->jamPelajaran->jam_mulai)->diffInMinutes(\Carbon\Carbon::parse($jadwal->jamPelajaran->jam_selesai));
                                        @endphp
                                        <li class="flex flex-col md:flex-row md:items-center justify-between gap-4 px-6 py-3.5 transition hover:bg-gray-50/60">
                                            <div class="flex flex-wrap items-center gap-3 md:gap-4">
                                                {{-- Badge Urutan Jam --}}
                                                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-gray-100 font-mono text-xs font-bold text-gray-700">
                                                    {{ $jadwal->jamPelajaran->urutan }}
                                                </span>

                                                {{-- Rentang Waktu --}}
                                                <div class="flex items-center gap-1.5 font-mono text-xs">
                                                    <span class="rounded bg-brand-50 px-2 py-1 font-bold text-brand-700 ring-1 ring-inset ring-brand-500/20">
                                                        {{ $jamMulai }}
                                                    </span>
                                                    <span class="text-gray-400">&rarr;</span>
                                                    <span class="rounded bg-gray-100 px-2 py-1 font-semibold text-gray-700 ring-1 ring-inset ring-gray-300/50">
                                                        {{ $jamSelesai }}
                                                    </span>
                                                </div>

                                                <span class="text-[10px] font-semibold text-gray-400">({{ $durasi }} mnt)</span>
                                                <span class="text-gray-300 hidden md:inline">&bull;</span>

                                                {{-- Label Slot Jam --}}
                                                <span class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 ring-1 ring-inset ring-gray-200/60">
                                                    {{ $jadwal->jamPelajaran->label }}
                                                </span>

                                                <span class="text-gray-300 hidden md:inline">&bull;</span>

                                                {{-- Mata Pelajaran, Guru & Ruangan --}}
                                                <div class="flex flex-wrap items-center gap-2.5">
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

                                            {{-- Tombol Aksi Badge Ber-border ala Pola Jam --}}
                                            @can('jadwal-pelajaran.kelola')
                                                <div class="flex items-center gap-2 shrink-0">
                                                    <x-tooltip text="Edit Sesi Jadwal">
                                                        <a href="{{ route('admin.jadwal-pelajaran.edit', $jadwal) }}"
                                                           @click.prevent="openEditModal({ id: {{ $jadwal->id }}, jam_id: {{ $jadwal->jam_pelajaran_id }}, mapel_id: {{ $jadwal->mata_pelajaran_id ?? 'null' }}, guru_id: {{ $jadwal->guru_id }}, ruangan_id: {{ $jadwal->ruangan_id ?? 'null' }}, url: '{{ route('admin.jadwal-pelajaran.update', $jadwal) }}' })"
                                                           class="rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition">
                                                            Edit
                                                        </a>
                                                    </x-tooltip>
                                                    <x-tooltip text="Hapus Sesi Jadwal">
                                                        <button type="button"
                                                                @click="hapusJadwal('{{ route('admin.jadwal-pelajaran.destroy', $jadwal) }}', @js(($jadwal->mataPelajaran?->nama ?? 'Sesi') . ' oleh ' . $jadwal->guru->nama))"
                                                                class="rounded-lg border border-error-200 bg-error-50/30 px-2.5 py-1 text-xs font-semibold text-error-600 hover:bg-error-50 hover:text-error-700 transition">
                                                            Hapus
                                                        </button>
                                                    </x-tooltip>
                                                </div>
                                            @endcan
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                @endif
            </div>
        </div>

        @include('portals.lembaga.akademik.jadwal-pelajaran._modal-form')
        @include('portals.lembaga.akademik.jadwal-pelajaran._modal-duplicate')
    </div>
@else
    <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
        <x-icon name="filter_alt" class="mx-auto h-10 w-10 text-gray-300 mb-2" />
        <p class="text-sm font-semibold text-gray-700">Lengkapi Filter Terlebih Dahulu</p>
        <p class="text-xs text-gray-500 mt-1">Pilih Tahun Ajaran, Semester, dan Kelas untuk menampilkan jadwal pelajaran.</p>
    </div>
@endif
