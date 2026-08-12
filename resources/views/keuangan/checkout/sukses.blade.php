{{-- resources/views/keuangan/checkout/sukses.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-xl font-bold text-gray-900">Pembayaran Berhasil</h2>
    </x-slot>

    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6">
        <p class="text-sm font-semibold text-emerald-800">Pembayaran dari Saldo Wallet berhasil diproses.</p>
        <ul class="mt-4 space-y-2">
            @foreach ($pembayaran->pembayaranTagihan as $pt)
                <li class="flex justify-between text-sm text-emerald-900">
                    <span>{{ $pt->tagihan->jenisTagihan->nama }}</span>
                    <span class="font-semibold">Rp{{ number_format($pt->amount_allocated, 0, ',', '.') }}</span>
                </li>
            @endforeach
        </ul>
        <a href="{{ route('keuangan.dashboard') }}" class="mt-6 inline-flex items-center justify-center rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white">
            Kembali ke Dashboard
        </a>
    </div>
</x-app-layout>
