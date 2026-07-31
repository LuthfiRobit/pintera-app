<?php

namespace App\Services;

use App\Models\Lembaga;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AkunSiswaGenerator
{
    public function buat(string $namaLengkap, string $nis, Lembaga $lembaga): User
    {
        $user = User::create([
            'name' => $namaLengkap,
            'email' => null,
            'username' => $this->usernameUntuk($lembaga, $nis),
            'password' => Hash::make($nis),
            'lembaga_id' => $lembaga->id,
            'email_verified_at' => null,
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $user->assignRole('siswa');

        return $user;
    }

    public function usernameUntuk(Lembaga $lembaga, string $nis): string
    {
        return $lembaga->kode_lembaga.'-'.$nis;
    }
}
