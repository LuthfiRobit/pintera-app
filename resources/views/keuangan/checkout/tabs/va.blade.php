<div x-show="activeTab === 'va'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;" class="space-y-4">
    <form method="POST" action="{{ route('keuangan.checkout.va') }}" class="space-y-4">
        @csrf
        @foreach ($tagihans as $tagihan)
            <input type="hidden" name="tagihan_ids[]" value="{{ $tagihan->id }}">
        @endforeach
        <div class="flex items-start gap-3 rounded-xl bg-gray-50 p-4 border border-gray-100 text-xs sm:text-sm text-gray-600">
            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-brand-100 text-brand-600 mt-0.5">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
            <div>
                <p class="font-semibold text-gray-800">Pembayaran Virtual Account BRI</p>
                <p class="mt-1 leading-relaxed text-gray-500">Nomor Virtual Account BRI Anda tetap sama setiap saat. Anda dapat melakukan pembayaran via ATM BRI, BRILink, BRImo, atau transfer dari bank lain.</p>
            </div>
        </div>

        <button type="submit" class="w-full inline-flex items-center justify-center rounded-xl bg-brand-600 px-5 py-3 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700">
            Buat Kode Virtual Account BRI
        </button>
    </form>
</div>
