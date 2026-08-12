{{-- resources/views/keuangan/checkout/menunggu-verifikasi.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-xl font-bold text-gray-900">Menunggu Verifikasi</h2>
    </x-slot>

    <div class="rounded-2xl border border-gray-200 bg-white p-6">
        <p class="text-sm text-gray-700">Bukti transfer Anda sudah diterima dan sedang menunggu verifikasi oleh admin. Anda akan menerima notifikasi setelah pembayaran ini diverifikasi.</p>
        <p class="mt-3 text-sm text-gray-500">Nominal: Rp{{ number_format($pembayaran->manualRequest?->amount ?? 0, 0, ',', '.') }}</p>
        <a href="{{ route('keuangan.dashboard') }}" class="mt-6 inline-flex items-center justify-center rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
            Kembali ke Dashboard
        </a>
    </div>
</x-app-layout>
