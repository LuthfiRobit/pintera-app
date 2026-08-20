<?php

namespace App\Domains\Akademik\Models;

use App\Enums\TipeKalenderAkademik;
use App\Models\Lembaga;
use Database\Factories\KalenderAkademikFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KalenderAkademik extends Model
{
    use HasFactory;

    protected static function newFactory(): KalenderAkademikFactory
    {
        return KalenderAkademikFactory::new();
    }

    protected $table = 'kalender_akademik';

    protected $fillable = ['lembaga_id', 'tanggal', 'tanggal_selesai', 'nama', 'tipe', 'keterangan'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'tanggal_selesai' => 'date',
            'tipe' => TipeKalenderAkademik::class,
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function scopeNasional($query)
    {
        return $query->whereNull('lembaga_id');
    }

    public function scopeUntukLembaga($query, int $lembagaId)
    {
        return $query->where('lembaga_id', $lembagaId);
    }
}
