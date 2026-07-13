<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">SPMB</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Jenis Tes</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl bg-signal-green/10 p-4 text-sm text-signal-green">{{ session('status') }}</div>
        @endif
        @error('jenis_tes')
            <div class="rounded-xl bg-signal-red/10 p-4 text-sm text-signal-red">{{ $message }}</div>
        @enderror

        <x-panel>
            <ul class="divide-y divide-ink/10 px-6">
                @forelse ($jenisTesList as $jenisTes)
                    <li class="flex items-center justify-between py-3">
                        <div>
                            <p class="text-sm font-medium text-ink">{{ $jenisTes->nama }}</p>
                            @if ($jenisTes->deskripsi)
                                <p class="text-xs text-slate">{{ $jenisTes->deskripsi }}</p>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('admin.jenis-tes.destroy', $jenisTes) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm font-medium text-signal-red hover:text-signal-red/80">Hapus</button>
                        </form>
                    </li>
                @empty
                    <li class="py-6 text-center text-sm text-slate">Belum ada jenis tes. Tambahkan lewat form di bawah.</li>
                @endforelse
            </ul>

            <form method="POST" action="{{ route('admin.jenis-tes.store') }}" class="flex flex-wrap items-end gap-2 border-t border-ink/10 bg-paper/50 px-6 py-4">
                @csrf
                <div class="flex-1">
                    <x-input-label value="Nama Jenis Tes" />
                    <x-text-input type="text" name="nama" value="{{ old('nama') }}" placeholder="mis. Tes Tulis, Wawancara" class="mt-1.5" />
                    <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
                </div>
                <div class="flex-1">
                    <x-input-label value="Deskripsi (opsional)" />
                    <x-text-input type="text" name="deskripsi" value="{{ old('deskripsi') }}" class="mt-1.5" />
                    <x-input-error :messages="$errors->get('deskripsi')" class="mt-1.5" />
                </div>
                <x-primary-button>Tambah</x-primary-button>
            </form>
        </x-panel>
    </div>
</x-app-layout>
