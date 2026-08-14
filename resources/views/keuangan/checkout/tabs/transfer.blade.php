<div x-show="activeTab === 'transfer'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;" class="space-y-4">
    <form method="POST" action="{{ route('keuangan.checkout.transfer') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        @foreach ($tagihans as $tagihan)
            <input type="hidden" name="tagihan_ids[]" value="{{ $tagihan->id }}">
        @endforeach

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider block mb-1.5">Bank Asal Transfer</label>
                <input type="text" name="bank_origin" required class="w-full rounded-xl border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500 shadow-sm" placeholder="Contoh: BCA, Mandiri, BNI">
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider block mb-1.5">Tanggal Transfer</label>
                <input type="date" name="transfer_date" required class="w-full rounded-xl border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500 shadow-sm">
            </div>
        </div>

        <div>
            <label for="transfer_proof" class="text-xs font-semibold text-gray-500 uppercase tracking-wider block mb-1.5">Bukti Transfer (Gambar / PDF)</label>
            <div class="relative flex items-center justify-center rounded-xl border-2 border-dashed border-gray-200 bg-gray-50 p-4 transition-colors hover:bg-gray-100/50 focus-within:border-brand-500">
                <input id="transfer_proof" type="file" name="transfer_proof" required accept="image/*,.pdf" class="absolute inset-0 h-full w-full opacity-0 cursor-pointer" @change="$el.nextElementSibling.innerText = $el.files[0] ? $el.files[0].name : 'Pilih file bukti transfer...'">
                <p class="text-xs font-medium text-gray-500">Pilih file bukti transfer...</p>
            </div>
        </div>
        
        @error('transfer_proof')
            <p class="text-xs font-semibold text-red-600">{{ $message }}</p>
        @enderror

        <button type="submit" class="w-full inline-flex items-center justify-center rounded-xl bg-brand-600 px-5 py-3 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700">
            Kirim Bukti Transfer & Ajukan Verifikasi
        </button>
    </form>
</div>
