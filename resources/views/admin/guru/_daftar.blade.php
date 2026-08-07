<div class="rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Daftar Guru</p>
        <div class="flex items-center gap-2">
            <label for="per_page" class="text-xs font-medium text-gray-500">Tampilkan:</label>
            <select id="per_page" x-model="perPage" @change="muatUlangDaftar()" class="rounded-lg border-gray-200 py-1 pl-2.5 pr-8 text-xs text-gray-700 shadow-sm transition focus:border-brand-500 focus:ring-brand-500">
                <option value="10">10 / hal</option>
                <option value="20">20 / hal</option>
                <option value="25">25 / hal</option>
                <option value="50">50 / hal</option>
            </select>
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
                    <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
                    <th class="px-5 py-3">Nama</th>
                    <th class="px-5 py-3">NIP</th>
                    <th class="px-5 py-3">Jenis PTK</th>
                    <th class="px-5 py-3">Status Kepegawaian</th>
                    <th class="px-5 py-3">Status Aktif</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($guruList as $item)
                    <tr class="transition hover:bg-gray-50">
                        <td class="sticky left-0 z-10 bg-white px-5 py-3">
                            <x-table-actions>
                                <x-dropdown-link :href="route('admin.guru.edit', $item)">
                                    <span class="inline-flex items-center gap-2.5">
                                        <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                        Edit Guru
                                    </span>
                                </x-dropdown-link>
                                @foreach ($statusAktifOptions as $value => $label)
                                    @if ($value !== $item->status_aktif)
                                        <form
                                            method="POST"
                                            action="{{ route('admin.guru.update-status', $item) }}"
                                            x-data
                                            @submit.prevent="confirmDialog('Ubah Status Guru?', @js('Ubah status \"' . $item->nama . '\" menjadi \"' . $label . '\"?'), { confirmLabel: 'Ya, Ubah' }).then(confirmed => { if (confirmed) $el.submit() })"
                                        >
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status_aktif" value="{{ $value }}">
                                            <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                                <x-icon name="autorenew" class="h-4 w-4 text-gray-500" />
                                                Jadikan {{ $label }}
                                            </button>
                                        </form>
                                    @endif
                                @endforeach
                            </x-table-actions>
                        </td>
                        <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $item->nama }}</td>
                        <td class="px-5 py-3.5 font-mono text-xs text-gray-600">{{ $item->nip ?: '—' }}</td>
                        <td class="px-5 py-3.5 text-gray-600">{{ $jenisPtkOptions[$item->jenis_ptk] ?? $item->jenis_ptk }}</td>
                        <td class="px-5 py-3.5">
                            @if (in_array($item->status_kepegawaian, ['PNS', 'PPPK']))
                                <x-badge tone="brass">{{ $item->status_kepegawaian }}</x-badge>
                            @else
                                <x-badge tone="slate">{{ $item->status_kepegawaian }}</x-badge>
                            @endif
                        </td>
                        <td class="px-5 py-3.5">
                            <x-badge tone="{{ $item->status_aktif === 'aktif' ? 'green' : 'amber' }}">
                                {{ $statusAktifOptions[$item->status_aktif] ?? $item->status_aktif }}
                            </x-badge>
                        </td>
                    </tr>
                @endforeach

                @if ($guruList->isEmpty())
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-gray-400">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                <x-icon name="school" class="h-7 w-7" />
                            </div>
                            <p class="mt-3 text-sm font-semibold text-gray-700">Belum Ada Data Guru</p>
                            <p class="mx-auto mt-0.5 max-w-sm text-xs text-gray-400">Tambahkan data guru pertama untuk lembaga ini.</p>
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    <div class="border-t border-gray-200 px-5 py-4">
        {{ $guruList->links('pagination.tailadmin') }}
    </div>
</div>
