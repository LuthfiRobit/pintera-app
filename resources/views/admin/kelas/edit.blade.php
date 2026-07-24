<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-2xl font-semibold text-ink">Ubah Kelas</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <x-panel>
            <form method="POST" action="{{ route('admin.kelas.update', $kelas) }}" class="space-y-4 p-6">
                @csrf
                @method('PUT')

                <div>
                    <label class="text-sm font-medium text-ink">Tahun Ajaran</label>
                    <select name="tahun_ajaran_id" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        @foreach ($tahunAjaranList as $tahunAjaran)
                            <option value="{{ $tahunAjaran->id }}" @selected(old('tahun_ajaran_id', $kelas->tahun_ajaran_id) == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
                        @endforeach
                    </select>
                    @error('tahun_ajaran_id')
                        <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-ink">Nama Kelas</label>
                    <input type="text" name="nama" value="{{ old('nama', $kelas->nama) }}" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                    @error('nama')
                        <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-ink">Tingkat (opsional)</label>
                    <input type="text" name="tingkat" value="{{ old('tingkat', $kelas->tingkat) }}" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                    @error('tingkat')
                        <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-ink">Wali Kelas (opsional)</label>
                    <select name="wali_kelas_guru_id" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        <option value="">— Belum ditentukan —</option>
                        @foreach ($guruList as $guru)
                            <option value="{{ $guru->id }}" @selected(old('wali_kelas_guru_id', $kelas->wali_kelas_guru_id) == $guru->id)>{{ $guru->nama }}</option>
                        @endforeach
                    </select>
                    @error('wali_kelas_guru_id')
                        <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="rounded-xl bg-ink px-4 py-2 text-sm font-medium text-paper transition hover:bg-ink/90">Simpan Perubahan</button>
            </form>
        </x-panel>
    </div>
</x-app-layout>
