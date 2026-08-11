<?php
// app/Models/Pembayaran.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Pembayaran extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'pembayaran';

    protected $attributes = [
        'is_auto_allocation' => false,
        'identifier_method' => 'manual',
    ];

    protected $fillable = [
        'tagihan_id', 'cicilan_id', 'sumber', 'metode', 'amount', 'file_path',
        'status', 'catatan_verifikasi', 'diverifikasi_oleh_user_id', 'diverifikasi_pada',
        'wallet_id', 'siswa_id', 'is_auto_allocation', 'channel_reference', 'identifier_method',
        'topup_status',
    ];

    protected function casts(): array
    {
        return [
            'diverifikasi_pada' => 'datetime',
            'is_auto_allocation' => 'boolean',
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

    public function pembayaranTagihan(): HasMany
    {
        return $this->hasMany(PembayaranTagihan::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function briVirtualAccount(): HasOne
    {
        return $this->hasOne(BriVirtualAccount::class);
    }

    public function briQrisPayment(): HasOne
    {
        return $this->hasOne(BriQrisPayment::class);
    }

    public function manualRequest(): HasOne
    {
        return $this->hasOne(ManualPaymentRequest::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'catatan_verifikasi', 'diverifikasi_oleh_user_id'])
            ->logOnlyDirty()
            ->useLogName('pembayaran');
    }
}
