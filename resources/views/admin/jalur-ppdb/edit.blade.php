<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Jalur: {{ $jalur->nama }}</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.jalur-ppdb.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Jalur PPDB</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Edit</b>
            </p>
        </div>

        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700">{{ $errors->first() }}</div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
                <p class="font-display text-sm font-bold text-gray-900">Kelengkapan</p>
                <div class="flex flex-wrap gap-2">
                    <x-badge :tone="$jalur->formulirField->count() > 0 ? 'brass' : 'slate'">Formulir ({{ $jalur->formulirField->count() }})</x-badge>
                    <x-badge :tone="$jalur->dokumenSyarat->count() > 0 ? 'brass' : 'slate'">Dokumen ({{ $jalur->dokumenSyarat->count() }})</x-badge>
                    <x-badge :tone="$jalur->seleksi->count() > 0 ? 'brass' : 'slate'">Seleksi ({{ $jalur->seleksi->count() }})</x-badge>
                    <x-badge :tone="$gelombangPemakai->isNotEmpty() ? 'brass' : 'slate'">Gelombang ({{ $gelombangPemakai->count() }})</x-badge>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.jalur-ppdb.update', $jalur) }}" class="p-5">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-input-label value="Nama Jalur" />
                        <x-text-input type="text" name="nama" value="{{ old('nama', $jalur->nama) }}" placeholder="Contoh: Reguler, Prestasi, Afirmasi" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
                    </div>

                    <div class="sm:col-span-2">
                        <x-input-label value="Deskripsi (Opsional)" />
                        <textarea name="deskripsi" rows="3" placeholder="Jelaskan kriteria atau ketentuan jalur ini" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm focus:border-brand-500 focus:ring-brand-500">{{ old('deskripsi', $jalur->deskripsi) }}</textarea>
                        <x-input-error :messages="$errors->get('deskripsi')" class="mt-1.5" />
                    </div>

                    <div class="sm:col-span-2">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="hidden" name="status_aktif" value="0">
                            <input type="checkbox" name="status_aktif" value="1" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500" @checked(old('status_aktif', $jalur->status_aktif))>
                            Jalur aktif (bisa dipilih calon murid saat portal pendaftaran dibuka)
                        </label>
                        <p class="mt-1.5 text-xs text-gray-500">
                            @if ($gelombangPemakai->isNotEmpty())
                                Dipakai di gelombang: {{ $gelombangPemakai->implode(', ') }}. Jalur tidak bisa dinonaktifkan selama masih dipakai.
                            @else
                                Tidak dipakai di gelombang manapun saat ini.
                            @endif
                        </p>
                        <x-input-error :messages="$errors->get('status_aktif')" class="mt-1.5" />
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <x-primary-button type="submit">Simpan Perubahan</x-primary-button>
                </div>
            </form>
        </div>

        @include('admin.jalur-ppdb.partials.formulir-field')
        @include('admin.jalur-ppdb.partials.dokumen-syarat')
        @include('admin.jalur-ppdb.partials.seleksi')
    </div>
</x-app-layout>
