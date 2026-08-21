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
        $kbit = Lembaga::where('npsn', '20223311')->firstOrFail();
        $tkit = Lembaga::where('npsn', '20223322')->firstOrFail();
        $sdit = Lembaga::where('npsn', '20223333')->firstOrFail();
        $smpit = Lembaga::where('npsn', '20223344')->firstOrFail();

        $this->seedGuru($kbit, [
            [
                'email' => 'guru.kb1@demo.test', 'name' => 'Fatimah, S.Psi.',
                'nik' => '3273011101850011', 'nuptk' => '3234567890123411', 'nip' => null,
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Probolinggo', 'tanggal_lahir' => '1989-05-12',
                'no_hp' => '081234567811', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
                'golongan_pangkat' => null, 'tmt_tugas' => '2018-07-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'guru.kb2@demo.test', 'name' => 'Zahra, S.Pd.',
                'nik' => '3273011202860012', 'nuptk' => '3234567890123412', 'nip' => null,
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Probolinggo', 'tanggal_lahir' => '1990-08-15',
                'no_hp' => '081234567812', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
                'golongan_pangkat' => null, 'tmt_tugas' => '2019-07-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'guru.kb3@demo.test', 'name' => 'Rini, S.Pd.',
                'nik' => '3273011303870013', 'nuptk' => null, 'nip' => null,
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Surabaja', 'tanggal_lahir' => '1992-03-20',
                'no_hp' => '081234567813', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'Honorer',
                'golongan_pangkat' => null, 'tmt_tugas' => '2020-07-01', 'tmt_pns' => null,
            ],
        ]);

        $this->seedGuru($tkit, [
            [
                'email' => 'guru.tk1@demo.test', 'name' => 'Dewi, S.Pd.I.',
                'nik' => '3273012101850021', 'nuptk' => '4234567890123421', 'nip' => null,
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Malang', 'tanggal_lahir' => '1988-04-10',
                'no_hp' => '081234567821', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
                'golongan_pangkat' => null, 'tmt_tugas' => '2017-07-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'guru.tk2@demo.test', 'name' => 'Latifah, S.Pd.',
                'nik' => '3273012202860022', 'nuptk' => '4234567890123422', 'nip' => null,
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Pasuruan', 'tanggal_lahir' => '1991-09-11',
                'no_hp' => '081234567822', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
                'golongan_pangkat' => null, 'tmt_tugas' => '2018-07-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'guru.tk3@demo.test', 'name' => 'Amel, S.Psi.',
                'nik' => '3273012303870023', 'nuptk' => null, 'nip' => null,
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Jember', 'tanggal_lahir' => '1993-11-25',
                'no_hp' => '081234567823', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'Honorer',
                'golongan_pangkat' => null, 'tmt_tugas' => '2021-07-01', 'tmt_pns' => null,
            ],
        ]);

        $this->seedGuru($smpit, [
            [
                'email' => 'budi.santoso@demo.test', 'name' => 'Budi Santoso, S.Pd.',
                'nik' => '3273011503850001', 'nuptk' => '1234567890123456', 'nip' => '198503152010011001',
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1985-03-15',
                'no_hp' => '081234567801', 'jenis_ptk' => 'guru_mapel', 'status_kepegawaian' => 'PNS',
                'golongan_pangkat' => 'Penata Muda Tk.I / III-b', 'tmt_tugas' => '2010-01-01', 'tmt_pns' => '2010-01-01',
            ],
            [
                'email' => 'siti.rahmawati@demo.test', 'name' => 'Siti Rahmawati, S.Pd.',
                'nik' => '3273015207880002', 'nuptk' => null, 'nip' => null,
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Cimahi', 'tanggal_lahir' => '1988-07-12',
                'no_hp' => '081234567802', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
                'golongan_pangkat' => null, 'tmt_tugas' => '2015-07-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'andi.wijaya@demo.test', 'name' => 'Andi Wijaya, S.Pd.I.',
                'nik' => '3273012009900003', 'nuptk' => null, 'nip' => null,
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Garut', 'tanggal_lahir' => '1990-09-20',
                'no_hp' => '081234567803', 'jenis_ptk' => 'guru_mapel', 'status_kepegawaian' => 'Honorer',
                'golongan_pangkat' => null, 'tmt_tugas' => '2019-07-01', 'tmt_pns' => null,
            ],
        ]);

        $this->seedGuru($sdit, [
            [
                'email' => 'hendra.gunawan@demo.test', 'name' => 'Hendra Gunawan, S.Pd.',
                'nik' => '3273010108820004', 'nuptk' => '2234567890123456', 'nip' => '198201082008011002',
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1982-01-08',
                'no_hp' => '081234567804', 'jenis_ptk' => 'guru_mapel', 'status_kepegawaian' => 'PNS',
                'golongan_pangkat' => 'Penata / III-c', 'tmt_tugas' => '2008-01-01', 'tmt_pns' => '2008-01-01',
            ],
            [
                'email' => 'maya.anggraini@demo.test', 'name' => 'Maya Anggraini, S.Pd.',
                'nik' => '3273014412910005', 'nuptk' => null, 'nip' => null,
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Sumedang', 'tanggal_lahir' => '1991-12-04',
                'no_hp' => '081234567805', 'jenis_ptk' => 'guru_mapel', 'status_kepegawaian' => 'PPPK',
                'golongan_pangkat' => 'IX', 'tmt_tugas' => '2021-03-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'taufik.hidayat@demo.test', 'name' => 'Taufik Hidayat, S.Pd.',
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
                    'alamat_jalan' => 'Jl. Cihampelas No. 125',
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
