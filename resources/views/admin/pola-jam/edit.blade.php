<x-app-layout>
    <div class="mx-auto max-w-2xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Edit Pola Jam</h1>
            <p class="text-sm text-gray-500">
                <a href="{{ route('admin.pola-jam.index') }}" class="text-gray-500 hover:text-gray-700">Pola Jam</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Edit</b>
            </p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <form method="POST" action="{{ route('admin.pola-jam.update', $polaJam) }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label value="Nama Pola" />
                    <x-text-input type="text" name="nama" value="{{ old('nama', $polaJam->nama) }}" placeholder="Kelas Rendah 1-3" class="mt-1.5" />
                    <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
                </div>

                <x-primary-button type="submit">Simpan</x-primary-button>
            </form>
        </div>
    </div>
</x-app-layout>
