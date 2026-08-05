<?php

namespace App\Models;

use App\Enums\StatusKasus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Kasus extends Model
{
    use HasFactory, BelongsToTenant, LogsActivity, SoftDeletes;

    protected $table = 'kasus';

    protected $fillable = [
        'siswa_id', 'lembaga_id', 'diajukan_oleh_guru_id', 'diajukan_oleh_orang_tua_id',
        'kategori_masalah', 'deskripsi', 'lampiran', 'tingkat_urgensi', 'status',
        'konselor_guru_id', 'konselor_karyawan_id', 'dikonfirmasi_pihak_lain_at',
    ];

    protected $attributes = [
        'status' => 'diajukan',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusKasus::class,
            'dikonfirmasi_pihak_lain_at' => 'datetime',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function diajukanOlehGuru(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'diajukan_oleh_guru_id');
    }

    public function diajukanOlehOrangTua(): BelongsTo
    {
        return $this->belongsTo(OrangTua::class, 'diajukan_oleh_orang_tua_id');
    }

    public function konselorGuru(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'konselor_guru_id');
    }

    public function konselorKaryawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'konselor_karyawan_id');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(KasusConsent::class);
    }

    public function sesi(): HasMany
    {
        return $this->hasMany(KasusSesi::class);
    }

    public function tugas(): HasMany
    {
        return $this->hasMany(KasusTugas::class);
    }

    public function evaluasi(): HasMany
    {
        return $this->hasMany(KasusEvaluasi::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'konselor_guru_id', 'konselor_karyawan_id'])
            ->logOnlyDirty()
            ->useLogName('kasus');
    }
}
