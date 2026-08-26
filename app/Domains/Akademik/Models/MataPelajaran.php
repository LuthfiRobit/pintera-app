<?php

namespace App\Domains\Akademik\Models;

use App\Domains\Akademik\Contracts\SubjekPenilaian;
use App\Enums\KelompokMataPelajaran;
use App\Enums\StatusMataPelajaran;
use App\Enums\TipeMataPelajaran;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use Database\Factories\MataPelajaranFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MataPelajaran extends Model implements SubjekPenilaian
{
    use HasFactory, BelongsToTenant;

    protected static function newFactory(): MataPelajaranFactory
    {
        return MataPelajaranFactory::new();
    }

    protected $table = 'mata_pelajaran';

    protected $fillable = [
        'lembaga_id',
        'kode',
        'nama',
        'no_urut',
        'tipe',
        'kelompok',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tipe' => TipeMataPelajaran::class,
            'kelompok' => KelompokMataPelajaran::class,
            'status' => StatusMataPelajaran::class,
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }
}
