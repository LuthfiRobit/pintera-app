<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Models;

use App\Models\Guru;
use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PiketHarian extends Model
{
    protected $table = 'piket_harian';

    protected $fillable = ['lembaga_id', 'guru_id', 'tanggal', 'sumber', 'jadwal_piket_mingguan_id'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function jadwalPiketMingguan(): BelongsTo
    {
        return $this->belongsTo(JadwalPiketMingguan::class);
    }
}
