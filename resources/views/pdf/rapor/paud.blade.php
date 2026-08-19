<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #111827; }
        h1 { font-size: 15px; margin-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #E5E7EB; padding: 5px 6px; text-align: left; }
        th { background-color: #F3F4F6; font-size: 10px; text-transform: uppercase; }
        .watermark { position: fixed; top: 40%; left: 15%; font-size: 60px; color: #EF4444; opacity: 0.15; transform: rotate(-30deg); z-index: -1; }
        .elemen-cp { margin-bottom: 14px; }
        .elemen-cp h3 { font-size: 12px; margin-bottom: 4px; }
    </style>
</head>
<body>
    @if($isDraft)
        <div class="watermark">DRAFT</div>
    @endif

    @include('pdf.rapor._identitas')

    @php
        $keranjangElemenCp = [
            'nilai_agama_moral' => ['label' => 'Nilai Agama dan Budi Pekerti', 'kalimat' => []],
            'jati_diri' => ['label' => 'Jati Diri', 'kalimat' => []],
            'literasi_steam' => ['label' => 'Literasi, STEAM, Seni, dan Budaya', 'kalimat' => []],
        ];
        foreach ($mapelList as $mapel) {
            $narasi = $narasiPerMapel[$mapel->id] ?? ['tertinggi' => null, 'terendah' => null];
            $komponenTerkait = \App\Domains\Akademik\Models\KomponenPenilaian::where('mata_pelajaran_id', $mapel->id)
                ->where('semester_id', $semester->id)
                ->whereNotNull('elemen_cp')
                ->first();
            $elemen = $komponenTerkait?->elemen_cp?->value;
            if ($elemen && isset($keranjangElemenCp[$elemen])) {
                if ($narasi['tertinggi']) { $keranjangElemenCp[$elemen]['kalimat'][] = $narasi['tertinggi']; }
                if ($narasi['terendah']) { $keranjangElemenCp[$elemen]['kalimat'][] = $narasi['terendah']; }
            }
        }
    @endphp

    <h2 style="font-size: 13px;">Capaian Pembelajaran</h2>
    @foreach ($keranjangElemenCp as $elemen)
        <div class="elemen-cp">
            <h3>{{ $elemen['label'] }}</h3>
            <p>{{ $elemen['kalimat'] ? implode(' ', $elemen['kalimat']) : 'Belum ada data capaian.' }}</p>
        </div>
    @endforeach

    <h2 style="font-size: 13px;">Pertumbuhan Fisik</h2>
    <table>
        <tr><th>Tinggi Badan</th><th>Berat Badan</th><th>Lingkar Kepala</th></tr>
        <tr>
            <td>{{ $catatan?->tinggi_badan_cm ? $catatan->tinggi_badan_cm.' cm' : '-' }}</td>
            <td>{{ $catatan?->berat_badan_kg ? $catatan->berat_badan_kg.' kg' : '-' }}</td>
            <td>{{ $catatan?->lingkar_kepala_cm ? $catatan->lingkar_kepala_cm.' cm' : '-' }}</td>
        </tr>
    </table>

    <h2 style="font-size: 13px; margin-top: 14px;">Catatan Wali Kelas</h2>
    <p>{{ $catatan?->catatan_sikap ?: '-' }}</p>
    <p>{{ $catatan?->catatan_perkembangan ?: '-' }}</p>

    @include('pdf.rapor._tanda-tangan')
</body>
</html>
