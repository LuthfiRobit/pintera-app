<div x-show="showModalForm" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center" x-cloak style="display: none;">
    <div x-show="showModalForm" class="fixed inset-0 transform transition-all" @click="showModalForm = false"
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="absolute inset-0 bg-gray-900/60"></div>
    </div>

    <div x-show="showModalForm" class="bg-white rounded-2xl overflow-hidden shadow-elevated transform transition-all sm:max-w-lg sm:w-full z-10 p-6 relative text-left"
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">

        <div class="flex items-center justify-between pb-3.5 border-b border-gray-200">
            <div>
                <h3 class="font-display text-base font-bold text-gray-900 flex items-center gap-2">
                    <x-icon name="edit_calendar" class="h-5 w-5 text-brand-500" />
                    <span x-text="formModal.mode === 'create' ? 'Tambah Jadwal Piket Mingguan' : 'Edit Jadwal Piket Mingguan'"></span>
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    Untuk lembaga <strong class="font-semibold text-gray-700" x-text="formModalLembagaNama()"></strong>.
                </p>
            </div>
            <button @click="showModalForm = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
                <x-icon name="cancel" class="h-5 w-5" />
            </button>
        </div>

        <form :action="formModal.actionUrl" method="POST" @submit="submitForm($event)" class="mt-4 space-y-5">
            @csrf
            <template x-if="formModal.mode === 'edit'">
                <input type="hidden" name="_method" value="PUT">
            </template>

            <div x-show="formModal.errorMessage" class="rounded-xl bg-error-50 p-3 text-xs text-error-700 font-medium border border-error-200">
                <p x-text="formModal.errorMessage"></p>
            </div>

            <div class="relative z-20">
                <x-input-label value="Guru" />
                <div class="mt-1.5">
                    <select
                        name="guru_id"
                        required
                        x-init="initModalGuruSelect($el)"
                        class="block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    >
                        <option value="">— Pilih atau cari guru —</option>
                    </select>
                </div>
                <p x-show="formModal.errors?.guru_id" x-text="formModal.errors?.guru_id?.[0]" class="mt-1 text-[11px] text-error-600"></p>
            </div>

            <div>
                <x-input-label value="Hari" />
                <select name="hari" x-model="formModal.hari" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">— Pilih hari —</option>
                    @foreach ([1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 0 => 'Minggu'] as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p x-show="formModal.errors?.hari" x-text="formModal.errors?.hari?.[0]" class="mt-1 text-[11px] text-error-600"></p>
            </div>

            <div>
                <x-input-label value="Tahun Ajaran & Semester" />
                <select name="semester_id" x-model="formModal.semester_id" x-init="initModalSemesterSelect($el)" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">— Pilih semester —</option>
                    @foreach (($semesterList ?? collect())->groupBy(fn ($semester) => $semester->tahunAjaran->nama) as $namaTahunAjaran => $semesterGrup)
                        <optgroup label="{{ $namaTahunAjaran }}">
                            @foreach ($semesterGrup as $semester)
                                <option value="{{ $semester->id }}" data-lembaga-id="{{ $semester->lembaga_id }}">
                                    {{ $semester->nama }}{{ ($isYayasanAggregate ?? false) ? ' ('.($semester->lembaga->nama ?? '-').')' : '' }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <p x-show="formModal.errors?.semester_id" x-text="formModal.errors?.semester_id?.[0]" class="mt-1 text-[11px] text-error-600"></p>
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 mt-2 border-t border-gray-100">
                <x-secondary-button type="button" @click="showModalForm = false" x-bind:disabled="formModal.loading">Batal</x-secondary-button>
                <x-primary-button type="submit" x-bind:disabled="formModal.loading" class="gap-1.5">
                    <span x-show="!formModal.loading" x-text="formModal.mode === 'create' ? 'Simpan Jadwal' : 'Perbarui Jadwal'"></span>
                    <span x-show="formModal.loading">Menyimpan...</span>
                </x-primary-button>
            </div>
        </form>
    </div>
</div>
