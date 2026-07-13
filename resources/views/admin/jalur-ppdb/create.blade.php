<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">SPMB</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Tambah Jalur — {{ $tahunAjaranAktif->nama }}</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <x-panel>
            <form method="POST" action="{{ route('admin.jalur-ppdb.store') }}" class="space-y-5 p-6">
                @csrf

                <div>
                    <x-input-label value="Nama Jalur" />
                    <x-text-input type="text" name="nama" value="{{ old('nama') }}" placeholder="mis. Reguler, Prestasi, Afirmasi" class="mt-1.5" />
                    <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label value="Deskripsi (opsional)" />
                    <textarea name="deskripsi" rows="3" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">{{ old('deskripsi') }}</textarea>
                    <x-input-error :messages="$errors->get('deskripsi')" class="mt-1.5" />
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-primary-button>Simpan &amp; Lanjutkan</x-primary-button>
                    <a href="{{ route('admin.jalur-ppdb.index') }}" class="text-sm text-slate hover:text-ink">Batal</a>
                </div>
            </form>
        </x-panel>
    </div>
</x-app-layout>
