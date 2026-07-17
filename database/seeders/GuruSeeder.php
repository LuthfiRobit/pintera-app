<?php
// database/seeders/GuruSeeder.php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\User;
use Illuminate\Database\Seeder;

class GuruSeeder extends Seeder
{
    public function run(): void
    {
        $smp = Lembaga::where('npsn', '20223344')->firstOrFail();
        $sma = Lembaga::where('npsn', '20223355')->firstOrFail();

        $this->seedGuru($smp, [
            [
                'email' => 'budi.santoso@alhikmah.sch.id', 'name' => 'Budi Santoso, S.Pd.',
                'nik' => '3273011503850001', 'nuptk' => '1234567890123456', 'nip' => '198503152010011001',
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1985-03-15',
                'no_hp' => '081234567801', 'jenis_ptk' => 'guru_mapel', 'status_kepegawaian' => 'PNS',
                'golongan_pangkat' => 'Penata Muda Tk.I / III-b', 'tmt_tugas' => '2010-01-01', 'tmt_pns' => '2010-01-01',
            ],
            [
                'email' => 'siti.rahmawati@alhikmah.sch.id', 'name' => 'Siti Rahmawati, S.Pd.',
                'nik' => '3273015207880002', 'nuptk' => null, 'nip' => null,
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Cimahi', 'tanggal_lahir' => '1988-07-12',
                'no_hp' => '081234567802', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
                'golongan_pangkat' => null, 'tmt_tugas' => '2015-07-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'andi.wijaya@alhikmah.sch.id', 'name' => 'Andi Wijaya, S.Pd.I.',
                'nik' => '3273012009900003', 'nuptk' => null, 'nip' => null,
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Garut', 'tanggal_lahir' => '1990-09-20',
                'no_hp' => '081234567803', 'jenis_ptk' => 'guru_mapel', 'status_kepegawaian' => 'Honorer',
                'golongan_pangkat' => null, 'tmt_tugas' => '2019-07-01', 'tmt_pns' => null,
            ],
        ]);

        $this->seedGuru($sma, [
            [
                'email' => 'hendra.gunawan@alhikmah.sch.id', 'name' => 'Hendra Gunawan, S.Pd.',
                'nik' => '3273010108820004', 'nuptk' => '2234567890123456', 'nip' => '198201082008011002',
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1982-01-08',
                'no_hp' => '081234567804', 'jenis_ptk' => 'guru_mapel', 'status_kepegawaian' => 'PNS',
                'golongan_pangkat' => 'Penata / III-c', 'tmt_tugas' => '2008-01-01', 'tmt_pns' => '2008-01-01',
            ],
            [
                'email' => 'maya.anggraini@alhikmah.sch.id', 'name' => 'Maya Anggraini, S.Pd.',
                'nik' => '3273014412910005', 'nuptk' => null, 'nip' => null,
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Sumedang', 'tanggal_lahir' => '1991-12-04',
                'no_hp' => '081234567805', 'jenis_ptk' => 'guru_mapel', 'status_kepegawaian' => 'PPPK',
                'golongan_pangkat' => 'IX', 'tmt_tugas' => '2021-03-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'taufik.hidayat@alhikmah.sch.id', 'name' => 'Taufik Hidayat, S.Pd.',
                'nik' => '3273011511870006', 'nuptk' => null, 'nip' => null,
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1987-11-15',
                'no_hp' => '081234567806', 'jenis_ptk' => 'guru_mapel', 'status_kepegawaian' => 'GTY',
                'golongan_pangkat' => null, 'tmt_tugas' => '2016-07-01', 'tmt_pns' => null,
            ],
        ]);
    }

    private function seedGuru(Lembaga $lembaga, array $guruList): void
    {
        foreach ($guruList as $data) {
            $user = User::where('email', $data['email'])->firstOrFail();

            Guru::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'lembaga_id' => $lembaga->id,
                    'nik' => $data['nik'],
                    'nuptk' => $data['nuptk'],
                    'nip' => $data['nip'],
                    'nama' => $data['name'],
                    'jenis_kelamin' => $data['jenis_kelamin'],
                    'tempat_lahir' => $data['tempat_lahir'],
                    'tanggal_lahir' => $data['tanggal_lahir'],
                    'agama' => 'Islam',
                    'kewarganegaraan' => 'WNI',
                    'alamat_jalan' => 'Jl. Cihampelas No. '.random_int(10, 200),
                    'rt' => '002',
                    'rw' => '005',
                    'desa_kelurahan' => 'Cipaganti',
                    'kecamatan' => 'Coblong',
                    'kabupaten_kota' => 'Kota Bandung',
                    'provinsi' => 'Jawa Barat',
                    'kode_pos' => '40131',
                    'no_hp' => $data['no_hp'],
                    'email' => $data['email'],
                    'jenis_ptk' => $data['jenis_ptk'],
                    'status_kepegawaian' => $data['status_kepegawaian'],
                    'golongan_pangkat' => $data['golongan_pangkat'],
                    'tmt_tugas' => $data['tmt_tugas'],
                    'tmt_pns' => $data['tmt_pns'],
                    'status_aktif' => 'aktif',
                ]
            );
        }
    }
}
