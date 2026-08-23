<?php
// app/Models/Tagihan.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Tagihan extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'tagihan';

    protected $fillable = [
        'pendaftaran_id', 'tagihable_type', 'tagihable_id', 'jenis_tagihan_id',
        'kategori', 'billing_period', 'source_trigger',
        'total_tagihan', 'discount_amount', 'discount_type', 'net_amount', 'paid_amount',
        'status', 'jatuh_tempo', 'cancelled_by', 'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'jatuh_tempo' => 'date',
            'discount_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(Pendaftaran::class);
    }

    public function tagihable(): MorphTo
    {
        return $this->morphTo();
    }

    public function jenisTagihan(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Keuangan\Models\JenisTagihan::class);
    }

    public function item(): HasMany
    {
        return $this->hasMany(\App\Domains\Keuangan\Models\TagihanItem::class);
    }

    public function skemaCicilan(): HasOne
    {
        return $this->hasOne(\App\Domains\Keuangan\Models\SkemaCicilan::class);
    }

    public function cicilan(): HasManyThrough
    {
        return $this->hasManyThrough(Cicilan::class, \App\Domains\Keuangan\Models\SkemaCicilan::class, 'tagihan_id', 'skema_cicilan_id');
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class);
    }

    public function pembayaranTagihan(): HasMany
    {
        return $this->hasMany(PembayaranTagihan::class);
    }

    /**
     * A tagihan can bundle multiple jenis_tagihan (line items) with different
     * bisa_dicicil rules — offering installment is allowed if ANY item is
     * cicilable, and the safe max termin count is the smallest maks_cicilan
     * among the cicilable items (never lets the whole invoice cicil beyond
     * what any single cicilable item's own rule allows).
     */
    public function bisaDicicil(): bool
    {
        return $this->item()->whereHas('jenisTagihan', fn ($q) => $q->where('bisa_dicicil', true))->exists();
    }

    public function maksCicilan(): ?int
    {
        return $this->item()
            ->whereHas('jenisTagihan', fn ($q) => $q->where('bisa_dicicil', true))
            ->with('jenisTagihan')
            ->get()
            ->min(fn (\App\Domains\Keuangan\Models\TagihanItem $item) => $item->jenisTagihan->maks_cicilan);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'total_tagihan'])
            ->logOnlyDirty()
            ->useLogName('tagihan');
    }
}
