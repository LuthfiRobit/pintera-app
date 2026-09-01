<x-app-layout>
    @php
        $discountLabels = ['fixed' => 'Potongan Tetap', 'persen' => 'Potongan Persentase', 'gabungan' => 'Potongan Gabungan'];
        $discountLabel = $discountLabels[$tagihan->discount_type] ?? 'Potongan';
        $sisa = (float) $tagihan->net_amount - (float) $tagihan->paid_amount;
    @endphp
    <div class="mx-auto max-w-2xl space-y-4 px-4 sm:px-0">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Detail Tagihan</h1>
                <p class="text-xs text-gray-500 mt-0.5">{{ $tagihan->jenisTagihan->nama }}</p>
            </div>
            <a href="{{ route('keuangan.tagihan.index') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50">
                <x-icon name="arrow_back" class="h-4 w-4 text-gray-500" />
                <span>Kembali ke Daftar Tagihan</span>
            </a>
        </div>

        @if ($tagihan->perlu_ditinjau_ulang)
            <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                    <x-icon name="notifications" class="h-4 w-4" />
                </span>
                <p class="font-semibold">Nominal sedang ditinjau ulang oleh admin, sementara belum bisa dibayar.</p>
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3.5">
                <div>
                    <p class="text-sm font-bold text-gray-900">{{ $tagihan->jenisTagihan->nama }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">Jatuh tempo: {{ $tagihan->jatuh_tempo?->translatedFormat('d M Y') ?? '-' }}</p>
                </div>
                <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider text-[10px] {{ $tagihan->status === 'sebagian' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-red-50 text-red-700 border-red-200' }}">
                    {{ str_replace('_', ' ', $tagihan->status) }}
                </span>
            </div>

            <div class="space-y-2.5 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-gray-500">Nominal Awal</span>
                    <span class="font-medium text-gray-900 font-mono">Rp{{ number_format((float) $tagihan->total_tagihan, 0, ',', '.') }}</span>
                </div>
                @if ((float) $tagihan->discount_amount > 0)
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">{{ $discountLabel }}</span>
                        <span class="font-medium text-emerald-600 font-mono">-Rp{{ number_format((float) $tagihan->discount_amount, 0, ',', '.') }}</span>
                    </div>
                @endif
                <div class="flex items-center justify-between border-t border-gray-100 pt-2.5">
                    <span class="font-semibold text-gray-900">Nominal Akhir</span>
                    <span class="font-bold text-gray-900 font-mono">Rp{{ number_format((float) $tagihan->net_amount, 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-500">Sudah Dibayar</span>
                    <span class="font-medium text-emerald-600 font-mono">Rp{{ number_format((float) $tagihan->paid_amount, 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between border-t border-gray-100 pt-2.5">
                    <span class="font-semibold text-gray-900">Sisa Tagihan</span>
                    <span class="font-bold text-gray-900 font-mono">Rp{{ number_format($sisa, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
