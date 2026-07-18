<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Tambah Jalur &mdash; {{ $tahunAjaranAktif->nama }}</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.jalur-ppdb.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Jalur PPDB</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Tambah</b>
            </p>
        </div>

        <form method="POST" action="{{ route('admin.jalur-ppdb.store') }}">
            @csrf

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="signpost" class="h-[15px] w-[15px] text-gray-400" />
                    Detail Jalur
                </p>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-input-label value="Nama Jalur" />
                        <x-text-input type="text" name="nama" value="{{ old('nama') }}" placeholder="Contoh: Reguler, Prestasi, Afirmasi" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
                    </div>

                    <div class="sm:col-span-2">
                        <x-input-label value="Deskripsi (Opsional)" />
                        <textarea name="deskripsi" rows="3" placeholder="Jelaskan kriteria atau ketentuan jalur ini" class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm focus:border-brand-500 focus:ring-brand-500">{{ old('deskripsi') }}</textarea>
                        <x-input-error :messages="$errors->get('deskripsi')" class="mt-1.5" />
                    </div>
                </div>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <x-primary-button type="submit">Simpan &amp; Lanjutkan</x-primary-button>
                <a href="{{ route('admin.jalur-ppdb.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Batal</a>
            </div>
        </form>
    </div>
</x-app-layout>
