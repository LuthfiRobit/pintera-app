<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-2xl font-semibold text-ink">Import Siswa (Excel)</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <x-panel>
            <div class="space-y-4 p-6">
                <p class="text-sm text-ink/70">
                    Kolom yang wajib ada pada baris pertama file: <code>nis</code>, <code>nisn</code>, <code>nama_lengkap</code>,
                    <code>jenis_kelamin</code> (L/P), <code>tempat_lahir</code>, <code>tanggal_lahir</code> (YYYY-MM-DD), <code>agama</code>, <code>kelas</code>
                    (harus persis sama dengan nama Kelas yang sudah ada).
                </p>

                <form method="POST" action="{{ route('admin.siswa.import.preview') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <input type="file" name="file" accept=".xlsx,.xls,.csv" class="block w-full text-sm text-ink" required>
                    @error('file')
                        <p class="text-sm text-signal-red">{{ $message }}</p>
                    @enderror
                    <button type="submit" class="rounded-xl bg-ink px-4 py-2 text-sm font-medium text-paper transition hover:bg-ink/90">Unggah &amp; Pratinjau</button>
                </form>
            </div>
        </x-panel>
    </div>
</x-app-layout>
