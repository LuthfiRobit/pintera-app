<?php

namespace App\Domains\Sdm\Models;

use App\Domains\Sdm\Enums\TipeKalenderKerjaSdm;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KalenderKerjaSdm extends Model
{
    use BelongsToTenant;

    protected $table = 'kalender_kerja_sdm';

    protected $fillable = ['yayasan_id', 'lembaga_id', 'tanggal', 'tanggal_selesai', 'nama', 'tipe', 'keterangan'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'tanggal_selesai' => 'date',
            'tipe' => TipeKalenderKerjaSdm::class,
        ];
    }

    public function yayasan(): BelongsTo
    {
        return $this->belongsTo(Yayasan::class);
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }
}
