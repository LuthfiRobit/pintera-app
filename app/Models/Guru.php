<?php

namespace App\Models;

use App\Domains\Identity\Models\Person;
use App\Domains\Sdm\Models\AttendanceEvent;
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Domains\Sdm\Models\EmployeeQrCode;
use App\Domains\Sdm\Models\JabatanTambahanMaster;
use App\Domains\Sdm\Models\PengajuanIzinCuti;
use App\Domains\Sdm\Models\PenugasanShift;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Guru extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, Notifiable;

    protected $table = 'guru';

    protected $fillable = [
        'person_id', 'user_id', 'lembaga_id', 'nik', 'nuptk', 'nip', 'nama', 'jenis_kelamin',
        'tempat_lahir', 'tanggal_lahir', 'agama', 'kewarganegaraan',
        'alamat_jalan', 'rt', 'rw', 'desa_kelurahan', 'kecamatan', 'kabupaten_kota',
        'provinsi', 'kode_pos', 'no_hp', 'email',
        'jenis_ptk', 'status_kepegawaian', 'golongan_pangkat', 'tmt_tugas', 'tmt_pns', 'status_aktif',
        'kapasitas_kasus_aktif',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'tmt_tugas' => 'date',
            'tmt_pns' => 'date',
        ];
    }

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
        return $this->person?->nik;
    }

    public function getJenisKelaminAttribute(): ?string
    {
        return $this->person?->jenis_kelamin ?? $this->attributes['jenis_kelamin'] ?? null;
    }

    public function getTempatLahirAttribute(): ?string
    {
        return $this->person?->tempat_lahir ?? $this->attributes['tempat_lahir'] ?? null;
    }

    public function getTanggalLahirAttribute(): ?Carbon
    {
        return $this->person?->tanggal_lahir ?? ($this->attributes['tanggal_lahir'] ?? null ? Carbon::parse($this->attributes['tanggal_lahir']) : null);
    }

    public function getAgamaAttribute(): ?string
    {
        return $this->person?->agama ?? $this->attributes['agama'] ?? null;
    }

    public function getNoHpAttribute(): ?string
    {
        return $this->person?->no_hp ?? $this->attributes['no_hp'] ?? null;
    }

    public function getEmailAttribute(): ?string
    {
        return $this->person?->email ?? $this->attributes['email'] ?? null;
    }

    public function getKewarganegaraanAttribute(): ?string
    {
        return $this->person?->kewarganegaraan ?? $this->attributes['kewarganegaraan'] ?? 'WNI';
    }

    public function getAlamatJalanAttribute(): ?string
    {
        return $this->person?->alamat_jalan ?? $this->attributes['alamat_jalan'] ?? null;
    }

    public function getRtAttribute(): ?string
    {
        return $this->person?->rt ?? $this->attributes['rt'] ?? null;
    }

    public function getRwAttribute(): ?string
    {
        return $this->person?->rw ?? $this->attributes['rw'] ?? null;
    }

    public function getDesaKelurahanAttribute(): ?string
    {
        return $this->person?->desa_kelurahan ?? $this->attributes['desa_kelurahan'] ?? null;
    }

    public function getKecamatanAttribute(): ?string
    {
        return $this->person?->kecamatan ?? $this->attributes['kecamatan'] ?? null;
    }

    public function getKabupatenKotaAttribute(): ?string
    {
        return $this->person?->kabupaten_kota ?? $this->attributes['kabupaten_kota'] ?? null;
    }

    public function getProvinsiAttribute(): ?string
    {
        return $this->person?->provinsi ?? $this->attributes['provinsi'] ?? null;
    }

    public function getKodePosAttribute(): ?string
    {
        return $this->person?->kode_pos ?? $this->attributes['kode_pos'] ?? null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function riwayatPendidikan(): HasMany
    {
        return $this->hasMany(RiwayatPendidikanGuru::class);
    }

    public function sertifikasi(): HasMany
    {
        return $this->hasMany(SertifikasiGuru::class);
    }

    public function jabatanTambahan(): BelongsToMany
    {
        return $this->belongsToMany(JabatanTambahanMaster::class, 'guru_jabatan_tambahan')
            ->withPivot(['mulai_periode', 'akhir_periode', 'no_sk'])
            ->withTimestamps()
            ->using(GuruJabatanTambahan::class);
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nama', 'jenis_ptk', 'status_kepegawaian', 'status_aktif', 'lembaga_id'])
            ->logOnlyDirty()
            ->useLogName('guru');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->whereHas('person', fn ($qp) => $qp->where('nama_lengkap', 'like', "%{$term}%"))
                ->orWhere('nama', 'like', "%{$term}%")
                ->orWhere('nip', 'like', "%{$term}%")
                ->orWhere('nuptk', 'like', "%{$term}%");
        });
    }

    public function scopeOrderByNama($query, string $direction = 'asc')
    {
        return $query->leftJoin('persons', 'guru.person_id', '=', 'persons.id')
            ->orderByRaw("COALESCE(persons.nama_lengkap, guru.nama) {$direction}")
            ->select('guru.*');
    }

    public function routeNotificationForMail(): ?string
    {
        return $this->email;
    }
}
