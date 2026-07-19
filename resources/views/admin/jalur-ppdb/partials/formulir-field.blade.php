<div
    class="rounded-2xl border border-gray-200 bg-white shadow-card"
    x-data="formulirFieldList({
        initialItems: @js($jalur->formulirField),
        jalurId: {{ $jalur->id }},
        storeUrl: @js(route('admin.formulir-field.store')),
        deleteUrlTemplate: @js(route('admin.formulir-field.destroy', ['formulirField' => '__ID__'])),
    })"
>
    <div class="border-b border-gray-200 px-5 py-4">
        <p class="font-display text-sm font-bold text-gray-900">Formulir Field</p>
        <p class="mt-0.5 text-sm text-gray-500">Field tambahan di luar data wajib Dapodik, khusus untuk jalur ini.</p>
    </div>

    <ul class="divide-y divide-gray-100 px-5">
        <template x-if="items.length === 0">
            <li class="py-6 text-center text-sm text-gray-500">Belum ada field tambahan.</li>
        </template>
        <template x-for="item in items" :key="item.id">
            <li class="flex items-center justify-between py-3">
                <div>
                    <span class="text-sm font-semibold text-gray-900" x-text="item.label"></span>
                    <span class="ml-2 text-xs uppercase text-gray-500" x-text="item.field_type"></span>
                    <span
                        x-show="item.is_required"
                        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold bg-brand-50 text-brand-600"
                    >Wajib</span>
                    <template x-if="item.field_type === 'select' && item.options && item.options.length">
                        <p class="mt-0.5 text-xs text-gray-500">Opsi: <span x-text="(item.options ?? []).join(', ')"></span></p>
                    </template>
                </div>
                <button type="button" @click="deleteItem(item)" class="text-sm font-semibold text-error-600 hover:text-error-700">Hapus</button>
            </li>
        </template>
    </ul>

    <form @submit.prevent="addItem()" class="space-y-3 border-t border-gray-200 bg-gray-50 px-5 py-4">
        <div class="flex flex-wrap items-end gap-2">
            <div class="flex-1">
                <x-input-label value="Label Field" />
                <x-text-input type="text" x-model="form.label" placeholder="Contoh: Nomor WhatsApp Orang Tua" class="mt-1.5" />
                <template x-if="errors.label">
                    <p class="mt-1.5 text-sm text-error-600" x-text="errors.label[0]"></p>
                </template>
            </div>
            <div>
                <x-input-label value="Tipe" />
                <select x-model="form.field_type" class="mt-1.5 rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="text">Teks</option>
                    <option value="textarea">Teks Panjang</option>
                    <option value="number">Angka</option>
                    <option value="date">Tanggal</option>
                    <option value="select">Pilihan</option>
                    <option value="file">Berkas</option>
                </select>
            </div>
            <label class="flex items-center gap-2 pb-2.5 text-sm text-gray-700">
                <input type="checkbox" x-model="form.is_required" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                Wajib
            </label>
        </div>
        <div>
            <x-input-label value="Opsi (khusus tipe Pilihan, satu per baris)" />
            <textarea x-model="form.options" rows="2" placeholder="Opsi 1&#10;Opsi 2" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
            <template x-if="errors.options">
                <p class="mt-1.5 text-sm text-error-600" x-text="errors.options[0]"></p>
            </template>
        </div>
        <x-secondary-button type="submit" x-bind:disabled="submitting">Tambah Field</x-secondary-button>
    </form>
</div>
