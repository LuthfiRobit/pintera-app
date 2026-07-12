<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SertifikasiGuru extends Model
{
    protected $table = 'sertifikasi_guru';

    protected $fillable = [
        'guru_id', 'jenis_sertifikasi', 'nomor_sertifikat', 'bidang_studi_sertifikasi',
        'nrg', 'tahun_sertifikasi', 'kode_lembaga_sertifikasi',
    ];

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }
}
