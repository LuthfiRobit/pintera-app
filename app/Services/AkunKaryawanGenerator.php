<?php

namespace App\Services;

use App\Domains\Identity\Actions\CreatePersonAction;
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
            $person = app(CreatePersonAction::class)->execute(
                identityData: [
                    'nama_lengkap' => $nama,
                    'nik' => $nik,
                    'no_hp' => $noHp,
                    'email' => $email,
                ],
                lembagaId: $lembagaId,
                actingYayasanId: $lembagaId === null ? $yayasanId : null,
            );

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
            $user->assignRole($lembagaId === null ? 'pegawai_yayasan' : 'pegawai_lembaga');
            $person->update(['user_id' => $user->id]);

            return Karyawan::create([
                'person_id' => $person->id,
                'user_id' => $user->id,
                'yayasan_id' => $yayasanId,
                'lembaga_id' => $lembagaId,
                'jenis_karyawan_id' => $jenisKaryawanId,
                'status_aktif' => 'aktif',
            ]);
        });
    }
}
