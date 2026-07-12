<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display font-semibold text-xl text-ink leading-tight">Edit Lembaga: {{ $lembaga->nama }}</h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto sm:px-6 lg:px-8">
        <form method="POST" action="{{ route('admin.lembaga.update', $lembaga) }}" class="bg-white shadow rounded p-6 space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="yayasan_id" value="{{ $lembaga->yayasan_id }}">

            <div>
                <label class="block font-medium text-ink">NPSN</label>
                <input type="text" name="npsn" value="{{ old('npsn', $lembaga->npsn) }}" class="w-full border border-slate/30 rounded p-2">
                @error('npsn') <p class="text-signal-red text-sm">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block font-medium text-ink">Nama Lembaga</label>
                <input type="text" name="nama" value="{{ old('nama', $lembaga->nama) }}" class="w-full border border-slate/30 rounded p-2">
                @error('nama') <p class="text-signal-red text-sm">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block font-medium text-ink">Bentuk Pendidikan</label>
                <select name="bentuk_pendidikan" class="w-full border border-slate/30 rounded p-2">
                    @foreach (['KB','TPA','SPS','TK','SD','SMP','SMA','SMK','SLB'] as $bentuk)
                        <option value="{{ $bentuk }}" @selected(old('bentuk_pendidikan', $lembaga->bentuk_pendidikan) === $bentuk)>{{ $bentuk }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block font-medium text-ink">Status Sekolah</label>
                <select name="status_sekolah" class="w-full border border-slate/30 rounded p-2">
                    <option value="negeri" @selected(old('status_sekolah', $lembaga->status_sekolah) === 'negeri')>Negeri</option>
                    <option value="swasta" @selected(old('status_sekolah', $lembaga->status_sekolah) === 'swasta')>Swasta</option>
                </select>
            </div>

            <div>
                <label class="block font-medium text-ink">Naungan</label>
                <select name="naungan" class="w-full border border-slate/30 rounded p-2">
                    <option value="kemendikdasmen" @selected(old('naungan', $lembaga->naungan) === 'kemendikdasmen')>Kemendikdasmen</option>
                    <option value="kemenag" @selected(old('naungan', $lembaga->naungan) === 'kemenag')>Kemenag</option>
                </select>
            </div>

            <div>
                <label class="block font-medium text-ink">Telepon</label>
                <input type="text" name="telepon" value="{{ old('telepon', $lembaga->telepon) }}" class="w-full border border-slate/30 rounded p-2">
            </div>

            <div>
                <label class="block font-medium text-ink">Email</label>
                <input type="email" name="email" value="{{ old('email', $lembaga->email) }}" class="w-full border border-slate/30 rounded p-2">
            </div>

            <button type="submit" class="px-4 py-2 bg-ink text-white rounded">Simpan</button>
        </form>
    </div>
</x-app-layout>
