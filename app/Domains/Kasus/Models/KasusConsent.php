<?php

namespace App\Domains\Kasus\Models;

use Database\Factories\KasusConsentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KasusConsent extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): KasusConsentFactory
    {
        return KasusConsentFactory::new();
    }

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
