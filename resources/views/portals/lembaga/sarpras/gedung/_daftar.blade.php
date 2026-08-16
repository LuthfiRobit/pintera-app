<div class="rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Daftar Gedung</p>
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
                    <th class="px-5 py-3">Kode Gedung</th>
                    <th class="px-5 py-3">Nama Gedung</th>
                    <th class="px-5 py-3 text-center">Jumlah Lantai</th>
                    <th class="px-5 py-3 text-center">Total Ruangan</th>
                    <th class="px-5 py-3 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($gedungList as $gedung)
                    <tr class="transition hover:bg-gray-50">
                        <td class="sticky left-0 z-10 bg-white px-5 py-3">
                            <x-table-actions>
                                <x-dropdown-link :href="route('admin.sarpras.gedung.edit', $gedung)">
                                    <span class="inline-flex items-center gap-2.5">
                                        <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                        Edit Gedung
                                    </span>
                                </x-dropdown-link>
                                <form action="{{ route('admin.sarpras.gedung.destroy', $gedung) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus gedung ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-xs text-rose-600 hover:bg-rose-50">
                                        <x-icon name="delete" class="h-4 w-4 text-rose-500" />
                                        Hapus Gedung
                                    </button>
                                </form>
                            </x-table-actions>
                        </td>
                        <td class="px-5 py-3.5 font-mono font-bold text-gray-900">
                            {{ $gedung->kode_gedung }}
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="font-semibold text-gray-900">{{ $gedung->nama_gedung }}</span>
                            @if ($gedung->deskripsi)
                                <p class="text-xs text-gray-500 mt-0.5">{{ $gedung->deskripsi }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-center text-gray-600">
                            {{ $gedung->jumlah_lantai }} Lantai
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <x-badge tone="blue">{{ $gedung->ruangan_count }} Ruangan</x-badge>
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            @if ($gedung->is_aktif)
                                <x-badge tone="green">Aktif</x-badge>
                            @else
                                <x-badge tone="slate">Nonaktif</x-badge>
                            @endif
                        </td>
                    </tr>
                @endforeach

                @if ($gedungList->isEmpty())
                    <tr>
                        <td colspan="6" class="px-5 py-10 text-center text-gray-500">
                            @if (request()->filled('search'))
                                Tidak ada gedung yang cocok dengan kata kunci pencarian.
                            @else
                                Belum ada gedung yang didaftarkan.
                            @endif
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    <div class="border-t border-gray-200 px-5 py-4">
        {{ $gedungList->links('pagination.tailadmin') }}
    </div>
</div>
