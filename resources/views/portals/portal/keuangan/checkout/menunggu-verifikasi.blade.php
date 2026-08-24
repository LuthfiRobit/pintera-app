{{-- resources/views/keuangan/checkout/menunggu-verifikasi.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-0">
        
        {{-- Header & Subtitle (Inline style matching admin/kasus/index.blade.php) --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Menunggu Verifikasi</h1>
                <p class="text-xs text-gray-500 mt-0.5">Bukti transfer Anda sedang diproses oleh tim admin kami.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> Keuangan <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Tunggu Verifikasi</b>
            </p>
        </div>

        {{-- Verification Card --}}
        <div class="mx-auto max-w-md bg-white rounded-2xl border border-gray-200 shadow-lg overflow-hidden p-8 text-center space-y-5">
            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 text-amber-600 mx-auto">
                <svg class="h-6 w-6 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
            
            <div>
                <p class="font-display text-base font-bold text-gray-900">Bukti Transfer Diterima</p>
                <p class="text-xs text-gray-500 mt-1.5 leading-relaxed">Terima kasih. Berkas bukti transfer manual Anda telah kami simpan. Tim administrasi sekolah akan segera memverifikasi nominal kiriman dana Anda.</p>
            </div>

            <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 text-xs text-gray-500">
                <div class="flex justify-between items-center">
                    <span>Nominal Transfer:</span>
                    <span class="font-bold text-gray-900 font-mono text-sm">Rp{{ number_format($pembayaran->manualRequest?->amount ?? 0, 0, ',', '.') }}</span>
                </div>
            </div>

            <a href="{{ route('keuangan.dashboard') }}" class="w-full inline-flex items-center justify-center rounded-xl bg-brand-600 px-5 py-3 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700">
                Kembali ke Dashboard Keuangan
            </a>
        </div>
    </div>
</x-app-layout>
