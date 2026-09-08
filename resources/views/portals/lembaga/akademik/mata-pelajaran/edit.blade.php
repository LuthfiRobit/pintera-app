<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="font-display text-lg font-bold text-gray-900">Edit Mata Pelajaran: {{ $mataPelajaran->nama }}</h1>
                @if ($isYayasan ?? false)
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
                        <x-icon name="apartment" class="h-3.5 w-3.5" />
                        {{ $mataPelajaran->lembaga->nama }}
                    </span>
                @endif
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.mata-pelajaran.index') }}" class="font-semibold text-gray-700 transition-colors duration-200 hover:text-brand-600">Mata Pelajaran</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Edit</b>
            </p>
        </div>

        <form method="POST" action="{{ route('admin.mata-pelajaran.update', $mataPelajaran) }}">
            @csrf
            @method('PUT')

            @include('portals.lembaga.akademik.mata-pelajaran._form', ['mataPelajaran' => $mataPelajaran, 'submitText' => 'Simpan Perubahan'])
        </form>
    </div>
</x-app-layout>

