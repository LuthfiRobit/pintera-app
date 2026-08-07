<div class="rounded-2xl border border-gray-200 bg-white shadow-card">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Daftar Kelas</p>
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
                    <th class="px-5 py-3">Nama Kelas</th>
                    <th class="px-5 py-3">Tahun Ajaran</th>
                    <th class="px-5 py-3">Wali Kelas</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($kelasList as $kelas)
                    <tr class="transition hover:bg-gray-50">
                        <td class="sticky left-0 z-10 bg-white px-5 py-3">
                            <x-table-actions>
                                <x-dropdown-link :href="route('admin.kelas.edit', $kelas)">
                                    <span class="inline-flex items-center gap-2.5">
                                        <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                        Edit Kelas
                                    </span>
                                </x-dropdown-link>
                            </x-table-actions>
                        </td>
                        <td class="px-5 py-3.5 font-semibold text-gray-900">
                            {{ $kelas->nama }}
                            @if ($kelas->tingkat)
                                <span class="ml-1 text-xs font-normal text-gray-400">(Tingkat {{ $kelas->tingkat }})</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-gray-600">
                            {{ $kelas->tahunAjaran->nama }}
                            @if ($kelas->tahunAjaran->status_aktif)
                                <x-badge tone="green">Aktif</x-badge>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-gray-600">
                            @if ($kelas->waliKelas)
                                {{ $kelas->waliKelas->nama }}
                            @else
                                <x-badge tone="slate">Belum ditentukan</x-badge>
                            @endif
                        </td>
                    </tr>
                @endforeach

                @if ($kelasList->isEmpty())
                    <tr>
                        <td colspan="4" class="px-5 py-10 text-center text-gray-500">
                            @if (request()->anyFilled(['search', 'tahun_ajaran_id']))
                                Tidak ada kelas yang cocok dengan filter ini.
                            @else
                                Belum ada kelas yang didaftarkan.
                            @endif
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    <div class="border-t border-gray-200 px-5 py-4">
        {{ $kelasList->links('pagination.tailadmin') }}
    </div>
</div>
