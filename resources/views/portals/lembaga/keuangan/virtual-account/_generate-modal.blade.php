<div
    x-data="virtualAccountGenerateModal({ calonUrlBase: @js(route('admin.virtual-account.calon')) })"
    x-on:open-generate-va-modal.window="buka()"
    x-show="open"
    class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center"
    x-cloak
    style="display: none;"
>
    {{-- Backdrop --}}
    <div
        x-show="open"
        class="fixed inset-0 transform transition-all"
        @click="open = false"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div class="absolute inset-0 bg-gray-900/60"></div>
    </div>

    {{-- Modal Box --}}
    <div
        x-show="open"
        class="bg-white rounded-2xl overflow-hidden shadow-elevated transform transition-all sm:max-w-2xl sm:w-full z-10 p-6 relative text-left max-h-[90vh] flex flex-col"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between pb-3.5 border-b border-gray-200 shrink-0">
            <div>
                <h3 class="font-display text-base font-bold text-gray-900 flex items-center gap-2">
                    <x-icon name="payments" class="h-5 w-5 text-brand-600" />
                    <span>Generate Virtual Account</span>
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">Buat nomor Virtual Account BRI siswa secara massal atau pilih manual.</p>
            </div>
            <button @click="open = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
                <x-icon name="cancel" class="h-5 w-5" />
            </button>
        </div>

        {{-- Form --}}
        <form method="POST" action="{{ route('admin.virtual-account.generate') }}" class="mt-4 flex-1 overflow-y-auto pr-1 space-y-4">
            @csrf
            <input type="hidden" name="mode" :value="mode">
            <template x-for="id in selectedIds" :key="id">
                <input type="hidden" name="siswa_ids[]" :value="id">
            </template>

            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Bank Virtual Account <span class="text-error-500">*</span></label>
                <select disabled class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm font-semibold text-gray-600">
                    <option selected>Bank BRI (Direct SNAP VA)</option>
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Pilihan Generate <span class="text-error-500">*</span></label>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="cursor-pointer rounded-xl border p-4 transition-all" :class="mode === 'semua' ? 'border-brand-500 bg-brand-50/70 shadow-sm' : 'border-gray-200 hover:border-gray-300'" @click="mode = 'semua'">
                        <div class="flex items-start justify-between">
                            <span class="text-sm font-semibold" :class="mode === 'semua' ? 'text-brand-700' : 'text-gray-800'">Semua Siswa Tanpa VA</span>
                            <span class="mt-1 h-4 w-4 shrink-0 rounded-full border-2 transition-all" :class="mode === 'semua' ? 'border-brand-600 bg-brand-600 ring-2 ring-brand-500/20' : 'border-gray-300'"></span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Generate Virtual Account untuk seluruh siswa aktif yang belum memiliki nomor VA.</p>
                    </label>
                    <label class="cursor-pointer rounded-xl border p-4 transition-all" :class="mode === 'manual' ? 'border-brand-500 bg-brand-50/70 shadow-sm' : 'border-gray-200 hover:border-gray-300'" @click="mode = 'manual'; muatCalon()">
                        <div class="flex items-start justify-between">
                            <span class="text-sm font-semibold" :class="mode === 'manual' ? 'text-brand-700' : 'text-gray-800'">Pilih Manual</span>
                            <span class="mt-1 h-4 w-4 shrink-0 rounded-full border-2 transition-all" :class="mode === 'manual' ? 'border-brand-600 bg-brand-600 ring-2 ring-brand-500/20' : 'border-gray-300'"></span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Pilih satu atau beberapa siswa tertentu dari daftar untuk dibuatkan VA.</p>
                    </label>
                </div>
            </div>

            {{-- Manual Selection Container --}}
            <div x-show="mode === 'manual'" class="space-y-3 rounded-xl border border-gray-100 bg-gray-50 p-4">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex flex-1 h-[38px] items-center gap-2 rounded-lg border border-gray-200 bg-white px-3">
                        <x-icon name="search" class="h-4 w-4 shrink-0 text-gray-400" />
                        <input type="text" x-model="search" @input.debounce.400ms="muatCalon()" placeholder="Cari nama atau NIS siswa..." class="w-full border-0 bg-transparent p-0 text-xs text-gray-900 placeholder:text-gray-400 focus:ring-0">
                    </div>
                    <select x-model="kelasId" @change="muatCalon()" class="rounded-lg border-gray-200 bg-white text-xs text-gray-700 h-[38px]">
                        <option value="">Semua Kelas</option>
                        @foreach ($kelasListGrouped ?? [] as $tahunAjaranNama => $grupKelas)
                            <optgroup label="{{ $tahunAjaranNama }}">
                                @foreach ($grupKelas as $kelas)
                                    <option value="{{ $kelas->id }}">{{ $kelas->nama }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>

                <div class="max-h-64 overflow-y-auto rounded-xl bg-white border border-gray-200 shadow-sm">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/75 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                <th class="w-10 px-3.5 py-2.5 text-center">
                                    <input type="checkbox" :checked="isAllSelected()" @change="toggleSelectAll()" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                </th>
                                <th class="px-3 py-2.5">Nama Siswa</th>
                                <th class="px-3 py-2.5">NIS</th>
                                <th class="px-3 py-2.5">Kelas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <template x-for="siswa in calonList" :key="siswa.id">
                                <tr
                                    @click="toggleSiswa(siswa.id)"
                                    class="cursor-pointer transition-colors hover:bg-gray-50/80"
                                    :class="selectedIds.includes(siswa.id) ? 'bg-brand-50/30' : ''"
                                >
                                    <td class="w-10 px-3.5 py-2.5 text-center" @click.stop>
                                        <input
                                            type="checkbox"
                                            :checked="selectedIds.includes(siswa.id)"
                                            @change="toggleSiswa(siswa.id)"
                                            class="rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                                        >
                                    </td>
                                    <td class="px-3 py-2.5 font-semibold text-gray-800" x-text="siswa.nama_lengkap"></td>
                                    <td class="px-3 py-2.5 font-mono text-gray-500" x-text="siswa.nis || '-'"></td>
                                    <td class="px-3 py-2.5 text-gray-600" x-text="siswa.kelas"></td>
                                </tr>
                            </template>
                            <tr x-show="calonList.length === 0">
                                <td colspan="4" class="px-3 py-8 text-center text-gray-400 font-medium">
                                    Tidak ada siswa aktif tanpa VA yang ditemukan.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between text-xs text-gray-500 px-1">
                    <p><span class="font-bold text-gray-800" x-text="selectedIds.length"></span> siswa dipilih.</p>
                    <button
                        type="button"
                        x-show="selectedIds.length > 0"
                        @click="selectedIds = []"
                        class="text-xs text-error-600 hover:underline"
                    >
                        Reset pilihan
                    </button>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-end gap-2 pt-4 mt-6 border-t border-gray-100 shrink-0">
                <button type="button" @click="open = false" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600 transition hover:bg-gray-50">
                    Batal
                </button>
                <button
                    type="submit"
                    :disabled="mode === 'manual' && selectedIds.length === 0"
                    class="rounded-lg bg-brand-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-50"
                >
                    Simpan &amp; Generate
                </button>
            </div>
        </form>
    </div>
</div>
