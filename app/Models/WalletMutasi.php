<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletMutasi extends Model
{
    use HasFactory;

    protected $table = 'wallet_mutasi';

    protected $fillable = [
        'wallet_id',
        'pembayaran_id',
        'tipe',
        'amount',
        'saldo_sebelum',
        'saldo_sesudah',
        'keterangan',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function pembayaran(): BelongsTo
    {
        return $this->belongsTo(Pembayaran::class);
    }
}
