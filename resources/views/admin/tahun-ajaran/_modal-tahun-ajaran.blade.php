<div x-show="showModalTahunAjaran" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center" x-cloak style="display: none;">
    <div x-show="showModalTahunAjaran" class="fixed inset-0 transform transition-all" @click="showModalTahunAjaran = false"
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="absolute inset-0 bg-gray-900/60"></div>
    </div>

    <div x-show="showModalTahunAjaran" class="bg-white rounded-2xl overflow-hidden shadow-elevated transform transition-all sm:max-w-md sm:w-full z-10 p-6 relative text-left"
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
        
        <div class="flex items-center justify-between pb-3.5 border-b border-gray-200">
            <h3 class="font-display text-base font-bold text-gray-900 flex items-center gap-2">
                <x-icon name="date_range" class="h-5 w-5 text-brand-500" />
                <span x-text="modalTahunAjaranMode === 'create' ? 'Tambah Tahun Ajaran' : 'Edit Tahun Ajaran'"></span>
            </h3>
            <button @click="showModalTahunAjaran = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
                <x-icon name="cancel" class="h-5 w-5" />
            </button>
        </div>

        <form :action="modalTahunAjaranAction" method="POST" class="mt-4 space-y-4">
            @csrf
            <template x-if="modalTahunAjaranMode === 'edit'">
                <input type="hidden" name="_method" value="PUT">
            </template>
            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Nama Tahun Ajaran <span class="text-error-500">*</span></label>
                <input x-model="formTa.nama" type="text" name="nama" required placeholder="Contoh: 2026/2027" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Tanggal Mulai <span class="text-error-500">*</span></label>
                    <input x-model="formTa.tanggal_mulai" type="date" name="tanggal_mulai" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Tanggal Selesai <span class="text-error-500">*</span></label>
                    <input x-model="formTa.tanggal_selesai" type="date" name="tanggal_selesai" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>
            <div class="flex items-center justify-end gap-2 pt-4 mt-6 border-t border-gray-100">
                <x-secondary-button type="button" @click="showModalTahunAjaran = false">Batal</x-secondary-button>
                <x-primary-button type="submit">Simpan</x-primary-button>
            </div>
        </form>
    </div>
</div>
