<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6">
        {{-- Flash Messages & Toast Integrations --}}
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm font-medium text-success-700 shadow-sm" x-data x-init="$store.toast ? $store.toast.push('success', @js(session('status'))) : null">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm font-medium text-error-700 shadow-sm" x-data x-init="$store.toast ? $store.toast.push('error', @js($errors->first())) : null">Terdapat kesalahan pengisian data, silakan periksa kembali formulir di bawah.</div>
        @endif

        {{-- Top Navigation & Breadcrumbs --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm text-gray-500">
                    Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                    <a href="{{ route('admin.siswa.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Siswa</a>
                    <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-900">Tambah Baru</b>
                </p>
            </div>
            <a href="{{ route('admin.siswa.index') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95">
                <x-icon name="arrow_back" class="h-4 w-4 text-gray-500" />
                <span>Kembali ke Daftar</span>
            </a>
        </div>

        <form method="POST" action="{{ route('admin.siswa.store') }}" class="space-y-6">
            @csrf

            {{-- Form Header --}}
            <div class="flex items-center justify-between rounded-2xl border border-brand-100 bg-brand-50/50 p-5">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-white shadow">
                        <x-icon name="add" class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900">Formulir Pendaftaran Siswa Baru</h3>
                        <p class="text-xs text-gray-500">Masukkan identitas lengkap. Akun login akan otomatis digenerate.</p>
                    </div>
                </div>
            </div>

            @include('admin.siswa._form', ['kelasList' => $kelasList, 'submitText' => 'Simpan Siswa'])
        </form>
    </div>
</x-app-layout>

