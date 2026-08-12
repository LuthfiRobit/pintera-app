<div x-show="activeTab === 'qris'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
    <form method="POST" action="{{ url('/keuangan/checkout/qris') }}" class="rounded-2xl border border-gray-200 bg-white p-6">
        @csrf
        @foreach ($tagihans as $tagihan)
            <input type="hidden" name="tagihan_ids[]" value="{{ $tagihan->id }}">
        @endforeach
        <input type="hidden" name="topup_amount" x-bind:value="topupAmount">
        <p class="text-sm text-gray-600">Bayar via QRIS. Kode QR akan ditampilkan setelah Anda klik tombol di bawah.</p>
        <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
            Buat Kode QRIS
        </button>
    </form>
</div>
