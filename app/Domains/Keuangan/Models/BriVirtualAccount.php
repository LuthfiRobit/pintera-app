<?php

namespace App\Domains\Keuangan\Models;

use Database\Factories\BriVirtualAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BriVirtualAccount extends Model
{
    use HasFactory;

    protected static function newFactory(): BriVirtualAccountFactory
    {
        return BriVirtualAccountFactory::new();
    }

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'expired_at' => 'datetime',
        'callback_payload' => 'array',
    ];

    public function pembayaran(): BelongsTo
    {
        return $this->belongsTo(Pembayaran::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}