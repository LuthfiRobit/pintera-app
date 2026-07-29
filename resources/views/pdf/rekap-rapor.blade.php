<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #111827; }
        h1 { font-size: 15px; margin-bottom: 2px; }
        p.subtitle { color: #5B6478; margin-top: 0; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #E5E7EB; padding: 5px 6px; text-align: center; }
        th { background-color: #F3F4F6; font-size: 10px; text-transform: uppercase; }
        td.nama { text-align: left; font-weight: bold; }
        td.tuntas { background-color: #ECFDF5; color: #047857; font-weight: bold; }
        td.bimbingan { background-color: #FFFBEB; color: #B45309; font-weight: bold; }
        td.umum { background-color: #EFF6FF; color: #1D4ED8; font-weight: bold; }
        p.legend { margin-top: 10px; font-size: 10px; color: #5B6478; }
    </style>
</head>
<body>
    <h1>Rekap Nilai Rapor — {{ $selectedKelas->nama }}</h1>
    <p class="subtitle">{{ $selectedSemester->nama }} — {{ $selectedSemester->tahunAjaran->nama }} &middot; Dicetak {{ now()->translatedFormat('d F Y H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th style="width: 30px;">No</th>
                <th style="width: 140px; text-align: left;">Nama Peserta Didik</th>
                @forelse ($mapelList as $mapel)
                    <th>{{ $mapel->nama }}</th>
                @empty
                    <th>Belum Ada Mapel Terasesmen</th>
                @endforelse
                <th style="width: 70px;">Rata-Rata Umum</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($siswaList as $index => $siswa)
                @php
                    $studentScores = collect($rekapNilai[$siswa->id] ?? [])->filter(fn ($v) => $v !== null);
                    $generalAvg = $studentScores->count() > 0 ? round($studentScores->avg(), 1) : null;
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="nama">{{ $siswa->nama_lengkap }}</td>
                    @forelse ($mapelList as $mapel)
                        @php $skor = $rekapNilai[$siswa->id][$mapel->id] ?? null; @endphp
                        <td class="{{ $skor === null ? '' : ($skor >= config('akademik.ambang_tuntas') ? 'tuntas' : 'bimbingan') }}">
                            {{ $skor ?? '—' }}
                        </td>
                    @empty
                        <td>—</td>
                    @endforelse
                    <td class="umum">{{ $generalAvg ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 3 + $mapelList->count() }}">Belum ada siswa terdaftar di kelas ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="legend">Tuntas: skor &ge; {{ config('akademik.ambang_tuntas') }} &nbsp;&nbsp; Perlu Bimbingan: skor &lt; {{ config('akademik.ambang_tuntas') }}</p>
</body>
</html>
