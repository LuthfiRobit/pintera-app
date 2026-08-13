{{-- resources/views/keuangan/riwayat/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-xl font-bold text-gray-900">Riwayat Transaksi — {{ $activeSiswa->nama_lengkap }}</h2>
    </x-slot>

    <div class="space-y-6">
        <form method="GET" action="{{ route('keuangan.riwayat.index') }}" class="flex flex-wrap items-end gap-4 rounded-2xl border border-gray-200 bg-white p-4">
            <div>
                <label class="text-xs font-semibold text-gray-500">Dari Tanggal</label>
                <input type="date" name="dari" value="{{ $dari }}" class="mt-1 rounded-xl border-gray-300 text-sm">
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500">Sampai Tanggal</label>
                <input type="date" name="sampai" value="{{ $sampai }}" class="mt-1 rounded-xl border-gray-300 text-sm">
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500">Metode</label>
                <select name="metode" class="mt-1 rounded-xl border-gray-300 text-sm">
                    <option value="">Semua Metode</option>
                    @foreach (['va_bri' => 'VA BRI', 'qris' => 'QRIS', 'wallet_saldo' => 'Saldo Wallet', 'wallet_auto' => 'Auto-Debit Wallet', 'transfer_manual' => 'Transfer Manual', 'cash' => 'Tunai'] as $value => $label)
                        <option value="{{ $value }}" @selected($metode === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white">
                Terapkan Filter
            </button>
            @if ($dari || $sampai || $metode)
                <a href="{{ route('keuangan.riwayat.index') }}" class="text-sm font-semibold text-gray-500 hover:text-gray-700">Reset</a>
            @endif
        </form>

        <div class="rounded-2xl border border-gray-200 bg-white p-6">
            @if ($pembayarans->isEmpty())
                <p class="text-sm text-gray-500">
                    @if ($dari || $sampai || $metode)
                        Tidak ada transaksi yang cocok dengan filter ini.
                    @else
                        Belum ada riwayat transaksi.
                    @endif
                </p>
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
                                : $rincianItems->first()->tagihan->jenisTagihan->nama.($rincianItems->count() > 1 ? ' +'.($rincianItems->count() - 1).' lainnya' : '');
                        @endphp
                        <div class="flex flex-wrap items-center justify-between gap-3 py-4">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $rincianLabel }}</p>
                                <p class="text-xs text-gray-500">{{ $pembayaran->created_at->translatedFormat('d M Y H:i') }} &middot; {{ $metodeLabel }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $statusBadge[0] }}">{{ $statusBadge[1] }}</span>
                                @if ($pembayaran->status === 'lunas')
                                    <a href="{{ route('keuangan.riwayat.kwitansi', $pembayaran) }}" target="_blank" class="text-sm font-semibold text-brand-600 hover:text-brand-700">
                                        Unduh Kwitansi
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">
                    {{ $pembayarans->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
