<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4">
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <h1 class="font-display text-lg font-bold text-gray-900">Tambah Assignment Kurikulum</h1>

        <form method="POST" action="{{ route('admin.kurikulum-assignment.store') }}">
            @csrf
            @include('admin.kurikulum-assignment._form', ['kurikulumList' => $kurikulumList, 'bentukPendidikanList' => $bentukPendidikanList, 'tahunAjaranList' => $tahunAjaranList, 'lembagaList' => $lembagaList, 'isPlatform' => $isPlatform, 'submitText' => 'Simpan Assignment'])
        </form>
    </div>
</x-app-layout>
