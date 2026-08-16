<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
        {{-- Flash Messages --}}
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Buat Usulan Pengadaan Sarpras</h1>
                <p class="text-xs text-gray-500 mt-0.5">Ajukan daftar barang/fasilitas yang dibutuhkan unit sekolah beserta estimasi anggaran.</p>
            </div>
            <p class="text-sm text-gray-500">
                <a href="{{ route('admin.pengadaan.proposal.index') }}" class="hover:underline">Pengadaan</a> <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Buat Usulan</b>
            </p>
        </div>

        <form
            action="{{ route('admin.pengadaan.proposal.store') }}"
            method="POST"
            enctype="multipart/form-data"
            x-data="{
                items: [
                    {
                        nama_barang: '',
                        kategori_aset_id: '',
                        target_ruangan_id: '',
                        merk: '',
                        spesifikasi: '',
                        qty: 1,
                        satuan: 'unit',
                        estimasi_harga_satuan: 0,
                        tipe_pencatatan: 'unit'
                    }
                ],
                tambahItem() {
                    this.items.push({
                        nama_barang: '',
                        kategori_aset_id: '',
                        target_ruangan_id: '',
                        merk: '',
                        spesifikasi: '',
                        qty: 1,
                        satuan: 'unit',
                        estimasi_harga_satuan: 0,
                        tipe_pencatatan: 'unit'
                    });
                },
                hapusItem(index) {
                    if (this.items.length > 1) {
                        this.items.splice(index, 1);
                    }
                },
                hitungTotal() {
                    return this.items.reduce((acc, item) => acc + ((Number(item.qty) || 0) * (Number(item.estimasi_harga_satuan) || 0)), 0);
                },
                formatRupiah(num) {
                    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(num);
                }
            }"
            class="space-y-6"
        >
            @csrf

            {{-- Card 1: Informasi Usulan --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                <h2 class="font-display text-sm font-bold text-gray-900 border-b border-gray-100 pb-3">1. Informasi Proposal</h2>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Judul Pengajuan <span class="text-error-600">*</span></label>
                        <input type="text" name="judul_pengajuan" required value="{{ old('judul_pengajuan') }}" placeholder="Contoh: Pengadaan 5 Unit Proyektor & Smart TV Lab" class="w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500 @error('judul_pengajuan') border-error-500 ring-error-500 @enderror">
                        <x-input-error :messages="$errors->get('judul_pengajuan')" class="mt-1" />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Tingkat Urgensi <span class="text-error-600">*</span></label>
                        <select name="tingkat_urgensi" required class="w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500 @error('tingkat_urgensi') border-error-500 ring-error-500 @enderror">
                            <option value="biasa" {{ old('tingkat_urgensi', 'biasa') == 'biasa' ? 'selected' : '' }}>Biasa / Rutin</option>
                            <option value="mendesak" {{ old('tingkat_urgensi') == 'mendesak' ? 'selected' : '' }}>Mendesak (Dibutuhkan Segera)</option>
                            <option value="kritis" {{ old('tingkat_urgensi') == 'kritis' ? 'selected' : '' }}>Kritis / Darurat</option>
                        </select>
                        <x-input-error :messages="$errors->get('tingkat_urgensi')" class="mt-1" />
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Latar Belakang / Justifikasi Kebutuhan</label>
                        <textarea name="latar_belakang" rows="3" placeholder="Jelaskan alasan pengadaan dan urgensinya terhadap kegiatan belajar mengajar..." class="w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500 @error('latar_belakang') border-error-500 ring-error-500 @enderror">{{ old('latar_belakang') }}</textarea>
                        <x-input-error :messages="$errors->get('latar_belakang')" class="mt-1" />
                    </div>
                </div>
            </div>

            <x-input-error :messages="$errors->get('items')" class="mt-1" />

            {{-- Card 2: Rincian Barang yang Diajukan --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <h2 class="font-display text-sm font-bold text-gray-900">2. Rincian Item Barang Belanja</h2>
                    <button type="button" @click="tambahItem()" class="inline-flex items-center gap-1.5 rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200 transition-colors">
                        <span class="text-base leading-none font-bold text-brand-600">+</span> Tambah Baris Barang
                    </button>
                </div>

                <div class="space-y-4">
                    <template x-for="(item, index) in items" :key="index">
                        <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-4 relative space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-brand-700 font-mono" x-text="`Item #${index + 1}`"></span>
                                <button type="button" @click="hapusItem(index)" x-show="items.length > 1" class="text-xs font-medium text-error-600 hover:underline">
                                    Hapus
                                </button>
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                                <div class="sm:col-span-2">
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Nama Barang <span class="text-error-600">*</span></label>
                                    <input type="text" :name="`items[${index}][nama_barang]`" x-model="item.nama_barang" required placeholder="Contoh: Asus Vivobook 14" class="w-full rounded-lg border-gray-200 bg-white text-xs focus:border-brand-500 focus:ring-brand-500">
                                </div>

                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Kategori Aset <span class="text-error-600">*</span></label>
                                    <select :name="`items[${index}][kategori_aset_id]`" x-model="item.kategori_aset_id" required class="w-full rounded-lg border-gray-200 bg-white text-xs focus:border-brand-500 focus:ring-brand-500">
                                        <option value="">Pilih Kategori</option>
                                        @foreach ($kategoris as $k)
                                            <option value="{{ $k->id }}">{{ $k->nama_kategori }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Target Penempatan (Ruangan) <span class="text-error-600">*</span></label>
                                    <select :name="`items[${index}][target_ruangan_id]`" x-model="item.target_ruangan_id" required class="w-full rounded-lg border-gray-200 bg-white text-xs focus:border-brand-500 focus:ring-brand-500">
                                        <option value="">Pilih Ruangan</option>
                                        @foreach ($ruangans as $r)
                                            <option value="{{ $r->id }}">{{ $r->nama_ruangan }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Merk / Brand</label>
                                    <input type="text" :name="`items[${index}][merk]`" x-model="item.merk" placeholder="Contoh: Asus" class="w-full rounded-lg border-gray-200 bg-white text-xs focus:border-brand-500 focus:ring-brand-500">
                                </div>

                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Tipe Pencatatan</label>
                                    <select :name="`items[${index}][tipe_pencatatan]`" x-model="item.tipe_pencatatan" class="w-full rounded-lg border-gray-200 bg-white text-xs focus:border-brand-500 focus:ring-brand-500">
                                        <option value="unit">Unit (Barcode Unik per fisik)</option>
                                        <option value="batch">Batch (Kuantitas Ruangan)</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Jumlah (Qty) <span class="text-error-600">*</span></label>
                                    <input type="number" min="1" :name="`items[${index}][qty]`" x-model.number="item.qty" required class="w-full rounded-lg border-gray-200 bg-white text-xs focus:border-brand-500 focus:ring-brand-500">
                                </div>

                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Satuan</label>
                                    <input type="text" :name="`items[${index}][satuan]`" x-model="item.satuan" placeholder="unit, buah, set..." required class="w-full rounded-lg border-gray-200 bg-white text-xs focus:border-brand-500 focus:ring-brand-500">
                                </div>

                                <div class="sm:col-span-2">
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Estimasi Harga Satuan (Rp) <span class="text-error-600">*</span></label>
                                    <input type="number" min="0" :name="`items[${index}][estimasi_harga_satuan]`" x-model.number="item.estimasi_harga_satuan" required class="w-full rounded-lg border-gray-200 bg-white text-xs focus:border-brand-500 focus:ring-brand-500">
                                </div>

                                <div class="sm:col-span-2">
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Subtotal Estimasi</label>
                                    <div class="rounded-lg bg-gray-100 px-3 py-2 text-xs font-bold text-gray-900" x-text="formatRupiah((Number(item.qty) || 0) * (Number(item.estimasi_harga_satuan) || 0))"></div>
                                </div>

                                <div class="sm:col-span-3">
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Spesifikasi Detail</label>
                                    <input type="text" :name="`items[${index}][spesifikasi]`" x-model="item.spesifikasi" placeholder="RAM 16GB, SSD 512GB, Garansi Resmi..." class="w-full rounded-lg border-gray-200 bg-white text-xs focus:border-brand-500 focus:ring-brand-500">
                                </div>

                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Foto Acuan / Brosur</label>
                                    <input type="file" :name="`items[${index}][foto_referensi]`" accept="image/*" class="w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-gray-200">
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Total Box --}}
                <div class="rounded-xl border border-brand-200 bg-brand-50/50 p-4 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-brand-700">Total Estimasi Anggaran Pengajuan</p>
                        <p class="text-xl font-extrabold text-brand-900" x-text="formatRupiah(hitungTotal())"></p>
                    </div>
                    <span class="text-xs text-brand-600 font-medium">* Belum termasuk evaluasi/koreksi yayasan</span>
                </div>
            </div>

            {{-- Actions Form --}}
            <div class="flex items-center justify-end gap-3 pt-2">
                <x-link-button variant="secondary" href="{{ route('admin.pengadaan.proposal.index') }}">
                    Batal
                </x-link-button>

                <x-secondary-button type="submit" name="submit_immediately" value="0">
                    Simpan Sebagai Draft
                </x-secondary-button>

                <x-primary-button type="submit" name="submit_immediately" value="1">
                    <x-icon name="send" class="h-4 w-4 mr-1" /> Ajukan Sekarang
                </x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
