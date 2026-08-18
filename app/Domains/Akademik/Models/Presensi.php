<?php

namespace App\Domains\Akademik\Models;

use App\Domains\Akademik\Enums\StatusPresensi;
use App\Models\Siswa;
use Database\Factories\PresensiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presensi extends Model
{
    use HasFactory;

    protected $table = 'presensi';

    protected $fillable = ['sesi_pembelajaran_id', 'siswa_id', 'status', 'keterangan'];

    protected function casts(): array
    {
        return [
            'status' => StatusPresensi::class,
        ];
    }

    protected static function newFactory(): PresensiFactory
    {
        return PresensiFactory::new();
    }

    public function sesiPembelajaran(): BelongsTo
    {
        return $this->belongsTo(SesiPembelajaran::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }
}
