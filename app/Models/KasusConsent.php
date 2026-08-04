<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KasusConsent extends Model
{
    protected $table = 'kasus_consent';

    protected $fillable = ['kasus_id', 'jenis', 'status', 'disetujui_at'];

    protected function casts(): array
    {
        return [
            'disetujui_at' => 'datetime',
        ];
    }

    public function kasus(): BelongsTo
    {
        return $this->belongsTo(Kasus::class);
    }
}
