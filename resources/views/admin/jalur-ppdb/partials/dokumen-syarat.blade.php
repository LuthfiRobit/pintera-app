<div
    class="rounded-2xl border border-gray-200 bg-white shadow-card"
    x-data="dokumenSyaratList({
        initialItems: @js($jalur->dokumenSyarat),
        jalurId: {{ $jalur->id }},
        storeUrl: @js(route('admin.dokumen-syarat.store')),
        deleteUrlTemplate: @js(route('admin.dokumen-syarat.destroy', ['dokumenSyarat' => '__ID__'])),
    })"
>
    <div class="border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Dokumen Syarat</p>
        <p class="mt-0.5 text-sm text-gray-500">Daftar dokumen yang harus diunggah calon murid pada jalur ini.</p>
    </div>

    <ul class="divide-y divide-gray-100 px-5">
        <template x-if="items.length === 0">
            <li class="py-6 text-center text-sm text-gray-500">Belum ada dokumen syarat.</li>
        </template>
        <template x-for="item in items" :key="item.id">
            <li class="flex items-center justify-between py-3">
                <span class="flex items-center gap-2 text-sm text-gray-900">
                    <span x-text="item.nama_dokumen"></span>
                    <span
                        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="item.wajib ? 'bg-brand-50 text-brand-600' : 'bg-gray-100 text-gray-600'"
                        x-text="item.wajib ? 'Wajib' : 'Opsional'"
                    ></span>
                </span>
                <button type="button" @click="deleteItem(item)" class="text-sm font-semibold text-error-600 hover:text-error-700">Hapus</button>
            </li>
        </template>
    </ul>

    <form @submit.prevent="addItem()" class="flex flex-wrap items-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-4">
        <div class="flex-1">
            <x-input-label value="Nama Dokumen" />
            <x-text-input type="text" x-model="form.nama_dokumen" placeholder="mis. Akta Kelahiran" class="mt-1.5" />
            <template x-if="errors.nama_dokumen">
                <p class="mt-1.5 text-sm text-error-600" x-text="errors.nama_dokumen[0]"></p>
            </template>
        </div>
        <label class="flex items-center gap-2 pb-2.5 text-sm text-gray-700">
            <input type="checkbox" x-model="form.wajib" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500">
            Wajib
        </label>
        <x-secondary-button type="submit" x-bind:disabled="submitting">Tambah Dokumen</x-secondary-button>
    </form>
</div>
