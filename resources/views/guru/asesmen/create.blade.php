<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('guru.asesmen.index') }}" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50 hover:text-gray-900 shadow-sm">
                <x-icon name="arrow_back" class="h-5 w-5" />
            </a>
            <div>
                <nav class="flex text-xs text-gray-500 mb-1">
                    <span class="hover:text-gray-700">Ruang Guru</span>
                    <span class="mx-2">/</span>
                    <a href="{{ route('guru.asesmen.index') }}" class="hover:text-gray-700">Asesmen</a>
                    <span class="mx-2">/</span>
                    <span class="text-gray-700 font-semibold">Buat Baru</span>
                </nav>
                <h2 class="text-2xl font-bold text-gray-900">
                    Buat Kegiatan Asesmen Baru
                </h2>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl py-6" x-data="{ selectedMapel: '{{ old('mata_pelajaran_id', '') }}' }">
        <div class="rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
            <form method="POST" action="{{ route('guru.asesmen.store') }}" class="space-y-6">
                @csrf

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Kelas <span class="text-error-500">*</span></label>
                        <select 
                            name="kelas_id" 
                            required
                            class="w-full rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500 py-2.5"
                        >
                            <option value="">— Pilih Kelas —</option>
                            @foreach ($kelasList as $kelas)
                                <option value="{{ $kelas->id }}" @selected(old('kelas_id') == $kelas->id)>{{ $kelas->nama }} ({{ $kelas->tahunAjaran->nama ?? '' }})</option>
                            @endforeach
                        </select>
                        @error('kelas_id')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Mata Pelajaran <span class="text-error-500">*</span></label>
                        <select 
                            name="mata_pelajaran_id" 
                            x-model="selectedMapel"
                            required
                            class="w-full rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500 py-2.5"
                        >
                            <option value="">— Pilih Mata Pelajaran —</option>
                            @foreach ($mataPelajaranList as $mapel)
                                <option value="{{ $mapel->id }}">{{ $mapel->nama }}</option>
                            @endforeach
                        </select>
                        @error('mata_pelajaran_id')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Semester <span class="text-error-500">*</span></label>
                        <select 
                            name="semester_id" 
                            required
                            class="w-full rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500 py-2.5"
                        >
                            <option value="">— Pilih Semester —</option>
                            @foreach ($semesterList as $semester)
                                <option value="{{ $semester->id }}" @selected(old('semester_id') == $semester->id || $semester->status_aktif)>{{ $semester->nama }} @if($semester->status_aktif) (Aktif) @endif</option>
                            @endforeach
                        </select>
                        @error('semester_id')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Jenis Asesmen <span class="text-error-500">*</span></label>
                        <select 
                            name="jenis" 
                            required
                            class="w-full rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500 py-2.5"
                        >
                            <option value="">— Pilih Jenis Asesmen —</option>
                            @foreach ($jenisAsesmenList as $jenis)
                                <option value="{{ $jenis->value }}" @selected(old('jenis') == $jenis->value)>{{ $jenis->label() }}</option>
                            @endforeach
                        </select>
                        @error('jenis')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Judul Kegiatan Asesmen <span class="text-error-500">*</span></label>
                        <input 
                            type="text" 
                            name="judul" 
                            value="{{ old('judul') }}" 
                            required
                            placeholder="Contoh: Ulangan Harian Bab 1 / Projek Pesta Karya / PAS Genap" 
                            class="w-full rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500 py-2.5"
                        >
                        @error('judul')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Tanggal Pelaksanaan <span class="text-error-500">*</span></label>
                        <input 
                            type="date" 
                            name="tanggal" 
                            value="{{ old('tanggal', now()->toDateString()) }}" 
                            required
                            class="w-full rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500 py-2.5"
                        >
                        @error('tanggal')
                            <p class="mt-1 text-xs text-error-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="border-t border-gray-150 pt-6">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <label class="block text-sm font-bold text-gray-900">Hubungan Tujuan Pembelajaran (TP) / KKTP</label>
                            <p class="text-xs text-gray-500">Pilih indikator TP dari Kurikulum Merdeka yang diasesmen pada kegiatan ini (opsional).</p>
                        </div>
                    </div>

                    <div class="space-y-2 max-h-72 overflow-y-auto pr-2 rounded-xl border border-gray-200 p-4 bg-gray-50/50">
                        @forelse ($komponenList as $komponen)
                            <label 
                                x-show="!selectedMapel || selectedMapel == '{{ $komponen->mata_pelajaran_id }}'"
                                x-transition
                                class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 bg-white transition hover:bg-gray-50 cursor-pointer"
                            >
                                <input 
                                    type="checkbox" 
                                    name="komponen_id[]" 
                                    value="{{ $komponen->id }}" 
                                    @checked(is_array(old('komponen_id')) && in_array($komponen->id, old('komponen_id')))
                                    class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500 h-4 w-4"
                                >
                                <div class="space-y-1 text-xs">
                                    <div class="flex items-center gap-2">
                                        @if ($komponen->kode)
                                            <span class="font-bold text-brand-700 bg-brand-50 px-2 py-0.5 rounded border border-brand-200/50">{{ $komponen->kode }}</span>
                                        @endif
                                        <span class="font-semibold text-gray-900 text-sm">{{ $komponen->deskripsi }}</span>
                                    </div>
                                    @if ($komponen->kktp)
                                        <p class="text-gray-500 italic">KKTP: {{ $komponen->kktp }}</p>
                                    @endif
                                </div>
                            </label>
                        @empty
                            <p class="text-xs text-gray-400 text-center py-4">Belum ada data Tujuan Pembelajaran untuk mata pelajaran Anda.</p>
                        @endforelse

                        <p x-show="selectedMapel && !@js($komponenList->groupBy('mata_pelajaran_id')->toArray())[selectedMapel]" class="text-xs text-gray-400 text-center py-4" style="display: none;">
                            Belum ada Tujuan Pembelajaran yang terdaftar untuk Mata Pelajaran yang Anda pilih.
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                    <a 
                        href="{{ route('guru.asesmen.index') }}" 
                        class="rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-100 hover:text-gray-900"
                    >
                        Batal
                    </a>
                    <button 
                        type="submit" 
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition duration-200 hover:bg-brand-600 active:scale-[0.98]"
                    >
                        Buat Asesmen & Lanjut Input Nilai
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
