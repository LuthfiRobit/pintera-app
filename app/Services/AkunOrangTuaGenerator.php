<?php

namespace App\Services;

use App\Domains\Identity\Actions\CreatePersonAction;
use App\Models\OrangTua;
use App\Models\User;
use App\Models\Yayasan;
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
        ?int $yayasanId = null,
    ): OrangTua {
        return DB::transaction(function () use ($namaLengkap, $nik, $noHp, $email, $alamat, $pekerjaan, $yayasanId) {
            $yayasanId ??= auth()->user()?->yayasan_id
                ?? auth()->user()?->lembaga?->yayasan_id
                ?? Yayasan::first()?->id
                ?? Yayasan::factory()->create()->id;

            $person = app(CreatePersonAction::class)->execute(
                identityData: [
                    'nama_lengkap' => $namaLengkap,
                    'nik' => $nik,
                    'no_hp' => $noHp,
                    'email' => $email,
                    'alamat_jalan' => $alamat,
                ],
                lembagaId: null,
                actingYayasanId: $yayasanId,
            );

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
            $person->update(['user_id' => $user->id]);

            return OrangTua::create([
                'person_id' => $person->id,
                'pekerjaan' => $pekerjaan,
            ]);
        });
    }
}
