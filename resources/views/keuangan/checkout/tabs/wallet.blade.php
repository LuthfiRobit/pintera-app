<div x-show="activeTab === 'wallet'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;" class="space-y-4">
    <form method="POST" action="{{ route('keuangan.checkout.wallet') }}" class="space-y-4">
        @csrf
        @foreach ($tagihans as $tagihan)
            <input type="hidden" name="tagihan_ids[]" value="{{ $tagihan->id }}">
        @endforeach
        
        @if (($wallet?->balance ?? 0) < $totalTagihan)
            {{-- Insufficient Balance Callout --}}
            <div class="flex items-start gap-3 rounded-xl bg-red-50 p-4 border border-red-200 text-xs sm:text-sm text-red-800">
                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600 mt-0.5 animate-pulse">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </span>
                <div>
                    <p class="font-semibold">Saldo Wallet Tidak Mencukupi</p>
                    <p class="mt-1 leading-normal text-red-700">Saldo wallet Anda saat ini adalah <b>Rp{{ number_format($wallet?->balance ?? 0, 0, ',', '.') }}</b>. Kurang sebesar <b>Rp{{ number_format($totalTagihan - ($wallet?->balance ?? 0), 0, ',', '.') }}</b> untuk melunasi tagihan ini.</p>
                </div>
            </div>

            <button type="submit" disabled class="w-full inline-flex cursor-not-allowed items-center justify-center rounded-xl bg-gray-200 px-5 py-3 text-xs font-semibold text-gray-400">
                Bayar dari Saldo Wallet (Saldo Kurang)
            </button>
        @else
            {{-- Sufficient Balance Callout --}}
            <div class="flex items-start gap-3 rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-xs sm:text-sm text-emerald-800">
                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 mt-0.5">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
                <div>
                    <p class="font-semibold">Saldo Wallet Mencukupi</p>
                    <p class="mt-1 leading-normal text-emerald-700">Saldo wallet Anda saat ini adalah <b>Rp{{ number_format($wallet?->balance ?? 0, 0, ',', '.') }}</b>. Saldo Anda akan terpotong secara instan setelah pembayaran dikonfirmasi.</p>
                </div>
            </div>

            <button type="submit" class="w-full inline-flex items-center justify-center rounded-xl bg-brand-600 px-5 py-3 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700">
                Konfirmasi & Bayar dari Saldo Wallet
            </button>
        @endif
    </form>
</div>
