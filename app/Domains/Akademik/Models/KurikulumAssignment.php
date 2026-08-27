<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Models;

use App\Domains\Akademik\Enums\KurikulumFramework;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KurikulumAssignment extends Model
{
    protected $table = 'kurikulum_assignment';

    protected $fillable = [
        'lembaga_id',
        'tahun_ajaran_id',
        'bentuk_pendidikan',
        'tingkat',
        'kurikulum',
    ];

    protected function casts(): array
    {
        return [
            'kurikulum' => KurikulumFramework::class,
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }
}
