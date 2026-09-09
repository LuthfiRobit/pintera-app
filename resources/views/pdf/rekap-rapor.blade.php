<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rekap Nilai Rapor — {{ $selectedKelas->nama }}</title>
    <style>
        @page {
            size: a4 landscape;
            margin: 12mm 15mm 12mm 15mm;
        }
        body { font-family: sans-serif; font-size: 10px; color: #111827; }
        h1 { font-size: 15px; margin-bottom: 2px; }
        p.subtitle { color: #5B6478; margin-top: 0; margin-bottom: 12px; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #D1D5DB; padding: 4px 6px; text-align: center; }
        th { background-color: #F3F4F6; font-size: 9.5px; font-weight: bold; text-transform: uppercase; }
        td.nama { text-align: left; font-weight: 600; }
        td.nis { font-family: monospace; font-size: 9.5px; color: #4B5563; }
        td.tuntas { background-color: #ECFDF5; color: #047857; font-weight: bold; }
        td.bimbingan { background-color: #FFFBEB; color: #B45309; font-weight: bold; }
        td.umum { background-color: #EFF6FF; color: #1D4ED8; font-weight: bold; }
        .legend-container { margin-top: 10px; font-size: 9px; color: #4B5563; }
        .mapel-legend { margin-top: 4px; font-size: 8.5px; color: #6B7280; line-height: 1.4; }
    </style>
</head>
<body>
    <h1>Rekap Nilai Rapor — {{ $selectedKelas->nama }}</h1>
    <p class="subtitle">{{ $selectedKelas->lembaga->nama ?? '-' }} &middot; {{ $selectedSemester->nama }} — {{ $selectedSemester->tahunAjaran->nama }} &middot; Dicetak {{ now()->translatedFormat('d F Y H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th style="width: 25px;">No</th>
                <th style="width: 80px;">NIS</th>
                <th style="width: 160px; text-align: left;">Nama Peserta Didik</th>
                @forelse ($mapelList as $mapel)
                    <th style="min-width: 45px;">{{ $mapel->kode ?: $mapel->nama }}</th>
                @empty
                    <th>Belum Ada Mapel Terasesmen</th>
                @endforelse
                <th style="width: 70px;">Rata-Rata Umum</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($siswaList as $index => $siswa)
                @php
                    $studentScores = collect($rekapNilai[$siswa->id] ?? [])
                        ->filter(fn ($sel) => $sel !== null && $sel->tuntas !== null)
                        ->map(fn ($sel) => (float) $sel->label);
                    $generalAvg = $studentScores->count() > 0 ? round($studentScores->avg(), 1) : null;
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="nis">{{ $siswa->nis ?: ($siswa->nisn ?: '-') }}</td>
                    <td class="nama">{{ $siswa->nama_lengkap }}</td>
                    @forelse ($mapelList as $subjekKey => $mapel)
                        @php $sel = $rekapNilai[$siswa->id][$subjekKey] ?? null; @endphp
                        <td class="{{ $sel !== null && $sel->tuntas !== null ? ($sel->tuntas ? 'tuntas' : 'bimbingan') : '' }}">
                            {{ $sel->label ?? '—' }}
                        </td>
                    @empty
                        <td>—</td>
                    @endforelse
                    <td class="umum">{{ $generalAvg ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 4 + $mapelList->count() }}">Belum ada siswa terdaftar di kelas ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="legend-container">
        <p style="margin: 0;"><strong>Kriteria:</strong> Tuntas (skor &ge; {{ config('akademik.ambang_tuntas') }}) &nbsp;&bull;&nbsp; Perlu Bimbingan (skor &lt; {{ config('akademik.ambang_tuntas') }})</p>
        @if ($mapelList->isNotEmpty())
            <p class="mapel-legend">
                <strong>Keterangan Kode Mapel:</strong>
                @foreach ($mapelList as $mapel)
                    {{ $mapel->kode ?: $mapel->nama }}: {{ $mapel->nama }}{{ ! $loop->last ? ' • ' : '' }}
                @endforeach
            </p>
        @endif
    </div>
</body>
</html>
