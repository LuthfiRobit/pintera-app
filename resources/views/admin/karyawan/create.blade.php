<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-6">
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm font-medium text-error-700 shadow-sm" x-data x-init="$store.toast ? $store.toast.push('error', @js($errors->first())) : null">Terdapat kesalahan pengisian data, silakan periksa kembali formulir di bawah.</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm text-gray-500">
                    Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                    <a href="{{ route('admin.karyawan.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Karyawan</a>
                    <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-900">Tambah Data Karyawan</b>
                </p>
            </div>
            <a href="{{ route('admin.karyawan.index') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95">
                <x-icon name="arrow_back" class="h-4 w-4 text-gray-500" />
                <span>Kembali ke Daftar</span>
            </a>
        </div>

        <div class="rounded-2xl border border-gray-200/80 bg-white p-6 shadow-card md:p-8">
            <div class="mb-6 border-b border-gray-200 pb-4">
                <h2 class="font-display text-2xl font-bold tracking-tight text-gray-900">Tambah Karyawan Baru</h2>
                <p class="mt-1 text-sm text-gray-500">Lengkapi formulir di bawah untuk menambahkan data karyawan atau staf ke dalam sistem.</p>
            </div>

            <form method="POST" action="{{ route('admin.karyawan.store') }}" x-data="karyawanForm({ canCreatePool: @js($canCreatePool) })" class="space-y-6">
                @csrf
                @include('admin.karyawan._form', [
                    'karyawan' => null,
                    'jenisKaryawanList' => $jenisKaryawanList,
                    'yayasanList' => $yayasanList,
                    'canCreatePool' => $canCreatePool,
                ])

                <div class="flex items-center justify-end gap-3 rounded-2xl border border-gray-200 bg-gray-50/50 p-4 shadow-sm">
                    <a href="{{ route('admin.karyawan.index') }}" class="rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 active:scale-95">
                        Batal
                    </a>
                    <x-primary-button type="submit" class="!rounded-xl !px-6 !py-2.5">
                        Simpan Data Karyawan
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
