<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        h1 { font-size: 16px; text-align: center; }
        h2 { font-size: 13px; text-align: center; font-weight: normal; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; }
        .ttd { margin-top: 60px; text-align: right; }
    </style>
</head>
<body>
    <h1>SURAT KEPUTUSAN PENETAPAN HASIL PPDB</h1>
    <h2>Nomor: {{ $sk->nomor_sk }}</h2>
    <p>{{ $lembaga->nama }} &mdash; {{ $gelombang->nama }}</p>
    <p>Tanggal Terbit: {{ $sk->tanggal_terbit->translatedFormat('d F Y') }}</p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Kode Pendaftaran</th>
                <th>Nama Calon Murid</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pendaftaranFinal as $index => $pendaftaran)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $pendaftaran->kode_pendaftaran }}</td>
                    <td>{{ $pendaftaran->calonMurid->nama_lengkap }}</td>
                    <td>{{ $pendaftaran->status === 'diterima' ? 'Diterima' : 'Ditolak' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="ttd">
        <p>Ditetapkan oleh,</p>
        <p style="margin-top: 50px;"><strong>{{ $diterbitkanOleh->name }}</strong></p>
    </div>
</body>
</html>
