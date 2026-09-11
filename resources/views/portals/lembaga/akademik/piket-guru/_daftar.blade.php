@php
    $namaHari = $namaHari ?? [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];
    $badgeHariTone = $badgeHariTone ?? [
        0 => 'rose',
        1 => 'blue',
        2 => 'indigo',
        3 => 'amber',
        4 => 'slate',
        5 => 'green',
        6 => 'purple',
    ];
    $guruOptions = collect([['id' => '', 'nama' => '— Pilih atau cari guru —', 'subtext' => '']])
        ->concat(($guruList ?? collect())->map(fn ($g) => [
            'id' => (string) $g->id,
            'nama' => $g->nama,
            'subtext' => ($g->nip ? 'NIP: '.$g->nip : ($g->nuptk ? 'NUPTK: '.$g->nuptk : ($g->jenis_ptk ? str_replace('_', ' ', ucwords($g->jenis_ptk, '_')) : ''))).((($isYayasan ?? false) && ! ($activeLembaga ?? null) && $g->lembaga) ? ' • '.$g->lembaga->nama : ''),
        ]))
        ->values();
@endphp

<div class="space-y-4">
    {{-- Tabbed Navigation Bar --}}
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex space-x-4 sm:space-x-8" aria-label="Tabs">
            <button
                type="button"
                @click="activeTab = 'mingguan'"
                :class="activeTab === 'mingguan' ? 'border-brand-600 text-brand-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                class="flex items-center gap-2 border-b-2 py-3 px-1 text-xs sm:text-sm transition whitespace-nowrap"
            >
                <x-icon name="calendar_month" class="h-4 w-4" />
                Jadwal Mingguan
                <span
                    class="rounded-full px-2 py-0.5 text-[11px] font-mono transition"
                    :class="activeTab === 'mingguan' ? 'bg-brand-100 text-brand-800' : 'bg-gray-100 text-gray-600'"
                >
                    {{ $jadwalList->count() }}
                </span>
            </button>

            <button
                type="button"
                @click="activeTab = 'kalender'"
                :class="activeTab === 'kalender' ? 'border-brand-600 text-brand-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                class="flex items-center gap-2 border-b-2 py-3 px-1 text-xs sm:text-sm transition whitespace-nowrap"
            >
                <x-icon name="event" class="h-4 w-4" />
                Kalender Piket Harian
                <span
                    class="rounded-full px-2 py-0.5 text-[11px] font-mono transition"
                    :class="activeTab === 'kalender' ? 'bg-brand-100 text-brand-800' : 'bg-gray-100 text-gray-600'"
                >
                    {{ $piketHarianMendatang->total() }}
                </span>
            </button>

            <button
                type="button"
                @click="activeTab = 'override'"
                :class="activeTab === 'override' ? 'border-brand-600 text-brand-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                class="flex items-center gap-2 border-b-2 py-3 px-1 text-xs sm:text-sm transition whitespace-nowrap"
            >
                <x-icon name="edit_calendar" class="h-4 w-4" />
                Override Manual
                <span
                    class="rounded-full px-2 py-0.5 text-[11px] font-mono transition"
                    :class="activeTab === 'override' ? 'bg-brand-100 text-brand-800' : 'bg-gray-100 text-gray-600'"
                >
                    {{ $overrides->count() }}
                </span>
            </button>
        </nav>
    </div>

    {{-- TAB 1: Jadwal Piket Mingguan --}}
    <div x-show="activeTab === 'mingguan'" class="space-y-4">
        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
                <p class="font-display text-sm font-bold text-gray-900">Daftar Jadwal Piket Mingguan</p>
                <span class="text-xs text-gray-500">{{ $jadwalList->count() }} data</span>
            </div>

            <div class="relative overflow-x-auto">
                <!-- Loading overlay -->
                <div x-show="false" class="absolute inset-0 z-20 flex items-center justify-center bg-white/50 backdrop-blur-sm"
                     x-transition.opacity
                     @ajax-start.window="$el.style.display = 'flex'"
                     @ajax-end.window="$el.style.display = 'none'">
                    <x-icon name="sync" class="h-8 w-8 animate-spin text-brand-500" />
                </div>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
                            <th class="px-5 py-3">Guru Piket</th>
                            <th class="px-5 py-3">Hari Bertugas</th>
                            <th class="px-5 py-3">Semester</th>
                            @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
                                <th class="px-5 py-3">Lembaga</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($jadwalList as $jadwal)
                            <tr class="transition hover:bg-gray-50">
                                <td class="sticky left-0 z-10 bg-white px-5 py-3">
                                    <x-table-actions>
                                        <x-dropdown-link :href="route('admin.piket-guru.edit', $jadwal)">
                                            <span class="inline-flex items-center gap-2.5">
                                                <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                                Edit Jadwal
                                            </span>
                                        </x-dropdown-link>
                                        <button
                                            type="button"
                                            class="block w-full px-4 py-2 text-left text-sm leading-5 text-error-600 transition hover:bg-error-50 hover:text-error-700"
                                            @click="confirmDialog('Hapus Jadwal Piket?', @js('Piket ' . ($jadwal->guru?->nama ?? 'guru ini') . ' pada hari ' . ($namaHari[$jadwal->hari] ?? $jadwal->hari) . ' akan dihapus. Baris piket harian otomatis yang terkait akan disinkronkan kembali.'), { confirmLabel: 'Ya, Hapus', isDanger: true }).then(confirmed => { if (confirmed) $refs['deleteForm' + {{ $jadwal->id }}].submit() })"
                                        >
                                            <span class="inline-flex items-center gap-2.5">
                                                <x-icon name="delete" class="h-4 w-4 text-error-500" />
                                                Hapus Jadwal
                                            </span>
                                        </button>
                                        <form x-ref="deleteForm{{ $jadwal->id }}" method="POST" action="{{ route('admin.piket-guru.destroy', $jadwal) }}" class="hidden">
                                            @csrf @method('DELETE')
                                        </form>
                                    </x-table-actions>
                                </td>
                                <td class="px-5 py-3.5 font-semibold text-gray-900">
                                    {{ $jadwal->guru?->nama ?? '-' }}
                                    @if ($jadwal->guru?->nip || $jadwal->guru?->nuptk)
                                        <span class="block text-xs font-normal text-gray-400">
                                            {{ $jadwal->guru->nip ? 'NIP: '.$jadwal->guru->nip : 'NUPTK: '.$jadwal->guru->nuptk }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-gray-700">
                                    <x-badge :tone="$badgeHariTone[$jadwal->hari] ?? 'slate'">
                                        {{ $namaHari[$jadwal->hari] ?? $jadwal->hari }}
                                    </x-badge>
                                </td>
                                <td class="px-5 py-3.5 text-gray-600">
                                    {{ $jadwal->semester ? $jadwal->semester->tahunAjaran->nama . ' - ' . $jadwal->semester->nama : '-' }}
                                </td>
                                @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
                                    <td class="px-5 py-3.5 text-gray-500">{{ $jadwal->lembaga->nama ?? '-' }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ (($isYayasan ?? false) && ! ($activeLembaga ?? null)) ? 5 : 4 }}" class="px-5 py-10 text-center text-gray-500">
                                    Belum ada jadwal piket yang cocok dengan filter ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- TAB 2: Kalender Piket Harian Mendatang --}}
    <div x-show="activeTab === 'kalender'" style="display: none;" class="space-y-4">
        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
                <div>
                    <p class="font-display text-sm font-bold text-gray-900">Kalender Piket Harian Mendatang</p>
                    <p class="text-xs text-gray-500 mt-0.5">Hasil generate otomatis mingguan digabung dengan override manual.</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2">
                        <label for="per_page_piket" class="text-xs font-medium text-gray-500">Tampilkan:</label>
                        <select id="per_page_piket" x-model="perPage" @change="muatUlangDaftar()" class="rounded-lg border-gray-200 py-1 pl-2.5 pr-8 text-xs text-gray-700 shadow-sm transition focus:border-brand-500 focus:ring-brand-500">
                            <option value="10">10 / hal</option>
                            <option value="20">20 / hal</option>
                            <option value="25">25 / hal</option>
                            <option value="50">50 / hal</option>
                        </select>
                    </div>
                    <span class="text-xs text-gray-500">{{ $piketHarianMendatang->total() }} data</span>
                </div>
            </div>

            <div class="relative overflow-x-auto">
                <!-- Loading overlay -->
                <div x-show="false" class="absolute inset-0 z-20 flex items-center justify-center bg-white/50 backdrop-blur-sm"
                     x-transition.opacity
                     @ajax-start.window="$el.style.display = 'flex'"
                     @ajax-end.window="$el.style.display = 'none'">
                    <x-icon name="sync" class="h-8 w-8 animate-spin text-brand-500" />
                </div>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-5 py-3">Tanggal</th>
                            <th class="px-5 py-3">Guru Piket Bertugas</th>
                            <th class="px-5 py-3">Sumber Penugasan</th>
                            @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
                                <th class="px-5 py-3">Lembaga</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($piketHarianMendatang as $item)
                            <tr class="transition hover:bg-gray-50">
                                <td class="px-5 py-3.5 font-semibold text-gray-900">
                                    {{ \Carbon\Carbon::parse($item->tanggal)->locale('id')->isoFormat('dddd, D MMMM Y') }}
                                </td>
                                <td class="px-5 py-3.5 text-gray-700">
                                    {{ $item->guru?->nama ?? '-' }}
                                    @if ($item->guru?->nip || $item->guru?->nuptk)
                                        <span class="block text-xs font-normal text-gray-400">
                                            {{ $item->guru->nip ? 'NIP: '.$item->guru->nip : 'NUPTK: '.$item->guru->nuptk }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5">
                                    @if ($item->sumber === 'override_manual')
                                        <x-badge tone="purple">Override Manual</x-badge>
                                    @else
                                        <x-badge tone="blue">Otomatis Mingguan</x-badge>
                                    @endif
                                </td>
                                @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
                                    <td class="px-5 py-3.5 text-gray-500">{{ $item->lembaga->nama ?? '-' }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ (($isYayasan ?? false) && ! ($activeLembaga ?? null)) ? 4 : 3 }}" class="px-5 py-10 text-center text-gray-500">
                                    Belum ada kalender piket harian yang cocok dengan filter ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($piketHarianMendatang->hasPages())
                <div class="border-t border-gray-200 px-5 py-4">
                    {{ $piketHarianMendatang->links('pagination.tailadmin') }}
                </div>
            @endif
        </div>
    </div>

    {{-- TAB 3: Override Manual Piket Harian --}}
    <div x-show="activeTab === 'override'" style="display: none;" class="space-y-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <div class="border-b border-gray-150 pb-3 mb-4">
                <h2 class="font-display text-base font-bold text-gray-900">Penugasan Override Manual</h2>
                <p class="text-xs text-gray-500 mt-0.5">Tugaskan guru piket khusus untuk tanggal tertentu. Jadwal override bersifat permanen dan tidak tertimpa sinkronisasi otomatis mingguan.</p>
            </div>

            @if (! ($isYayasan ?? false) || ($activeLembaga ?? null))
                <form method="POST" action="{{ route('admin.piket-harian.store') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:items-end">
                    @csrf
                    <div class="relative z-20" x-data="tomSelectPegawai({
                        options: @js($guruOptions),
                        oldValue: @js(old('guru_id', '')),
                        placeholder: '— Pilih atau cari guru —'
                    })">
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Pilih Guru</label>
                        <div>
                            <select
                                name="guru_id"
                                x-ref="selectElement"
                                class="block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                                autocomplete="off"
                                required
                            >
                                <option value="">— Pilih atau cari guru —</option>
                                @foreach ($guruList ?? [] as $guru)
                                    <option value="{{ $guru->id }}">{{ $guru->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-500">Tanggal Piket</label>
                        <input type="date" name="tanggal" required class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500" value="{{ now()->toDateString() }}" />
                    </div>
                    <div>
                        <x-primary-button type="submit" class="w-full justify-center">Tambah Override</x-primary-button>
                    </div>
                </form>
            @else
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800 flex items-start gap-3">
                    <x-icon name="warning" class="h-4 w-4 shrink-0 text-amber-600 mt-0.5" />
                    <div>
                        <p class="font-semibold">Pilih 1 Lembaga untuk Menambah Override</p>
                        <p class="mt-0.5 text-amber-700">Penugasan override manual harus terikat ke satu lembaga spesifik. Silakan gunakan pengalih lembaga di topbar untuk memilih unit sekolah yang ingin ditugaskan.</p>
                    </div>
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
                <p class="font-display text-sm font-bold text-gray-900">Daftar Override Manual Mendatang</p>
                <span class="text-xs text-gray-500">{{ $overrides->count() }} data aktif</span>
            </div>

            <div class="relative overflow-x-auto">
                <!-- Loading overlay -->
                <div x-show="false" class="absolute inset-0 z-20 flex items-center justify-center bg-white/50 backdrop-blur-sm"
                     x-transition.opacity
                     @ajax-start.window="$el.style.display = 'flex'"
                     @ajax-end.window="$el.style.display = 'none'">
                    <x-icon name="sync" class="h-8 w-8 animate-spin text-brand-500" />
                </div>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
                            <th class="px-5 py-3">Tanggal</th>
                            <th class="px-5 py-3">Guru Piket</th>
                            @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
                                <th class="px-5 py-3">Lembaga</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($overrides as $override)
                            <tr class="transition hover:bg-gray-50">
                                <td class="sticky left-0 z-10 bg-white px-5 py-3">
                                    <x-table-actions>
                                        <button
                                            type="button"
                                            class="block w-full px-4 py-2 text-left text-sm leading-5 text-error-600 transition hover:bg-error-50 hover:text-error-700"
                                            @click="confirmDialog('Hapus Override Piket?', @js('Override piket manual untuk ' . \Carbon\Carbon::parse($override->tanggal)->locale('id')->isoFormat('D MMMM Y') . ' akan dihapus.'), { confirmLabel: 'Ya, Hapus', isDanger: true }).then(confirmed => { if (confirmed) $refs['deleteOverrideForm' + {{ $override->id }}].submit() })"
                                        >
                                            <span class="inline-flex items-center gap-2.5">
                                                <x-icon name="delete" class="h-4 w-4 text-error-500" />
                                                Hapus Override
                                            </span>
                                        </button>
                                        <form x-ref="deleteOverrideForm{{ $override->id }}" method="POST" action="{{ route('admin.piket-harian.destroy', $override) }}" class="hidden">
                                            @csrf @method('DELETE')
                                        </form>
                                    </x-table-actions>
                                </td>
                                <td class="px-5 py-3.5 font-semibold text-gray-900">
                                    {{ \Carbon\Carbon::parse($override->tanggal)->locale('id')->isoFormat('dddd, D MMMM Y') }}
                                </td>
                                <td class="px-5 py-3.5 text-gray-700">
                                    {{ $override->guru?->nama ?? '-' }}
                                </td>
                                @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
                                    <td class="px-5 py-3.5 text-gray-500">{{ $override->lembaga->nama ?? '-' }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ (($isYayasan ?? false) && ! ($activeLembaga ?? null)) ? 4 : 3 }}" class="px-5 py-10 text-center text-gray-500">
                                    Tidak ada override manual aktif mendatang.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
