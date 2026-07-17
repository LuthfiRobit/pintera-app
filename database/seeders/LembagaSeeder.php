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
            ['npsn' => '20223344'],
            [
                'yayasan_id' => $yayasan->id,
                'nss' => '202026001045',
                'nama' => 'SMP Islam Al-Hikmah',
                'bentuk_pendidikan' => 'SMP',
                'status_sekolah' => 'swasta',
                'status_kepemilikan' => 'Yayasan',
                'naungan' => 'kemendikdasmen',
                'sk_pendirian_nomor' => '421.3/SK.045/Disdik/2006',
                'sk_pendirian_tanggal' => '2006-06-01',
                'sk_izin_operasional_nomor' => '421.3/IOP.089/Disdik/2006',
                'sk_izin_operasional_tanggal' => '2006-07-15',
                'akreditasi' => 'A',
                'sk_akreditasi_nomor' => '1234/BAN-SM/SK/2022',
                'tanggal_sk_akreditasi' => '2022-11-10',
                'nama_kepala_sekolah' => 'Drs. H. Bambang Suryadi, M.Pd.',
                'nama_bendahara_bosp' => 'Nur Aisyah, S.Pd.',
                'alamat_jalan' => 'Jl. Pendidikan Raya No. 45',
                'rt' => '003',
                'rw' => '008',
                'desa_kelurahan' => 'Sukaluyu',
                'kecamatan' => 'Cibeunying Kaler',
                'kabupaten_kota' => 'Kota Bandung',
                'provinsi' => 'Jawa Barat',
                'kode_pos' => '40123',
                'lintang' => '-6.8951000',
                'bujur' => '107.6134000',
                'telepon' => '022-7301234',
                'email' => 'smp@alhikmah.sch.id',
                'website' => 'https://smp.alhikmah.sch.id',
                'nama_bank' => 'Bank BRI',
                'cabang_kcp_unit' => 'KCP Bandung Cibeunying',
                'rekening_atas_nama' => 'SMP Islam Al-Hikmah',
                'nomor_rekening' => '0123-01-987654-50-1',
                'mbs' => true,
                'nama_wajib_pajak' => 'SMP Islam Al-Hikmah',
                'npwp' => '02.345.678.9-012.000',
                'memungut_iuran' => true,
                'nominal_iuran' => 350000,
                'periode_iuran' => 'bulanan',
                'status_aktif' => true,
            ]
        );

        Lembaga::firstOrCreate(
            ['npsn' => '20223355'],
            [
                'yayasan_id' => $yayasan->id,
                'nss' => '302026001046',
                'nama' => 'SMA Islam Al-Hikmah',
                'bentuk_pendidikan' => 'SMA',
                'status_sekolah' => 'swasta',
                'status_kepemilikan' => 'Yayasan',
                'naungan' => 'kemendikdasmen',
                'sk_pendirian_nomor' => '421.3/SK.078/Disdik/2010',
                'sk_pendirian_tanggal' => '2010-05-20',
                'sk_izin_operasional_nomor' => '421.3/IOP.112/Disdik/2010',
                'sk_izin_operasional_tanggal' => '2010-06-30',
                'akreditasi' => 'A',
                'sk_akreditasi_nomor' => '5678/BAN-SM/SK/2021',
                'tanggal_sk_akreditasi' => '2021-09-05',
                'nama_kepala_sekolah' => 'Dr. Hj. Ratna Dewi, M.M.Pd.',
                'nama_bendahara_bosp' => 'Fajar Ramadhan, S.E.',
                'alamat_jalan' => 'Jl. Pendidikan Raya No. 47',
                'rt' => '003',
                'rw' => '008',
                'desa_kelurahan' => 'Sukaluyu',
                'kecamatan' => 'Cibeunying Kaler',
                'kabupaten_kota' => 'Kota Bandung',
                'provinsi' => 'Jawa Barat',
                'kode_pos' => '40123',
                'lintang' => '-6.8953000',
                'bujur' => '107.6138000',
                'telepon' => '022-7301235',
                'email' => 'sma@alhikmah.sch.id',
                'website' => 'https://sma.alhikmah.sch.id',
                'nama_bank' => 'Bank BRI',
                'cabang_kcp_unit' => 'KCP Bandung Cibeunying',
                'rekening_atas_nama' => 'SMA Islam Al-Hikmah',
                'nomor_rekening' => '0123-01-987655-50-2',
                'mbs' => true,
                'nama_wajib_pajak' => 'SMA Islam Al-Hikmah',
                'npwp' => '02.345.679.0-012.000',
                'memungut_iuran' => true,
                'nominal_iuran' => 450000,
                'periode_iuran' => 'bulanan',
                'status_aktif' => true,
            ]
        );
    }
}
