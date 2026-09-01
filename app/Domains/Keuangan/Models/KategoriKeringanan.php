<?php

// app/Domains/Keuangan/Models/KategoriKeringanan.php

namespace App\Domains\Keuangan\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use Database\Factories\KategoriKeringananFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KategoriKeringanan extends Model
{
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): KategoriKeringananFactory
    {
        return KategoriKeringananFactory::new();
    }

    protected $table = 'kategori_keringanan';

    protected $fillable = ['lembaga_id', 'nama', 'keterangan', 'bisa_digabung'];

    protected function casts(): array
    {
        return [
            'bisa_digabung' => 'boolean',
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function jenisTagihanKeringanan(): HasMany
    {
        return $this->hasMany(JenisTagihanKeringanan::class);
    }

    public function siswaKeringanan(): HasMany
    {
        return $this->hasMany(SiswaKeringanan::class);
    }
}
