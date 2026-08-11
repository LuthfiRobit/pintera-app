<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500">
                <th class="sticky left-0 z-10 bg-white px-5 py-3.5">Aksi</th>
                <th class="px-5 py-3.5">Nama</th>
                <th class="px-5 py-3.5">Kategori</th>
                <th class="px-5 py-3.5">Cicilan</th>
                <th class="px-5 py-3.5">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <template x-if="filteredItems.length === 0">
                <tr><td colspan="5" class="px-5 py-12 text-center text-sm font-medium text-gray-500">Belum ada jenis tagihan yang ditambahkan.</td></tr>
            </template>
            <template x-for="item in filteredItems" :key="item.id">
                <tr class="transition hover:bg-gray-50/80">
                    <td class="sticky left-0 z-10 bg-white px-5 py-4 shadow-[1px_0_0_0_#f3f4f6]">
                        <x-table-actions>
                            @can('jenis-tagihan.edit')
                                <x-dropdown-link x-bind:href="editUrl(item)">Edit</x-dropdown-link>
                                <template x-if="['pendaftaran', 'daftar_ulang'].includes(item.kategori)">
                                    <x-dropdown-link x-bind:href="nominalUrl(item)">Kelola Nominal</x-dropdown-link>
                                </template>
                                <template x-if="!['pendaftaran', 'daftar_ulang'].includes(item.kategori)">
                                    <x-dropdown-link href="#" @click.prevent="prosesTagihan(item)">Proses Tagihan</x-dropdown-link>
                                </template>
                                <template x-if="!['pendaftaran', 'daftar_ulang'].includes(item.kategori)">
                                    <x-dropdown-link x-bind:href="monitoringUrl(item)">Monitoring</x-dropdown-link>
                                </template>
                            @endcan
                            @can('jenis-tagihan.delete')
                                <x-dropdown-link href="#" @click.prevent="deleteItem(item)" class="text-error-600 font-medium">Hapus</x-dropdown-link>
                            @endcan
                        </x-table-actions>
                    </td>
                    <td class="px-5 py-4 font-bold text-gray-900" x-text="item.nama"></td>
                    <td class="px-5 py-4 text-gray-600 font-medium" x-text="{ pendaftaran: 'Pendaftaran', daftar_ulang: 'Daftar Ulang', lainnya: 'Lainnya', spp: 'SPP', tahunan: 'Tahunan', kegiatan: 'Kegiatan', custom: 'Custom' }[item.kategori]"></td>
                    <td class="px-5 py-4 text-gray-600" x-text="item.bisa_dicicil ? 'Maks ' + item.maks_cicilan + 'x' : 'Tidak dicicil'"></td>
                    <td class="px-5 py-4">
                        <span
                            class="inline-flex items-center rounded-lg px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide shadow-sm"
                            :class="item.tagihan_item_count > 0 ? 'bg-brand-50 text-brand-600 border border-brand-200' : (item.nominal_jalur_count > 0 ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-gray-50 text-gray-600 border border-gray-200')"
                            x-text="item.tagihan_item_count > 0 ? 'Dipakai di ' + item.tagihan_item_count + ' Tagihan' : (item.nominal_jalur_count > 0 ? item.nominal_jalur_count + ' Dikonfigurasi' : 'Belum Dipakai')"
                        ></span>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>
</div>
