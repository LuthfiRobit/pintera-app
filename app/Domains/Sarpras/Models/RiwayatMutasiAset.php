<?php

namespace App\Domains\Sarpras\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RiwayatMutasiAset extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'riwayat_mutasi_aset';

    protected $fillable = [
        'aset_barang_id',
        'ruangan_asal_id',
        'ruangan_tujuan_id',
        'qty_pindah',
        'tanggal_mutasi',
        'alasan_mutasi',
        'dilakukan_oleh_user_id',
    ];

    protected function casts(): array
    {
        return [
            'qty_pindah' => 'integer',
            'tanggal_mutasi' => 'date',
        ];
    }

    public function asetBarang(): BelongsTo
    {
        return $this->belongsTo(AsetBarang::class, 'aset_barang_id');
    }

    public function ruanganAsal(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_asal_id');
    }

    public function ruanganTujuan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_tujuan_id');
    }

    public function dilakukanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dilakukan_oleh_user_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['aset_barang_id', 'ruangan_asal_id', 'ruangan_tujuan_id', 'qty_pindah'])
            ->logOnlyDirty()
            ->useLogName('sarpras_mutasi');
    }
}
