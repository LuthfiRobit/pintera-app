<?php

namespace App\Services;

use App\Models\OrangTua;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AkunOrangTuaGenerator
{
    public function buat(
        string $namaLengkap,
        string $nik,
        string $noHp,
        ?string $email = null,
        ?string $alamat = null,
        ?string $pekerjaan = null,
    ): OrangTua {
        return DB::transaction(function () use ($namaLengkap, $nik, $noHp, $email, $alamat, $pekerjaan) {
            $user = User::create([
                'name' => $namaLengkap,
                'email' => null,
                'username' => $nik,
                'password' => Hash::make($nik),
                'lembaga_id' => null,
                'email_verified_at' => null,
                'is_active' => true,
                'must_change_password' => true,
            ]);
            $user->assignRole('orang_tua');

            return OrangTua::create([
                'user_id' => $user->id,
                'nama_lengkap' => $namaLengkap,
                'nik' => $nik,
                'no_hp' => $noHp,
                'email' => $email,
                'alamat' => $alamat,
                'pekerjaan' => $pekerjaan,
            ]);
        });
    }
}
