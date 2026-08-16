<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
        {{-- Flash Messages --}}
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Form Laporan Pertanggungjawaban (LPJ)</h1>
                <p class="text-xs text-gray-500 mt-0.5 font-mono">Proposal: {{ $proposal->nomor_pengajuan }} &bull; {{ $proposal->judul_pengajuan }}</p>
            </div>
            <p class="text-sm text-gray-500">
                <a href="{{ route('admin.pengadaan.proposal.show', $proposal) }}" class="hover:underline">Proposal</a> <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Isi LPJ</b>
            </p>
        </div>

        {{-- Information Banner --}}
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50/60 p-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-sm">
                    <x-icon name="account_balance_wallet" class="h-5 w-5" />
                </span>
                <div>
                    <p class="text-xs font-bold text-indigo-900">Dana Kas yang Dicairkan Yayasan</p>
                    <p class="text-base font-extrabold text-indigo-950 font-mono">Rp {{ number_format($proposal->nominal_pencairan, 0, ',', '.') }}</p>
                </div>
            </div>
            <p class="text-xs text-indigo-800 max-w-sm text-right leading-relaxed">
                Input nominal nota riil per barang dan lampirkan <b>scan nota/faktur</b> serta <b>foto fisik barang</b> saat tiba di sekolah.
            </p>
        </div>

        <form
            action="{{ route('admin.pengadaan.lpj.store', $proposal) }}"
            method="POST"
            enctype="multipart/form-data"
            x-data="lpjCreateForm()"
            class="space-y-6"
        >
            @csrf

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                <div class="border-b border-gray-100 pb-3">
                    <h2 class="font-display text-sm font-bold text-gray-900">1. Realisasi Harga & Bukti Nota Belanja</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Semua item belanja wajib melampirkan scan struk/faktur asli dan foto fisik barang.</p>
                </div>

                <div class="space-y-4">
                    <template x-for="(item, index) in items" :key="item.id">
                        <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-4 space-y-3">
                            <input type="hidden" :name="`items[${index}][pengajuan_item_id]`" :value="item.id">

                            <div class="flex items-center justify-between border-b border-gray-200/75 pb-2">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-brand-600 text-[10px] font-bold text-white" x-text="index + 1"></span>
                                    <span class="text-xs font-bold text-gray-900" x-text="item.nama"></span>
                                    <span class="text-xs text-gray-500 font-medium" x-text="`(${item.qty} ${item.satuan})`"></span>
                                </div>
                                <span class="text-xs font-bold text-brand-700 font-mono" x-text="formatRupiah(item.total_riil)"></span>
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                {{-- Harga Satuan Riil --}}
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Harga Satuan Riil (Rp) <span class="text-error-600">*</span></label>
                                    <input
                                        type="number"
                                        min="0"
                                        :name="`items[${index}][harga_satuan_riil]`"
                                        x-model.number="item.harga_satuan_riil"
                                        required
                                        placeholder="0"
                                        class="w-full rounded border-gray-200 bg-white text-xs focus:border-brand-500 focus:ring-brand-500 font-mono font-semibold"
                                    >
                                    <input type="hidden" :name="`items[${index}][total_riil]`" :value="item.total_riil">
                                </div>

                                {{-- Subtotal Belanja Riil --}}
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Subtotal Belanja Riil</label>
                                    <div class="h-8 rounded bg-gray-100 border border-gray-200 px-3 flex items-center justify-end font-mono text-xs font-bold text-gray-900" x-text="formatRupiah(item.total_riil)"></div>
                                </div>

                                {{-- Scan Nota / Faktur --}}
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Scan Nota / Faktur <span class="text-error-600">*</span></label>
                                    <input
                                        type="file"
                                        :name="`items[${index}][foto_nota]`"
                                        accept="image/jpeg,image/png,image/jpg,application/pdf"
                                        required
                                        class="block w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-gray-200 file:text-gray-700 hover:file:bg-gray-300"
                                    >
                                    <p class="text-[10px] text-gray-400 mt-0.5">JPG, PNG, PDF (Maks 5MB)</p>
                                </div>

                                {{-- Foto Fisik Barang Tiba --}}
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Foto Fisik Barang Tiba <span class="text-error-600">*</span></label>
                                    <input
                                        type="file"
                                        :name="`items[${index}][foto_fisik]`"
                                        accept="image/jpeg,image/png,image/jpg"
                                        required
                                        class="block w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-gray-200 file:text-gray-700 hover:file:bg-gray-300"
                                    >
                                    <p class="text-[10px] text-gray-400 mt-0.5">Foto Barang JPG, PNG (Maks 5MB)</p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Rekapitulasi Realisasi & Selisih Kas --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 pt-2">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-1">
                        <span class="text-xs text-gray-500 font-semibold uppercase tracking-wider">Total Realisasi Belanja:</span>
                        <p class="font-display text-lg font-bold text-gray-900" x-text="formatRupiah(hitungTotalBelanja())"></p>
                    </div>

                    <div
                        class="rounded-xl border p-4 space-y-1 transition-all"
                        :class="hitungSelisih() > 0 ? 'border-amber-300 bg-amber-50/70' : (hitungSelisih() === 0 ? 'border-emerald-200 bg-emerald-50/50' : 'border-rose-200 bg-rose-50/50')"
                    >
                        <span
                            class="text-xs font-semibold uppercase tracking-wider"
                            :class="hitungSelisih() > 0 ? 'text-amber-800' : (hitungSelisih() === 0 ? 'text-emerald-700' : 'text-rose-700')"
                            x-text="hitungSelisih() > 0 ? 'Sisa Kas (Wajib Dikembalikan ke Yayasan):' : (hitungSelisih() === 0 ? 'Sisa Kas Nihil (Pas):' : 'Kekurangan Dana (Defisit):')"
                        ></span>
                        <p
                            class="font-display text-lg font-bold font-mono"
                            :class="hitungSelisih() > 0 ? 'text-amber-950 font-extrabold' : (hitungSelisih() === 0 ? 'text-emerald-900' : 'text-rose-900')"
                            x-text="formatRupiah(Math.abs(hitungSelisih()))"
                        ></p>
                    </div>
                </div>

                {{-- Lampiran Bukti Pengembalian Sisa Dana (Wajib jika ada sisa dana kas) --}}
                <div
                    class="rounded-xl border p-4 space-y-2 transition-all"
                    :class="hitungSelisih() > 0 ? 'border-amber-300 bg-amber-50/50' : 'border-gray-200 bg-gray-50'"
                    x-show="hitungSelisih() > 0"
                >
                    <div class="flex items-start gap-2">
                        <x-icon name="warning" class="h-4 w-4 text-amber-600 shrink-0 mt-0.5" />
                        <div>
                            <label class="block text-xs font-bold text-amber-900">
                                Upload Bukti Transfer / Setoran Pengembalian Sisa Kas ke Yayasan <span class="text-error-600">*</span>
                            </label>
                            <p class="text-[11px] text-amber-800 leading-relaxed mt-0.5">
                                Karena terdapat surplus/sisa dana kas sebesar <b x-text="formatRupiah(hitungSelisih())"></b>, Anda wajib melampirkan struk setor tunai atau bukti transfer mutasi rekening pengembalian sisa kas ke Yayasan.
                            </p>
                        </div>
                    </div>
                    <input
                        type="file"
                        name="bukti_kembali_sisa"
                        :required="hitungSelisih() > 0"
                        accept="image/jpeg,image/png,image/jpg,application/pdf"
                        class="block w-full text-xs text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-amber-100 file:text-amber-800 hover:file:bg-amber-200"
                    >
                    <x-input-error :messages="$errors->get('bukti_kembali_sisa')" class="mt-1" />
                </div>
            </div>

            {{-- Actions Form --}}
            <div class="flex items-center justify-end gap-3">
                <x-link-button variant="secondary" href="{{ route('admin.pengadaan.proposal.show', $proposal) }}">
                    Batal
                </x-link-button>

                <x-primary-button type="submit">
                    <x-icon name="send" class="h-4 w-4 mr-1" /> Kirim LPJ untuk Diverifikasi
                </x-primary-button>
            </div>
        </form>
    </div>

    <script>
        function lpjCreateForm() {
            return {
                nominalCair: {{ (float) $proposal->nominal_pencairan }},
                items: [
                    @foreach ($proposal->items as $idx => $item)
                    {
                        id: {{ $item->id }},
                        nama: @js($item->nama_barang),
                        qty: {{ $item->qty }},
                        satuan: @js($item->satuan),
                        harga_satuan_riil: {{ (float) $item->estimasi_harga_satuan }},
                        get total_riil() { return this.qty * (Number(this.harga_satuan_riil) || 0); }
                    },
                    @endforeach
                ],
                hitungTotalBelanja() {
                    return this.items.reduce((acc, item) => acc + item.total_riil, 0);
                },
                hitungSelisih() {
                    return this.nominalCair - this.hitungTotalBelanja();
                },
                formatRupiah(num) {
                    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(num);
                }
            };
        }
    </script>
</x-app-layout>
