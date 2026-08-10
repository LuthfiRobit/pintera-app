<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisTagihan extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'jenis_tagihan';

    protected $attributes = [
        'mode' => 'manual',
        'is_active' => true,
    ];

    protected $fillable = [
        'lembaga_id', 'nama', 'kategori', 'bisa_dicicil', 'maks_cicilan',
        'priority_score', 'default_amount', 'mode',
        'tanggal_mulai', 'tanggal_selesai', 'tanggal_generate', 'hari_jatuh_tempo',
        'va_expire_hours', 'is_active', 'last_generated_period',
    ];

    protected function casts(): array
    {
        return [
            'bisa_dicicil' => 'boolean',
            'default_amount' => 'decimal:2',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function nominalJalur(): HasMany
    {
        return $this->hasMany(NominalTagihanJalur::class);
    }

    public function tagihanItem(): HasMany
    {
        return $this->hasMany(TagihanItem::class);
    }

    public function sasaranGrup(): HasMany
    {
        return $this->hasMany(JenisTagihanSasaranGrup::class);
    }

    public function nominalTagihanSiswa(): HasMany
    {
        return $this->hasMany(NominalTagihanSiswa::class);
    }

    public function keringananRules(): HasMany
    {
        return $this->hasMany(JenisTagihanKeringanan::class);
    }
}
