{{-- resources/views/keuangan/tagihan/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-xl font-bold text-gray-900">Rekap Tagihan Aktif — {{ $activeSiswa->nama_lengkap }}</h2>
    </x-slot>

    <div class="space-y-6" x-data="{ selected: [] }">
        @if ($autoDebitEnabled)
            <div class="rounded-2xl border border-brand-200 bg-brand-50 p-4 text-sm text-brand-800">
                Auto-debit aktif — tagihan akan otomatis dipotong dari saldo wallet saat top-up. Anda tetap bisa membayar tagihan tertentu secara manual di bawah ini.
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-white p-6">
            @if ($tagihans->isEmpty())
                <p class="text-sm text-gray-500">Tidak ada tagihan aktif saat ini.</p>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($tagihans as $tagihan)
                        <label class="flex items-center gap-4 py-4">
                            <input type="checkbox" value="{{ $tagihan->id }}" x-model="selected" class="h-4 w-4 rounded border-gray-300 text-brand-600">
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-gray-900">{{ $tagihan->jenisTagihan->nama }}</p>
                                <p class="text-xs text-gray-500">Jatuh tempo {{ $tagihan->jatuh_tempo?->translatedFormat('d M Y') ?? '-' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold text-gray-900">Rp{{ number_format($tagihan->net_amount - $tagihan->paid_amount, 0, ',', '.') }}</p>
                                <span class="text-xs uppercase text-gray-400">{{ $tagihan->status }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>

                <div x-show="selected.length > 0" x-cloak class="mt-6 flex items-center justify-end">
                    <a :href="`{{ route('keuangan.checkout.create') }}?` + selected.map(id => `tagihan_ids[]=${id}`).join('&')"
                       class="inline-flex items-center justify-center rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                        Bayar Terpilih
                    </a>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
