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
            <h1 class="font-display text-lg font-bold text-gray-900">Tambah Komponen Penilaian (TP)</h1>
            <p class="text-sm text-gray-500">
                Ruang Guru <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('guru.komponen-penilaian.index') }}" class="font-semibold text-gray-700 hover:text-brand-600 transition-colors">Komponen Penilaian</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Tambah TP</b>
            </p>
        </div>

        {{-- Form Card --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="border-b border-gray-100 bg-white px-6 py-4">
                <p class="flex items-center gap-2 font-display text-sm font-bold text-gray-900">
                    <x-icon name="checklist" class="h-4 w-4 text-brand-500" />
                    Formulir Tujuan Pembelajaran &amp; KKTP
                </p>
                <p class="mt-0.5 text-xs text-gray-500">Pilih jenis subjek (Mata Pelajaran / Elemen CP PAUD), semester, dan deskripsi TP.</p>
            </div>

            <form method="POST" action="{{ route('guru.komponen-penilaian.store') }}" class="p-6 space-y-6" x-data="{ subjekType: '{{ old('subjek_type', in_array($bentukPendidikan, ['KB', 'TPA', 'SPS', 'TK'], true) ? 'elemen_cp' : 'mata_pelajaran') }}', assessmentType: '{{ old('assessment_type', in_array($bentukPendidikan, ['KB', 'TPA', 'SPS', 'TK'], true) ? 'narrative' : 'numeric') }}' }">
                @csrf

                <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-4 space-y-3">
                    <x-input-label value="Jenis Subjek Penilaian *" />
                    <div class="flex items-center gap-6">
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-800 cursor-pointer">
                            <input type="radio" name="subjek_type" value="mata_pelajaran" x-model="subjekType" @change="assessmentType = 'numeric'" class="text-brand-600 focus:ring-brand-500">
                            Mata Pelajaran
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-800 cursor-pointer">
                            <input type="radio" name="subjek_type" value="elemen_cp" x-model="subjekType" @change="assessmentType = 'narrative'" class="text-brand-600 focus:ring-brand-500">
                            Elemen CP (PAUD)
                        </label>
                    </div>
                    <x-input-error :messages="$errors->get('subjek_type')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="Tipe Penilaian *" />
                    <select name="assessment_type" x-model="assessmentType" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
                        <option value="numeric">Nilai Angka</option>
                        <option value="narrative">Naratif/Deskriptif</option>
                        <option value="predicate">Predikat Capaian (BB/MB/BSH/BSB)</option>
                    </select>
                    <x-input-error :messages="$errors->get('assessment_type')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div x-show="subjekType === 'mata_pelajaran'">
                        <x-input-label value="Mata Pelajaran *" />
                        <select name="subjek_id" :disabled="subjekType !== 'mata_pelajaran'" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">— Pilih Mata Pelajaran —</option>
                            @foreach ($mataPelajaranList as $mapel)
                                <option value="{{ $mapel->id }}" @selected(old('subjek_type') === 'mata_pelajaran' && old('subjek_id') == $mapel->id)>{{ $mapel->nama }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('subjek_id')" class="mt-1" />
                    </div>

                    <div x-show="subjekType === 'elemen_cp'">
                        <x-input-label value="Elemen CP (PAUD) *" />
                        <select name="subjek_id" :disabled="subjekType !== 'elemen_cp'" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">— Pilih Elemen CP —</option>
                            @foreach ($elemenCpList as $elemen)
                                <option value="{{ $elemen->id }}" @selected(old('subjek_type') === 'elemen_cp' && old('subjek_id') == $elemen->id)>{{ $elemen->nama }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('subjek_id')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Semester *" />
                        <select name="semester_id" required class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">— Pilih Semester —</option>
                            @foreach ($semesterList as $semester)
                                <option value="{{ $semester->id }}" @selected(old('semester_id') == $semester->id)>{{ $semester->nama }} — {{ $semester->tahunAjaran->nama }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('semester_id')" class="mt-1" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Kode Tujuan Pembelajaran (Opsional)" />
                        <input
                            type="text"
                            name="kode"
                            value="{{ old('kode') }}"
                            placeholder="Contoh: TP 3.1 atau TP 4.2"
                            class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                        >
                        <p class="mt-1 text-xs text-gray-400">Kode ringkas untuk dihubungkan saat input asesmen.</p>
                        <x-input-error :messages="$errors->get('kode')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Bobot Penilaian (%) *" />
                        <input 
                            type="number" 
                            name="bobot" 
                            value="{{ old('bobot', 100) }}" 
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
                        placeholder="Contoh: Peserta didik mampu menganalisis struktur lapisan bumi dan mitigasi bencana alam..."
                        class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500 p-3"
                    >{{ old('deskripsi') }}</textarea>
                    <x-input-error :messages="$errors->get('deskripsi')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="KKTP / Kriteria Ketercapaian (Opsional)" />
                    <textarea
                        name="kktp"
                        rows="3"
                        placeholder="Contoh: Mampu menyebutkan minimal 3 lapisan bumi dan 2 tindakan penyelamatan diri saat gempa..."
                        class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500 p-3"
                    >{{ old('kktp') }}</textarea>
                    <x-input-error :messages="$errors->get('kktp')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Ambang KKTP Minimal (Opsional)" />
                        <input
                            type="number"
                            name="kktp_minimal"
                            value="{{ old('kktp_minimal') }}"
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
                        Simpan Komponen
                    </x-primary-button>
                    <a href="{{ route('guru.komponen-penilaian.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                        Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
