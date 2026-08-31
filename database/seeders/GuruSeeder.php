<?php

// database/seeders/GuruSeeder.php

namespace Database\Seeders;

use App\Domains\Identity\Models\Person;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\User;
use Illuminate\Database\Seeder;

class GuruSeeder extends Seeder
{
    public function run(): void
    {
        $sdit = Lembaga::where('npsn', '20223333')->firstOrFail();

        // 12 wali kelas (guru_kelas) -- 1 per rombel, 2 rombel x 6 tingkat
        $this->seedGuru($sdit, [
            [
                'email' => 'sari.wulandari@demo.test', 'name' => 'Sari Wulandari, S.Pd.',
                'nik' => '3273011101880101', 'nuptk' => '5234567890123401', 'nip' => null,
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Probolinggo', 'tanggal_lahir' => '1988-02-14',
                'no_hp' => '081234568101', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
                'golongan_pangkat' => null, 'tmt_tugas' => '2015-07-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'agus.setiawan@demo.test', 'name' => 'Agus Setiawan, S.Pd.',
                'nik' => '3273011203870102', 'nuptk' => '5234567890123402', 'nip' => null,
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Kraksaan', 'tanggal_lahir' => '1987-05-20',
                'no_hp' => '081234568102', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
                'golongan_pangkat' => null, 'tmt_tugas' => '2016-07-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'nita.kurniawati@demo.test', 'name' => 'Nita Kurniawati, S.Pd.',
                'nik' => '3273011304890103', 'nuptk' => null, 'nip' => null,
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Probolinggo', 'tanggal_lahir' => '1989-08-11',
                'no_hp' => '081234568103', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'Honorer',
                'golongan_pangkat' => null, 'tmt_tugas' => '2019-07-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'rudi.hartono@demo.test', 'name' => 'Rudi Hartono, S.Pd.',
                'nik' => '3273011405860104', 'nuptk' => '5234567890123404', 'nip' => '198605042009011010',
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Jember', 'tanggal_lahir' => '1986-05-04',
                'no_hp' => '081234568104', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'PNS',
                'golongan_pangkat' => 'Penata Muda / III-a', 'tmt_tugas' => '2009-01-01', 'tmt_pns' => '2009-01-01',
            ],
            [
                'email' => 'wahyu.astuti@demo.test', 'name' => 'Wahyu Astuti, S.Pd.',
                'nik' => '3273011506910105', 'nuptk' => null, 'nip' => null,
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Malang', 'tanggal_lahir' => '1991-06-15',
                'no_hp' => '081234568105', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'PPPK',
                'golongan_pangkat' => 'IX', 'tmt_tugas' => '2020-03-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'dedi.iskandar@demo.test', 'name' => 'Dedi Iskandar, S.Pd.',
                'nik' => '3273011607850106', 'nuptk' => '5234567890123406', 'nip' => null,
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Probolinggo', 'tanggal_lahir' => '1985-07-19',
                'no_hp' => '081234568106', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
                'golongan_pangkat' => null, 'tmt_tugas' => '2014-07-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'fitriani.rahmawati@demo.test', 'name' => 'Fitriani Rahmawati, S.Pd.',
                'nik' => '3273011708920107', 'nuptk' => null, 'nip' => null,
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Bondowoso', 'tanggal_lahir' => '1992-08-23',
                'no_hp' => '081234568107', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'Honorer',
                'golongan_pangkat' => null, 'tmt_tugas' => '2021-07-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'bambang.wijaya@demo.test', 'name' => 'Bambang Wijaya, S.Pd.',
                'nik' => '3273011809840108', 'nuptk' => '5234567890123408', 'nip' => '198409082008011008',
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Situbondo', 'tanggal_lahir' => '1984-09-08',
                'no_hp' => '081234568108', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'PNS',
                'golongan_pangkat' => 'Penata / III-c', 'tmt_tugas' => '2008-01-01', 'tmt_pns' => '2008-01-01',
            ],
            [
                'email' => 'ratna.puspita@demo.test', 'name' => 'Ratna Puspita, S.Pd.',
                'nik' => '3273011910930109', 'nuptk' => null, 'nip' => null,
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Probolinggo', 'tanggal_lahir' => '1993-10-30',
                'no_hp' => '081234568109', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'Honorer',
                'golongan_pangkat' => null, 'tmt_tugas' => '2022-07-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'yusuf.maulana@demo.test', 'name' => 'Yusuf Maulana, S.Pd.',
                'nik' => '3273012011890110', 'nuptk' => '5234567890123410', 'nip' => null,
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Kraksaan', 'tanggal_lahir' => '1989-11-05',
                'no_hp' => '081234568110', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
                'golongan_pangkat' => null, 'tmt_tugas' => '2017-07-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'lina.marlina@demo.test', 'name' => 'Lina Marlina, S.Pd.',
                'nik' => '3273012112870111', 'nuptk' => null, 'nip' => null,
                'jenis_kelamin' => 'P', 'tempat_lahir' => 'Probolinggo', 'tanggal_lahir' => '1987-12-12',
                'no_hp' => '081234568111', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
                'golongan_pangkat' => null, 'tmt_tugas' => '2013-07-01', 'tmt_pns' => null,
            ],
            [
                'email' => 'irfan.hakim@demo.test', 'name' => 'Irfan Hakim, S.Pd.',
                'nik' => '3273012213900112', 'nuptk' => '5234567890123412', 'nip' => null,
                'jenis_kelamin' => 'L', 'tempat_lahir' => 'Lumajang', 'tanggal_lahir' => '1990-01-13',
                'no_hp' => '081234568112', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'PPPK',
                'golongan_pangkat' => 'VIII', 'tmt_tugas' => '2020-08-01', 'tmt_pns' => null,
            ],
        ]);

        // 3 guru mapel spesialis (existing, dipertahankan persis) + 1 diangkat jadi guru_bk
        // (Task 6 mengangkat hendra.gunawan sbg guru_bk pengganti budi.santoso SMP yang dihapus)
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
            $user = User::where('email', $data['email'])->first();

            if (! $user) {
                $this->command?->warn("GuruSeeder: user {$data['email']} tidak ditemukan (UserSeeder mungkin dilewati di env non-local/testing), dilewati.");

                continue;
            }

            $person = Person::withoutGlobalScopes()->where('yayasan_id', $lembaga->yayasan_id)
                ->where('nik_hash', hash('sha256', $data['nik']))
                ->first();

            if (! $person) {
                $person = Person::create([
                    'yayasan_id' => $lembaga->yayasan_id,
                    'user_id' => $user->id,
                    'nik' => $data['nik'],
                    'nama_lengkap' => $data['name'],
                    'jenis_kelamin' => $data['jenis_kelamin'],
                    'tempat_lahir' => $data['tempat_lahir'],
                    'tanggal_lahir' => $data['tanggal_lahir'],
                    'agama' => 'Islam',
                    'kewarganegaraan' => 'WNI',
                    'alamat_jalan' => 'Jl. Panglima Sudirman No. 88C',
                    'rt' => '001',
                    'rw' => '003',
                    'desa_kelurahan' => 'Sidomulyo',
                    'kecamatan' => 'Kraksaan',
                    'kabupaten_kota' => 'Kabupaten Probolinggo',
                    'provinsi' => 'Jawa Timur',
                    'kode_pos' => '67282',
                    'no_hp' => $data['no_hp'],
                    'email' => $data['email'],
                ]);
            } else {
                $person->update(['user_id' => $user->id]);
            }

            Guru::firstOrCreate(
                ['person_id' => $person->id],
                [
                    'lembaga_id' => $lembaga->id,
                    'nuptk' => $data['nuptk'],
                    'nip' => $data['nip'],
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
