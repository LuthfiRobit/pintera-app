<div x-show="activeTab === 'wallet'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
    <form method="POST" action="{{ url('/keuangan/checkout/wallet') }}" class="rounded-2xl border border-gray-200 bg-white p-6">
        @csrf
        @foreach ($tagihans as $tagihan)
            <input type="hidden" name="tagihan_ids[]" value="{{ $tagihan->id }}">
        @endforeach
        <p class="text-sm text-gray-600">Saldo Wallet saat ini: <span class="font-semibold">Rp{{ number_format($wallet?->balance ?? 0, 0, ',', '.') }}</span></p>
        @if (($wallet?->balance ?? 0) < $totalTagihan)
            <p class="mt-2 text-sm font-semibold text-red-600">Saldo tidak cukup, kurang Rp{{ number_format($totalTagihan - ($wallet?->balance ?? 0), 0, ',', '.') }}</p>
            <button type="submit" disabled class="mt-4 inline-flex cursor-not-allowed items-center justify-center rounded-xl bg-gray-300 px-4 py-2.5 text-sm font-semibold text-white">
                Bayar dari Saldo Wallet
            </button>
        @else
            <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                Bayar dari Saldo Wallet
            </button>
        @endif
    </form>
</div>
