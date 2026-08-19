<div style="margin-bottom: 16px;">
    <h1>{{ $judulDokumen }}</h1>
    <table style="border: none; margin-top: 8px;">
        <tr style="border: none;">
            <td style="border: none; text-align: left; padding: 2px 0; width: 50%;">
                <strong>{{ $lembaga->nama }}</strong><br>
                NPSN: {{ $lembaga->npsn ?: '-' }}<br>
                {{ $lembaga->alamat_jalan }}
            </td>
            <td style="border: none; text-align: left; padding: 2px 0;">
                Nama: <strong>{{ $siswa->nama_lengkap }}</strong><br>
                NIS/NISN: {{ $siswa->nis ?: '-' }} / {{ $siswa->nisn ?: '-' }}<br>
                Kelas: {{ $kelas->nama }}
            </td>
        </tr>
    </table>
</div>
