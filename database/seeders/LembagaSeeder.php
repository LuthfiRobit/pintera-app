<?php
// database/seeders/LembagaSeeder.php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Database\Seeder;

class LembagaSeeder extends Seeder
{
    public function run(): void
    {
        $yayasan = Yayasan::firstOrFail();

        Lembaga::firstOrCreate(
            ['npsn' => '20223333'],
            [
                'yayasan_id' => $yayasan->id,
                'nss' => '102026001033',
                'kode_lembaga' => 'SDITPTR',
                'nama' => 'SDIT PINTERA',
                'bentuk_pendidikan' => 'SD',
                'status_sekolah' => 'swasta',
                'status_kepemilikan' => 'Yayasan',
                'naungan' => 'kemendikdasmen',
                'sk_pendirian_nomor' => '421.2/SK.033/Disdik/2010',
                'sk_pendirian_tanggal' => '2010-06-01',
                'sk_izin_operasional_nomor' => '421.2/IOP.033/Disdik/2010',
                'sk_izin_operasional_tanggal' => '2010-07-15',
                'akreditasi' => 'A',
                'sk_akreditasi_nomor' => '033/BAN-SM/SK/2022',
                'tanggal_sk_akreditasi' => '2022-11-10',
                'nama_kepala_sekolah' => 'Abdullah, M.Pd.',
                'nama_bendahara_bosp' => 'Hasan, S.E.',
                'alamat_jalan' => 'Jl. Panglima Sudirman No. 88C',
                'rt' => '001',
                'rw' => '003',
                'desa_kelurahan' => 'Sidomulyo',
                'kecamatan' => 'Kraksaan',
                'kabupaten_kota' => 'Kabupaten Probolinggo',
                'provinsi' => 'Jawa Timur',
                'kode_pos' => '67282',
                'lintang' => '-7.7552000',
                'bujur' => '113.4152000',
                'telepon' => '0335-771233',
                'email' => 'sdit@pintera.sch.id',
                'website' => 'https://sdit.pintera.sch.id',
                'nama_bank' => 'Bank BSI',
                'cabang_kcp_unit' => 'KCP Kraksaan',
                'rekening_atas_nama' => 'SDIT PINTERA',
                'nomor_rekening' => '7123456033',
                'mbs' => true,
                'nama_wajib_pajak' => 'SDIT PINTERA',
                'npwp' => '02.345.673.3-012.000',
                'status_aktif' => true,
            ]
        );
    }
}
