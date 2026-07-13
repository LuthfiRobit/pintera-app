<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">SPMB</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Jalur: {{ $jalur->nama }}</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status'))
            <div class="rounded-xl bg-signal-green/10 p-4 text-sm text-signal-green">{{ session('status') }}</div>
        @endif
        @error('seleksi')
            <div class="rounded-xl bg-signal-red/10 p-4 text-sm text-signal-red">{{ $message }}</div>
        @enderror

        <x-panel>
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-ink/10 px-6 py-4">
                <h3 class="font-display font-semibold text-ink">Kelengkapan</h3>
                <div class="flex flex-wrap gap-2">
                    <x-badge :tone="$jalur->formulirField->count() > 0 ? 'brass' : 'slate'">Formulir ({{ $jalur->formulirField->count() }})</x-badge>
                    <x-badge :tone="$jalur->dokumenSyarat->count() > 0 ? 'brass' : 'slate'">Dokumen ({{ $jalur->dokumenSyarat->count() }})</x-badge>
                    <x-badge :tone="$jalur->seleksi->count() > 0 ? 'brass' : 'slate'">Seleksi ({{ $jalur->seleksi->count() }})</x-badge>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.jalur-ppdb.update', $jalur) }}" class="space-y-5 p-6">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label value="Nama Jalur" />
                    <x-text-input type="text" name="nama" value="{{ old('nama', $jalur->nama) }}" class="mt-1.5" />
                    <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label value="Deskripsi" />
                    <textarea name="deskripsi" rows="3" class="mt-1.5 w-full rounded-xl border-ink/15 text-sm text-ink shadow-sm focus:border-brass focus:ring-brass">{{ old('deskripsi', $jalur->deskripsi) }}</textarea>
                </div>

                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="hidden" name="status_aktif" value="0">
                    <input type="checkbox" name="status_aktif" value="1" class="rounded border-ink/25 text-brass focus:ring-brass" @checked($jalur->status_aktif)>
                    Jalur aktif (bisa dipilih calon murid saat portal pendaftaran dibuka)
                </label>

                <x-primary-button>Simpan Perubahan</x-primary-button>
            </form>
        </x-panel>

        @include('admin.jalur-ppdb.partials.formulir-field')
        @include('admin.jalur-ppdb.partials.dokumen-syarat')
        @include('admin.jalur-ppdb.partials.seleksi')
    </div>
</x-app-layout>
