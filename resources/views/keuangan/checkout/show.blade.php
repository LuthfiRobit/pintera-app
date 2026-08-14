{{-- resources/views/keuangan/checkout/show.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-0">
        
        {{-- Header & Subtitle (Inline style matching admin/kasus/index.blade.php) --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Menunggu Pembayaran</h1>
                <p class="text-xs text-gray-500 mt-0.5">Selesaikan pelunasan transaksi tagihan Anda.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> Keuangan <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Instruksi Pembayaran</b>
            </p>
        </div>

        {{-- Payment Ticket Card --}}
        <div class="mx-auto max-w-md bg-white rounded-2xl border border-gray-200 shadow-lg overflow-hidden"
             x-data="{
                status: '{{ $pembayaran->status }}',
                expiredAt: {{ $pembayaran->briVirtualAccount?->expired_at?->timestamp ?? $pembayaran->briQrisPayment?->expired_at?->timestamp ?? 'null' }},
                remaining: '',
                expired: false,
                pollIntervalId: null,
                tick() {
                    if (this.expiredAt === null) return;
                    const diff = this.expiredAt - Math.floor(Date.now() / 1000);
                    if (diff <= 0) {
                        this.expired = true;
                        this.remaining = '00:00';
                        if (this.pollIntervalId !== null) { clearInterval(this.pollIntervalId); this.pollIntervalId = null; }
                        return;
                    }
                    const m = Math.floor(diff / 60).toString().padStart(2, '0');
                    const s = (diff % 60).toString().padStart(2, '0');
                    this.remaining = `${m}:${s}`;
                },
                poll() {
                    fetch('{{ route('keuangan.checkout.status', $pembayaran) }}')
                        .then(r => r.json())
                        .then(data => {
                            this.status = data.status;
                            if (this.status === 'lunas' && this.pollIntervalId !== null) {
                                clearInterval(this.pollIntervalId);
                                this.pollIntervalId = null;
                            }
                        });
                }
             }"
             x-init="tick(); setInterval(() => tick(), 1000); pollIntervalId = setInterval(() => poll(), 5000)">

            {{-- 1. Success Ticket State --}}
            <template x-if="status === 'lunas'">
                <div class="p-8 text-center bg-emerald-50 text-emerald-800 border-b border-emerald-100 space-y-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 mx-auto">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    <div>
                        <p class="font-display text-base font-bold">Pembayaran Berhasil Diterima</p>
                        <p class="text-xs text-emerald-600 mt-1">Terima kasih, kewajiban pembayaran tagihan Anda telah lunas didebit secara otomatis.</p>
                    </div>
                    <a href="{{ route('keuangan.dashboard') }}" class="mt-4 inline-flex items-center justify-center rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                        Kembali ke Dashboard
                    </a>
                </div>
            </template>

            {{-- 2. Pending Payment Ticket State --}}
            <template x-if="status !== 'lunas' && !expired">
                <div>
                    {{-- Active Timer Header --}}
                    <div class="bg-amber-50 border-b border-amber-100 py-3 text-center">
                        <p class="text-[9px] font-semibold text-amber-700 uppercase tracking-widest">Sisa Waktu Pembayaran</p>
                        <p class="font-mono text-2xl font-black text-amber-900 mt-1" x-text="remaining"></p>
                    </div>

                    {{-- Invoice Body --}}
                    <div class="p-6 space-y-5">
                        
                        {{-- Virtual Account BRI Instruction --}}
                        @if ($pembayaran->briVirtualAccount)
                            <div class="space-y-3">
                                <div class="text-center">
                                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Virtual Account BRI</p>
                                </div>
                                <div class="flex items-center justify-between rounded-xl bg-gray-50 px-4 py-3 border border-gray-100">
                                    <div>
                                        <p class="text-[9px] font-semibold text-gray-400 uppercase">Nomor Virtual Account</p>
                                        <p class="font-mono text-lg font-bold text-gray-800 mt-0.5">{{ $pembayaran->briVirtualAccount->va_number }}</p>
                                    </div>
                                    <button 
                                        @click="
                                            navigator.clipboard.writeText('{{ $pembayaran->briVirtualAccount->va_number }}'); 
                                            $store.toast ? $store.toast.push('success', 'Nomor VA berhasil disalin!') : alert('Nomor VA berhasil disalin!');
                                        " 
                                        type="button" 
                                        class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg hover:bg-gray-150 transition"
                                        title="Salin VA"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="flex justify-between text-xs py-1">
                                    <span class="text-gray-400">Total Nominal VA:</span>
                                    <span class="font-bold text-gray-900 font-mono">Rp{{ number_format($pembayaran->briVirtualAccount->amount, 0, ',', '.') }}</span>
                                </div>
                            </div>

                        {{-- QRIS Code Instruction --}}
                        @elseif ($pembayaran->briQrisPayment)
                            <div class="space-y-4">
                                <div class="text-center">
                                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Scan Kode QRIS Pintera</p>
                                </div>
                                {{-- Rendering QRIS Code dynamically as a real image --}}
                                <div class="flex flex-col items-center justify-center p-4 bg-white border border-gray-100 rounded-2xl shadow-inner max-w-xs mx-auto">
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data={{ urlencode($pembayaran->briQrisPayment->qr_code) }}" alt="QRIS Code" class="h-40 w-40 object-contain">
                                    <span class="mt-2.5 text-[9px] uppercase font-bold text-gray-400 tracking-widest">Gunakan Aplikasi QRIS Anda</span>
                                </div>
                                <div class="flex justify-between text-xs py-1 border-t border-gray-50 pt-3">
                                    <span class="text-gray-400">Total Nominal QRIS:</span>
                                    <span class="font-bold text-gray-900 font-mono">Rp{{ number_format($pembayaran->briQrisPayment->amount, 0, ',', '.') }}</span>
                                </div>
                            </div>
                        @endif

                        {{-- Top Up Breakdown details --}}
                        @if ($pembayaran->topup_status !== 'none')
                            @php
                                $porsiTagihan = $pembayaran->pembayaranTagihan->sum('amount_allocated');
                                $porsiTopup = (float) $pembayaran->amount - $porsiTagihan;
                            @endphp
                            <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 text-xs text-gray-500 space-y-1.5">
                                <p class="font-semibold text-gray-700 border-b border-gray-200 pb-1.5 mb-1.5">Rincian Alokasi Dana</p>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Tagihan Sekolah:</span>
                                    <span class="font-bold text-gray-800 font-mono">Rp{{ number_format($porsiTagihan, 0, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Top Up Saldo Wallet:</span>
                                    <span class="font-bold text-gray-800 font-mono">Rp{{ number_format($porsiTopup, 0, ',', '.') }}</span>
                                </div>
                            </div>
                        @endif

                        {{-- Footer Quick Actions --}}
                        <div class="flex items-center gap-2 pt-2">
                            <a href="{{ route('keuangan.tagihan.index') }}" class="flex-1 inline-flex items-center justify-center rounded-xl border border-gray-200 px-4 py-2.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition">
                                Lihat Tagihan
                            </a>
                            <a href="{{ route('keuangan.dashboard') }}" class="flex-1 inline-flex items-center justify-center rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-semibold text-white hover:bg-brand-700 transition shadow-sm">
                                Ke Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </template>

            {{-- 3. Expired State --}}
            <template x-if="status !== 'lunas' && expired">
                <div class="p-8 text-center space-y-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-rose-50 text-rose-500 mx-auto">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    <div>
                        <p class="font-display text-base font-bold text-rose-600">Kode Pembayaran Kadaluarsa</p>
                        <p class="text-xs text-gray-400 mt-1">Batas waktu pelunasan tagihan ini telah terlampaui. Silakan buat kode transaksi baru.</p>
                    </div>
                    <a href="{{ route('keuangan.tagihan.index') }}" class="mt-4 inline-flex items-center justify-center rounded-xl bg-brand-600 px-5 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700">
                        Buat Kode Baru
                    </a>
                </div>
            </template>
        </div>
    </div>
</x-app-layout>
