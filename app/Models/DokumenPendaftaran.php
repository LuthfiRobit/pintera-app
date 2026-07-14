<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DokumenPendaftaran extends Model
{
    protected $table = 'dokumen_pendaftaran';

    protected $fillable = [
        'pendaftaran_id',
        'dokumen_syarat_ppdb_id',
        'file_path',
        'nama_file_asli',
        'mime_type',
        'ukuran_bytes',
        'status_verifikasi',
    ];

    protected $attributes = [
        'status_verifikasi' => 'belum_diverifikasi',
    ];

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(Pendaftaran::class);
    }

    public function dokumenSyaratPpdb(): BelongsTo
    {
        return $this->belongsTo(DokumenSyaratPpdb::class);
    }
}
