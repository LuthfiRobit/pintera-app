<div x-show="activeTab === 'transfer'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
    <form method="POST" action="{{ route('keuangan.checkout.transfer') }}" enctype="multipart/form-data" class="rounded-2xl border border-gray-200 bg-white p-6 space-y-4">
        @csrf
        @foreach ($tagihans as $tagihan)
            <input type="hidden" name="tagihan_ids[]" value="{{ $tagihan->id }}">
        @endforeach
        <div>
            <label class="text-sm font-medium text-gray-700">Bank Asal Transfer</label>
            <input type="text" name="bank_origin" class="mt-1 w-full rounded-xl border-gray-300 text-sm" placeholder="Contoh: BCA">
        </div>
        <div>
            <label class="text-sm font-medium text-gray-700">Tanggal Transfer</label>
            <input type="date" name="transfer_date" required class="mt-1 w-full rounded-xl border-gray-300 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-gray-700">Bukti Transfer</label>
            <input type="file" name="transfer_proof" required accept="image/*,.pdf" class="mt-1 w-full text-sm">
        </div>
        @error('transfer_proof')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror
        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
            Kirim Bukti Transfer
        </button>
    </form>
</div>
