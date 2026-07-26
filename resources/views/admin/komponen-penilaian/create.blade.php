<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
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

            <form method="POST" action="{{ route('admin.komponen-penilaian.store') }}" class="p-6 space-y-6">
                @csrf

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Mata Pelajaran *" />
                        <select 
                            name="mata_pelajaran_id" 
                            required
                            class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                        >
                            <option value="">— Pilih Mata Pelajaran —</option>
                            @foreach ($mataPelajaranList as $mapel)
                                <option value="{{ $mapel->id }}" @selected(old('mata_pelajaran_id') == $mapel->id)>{{ $mapel->nama }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('mata_pelajaran_id')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Semester *" />
                        <select 
                            name="semester_id" 
                            required
                            class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                        >
                            <option value="">— Pilih Semester —</option>
                            @foreach ($semesterList as $semester)
                                <option value="{{ $semester->id }}" @selected(old('semester_id') == $semester->id)>{{ $semester->nama }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('semester_id')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label value="Kode Tujuan Pembelajaran (Opsional)" />
                    <input 
                        type="text" 
                        name="kode" 
                        value="{{ old('kode') }}" 
                        placeholder="Contoh: TP 3.1 atau TP 4.2" 
                        class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                    >
                    <p class="mt-1 text-xs text-gray-400">Kode ringkas untuk dihubungkan pada saat guru melakukan input asesmen.</p>
                    <x-input-error :messages="$errors->get('kode')" class="mt-1" />
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
