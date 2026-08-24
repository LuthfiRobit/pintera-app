{{-- resources/views/keuangan/checkout/sukses.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-0">
        
        {{-- Header & Subtitle (Inline style matching admin/kasus/index.blade.php) --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Pembayaran Sukses</h1>
                <p class="text-xs text-gray-500 mt-0.5">Transaksi pembayaran tagihan Anda telah sukses diproses.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> Keuangan <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Pembayaran Sukses</b>
            </p>
        </div>

        {{-- Success Ticket Card --}}
        <div class="mx-auto max-w-md bg-white rounded-2xl border border-gray-200 shadow-lg overflow-hidden p-8 text-center space-y-5">
            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 mx-auto">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
            
            <div>
                <p class="font-display text-base font-bold text-gray-900">Pembayaran Selesai!</p>
                <p class="text-xs text-gray-500 mt-1.5 leading-relaxed">Dana pembayaran tagihan dari Saldo Wallet telah sukses didebit. Rekap pembayaran dapat Anda pantau di menu Riwayat Transaksi.</p>
            </div>

            {{-- Paid Breakdown Table --}}
            <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 space-y-2 text-xs">
                <p class="font-semibold text-gray-700 border-b border-gray-200 pb-1.5 text-left uppercase tracking-wider text-[9px] text-gray-400">Rincian Pembayaran</p>
                <table class="w-full text-left text-gray-600">
                    <tbody class="divide-y divide-gray-100/50">
                        @foreach ($pembayaran->pembayaranTagihan as $pt)
                            <tr>
                                <td class="py-2 text-gray-700 font-medium leading-normal">{{ $pt->tagihan->jenisTagihan->nama }}</td>
                                <td class="py-2 text-right font-bold text-gray-900 font-mono">Rp{{ number_format($pt->amount_allocated, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="flex justify-between items-center border-t border-gray-200 pt-2 font-bold text-gray-900 mt-2">
                    <span>Total Pembayaran:</span>
                    <span class="font-mono text-sm text-brand-600">Rp{{ number_format($pembayaran->pembayaranTagihan->sum('amount_allocated'), 0, ',', '.') }}</span>
                </div>
            </div>

            <a href="{{ route('keuangan.dashboard') }}" class="w-full inline-flex items-center justify-center rounded-xl bg-brand-600 px-5 py-3 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700">
                Kembali ke Dashboard Keuangan
            </a>
        </div>
    </div>
</x-app-layout>
