{{-- resources/views/keuangan/riwayat/index.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-0">
        
        {{-- Header & Subtitle (Inline style matching kasus/index.blade.php) --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Riwayat Transaksi</h1>
                <p class="text-xs text-gray-500 mt-0.5">Pantau riwayat pembayaran tagihan sekolah, deposit wallet, dan debit otomatis {{ $activeSiswa->nama_lengkap }}.</p>
            </div>
            <div class="text-xs text-gray-400">
                Keuangan <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-600">Riwayat</b>
            </div>
        </div>

        {{-- Filter Bar --}}
        <form method="GET" action="{{ route('keuangan.riwayat.index') }}" class="flex flex-wrap items-end gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <div class="w-full sm:w-auto">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider block mb-1.5">Dari Tanggal</label>
                <input type="date" name="dari" value="{{ $dari }}" class="w-full sm:w-44 rounded-xl border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500 shadow-sm">
            </div>
            <div class="w-full sm:w-auto">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider block mb-1.5">Sampai Tanggal</label>
                <input type="date" name="sampai" value="{{ $sampai }}" class="w-full sm:w-44 rounded-xl border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500 shadow-sm">
            </div>
            <div class="w-full sm:w-auto">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider block mb-1.5">Metode Pembayaran</label>
                <select name="metode" class="w-full sm:w-48 rounded-xl border-gray-200 text-sm focus:border-brand-500 focus:ring-brand-500 shadow-sm">
                    <option value="">Semua Metode</option>
                    @foreach (['va_bri' => 'VA BRI', 'qris' => 'QRIS', 'wallet_saldo' => 'Saldo Wallet', 'wallet_auto' => 'Auto-Debit Wallet', 'transfer_manual' => 'Transfer Manual', 'cash' => 'Tunai'] as $value => $label)
                        <option value="{{ $value }}" @selected($metode === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-700 transition w-full sm:w-auto">
                    Terapkan Filter
                </button>
                @if ($dari || $sampai || $metode)
                    <a href="{{ route('keuangan.riwayat.index') }}" class="text-sm font-semibold text-gray-500 hover:text-gray-700 transition">Reset</a>
                @endif
            </div>
        </form>

        {{-- Transactions List Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
            @if ($pembayarans->isEmpty())
                <div class="text-center py-8">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-50 text-gray-400 mx-auto mb-3">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </span>
                    <p class="text-sm font-semibold text-gray-700">Tidak ada riwayat transaksi</p>
                    <p class="text-xs text-gray-400 mt-1">
                        @if ($filterActive)
                            Tidak ada transaksi yang cocok dengan filter tanggal/metode saat ini.
                        @else
                            Belum ada riwayat transaksi pembayaran terdaftar untuk siswa ini.
                        @endif
                    </p>
                </div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($pembayarans as $pembayaran)
                        @php
                            $metodeLabel = match ($pembayaran->metode) {
                                'va_bri' => 'VA BRI',
                                'qris' => 'QRIS',
                                'wallet_saldo' => 'Saldo Wallet',
                                'wallet_auto' => 'Auto-Debit Wallet',
                                'transfer_manual' => 'Transfer Manual',
                                'cash' => 'Tunai',
                                default => $pembayaran->metode,
                            };
                            $statusBadge = match ($pembayaran->status) {
                                'lunas' => ['bg-emerald-50 text-emerald-700 border-emerald-200', 'Lunas'],
                                'menunggu_pembayaran' => ['bg-amber-50 text-amber-700 border-amber-200', 'Menunggu Pembayaran'],
                                'menunggu_verifikasi' => ['bg-amber-50 text-amber-700 border-amber-200', 'Menunggu Verifikasi'],
                                'ditolak' => ['bg-rose-50 text-rose-700 border-rose-200', 'Ditolak'],
                                default => ['bg-gray-50 text-gray-600 border-gray-200', $pembayaran->status],
                            };
                            $rincianItems = $pembayaran->pembayaranTagihan;
                            $rincianLabel = $rincianItems->isEmpty()
                                ? '-'
                                : ($rincianItems->first()->tagihan?->jenisTagihan?->nama ?? '-').($rincianItems->count() > 1 ? ' +'.($rincianItems->count() - 1).' lainnya' : '');
                            if ($pembayaran->topup_status !== 'none') {
                                $rincianLabel = $rincianItems->isEmpty()
                                    ? 'Top Up Wallet'
                                    : $rincianLabel.' + Top Up Wallet';
                            }
                            $totalAmount = $pembayaran->topup_status !== 'none'
                                ? (float) ($pembayaran->amount ?? 0)
                                : ($rincianItems->isNotEmpty() ? $rincianItems->sum('amount_allocated') : ($pembayaran->amount ?? 0));
                        @endphp
                        <div class="flex flex-wrap items-center justify-between gap-4 py-4 hover:bg-gray-50/20 px-2 rounded-xl transition">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $rincianLabel }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">{{ $pembayaran->created_at->translatedFormat('d M Y H:i') }} &middot; {{ $metodeLabel }}</p>
                            </div>
                            <div class="flex items-center gap-3 w-full sm:w-auto justify-between sm:justify-end">
                                <div class="text-right">
                                    <p class="text-sm font-bold text-gray-900">Rp{{ number_format($totalAmount, 0, ',', '.') }}</p>
                                    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold mt-1 {{ $statusBadge[0] }}">{{ $statusBadge[1] }}</span>
                                </div>
                                @if ($pembayaran->status === 'lunas')
                                    <a href="{{ route('keuangan.riwayat.kwitansi', $pembayaran) }}" target="_blank" class="inline-flex items-center justify-center p-2 rounded-xl border border-gray-200 text-gray-500 hover:text-brand-600 hover:bg-gray-50 transition shadow-sm" title="Unduh Kwitansi">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 border-t border-gray-100 pt-4">
                    {{ $pembayarans->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
