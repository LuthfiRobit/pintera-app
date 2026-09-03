<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4">
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex items-center justify-between">
            <h1 class="font-display text-lg font-bold text-gray-900">Edit Assignment Kurikulum</h1>
            <a href="{{ route('admin.kurikulum-assignment.resync') }}" class="text-sm font-medium text-blue-600 hover:text-blue-700">
                Cek &amp; Perbaiki Kurikulum/Fase Kelas
            </a>
        </div>

        <form method="POST" action="{{ route('admin.kurikulum-assignment.update', $assignment) }}">
            @csrf
            @method('PUT')
            @include('admin.kurikulum-assignment._form', ['assignment' => $assignment, 'kurikulumList' => $kurikulumList, 'bentukPendidikanList' => $bentukPendidikanList, 'isPlatformOrYayasan' => false, 'submitText' => 'Simpan Perubahan'])
        </form>
    </div>
</x-app-layout>
