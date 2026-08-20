<div x-show="showModalDuplicate" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center" x-cloak style="display: none;">
    <div x-show="showModalDuplicate" class="fixed inset-0 transform transition-all" @click="showModalDuplicate = false"
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="absolute inset-0 bg-gray-900/60"></div>
    </div>

    <div x-show="showModalDuplicate" class="bg-white rounded-2xl overflow-hidden shadow-elevated transform transition-all sm:max-w-lg sm:w-full z-10 p-6 relative text-left"
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
        
        <div class="flex items-center justify-between pb-3.5 border-b border-gray-200">
            <div>
                <h3 class="font-display text-base font-bold text-gray-900 flex items-center gap-2">
                    <x-icon name="content_copy" class="h-5 w-5 text-brand-500" />
                    <span>Salin Jadwal dari Kelas Lain</span>
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">Salin susunan mata pelajaran dan guru dari kelas lain ke kelas yang terpilih.</p>
            </div>
            <button @click="showModalDuplicate = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
                <x-icon name="cancel" class="h-5 w-5" />
            </button>
        </div>

        <form action="{{ route('admin.jadwal-pelajaran.duplicate') }}" method="POST" @submit="submitDuplicate($event)" class="mt-4 space-y-5">
            @csrf
            <input type="hidden" name="target_kelas_id" :value="duplicateForm.target_kelas_id">
            <input type="hidden" name="target_semester_id" :value="duplicateForm.target_semester_id">

            <div x-show="duplicateForm.errorMessage" class="rounded-xl bg-error-50 p-3 text-xs text-error-700 font-medium border border-error-200">
                <p x-text="duplicateForm.errorMessage"></p>
            </div>

            <div class="rounded-xl bg-brand-50/70 p-3.5 border border-brand-100 text-xs space-y-1 text-brand-900">
                <div class="flex items-center gap-1.5 font-semibold text-brand-700">
                    <x-icon name="info" class="h-4 w-4 shrink-0 text-brand-500" />
                    <span>Mekanisme Anti-Bentrok Proaktif</span>
                </div>
                <p class="text-brand-800/80 leading-relaxed">
                    Sistem akan secara otomatis melepaskan slot yang bertentangan (jika slot sudah diisi di kelas tujuan, atau guru sudah mengajar di kelas lain pada jam tersebut).
                </p>
            </div>

            <div class="space-y-4">
                <div>
                    <x-input-label value="Semester Sumber" />
                    <select name="source_semester_id" x-model="duplicateForm.source_semester_id" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Pilih Semester Sumber —</option>
                        @foreach ($semesterList as $sem)
                            <option value="{{ $sem->id }}">{{ $sem->nama }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-gray-400">Pilih semester asal data yang akan di-copy.</p>
                </div>

                <div>
                    <x-input-label value="Kelas Sumber" />
                    <select name="source_kelas_id" x-model="duplicateForm.source_kelas_id" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Pilih Kelas Sumber —</option>
                        @foreach ($kelasList as $kel)
                            <option value="{{ $kel->id }}" x-show="String({{ $kel->id }}) !== String(duplicateForm.target_kelas_id)">{{ $kel->nama }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-gray-400">Pilih kelas yang memiliki konfigurasi jadwal yang ingin diterapkan.</p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 border-t border-gray-100">
                <x-secondary-button type="button" @click="showModalDuplicate = false" x-bind:disabled="duplicateForm.loading">Batal</x-secondary-button>
                <x-primary-button type="submit" x-bind:disabled="duplicateForm.loading" class="gap-1.5">
                    <span x-show="!duplicateForm.loading">Mulai Duplikasi</span>
                    <span x-show="duplicateForm.loading">Menyalin...</span>
                </x-primary-button>
            </div>
        </form>
    </div>
</div>
