<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4">
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center gap-2.5">
            <h1 class="font-display text-lg font-bold text-gray-900">Tambah Assignment Kurikulum</h1>
            @if (! ($isPlatform ?? false))
                <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
                    <x-icon name="apartment" class="h-3.5 w-3.5" />
                    {{ $activeLembaga->nama }}
                </span>
            @endif
        </div>

        <form method="POST" action="{{ route('admin.kurikulum-assignment.store') }}">
            @csrf
            @include('admin.kurikulum-assignment._form', ['kurikulumList' => $kurikulumList, 'bentukPendidikanList' => $bentukPendidikanList, 'tahunAjaranList' => $tahunAjaranList, 'lembagaList' => $lembagaList, 'isPlatform' => $isPlatform, 'submitText' => 'Simpan Assignment'])
        </form>
    </div>
</x-app-layout>
