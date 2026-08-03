<?php

namespace App\Models;

use App\Enums\StatusSiswa;
use App\Enums\SumberDataSiswa;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Siswa extends Model
{
    use HasFactory, BelongsToTenant, LogsActivity;

    protected $table = 'siswa';

    protected $fillable = [
        'lembaga_id', 'kelas_id', 'calon_murid_id', 'pendaftaran_asal_id', 'user_id',
        'sumber_data', 'nis', 'nisn', 'nama_lengkap', 'jenis_kelamin',
        'tempat_lahir', 'tanggal_lahir', 'agama', 'status',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'sumber_data' => SumberDataSiswa::class,
            'status' => StatusSiswa::class,
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function calonMurid(): BelongsTo
    {
        return $this->belongsTo(CalonMurid::class);
    }

    public function pendaftaranAsal(): BelongsTo
    {
        return $this->belongsTo(Pendaftaran::class, 'pendaftaran_asal_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orangTua(): BelongsToMany
    {
        return $this->belongsToMany(OrangTua::class, 'siswa_orang_tua')
            ->withPivot(['hubungan', 'is_kontak_utama'])
            ->withTimestamps()
            ->using(SiswaOrangTua::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nama_lengkap', 'kelas_id', 'status', 'lembaga_id'])
            ->logOnlyDirty()
            ->useLogName('siswa');
    }
}
