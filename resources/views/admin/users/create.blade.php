<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6">
        {{-- Flash Messages & Toast Integrations --}}
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm font-medium text-error-700 shadow-sm" x-data x-init="$store.toast ? $store.toast.push('error', @js($errors->first())) : null">Terdapat kesalahan pengisian data, silakan periksa kembali formulir di bawah.</div>
        @endif

        {{-- Top Navigation & Breadcrumbs --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm text-gray-500">
                    Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                    <a href="{{ route('admin.users.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Akses & Peran</a>
                    <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-900">Tambah Akun Pengguna</b>
                </p>
            </div>
            <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95">
                <x-icon name="arrow_back" class="h-4 w-4 text-gray-500" />
                <span>Kembali ke Daftar</span>
            </a>
        </div>

        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-6">
            @csrf

            {{-- Form Header --}}
            <div class="flex items-center justify-between rounded-2xl border border-brand-100 bg-brand-50/50 p-5">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-white shadow">
                        <x-icon name="person_add" class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900">Formulir Pembuatan Akun Baru</h3>
                        <p class="text-xs text-gray-500">Isi data dasar pengguna dan tentukan hak akses (Role) yang sesuai.</p>
                    </div>
                </div>
            </div>

            @include('admin.users._form', ['targetUser' => null])

            <div class="flex items-center justify-end gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-card">
                <a href="{{ route('admin.users.index') }}" class="rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 active:scale-95">
                    Batal
                </a>
                <x-primary-button type="submit" class="!rounded-xl !px-6 !py-2.5">
                    Buat Akun Pengguna
                </x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
