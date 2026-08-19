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
                Akademik <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.komponen-penilaian.index') }}" class="font-semibold text-gray-700 hover:text-brand-600 transition-colors">Komponen Penilaian</a>
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
                <p class="mt-0.5 text-xs text-gray-500">Isi rincian kode, deskripsi TP, dan kriteria ketuntasan.</p>
            </div>

            <form method="POST" action="{{ route('admin.komponen-penilaian.store') }}" class="p-6 space-y-6" x-data="komponenPenilaianCreateForm({ tahunAjaranId: @js($tahunAjaranId), opsiUrl: @js(route('admin.komponen-penilaian.opsi')) })">
                @csrf

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <x-input-label value="Tahun Ajaran *" />
                        <select
                            name="tahun_ajaran_id"
                            required
                            x-ref="tahunAjaranSelect"
                            x-init="initTahunAjaranSelect($refs.tahunAjaranSelect)"
                            class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                        >
                            <option value="">— Pilih Tahun Ajaran —</option>
                            @foreach ($tahunAjaranList as $tahunAjaran)
                                <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label value="Semester *" />
                        <select
                            name="semester_id"
                            required
                            x-ref="semesterSelect"
                            x-init="initSemesterSelect($refs.semesterSelect)"
                            class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                        >
                            <option value="">— Pilih Semester —</option>
                            @foreach ($semesterList as $semester)
                                <option value="{{ $semester->id }}" @selected(old('semester_id') == $semester->id)>{{ $semester->nama }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('semester_id')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Mata Pelajaran *" />
                        <select
                            name="mata_pelajaran_id"
                            required
                            x-ref="mataPelajaranSelect"
                            x-init="initMataPelajaranSelect($refs.mataPelajaranSelect)"
                            class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                        >
                            <option value="">— Pilih Mata Pelajaran —</option>
                            @foreach ($mataPelajaranList as $mapel)
                                <option value="{{ $mapel->id }}" @selected(old('mata_pelajaran_id') == $mapel->id)>{{ $mapel->nama }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('mata_pelajaran_id')" class="mt-1" />
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
                        <p class="mt-1 text-xs text-gray-400">Total bobot per Mapel dan Semester maksimal 100% (Guard aktif).</p>
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
                    <p class="mt-1 text-xs text-gray-400">Indikator atau rubrik ketuntasan yang akan dipedomani oleh guru mata pelajaran.</p>
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

                    @if (in_array($bentukPendidikan, ['KB', 'TPA', 'SPS', 'TK'], true))
                        <div>
                            <x-input-label value="Elemen Capaian Pembelajaran (PAUD)" />
                            <select
                                name="elemen_cp"
                                class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                            >
                                <option value="">— Pilih Elemen CP —</option>
                                <option value="nilai_agama_moral" @selected(old('elemen_cp') === 'nilai_agama_moral')>Nilai Agama dan Budi Pekerti</option>
                                <option value="jati_diri" @selected(old('elemen_cp') === 'jati_diri')>Jati Diri</option>
                                <option value="literasi_steam" @selected(old('elemen_cp') === 'literasi_steam')>Literasi, STEAM, Seni, dan Budaya</option>
                            </select>
                            <x-input-error :messages="$errors->get('elemen_cp')" class="mt-1" />
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-3 pt-4 border-t border-gray-100">
                    <x-primary-button type="submit">
                        Simpan Komponen
                    </x-primary-button>
                    <a href="{{ route('admin.komponen-penilaian.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                        Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
