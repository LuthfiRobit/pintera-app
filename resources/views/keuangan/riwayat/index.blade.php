{{-- resources/views/keuangan/riwayat/index.blade.php --}}
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-0">
        
        {{-- Header & Subtitle (Inline style matching admin/kasus/index.blade.php) --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Riwayat Transaksi</h1>
                <p class="text-xs text-gray-500 mt-0.5">Pantau riwayat pembayaran tagihan sekolah, deposit wallet, dan debit otomatis {{ $activeSiswa->nama_lengkap }}.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> Keuangan <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Riwayat</b>
            </p>
        </div>

        {{-- Compact Statistic Cards (Matching admin/kasus/index.blade.php) --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            {{-- Card 1: Total Pembayaran Lunas --}}
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Total Pembayaran Lunas</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">Rp{{ number_format($totalLunasNominal, 0, ',', '.') }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-emerald-600 font-semibold">Dana Sukses</span>
            </div>

            {{-- Card 2: Menunggu Verifikasi --}}
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-amber-600">Menunggu Verifikasi</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalMenungguCount }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-amber-600 font-semibold">Proses Verifikasi</span>
            </div>

            {{-- Card 3: Total Transaksi --}}
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card transition hover:shadow-elevated">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Transaksi</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalTransaksiCount }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-medium text-gray-400">Akumulasi Riwayat</span>
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

        {{-- Table Card (Matching admin/kasus/index.blade.php) --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-card">
            {{-- Table Header --}}
            <div class="flex flex-col gap-2.5 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2.5">
                    <p class="font-display text-sm font-bold text-gray-900">Daftar Riwayat Transaksi</p>
                    <span class="inline-flex items-center rounded-full bg-brand-50 border border-brand-200 px-2.5 py-0.5 text-xs font-semibold text-brand-700">{{ $pembayarans->total() }} Data Transaksi</span>
                </div>
            </div>

            @if ($pembayarans->isEmpty())
                <div class="text-center py-12">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 mx-auto mb-3">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </span>
                    <p class="text-sm font-semibold text-gray-700">Tidak ada riwayat transaksi</p>
                    <p class="text-xs text-gray-400 mt-1 max-w-sm mx-auto">
                        @if ($filterActive)
                            Tidak ada transaksi yang cocok dengan filter tanggal/metode saat ini.
                        @else
                            Belum ada riwayat transaksi pembayaran terdaftar untuk siswa ini.
                        @endif
                    </p>
                </div>
            @else
                {{-- Table --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-100 bg-gray-50/50">
                                <th class="sticky left-0 z-10 bg-gray-50/50 px-5 py-3 w-36">Aksi</th>
                                <th class="px-5 py-3">Transaksi</th>
                                <th class="px-5 py-3">Metode</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3">Nominal</th>
                                <th class="px-5 py-3">Waktu Transaksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
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
                                <tr class="transition hover:bg-gray-50">
                                    <td class="sticky left-0 z-10 bg-white px-5 py-3">
                                        <x-table-actions>
                                            @if ($pembayaran->status === 'lunas')
                                                <a href="{{ route('keuangan.riwayat.kwitansi', $pembayaran) }}" target="_blank" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                                    <svg class="h-4 w-4 text-gray-550 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                    </svg>
                                                    Unduh Kwitansi
                                                </a>
                                            @else
                                                <span class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start text-xs leading-5 text-gray-400">
                                                    Belum ada aksi
                                                </span>
                                            @endif
                                        </x-table-actions>
                                    </td>
                                    <td class="px-5 py-3.5 font-medium text-gray-900">
                                        {{ $rincianLabel }}
                                    </td>
                                    <td class="px-5 py-3.5 text-gray-600">
                                        {{ $metodeLabel }}
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $statusBadge[0] }}">{{ $statusBadge[1] }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 font-bold text-gray-900">
                                        Rp{{ number_format($totalAmount, 0, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-3.5 text-gray-500 font-mono text-xs">
                                        {{ $pembayaran->created_at->translatedFormat('d M Y H:i') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination Footer --}}
                <div class="border-t border-gray-200 px-5 py-4">
                    {{ $pembayarans->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
