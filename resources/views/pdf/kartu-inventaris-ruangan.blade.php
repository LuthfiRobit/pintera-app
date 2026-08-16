<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>KARTU INVENTARIS RUANGAN (KIR)</title>
    <style>
        @page {
            size: a4 landscape;
            margin: 1.5cm 1.5cm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1f2937;
            font-size: 11px;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #111827;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header h2 {
            margin: 0 0 4px 0;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header h3 {
            margin: 0;
            font-size: 13px;
            font-weight: normal;
            color: #4b5563;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 15px;
        }
        .meta-table td {
            padding: 3px 0;
            vertical-align: top;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .data-table th, .data-table td {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            text-align: left;
        }
        .data-table th {
            background-color: #f3f4f6;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10px;
            color: #374151;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .footer-sign {
            width: 100%;
            margin-top: 30px;
        }
        .footer-sign td {
            width: 50%;
            text-align: center;
            vertical-align: top;
        }
        .sign-space {
            height: 60px;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-baik { background: #d1fae5; color: #065f46; }
        .badge-rusak { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="header">
        <h2>KARTU INVENTARIS RUANGAN (KIR)</h2>
        <h3>{{ $ruangan->lembaga->nama ?? 'Unit Sekolah' }} — {{ $ruangan->lembaga->yayasan->nama ?? '' }}</h3>
    </div>

    <table class="meta-table">
        <tr>
            <td style="width: 15%;"><strong>Kode Ruangan</strong></td>
            <td style="width: 35%;">: {{ $ruangan->kode_ruangan }}</td>
            <td style="width: 15%;"><strong>Gedung / Lantai</strong></td>
            <td style="width: 35%;">: {{ $ruangan->gedung->nama_gedung ?? '-' }} (Lantai {{ $ruangan->lantai }})</td>
        </tr>
        <tr>
            <td><strong>Nama Ruangan</strong></td>
            <td>: {{ $ruangan->nama_ruangan }}</td>
            <td><strong>Jenis Ruangan</strong></td>
            <td>: {{ $ruangan->jenis_ruangan->label() }}</td>
        </tr>
        <tr>
            <td><strong>Kapasitas / Luas</strong></td>
            <td>: {{ $ruangan->kapasitas_siswa ?? '-' }} Siswa / {{ $ruangan->luas_m2 ? $ruangan->luas_m2 . ' m²' : '-' }}</td>
            <td><strong>Penanggung Jawab</strong></td>
            <td>: {{ $ruangan->penanggungJawab->nama ?? 'Belum Ditentukan' }}</td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 4%;" class="text-center">No</th>
                <th style="width: 16%;">Kode Inventaris</th>
                <th style="width: 25%;">Nama Barang / Merk</th>
                <th style="width: 15%;">Kategori</th>
                <th style="width: 8%;" class="text-center">Jumlah</th>
                <th style="width: 12%;" class="text-center">Kondisi</th>
                <th style="width: 20%;">Keterangan / Spesifikasi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ruangan->aset as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td><strong>{{ $item->kode_inventaris }}</strong></td>
                    <td>
                        {{ $item->nama_barang }}
                        @if($item->merk)
                            <br><small style="color: #6b7280;">Merk: {{ $item->merk }}</small>
                        @endif
                    </td>
                    <td>{{ $item->kategori->nama_kategori ?? '-' }}</td>
                    <td class="text-center">{{ $item->qty }} {{ $item->satuan }}</td>
                    <td class="text-center">
                        <span class="badge {{ $item->kondisi->value === 'baik' ? 'badge-baik' : 'badge-rusak' }}">
                            {{ $item->kondisi->label() }}
                        </span>
                    </td>
                    <td>{{ $item->spesifikasi ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center" style="padding: 20px; color: #6b7280;">
                        Belum ada aset/inventaris yang terdaftar di ruangan ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="footer-sign">
        <tr>
            <td>
                Mengetahui,<br>
                <strong>Penanggung Jawab Ruangan</strong>
                <div class="sign-space"></div>
                <strong><u>{{ $ruangan->penanggungJawab->nama ?? '( .................................... )' }}</u></strong><br>
                NIP: {{ $ruangan->penanggungJawab->nip ?? '........................' }}
            </td>
            <td>
                Dicetak pada: {{ now()->translatedFormat('d F Y') }}<br>
                <strong>Pengelola Sarpras Lembaga</strong>
                <div class="sign-space"></div>
                <strong><u>( .................................... )</u></strong><br>
                NIP: ........................
            </td>
        </tr>
    </table>
</body>
</html>
