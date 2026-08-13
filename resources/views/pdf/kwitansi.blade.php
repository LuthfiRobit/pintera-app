<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1F2937; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        .header { display: flex; align-items: center; gap: 12px; border-bottom: 2px solid #1F2937; padding-bottom: 12px; margin-bottom: 16px; }
        .header img { height: 48px; }
        .header .lembaga-info p { margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        td { padding: 6px 0; }
        td.label { color: #5B6478; width: 40%; }
        table.rincian { border: 1px solid #E5E7EB; margin-top: 16px; }
        table.rincian th, table.rincian td { border: 1px solid #E5E7EB; padding: 8px; text-align: left; }
        table.rincian th { background: #F9FAFB; }
        .total-row td { font-weight: bold; }
        .footer { margin-top: 48px; text-align: right; font-size: 11px; color: #5B6478; }
    </style>
</head>
<body>
    <div class="header">
        @if ($yayasan?->logo)
            <img src="{{ public_path('storage/'.$yayasan->logo) }}" alt="Logo">
        @endif
        <div class="lembaga-info">
            <h1>{{ $lembaga->nama }}</h1>
            <p>{{ $lembaga->alamat ?? '-' }}</p>
        </div>
    </div>

    <h2 style="text-align: center;">KWITANSI PEMBAYARAN</h2>
    <p style="text-align: center;">No. KW-{{ $pembayaran->id }}</p>

    <table>
        <tr><td class="label">Tanggal Pembayaran</td><td>{{ $pembayaran->created_at->translatedFormat('d F Y H:i') }}</td></tr>
        <tr><td class="label">Nama Siswa</td><td>{{ $siswa->nama_lengkap }}</td></tr>
        <tr><td class="label">NIS / NISN</td><td>{{ $siswa->nis ?? '-' }} / {{ $siswa->nisn ?? '-' }}</td></tr>
        <tr><td class="label">Kelas</td><td>{{ $siswa->kelas?->nama ?? '-' }}</td></tr>
        <tr><td class="label">Metode Pembayaran</td><td>{{ match ($pembayaran->metode) {
            'va_bri' => 'VA BRI', 'qris' => 'QRIS', 'wallet_saldo' => 'Saldo Wallet',
            'wallet_auto' => 'Auto-Debit Wallet', 'transfer_manual' => 'Transfer Manual', 'cash' => 'Tunai',
            default => $pembayaran->metode,
        } }}</td></tr>
    </table>

    <table class="rincian">
        <thead>
            <tr><th>Rincian Tagihan</th><th style="text-align: right;">Nominal</th></tr>
        </thead>
        <tbody>
            @forelse ($pembayaran->pembayaranTagihan as $item)
                <tr>
                    <td>{{ $item->tagihan?->jenisTagihan?->nama ?? '-' }}</td>
                    <td style="text-align: right;">Rp{{ number_format($item->amount_allocated, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td>Rincian tidak tersedia</td><td style="text-align: right;">Rp{{ number_format($pembayaran->amount ?? 0, 0, ',', '.') }}</td></tr>
            @endforelse
            <tr class="total-row">
                <td>Total</td>
                <td style="text-align: right;">Rp{{ number_format($pembayaran->pembayaranTagihan->isNotEmpty() ? $pembayaran->pembayaranTagihan->sum('amount_allocated') : ($pembayaran->amount ?? 0), 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <p>Dokumen ini dicetak otomatis oleh sistem dan sah tanpa tanda tangan basah.</p>
        <p>Administrasi Keuangan — {{ $lembaga->nama }}</p>
    </div>
</body>
</html>
