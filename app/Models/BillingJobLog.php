<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingJobLog extends Model
{
    protected $table = 'billing_job_logs';

    protected $fillable = [
        'jenis_tagihan_id', 'trigger_type', 'trigger_event', 'period',
        'bills_generated', 'status', 'error_log', 'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'error_log' => 'array',
            'executed_at' => 'datetime',
        ];
    }

    public function jenisTagihan(): BelongsTo
    {
        return $this->belongsTo(JenisTagihan::class);
    }
}
