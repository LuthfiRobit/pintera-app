<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Tambah Pola Jam</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.pola-jam.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Pola Jam</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Tambah</b>
            </p>
        </div>

        <form method="POST" action="{{ route('admin.pola-jam.store') }}">
            @csrf

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="schedule" class="h-[15px] w-[15px] text-gray-400" />
                    Identitas Pola Jam
                </p>

                <div class="max-w-xl">
                    <x-input-label value="Nama Pola Jam" />
                    <x-text-input type="text" name="nama" value="{{ old('nama') }}" placeholder="Contoh: Kelas Rendah 1-3" class="mt-1.5" />
                    <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
                </div>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <x-primary-button type="submit">Simpan Pola Jam</x-primary-button>
                <a href="{{ route('admin.pola-jam.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">Batal</a>
            </div>
        </form>
    </div>
</x-app-layout>
