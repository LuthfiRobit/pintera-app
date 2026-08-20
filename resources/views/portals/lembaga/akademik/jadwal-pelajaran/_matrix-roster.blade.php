@if (isset($jamPelajaranPerHari) && $jamPelajaranPerHari->isEmpty())
    <div class="px-6 py-16 text-center bg-white rounded-2xl border border-gray-200">
        <x-icon name="schedule" class="mx-auto h-10 w-10 text-gray-300 mb-2" />
        <p class="text-sm font-semibold text-gray-700">Belum Ada Pola Jam untuk Kelas Ini</p>
        <p class="text-xs text-gray-500 mt-1">Anda perlu mengatur struktur slot jam pelajaran dan hari aktif terlebih dahulu.</p>
        @can('pola-jam.view')
            <div class="mt-4">
                <a href="{{ route('admin.pola-jam.index') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-50 px-4 py-2 text-xs font-bold text-brand-700 hover:bg-brand-100 transition-colors">
                    <span>Atur Pola Jam Sekarang</span>
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>
        @endcan
    </div>
@else
    @php
        $maxSlots = $jamPelajaranPerHari->max(fn ($group) => $group['items']->count()) ?? 0;
        $jadwalByJam = $jadwalList->keyBy('jam_pelajaran_id');
    @endphp

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 border-separate border-spacing-0">
                <thead>
                    <tr class="bg-gray-50 text-left">
                        <th scope="col" class="sticky left-0 z-10 bg-gray-50 py-3.5 pl-6 pr-4 text-xs font-bold text-gray-600 uppercase tracking-wider border-b border-r border-gray-200 w-36 shrink-0 shadow-xs">
                            Sesi / Jam
                        </th>
                        @foreach ($jamPelajaranPerHari as $group)
                            <th scope="col" class="px-4 py-3.5 text-center text-xs font-bold text-gray-800 uppercase tracking-wider border-b border-r border-gray-200 last:border-r-0 min-w-[220px] bg-gray-50/90">
                                <div class="flex items-center justify-center gap-1.5">
                                    <x-icon name="calendar_today" class="h-4 w-4 text-brand-500" />
                                    <span>{{ $group['hari']->label() }}</span>
                                    <span class="ml-1 rounded-full bg-gray-200 px-2 py-0.5 text-[10px] font-bold text-gray-700">{{ $group['items']->count() }} Slot</span>
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @for ($rowIndex = 0; $rowIndex < $maxSlots; $rowIndex++)
                        <tr class="hover:bg-gray-50/30 transition-colors">
                            <td class="sticky left-0 z-10 bg-white hover:bg-gray-50/80 transition-colors px-4 py-5 border-b border-r border-gray-200 align-top text-center shadow-xs">
                                <span class="inline-block px-3 py-1 text-xs font-bold text-brand-700 bg-brand-50 border border-brand-200/60 rounded-xl shadow-xs">
                                    Jam ke-{{ $rowIndex + 1 }}
                                </span>
                            </td>
                            @foreach ($jamPelajaranPerHari as $group)
                                @php
                                    $slot = $group['items']->values()->get($rowIndex);
                                @endphp
                                <td class="px-3.5 py-3.5 border-b border-r border-gray-200 last:border-r-0 align-top">
                                    @if ($slot)
                                        @php
                                            $jadwal = $jadwalByJam->get($slot->id);
                                            $waktuMulai = substr($slot->jam_mulai, 0, 5);
                                            $waktuSelesai = substr($slot->jam_selesai, 0, 5);
                                            $ruanganNama = $jadwal?->ruangan?->nama_ruangan ?? ($kelas?->ruangan?->nama_ruangan ?? null);
                                        @endphp
                                        @if ($jadwal)
                                            {{-- Executive Schedule Card (Terisi) Sesuai Referensi Gambar --}}
                                            <div class="group relative rounded-2xl border border-gray-200/90 bg-white p-4 shadow-xs hover:shadow-md transition-all duration-200 hover:border-brand-300 flex flex-col justify-between h-full min-h-[140px]">
                                                <div class="space-y-3">
                                                    {{-- 1. Badge Waktu --}}
                                                    <div>
                                                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200/80 bg-white px-2.5 py-1 text-xs font-bold font-mono text-gray-900 shadow-2xs">
                                                            <x-icon name="schedule" class="h-4 w-4 text-brand-500" />
                                                            <span>{{ $waktuMulai }}–{{ $waktuSelesai }}</span>
                                                        </span>
                                                    </div>

                                                    {{-- 2. Mata Pelajaran --}}
                                                    <h4 class="font-display text-sm font-bold text-gray-900 leading-snug break-words group-hover:text-brand-600 transition-colors">
                                                        {{ $jadwal->mataPelajaran?->nama ?? '(tanpa mapel)' }}
                                                    </h4>

                                                    {{-- 3. Guru Pengampu --}}
                                                    <div class="flex items-center gap-2 text-xs text-gray-700">
                                                        <div class="h-6 w-6 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center shrink-0 border border-gray-200">
                                                            <x-icon name="person" class="h-3.5 w-3.5" />
                                                        </div>
                                                        <span class="font-medium truncate" title="{{ $jadwal->guru->nama }}">{{ $jadwal->guru->nama }}</span>
                                                    </div>

                                                    {{-- 4. Ruangan Sarpras --}}
                                                    @if ($ruanganNama)
                                                        <div class="flex items-center gap-2 text-xs text-brand-600">
                                                            <x-icon name="meeting_room" class="h-4 w-4 text-brand-500 shrink-0" />
                                                            <span class="font-medium truncate text-gray-700" title="{{ $ruanganNama }}">{{ $ruanganNama }}</span>
                                                        </div>
                                                    @endif
                                                </div>

                                                @can('jadwal-pelajaran.kelola')
                                                    <div class="mt-3.5 pt-2.5 border-t border-gray-100 flex items-center justify-end gap-3 opacity-95 sm:opacity-0 group-hover:opacity-100 transition-opacity">
                                                        <a href="{{ route('admin.jadwal-pelajaran.edit', $jadwal) }}" @click.prevent="openEditModal({ id: {{ $jadwal->id }}, jam_id: {{ $jadwal->jam_pelajaran_id }}, mapel_id: {{ $jadwal->mata_pelajaran_id ?? 'null' }}, guru_id: {{ $jadwal->guru_id }}, url: '{{ route('admin.jadwal-pelajaran.update', $jadwal) }}' })" class="inline-flex items-center gap-1 text-[11px] font-bold text-brand-600 hover:text-brand-700 transition">
                                                            <x-icon name="edit" class="h-3.5 w-3.5" />
                                                            <span>Edit</span>
                                                        </a>
                                                        <form method="POST" action="{{ route('admin.jadwal-pelajaran.destroy', $jadwal) }}" x-data @submit.prevent="confirmDialog('Hapus Jadwal?', @js('Apakah Anda yakin ingin menghapus jadwal ' . ($jadwal->mataPelajaran?->nama ?? 'ini') . ' oleh ' . $jadwal->guru->nama . '?'), { confirmLabel: 'Ya, Hapus' }).then(confirmed => { if (confirmed) $el.submit() })" class="inline-flex">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="inline-flex items-center gap-1 text-[11px] font-bold text-error-500 hover:text-error-700 transition">
                                                                <x-icon name="delete" class="h-3.5 w-3.5" />
                                                                <span>Hapus</span>
                                                            </button>
                                                        </form>
                                                    </div>
                                                @endcan
                                            </div>
                                        @else
                                            {{-- Empty Slot Dropzone --}}
                                            @can('jadwal-pelajaran.kelola')
                                                <div @click="openCreateModal({ jam_ids: [{{ $slot->id }}] })" class="group flex flex-col items-center justify-center text-center rounded-2xl border-2 border-dashed border-gray-200 hover:border-brand-400 bg-gray-50/40 hover:bg-brand-50/30 p-4 transition-all duration-200 cursor-pointer h-full min-h-[140px]">
                                                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200/60 bg-white/80 px-2.5 py-1 text-xs font-mono font-bold text-gray-500 group-hover:text-brand-600 group-hover:border-brand-200 mb-2">
                                                        <x-icon name="schedule" class="h-3.5 w-3.5 opacity-60" />
                                                        <span>{{ $waktuMulai }}–{{ $waktuSelesai }}</span>
                                                    </span>
                                                    <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-white shadow-xs border border-gray-200 group-hover:border-brand-300 group-hover:bg-brand-50 text-gray-400 group-hover:text-brand-600 transition-all mb-1 group-hover:scale-105">
                                                        <x-icon name="add" class="h-4 w-4" />
                                                    </span>
                                                    <span class="text-xs font-bold text-gray-400 group-hover:text-brand-700 transition-colors">+ Isi Jadwal</span>
                                                </div>
                                            @else
                                                <div class="flex flex-col items-center justify-center text-center rounded-2xl border border-dashed border-gray-200 bg-gray-50/20 p-4 h-full min-h-[140px] text-gray-400">
                                                    <span class="text-xs font-mono font-semibold text-gray-500">{{ $waktuMulai }}–{{ $waktuSelesai }}</span>
                                                    <span class="text-xs font-medium text-gray-400 mt-1">Kosong</span>
                                                </div>
                                            @endcan
                                        @endif
                                    @else
                                        <div class="h-full min-h-[140px] bg-gray-50/30 rounded-2xl border border-dashed border-gray-100 flex items-center justify-center text-gray-300 text-xs">
                                            —
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    </div>
@endif
