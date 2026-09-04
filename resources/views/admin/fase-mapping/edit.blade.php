<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4">
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <h1 class="font-display text-lg font-bold text-gray-900">Edit Mapping Default Fase</h1>

        <form method="POST" action="{{ route('admin.fase-mapping.update', $mapping) }}">
            @csrf
            @method('PUT')
            @include('admin.fase-mapping._form', ['mapping' => $mapping, 'faseList' => $faseList, 'isPlatform' => $isPlatform, 'bentukPendidikanList' => $bentukPendidikanList, 'submitText' => 'Simpan Perubahan'])
        </form>
    </div>
</x-app-layout>
