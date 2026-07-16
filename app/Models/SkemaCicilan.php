<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkemaCicilan extends Model
{
    use HasFactory;

    protected $table = 'skema_cicilan';

    protected $fillable = ['tagihan_id', 'jumlah_termin', 'dibuat_oleh', 'dibuat_oleh_user_id'];

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class);
    }

    public function cicilan(): HasMany
    {
        return $this->hasMany(Cicilan::class)->orderBy('urutan');
    }

    public function dibuatOlehUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh_user_id');
    }
}
