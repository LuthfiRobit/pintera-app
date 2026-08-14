{{-- resources/views/keuangan/tagihan/index.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-0" x-data="{ selected: [] }">
        
        {{-- Header & Subtitle (Inline style matching kasus/index.blade.php) --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Rekap Tagihan Aktif</h1>
                <p class="text-xs text-gray-500 mt-0.5">Lihat rekap komponen biaya sekolah dan lunasi tagihan {{ $activeSiswa->nama_lengkap }} secara mandiri.</p>
            </div>
            <div class="text-xs text-gray-400">
                Keuangan <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-600">Tagihan</b>
            </div>
        </div>

        {{-- Billing Engine & Auto-debit Info --}}
        @if ($autoDebitEnabled)
            <div class="flex items-start gap-3 rounded-2xl border border-brand-200 bg-brand-50/50 p-4 text-xs text-brand-800">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-brand-100 text-brand-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </span>
                <div>
                    <p class="font-semibold text-brand-900">Sistem Auto-Debit Aktif</p>
                    <p class="mt-0.5 text-gray-500 leading-relaxed">Setiap kali saldo wallet didepositkan (top-up), sistem akan langsung mendebit tagihan prioritas secara otomatis. Anda tetap dapat mencicil/membayar tagihan pilihan Anda secara instan di bawah ini.</p>
                </div>
            </div>
        @endif

        {{-- Billing Card (List and payment select) --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </span>
                    <p class="font-display text-sm font-bold text-gray-900">Daftar Tagihan Tertunggak</p>
                </div>
                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-[10px] font-semibold text-gray-600" x-text="`${selected.length} dipilih`"></span>
            </div>

            @if ($tagihans->isEmpty())
                <div class="text-center py-8">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-green-50 text-green-500 mx-auto mb-3">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    <p class="text-sm font-semibold text-gray-700">Semua Tagihan Lunas!</p>
                    <p class="text-xs text-gray-400 mt-1">Tidak ada tagihan aktif tertunggak saat ini.</p>
                </div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($tagihans as $tagihan)
                        <label class="flex items-start gap-4 py-4 cursor-pointer hover:bg-gray-50/50 transition-colors px-2 rounded-xl">
                            <input 
                                type="checkbox" 
                                value="{{ $tagihan->id }}" 
                                x-model="selected" 
                                class="mt-1 h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                            >
                            <div class="flex-1">
                                <p class="text-xs font-semibold text-gray-900">{{ $tagihan->jenisTagihan->nama }}</p>
                                <p class="text-[10px] text-gray-400 mt-0.5">Jatuh tempo: {{ $tagihan->jatuh_tempo?->translatedFormat('d M Y') ?? '-' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-bold text-gray-900">Rp{{ number_format($tagihan->net_amount - $tagihan->paid_amount, 0, ',', '.') }}</p>
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[8px] font-bold tracking-wider uppercase mt-1 {{ $tagihan->status === 'sebagian' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800' }}">
                                    {{ str_replace('_', ' ', $tagihan->status) }}
                                </span>
                            </div>
                        </label>
                    @endforeach
                </div>

                {{-- Payment Action --}}
                <div x-show="selected.length > 0" x-cloak class="mt-6 flex items-center justify-end border-t border-gray-100 pt-4" style="display: none;">
                    <a :href="`{{ route('keuangan.checkout.create') }}?` + selected.map(id => `tagihan_ids[]=${id}`).join('&')"
                       class="inline-flex items-center justify-center rounded-xl bg-brand-600 px-5 py-3 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700 w-full sm:w-auto text-center gap-1.5">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        Bayar Terpilih (<span x-text="selected.length"></span>)
                    </a>
                </div>
            @endif
        </div>

    </div>
</x-app-layout>
