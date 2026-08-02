<div x-show="showModalSemester" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center" x-cloak style="display: none;">
    <div x-show="showModalSemester" class="fixed inset-0 transform transition-all" @click="showModalSemester = false"
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="absolute inset-0 bg-gray-900/60"></div>
    </div>

    <div x-show="showModalSemester" class="bg-white rounded-2xl overflow-hidden shadow-elevated transform transition-all sm:max-w-3xl sm:w-full z-10 p-6 relative text-left max-h-[90vh] flex flex-col"
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
        
        <div class="flex items-center justify-between pb-3.5 border-b border-gray-200 shrink-0">
            <div>
                <h3 class="font-display text-base font-bold text-gray-900 flex items-center gap-2">
                    <x-icon name="view_timeline" class="h-5 w-5 text-brand-500" />
                    <span>Konfigurasi Semester Ganjil &amp; Genap</span>
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">Tahun Ajaran <b class="text-gray-800" x-text="selectedTaName"></b>. Atur kalender dan kode Dapodik untuk kedua semester sekaligus.</p>
            </div>
            <button @click="showModalSemester = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
                <x-icon name="cancel" class="h-5 w-5" />
            </button>
        </div>

        <form action="{{ route('admin.semester.store') }}" method="POST" class="mt-4 space-y-6 overflow-y-auto pr-1">
            @csrf
            <input type="hidden" name="tahun_ajaran_id" :value="selectedTaId">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Sesi Ganjil --}}
                <div class="rounded-xl border border-blue-100 bg-blue-50/30 p-4 space-y-3.5">
                    <div class="flex items-center justify-between border-b border-blue-100 pb-2.5">
                        <span class="font-display text-xs font-bold uppercase tracking-wider text-blue-800 flex items-center gap-1.5">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-600 text-[10px] text-white font-mono">1</span>
                            Semester Ganjil
                        </span>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Kode Dapodik <span class="text-gray-400 font-normal">(Opsional, Maks 5 Karakter)</span></label>
                        <input x-model="formSem.ganjil_kode_dapodik" type="text" name="ganjil_kode_dapodik" maxlength="5" placeholder="Misal: 20261" class="w-full rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Tanggal Mulai <span class="text-error-500">*</span></label>
                            <input x-model="formSem.ganjil_tanggal_mulai" type="date" name="ganjil_tanggal_mulai" required class="w-full rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Tanggal Selesai <span class="text-error-500">*</span></label>
                            <input x-model="formSem.ganjil_tanggal_selesai" type="date" name="ganjil_tanggal_selesai" required class="w-full rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        </div>
                    </div>
                </div>

                {{-- Sesi Genap --}}
                <div class="rounded-xl border border-amber-100 bg-amber-50/30 p-4 space-y-3.5">
                    <div class="flex items-center justify-between border-b border-amber-100 pb-2.5">
                        <span class="font-display text-xs font-bold uppercase tracking-wider text-amber-800 flex items-center gap-1.5">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-amber-600 text-[10px] text-white font-mono">2</span>
                            Semester Genap
                        </span>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Kode Dapodik <span class="text-gray-400 font-normal">(Opsional, Maks 5 Karakter)</span></label>
                        <input x-model="formSem.genap_kode_dapodik" type="text" name="genap_kode_dapodik" maxlength="5" placeholder="Misal: 20262" class="w-full rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Tanggal Mulai <span class="text-error-500">*</span></label>
                            <input x-model="formSem.genap_tanggal_mulai" type="date" name="genap_tanggal_mulai" required class="w-full rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Tanggal Selesai <span class="text-error-500">*</span></label>
                            <input x-model="formSem.genap_tanggal_selesai" type="date" name="genap_tanggal_selesai" required class="w-full rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 mt-6 border-t border-gray-100 shrink-0">
                <x-secondary-button type="button" @click="showModalSemester = false">Batal</x-secondary-button>
                <x-primary-button type="submit">Simpan Konfigurasi</x-primary-button>
            </div>
        </form>
    </div>
</div>
