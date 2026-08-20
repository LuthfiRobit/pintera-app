<?php

namespace App\Domains\Akademik\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Kelas;
use App\Models\Lembaga;
use Database\Factories\PolaJamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PolaJam extends Model
{
    use HasFactory, BelongsToTenant;

    protected static function newFactory(): PolaJamFactory
    {
        return PolaJamFactory::new();
    }

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

    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class);
    }
}
