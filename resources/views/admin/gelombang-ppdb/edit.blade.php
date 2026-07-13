<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">SPMB</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Edit Gelombang: {{ $gelombang->nama }}</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <x-panel>
            <form method="POST" action="{{ route('admin.gelombang-ppdb.update', $gelombang) }}" class="space-y-5 p-6">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label value="Nama Gelombang" />
                    <x-text-input type="text" name="nama" value="{{ old('nama', $gelombang->nama) }}" class="mt-1.5" />
                    <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label value="Tanggal Buka" />
                        <x-text-input type="date" name="tanggal_buka" value="{{ old('tanggal_buka', $gelombang->tanggal_buka->format('Y-m-d')) }}" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('tanggal_buka')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label value="Tanggal Tutup" />
                        <x-text-input type="date" name="tanggal_tutup" value="{{ old('tanggal_tutup', $gelombang->tanggal_tutup->format('Y-m-d')) }}" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('tanggal_tutup')" class="mt-1.5" />
                    </div>
                </div>

                <div>
                    <x-input-label value="Kuota" />
                    <x-text-input type="number" name="kuota" value="{{ old('kuota', $gelombang->kuota) }}" class="mt-1.5" />
                    <x-input-error :messages="$errors->get('kuota')" class="mt-1.5" />
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-primary-button>Simpan</x-primary-button>
                    <a href="{{ route('admin.gelombang-ppdb.index') }}" class="text-sm text-slate hover:text-ink">Batal</a>
                </div>
            </form>
        </x-panel>
    </div>
</x-app-layout>
