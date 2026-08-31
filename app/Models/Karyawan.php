<?php

namespace App\Models;

use App\Domains\Identity\Models\Person;
use App\Domains\Sdm\Models\AttendanceEvent;
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Domains\Sdm\Models\EmployeeQrCode;
use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Domains\Sdm\Models\PengajuanIzinCuti;
use App\Domains\Sdm\Models\PenugasanShift;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Karyawan extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'karyawan';

    protected $fillable = [
        'person_id', 'user_id', 'yayasan_id', 'lembaga_id', 'jenis_karyawan_id',
        'nama', 'nik', 'nik_hash', 'no_hp', 'email', 'status_aktif', 'kapasitas_kasus_aktif',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function getNamaAttribute(): ?string
    {
        return $this->person?->nama_lengkap ?? $this->attributes['nama'] ?? null;
    }

    public function getNikAttribute(): ?string
    {
        return $this->person?->nik ?? $this->attributes['nik'] ?? null;
    }

    public function getNoHpAttribute(): ?string
    {
        return $this->person?->no_hp ?? $this->attributes['no_hp'] ?? null;
    }

    public function getEmailAttribute(): ?string
    {
        return $this->person?->email ?? $this->attributes['email'] ?? null;
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

    protected static function booted(): void
    {
        static::creating(function (Karyawan $karyawan) {
            if (empty($karyawan->person_id)) {
                $yayasanId = $karyawan->yayasan_id ?? ($karyawan->lembaga_id ? Lembaga::find($karyawan->lembaga_id)?->yayasan_id : null) ?? Yayasan::first()?->id ?? Yayasan::factory()->create()->id;
                $person = Person::create([
                    'yayasan_id' => $yayasanId,
                    'user_id' => $karyawan->user_id,
                    'nama_lengkap' => $karyawan->attributes['nama'] ?? 'Karyawan',
                    'nik' => $karyawan->attributes['nik'] ?? null,
                    'no_hp' => $karyawan->attributes['no_hp'] ?? null,
                    'email' => $karyawan->attributes['email'] ?? null,
                ]);
                $karyawan->person_id = $person->id;
            }
        });

        static::saving(function (Karyawan $karyawan) {
            if (! empty($karyawan->attributes['nik'])) {
                $karyawan->nik_hash = hash('sha256', $karyawan->attributes['nik']);
            }
        });

        static::saved(function (Karyawan $karyawan) {
            if ($karyawan->user_id && $karyawan->person_id) {
                Person::withoutGlobalScopes()->where('id', $karyawan->person_id)->update(['user_id' => $karyawan->user_id]);
            }
        });
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->whereHas('person', fn ($qp) => $qp->where('nama_lengkap', 'like', "%{$term}%"))
                ->orWhere('nama', 'like', "%{$term}%");
        });
    }

    public function scopeOrderByNama($query, string $direction = 'asc')
    {
        return $query->leftJoin('persons', 'karyawan.person_id', '=', 'persons.id')
            ->orderByRaw("COALESCE(persons.nama_lengkap, karyawan.nama) {$direction}")
            ->select('karyawan.*');
    }
}
