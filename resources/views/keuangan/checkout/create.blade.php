{{-- resources/views/keuangan/checkout/create.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-xl font-bold text-gray-900">Checkout Pembayaran</h2>
    </x-slot>

    <div class="space-y-6" x-data="{ activeTab: 'va' }">
        <div class="rounded-2xl border border-gray-200 bg-white p-6">
            <p class="text-sm font-semibold text-gray-900">Tagihan Terpilih</p>
            @if ($tagihans->isEmpty())
                <p class="mt-2 text-sm text-gray-500">Tidak ada tagihan valid yang dipilih.</p>
            @else
                <ul class="mt-3 space-y-2">
                    @foreach ($tagihans as $tagihan)
                        <li class="flex justify-between text-sm">
                            <span class="text-gray-700">{{ $tagihan->jenisTagihan->nama }}</span>
                            <span class="font-semibold text-gray-900">Rp{{ number_format($tagihan->net_amount - $tagihan->paid_amount, 0, ',', '.') }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-4 flex justify-between border-t border-gray-100 pt-3 text-sm font-bold">
                    <span>Total</span>
                    <span>Rp{{ number_format($totalTagihan, 0, ',', '.') }}</span>
                </div>
            @endif
        </div>

        <div>
            <div class="flex border-b border-gray-200 overflow-x-auto text-sm font-semibold text-gray-500 scrollbar-none">
                <button type="button" @click="activeTab = 'va'" :class="activeTab === 'va' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="flex items-center gap-2 border-b-2 py-3 px-4 transition whitespace-nowrap">
                    <span>VA BRI</span>
                </button>
                <button type="button" @click="activeTab = 'qris'" :class="activeTab === 'qris' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="flex items-center gap-2 border-b-2 py-3 px-4 transition whitespace-nowrap">
                    <span>QRIS</span>
                </button>
                <button type="button" @click="activeTab = 'wallet'" :class="activeTab === 'wallet' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="flex items-center gap-2 border-b-2 py-3 px-4 transition whitespace-nowrap">
                    <span>Saldo Wallet</span>
                </button>
                <button type="button" @click="activeTab = 'transfer'" :class="activeTab === 'transfer' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="flex items-center gap-2 border-b-2 py-3 px-4 transition whitespace-nowrap">
                    <span>Transfer Manual</span>
                </button>
            </div>

            <div class="mt-6">
                @include('keuangan.checkout.tabs.va')
                @include('keuangan.checkout.tabs.qris')
                @include('keuangan.checkout.tabs.wallet')
                @include('keuangan.checkout.tabs.transfer')
            </div>
        </div>
    </div>
</x-app-layout>
