<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Pembayaran extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'pembayaran';

    protected $fillable = [
        'tagihan_id', 'cicilan_id', 'sumber', 'metode', 'file_path',
        'status', 'catatan_verifikasi', 'diverifikasi_oleh_user_id', 'diverifikasi_pada',
    ];

    protected function casts(): array
    {
        return [
            'diverifikasi_pada' => 'datetime',
        ];
    }

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class);
    }

    public function cicilan(): BelongsTo
    {
        return $this->belongsTo(Cicilan::class);
    }

    public function diverifikasiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh_user_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'catatan_verifikasi', 'diverifikasi_oleh_user_id'])
            ->logOnlyDirty()
            ->useLogName('pembayaran');
    }
}
