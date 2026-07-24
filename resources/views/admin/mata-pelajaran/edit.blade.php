<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Edit Mata Pelajaran: {{ $mataPelajaran->nama }}</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.mata-pelajaran.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Mata Pelajaran</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Edit</b>
            </p>
        </div>

        <form method="POST" action="{{ route('admin.mata-pelajaran.update', $mataPelajaran) }}">
            @csrf
            @method('PUT')

            @include('admin.mata-pelajaran._form', ['mataPelajaran' => $mataPelajaran])

            <div class="mt-4 flex items-center gap-3">
                <x-primary-button type="submit">Simpan Perubahan</x-primary-button>
                <a href="{{ route('admin.mata-pelajaran.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Batal</a>
            </div>
        </form>
    </div>
</x-app-layout>
