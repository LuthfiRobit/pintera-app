<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Tambah Data Orang Tua</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.orang-tua.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Orang Tua</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Tambah</b>
            </p>
        </div>

        <form method="POST" action="{{ route('admin.orang-tua.store') }}">
            @csrf
            @include('admin.orang-tua._form', ['orangTua' => null])

            <div class="mt-4 flex items-center gap-3">
                <x-primary-button type="submit">Simpan Data Orang Tua</x-primary-button>
                <a href="{{ route('admin.orang-tua.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Batal</a>
            </div>
        </form>
    </div>
</x-app-layout>
