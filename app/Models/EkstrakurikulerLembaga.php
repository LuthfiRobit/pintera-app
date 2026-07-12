<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EkstrakurikulerLembaga extends Model
{
    use BelongsToTenant;

    protected $table = 'ekstrakurikuler_lembaga';

    protected $fillable = ['lembaga_id', 'jenis_ekskul', 'nama_ekskul', 'no_sk', 'tanggal_sk', 'jam_per_minggu'];

    protected function casts(): array
    {
        return ['tanggal_sk' => 'date'];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }
}
