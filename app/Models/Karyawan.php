<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Sdm\Models\AttendanceEvent;
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Domains\Sdm\Models\EmployeeQrCode;
use App\Domains\Sdm\Models\PenugasanShift;
use App\Domains\Sdm\Models\PengajuanIzinCuti;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Karyawan extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'karyawan';

    protected $fillable = [
        'user_id', 'yayasan_id', 'lembaga_id', 'jenis_karyawan_id',
        'nama', 'nik', 'no_hp', 'email', 'status_aktif', 'kapasitas_kasus_aktif',
    ];

    protected function casts(): array
    {
        return [
            'nik' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Karyawan $karyawan) {
            $karyawan->nik_hash = hash('sha256', $karyawan->nik);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function yayasan(): BelongsTo
    {
        return $this->belongsTo(Yayasan::class);
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function jenisKaryawan(): BelongsTo
    {
        return $this->belongsTo(JenisKaryawanMaster::class, 'jenis_karyawan_id');
    }

    public function attendanceEvents(): MorphMany
    {
        return $this->morphMany(AttendanceEvent::class, 'pegawai');
    }

    public function attendanceRecords(): MorphMany
    {
        return $this->morphMany(AttendanceRecord::class, 'pegawai');
    }

    public function employeeQrCode(): MorphOne
    {
        return $this->morphOne(EmployeeQrCode::class, 'pegawai')->where('is_active', true);
    }

    public function penugasanShift(): MorphMany
    {
        return $this->morphMany(PenugasanShift::class, 'pegawai');
    }

    public function pengajuanIzinCuti(): MorphMany
    {
        return $this->morphMany(PengajuanIzinCuti::class, 'pegawai');
    }
}
