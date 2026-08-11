<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Jenis Tagihan</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Jenis Tagihan</b>
            </p>
        </div>

        <div
            x-data="jenisTagihanTable({
                initialItems: @js($jenisTagihanList),
                deleteUrlTemplate: @js(route('admin.jenis-tagihan.destroy', ['jenisTagihan' => '__ID__'])),
                nominalUrlTemplate: @js(route('admin.jenis-tagihan.nominal', ['jenisTagihan' => '__ID__'])),
                editUrlTemplate: @js(route('admin.jenis-tagihan.edit', ['jenisTagihan' => '__ID__'])),
            })"
            class="space-y-5"
        >
            @can('jenis-tagihan.create')
                <div class="flex justify-end">
                    <a href="{{ route('admin.jenis-tagihan.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">
                        <x-icon name="add" class="h-4 w-4" /> Tambah Jenis Tagihan
                    </a>
                </div>
            @endcan

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
                <div class="border-b border-gray-200 px-5 py-4">
                    <p class="font-display text-sm font-bold text-gray-900">Daftar Jenis Tagihan</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
                                <th class="px-5 py-3">Nama</th>
                                <th class="px-5 py-3">Kategori</th>
                                <th class="px-5 py-3">Cicilan</th>
                                <th class="px-5 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-if="items.length === 0">
                                <tr><td colspan="5" class="px-5 py-6 text-center text-sm text-gray-500">Belum ada jenis tagihan.</td></tr>
                            </template>
                            <template x-for="item in items" :key="item.id">
                                <tr class="transition hover:bg-gray-50">
                                    <td class="sticky left-0 z-10 bg-white px-5 py-3">
                                        <x-table-actions>
                                            @can('jenis-tagihan.edit')
                                                <x-dropdown-link x-bind:href="editUrl(item)">Edit</x-dropdown-link>
                                                <template x-if="['pendaftaran', 'daftar_ulang'].includes(item.kategori)">
                                                    <x-dropdown-link x-bind:href="nominalUrl(item)">Kelola Nominal</x-dropdown-link>
                                                </template>
                                            @endcan
                                            @can('jenis-tagihan.delete')
                                                <x-dropdown-link href="#" @click.prevent="deleteItem(item)" class="text-error-600">Hapus</x-dropdown-link>
                                            @endcan
                                        </x-table-actions>
                                    </td>
                                    <td class="px-5 py-3.5 font-semibold text-gray-900" x-text="item.nama"></td>
                                    <td class="px-5 py-3.5 text-gray-600" x-text="{ pendaftaran: 'Pendaftaran', daftar_ulang: 'Daftar Ulang', lainnya: 'Lainnya', spp: 'SPP', tahunan: 'Tahunan', kegiatan: 'Kegiatan', custom: 'Custom' }[item.kategori]"></td>
                                    <td class="px-5 py-3.5 text-gray-600" x-text="item.bisa_dicicil ? 'Maks ' + item.maks_cicilan + 'x' : 'Tidak dicicil'"></td>
                                    <td class="px-5 py-3.5">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                            :class="item.tagihan_item_count > 0 ? 'bg-brand-50 text-brand-600' : (item.nominal_jalur_count > 0 ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600')"
                                            x-text="item.tagihan_item_count > 0 ? 'Dipakai di ' + item.tagihan_item_count + ' Tagihan' : (item.nominal_jalur_count > 0 ? item.nominal_jalur_count + ' Nominal Dikonfigurasi' : 'Belum Dipakai')"
                                        ></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
