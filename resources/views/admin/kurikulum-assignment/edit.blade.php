<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4">
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Edit Aturan Kurikulum</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.kurikulum-assignment.index') }}" class="hover:text-gray-700">Pengaturan Kurikulum</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span>
                <b class="font-semibold text-gray-700">Edit Aturan</b>
            </p>
        </div>
        <a href="{{ route('admin.kurikulum-assignment.resync') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 hover:text-blue-700">
            <x-icon name="sync" class="h-4 w-4" />
            Sinkronisasi Kurikulum Kelas
        </a>

        <form method="POST" action="{{ route('admin.kurikulum-assignment.update', $assignment) }}">
            @csrf
            @method('PUT')
            @include('admin.kurikulum-assignment._form', ['assignment' => $assignment, 'kurikulumList' => $kurikulumList, 'bentukPendidikanList' => $bentukPendidikanList, 'isPlatform' => $isPlatform, 'tingkatOptionsByBentuk' => $tingkatOptionsByBentuk, 'submitText' => 'Simpan Perubahan'])
        </form>
    </div>
</x-app-layout>
