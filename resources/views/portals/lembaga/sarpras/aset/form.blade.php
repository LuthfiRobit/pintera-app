<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-4">
        {{-- Flash Messages --}}
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">{{ $isEdit ? 'Edit Aset / Barang' : 'Tambah Aset Baru' }}</h1>
                <p class="text-xs text-gray-500 mt-0.5">Lengkapi formulir informasi inventaris barang, spesifikasi, dan lokasi penempatan.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> Sarpras <span class="mx-1 text-gray-300">&rsaquo;</span> <a href="{{ route('admin.sarpras.aset.index') }}" class="hover:underline">Aset</a> <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">{{ $isEdit ? 'Edit' : 'Tambah' }}</b>
            </p>
        </div>

        <form action="{{ $isEdit ? route('admin.sarpras.aset.update', $aset) : route('admin.sarpras.aset.store') }}" method="POST" enctype="multipart/form-data" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-5">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Kategori Aset <span class="text-rose-500">*</span></label>
                    <select name="kategori_aset_id" required class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Pilih Kategori...</option>
                        @foreach($kategoriOptions as $k)
                            <option value="{{ $k->id }}" {{ old('kategori_aset_id', $aset->kategori_aset_id) == $k->id ? 'selected' : '' }}>{{ $k->nama_kategori }} ({{ $k->kode_kategori }})</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('kategori_aset_id')" class="mt-1" />
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Lokasi Ruangan Penempatan <span class="text-rose-500">*</span></label>
                    <select name="ruangan_id" required class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Pilih Ruangan...</option>
                        @foreach($ruanganOptions as $r)
                            <option value="{{ $r->id }}" {{ old('ruangan_id', $aset->ruangan_id) == $r->id ? 'selected' : '' }}>{{ $r->nama_ruangan }} ({{ $r->gedung->nama_gedung ?? 'Gedung' }})</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('ruangan_id')" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Kode Inventaris / Barcode <span class="text-rose-500">*</span></label>
                    <input type="text" name="kode_inventaris" value="{{ old('kode_inventaris', $aset->kode_inventaris) }}" required placeholder="Contoh: INV/2026/ELK/001" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                    <x-input-error :messages="$errors->get('kode_inventaris')" class="mt-1" />
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Nama Barang <span class="text-rose-500">*</span></label>
                    <input type="text" name="nama_barang" value="{{ old('nama_barang', $aset->nama_barang) }}" required placeholder="Contoh: Laptop Asus VivoBook 14" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                    <x-input-error :messages="$errors->get('nama_barang')" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Merk / Brand</label>
                    <input type="text" name="merk" value="{{ old('merk', $aset->merk) }}" placeholder="Contoh: Asus, Epson, Informa" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Tipe Pencatatan <span class="text-rose-500">*</span></label>
                    <select name="tipe_pencatatan" required class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        @foreach($tipeOptions as $tipe)
                            <option value="{{ $tipe->value }}" {{ old('tipe_pencatatan', $aset->tipe_pencatatan?->value) == $tipe->value ? 'selected' : '' }}>{{ $tipe->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jumlah & Satuan <span class="text-rose-500">*</span></label>
                    <div class="flex gap-2">
                        <input type="number" name="qty" value="{{ old('qty', $aset->qty ?? 1) }}" min="1" required class="w-20 rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <input type="text" name="satuan" value="{{ old('satuan', $aset->satuan ?? 'unit') }}" required placeholder="unit/pcs" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Kondisi Barang <span class="text-rose-500">*</span></label>
                    <select name="kondisi" required class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        @foreach($kondisiOptions as $kondisi)
                            <option value="{{ $kondisi->value }}" {{ old('kondisi', $aset->kondisi?->value) == $kondisi->value ? 'selected' : '' }}>{{ $kondisi->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Sumber Perolehan <span class="text-rose-500">*</span></label>
                    <select name="sumber_perolehan" required class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        @foreach($sumberOptions as $sumber)
                            <option value="{{ $sumber->value }}" {{ old('sumber_perolehan', $aset->sumber_perolehan?->value) == $sumber->value ? 'selected' : '' }}>{{ $sumber->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Tanggal Perolehan</label>
                    <input type="date" name="tanggal_perolehan" value="{{ old('tanggal_perolehan', $aset->tanggal_perolehan?->format('Y-m-d')) }}" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">Harga Perolehan (Rp)</label>
                    <input type="number" step="0.01" name="harga_perolehan" value="{{ old('harga_perolehan', $aset->harga_perolehan) }}" placeholder="Contoh: 7500000" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Spesifikasi / Detail Teknis</label>
                <textarea name="spesifikasi" rows="3" placeholder="Rincian RAM, Processor, Bahan Kayu, dll..." class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">{{ old('spesifikasi', $aset->spesifikasi) }}</textarea>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700">Foto Fisik Barang</label>
                <input type="file" name="foto" accept="image/*" class="w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                @if($aset->foto_barang_path)
                    <div class="mt-2 text-xs text-gray-500">Foto saat ini tersimpan di server.</div>
                @endif
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                <x-link-button variant="secondary" href="{{ route('admin.sarpras.aset.index') }}">
                    Batal
                </x-link-button>
                <x-primary-button type="submit">
                    {{ $isEdit ? 'Simpan Perubahan' : 'Tambah Aset' }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
