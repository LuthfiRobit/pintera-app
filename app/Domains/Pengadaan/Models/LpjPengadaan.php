<?php

namespace App\Domains\Pengadaan\Models;

use App\Domains\Pengadaan\Enums\StatusLpj;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LpjPengadaan extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'lpj_pengadaan';

    protected $fillable = [
        'pengajuan_pengadaan_id',
        'total_realisasi',
        'selisih_dana',
        'bukti_kembali_sisa_dana_path',
        'status_lpj',
        'catatan_verifikasi',
        'verified_at',
        'verified_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'total_realisasi' => 'decimal:2',
            'selisih_dana' => 'decimal:2',
            'status_lpj' => StatusLpj::class,
            'verified_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['total_realisasi', 'selisih_dana', 'status_lpj'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(PengajuanPengadaan::class, 'pengajuan_pengadaan_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LpjPengadaanItem::class, 'lpj_pengadaan_id');
    }
}
