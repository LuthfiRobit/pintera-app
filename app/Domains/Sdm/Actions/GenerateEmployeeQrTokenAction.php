<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions;

use App\Domains\Sdm\Models\EmployeeQrCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class GenerateEmployeeQrTokenAction
{
    public function execute(Model $pegawai): EmployeeQrCode
    {
        return DB::transaction(function () use ($pegawai) {
            EmployeeQrCode::where('pegawai_type', $pegawai::class)
                ->where('pegawai_id', $pegawai->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            return EmployeeQrCode::create([
                'pegawai_type' => $pegawai::class,
                'pegawai_id' => $pegawai->id,
                'token' => Str::random(48),
                'is_active' => true,
            ]);
        });
    }
}
