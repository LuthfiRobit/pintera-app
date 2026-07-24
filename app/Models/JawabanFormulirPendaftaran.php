<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JawabanFormulirPendaftaran extends Model
{
    protected $table = 'jawaban_formulir_pendaftaran';

    protected $fillable = [
        'pendaftaran_id',
        'formulir_field_id',
        'nilai',
        'nama_file_asli',
        'mime_type',
        'ukuran_bytes',
    ];

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(Pendaftaran::class);
    }

    public function formulirField(): BelongsTo
    {
        return $this->belongsTo(FormulirField::class);
    }
}
