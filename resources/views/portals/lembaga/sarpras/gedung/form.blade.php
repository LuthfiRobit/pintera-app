<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-4">
        {{-- Flash Messages --}}
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">{{ $isEdit ? 'Edit Gedung' : 'Tambah Gedung Baru' }}</h1>
                <p class="text-xs text-gray-500 mt-0.5">Lengkapi formulir informasi fisik gedung fasilitas sekolah.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> Sarpras <span class="mx-1 text-gray-300">&rsaquo;</span> <a href="{{ route('admin.sarpras.gedung.index') }}" class="hover:underline">Gedung</a> <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">{{ $isEdit ? 'Edit' : 'Tambah' }}</b>
            </p>
        </div>

        <form action="{{ $isEdit ? route('admin.sarpras.gedung.update', $gedung) : route('admin.sarpras.gedung.store') }}" method="POST" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-5">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Kode Gedung <span class="text-rose-500">*</span></label>
                    <input type="text" name="kode_gedung" value="{{ old('kode_gedung', $gedung->kode_gedung) }}" required placeholder="Contoh: GD-A, GD-UTAMA" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                    <x-input-error :messages="$errors->get('kode_gedung')" class="mt-1" />
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jumlah Lantai <span class="text-rose-500">*</span></label>
                    <input type="number" name="jumlah_lantai" value="{{ old('jumlah_lantai', $gedung->jumlah_lantai ?? 1) }}" min="1" max="50" required class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                    <x-input-error :messages="$errors->get('jumlah_lantai')" class="mt-1" />
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Nama Gedung <span class="text-rose-500">*</span></label>
                <input type="text" name="nama_gedung" value="{{ old('nama_gedung', $gedung->nama_gedung) }}" required placeholder="Contoh: Gedung Umar bin Khattab" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                <x-input-error :messages="$errors->get('nama_gedung')" class="mt-1" />
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Deskripsi / Keterangan Tambahan</label>
                <textarea name="deskripsi" rows="3" placeholder="Keterangan spesifikasi atau lokasi gedung..." class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">{{ old('deskripsi', $gedung->deskripsi) }}</textarea>
                <x-input-error :messages="$errors->get('deskripsi')" class="mt-1" />
            </div>

            <div class="flex items-center gap-2 pt-1">
                <input type="checkbox" id="is_aktif" name="is_aktif" value="1" {{ old('is_aktif', $gedung->is_aktif ?? true) ? 'checked' : '' }} class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                <label for="is_aktif" class="text-xs font-medium text-gray-700">Status Gedung Aktif Digunakan</label>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                <x-link-button variant="secondary" href="{{ route('admin.sarpras.gedung.index') }}">
                    Batal
                </x-link-button>
                <x-primary-button type="submit">
                    {{ $isEdit ? 'Simpan Perubahan' : 'Tambah Gedung' }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
