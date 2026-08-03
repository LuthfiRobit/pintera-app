<div x-show="showModalForm" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center" x-cloak style="display: none;">
    <div x-show="showModalForm" class="fixed inset-0 transform transition-all" @click="showModalForm = false"
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="absolute inset-0 bg-gray-900/60"></div>
    </div>

    <div x-show="showModalForm" class="bg-white rounded-2xl overflow-hidden shadow-elevated transform transition-all sm:max-w-xl sm:w-full z-10 p-6 relative text-left max-h-[90vh] flex flex-col"
         x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
        
        <div class="flex items-center justify-between pb-3.5 border-b border-gray-200 shrink-0">
            <div>
                <h3 class="font-display text-base font-bold text-gray-900 flex items-center gap-2">
                    <x-icon name="schedule" class="h-5 w-5 text-brand-500" />
                    <span x-text="formModal.mode === 'create' ? 'Tambah Slot Jadwal' : 'Edit Sesi Jadwal'"></span>
                </h3>
                <p class="text-xs text-gray-500 mt-0.5" x-text="formModal.mode === 'create' ? 'Tentukan slot jam, mata pelajaran, dan pengampu baru.' : 'Perbarui informasi slot waktu, mata pelajaran, atau guru pengampu.'"></p>
            </div>
            <button @click="showModalForm = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
                <x-icon name="cancel" class="h-5 w-5" />
            </button>
        </div>

        @if (isset($jamPelajaranPerHari) && $jamPelajaranPerHari->isEmpty())
            <div class="mt-4 flex items-start gap-3 rounded-2xl border border-warning-200 bg-warning-50 p-5 text-sm text-warning-700">
                <x-icon name="warning" class="mt-0.5 h-5 w-5 shrink-0 text-warning-500" />
                <div>
                    <p class="font-semibold">Kelas ini belum punya Pola Jam</p>
                    <p class="mt-1">Atur Pola Jam terlebih dahulu sebelum menambahkan atau mengelola jadwal pelajaran untuk kelas ini.</p>
                    <a href="{{ route('admin.pola-jam.index') }}" class="mt-2 inline-block font-semibold text-warning-800 underline">Buka halaman Pola Jam &rarr;</a>
                </div>
            </div>
            <div class="flex items-center justify-end gap-2 pt-4 mt-6 border-t border-gray-100 shrink-0">
                <x-secondary-button type="button" @click="showModalForm = false">Tutup</x-secondary-button>
            </div>
        @else
            <form :action="formModal.actionUrl" method="POST" @submit="submitForm($event)" class="mt-4 flex-1 overflow-y-auto pr-1 space-y-5">
                @csrf
                <template x-if="formModal.mode === 'edit'">
                    <input type="hidden" name="_method" value="PUT">
                </template>
                <input type="hidden" name="kelas_id" :value="kelasId">
                <input type="hidden" name="semester_id" :value="semesterId">

                <div x-show="formModal.errorMessage" class="rounded-xl bg-error-50 p-3 text-xs text-error-700 font-medium border border-error-200">
                    <p x-text="formModal.errorMessage"></p>
                </div>

                {{-- Slot Jam Pelajaran --}}
                <div>
                    <x-input-label value="Jam Pelajaran" />
                    <template x-if="formModal.mode === 'create'">
                        <div>
                            <select name="jam_pelajaran_id[]" multiple required x-init="initModalCreateJamSelect($el)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                @foreach ($jamPelajaranPerHari ?? [] as $grup)
                                    <optgroup label="{{ $grup['hari']->label() }}">
                                        @foreach ($grup['items'] as $jam)
                                            <option value="{{ $jam->id }}">{{ substr($jam->jam_mulai, 0, 5) }}–{{ substr($jam->jam_selesai, 0, 5) }} ({{ $jam->label }})</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <p class="mt-1.5 text-[11px] text-gray-400">Pilih satu atau beberapa slot sekaligus. Klik tombol <span class="font-semibold text-gray-600">×</span> pada tag untuk membatalkan pilihan.</p>
                        </div>
                    </template>
                    <template x-if="formModal.mode === 'edit'">
                        <div>
                            <select name="jam_pelajaran_id" required x-init="initModalEditJamSelect($el)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="">— Pilih slot jam —</option>
                                @foreach ($jamPelajaranPerHari ?? [] as $grup)
                                    <optgroup label="{{ $grup['hari']->label() }}">
                                        @foreach ($grup['items'] as $jam)
                                            <option value="{{ $jam->id }}">{{ substr($jam->jam_mulai, 0, 5) }}–{{ substr($jam->jam_selesai, 0, 5) }} ({{ $jam->label }})</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                    </template>
                </div>

                {{-- Mata Pelajaran & Guru --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Mata Pelajaran" />
                        <select name="mata_pelajaran_id" x-init="initModalMapelSelect($el)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="">— Tidak ada —</option>
                            @foreach ($mataPelajaranList ?? [] as $mapel)
                                <option value="{{ $mapel->id }}">{{ $mapel->nama }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[11px] text-gray-400">Opsional untuk kelas PAUD/Tematik.</p>
                    </div>

                    <div>
                        <x-input-label value="Guru Pengampu" />
                        <select name="guru_id" required x-init="initModalGuruSelect($el)" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="" disabled>— Pilih Guru —</option>
                            @foreach ($guruList ?? [] as $guru)
                                <option value="{{ $guru->id }}">{{ $guru->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-4 mt-6 border-t border-gray-100 shrink-0">
                    <x-secondary-button type="button" @click="showModalForm = false" x-bind:disabled="formModal.loading">Batal</x-secondary-button>
                    <x-primary-button type="submit" x-bind:disabled="formModal.loading" class="gap-1.5">
                        <span x-show="!formModal.loading" x-text="formModal.mode === 'create' ? 'Simpan Jadwal' : 'Perbarui Jadwal'"></span>
                        <span x-show="formModal.loading">Menyeduh...</span>
                    </x-primary-button>
                </div>
            </form>
        @endif
    </div>
</div>
