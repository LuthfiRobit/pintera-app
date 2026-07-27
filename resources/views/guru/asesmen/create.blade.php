<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4" x-data="{ selectedMapel: '{{ old('mata_pelajaran_id', '') }}' }">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Buat Asesmen Baru</h1>
            <p class="text-sm text-gray-500">
                Ruang Guru <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('guru.asesmen.index') }}" class="font-semibold text-gray-700 hover:text-brand-600 transition-colors">Asesmen &amp; Nilai</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Buat Baru</b>
            </p>
        </div>

        {{-- Form Card --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="border-b border-gray-100 bg-white px-6 py-4">
                <p class="flex items-center gap-2 font-display text-sm font-bold text-gray-900">
                    <x-icon name="assessment" class="h-4 w-4 text-brand-500" />
                    Formulir Kegiatan Asesmen Pembelajaran
                </p>
                <p class="mt-0.5 text-xs text-gray-500">Pilih kelas, mata pelajaran, jenis asesmen, dan indikator TP terkait.</p>
            </div>

            <form method="POST" action="{{ route('guru.asesmen.store') }}" class="p-6 space-y-6">
                @csrf

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Kelas *" />
                        <select 
                            name="kelas_id" 
                            required
                            class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                        >
                            <option value="">— Pilih Kelas —</option>
                            @foreach ($kelasList as $kelas)
                                <option value="{{ $kelas->id }}" @selected(old('kelas_id') == $kelas->id)>{{ $kelas->nama }} ({{ $kelas->tahunAjaran->nama ?? '' }})</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('kelas_id')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Mata Pelajaran *" />
                        <select 
                            name="mata_pelajaran_id" 
                            x-model="selectedMapel"
                            required
                            class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                        >
                            <option value="">— Pilih Mata Pelajaran —</option>
                            @foreach ($mataPelajaranList as $mapel)
                                <option value="{{ $mapel->id }}">{{ $mapel->nama }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('mata_pelajaran_id')" class="mt-1" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Semester *" />
                        <select 
                            name="semester_id" 
                            required
                            class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                        >
                            <option value="">— Pilih Semester —</option>
                            @foreach ($semesterList as $semester)
                                <option value="{{ $semester->id }}" @selected(old('semester_id') == $semester->id || $semester->status_aktif)>{{ $semester->nama }} @if($semester->status_aktif) (Aktif) @endif</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('semester_id')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Jenis Asesmen *" />
                        <select 
                            name="jenis" 
                            required
                            class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                        >
                            <option value="">— Pilih Jenis Asesmen —</option>
                            @foreach ($jenisAsesmenList as $jenis)
                                <option value="{{ $jenis->value }}" @selected(old('jenis') == $jenis->value)>{{ $jenis->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('jenis')" class="mt-1" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <x-input-label value="Judul Kegiatan Asesmen *" />
                        <input 
                            type="text" 
                            name="judul" 
                            value="{{ old('judul') }}" 
                            required
                            placeholder="Contoh: Ulangan Harian Bab 1 / Projek Pesta Karya / PAS Genap" 
                            class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                        >
                        <x-input-error :messages="$errors->get('judul')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Tanggal Pelaksanaan *" />
                        <input 
                            type="date" 
                            name="tanggal" 
                            value="{{ old('tanggal', now()->toDateString()) }}" 
                            required
                            class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                        >
                        <x-input-error :messages="$errors->get('tanggal')" class="mt-1" />
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-6">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <x-input-label value="Hubungan Tujuan Pembelajaran (TP) / KKTP" />
                            <p class="text-xs text-gray-500">Pilih minimal satu indikator TP dari Kurikulum Merdeka yang diasesmen pada kegiatan ini. Nilai siswa dicatat per-TP.</p>
                        </div>
                    </div>

                    <x-input-error :messages="$errors->get('komponen_id')" class="mt-1" />

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

                <div class="flex items-center gap-3 pt-4 border-t border-gray-100">
                    <x-primary-button type="submit">
                        Buat Asesmen &amp; Lanjut Input Nilai
                    </x-primary-button>
                    <a href="{{ route('guru.asesmen.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                        Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
