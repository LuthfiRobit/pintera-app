{{-- resources/views/keuangan/checkout/create.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-0" x-data="{ activeTab: 'va', topupAmount: '' }">
        
        {{-- Header & Subtitle (Inline style matching admin/kasus/index.blade.php) --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Checkout Pembayaran</h1>
                <p class="text-xs text-gray-500 mt-0.5">Selesaikan pelunasan tagihan biaya pendidikan anak Anda.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> Keuangan <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Checkout</b>
            </p>
        </div>

        {{-- Main Responsive Grid --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            
            {{-- Left Column: Ringkasan Tagihan (Invoice Style) --}}
            <div class="space-y-4 lg:col-span-5">
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
                    <div class="flex items-center gap-2 border-b border-gray-100 pb-3">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </span>
                        <p class="font-display text-sm font-bold text-gray-900">Ringkasan Tagihan</p>
                    </div>

                    @if ($tagihans->isEmpty())
                        <p class="text-xs text-gray-400 py-4 text-center">Tidak ada tagihan valid yang terpilih.</p>
                    @else
                        {{-- Detailed Table --}}
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left text-gray-500">
                                <thead>
                                    <tr class="border-b border-gray-100 text-[10px] uppercase tracking-wider text-gray-400">
                                        <th class="py-2 font-semibold">Nama Tagihan</th>
                                        <th class="py-2 text-right font-semibold">Nominal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @foreach ($tagihans as $tagihan)
                                        <tr>
                                            <td class="py-2.5 font-medium text-gray-700 leading-normal">{{ $tagihan->jenisTagihan->nama }}</td>
                                            <td class="py-2.5 text-right font-bold text-gray-900 font-mono">Rp{{ number_format($tagihan->net_amount - $tagihan->paid_amount, 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Total Billing Info --}}
                        <div class="flex items-center justify-between border-t border-gray-100 pt-4 text-sm font-bold text-gray-900">
                            <span>Total Tagihan</span>
                            <span class="font-mono text-base text-brand-600">Rp{{ number_format($totalTagihan, 0, ',', '.') }}</span>
                        </div>
                    @endif

                    {{-- Top Up Wallet Integration --}}
                    <div class="mt-4 pt-4 border-t border-gray-100 space-y-2" x-show="activeTab === 'qris'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider block">Sekalian Top Up Wallet (Opsional)</label>
                        <div class="relative rounded-xl border border-gray-200 shadow-sm focus-within:border-brand-500 focus-within:ring-1 focus-within:ring-brand-500">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-bold text-gray-400">Rp</span>
                            <input type="number" min="0" step="1000" x-model="topupAmount" placeholder="0" class="block w-full border-0 bg-transparent py-2.5 pl-8 pr-3 text-xs sm:text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0 font-mono">
                        </div>
                        <p class="text-[10px] text-gray-400 leading-relaxed">Nominal ini akan digabungkan ke kode QRIS dan otomatis dikreditkan ke saldo wallet setelah pembayaran lunas.</p>
                    </div>
                </div>
            </div>

            {{-- Right Column: Payment Tabs & Form Containers --}}
            <div class="space-y-4 lg:col-span-7">
                {{-- Payment Methods Card --}}
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                    <label class="mb-2 block text-xs font-semibold text-gray-500 uppercase tracking-wider">Pilih Metode Pembayaran</label>
                    <div class="flex border-b border-gray-100 overflow-x-auto text-xs font-bold text-gray-500 scrollbar-none gap-2">
                        {{-- Tab Button: VA --}}
                        <button type="button" @click="activeTab = 'va'" :class="activeTab === 'va' ? 'border-brand-600 text-brand-600 font-extrabold' : 'border-transparent text-gray-500 hover:text-gray-700'" class="flex items-center gap-1.5 border-b-2 py-3 px-3 transition whitespace-nowrap">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                            <span>VA BRI</span>
                        </button>
                        {{-- Tab Button: QRIS --}}
                        <button type="button" @click="activeTab = 'qris'" :class="activeTab === 'qris' ? 'border-brand-600 text-brand-600 font-extrabold' : 'border-transparent text-gray-500 hover:text-gray-700'" class="flex items-center gap-1.5 border-b-2 py-3 px-3 transition whitespace-nowrap">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 4h.01M12 12h.01M16 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>QRIS</span>
                        </button>
                        {{-- Tab Button: Wallet --}}
                        <button type="button" @click="activeTab = 'wallet'" :class="activeTab === 'wallet' ? 'border-brand-600 text-brand-600 font-extrabold' : 'border-transparent text-gray-500 hover:text-gray-700'" class="flex items-center gap-1.5 border-b-2 py-3 px-3 transition whitespace-nowrap">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span>Saldo Wallet</span>
                        </button>
                        {{-- Tab Button: Transfer --}}
                        <button type="button" @click="activeTab = 'transfer'" :class="activeTab === 'transfer' ? 'border-brand-600 text-brand-600 font-extrabold' : 'border-transparent text-gray-500 hover:text-gray-700'" class="flex items-center gap-1.5 border-b-2 py-3 px-3 transition whitespace-nowrap">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                            </svg>
                            <span>Transfer Manual</span>
                        </button>
                    </div>

                    <div class="mt-4">
                        @include('portals.portal.keuangan.checkout.tabs.va')
                        @include('portals.portal.keuangan.checkout.tabs.qris')
                        @include('portals.portal.keuangan.checkout.tabs.wallet')
                        @include('portals.portal.keuangan.checkout.tabs.transfer')
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
