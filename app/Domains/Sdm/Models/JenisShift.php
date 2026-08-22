<?php

namespace App\Domains\Sdm\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JenisShift extends Model
{
    use BelongsToTenant;

    protected $table = 'jenis_shift';

    protected $fillable = ['yayasan_id', 'lembaga_id', 'nama', 'jam_masuk', 'jam_pulang'];

    public function yayasan(): BelongsTo
    {
        return $this->belongsTo(Yayasan::class);
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }
}
