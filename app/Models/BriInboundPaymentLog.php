<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BriInboundPaymentLog extends Model
{
    protected $fillable = [
        'payment_request_id',
        'va_number',
        'amount',
        'pembayaran_id',
    ];

    public function pembayaran(): BelongsTo
    {
        return $this->belongsTo(Pembayaran::class);
    }
}
