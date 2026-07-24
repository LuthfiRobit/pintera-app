<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-2xl font-semibold text-ink">Ubah Mata Pelajaran</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <x-panel>
            <form method="POST" action="{{ route('admin.mata-pelajaran.update', $mataPelajaran) }}" class="space-y-4 p-6">
                @csrf
                @method('PUT')

                <div>
                    <label class="text-sm font-medium text-ink">Nama</label>
                    <input type="text" name="nama" value="{{ old('nama', $mataPelajaran->nama) }}" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                    @error('nama')
                        <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-ink">Tipe</label>
                    <select name="tipe" class="mt-1 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">
                        <option value="mapel" @selected(old('tipe', $mataPelajaran->tipe->value) === 'mapel')>Mata Pelajaran</option>
                        <option value="aspek_perkembangan" @selected(old('tipe', $mataPelajaran->tipe->value) === 'aspek_perkembangan')>Aspek Perkembangan (PAUD)</option>
                    </select>
                    @error('tipe')
                        <p class="mt-1 text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="rounded-xl bg-ink px-4 py-2 text-sm font-medium text-paper transition hover:bg-ink/90">Simpan Perubahan</button>
            </form>
        </x-panel>
    </div>
</x-app-layout>
