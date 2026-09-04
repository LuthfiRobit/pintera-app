{{-- Modal Form Tambah / Edit RPP --}}
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
                    <x-icon name="description" class="h-5 w-5 text-brand-500" />
                    <span x-text="formModal.mode === 'create' ? 'Unggah Perangkat Ajar (RPP)' : 'Edit Dokumen RPP'"></span>
                </h3>
                <p class="text-xs text-gray-500 mt-0.5" x-text="formModal.mode === 'create' ? 'Lengkapi metadata dan unggah berkas RPP / Modul Ajar.' : 'Perbarui informasi topik atau ganti berkas lampiran.'"></p>
            </div>
            <button @click="showModalForm = false" type="button" class="text-gray-400 hover:text-gray-600 transition">
                <x-icon name="cancel" class="h-5 w-5" />
            </button>
        </div>

        <form :action="formModal.actionUrl" method="POST" enctype="multipart/form-data" class="mt-4 flex-1 overflow-y-auto pr-1 space-y-4">
            @csrf
            <template x-if="formModal.mode === 'edit'">
                <input type="hidden" name="_method" value="PUT">
            </template>

            <div x-show="formModal.errorMessage" class="rounded-xl bg-error-50 p-3 text-xs text-error-700 font-medium border border-error-200">
                <p x-text="formModal.errorMessage"></p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Semester --}}
                <div>
                    <x-input-label value="Semester" />
                    <select name="semester_id" required x-model="formModal.semester_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-xs text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Pilih Semester —</option>
                        @foreach ($semesterList as $semester)
                            <option value="{{ $semester->id }}">{{ $semester->nama }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Kelas --}}
                <div>
                    <x-input-label value="Kelas Target" />
                    <select name="kelas_id" required x-model="formModal.kelas_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-xs text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Pilih Kelas —</option>
                        @foreach ($kelasList as $kelas)
                            <option value="{{ $kelas->id }}">{{ $kelas->nama }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if (! auth()->user()->guru)
                <div>
                    <x-input-label value="Guru Pengampu" />
                    <select name="guru_id" required x-model="formModal.guru_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-xs text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Pilih Guru Pengampu —</option>
                        @foreach ($guruList ?? [] as $guruOpsi)
                            <option value="{{ $guruOpsi->id }}">{{ $guruOpsi->nama }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- Mata Pelajaran --}}
            <div>
                <x-input-label value="Mata Pelajaran" />
                <select name="mata_pelajaran_id" x-model="formModal.mata_pelajaran_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-xs text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">— Tidak ada (Khusus PAUD / Sentra Tematik) —</option>
                    @foreach ($mataPelajaranList as $mapel)
                        <option value="{{ $mapel->id }}">{{ $mapel->nama }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] text-gray-400">Kosongkan jika RPP berlaku tematik menyeluruh (PAUD/TK).</p>
            </div>

            {{-- Judul Topik / Lingkup Materi --}}
            <div>
                <x-input-label value="Judul Topik / Lingkup Materi" />
                <input type="text" name="judul_topik" required x-model="formModal.judul_topik" placeholder="Contoh: Operasi Hitung Aljabar, Pengenalan Hewan Ternak" class="mt-1.5 block w-full rounded-lg border-gray-200 text-xs text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Alokasi Waktu --}}
                <div>
                    <x-input-label value="Alokasi Waktu" />
                    <input type="text" name="alokasi_waktu" required x-model="formModal.alokasi_waktu" placeholder="Contoh: 2 x 35 Menit / 1 Pekan" class="mt-1.5 block w-full rounded-lg border-gray-200 text-xs text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                </div>

                {{-- Pertemuan Ke --}}
                <div>
                    <x-input-label value="Pertemuan Ke- (Opsional)" />
                    <input type="text" name="pertemuan_ke" x-model="formModal.pertemuan_ke" placeholder="Contoh: 1, 2, atau Pekan 3" class="mt-1.5 block w-full rounded-lg border-gray-200 text-xs text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>

            {{-- Upload File Lampiran --}}
            <div>
                <x-input-label value="Berkas Lampiran RPP / Modul Ajar" />
                <div class="mt-1.5 flex justify-center rounded-xl border-2 border-dashed border-gray-200 px-6 pt-5 pb-6 hover:border-brand-400 transition-colors bg-gray-50/50 relative">
                    <input type="file" id="rpp_file_input_lembaga" name="file" :required="formModal.mode === 'create'" accept=".pdf,.doc,.docx" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" @change="selectedFileName = $event.target.files[0] ? $event.target.files[0].name : ''">
                    <div class="space-y-1 text-center pointer-events-none">
                        <x-icon name="cloud_upload" class="mx-auto h-8 w-8 text-gray-400" />
                        <div class="text-xs text-gray-600 font-medium">
                            <span class="font-bold text-brand-600">Klik untuk pilih berkas</span> atau seret file ke sini
                        </div>
                        <p class="text-[11px] text-gray-400">PDF, DOC, DOCX hingga 10MB</p>
                        <template x-if="selectedFileName">
                            <p class="text-xs font-bold text-emerald-600 pt-1 flex items-center justify-center gap-1">
                                <x-icon name="check_circle" class="h-4 w-4" />
                                <span x-text="selectedFileName"></span>
                            </p>
                        </template>
                    </div>
                </div>
                <template x-if="formModal.mode === 'edit'">
                    <p class="mt-1 text-[11px] text-gray-400">Biarkan kosong jika tidak ingin mengubah berkas dokumen yang sudah ada.</p>
                </template>
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 mt-6 border-t border-gray-100 shrink-0">
                <x-secondary-button type="button" @click="showModalForm = false">Batal</x-secondary-button>
                <x-primary-button type="submit" class="gap-1.5">
                    <x-icon name="save" class="h-4 w-4" />
                    <span x-text="formModal.mode === 'create' ? 'Simpan Sebagai Draf' : 'Perbarui Dokumen'"></span>
                </x-primary-button>
            </div>
        </form>
    </div>
</div>
