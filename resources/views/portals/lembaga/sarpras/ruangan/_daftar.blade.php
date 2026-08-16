<div class="rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Daftar Ruangan</p>
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
                    <th class="px-5 py-3">Kode</th>
                    <th class="px-5 py-3">Nama Ruangan</th>
                    <th class="px-5 py-3">Gedung / Lantai</th>
                    <th class="px-5 py-3">Jenis Ruangan</th>
                    <th class="px-5 py-3 text-center">Kapasitas</th>
                    <th class="px-5 py-3 text-center">Total Aset</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($ruanganList as $ruangan)
                    <tr class="transition hover:bg-gray-50">
                        <td class="sticky left-0 z-10 bg-white px-5 py-3">
                            <x-table-actions>
                                <x-dropdown-link :href="route('admin.sarpras.ruangan.show', $ruangan)">
                                    <span class="inline-flex items-center gap-2.5">
                                        <x-icon name="visibility" class="h-4 w-4 text-gray-500" />
                                        Lihat Detail
                                    </span>
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('admin.sarpras.kir.show', $ruangan)">
                                    <span class="inline-flex items-center gap-2.5">
                                        <x-icon name="receipt_long" class="h-4 w-4 text-emerald-600" />
                                        Kartu Inventaris (KIR)
                                    </span>
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('admin.sarpras.ruangan.edit', $ruangan)">
                                    <span class="inline-flex items-center gap-2.5">
                                        <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                        Edit Ruangan
                                    </span>
                                </x-dropdown-link>
                                <form action="{{ route('admin.sarpras.ruangan.destroy', $ruangan) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus ruangan ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-xs text-rose-600 hover:bg-rose-50">
                                        <x-icon name="delete" class="h-4 w-4 text-rose-500" />
                                        Hapus Ruangan
                                    </button>
                                </form>
                            </x-table-actions>
                        </td>
                        <td class="px-5 py-3.5 font-mono font-bold text-brand-600">
                            <a href="{{ route('admin.sarpras.ruangan.show', $ruangan) }}" class="hover:underline">
                                {{ $ruangan->kode_ruangan }}
                            </a>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="font-semibold text-gray-900">{{ $ruangan->nama_ruangan }}</span>
                            @if ($ruangan->is_shared)
                                <span class="ml-1.5"><x-badge tone="purple">Shared</x-badge></span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-gray-600">
                            {{ $ruangan->gedung->nama_gedung ?? '-' }}
                            <span class="text-xs text-gray-400">(Lt. {{ $ruangan->lantai }})</span>
                        </td>
                        <td class="px-5 py-3.5 text-gray-600">
                            <x-badge tone="slate">{{ $ruangan->jenis_ruangan->label() }}</x-badge>
                        </td>
                        <td class="px-5 py-3.5 text-center text-gray-600">
                            {{ $ruangan->kapasitas_siswa ? $ruangan->kapasitas_siswa . ' Siswa' : '-' }}
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <x-badge tone="blue">{{ $ruangan->aset_count }} Item</x-badge>
                        </td>
                    </tr>
                @endforeach

                @if ($ruanganList->isEmpty())
                    <tr>
                        <td colspan="7" class="px-5 py-10 text-center text-gray-500">
                            @if (request()->anyFilled(['search', 'gedung_id', 'jenis_ruangan']))
                                Tidak ada ruangan yang cocok dengan filter pencarian.
                            @else
                                Belum ada ruangan yang didaftarkan.
                            @endif
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    <div class="border-t border-gray-200 px-5 py-4">
        {{ $ruanganList->links('pagination.tailadmin') }}
    </div>
</div>
