<?php
// database/seeders/AkunPendaftarSeeder.php

namespace Database\Seeders;

use App\Models\AkunPendaftar;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use Illuminate\Database\Seeder;

class AkunPendaftarSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn(static::class.': dilewati, hanya boleh jalan di environment local/testing.');

            return;
        }

        $emailPerNpsn = [
            '20223333' => ['email' => 'pendaftar.sd@demo.test', 'nama' => 'Wali SD (Contoh)'],
        ];

        foreach (Lembaga::whereIn('npsn', array_keys($emailPerNpsn))->get() as $lembaga) {
            $data = $emailPerNpsn[$lembaga->npsn];

            $akun = AkunPendaftar::firstOrCreate(
                ['email' => $data['email']],
                [
                    'nama' => $data['nama'],
                    'password' => 'password',
                    'email_verified_at' => now(),
                ]
            );

            $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@demo.test')->first();

            if ($diterima) {
                $diterima->update(['akun_pendaftar_id' => $akun->id]);
            }
        }
    }
}
