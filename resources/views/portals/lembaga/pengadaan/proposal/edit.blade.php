<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
        {{-- Flash Messages --}}
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Edit Usulan Pengadaan Sarpras</h1>
                <p class="text-xs text-gray-500 mt-0.5 font-mono">No: {{ $proposal->nomor_pengajuan }} &bull; Status: {{ $proposal->status->label() }}</p>
            </div>
            <p class="text-sm text-gray-500">
                <a href="{{ route('admin.pengadaan.proposal.index') }}" class="hover:underline">Pengadaan</a> <span class="mx-1 text-gray-300">&rsaquo;</span>
                <a href="{{ route('admin.pengadaan.proposal.show', $proposal) }}" class="hover:underline">{{ $proposal->nomor_pengajuan }}</a> <span class="mx-1 text-gray-300">&rsaquo;</span>
                <b class="font-semibold text-gray-700">Edit</b>
            </p>
        </div>

        {{-- Revision Callout Banner if Revision Required --}}
        @if ($proposal->status === \App\Domains\Pengadaan\Enums\StatusPengajuan::RevisionRequired)
            <div class="rounded-2xl border border-amber-300 bg-amber-50 p-5 shadow-card">
                <div class="flex items-start gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-white shadow-sm">
                        <x-icon name="edit_note" class="h-5 w-5" />
                    </span>
                    <div class="space-y-1">
                        <h2 class="font-display text-sm font-bold text-amber-900">Perhatian: Usulan Ini Memerlukan Perbaikan</h2>
                        <p class="text-xs text-amber-800 leading-relaxed">
                            {{ $proposal->approvalRequest?->last_notes ?? 'Silakan sesuaikan item barang, spesifikasi, atau kuantitas sesuai instruksi reviewer di bawah ini.' }}
                        </p>
                    </div>
                </div>
            </div>
        @endif

        @php
            $initialItems = $proposal->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'nama_barang' => $item->nama_barang,
                    'kategori_aset_id' => (string) $item->kategori_aset_id,
                    'target_ruangan_id' => (string) $item->target_ruangan_id,
                    'merk' => $item->merk ?? '',
                    'spesifikasi' => $item->spesifikasi ?? '',
                    'qty' => $item->qty,
                    'satuan' => $item->satuan,
                    'estimasi_harga_satuan' => (float) $item->estimasi_harga_satuan,
                    'tipe_pencatatan' => $item->tipe_pencatatan->value,
                    'foto_referensi_url' => $item->foto_referensi_path ? asset('storage/' . $item->foto_referensi_path) : null,
                    'status_item' => $item->status_item->value,
                    'catatan_reviewer' => $item->catatan_reviewer,
                ];
            })->toArray();
        @endphp

        <form
            action="{{ route('admin.pengadaan.proposal.update', $proposal) }}"
            method="POST"
            enctype="multipart/form-data"
            x-data="proposalEditForm()"
            class="space-y-6"
        >
            @csrf
            @method('PUT')

            {{-- Card 1: Informasi Usulan --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                <h2 class="font-display text-sm font-bold text-gray-900 border-b border-gray-100 pb-3">1. Informasi Proposal</h2>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Judul Pengajuan <span class="text-error-600">*</span></label>
                        <input
                            type="text"
                            name="judul_pengajuan"
                            required
                            value="{{ old('judul_pengajuan', $proposal->judul_pengajuan) }}"
                            placeholder="Contoh: Pengadaan 5 Unit Proyektor & Smart TV Lab"
                            class="w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500 @error('judul_pengajuan') border-error-500 ring-error-500 @enderror"
                        >
                        <x-input-error :messages="$errors->get('judul_pengajuan')" class="mt-1" />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Tingkat Urgensi <span class="text-error-600">*</span></label>
                        <select name="tingkat_urgensi" required class="w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500 @error('tingkat_urgensi') border-error-500 ring-error-500 @enderror">
                            <option value="biasa" {{ old('tingkat_urgensi', $proposal->tingkat_urgensi->value) == 'biasa' ? 'selected' : '' }}>Biasa / Rutin</option>
                            <option value="mendesak" {{ old('tingkat_urgensi', $proposal->tingkat_urgensi->value) == 'mendesak' ? 'selected' : '' }}>Mendesak / Prioritas</option>
                            <option value="darurat" {{ old('tingkat_urgensi', $proposal->tingkat_urgensi->value) == 'darurat' ? 'selected' : '' }}>Darurat (Segera)</option>
                        </select>
                        <x-input-error :messages="$errors->get('tingkat_urgensi')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Latar Belakang & Justifikasi Kebutuhan</label>
                    <textarea
                        name="latar_belakang"
                        rows="3"
                        placeholder="Jelaskan alasan mengapa barang/sarpras ini perlu diadakan untuk kelancaran KBM atau operasional..."
                        class="w-full rounded-lg border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500"
                    >{{ old('latar_belakang', $proposal->latar_belakang) }}</textarea>
                    <x-input-error :messages="$errors->get('latar_belakang')" class="mt-1" />
                </div>
            </div>

            {{-- Card 2: Rincian Barang --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-3">
                    <div>
                        <h2 class="font-display text-sm font-bold text-gray-900">2. Rincian Barang & Estimasi Biaya</h2>
                        <p class="text-xs text-gray-500">Item yang telah disetujui reviewer terkunci, sementara item yang ditolak atau baru dapat disesuaikan kembali.</p>
                    </div>
                    <button
                        type="button"
                        @click="tambahItem()"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand-50 px-3 py-1.5 text-xs font-bold text-brand-600 hover:bg-brand-100 transition"
                    >
                        <x-icon name="add" class="h-4 w-4" /> Tambah Baris Barang
                    </button>
                </div>

                <div class="space-y-4">
                    <template x-for="(item, index) in items" :key="index">
                        <div
                            class="rounded-xl border p-4 space-y-3 relative transition-all"
                            :class="{
                                'border-emerald-200 bg-emerald-50/30': item.status_item === 'approved',
                                'border-rose-300 bg-rose-50/40 shadow-sm': item.status_item === 'rejected',
                                'border-gray-200 bg-gray-50/50': item.status_item !== 'approved' && item.status_item !== 'rejected'
                            }"
                        >
                            {{-- Header Baris --}}
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 pb-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold text-white"
                                        :class="item.status_item === 'approved' ? 'bg-emerald-600' : (item.status_item === 'rejected' ? 'bg-rose-600' : 'bg-brand-600')"
                                        x-text="index + 1"
                                    ></span>
                                    <span class="text-xs font-bold text-gray-800" x-text="item.nama_barang ? item.nama_barang : 'Barang #' + (index + 1)"></span>
                                    
                                    {{-- Status Badges --}}
                                    <template x-if="item.status_item === 'approved'">
                                        <span class="inline-flex items-center gap-1 rounded-md bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-800 border border-emerald-300">
                                            <x-icon name="check_circle" class="h-3.5 w-3.5 text-emerald-600" /> Disetujui Reviewer (Terkunci)
                                        </span>
                                    </template>
                                    <template x-if="item.status_item === 'rejected'">
                                        <span class="inline-flex items-center gap-1 rounded-md bg-rose-100 px-2 py-0.5 text-[11px] font-bold text-rose-800 border border-rose-300">
                                            <x-icon name="error" class="h-3.5 w-3.5 text-rose-600" /> Perlu Revisi / Ditolak
                                        </span>
                                    </template>
                                    <template x-if="item.status_item === 'pending' || !item.status_item">
                                        <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600 border border-gray-200">
                                            Dapat Diedit
                                        </span>
                                    </template>
                                </div>

                                {{-- Action Buttons --}}
                                <div>
                                    <template x-if="item.status_item === 'approved'">
                                        <span class="text-[11px] text-emerald-700 flex items-center gap-1 font-medium bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200">
                                            <x-icon name="lock" class="h-3.5 w-3.5" /> Item Terverifikasi
                                        </span>
                                    </template>
                                    <template x-if="item.status_item !== 'approved'">
                                        <button
                                            type="button"
                                            x-show="items.length > 1"
                                            @click="hapusItem(index)"
                                            class="text-xs text-rose-600 hover:text-rose-800 flex items-center gap-1 font-medium transition py-1 px-2 hover:bg-rose-100 rounded-lg"
                                        >
                                            <x-icon name="delete" class="h-4 w-4" /> Hapus Baris
                                        </button>
                                    </template>
                                </div>
                            </div>

                            {{-- Catatan Reviewer jika ada --}}
                            <template x-if="item.catatan_reviewer">
                                <div class="flex items-start gap-2 rounded-lg bg-rose-50 border border-rose-200 p-2.5 text-xs text-rose-900">
                                    <x-icon name="info" class="h-4 w-4 shrink-0 text-rose-600 mt-0.5" />
                                    <div>
                                        <span class="font-bold">Catatan Reviewer:</span>
                                        <span x-text="item.catatan_reviewer" class="leading-relaxed"></span>
                                    </div>
                                </div>
                            </template>

                            <input type="hidden" :name="'items[' + index + '][id]'" x-model="item.id">

                            {{-- Hidden inputs when item is approved to ensure clean form submission --}}
                            <template x-if="item.status_item === 'approved'">
                                <div>
                                    <input type="hidden" :name="'items[' + index + '][kategori_aset_id]'" :value="item.kategori_aset_id">
                                    <input type="hidden" :name="'items[' + index + '][target_ruangan_id]'" :value="item.target_ruangan_id">
                                    <input type="hidden" :name="'items[' + index + '][tipe_pencatatan]'" :value="item.tipe_pencatatan">
                                </div>
                            </template>

                            {{-- Grid Input Item --}}
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                {{-- Nama Barang --}}
                                <div class="lg:col-span-2">
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Nama Barang <span class="text-error-600">*</span></label>
                                    <input
                                        type="text"
                                        :name="'items[' + index + '][nama_barang]'"
                                        x-model="item.nama_barang"
                                        required
                                        :readonly="item.status_item === 'approved'"
                                        :class="item.status_item === 'approved' ? 'bg-gray-100 text-gray-600 cursor-not-allowed' : 'bg-white'"
                                        placeholder="Contoh: Laptop ASUS ExpertBook B1400"
                                        class="w-full rounded border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500"
                                    >
                                </div>

                                {{-- Kategori Aset --}}
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Kategori Sarpras <span class="text-error-600">*</span></label>
                                    <select
                                        :name="item.status_item === 'approved' ? '' : 'items[' + index + '][kategori_aset_id]'"
                                        x-model="item.kategori_aset_id"
                                        required
                                        :disabled="item.status_item === 'approved'"
                                        :class="item.status_item === 'approved' ? 'bg-gray-100 text-gray-600 cursor-not-allowed' : 'bg-white'"
                                        class="w-full rounded border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500"
                                    >
                                        <option value="">Pilih Kategori</option>
                                        @foreach ($kategoris as $kat)
                                            <option value="{{ $kat->id }}">{{ $kat->nama_kategori }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Target Ruangan --}}
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Target Ruangan <span class="text-error-600">*</span></label>
                                    <select
                                        :name="item.status_item === 'approved' ? '' : 'items[' + index + '][target_ruangan_id]'"
                                        x-model="item.target_ruangan_id"
                                        required
                                        :disabled="item.status_item === 'approved'"
                                        :class="item.status_item === 'approved' ? 'bg-gray-100 text-gray-600 cursor-not-allowed' : 'bg-white'"
                                        class="w-full rounded border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500"
                                    >
                                        <option value="">Pilih Ruangan</option>
                                        @foreach ($ruangans as $r)
                                            <option value="{{ $r->id }}">{{ $r->nama_ruangan }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Merk --}}
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Merk / Brand</label>
                                    <input
                                        type="text"
                                        :name="'items[' + index + '][merk]'"
                                        x-model="item.merk"
                                        :readonly="item.status_item === 'approved'"
                                        :class="item.status_item === 'approved' ? 'bg-gray-100 text-gray-600 cursor-not-allowed' : 'bg-white'"
                                        placeholder="Contoh: ASUS, Epson, Custom"
                                        class="w-full rounded border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500"
                                    >
                                </div>

                                {{-- Tipe Pencatatan --}}
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Metode Pencatatan <span class="text-error-600">*</span></label>
                                    <select
                                        :name="item.status_item === 'approved' ? '' : 'items[' + index + '][tipe_pencatatan]'"
                                        x-model="item.tipe_pencatatan"
                                        required
                                        :disabled="item.status_item === 'approved'"
                                        :class="item.status_item === 'approved' ? 'bg-gray-100 text-gray-600 cursor-not-allowed' : 'bg-white'"
                                        class="w-full rounded border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500 font-medium"
                                    >
                                        <option value="unit">Unit (Barcode Terpisah)</option>
                                        <option value="batch">Batch (Kuantitas Gabungan)</option>
                                    </select>
                                </div>

                                {{-- Qty & Satuan --}}
                                <div class="flex gap-2">
                                    <div class="w-1/2">
                                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">Kuantitas <span class="text-error-600">*</span></label>
                                        <input
                                            type="number"
                                            min="1"
                                            :name="'items[' + index + '][qty]'"
                                            x-model.number="item.qty"
                                            required
                                            :readonly="item.status_item === 'approved'"
                                            :class="item.status_item === 'approved' ? 'bg-gray-100 text-gray-600 cursor-not-allowed' : 'bg-white'"
                                            class="w-full rounded border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500 text-center font-bold"
                                        >
                                    </div>
                                    <div class="w-1/2">
                                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">Satuan <span class="text-error-600">*</span></label>
                                        <input
                                            type="text"
                                            :name="'items[' + index + '][satuan]'"
                                            x-model="item.satuan"
                                            required
                                            :readonly="item.status_item === 'approved'"
                                            :class="item.status_item === 'approved' ? 'bg-gray-100 text-gray-600 cursor-not-allowed' : 'bg-white'"
                                            placeholder="unit/buah"
                                            class="w-full rounded border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500 text-center"
                                        >
                                    </div>
                                </div>

                                {{-- Estimasi Harga Satuan --}}
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Estimasi Harga Satuan (Rp) <span class="text-error-600">*</span></label>
                                    <input
                                        type="number"
                                        min="0"
                                        :name="'items[' + index + '][estimasi_harga_satuan]'"
                                        x-model.number="item.estimasi_harga_satuan"
                                        required
                                        :readonly="item.status_item === 'approved'"
                                        :class="item.status_item === 'approved' ? 'bg-gray-100 text-gray-600 cursor-not-allowed' : 'bg-white'"
                                        placeholder="0"
                                        class="w-full rounded border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500 font-mono font-semibold"
                                    >
                                </div>

                                {{-- Spesifikasi Lengkap --}}
                                <div class="lg:col-span-3">
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Spesifikasi Detail</label>
                                    <input
                                        type="text"
                                        :name="'items[' + index + '][spesifikasi]'"
                                        x-model="item.spesifikasi"
                                        :readonly="item.status_item === 'approved'"
                                        :class="item.status_item === 'approved' ? 'bg-gray-100 text-gray-600 cursor-not-allowed' : 'bg-white'"
                                        placeholder="Spesifikasi teknis, warna, ukuran, bahan..."
                                        class="w-full rounded border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500"
                                    >
                                </div>

                                {{-- Subtotal Item & Foto Referensi --}}
                                <div class="flex flex-col justify-end">
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Subtotal Estimasi</label>
                                    <div class="h-8 rounded bg-gray-100 border border-gray-200 px-3 flex items-center justify-end font-mono text-xs font-bold text-gray-900">
                                        <span x-text="formatRupiah((item.qty || 0) * (item.estimasi_harga_satuan || 0))"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-1" x-show="item.status_item !== 'approved'">
                                <label class="block text-[11px] font-semibold text-gray-600 mb-1">Foto / Brosur Referensi (Opsional)</label>
                                <input
                                    type="file"
                                    :name="'items[' + index + '][foto_referensi]'"
                                    accept="image/*"
                                    class="block w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-2.5 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-gray-200 file:text-gray-700 hover:file:bg-gray-300"
                                >
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Total Banner --}}
                <div class="flex flex-wrap items-center justify-between rounded-xl bg-gray-900 p-4 text-white">
                    <div>
                        <p class="text-xs text-gray-400 font-medium">Total Estimasi Anggaran Proposal:</p>
                        <p class="font-display text-xl font-bold text-brand-400" x-text="formatRupiah(hitungTotal())"></p>
                    </div>
                    <div class="text-right text-xs text-gray-400">
                        <span x-text="items.length"></span> macam barang diajukan
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-3">
                <x-link-button variant="secondary" href="{{ route('admin.pengadaan.proposal.show', $proposal) }}">
                    Batal
                </x-link-button>

                <button
                    type="submit"
                    name="submit_immediately"
                    value="0"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition"
                >
                    <x-icon name="save" class="h-4 w-4 text-gray-500" /> Simpan Perubahan (Draft)
                </button>

                <x-primary-button type="submit" name="submit_immediately" value="1">
                    <x-icon name="send" class="h-4 w-4 mr-1" />
                    {{ $proposal->status === \App\Domains\Pengadaan\Enums\StatusPengajuan::RevisionRequired ? 'Simpan & Ajukan Ulang' : 'Simpan & Ajukan Usulan' }}
                </x-primary-button>
            </div>
        </form>
    </div>

    <script>
        function proposalEditForm() {
            return {
                items: @js(old('items', $initialItems)),
                tambahItem() {
                    this.items.push({
                        id: null,
                        nama_barang: '',
                        kategori_aset_id: '',
                        target_ruangan_id: '',
                        merk: '',
                        spesifikasi: '',
                        qty: 1,
                        satuan: 'unit',
                        estimasi_harga_satuan: 0,
                        tipe_pencatatan: 'unit',
                        foto_referensi_url: null,
                        status_item: 'pending',
                        catatan_reviewer: null
                    });
                },
                hapusItem(index) {
                    if (this.items.length <= 1) return;
                    if (this.items[index].status_item === 'approved') return;

                    const nama = this.items[index].nama_barang || ('Barang #' + (index + 1));
                    confirmDialog(
                        'Hapus Item Barang?',
                        'Apakah Anda yakin ingin menghapus item ' + nama + ' dari daftar usulan ini?',
                        { confirmLabel: 'Ya, Hapus', isDanger: true }
                    ).then(confirmed => {
                        if (confirmed) {
                            this.items.splice(index, 1);
                        }
                    });
                },
                hitungTotal() {
                    return this.items.reduce((acc, item) => acc + ((Number(item.qty) || 0) * (Number(item.estimasi_harga_satuan) || 0)), 0);
                },
                formatRupiah(num) {
                    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(num);
                }
            };
        }
    </script>
</x-app-layout>
