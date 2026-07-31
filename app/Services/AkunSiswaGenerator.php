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
            'username' => $lembaga->kode_lembaga.'-'.$nis,
            'password' => Hash::make($nis),
            'lembaga_id' => $lembaga->id,
            'email_verified_at' => null,
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $user->assignRole('siswa');

        return $user;
    }
}
