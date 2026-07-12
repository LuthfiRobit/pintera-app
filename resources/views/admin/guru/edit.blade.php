<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display font-semibold text-xl text-ink leading-tight">Edit Data Guru: {{ $guru->nama }}</h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto sm:px-6 lg:px-8">
        <form method="POST" action="{{ route('admin.guru.update', $guru) }}" class="bg-white shadow rounded p-6 space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block font-medium text-ink">NIK</label>
                <input type="text" name="nik" value="{{ old('nik', $guru->nik) }}" class="w-full border border-slate/30 rounded p-2">
                @error('nik') <p class="text-signal-red text-sm">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block font-medium text-ink">Nama</label>
                <input type="text" name="nama" value="{{ old('nama', $guru->nama) }}" class="w-full border border-slate/30 rounded p-2">
                @error('nama') <p class="text-signal-red text-sm">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block font-medium text-ink">Jenis Kelamin</label>
                <select name="jenis_kelamin" class="w-full border border-slate/30 rounded p-2">
                    <option value="L" @selected(old('jenis_kelamin', $guru->jenis_kelamin) === 'L')>Laki-laki</option>
                    <option value="P" @selected(old('jenis_kelamin', $guru->jenis_kelamin) === 'P')>Perempuan</option>
                </select>
            </div>

            <div>
                <label class="block font-medium text-ink">Jenis PTK</label>
                <select name="jenis_ptk" class="w-full border border-slate/30 rounded p-2">
                    @foreach (['guru_kelas','guru_mapel','kepala_sekolah','tenaga_administrasi'] as $jenis)
                        <option value="{{ $jenis }}" @selected(old('jenis_ptk', $guru->jenis_ptk) === $jenis)>{{ $jenis }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block font-medium text-ink">Status Kepegawaian</label>
                <select name="status_kepegawaian" class="w-full border border-slate/30 rounded p-2">
                    @foreach (['PNS','PPPK','GTY','PTY','Honorer'] as $status)
                        <option value="{{ $status }}" @selected(old('status_kepegawaian', $guru->status_kepegawaian) === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="px-4 py-2 bg-ink text-white rounded">Simpan</button>
        </form>
    </div>
</x-app-layout>
