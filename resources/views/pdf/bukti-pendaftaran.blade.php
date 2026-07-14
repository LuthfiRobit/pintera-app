<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        h1 { font-size: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        td { padding: 6px 0; }
        td.label { color: #5B6478; width: 40%; }
    </style>
</head>
<body>
    <h1>Bukti Pendaftaran SPMB — {{ $lembaga->nama }}</h1>
    <p>Kode Pendaftaran: <strong>{{ $pendaftaran->kode_pendaftaran }}</strong></p>

    <table>
        <tr><td class="label">Nama Calon Murid</td><td>{{ $pendaftaran->calonMurid->nama_lengkap }}</td></tr>
        <tr><td class="label">Jalur</td><td>{{ $pendaftaran->jalurPpdb->nama }}</td></tr>
        <tr><td class="label">Gelombang</td><td>{{ $pendaftaran->gelombangPpdb->nama }}</td></tr>
        <tr><td class="label">Tanggal Submit</td><td>{{ $pendaftaran->submitted_at->format('d F Y H:i') }}</td></tr>
        <tr><td class="label">Status</td><td>{{ $pendaftaran->status }}</td></tr>
    </table>

    @if ($pendaftaran->sk_ppdb_id)
        <p style="margin-top: 16px;">Ditetapkan berdasarkan SK No. {{ $pendaftaran->skPpdb->nomor_sk }} tanggal {{ $pendaftaran->skPpdb->tanggal_terbit->translatedFormat('d F Y') }}.</p>
    @endif
</body>
</html>
