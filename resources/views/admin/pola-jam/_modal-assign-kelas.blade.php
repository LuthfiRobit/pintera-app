<div x-show="showModalAssign" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center" x-cloak style="display: none;">
    <div x-show="showModalAssign" class="fixed inset-0 transform transition-all" @click="showModalAssign = false"
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="absolute inset-0 bg-gray-900/60"></div>
    </div>

    <div x-show="showModalAssign" class="bg-white rounded-2xl overflow-hidden shadow-elevated transform transition-all sm:max-w-2xl sm:w-full z-10 p-6 relative text-left max-h-[85vh] flex flex-col"
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
        
        <div class="flex items-center justify-between pb-3.5 border-b border-gray-200 shrink-0">
            <div>
                <h3 class="font-display text-base font-bold text-gray-900 flex items-center gap-2">
                    <x-icon name="group" class="h-5 w-5 text-brand-500" />
                    <span>Kelola Tautan Kelas</span>
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">Pilih kelas yang akan menggunakan <b class="text-gray-800" x-text="formAssign.polaNama"></b>. Daftar dibawah terbagi berdasar Tahun Ajaran.</p>
            </div>
            <button @click="showModalAssign = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
                <x-icon name="cancel" class="h-5 w-5" />
            </button>
        </div>

        <form :action="formAssign.actionUrl" method="POST" class="mt-4 flex-1 overflow-y-auto pr-1 space-y-6">
            @csrf
            @method('PUT')

            @php
                // Mengelompokkan kelas berdasar Tahun Ajaran dan menyortir Tahun Ajaran Aktif ke teratas
                $groupedKelas = $kelasList->groupBy(fn($k) => $k->tahunAjaran ? ($k->tahunAjaran->nama . ($k->tahunAjaran->status_aktif ? ' (Aktif)' : '')) : 'Tanpa Tahun Ajaran')
                    ->sortByDesc(fn($list, $key) => str_contains($key, '(Aktif)') ? 'ZZZ_'.$key : $key);
            @endphp

            <div class="space-y-5">
                @foreach ($groupedKelas as $groupTitle => $classes)
                    <div x-show="formAssign.lembagaId === null || @js($classes->pluck('lembaga_id')->unique()->values()->all()).includes(formAssign.lembagaId)" 
                         class="rounded-xl border border-gray-200 overflow-hidden">
                        <div class="bg-gray-50/80 px-4 py-2.5 border-b border-gray-200 flex items-center justify-between">
                            <span class="font-display text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-1.5">
                                @if (str_contains($groupTitle, '(Aktif)'))
                                    <span class="inline-block h-2 w-2 rounded-full bg-success-500"></span>
                                    <span class="text-success-800">{{ $groupTitle }}</span>
                                @else
                                    <span class="inline-block h-2 w-2 rounded-full bg-gray-300"></span>
                                    <span>{{ $groupTitle }}</span>
                                @endif
                            </span>
                            <span class="text-[11px] font-medium text-gray-400">
                                <span x-text="($el.closest('.rounded-xl').querySelectorAll('input[type=checkbox]:not([disabled])')).length"></span> opsi kompatibel
                            </span>
                        </div>
                        <div class="p-4 bg-white grid grid-cols-2 sm:grid-cols-3 gap-3">
                            @foreach ($classes as $kelasOpsi)
                                <div x-show="formAssign.lembagaId === null || formAssign.lembagaId === @js($kelasOpsi->lembaga_id)" 
                                     class="flex items-center">
                                    <label class="group flex items-center gap-2 text-sm text-gray-700 cursor-pointer hover:text-gray-950 font-medium select-none w-full p-1.5 rounded-lg hover:bg-gray-50 transition">
                                        <input
                                            type="checkbox"
                                            name="kelas_ids[]"
                                            :value="{{ $kelasOpsi->id }}"
                                            x-model="formAssign.selectedKelasIds"
                                            class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500"
                                        >
                                        <span class="truncate">{{ $kelasOpsi->nama }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 mt-6 border-t border-gray-100 shrink-0">
                <x-secondary-button type="button" @click="showModalAssign = false">Batal</x-secondary-button>
                <x-primary-button type="submit">Simpan Tautan</x-primary-button>
            </div>
        </form>
    </div>
</div>
