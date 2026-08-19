<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #111827; }
        h1 { font-size: 15px; margin-bottom: 2px; }
        h2 { font-size: 13px; margin-top: 14px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #E5E7EB; padding: 5px 6px; text-align: center; }
        th { background-color: #F3F4F6; font-size: 10px; text-transform: uppercase; }
        td.nama, td.narasi { text-align: left; }
        .watermark { position: fixed; top: 40%; left: 15%; font-size: 60px; color: #EF4444; opacity: 0.15; transform: rotate(-30deg); z-index: -1; }
    </style>
</head>
<body>
    @if($isDraft)
        <div class="watermark">DRAFT</div>
    @endif

    @include('pdf.rapor._identitas')

    <h2>Nilai Akademik</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 30%;">Mata Pelajaran</th>
                <th style="width: 12%;">Nilai</th>
                @if ($isGenap && $nilaiRataRataTahunan !== null)
                    <th style="width: 12%;">Rata-Rata Tahunan</th>
                @endif
                <th>Capaian Kompetensi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($mapelList as $mapel)
                @php($narasi = $narasiPerMapel[$mapel->id] ?? ['tertinggi' => null, 'terendah' => null])
                <tr>
                    <td class="nama">{{ $mapel->nama }}</td>
                    <td>{{ $rekapNilai[$mapel->id] ?? '-' }}</td>
                    @if ($isGenap && $nilaiRataRataTahunan !== null)
                        <td>{{ $nilaiRataRataTahunan[$mapel->id] ?? '-' }}</td>
                    @endif
                    <td class="narasi">{{ trim(($narasi['tertinggi'] ?? '').' '.($narasi['terendah'] ?? '')) ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Belum ada data nilai.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Ekstrakurikuler</h2>
    <table>
        <thead><tr><th>Kegiatan</th><th>Peran</th></tr></thead>
        <tbody>
            @forelse (($catatan?->ekstrakurikuler ?? []) as $row)
                <tr><td class="nama">{{ $row['nama'] ?? '-' }}</td><td>{{ $row['peran'] ?? '-' }}</td></tr>
            @empty
                <tr><td colspan="2">Tidak mengikuti ekstrakurikuler.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Kehadiran Semester {{ $semester->nama }}</h2>
    <table>
        <thead><tr><th>Hadir</th><th>Izin</th><th>Sakit</th><th>Alpa</th><th>Terlambat</th></tr></thead>
        <tbody>
            <tr>
                <td>{{ $absensi['hadir'] }}</td><td>{{ $absensi['izin'] }}</td><td>{{ $absensi['sakit'] }}</td><td>{{ $absensi['alpa'] }}</td><td>{{ $absensi['terlambat'] }}</td>
            </tr>
        </tbody>
    </table>

    @if ($isGenap && $absensiTahunan !== null)
        <h2>Akumulasi Kehadiran Semester Ganjil + Genap</h2>
        <table>
            <thead><tr><th>Hadir</th><th>Izin</th><th>Sakit</th><th>Alpa</th><th>Terlambat</th></tr></thead>
            <tbody>
                <tr>
                    <td>{{ $absensiTahunan['hadir'] }}</td><td>{{ $absensiTahunan['izin'] }}</td><td>{{ $absensiTahunan['sakit'] }}</td><td>{{ $absensiTahunan['alpa'] }}</td><td>{{ $absensiTahunan['terlambat'] }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <h2>Catatan Wali Kelas</h2>
    <p>{{ $catatan?->catatan_sikap ?: '-' }}</p>

    @if ($isGenap)
        <h2>{{ $labelKenaikan }}</h2>
        <p>{{ $catatan?->keterangan_kenaikan ?: '-' }}</p>
    @endif

    @include('pdf.rapor._tanda-tangan')
</body>
</html>
