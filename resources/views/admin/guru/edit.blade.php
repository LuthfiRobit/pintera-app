<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Data Induk</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Edit Data Guru: {{ $guru->nama }}</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <x-panel>
            <form method="POST" action="{{ route('admin.guru.update', $guru) }}" class="space-y-5 p-6">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label value="NIK" />
                    <x-text-input type="text" name="nik" value="{{ old('nik', $guru->nik) }}" class="mt-1.5 font-mono" />
                    <x-input-error :messages="$errors->get('nik')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label value="Nama" />
                    <x-text-input type="text" name="nama" value="{{ old('nama', $guru->nama) }}" class="mt-1.5" />
                    <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label value="Jenis Kelamin" />
                        <select name="jenis_kelamin" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                            <option value="L" @selected(old('jenis_kelamin', $guru->jenis_kelamin) === 'L')>Laki-laki</option>
                            <option value="P" @selected(old('jenis_kelamin', $guru->jenis_kelamin) === 'P')>Perempuan</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label value="Jenis PTK" />
                        <select name="jenis_ptk" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                            @foreach (['guru_kelas','guru_mapel','kepala_sekolah','tenaga_administrasi'] as $jenis)
                                <option value="{{ $jenis }}" @selected(old('jenis_ptk', $guru->jenis_ptk) === $jenis)>{{ $jenis }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <x-input-label value="Status Kepegawaian" />
                    <select name="status_kepegawaian" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        @foreach (['PNS','PPPK','GTY','PTY','Honorer'] as $status)
                            <option value="{{ $status }}" @selected(old('status_kepegawaian', $guru->status_kepegawaian) === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-primary-button>Simpan</x-primary-button>
                    <a href="{{ route('admin.guru.index') }}" class="text-sm text-slate hover:text-ink">Batal</a>
                </div>
            </form>
        </x-panel>
    </div>
</x-app-layout>
