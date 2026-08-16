<x-app-layout>
    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <h1 class="font-display text-xl font-bold text-gray-900">{{ $isEdit ? 'Edit Gedung' : 'Tambah Gedung Baru' }}</h1>
            <p class="text-xs text-gray-500 mt-1">Lengkapi informasi fisik gedung fasilitas sekolah.</p>
        </div>

        <form action="{{ $isEdit ? route('admin.sarpras.gedung.update', $gedung) : route('admin.sarpras.gedung.store') }}" method="POST" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm space-y-5">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Kode Gedung <span class="text-rose-500">*</span></label>
                    <input type="text" name="kode_gedung" value="{{ old('kode_gedung', $gedung->kode_gedung) }}" required placeholder="Contoh: GD-A, GD-UTAMA" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    @error('kode_gedung') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Jumlah Lantai <span class="text-rose-500">*</span></label>
                    <input type="number" name="jumlah_lantai" value="{{ old('jumlah_lantai', $gedung->jumlah_lantai ?? 1) }}" min="1" max="50" required class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    @error('jumlah_lantai') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Nama Gedung <span class="text-rose-500">*</span></label>
                <input type="text" name="nama_gedung" value="{{ old('nama_gedung', $gedung->nama_gedung) }}" required placeholder="Contoh: Gedung Umar bin Khattab" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                @error('nama_gedung') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Deskripsi / Keterangan</label>
                <textarea name="deskripsi" rows="3" placeholder="Keterangan tambahan lokasi gedung..." class="w-full rounded-xl border border-gray-200 px-3.5 py-2 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">{{ old('deskripsi', $gedung->deskripsi) }}</textarea>
                @error('deskripsi') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-2 pt-2">
                <input type="checkbox" id="is_aktif" name="is_aktif" value="1" {{ old('is_aktif', $gedung->is_aktif ?? true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <label for="is_aktif" class="text-xs font-medium text-gray-700">Status Gedung Aktif Digunakan</label>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="{{ route('admin.sarpras.gedung.index') }}" class="rounded-xl border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition">Batal</a>
                <button type="submit" class="rounded-xl bg-indigo-600 px-5 py-2 text-xs font-semibold text-white hover:bg-indigo-700 transition">Simpan Data</button>
            </div>
        </form>
    </div>
</x-app-layout>
