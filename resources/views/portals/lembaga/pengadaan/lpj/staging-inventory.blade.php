<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700" x-data>{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Konfirmasi Inventarisasi Barang ke Sarpras</h1>
                <p class="text-xs text-gray-500 mt-0.5 font-mono">LPJ Terverifikasi Yayasan &bull; Proposal: {{ $lpj->proposal->nomor_pengajuan }}</p>
            </div>
            <x-link-button variant="secondary" href="{{ route('admin.pengadaan.proposal.show', $lpj->proposal) }}">
                <x-icon name="arrow_back" class="h-4 w-4 mr-1" /> Kembali
            </x-link-button>
        </div>

        @php
            $isAllConverted = $lpj->items->isNotEmpty() && $lpj->items->every(fn($item) => $item->status_konversi_sarpras === 'converted');
        @endphp

        @if ($isAllConverted)
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-6 shadow-card space-y-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white">
                        <x-icon name="verified" class="h-6 w-6" />
                    </span>
                    <div>
                        <h2 class="font-display text-base font-bold text-emerald-950">Inventarisasi Selesai Diterbitkan</h2>
                        <p class="text-xs text-emerald-800 mt-0.5">Seluruh barang hasil pengadaan ini telah berhasil dikonversi dan tercatat di Master Aset Sarpras & Kartu Inventaris Ruangan (KIR).</p>
                    </div>
                </div>

                <div class="space-y-3 pt-2">
                    @foreach ($lpj->items as $item)
                        @php $pItem = $item->pengajuanItem; @endphp
                        <div class="rounded-xl border border-emerald-200/70 bg-white p-4 flex flex-wrap items-center justify-between gap-3 text-xs">
                            <div>
                                <span class="font-bold text-gray-900">{{ $pItem->nama_barang }}</span>
                                <p class="text-[11px] text-gray-500 mt-0.5">
                                    Kategori: <b>{{ $pItem->kategori->nama_kategori ?? '-' }}</b> &bull;
                                    Ruangan: <b>{{ $pItem->ruangan->nama_ruangan ?? '-' }}</b> &bull;
                                    Kuantitas: <b>{{ $pItem->qty }} {{ $pItem->satuan }}</b> (Metode: <span class="uppercase font-mono font-semibold text-brand-600">{{ $pItem->tipe_pencatatan->value }}</span>)
                                </p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">
                                <x-icon name="check" class="h-3.5 w-3.5" /> Terdaftar di Sarpras
                            </span>
                        </div>
                    @endforeach
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-emerald-200">
                    <x-link-button variant="secondary" href="{{ route('admin.pengadaan.proposal.show', $lpj->proposal) }}">
                        <x-icon name="arrow_back" class="h-4 w-4 mr-1" /> Kembali ke Proposal
                    </x-link-button>

                    <x-link-button href="{{ route('admin.sarpras.aset.index') }}">
                        <x-icon name="inventory_2" class="h-4 w-4 mr-1" /> Buka Master Aset Sarpras
                    </x-link-button>
                </div>
            </div>
        @else
            <form action="{{ route('admin.pengadaan.lpj.convert-inventory', $lpj) }}" method="POST" class="space-y-6">
                @csrf

                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                        <div>
                            <h2 class="font-display text-sm font-bold text-gray-900">Daftar Barang yang Siap Diterbitkan</h2>
                            <p class="text-xs text-gray-500">Anda dapat melengkapi Nomor Seri (Serial Number) pabrik sebelum data masuk ke Master Aset.</p>
                        </div>
                        <span class="rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 border border-emerald-200">
                            Status LPJ: Terverifikasi
                        </span>
                    </div>

                    <div class="space-y-4">
                        @foreach ($lpj->items as $item)
                            @php $pItem = $item->pengajuanItem; @endphp
                            <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-xs font-bold text-gray-900">{{ $pItem->nama_barang }}</h3>
                                        <p class="text-[11px] text-gray-500">
                                            Kategori: <b>{{ $pItem->kategori->nama_kategori ?? '-' }}</b> &bull;
                                            Ruangan: <b>{{ $pItem->ruangan->nama_ruangan ?? '-' }}</b> &bull;
                                            Metode: <b class="uppercase font-mono text-brand-600">{{ $pItem->tipe_pencatatan->value }}</b>
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @if ($item->foto_nota_path)
                                            <button
                                                type="button"
                                                @click="$store.imagePreview.buka('{{ Storage::url($item->foto_nota_path) }}', 'Nota - {{ $pItem->nama_barang }}')"
                                                class="text-[11px] font-medium text-brand-600 hover:text-brand-800 bg-brand-50 hover:bg-brand-100 px-2 py-0.5 rounded border border-brand-200 inline-flex items-center gap-1 transition"
                                            >
                                                <x-icon name="receipt" class="h-3 w-3 text-brand-500" /> Nota
                                            </button>
                                        @endif
                                        @if ($item->foto_fisik_barang_path)
                                            <button
                                                type="button"
                                                @click="$store.imagePreview.buka('{{ Storage::url($item->foto_fisik_barang_path) }}', 'Foto Fisik - {{ $pItem->nama_barang }}')"
                                                class="text-[11px] font-medium text-brand-600 hover:text-brand-800 bg-brand-50 hover:bg-brand-100 px-2 py-0.5 rounded border border-brand-200 inline-flex items-center gap-1 transition"
                                            >
                                                <x-icon name="image" class="h-3 w-3 text-brand-500" /> Foto Barang
                                            </button>
                                        @endif
                                        <span class="text-xs font-bold text-gray-800">{{ $pItem->qty }} {{ $pItem->satuan }}</span>
                                    </div>
                                </div>

                                @if ($pItem->tipe_pencatatan === \App\Domains\Sarpras\Enums\TipePencatatanAset::Unit)
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3 pt-2">
                                        @for ($i = 1; $i <= $pItem->qty; $i++)
                                            <div class="rounded-lg border border-gray-200 bg-white p-3 space-y-1">
                                                <div class="flex items-center justify-between text-[11px]">
                                                    <span class="font-bold text-gray-700">Unit #{{ $i }}</span>
                                                    <span class="text-[10px] text-gray-400 font-mono">Auto-Barcode</span>
                                                </div>
                                                <input
                                                    type="text"
                                                    name="serial_numbers[{{ $pItem->id }}][{{ $i }}]"
                                                    placeholder="Nomor Seri / S/N Pabrik (Opsional)"
                                                    class="w-full rounded border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500"
                                                >
                                            </div>
                                        @endfor
                                    </div>
                                @else
                                    <div class="rounded-lg bg-white border border-gray-200 p-3 text-xs text-gray-600">
                                        Barang ini akan dicatat sebagai <b>1 record kuantitas (Batch Qty: {{ $pItem->qty }} {{ $pItem->satuan }})</b> di <b>{{ $pItem->ruangan->nama_ruangan ?? 'Ruangan Tujuan' }}</b>.
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <x-link-button variant="secondary" href="{{ route('admin.pengadaan.proposal.show', $lpj->proposal) }}">
                        Batal
                    </x-link-button>

                    <x-primary-button type="submit">
                        <x-icon name="check_circle" class="h-4 w-4 mr-1" /> Terbitkan ke Master Inventaris Sarpras
                    </x-primary-button>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
