<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KasusEvaluasi extends Model
{
    use HasFactory;

    protected $table = 'kasus_evaluasi';

    protected $fillable = ['kasus_id', 'tanggal', 'catatan', 'keputusan', 'dibuat_oleh_user_id'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'datetime',
        ];
    }

    public function kasus(): BelongsTo
    {
        return $this->belongsTo(Kasus::class);
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh_user_id');
    }
}
