<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class GuruJabatanTambahan extends Pivot
{
    protected $table = 'guru_jabatan_tambahan';

    protected function casts(): array
    {
        return [
            'mulai_periode' => 'date',
            'akhir_periode' => 'date',
        ];
    }
}
