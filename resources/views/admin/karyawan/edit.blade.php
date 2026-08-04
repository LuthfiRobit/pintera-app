{{-- resources/views/admin/karyawan/edit.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data x-init="$store.toast.push('success', @js(session('status')))">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Edit Karyawan: {{ $karyawan->nama }}</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.karyawan.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Karyawan</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Edit</b>
            </p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <p class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-700">
                <x-icon name="lock" class="h-[15px] w-[15px] text-gray-400" />
                Info Akun Login
            </p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold text-gray-500">Username</p>
                    <p class="mt-1 font-mono text-sm text-gray-900">{{ $karyawan->user->username }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500">Penempatan</p>
                    <p class="mt-1 text-sm text-gray-900">{{ $karyawan->lembaga?->nama ?? 'Pool Yayasan (' . $karyawan->yayasan->nama . ')' }}</p>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.karyawan.update', $karyawan) }}" x-data="karyawanForm({ canCreatePool: false })">
            @csrf
            @method('PUT')
            @include('admin.karyawan._form', [
                'karyawan' => $karyawan,
                'jenisKaryawanList' => $jenisKaryawanList,
                'yayasanList' => collect(),
                'canCreatePool' => false,
            ])

            <div class="mt-4 flex items-center gap-3">
                <x-primary-button type="submit">Simpan Perubahan</x-primary-button>
                <a href="{{ route('admin.karyawan.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Batal</a>
            </div>
        </form>
    </div>
</x-app-layout>
