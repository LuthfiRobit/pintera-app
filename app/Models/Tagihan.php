<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Tagihan extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'tagihan';

    protected $fillable = ['pendaftaran_id', 'kategori', 'total_tagihan', 'status', 'jatuh_tempo'];

    protected function casts(): array
    {
        return [
            'jatuh_tempo' => 'date',
        ];
    }

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(Pendaftaran::class);
    }

    public function item(): HasMany
    {
        return $this->hasMany(TagihanItem::class);
    }

    public function skemaCicilan(): HasOne
    {
        return $this->hasOne(SkemaCicilan::class);
    }

    public function cicilan(): HasManyThrough
    {
        return $this->hasManyThrough(Cicilan::class, SkemaCicilan::class, 'tagihan_id', 'skema_cicilan_id');
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
            ->min(fn (TagihanItem $item) => $item->jenisTagihan->maks_cicilan);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'total_tagihan'])
            ->logOnlyDirty()
            ->useLogName('tagihan');
    }
}
