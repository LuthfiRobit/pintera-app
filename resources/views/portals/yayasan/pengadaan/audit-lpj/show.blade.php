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
                <h1 class="font-display text-lg font-bold text-gray-900">Audit Bukti LPJ Belanja Sekolah</h1>
                <p class="text-xs text-gray-500 mt-0.5 font-mono">Unit: {{ $lpj->proposal->lembaga->nama ?? 'Sekolah' }} &bull; Proposal: {{ $lpj->proposal->nomor_pengajuan }}</p>
            </div>
            <x-link-button variant="secondary" href="{{ route('admin.pengadaan.audit-lpj.index') }}">
                <x-icon name="arrow_back" class="h-4 w-4 mr-1" /> Kembali ke Daftar LPJ
            </x-link-button>
        </div>

        {{-- Financial Summary Box --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
            <h2 class="font-display text-sm font-bold text-gray-900 border-b border-gray-100 pb-3">Ringkasan Rekonsiliasi Dana Kas</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 text-xs">
                <div class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-3">
                    <span class="text-indigo-600 font-semibold uppercase">Dana Cair Kas Yayasan:</span>
                    <p class="text-base font-bold text-indigo-950 mt-0.5">Rp {{ number_format($lpj->proposal->nominal_pencairan, 0, ',', '.') }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                    <span class="text-gray-500 font-semibold uppercase">Total Nota Belanja Riil:</span>
                    <p class="text-base font-bold text-gray-900 mt-0.5">Rp {{ number_format($lpj->total_realisasi, 0, ',', '.') }}</p>
                </div>
                <div class="rounded-xl border p-3 {{ $lpj->selisih_dana >= 0 ? 'border-emerald-200 bg-emerald-50/50 text-emerald-900' : 'border-rose-200 bg-rose-50/50 text-rose-900' }}">
                    <span class="font-semibold uppercase {{ $lpj->selisih_dana >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                        {{ $lpj->selisih_dana >= 0 ? 'Sisa Dana Kas (Surplus):' : 'Defisit Belanja:' }}
                    </span>
                    <p class="text-base font-bold mt-0.5">Rp {{ number_format(abs($lpj->selisih_dana), 0, ',', '.') }}</p>
                </div>
            </div>

            @if ($lpj->bukti_kembali_sisa_dana_path)
                <div class="flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50/30 p-3 text-xs">
                    <span class="text-emerald-800 font-medium">Lampiran Bukti Setoran Pengembalian Sisa Kas:</span>
                    <a href="{{ Storage::url($lpj->bukti_kembali_sisa_dana_path) }}" target="_blank" class="inline-flex items-center gap-1 font-bold text-emerald-700 hover:underline">
                        <x-icon name="attach_file" class="h-4 w-4" /> Lihat Dokumen
                    </a>
                </div>
            @endif
        </div>

        {{-- LPJ Item Verification Table --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
            <h2 class="font-display text-sm font-bold text-gray-900 border-b border-gray-100 pb-3">Rincian Fisik Barang & Bukti Nota</h2>

            <div class="space-y-4">
                @foreach ($lpj->items as $item)
                    @php $pItem = $item->pengajuanItem; @endphp
                    <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-4 space-y-3">
                        <div class="flex flex-wrap items-start justify-between gap-2 border-b border-gray-200/75 pb-2">
                            <div>
                                <h3 class="text-xs font-bold text-gray-900">{{ $pItem->nama_barang }}</h3>
                                <p class="text-[11px] text-gray-500">
                                    Target: <b>{{ $pItem->ruangan->nama_ruangan ?? '-' }}</b> &bull;
                                    Qty: <b>{{ $pItem->qty }} {{ $pItem->satuan }}</b>
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="text-xs font-bold text-gray-900">Total Nota: Rp {{ number_format($item->total_riil, 0, ',', '.') }}</span>
                                <span class="text-[10px] text-gray-400 block font-mono">(Rp {{ number_format($item->harga_satuan_riil, 0, ',', '.') }} / {{ $pItem->satuan }})</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 text-xs">
                            <div class="rounded-lg bg-white border border-gray-200 p-3 flex items-center justify-between">
                                <span class="text-gray-600">Scan Nota / Faktur:</span>
                                @if ($item->foto_nota_path)
                                    <a href="{{ Storage::url($item->foto_nota_path) }}" target="_blank" class="font-semibold text-brand-600 hover:underline inline-flex items-center gap-1">
                                        <x-icon name="receipt" class="h-4 w-4" /> Buka Nota
                                    </a>
                                @else
                                    <span class="text-gray-400 italic">Tidak ada lampiran</span>
                                @endif
                            </div>

                            <div class="rounded-lg bg-white border border-gray-200 p-3 flex items-center justify-between">
                                <span class="text-gray-600">Foto Fisik Barang:</span>
                                @if ($item->foto_fisik_barang_path)
                                    <a href="{{ Storage::url($item->foto_fisik_barang_path) }}" target="_blank" class="font-semibold text-brand-600 hover:underline inline-flex items-center gap-1">
                                        <x-icon name="image" class="h-4 w-4" /> Buka Foto
                                    </a>
                                @else
                                    <span class="text-gray-400 italic">Tidak ada lampiran</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Verification Decision Form --}}
        @if ($lpj->status_lpj === \App\Domains\Pengadaan\Enums\StatusLpj::Submitted)
            <form action="{{ route('admin.pengadaan.audit-lpj.verify', $lpj) }}" method="POST" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                @csrf

                <h2 class="font-display text-sm font-bold text-gray-900 border-b border-gray-100 pb-3">Keputusan Verifikasi Audit Yayasan</h2>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Catatan Audit / Evaluasi</label>
                    <textarea name="catatan_verifikasi" rows="2" placeholder="Catatan keabsahan nota atau instruksi bila ada kekurangan bukti..." class="w-full rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500"></textarea>
                    <x-input-error :messages="$errors->get('catatan_verifikasi')" class="mt-1" />
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <x-secondary-button type="submit" name="is_approved" value="0">
                        <x-icon name="assignment_late" class="h-4 w-4 mr-1 text-amber-600" /> Minta Perbaikan LPJ
                    </x-secondary-button>

                    <x-primary-button type="submit" name="is_approved" value="1">
                        <x-icon name="check_circle" class="h-4 w-4 mr-1" /> Verifikasi & Setujui LPJ
                    </x-primary-button>
                </div>
            </form>
        @else
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4 text-xs text-emerald-900 flex items-center gap-2">
                <x-icon name="verified" class="h-5 w-5 text-emerald-600" />
                <span>LPJ ini telah selesai diverifikasi oleh <b>{{ $lpj->verifiedBy->name ?? 'Auditor Yayasan' }}</b> pada {{ $lpj->verified_at?->translatedFormat('d F Y H:i') }}.</span>
            </div>
        @endif
    </div>
</x-app-layout>
