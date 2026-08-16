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
                                <span class="text-xs font-bold text-gray-800">{{ $pItem->qty }} {{ $pItem->satuan }}</span>
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
    </div>
</x-app-layout>
