<div x-show="showModalTahunAjaran" class="fixed inset-0 z-50 overflow-y-auto" x-cloak style="display: none;">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity" @click="showModalTahunAjaran = false"></div>
        <div class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-xl border border-gray-200" @click.stop>
            <div class="border-b border-gray-100 bg-gray-50/50 px-6 py-4 flex items-center justify-between">
                <p class="font-display text-sm font-bold text-gray-900 flex items-center gap-2">
                    <x-icon name="date_range" class="h-4 w-4 text-brand-500" />
                    <span x-text="modalTahunAjaranMode === 'create' ? 'Tambah Tahun Ajaran Baru' : 'Edit Tahun Ajaran'"></span>
                </p>
                <button type="button" @click="showModalTahunAjaran = false" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <form :action="modalTahunAjaranAction" method="POST" class="p-6 space-y-4">
                @csrf
                <template x-if="modalTahunAjaranMode === 'edit'">
                    <input type="hidden" name="_method" value="PUT">
                </template>
                <div>
                    <x-input-label value="Nama Tahun Ajaran *" />
                    <x-text-input type="text" name="nama" x-model="formTa.nama" required placeholder="Contoh: 2026/2027" class="mt-1.5 block w-full text-sm" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label value="Tanggal Mulai *" />
                        <x-text-input type="date" name="tanggal_mulai" x-model="formTa.tanggal_mulai" required class="mt-1.5 block w-full text-sm" />
                    </div>
                    <div>
                        <x-input-label value="Tanggal Selesai *" />
                        <x-text-input type="date" name="tanggal_selesai" x-model="formTa.tanggal_selesai" required class="mt-1.5 block w-full text-sm" />
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                    <button type="button" @click="showModalTahunAjaran = false" class="px-4 py-2 rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-100 transition">Batal</button>
                    <x-primary-button type="submit">Simpan Tahun Ajaran</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</div>
