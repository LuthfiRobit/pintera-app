<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PolaJam extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'pola_jam';

    protected $fillable = ['lembaga_id', 'nama'];

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function jamPelajaran(): HasMany
    {
        return $this->hasMany(JamPelajaran::class);
    }
}
