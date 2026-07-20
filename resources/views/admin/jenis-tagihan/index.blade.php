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
                storeUrl: @js(route('admin.jenis-tagihan.store')),
                updateUrlTemplate: @js(route('admin.jenis-tagihan.update', ['jenisTagihan' => '__ID__'])),
                deleteUrlTemplate: @js(route('admin.jenis-tagihan.destroy', ['jenisTagihan' => '__ID__'])),
                nominalUrlTemplate: @js(route('admin.jenis-tagihan.nominal', ['jenisTagihan' => '__ID__'])),
            })"
            class="space-y-5"
        >
            @can('jenis-tagihan.create')
                <div x-ref="formCard" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                    <p class="font-display text-sm font-bold text-gray-900" x-text="editingId === null ? 'Tambah Jenis Tagihan' : 'Edit Jenis Tagihan'"></p>
                    <form @submit.prevent="submit()" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <x-input-label value="Nama" />
                            <x-text-input type="text" x-model="form.nama" placeholder="mis. Biaya Pendaftaran" class="mt-1.5" />
                            <p class="mt-1.5 text-sm text-error-600" x-show="errors.nama" x-text="errors.nama?.[0]"></p>
                        </div>
                        <div>
                            <x-input-label value="Kategori" />
                            <select x-model="form.kategori" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="pendaftaran">Pendaftaran</option>
                                <option value="daftar_ulang">Daftar Ulang</option>
                                <option value="lainnya">Lainnya</option>
                            </select>
                            <p class="mt-1.5 text-sm text-error-600" x-show="errors.kategori" x-text="errors.kategori?.[0]"></p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" x-model="form.bisa_dicicil" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                                Bisa dicicil
                            </label>
                            <div x-show="form.bisa_dicicil" x-cloak class="mt-2 max-w-[160px]">
                                <x-input-label value="Maksimal Jumlah Cicilan" />
                                <x-text-input type="number" min="2" x-model="form.maks_cicilan" class="mt-1.5" />
                            </div>
                        </div>
                        <div class="flex items-center gap-3 sm:col-span-2">
                            <x-primary-button type="submit" x-bind:disabled="submitting" x-text="editingId === null ? 'Tambah' : 'Simpan'"></x-primary-button>
                            <x-secondary-button type="button" x-show="editingId !== null" @click="cancelEdit()">Batal</x-secondary-button>
                        </div>
                    </form>
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
                                                <x-dropdown-link href="#" @click.prevent="startEdit(item)">
                                                    <span class="inline-flex items-center gap-2.5">
                                                        <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                                        Edit
                                                    </span>
                                                </x-dropdown-link>
                                                <x-dropdown-link x-bind:href="nominalUrl(item)">Kelola Nominal</x-dropdown-link>
                                            @endcan
                                            @can('jenis-tagihan.delete')
                                                <x-dropdown-link href="#" @click.prevent="deleteItem(item)" class="text-error-600">Hapus</x-dropdown-link>
                                            @endcan
                                        </x-table-actions>
                                    </td>
                                    <td class="px-5 py-3.5 font-semibold text-gray-900" x-text="item.nama"></td>
                                    <td class="px-5 py-3.5 text-gray-600" x-text="{ pendaftaran: 'Pendaftaran', daftar_ulang: 'Daftar Ulang', lainnya: 'Lainnya' }[item.kategori]"></td>
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
