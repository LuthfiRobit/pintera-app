<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatPendidikanGuru extends Model
{
    protected $table = 'riwayat_pendidikan_guru';

    protected $fillable = [
        'guru_id', 'jenjang_pendidikan', 'gelar_akademik', 'sekolah_formal',
        'fakultas', 'bidang_studi', 'kependidikan', 'tahun_masuk', 'tahun_lulus',
    ];

    protected function casts(): array
    {
        return ['kependidikan' => 'boolean'];
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }
}
