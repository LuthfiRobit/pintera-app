<div x-show="showModalSemester" class="fixed inset-0 z-50 overflow-y-auto" x-cloak style="display: none;">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity" @click="showModalSemester = false"></div>
        <div class="relative w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-xl border border-gray-200" @click.stop>
            <div class="border-b border-gray-100 bg-gray-50/50 px-6 py-4 flex items-center justify-between">
                <div>
                    <p class="font-display text-sm font-bold text-gray-900 flex items-center gap-2">
                        <x-icon name="view_timeline" class="h-4 w-4 text-brand-500" />
                        Konfigurasi Semester Ganjil &amp; Genap
                    </p>
                    <p class="text-xs text-gray-500 mt-0.5">Tahun Ajaran <b class="text-gray-800" x-text="selectedTaName"></b>. Atur kalender dan kode Dapodik untuk kedua semester sekaligus.</p>
                </div>
                <button type="button" @click="showModalSemester = false" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <form action="{{ route('admin.semester.store') }}" method="POST" class="p-6 space-y-6">
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
                            <x-input-label value="Kode Dapodik (Opsional, Maks 5 Karakter)" />
                            <x-text-input type="text" name="ganjil_kode_dapodik" x-model="formSem.ganjil_kode_dapodik" maxlength="5" placeholder="Misal: 20261" class="mt-1 block w-full text-sm" />
                        </div>
                        <div class="grid grid-cols-2 gap-2.5">
                            <div>
                                <x-input-label value="Tanggal Mulai *" />
                                <x-text-input type="date" name="ganjil_tanggal_mulai" x-model="formSem.ganjil_tanggal_mulai" required class="mt-1 block w-full text-sm" />
                            </div>
                            <div>
                                <x-input-label value="Tanggal Selesai *" />
                                <x-text-input type="date" name="ganjil_tanggal_selesai" x-model="formSem.ganjil_tanggal_selesai" required class="mt-1 block w-full text-sm" />
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
                            <x-input-label value="Kode Dapodik (Opsional, Maks 5 Karakter)" />
                            <x-text-input type="text" name="genap_kode_dapodik" x-model="formSem.genap_kode_dapodik" maxlength="5" placeholder="Misal: 20262" class="mt-1 block w-full text-sm" />
                        </div>
                        <div class="grid grid-cols-2 gap-2.5">
                            <div>
                                <x-input-label value="Tanggal Mulai *" />
                                <x-text-input type="date" name="genap_tanggal_mulai" x-model="formSem.genap_tanggal_mulai" required class="mt-1 block w-full text-sm" />
                            </div>
                            <div>
                                <x-input-label value="Tanggal Selesai *" />
                                <x-text-input type="date" name="genap_tanggal_selesai" x-model="formSem.genap_tanggal_selesai" required class="mt-1 block w-full text-sm" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                    <button type="button" @click="showModalSemester = false" class="px-4 py-2 rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-100 transition">Batal</button>
                    <x-primary-button type="submit">Simpan Konfigurasi Semester</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</div>
