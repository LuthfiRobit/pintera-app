<div x-show="showModalEditSlot" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center" x-cloak style="display: none;">
    <div x-show="showModalEditSlot" class="fixed inset-0 transform transition-all" @click="showModalEditSlot = false"
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="absolute inset-0 bg-gray-900/60"></div>
    </div>

    <div x-show="showModalEditSlot" class="bg-white rounded-2xl overflow-hidden shadow-elevated transform transition-all sm:max-w-lg sm:w-full z-10 p-6 relative text-left"
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
        
        <div class="flex items-center justify-between pb-3.5 border-b border-gray-200">
            <h3 class="font-display text-base font-bold text-gray-900 flex items-center gap-2">
                <x-icon name="edit" class="h-5 w-5 text-brand-500" />
                <span>Edit Sesi Jam Pelajaran</span>
            </h3>
            <button @click="showModalEditSlot = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
                <x-icon name="cancel" class="h-5 w-5" />
            </button>
        </div>

        <form :action="formSlot.updateUrl" method="POST" class="mt-4 space-y-4">
            @csrf
            @method('PUT')
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Hari <span class="text-error-500">*</span></label>
                    <select x-model="formSlot.hari" name="hari" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        @foreach (\App\Enums\Hari::cases() as $hariOpsi)
                            <option value="{{ $hariOpsi->value }}">{{ $hariOpsi->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Urutan Ke- <span class="text-error-500">*</span></label>
                    <input x-model="formSlot.urutan" type="number" min="1" name="urutan" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jam Mulai <span class="text-error-500">*</span></label>
                    <input x-model="formSlot.jam_mulai" type="time" name="jam_mulai" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 font-mono text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jam Selesai <span class="text-error-500">*</span></label>
                    <input x-model="formSlot.jam_selesai" type="time" name="jam_selesai" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 font-mono text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Label Sesi <span class="text-error-500">*</span></label>
                    <input x-model="formSlot.label" type="text" name="label" required placeholder="mis. Jam ke-1 / Istirahat" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jenis Sesi <span class="text-error-500">*</span></label>
                    <select x-model="formSlot.is_pelajaran" name="is_pelajaran" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <option :value="1">Jam Belajar</option>
                        <option :value="0">Non-pelajaran</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 mt-6 border-t border-gray-100">
                <x-secondary-button type="button" @click="showModalEditSlot = false">Batal</x-secondary-button>
                <x-primary-button type="submit">Simpan Perubahan</x-primary-button>
            </div>
        </form>
    </div>
</div>
