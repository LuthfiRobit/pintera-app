<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.komponen-penilaian.index') }}" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50 hover:text-gray-900 shadow-sm">
                <x-icon name="arrow_back" class="h-5 w-5" />
            </a>
            <div>
                <nav class="flex text-xs text-gray-500 mb-1">
                    <span class="hover:text-gray-700">Akademik</span>
                    <span class="mx-2">/</span>
                    <a href="{{ route('admin.komponen-penilaian.index') }}" class="hover:text-gray-700">Komponen Penilaian</a>
                    <span class="mx-2">/</span>
                    <span class="text-gray-700 font-semibold">Tambah</span>
                </nav>
                <h2 class="text-2xl font-bold text-gray-900">
                    Tambah Komponen Penilaian (TP)
                </h2>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl py-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
            <form method="POST" action="{{ route('admin.komponen-penilaian.store') }}" class="space-y-6">
                @csrf

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Mata Pelajaran <span class="text-error-500">*</span></label>
                        <select 
                            name="mata_pelajaran_id" 
                            required
                            class="w-full rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500 py-2.5"
                        >
                            <option value="">— Pilih Mata Pelajaran —</option>
                            @foreach ($mataPelajaranList as $mapel)
                                <option value="{{ $mapel->id }}" @selected(old('mata_pelajaran_id') == $mapel->id)>{{ $mapel->nama }}</option>
                            @endforeach
                        </select>
                        @error('mata_pelajaran_id')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Semester <span class="text-error-500">*</span></label>
                        <select 
                            name="semester_id" 
                            required
                            class="w-full rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500 py-2.5"
                        >
                            <option value="">— Pilih Semester —</option>
                            @foreach ($semesterList as $semester)
                                <option value="{{ $semester->id }}" @selected(old('semester_id') == $semester->id)>{{ $semester->nama }}</option>
                            @endforeach
                        </select>
                        @error('semester_id')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Kode Tujuan Pembelajaran (Opsional)</label>
                    <input 
                        type="text" 
                        name="kode" 
                        value="{{ old('kode') }}" 
                        placeholder="Contoh: TP 3.1 atau TP 4.2" 
                        class="w-full rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500 py-2.5"
                    >
                    <p class="mt-1 text-xs text-gray-400">Kode ringkas untuk dihubungkan pada saat guru melakukan input asesmen.</p>
                    @error('kode')
                        <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Deskripsi Tujuan Pembelajaran <span class="text-error-500">*</span></label>
                    <textarea 
                        name="deskripsi" 
                        rows="3" 
                        required
                        placeholder="Contoh: Peserta didik mampu menganalisis struktur lapisan bumi dan mitigasi bencana alam..."
                        class="w-full rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500 p-3"
                    >{{ old('deskripsi') }}</textarea>
                    @error('deskripsi')
                        <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">KKTP / Kriteria Ketercapaian (Opsional)</label>
                    <textarea 
                        name="kktp" 
                        rows="3" 
                        placeholder="Contoh: Mampu menyebutkan minimal 3 lapisan bumi dan 2 tindakan penyelamatan diri saat gempa..."
                        class="w-full rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500 p-3"
                    >{{ old('kktp') }}</textarea>
                    <p class="mt-1 text-xs text-gray-400">Indikator atau rubrik ketuntasan yang akan dipedomani oleh guru mata pelajaran.</p>
                    @error('kktp')
                        <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                    <a 
                        href="{{ route('admin.komponen-penilaian.index') }}" 
                        class="rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-100 hover:text-gray-900"
                    >
                        Batal
                    </a>
                    <button 
                        type="submit" 
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition duration-200 hover:bg-brand-600 active:scale-[0.98]"
                    >
                        Simpan Komponen
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
