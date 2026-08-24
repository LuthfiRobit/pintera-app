<?php

namespace Database\Seeders;

use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn(static::class.': dilewati, hanya boleh jalan di environment local/testing.');

            return;
        }

        // Admin yayasan scope-nya yayasan, TIDAK bergantung pada lembaga manapun -- sebelumnya
        // salah query ke Lembaga NPSN KB yang sekarang sudah dihapus.
        $yayasan = Yayasan::firstOrFail();

        if (! User::where('email', 'adm.yayasan@demo.test')->exists()) {
            $adminYayasan = User::create([
                'name' => 'Ahmad Fauzi (Admin Yayasan)',
                'email' => 'adm.yayasan@demo.test',
                'password' => 'password',
                'yayasan_id' => $yayasan->id,
                'email_verified_at' => now(),
                'is_active' => true,
            ]);
            $adminYayasan->assignRole('yayasan_super_admin');
        }

        $sdit = Lembaga::where('npsn', '20223333')->firstOrFail();

        // SDIT -- pimpinan + 12 wali kelas + 3 guru mapel spesialis (total 15 guru)
        $this->seedStaf($sdit, [
            ['name' => 'Abdullah, M.Pd.', 'email' => 'kepsek.sd@demo.test', 'role' => 'kepala_sekolah'],
            ['name' => 'Lukman, S.Kom.', 'email' => 'adm.sd@demo.test', 'role' => 'admin_administrasi'],
            ['name' => 'Hasan, S.E.', 'email' => 'keuangan.sd@demo.test', 'role' => 'admin_keuangan'],
        ], [
            ['name' => 'Sari Wulandari, S.Pd.', 'email' => 'sari.wulandari@demo.test'],
            ['name' => 'Agus Setiawan, S.Pd.', 'email' => 'agus.setiawan@demo.test'],
            ['name' => 'Nita Kurniawati, S.Pd.', 'email' => 'nita.kurniawati@demo.test'],
            ['name' => 'Rudi Hartono, S.Pd.', 'email' => 'rudi.hartono@demo.test'],
            ['name' => 'Wahyu Astuti, S.Pd.', 'email' => 'wahyu.astuti@demo.test'],
            ['name' => 'Dedi Iskandar, S.Pd.', 'email' => 'dedi.iskandar@demo.test'],
            ['name' => 'Fitriani Rahmawati, S.Pd.', 'email' => 'fitriani.rahmawati@demo.test'],
            ['name' => 'Bambang Wijaya, S.Pd.', 'email' => 'bambang.wijaya@demo.test'],
            ['name' => 'Ratna Puspita, S.Pd.', 'email' => 'ratna.puspita@demo.test'],
            ['name' => 'Yusuf Maulana, S.Pd.', 'email' => 'yusuf.maulana@demo.test'],
            ['name' => 'Lina Marlina, S.Pd.', 'email' => 'lina.marlina@demo.test'],
            ['name' => 'Irfan Hakim, S.Pd.', 'email' => 'irfan.hakim@demo.test'],
            ['name' => 'Hendra Gunawan, S.Pd.', 'email' => 'hendra.gunawan@demo.test'],
            ['name' => 'Maya Anggraini, S.Pd.', 'email' => 'maya.anggraini@demo.test'],
            ['name' => 'Taufik Hidayat, S.Pd.', 'email' => 'taufik.hidayat@demo.test'],
        ]);
    }

    private function seedStaf(Lembaga $lembaga, array $pimpinan, array $guruList): void
    {
        foreach ($pimpinan as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => 'password',
                    'lembaga_id' => $lembaga->id,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );
            $user->update(['name' => $data['name'], 'lembaga_id' => $lembaga->id]);
            $user->assignRole($data['role']);
        }

        foreach ($guruList as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => 'password',
                    'lembaga_id' => $lembaga->id,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );
            $user->update(['name' => $data['name'], 'lembaga_id' => $lembaga->id]);
            $user->assignRole('guru');
        }
    }
}
