<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Jenis Tes</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Jenis Tes</b>
            </p>
        </div>

        <div
            x-data="jenisTesTable({
                initialItems: @js($jenisTesList),
                storeUrl: @js(route('admin.jenis-tes.store')),
                updateUrlTemplate: @js(route('admin.jenis-tes.update', ['jenisTes' => '__ID__'])),
                deleteUrlTemplate: @js(route('admin.jenis-tes.destroy', ['jenisTes' => '__ID__'])),
            })"
            class="space-y-5"
        >
            @can('jenis-tes.create')
                <div x-ref="formCard" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                    <p class="font-display text-sm font-bold text-gray-900" x-text="editingId === null ? 'Tambah Jenis Tes' : 'Edit Jenis Tes'"></p>
                    <form @submit.prevent="submit()" class="mt-3 flex flex-wrap items-end gap-3">
                        <div class="min-w-[200px] flex-1">
                            <x-input-label value="Nama Jenis Tes" />
                            <x-text-input type="text" x-model="form.nama" placeholder="mis. Tes Tulis, Wawancara" class="mt-1.5" />
                            <p class="mt-1.5 text-sm text-error-600" x-show="errors.nama" x-text="errors.nama?.[0]"></p>
                        </div>
                        <div class="min-w-[200px] flex-1">
                            <x-input-label value="Deskripsi (Opsional)" />
                            <x-text-input type="text" x-model="form.deskripsi" class="mt-1.5" />
                            <p class="mt-1.5 text-sm text-error-600" x-show="errors.deskripsi" x-text="errors.deskripsi?.[0]"></p>
                        </div>
                        <x-primary-button type="submit" x-bind:disabled="submitting" x-text="editingId === null ? 'Tambah' : 'Simpan'"></x-primary-button>
                        <x-secondary-button type="button" x-show="editingId !== null" @click="cancelEdit()">Batal</x-secondary-button>
                    </form>
                </div>
            @endcan

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
                <div class="border-b border-gray-200 px-5 py-4">
                    <p class="font-display text-sm font-bold text-gray-900">Daftar Jenis Tes</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                <th class="sticky left-0 z-10 bg-white px-5 py-3">Aksi</th>
                                <th class="px-5 py-3">Nama</th>
                                <th class="px-5 py-3">Deskripsi</th>
                                <th class="px-5 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-if="items.length === 0">
                                <tr><td colspan="4" class="px-5 py-6 text-center text-sm text-gray-500">Belum ada jenis tes.</td></tr>
                            </template>
                            <template x-for="item in items" :key="item.id">
                                <tr class="transition hover:bg-gray-50">
                                    <td class="sticky left-0 z-10 bg-white px-5 py-3">
                                        <x-table-actions>
                                            @can('jenis-tes.edit')
                                                <x-dropdown-link href="#" @click.prevent="startEdit(item)">
                                                    <span class="inline-flex items-center gap-2.5">
                                                        <x-icon name="edit" class="h-4 w-4 text-gray-500" />
                                                        Edit
                                                    </span>
                                                </x-dropdown-link>
                                            @endcan
                                            @can('jenis-tes.delete')
                                                <x-dropdown-link href="#" @click.prevent="deleteItem(item)" class="text-error-600">Hapus</x-dropdown-link>
                                            @endcan
                                        </x-table-actions>
                                    </td>
                                    <td class="px-5 py-3.5 font-semibold text-gray-900" x-text="item.nama"></td>
                                    <td class="px-5 py-3.5 text-gray-600" x-text="item.deskripsi || '—'"></td>
                                    <td class="px-5 py-3.5">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                            :class="item.seleksi_count > 0 ? 'bg-brand-50 text-brand-600' : 'bg-gray-100 text-gray-600'"
                                            x-text="item.seleksi_count > 0 ? 'Dipakai di ' + item.seleksi_count + ' Seleksi' : 'Tidak Dipakai'"
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
