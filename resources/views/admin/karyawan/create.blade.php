<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Tambah Data Karyawan</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.karyawan.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Karyawan</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Tambah</b>
            </p>
        </div>

        <form method="POST" action="{{ route('admin.karyawan.store') }}" x-data="karyawanForm({ canCreatePool: @js($canCreatePool) })">
            @csrf
            @include('admin.karyawan._form', [
                'karyawan' => null,
                'jenisKaryawanList' => $jenisKaryawanList,
                'yayasanList' => $yayasanList,
                'canCreatePool' => $canCreatePool,
            ])

            <div class="mt-4 flex items-center gap-3">
                <x-primary-button type="submit">Simpan Data Karyawan</x-primary-button>
                <a href="{{ route('admin.karyawan.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Batal</a>
            </div>
        </form>
    </div>
</x-app-layout>
