<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeluargaCalonMurid extends Model
{
    protected $table = 'keluarga_calon_murid';

    protected $fillable = [
        'calon_murid_id',
        'jenis',
        'nama',
        'nik',
        'tahun_lahir',
        'pendidikan_terakhir',
        'pekerjaan',
        'penghasilan',
    ];

    protected function casts(): array
    {
        return [
            'nik' => 'encrypted',
        ];
    }

    public function calonMurid(): BelongsTo
    {
        return $this->belongsTo(CalonMurid::class);
    }
}
