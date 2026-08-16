<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h1 class="font-display text-xl font-bold text-gray-900">{{ $isEdit ? 'Edit Aset / Barang' : 'Tambah Aset Baru' }}</h1>
            <p class="text-xs text-gray-500 mt-1">Lengkapi informasi inventaris barang, spesifikasi, dan lokasi penempatan.</p>
        </div>

        <form action="{{ $isEdit ? route('admin.sarpras.aset.update', $aset) : route('admin.sarpras.aset.store') }}" method="POST" enctype="multipart/form-data" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm space-y-5">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Kategori Aset <span class="text-rose-500">*</span></label>
                    <select name="kategori_aset_id" required class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        <option value="">Pilih Kategori...</option>
                        @foreach($kategoriOptions as $k)
                            <option value="{{ $k->id }}" {{ old('kategori_aset_id', $aset->kategori_aset_id) == $k->id ? 'selected' : '' }}>{{ $k->nama_kategori }} ({{ $k->kode_kategori }})</option>
                        @endforeach
                    </select>
                    @error('kategori_aset_id') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Lokasi Ruangan Penempatan <span class="text-rose-500">*</span></label>
                    <select name="ruangan_id" required class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        <option value="">Pilih Ruangan...</option>
                        @foreach($ruanganOptions as $r)
                            <option value="{{ $r->id }}" {{ old('ruangan_id', $aset->ruangan_id) == $r->id ? 'selected' : '' }}>{{ $r->nama_ruangan }} ({{ $r->gedung->nama_gedung ?? 'Gedung' }})</option>
                        @endforeach
                    </select>
                    @error('ruangan_id') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Kode Inventaris / Barcode <span class="text-rose-500">*</span></label>
                    <input type="text" name="kode_inventaris" value="{{ old('kode_inventaris', $aset->kode_inventaris) }}" required placeholder="Contoh: INV/2026/ELK/001" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    @error('kode_inventaris') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Nama Barang <span class="text-rose-500">*</span></label>
                    <input type="text" name="nama_barang" value="{{ old('nama_barang', $aset->nama_barang) }}" required placeholder="Contoh: Laptop Asus VivoBook 14" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    @error('nama_barang') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Merk / Brand</label>
                    <input type="text" name="merk" value="{{ old('merk', $aset->merk) }}" placeholder="Contoh: Asus, Epson, Informa" class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Tipe Pencatatan <span class="text-rose-500">*</span></label>
                    <select name="tipe_pencatatan" required class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        @foreach($tipeOptions as $tipe)
                            <option value="{{ $tipe->value }}" {{ old('tipe_pencatatan', $aset->tipe_pencatatan?->value) == $tipe->value ? 'selected' : '' }}>{{ $tipe->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Jumlah & Satuan <span class="text-rose-500">*</span></label>
                    <div class="flex gap-2">
                        <input type="number" name="qty" value="{{ old('qty', $aset->qty ?? 1) }}" min="1" required class="w-20 rounded-xl border border-gray-200 px-3 py-2 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none">
                        <input type="text" name="satuan" value="{{ old('satuan', $aset->satuan ?? 'unit') }}" required placeholder="unit/pcs" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Kondisi Barang <span class="text-rose-500">*</span></label>
                    <select name="kondisi" required class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        @foreach($kondisiOptions as $kondisi)
                            <option value="{{ $kondisi->value }}" {{ old('kondisi', $aset->kondisi?->value) == $kondisi->value ? 'selected' : '' }}>{{ $kondisi->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Sumber Perolehan <span class="text-rose-500">*</span></label>
                    <select name="sumber_perolehan" required class="w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        @foreach($sumberOptions as $sumber)
                            <option value="{{ $sumber->value }}" {{ old('sumber_perolehan', $aset->sumber_perolehan?->value) == $sumber->value ? 'selected' : '' }}>{{ $sumber->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Tanggal Perolehan</label>
                    <input type="date" name="tanggal_perolehan" value="{{ old('tanggal_perolehan', $aset->tanggal_perolehan?->format('Y-m-d')) }}" class="w-full rounded-xl border border-gray-200 px-3.5 py-2 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Harga Perolehan (Rp)</label>
                    <input type="number" step="0.01" name="harga_perolehan" value="{{ old('harga_perolehan', $aset->harga_perolehan) }}" placeholder="Contoh: 7500000" class="w-full rounded-xl border border-gray-200 px-3.5 py-2 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Spesifikasi / Detail Teknis</label>
                <textarea name="spesifikasi" rows="3" placeholder="Rincian RAM, Processor, Bahan Kayu, dll..." class="w-full rounded-xl border border-gray-200 px-3.5 py-2 text-xs text-gray-900 focus:border-indigo-500 focus:outline-none">{{ old('spesifikasi', $aset->spesifikasi) }}</textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Foto Barang</label>
                <input type="file" name="foto" accept="image/*" class="w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                @if($aset->foto_barang_path)
                    <div class="mt-2 text-[11px] text-gray-500">Foto tersimpan: {{ $aset->foto_barang_path }}</div>
                @endif
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="{{ route('admin.sarpras.aset.index') }}" class="rounded-xl border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition">Batal</a>
                <button type="submit" class="rounded-xl bg-indigo-600 px-5 py-2 text-xs font-semibold text-white hover:bg-indigo-700 transition">Simpan Aset</button>
            </div>
        </form>
    </div>
</x-app-layout>
