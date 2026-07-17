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
        $emailPerNpsn = [
            '20223344' => ['email' => 'pendaftar.smp@example.test', 'nama' => 'Wali SMP (Contoh)'],
            '20223355' => ['email' => 'pendaftar.sma@example.test', 'nama' => 'Wali SMA (Contoh)'],
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
