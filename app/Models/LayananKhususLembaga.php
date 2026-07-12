<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LayananKhususLembaga extends Model
{
    use BelongsToTenant;

    protected $table = 'layanan_khusus_lembaga';

    protected $fillable = ['lembaga_id', 'jenis_layanan', 'no_sk', 'tmt', 'tst', 'keterangan'];

    protected function casts(): array
    {
        return ['tmt' => 'date', 'tst' => 'date'];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }
}
