<table style="border: none; margin-top: 30px;">
    <tr style="border: none;">
        <td style="border: none; text-align: center; width: 33%;">
            Orang Tua/Wali
            <div style="height: 50px;"></div>
            <strong>{{ $namaOrangTua ?? '.....................' }}</strong>
        </td>
        <td style="border: none; text-align: center; width: 33%;">
            Wali Kelas
            <div style="height: 50px;"></div>
            <strong>{{ $namaWaliKelas ?? '(Menunggu Verifikasi)' }}</strong>
        </td>
        <td style="border: none; text-align: center; width: 33%;">
            Kepala Sekolah
            <div style="height: 50px;"></div>
            <strong>{{ $namaKepalaSekolah ?? '(Menunggu Persetujuan)' }}</strong>
        </td>
    </tr>
</table>
