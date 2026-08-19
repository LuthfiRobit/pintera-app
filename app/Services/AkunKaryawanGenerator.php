<?php

namespace App\Services;

use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AkunKaryawanGenerator
{
    public function buat(
        string $nama,
        string $nik,
        int $yayasanId,
        ?int $lembagaId,
        int $jenisKaryawanId,
        ?string $noHp = null,
        ?string $email = null,
    ): Karyawan {
        return DB::transaction(function () use ($nama, $nik, $yayasanId, $lembagaId, $jenisKaryawanId, $noHp, $email) {
            $user = User::create([
                'name' => $nama,
                'email' => null,
                'username' => $nik,
                'password' => Hash::make($nik),
                'lembaga_id' => $lembagaId,
                'yayasan_id' => $lembagaId === null ? $yayasanId : null,
                'email_verified_at' => null,
                'is_active' => true,
                'must_change_password' => true,
            ]);
            $user->assignRole($lembagaId === null ? 'karyawan_pool' : 'karyawan_lembaga');

            return Karyawan::create([
                'user_id' => $user->id,
                'yayasan_id' => $yayasanId,
                'lembaga_id' => $lembagaId,
                'jenis_karyawan_id' => $jenisKaryawanId,
                'nama' => $nama,
                'nik' => $nik,
                'no_hp' => $noHp,
                'email' => $email,
                'status_aktif' => 'aktif',
            ]);
        });
    }
}
