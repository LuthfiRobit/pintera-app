<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Edit Siswa: {{ $siswa->nama_lengkap }}</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.siswa.index') }}" class="font-semibold text-gray-700 transition-colors duration-200 hover:text-brand-600">Siswa</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Edit</b>
            </p>
        </div>

        @if ($siswa->user)
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <p class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="lock" class="h-[15px] w-[15px] text-gray-400" />
                    Info Akun Login
                </p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold text-gray-500">Username</p>
                        <p class="mt-1 font-mono text-sm text-gray-900">{{ $siswa->user->username }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500">Status Akun</p>
                        <x-badge tone="{{ $siswa->user->is_active ? 'green' : 'amber' }}">{{ $siswa->user->is_active ? 'Aktif' : 'Non-aktif' }}</x-badge>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500">Password awal sama dengan NIS. Siswa wajib menggantinya saat login pertama.</p>
            </div>
        @endif

        @include('admin.siswa._orang_tua', ['siswa' => $siswa])

        <form method="POST" action="{{ route('admin.siswa.update', $siswa) }}">
            @csrf
            @method('PUT')

            @include('admin.siswa._form', ['siswa' => $siswa, 'kelasList' => $kelasList, 'submitText' => 'Simpan Perubahan'])
        </form>
    </div>
</x-app-layout>

