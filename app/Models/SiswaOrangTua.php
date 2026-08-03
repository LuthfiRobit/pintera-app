<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class SiswaOrangTua extends Pivot
{
    protected $table = 'siswa_orang_tua';

    protected $fillable = ['siswa_id', 'orang_tua_id', 'hubungan', 'is_kontak_utama'];

    protected function casts(): array
    {
        return [
            'is_kontak_utama' => 'boolean',
        ];
    }
}
