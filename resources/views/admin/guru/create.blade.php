<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display font-semibold text-xl text-ink leading-tight">Tambah Data Guru</h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto sm:px-6 lg:px-8">
        <form method="POST" action="{{ route('admin.guru.store') }}" class="bg-white shadow rounded p-6 space-y-4">
            @csrf

            <div>
                <label class="block font-medium text-ink">Akun User (harus sudah punya role guru)</label>
                <select name="user_id" class="w-full border border-slate/30 rounded p-2">
                    @foreach ($eligibleUsers as $eligibleUser)
                        <option value="{{ $eligibleUser->id }}">{{ $eligibleUser->name }} ({{ $eligibleUser->email }})</option>
                    @endforeach
                </select>
                @error('user_id') <p class="text-signal-red text-sm">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block font-medium text-ink">NIK</label>
                <input type="text" name="nik" value="{{ old('nik') }}" class="w-full border border-slate/30 rounded p-2">
                @error('nik') <p class="text-signal-red text-sm">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block font-medium text-ink">Nama</label>
                <input type="text" name="nama" value="{{ old('nama') }}" class="w-full border border-slate/30 rounded p-2">
                @error('nama') <p class="text-signal-red text-sm">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block font-medium text-ink">Jenis Kelamin</label>
                <select name="jenis_kelamin" class="w-full border border-slate/30 rounded p-2">
                    <option value="L">Laki-laki</option>
                    <option value="P">Perempuan</option>
                </select>
            </div>

            <div>
                <label class="block font-medium text-ink">Jenis PTK</label>
                <select name="jenis_ptk" class="w-full border border-slate/30 rounded p-2">
                    <option value="guru_kelas">Guru Kelas</option>
                    <option value="guru_mapel">Guru Mapel</option>
                    <option value="kepala_sekolah">Kepala Sekolah</option>
                    <option value="tenaga_administrasi">Tenaga Administrasi</option>
                </select>
            </div>

            <div>
                <label class="block font-medium text-ink">Status Kepegawaian</label>
                <select name="status_kepegawaian" class="w-full border border-slate/30 rounded p-2">
                    <option value="PNS">PNS</option>
                    <option value="PPPK">PPPK</option>
                    <option value="GTY">GTY</option>
                    <option value="PTY">PTY</option>
                    <option value="Honorer">Honorer</option>
                </select>
            </div>

            <button type="submit" class="px-4 py-2 bg-ink text-white rounded">Simpan</button>
        </form>
    </div>
</x-app-layout>
