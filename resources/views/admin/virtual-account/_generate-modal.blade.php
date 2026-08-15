<div
    x-data="virtualAccountGenerateModal({ calonUrlBase: @js(route('admin.virtual-account.calon')) })"
    x-on:open-generate-va-modal.window="buka()"
    x-show="open"
    style="display: none;"
    class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4"
>
    <div class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-elevated" @click.outside="open = false">
        <h3 class="font-display text-sm font-bold text-gray-900">Generate Virtual Account</h3>

        <form method="POST" action="{{ route('admin.virtual-account.generate') }}" class="mt-4 space-y-4">
            @csrf
            <input type="hidden" name="mode" :value="mode">
            <template x-for="id in selectedIds" :key="id">
                <input type="hidden" name="siswa_ids[]" :value="id">
            </template>

            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Bank <span class="text-error-500">*</span></label>
                <select disabled class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-500">
                    <option selected>BRI</option>
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Pilihan Generate <span class="text-error-500">*</span></label>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="cursor-pointer rounded-xl border p-4" :class="mode === 'semua' ? 'border-brand-500 bg-brand-50' : 'border-gray-200'" @click="mode = 'semua'">
                        <div class="flex items-start justify-between">
                            <span class="text-sm font-semibold" :class="mode === 'semua' ? 'text-brand-700' : 'text-gray-800'">Semua Siswa Tanpa VA</span>
                            <span class="mt-1 h-4 w-4 shrink-0 rounded-full border-2" :class="mode === 'semua' ? 'border-brand-600 bg-brand-600' : 'border-gray-300'"></span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Generate Virtual Account untuk seluruh siswa aktif yang belum memiliki nomor VA.</p>
                    </label>
                    <label class="cursor-pointer rounded-xl border p-4" :class="mode === 'manual' ? 'border-brand-500 bg-brand-50' : 'border-gray-200'" @click="mode = 'manual'; muatCalon()">
                        <div class="flex items-start justify-between">
                            <span class="text-sm font-semibold" :class="mode === 'manual' ? 'text-brand-700' : 'text-gray-800'">Pilih Manual</span>
                            <span class="mt-1 h-4 w-4 shrink-0 rounded-full border-2" :class="mode === 'manual' ? 'border-brand-600 bg-brand-600' : 'border-gray-300'"></span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Pilih satu atau beberapa siswa tertentu dari daftar untuk dibuatkan VA.</p>
                    </label>
                </div>
            </div>

            <div x-show="mode === 'manual'" class="space-y-3 rounded-xl border border-gray-100 bg-gray-50 p-4">
                <div class="flex flex-wrap items-center gap-3">
                    <input type="text" x-model="search" @input.debounce.400ms="muatCalon()" placeholder="Cari nama/NIS..." class="flex-1 rounded-lg border-gray-200 text-xs">
                    <select x-model="kelasId" @change="muatCalon()" class="rounded-lg border-gray-200 text-xs">
                        <option value="">Semua Kelas</option>
                        @foreach ($kelasList as $kelas)
                            <option value="{{ $kelas->id }}">{{ $kelas->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="max-h-64 overflow-y-auto rounded-lg bg-white border border-gray-100">
                    <table class="w-full text-left text-xs">
                        <tbody class="divide-y divide-gray-50">
                            <template x-for="siswa in calonList" :key="siswa.id">
                                <tr class="hover:bg-gray-50">
                                    <td class="w-8 px-3 py-2">
                                        <input type="checkbox" :checked="selectedIds.includes(siswa.id)" @change="toggleSiswa(siswa.id)">
                                    </td>
                                    <td class="px-3 py-2 font-medium text-gray-800" x-text="siswa.nama_lengkap"></td>
                                    <td class="px-3 py-2 text-gray-500" x-text="siswa.nis"></td>
                                    <td class="px-3 py-2 text-gray-500" x-text="siswa.kelas"></td>
                                </tr>
                            </template>
                            <tr x-show="calonList.length === 0">
                                <td colspan="4" class="px-3 py-6 text-center text-gray-400">Tidak ada siswa aktif tanpa VA.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-500"><span x-text="selectedIds.length"></span> siswa terpilih.</p>
            </div>

            <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                <button type="button" @click="open = false" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                <button type="submit" :disabled="mode === 'manual' && selectedIds.length === 0" class="rounded-lg bg-brand-600 px-4 py-2 text-xs font-semibold text-white hover:bg-brand-700 disabled:opacity-50">Simpan</button>
            </div>
        </form>
    </div>
</div>
