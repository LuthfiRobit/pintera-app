<x-app-layout>
    <div class="mx-auto max-w-2xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Import Siswa (Excel)</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.siswa.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Siswa</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Import</b>
            </p>
        </div>

        <div class="rounded-2xl border border-brand-100 bg-brand-50 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-gray-900">Gunakan template resmi</p>
                    <p class="mt-0.5 text-sm text-gray-600">
                        Unduh template Excel ini dan isi datanya — kolom, urutan, dan format tanggal sudah sesuai supaya file Anda tidak ditolak saat diperiksa.
                    </p>
                </div>
                <x-link-button variant="ghost" href="{{ route('admin.siswa.import.template') }}" class="shrink-0 bg-white">
                    <x-icon name="description" class="h-4 w-4" /> Download Template Excel
                </x-link-button>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <p class="text-sm text-gray-600">
                Kolom yang wajib ada pada baris pertama file: <code class="rounded bg-gray-100 px-1 py-0.5 text-xs">nis</code>,
                <code class="rounded bg-gray-100 px-1 py-0.5 text-xs">nisn</code>,
                <code class="rounded bg-gray-100 px-1 py-0.5 text-xs">nama_lengkap</code>,
                <code class="rounded bg-gray-100 px-1 py-0.5 text-xs">jenis_kelamin</code> (L/P),
                <code class="rounded bg-gray-100 px-1 py-0.5 text-xs">tempat_lahir</code>,
                <code class="rounded bg-gray-100 px-1 py-0.5 text-xs">tanggal_lahir</code> (YYYY-MM-DD),
                <code class="rounded bg-gray-100 px-1 py-0.5 text-xs">agama</code>,
                <code class="rounded bg-gray-100 px-1 py-0.5 text-xs">kelas</code>
                (harus persis sama dengan nama Kelas yang sudah ada).
            </p>

            <form method="POST" action="{{ route('admin.siswa.import.preview') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <div>
                    <x-input-label value="File Excel/CSV" />
                    <input type="file" name="file" accept=".xlsx,.xls,.csv" class="mt-1.5 block w-full text-sm text-gray-700" required>
                    <x-input-error :messages="$errors->get('file')" class="mt-1.5" />
                </div>
                <x-primary-button type="submit">Unggah &amp; Pratinjau</x-primary-button>
            </form>
        </div>
    </div>
</x-app-layout>
