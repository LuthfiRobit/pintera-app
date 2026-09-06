<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Models;

use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KartuSiswa extends Model
{
    protected $table = 'kartu_siswa';

    protected $fillable = ['siswa_id', 'tipe', 'kode', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class)->withoutGlobalScope(TenantScope::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function resolveSiswa(string $kode): ?Siswa
    {
        $kartu = self::aktif()->where('kode', $kode)->first();

        return $kartu?->siswa;
    }
}
