{{-- resources/views/keuangan/checkout/show.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-xl font-bold text-gray-900">Menunggu Pembayaran</h2>
    </x-slot>

    <div class="rounded-2xl border border-gray-200 bg-white p-6"
         x-data="{
            status: '{{ $pembayaran->status }}',
            expiredAt: {{ $pembayaran->briVirtualAccount?->expired_at?->timestamp ?? $pembayaran->briQrisPayment?->expired_at?->timestamp ?? 'null' }},
            remaining: '',
            expired: false,
            tick() {
                if (this.expiredAt === null) return;
                const diff = this.expiredAt - Math.floor(Date.now() / 1000);
                if (diff <= 0) { this.expired = true; this.remaining = '00:00'; return; }
                const m = Math.floor(diff / 60).toString().padStart(2, '0');
                const s = (diff % 60).toString().padStart(2, '0');
                this.remaining = `${m}:${s}`;
            },
            poll() {
                fetch('{{ route('keuangan.checkout.status', $pembayaran) }}')
                    .then(r => r.json())
                    .then(data => { this.status = data.status; });
            }
         }"
         x-init="tick(); setInterval(() => tick(), 1000); setInterval(() => poll(), 5000)">

        <template x-if="status === 'lunas'">
            <p class="text-sm font-semibold text-emerald-700">Pembayaran berhasil diterima. Terima kasih.</p>
        </template>

        <template x-if="status !== 'lunas' && !expired">
            <div>
                @if ($pembayaran->briVirtualAccount)
                    <p class="text-sm text-gray-500">Nomor Virtual Account BRI</p>
                    <p class="mt-1 font-mono text-2xl font-bold text-gray-900">{{ $pembayaran->briVirtualAccount->va_number }}</p>
                    <p class="mt-1 text-sm text-gray-500">Nominal: Rp{{ number_format($pembayaran->briVirtualAccount->amount, 0, ',', '.') }}</p>
                @elseif ($pembayaran->briQrisPayment)
                    <p class="text-sm text-gray-500">Kode QRIS</p>
                    <p class="mt-1 font-mono text-lg font-bold text-gray-900">{{ $pembayaran->briQrisPayment->qr_code }}</p>
                    <p class="mt-1 text-sm text-gray-500">Nominal: Rp{{ number_format($pembayaran->briQrisPayment->amount, 0, ',', '.') }}</p>
                @endif
                <p class="mt-4 text-sm text-gray-500">Sisa waktu: <span class="font-mono font-semibold" x-text="remaining"></span></p>
            </div>
        </template>

        <template x-if="status !== 'lunas' && expired">
            <div>
                <p class="text-sm font-semibold text-red-600">Kode pembayaran sudah kadaluarsa.</p>
                <a href="{{ route('keuangan.checkout.create') }}" class="mt-4 inline-flex items-center justify-center rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white">
                    Buat Ulang
                </a>
            </div>
        </template>
    </div>
</x-app-layout>
