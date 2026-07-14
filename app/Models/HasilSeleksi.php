<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HasilSeleksi extends Model
{
    use HasFactory;

    protected $table = 'hasil_seleksi';

    protected $fillable = [
        'pendaftaran_id',
        'seleksi_ppdb_id',
        'nilai',
        'catatan',
        'dinilai_oleh_user_id',
        'dinilai_pada',
    ];

    protected function casts(): array
    {
        return [
            'nilai' => 'decimal:2',
            'dinilai_pada' => 'datetime',
        ];
    }

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(Pendaftaran::class);
    }

    public function seleksiPpdb(): BelongsTo
    {
        return $this->belongsTo(SeleksiPpdb::class);
    }

    public function dinilaiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dinilai_oleh_user_id');
    }
}
