<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Edit Komponen Penilaian (TP)</h1>
            <p class="text-sm text-gray-500">
                Akademik <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.komponen-penilaian.index') }}" class="font-semibold text-gray-700 hover:text-brand-600 transition-colors">Komponen Penilaian</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Edit</b>
            </p>
        </div>

        @if ($dipakai)
            <div class="flex items-start gap-3 rounded-2xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-700">
                <x-icon name="warning" class="mt-0.5 h-5 w-5 shrink-0 text-warning-500" />
                <p>Komponen ini sudah dipakai pada asesmen atau nilai siswa — Subjek Penilaian dan Semester tidak bisa diubah supaya data nilai yang sudah tercatat tetap konsisten.</p>
            </div>
        @endif

        {{-- Form Card --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="border-b border-gray-100 bg-white px-6 py-4">
                <p class="flex items-center gap-2 font-display text-sm font-bold text-gray-900">
                    <x-icon name="checklist" class="h-4 w-4 text-brand-500" />
                    Formulir Tujuan Pembelajaran &amp; KKTP
                </p>
                <p class="mt-0.5 text-xs text-gray-500">Ubah rincian kode, deskripsi TP, dan kriteria ketuntasan.</p>
            </div>

            <form method="POST" action="{{ route('admin.komponen-penilaian.update', $komponenPenilaian) }}" class="p-6 space-y-6" x-data="komponenPenilaianEditForm()">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Subjek Penilaian" />
                        <p class="mt-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ $komponenPenilaian->subjek->nama }}</p>
                    </div>

                    <div>
                        <x-input-label value="Semester" />
                        <p class="mt-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ $komponenPenilaian->semester->nama }} — {{ $komponenPenilaian->semester->tahunAjaran->nama }}</p>
                    </div>
                </div>

                <div>
                    <x-input-label value="Tipe Penilaian *" />
                    @if ($dipakai)
                        <input type="hidden" name="assessment_type" value="{{ $komponenPenilaian->assessment_type->value }}">
                    @endif
                    <select name="assessment_type" @disabled($dipakai) required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500 @if($dipakai) bg-gray-50 @endif">
                        <option value="numeric" @selected(old('assessment_type', $komponenPenilaian->assessment_type->value) === 'numeric')>Nilai Angka</option>
                        <option value="narrative" @selected(old('assessment_type', $komponenPenilaian->assessment_type->value) === 'narrative')>Naratif/Deskriptif</option>
                        <option value="predicate" @selected(old('assessment_type', $komponenPenilaian->assessment_type->value) === 'predicate')>Predikat Capaian (BB/MB/BSH/BSB)</option>
                    </select>
                    <x-input-error :messages="$errors->get('assessment_type')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Kode Tujuan Pembelajaran (Opsional)" />
                        <input
                            type="text"
                            name="kode"
                            value="{{ old('kode', $komponenPenilaian->kode) }}"
                            placeholder="Contoh: TP 3.1 atau TP 4.2"
                            class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                        >
                        <x-input-error :messages="$errors->get('kode')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Bobot Penilaian (%) *" />
                        <input 
                            type="number" 
                            name="bobot" 
                            value="{{ old('bobot', $komponenPenilaian->bobot ?? 100) }}" 
                            min="1" 
                            max="100" 
                            required
                            placeholder="1 - 100" 
                            class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm font-semibold transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                        >
                        <p class="mt-1 text-xs text-gray-400">Total bobot per Subjek dan Semester maksimal 100% (Guard aktif).</p>
                        <x-input-error :messages="$errors->get('bobot')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label value="Deskripsi Tujuan Pembelajaran *" />
                    <textarea
                        name="deskripsi"
                        rows="3"
                        required
                        class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500 p-3"
                    >{{ old('deskripsi', $komponenPenilaian->deskripsi) }}</textarea>
                    <x-input-error :messages="$errors->get('deskripsi')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="KKTP / Kriteria Ketercapaian (Opsional)" />
                    <textarea
                        name="kktp"
                        rows="3"
                        class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500 p-3"
                    >{{ old('kktp', $komponenPenilaian->kktp) }}</textarea>
                    <x-input-error :messages="$errors->get('kktp')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Ambang KKTP Minimal (Opsional)" />
                        <input
                            type="number"
                            name="kktp_minimal"
                            value="{{ old('kktp_minimal', $komponenPenilaian->kktp_minimal) }}"
                            min="0"
                            max="100"
                            placeholder="Contoh: 75"
                            class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                        >
                        <p class="mt-1 text-xs text-gray-400">Ambang skor numerik untuk narasi capaian otomatis (default 75 jika kosong).</p>
                        <x-input-error :messages="$errors->get('kktp_minimal')" class="mt-1" />
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-4 border-t border-gray-100">
                    <x-primary-button type="submit">
                        Simpan Perubahan
                    </x-primary-button>
                    <a href="{{ route('admin.komponen-penilaian.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                        Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
