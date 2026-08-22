<?php

namespace App\Domains\Sdm\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PenugasanShift extends Model
{
    use BelongsToTenant;

    protected $table = 'penugasan_shift';

    protected $fillable = ['lembaga_id', 'pegawai_type', 'pegawai_id', 'jenis_shift_id', 'tanggal_mulai', 'tanggal_selesai', 'hari_kerja'];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'hari_kerja' => 'array',
        ];
    }

    public function pegawai(): MorphTo
    {
        return $this->morphTo();
    }

    public function jenisShift(): BelongsTo
    {
        return $this->belongsTo(JenisShift::class);
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }
}
