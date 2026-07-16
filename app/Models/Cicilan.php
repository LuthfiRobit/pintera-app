<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cicilan extends Model
{
    use HasFactory;

    protected $table = 'cicilan';

    protected $fillable = ['skema_cicilan_id', 'urutan', 'nominal', 'jatuh_tempo', 'status'];

    protected function casts(): array
    {
        return [
            'jatuh_tempo' => 'date',
        ];
    }

    public function skemaCicilan(): BelongsTo
    {
        return $this->belongsTo(SkemaCicilan::class);
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class);
    }
}
