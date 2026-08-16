<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-4">
        {{-- Flash Messages --}}
        @if ($errors->any())
            <div class="rounded-lg bg-error-50 p-4 text-sm text-error-700" x-data x-init="$store.toast.push('error', @js($errors->first()))">{{ $errors->first() }}</div>
        @endif

        {{-- Header & Breadcrumb --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Panel Keputusan Persetujuan Proposal</h1>
                <p class="text-xs text-gray-500 mt-0.5 font-mono">Unit: {{ $proposal->lembaga->nama ?? 'Sekolah' }} &bull; {{ $proposal->nomor_pengajuan }}</p>
            </div>
            <x-link-button variant="secondary" href="{{ route('admin.pengadaan.inbox.index') }}">
                <x-icon name="arrow_back" class="h-4 w-4 mr-1" /> Kembali ke Inbox
            </x-link-button>
        </div>

        {{-- Proposal Overview Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 pb-4">
                <div>
                    <h2 class="text-base font-bold text-gray-900">{{ $proposal->judul_pengajuan }}</h2>
                    <p class="text-xs text-gray-600 mt-1">{{ $proposal->latar_belakang ?: 'Tidak ada keterangan latar belakang.' }}</p>
                </div>
                <div class="text-right">
                    <x-badge :tone="$proposal->tingkat_urgensi->badgeTone()">
                        Urgensi: {{ $proposal->tingkat_urgensi->label() }}
                    </x-badge>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 text-xs text-gray-600">
                <div>
                    <span class="text-gray-400">Pengusul:</span>
                    <p class="font-semibold text-gray-900">{{ $proposal->pengaju->name ?? 'Admin Sekolah' }}</p>
                </div>
                <div>
                    <span class="text-gray-400">Tanggal Pengajuan:</span>
                    <p class="font-semibold text-gray-900">{{ $proposal->created_at->translatedFormat('d F Y H:i') }}</p>
                </div>
                <div>
                    <span class="text-gray-400">Total Estimasi Awal:</span>
                    <p class="font-bold text-brand-700 text-sm">Rp {{ number_format($proposal->total_estimasi, 0, ',', '.') }}</p>
                </div>
            </div>
        </div>

        {{-- Review & Decision Form --}}
        <form
            action="{{ route('admin.pengadaan.inbox.decision', $proposal) }}"
            method="POST"
            x-data="{
                action: 'APPROVE',
                decisions: {
                    @foreach ($proposal->items as $item)
                        {{ $item->id }}: {
                            status: 'approved',
                            catatan: ''
                        },
                    @endforeach
                }
            }"
            class="space-y-6"
        >
            @csrf

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                <div class="border-b border-gray-100 pb-3">
                    <h2 class="font-display text-sm font-bold text-gray-900">Evaluasi & Persetujuan Parsial Item Belanja</h2>
                    <p class="text-xs text-gray-500">Anda dapat menyetujui seluruh item atau menolak/mencoret item tertentu dengan catatan.</p>
                </div>

                <div class="space-y-4">
                    @foreach ($proposal->items as $item)
                        <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-4 space-y-3">
                            <div class="flex flex-wrap items-start justify-between gap-2 border-b border-gray-200/75 pb-2">
                                <div>
                                    <h3 class="text-xs font-bold text-gray-900">{{ $item->nama_barang }}</h3>
                                    <p class="text-[11px] text-gray-500">
                                        {{ $item->qty }} {{ $item->satuan }} &bull;
                                        Estimasi Satuan: Rp {{ number_format($item->estimasi_harga_satuan, 0, ',', '.') }} &bull;
                                        Ruangan: <b>{{ $item->ruangan->nama_ruangan ?? '-' }}</b>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs font-bold text-gray-900">Rp {{ number_format($item->total_estimasi, 0, ',', '.') }}</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Keputusan Item</label>
                                    <select :name="`item_decisions[{{ $item->id }}][status]`" x-model="decisions[{{ $item->id }}].status" class="w-full rounded-lg border-gray-200 bg-white text-xs focus:border-brand-500 focus:ring-brand-500">
                                        <option value="approved">Disetujui (ACC)</option>
                                        <option value="rejected">Ditolak / Dicoret</option>
                                    </select>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Catatan Koreksi (Opsional)</label>
                                    <input type="text" :name="`item_decisions[{{ $item->id }}][catatan]`" x-model="decisions[{{ $item->id }}].catatan" placeholder="Alasan penolakan / instruksi khusus..." class="w-full rounded-lg border-gray-200 bg-white text-xs focus:border-brand-500 focus:ring-brand-500">
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- General Decision Section --}}
                <div class="rounded-xl border border-gray-200 bg-white p-4 space-y-3">
                    <label class="block text-xs font-semibold text-gray-700">Keputusan Alur Keseluruhan</label>
                    <div class="flex flex-wrap gap-4">
                        <label class="flex items-center gap-2 text-xs font-medium text-gray-800 cursor-pointer">
                            <input type="radio" name="action" value="APPROVE" x-model="action" class="text-brand-600 focus:ring-brand-500">
                            <span>Setujui Proposal (Lanjut ke Pencairan Kas)</span>
                        </label>
                        <label class="flex items-center gap-2 text-xs font-medium text-amber-800 cursor-pointer">
                            <input type="radio" name="action" value="REQUEST_REVISION" x-model="action" class="text-amber-600 focus:ring-amber-500">
                            <span>Minta Revisi Proposal ke Sekolah</span>
                        </label>
                        <label class="flex items-center gap-2 text-xs font-medium text-rose-800 cursor-pointer">
                            <input type="radio" name="action" value="REJECT" x-model="action" class="text-rose-600 focus:ring-rose-500">
                            <span>Tolak Proposal Keseluruhan</span>
                        </label>
                    </div>

                    <div class="pt-2">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Catatan Evaluasi Final</label>
                        <textarea name="notes" rows="2" placeholder="Tuliskan catatan arahan pengurus yayasan..." class="w-full rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500"></textarea>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <x-link-button variant="secondary" href="{{ route('admin.pengadaan.inbox.index') }}">
                    Batal
                </x-link-button>

                <x-primary-button type="submit">
                    <x-icon name="send" class="h-4 w-4 mr-1" /> Simpan & Kirim Keputusan
                </x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
