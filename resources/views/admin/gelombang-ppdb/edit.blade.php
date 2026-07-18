<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-lg font-bold text-gray-900">Edit Gelombang: {{ $gelombang->nama }}</h1>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.gelombang-ppdb.index') }}" class="font-semibold text-gray-700 hover:text-brand-600">Gelombang PPDB</a>
                <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Edit</b>
            </p>
        </div>

        <form method="POST" action="{{ route('admin.gelombang-ppdb.update', $gelombang) }}">
            @csrf
            @method('PUT')

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="waves" class="h-[15px] w-[15px] text-gray-400" />
                    Detail Gelombang
                </p>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="sm:col-span-2 lg:col-span-3">
                        <x-input-label value="Nama Gelombang" />
                        <x-text-input type="text" name="nama" value="{{ old('nama', $gelombang->nama) }}" placeholder="Contoh: Gelombang 1" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('nama')" class="mt-1.5" />
                    </div>

                    <div>
                        <x-input-label value="Tanggal Buka" />
                        <x-text-input type="date" name="tanggal_buka" value="{{ old('tanggal_buka', $gelombang->tanggal_buka->format('Y-m-d')) }}" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('tanggal_buka')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label value="Tanggal Tutup" />
                        <x-text-input type="date" name="tanggal_tutup" value="{{ old('tanggal_tutup', $gelombang->tanggal_tutup->format('Y-m-d')) }}" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('tanggal_tutup')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label value="Kuota" />
                        <x-text-input type="number" name="kuota" value="{{ old('kuota', $gelombang->kuota) }}" placeholder="Contoh: 40" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('kuota')" class="mt-1.5" />
                    </div>
                </div>
            </div>

            <div class="mt-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                <p class="mb-1 flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="signpost" class="h-[15px] w-[15px] text-gray-400" />
                    Batasi Jalur (Opsional)
                </p>
                <p class="mb-4 text-xs text-gray-500">Kosongkan semua supaya semua jalur aktif tersedia untuk gelombang ini. Centang jalur tertentu untuk membatasi hanya jalur itu yang bisa dipilih calon murid.</p>

                @if ($jalurAktif->isEmpty())
                    <p class="text-sm text-gray-500">
                        Belum ada jalur aktif di tahun ajaran ini. Tambahkan dulu di halaman
                        <a href="{{ route('admin.jalur-ppdb.index') }}" class="font-semibold text-brand-600 hover:underline">Jalur PPDB</a>.
                    </p>
                @else
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($jalurAktif as $jalur)
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="jalur_ids[]" value="{{ $jalur->id }}" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500" @checked(in_array($jalur->id, old('jalur_ids', $jalurTerpilih)))>
                                {{ $jalur->nama }}
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="mt-4 flex items-center gap-3">
                <x-primary-button type="submit">Simpan Perubahan</x-primary-button>
                <a href="{{ route('admin.gelombang-ppdb.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Batal</a>
            </div>
        </form>
    </div>
</x-app-layout>
