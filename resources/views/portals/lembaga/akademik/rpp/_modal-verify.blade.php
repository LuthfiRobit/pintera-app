{{-- Modal Review & Verifikasi Kurikulum RPP --}}
<div x-show="showModalVerify" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center" x-cloak style="display: none;">
    <div x-show="showModalVerify" class="fixed inset-0 transform transition-all" @click="showModalVerify = false"
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="absolute inset-0 bg-gray-900/60"></div>
    </div>

    <div x-show="showModalVerify" class="bg-white rounded-2xl overflow-hidden shadow-elevated transform transition-all sm:max-w-lg sm:w-full z-10 p-6 relative text-left max-h-[90vh] flex flex-col"
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
        
        <div class="flex items-center justify-between pb-3.5 border-b border-gray-200 shrink-0">
            <div>
                <h3 class="font-display text-base font-bold text-gray-900 flex items-center gap-2">
                    <x-icon name="verified" class="h-5 w-5 text-brand-500" />
                    <span>Verifikasi Perangkat Ajar</span>
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">Tinjau kesesuaian materi RPP dan tetapkan keputusan verifikasi.</p>
            </div>
            <button @click="showModalVerify = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
                <x-icon name="cancel" class="h-5 w-5" />
            </button>
        </div>

        <form :action="verifyModal.actionUrl" method="POST" class="mt-4 flex-1 overflow-y-auto pr-1 space-y-4">
            @csrf

            {{-- Ringkasan Dokumen --}}
            <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-200/80 space-y-2 text-xs">
                <div class="flex justify-between items-start">
                    <span class="text-gray-500">Guru Pengampu:</span>
                    <strong class="text-gray-900 font-semibold" x-text="verifyModal.guruNama"></strong>
                </div>
                <div class="flex justify-between items-start">
                    <span class="text-gray-500">Kelas / Mapel:</span>
                    <span class="text-gray-800" x-text="`${verifyModal.kelasNama} • ${verifyModal.mapelNama}`"></span>
                </div>
                <div class="flex justify-between items-start">
                    <span class="text-gray-500">Judul Topik:</span>
                    <span class="text-gray-900 font-medium" x-text="verifyModal.judulTopik"></span>
                </div>
                <div class="pt-2 border-t border-gray-200 flex flex-wrap justify-between items-center gap-2">
                    <span class="text-gray-500">Berkas Dokumen:</span>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="bukaBerkas(verifyModal.fileUrl, verifyModal.fileName)"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-700 hover:bg-brand-100 border border-brand-200 transition"
                        >
                            <x-icon name="visibility" class="h-3.5 w-3.5 text-brand-600" />
                            <span>Buka di Platform</span>
                        </button>
                        <a
                            :href="verifyModal.downloadUrl"
                            class="inline-flex items-center gap-1 text-[11px] text-gray-500 hover:text-gray-800 hover:underline"
                            title="Unduh berkas asli"
                        >
                            <x-icon name="download" class="h-3.5 w-3.5" />
                            <span>Unduh</span>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Keputusan Verifikasi --}}
            <div>
                <x-input-label value="Keputusan Verifikasi Kurikulum" />
                <div class="grid grid-cols-2 gap-3 mt-1.5">
                    <label class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer transition-all"
                           :class="verifyModal.status === 'disetujui' ? 'border-emerald-500 bg-emerald-50/50 text-emerald-900 font-bold' : 'border-gray-200 hover:bg-gray-50 text-gray-700'">
                        <input type="radio" name="status" value="disetujui" x-model="verifyModal.status" class="text-emerald-600 focus:ring-emerald-500">
                        <div class="flex items-center gap-1.5 text-xs">
                            <x-icon name="check_circle" class="h-4 w-4 text-emerald-600" />
                            <span>Setujui RPP</span>
                        </div>
                    </label>

                    <label class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer transition-all"
                           :class="verifyModal.status === 'perlu_revisi' ? 'border-rose-500 bg-rose-50/50 text-rose-900 font-bold' : 'border-gray-200 hover:bg-gray-50 text-gray-700'">
                        <input type="radio" name="status" value="perlu_revisi" x-model="verifyModal.status" class="text-rose-600 focus:ring-rose-500">
                        <div class="flex items-center gap-1.5 text-xs">
                            <x-icon name="history_edu" class="h-4 w-4 text-rose-600" />
                            <span>Minta Revisi</span>
                        </div>
                    </label>
                </div>
            </div>

            {{-- Catatan Revisi (Wajib jika revisi) --}}
            <div x-show="verifyModal.status === 'perlu_revisi'" x-transition>
                <x-input-label value="Catatan Koreksi / Hal yang Perlu Direvisi" />
                <textarea name="catatan_revisi" x-model="verifyModal.catatanRevisi" rows="3" placeholder="Tuliskan catatan perbaikan untuk guru pengampu..." class="mt-1.5 block w-full rounded-lg border-gray-200 text-xs text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 mt-6 border-t border-gray-100 shrink-0">
                <x-secondary-button type="button" @click="showModalVerify = false">Batal</x-secondary-button>
                <x-primary-button type="submit" class="gap-1.5">
                    <x-icon name="check" class="h-4 w-4" />
                    <span>Simpan Keputusan</span>
                </x-primary-button>
            </div>
        </form>
    </div>
</div>
