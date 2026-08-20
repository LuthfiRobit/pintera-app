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
            '20223311' => ['email' => 'pendaftar.kbit@example.test', 'nama' => 'Wali KBIT (Contoh)'],
            '20223322' => ['email' => 'pendaftar.tkit@example.test', 'nama' => 'Wali TKIT (Contoh)'],
            '20223333' => ['email' => 'pendaftar.sdit@example.test', 'nama' => 'Wali SDIT (Contoh)'],
            '20223344' => ['email' => 'pendaftar.smpit@example.test', 'nama' => 'Wali SMPIT (Contoh)'],
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

            $diterima = Pendaftaran::where('lembaga_id', $lembaga->id)->where('email_pendaftaran', 'wali.diterima@example.test')->first();

            if ($diterima) {
                $diterima->update(['akun_pendaftar_id' => $akun->id]);
            }
        }
    }
}
