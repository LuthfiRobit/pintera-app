<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-2xl font-semibold text-ink">Ubah Siswa</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <x-panel>
            <form method="POST" action="{{ route('admin.siswa.update', $siswa) }}" class="space-y-4 p-6">
                @csrf
                @method('PUT')

                <div>
                    <label class="text-sm font-medium text-ink">Kelas (opsional)</label>
                    <select name="kelas_id" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        <option value="">— Belum ditempatkan —</option>
                        @foreach ($kelasList as $kelas)
                            <option value="{{ $kelas->id }}" @selected(old('kelas_id', $siswa->kelas_id) == $kelas->id)>{{ $kelas->nama }}</option>
                        @endforeach
                    </select>
                    @error('kelas_id')
                        <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-ink">NIS</label>
                        <input type="text" name="nis" value="{{ old('nis', $siswa->nis) }}" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        @error('nis')
                            <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium text-ink">NISN</label>
                        <input type="text" name="nisn" value="{{ old('nisn', $siswa->nisn) }}" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        @error('nisn')
                            <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium text-ink">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" value="{{ old('nama_lengkap', $siswa->nama_lengkap) }}" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                    @error('nama_lengkap')
                        <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-ink">Jenis Kelamin</label>
                        <select name="jenis_kelamin" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                            <option value="L" @selected(old('jenis_kelamin', $siswa->jenis_kelamin) === 'L')>Laki-laki</option>
                            <option value="P" @selected(old('jenis_kelamin', $siswa->jenis_kelamin) === 'P')>Perempuan</option>
                        </select>
                        @error('jenis_kelamin')
                            <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium text-ink">Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" value="{{ old('tanggal_lahir', optional($siswa->tanggal_lahir)->format('Y-m-d')) }}" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        @error('tanggal_lahir')
                            <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-ink">Tempat Lahir</label>
                        <input type="text" name="tempat_lahir" value="{{ old('tempat_lahir', $siswa->tempat_lahir) }}" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        @error('tempat_lahir')
                            <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-sm font-medium text-ink">Agama</label>
                        <input type="text" name="agama" value="{{ old('agama', $siswa->agama) }}" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        @error('agama')
                            <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <button type="submit" class="rounded-xl bg-ink px-4 py-2 text-sm font-medium text-paper transition hover:bg-ink/90">Simpan Perubahan</button>
            </form>
        </x-panel>
    </div>
</x-app-layout>
