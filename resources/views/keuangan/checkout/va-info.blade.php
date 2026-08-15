<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Bayar via Virtual Account BRI') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-5">
                @if ($totalTagihan > 0)
                    <div class="rounded-xl bg-brand-50 border border-brand-100 p-4">
                        <p class="text-[10px] font-semibold text-brand-700 uppercase tracking-wider">Saran nominal transfer</p>
                        <p class="text-xl font-bold text-brand-800 mt-1">Rp {{ number_format($totalTagihan, 0, ',', '.') }}</p>
                        <p class="text-xs text-gray-500 mt-1 leading-relaxed">Ini nominal total tagihan yang Anda pilih — transfer sesuai nominal ini supaya tagihan langsung lunas otomatis.</p>
                    </div>
                @endif

                <div class="rounded-xl bg-gray-50 p-4 border border-gray-100 flex items-center justify-between" x-data>
                    <div>
                        <p class="text-[9px] font-semibold text-gray-400 uppercase tracking-wider">Nomor Virtual Account (BRIVA) Anda</p>
                        <p class="font-mono text-base font-bold text-gray-800 mt-1">{{ $va->va_number }}</p>
                        <p class="text-[10px] text-gray-400 mt-1">Nomor ini tetap, bisa dipakai kapan saja.</p>
                    </div>
                    <button
                        @click="
                            navigator.clipboard.writeText('{{ $va->va_number }}');
                            $store.toast.push('success', 'Nomor VA berhasil disalin!');
                        "
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition shadow-sm"
                        title="Salin VA"
                        aria-label="Salin VA"
                    >
                        <svg class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                        </svg>
                        <span>Salin VA</span>
                    </button>
                </div>

                <p class="text-sm text-gray-500 leading-relaxed">
                    Transfer ke nomor ini lewat ATM BRI, BRImo, BRILink, atau bank lain. Saldo akan bertambah otomatis begitu transfer diterima, dan tagihan jatuh tempo akan langsung dilunasi otomatis kalau saldo mencukupi.
                </p>

                <a href="{{ route('keuangan.tagihan.index') }}" class="inline-flex w-full items-center justify-center rounded-xl bg-gray-100 px-5 py-3 text-xs font-semibold text-gray-700 hover:bg-gray-200 transition">
                    Kembali ke Daftar Tagihan
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
