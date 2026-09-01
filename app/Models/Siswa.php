<?php

// app/Models/Siswa.php

namespace App\Models;

use App\Domains\Identity\Models\Person;
use App\Domains\Keuangan\Models\SiswaKeringanan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Models\Wallet;
use App\Enums\StatusSiswa;
use App\Enums\SumberDataSiswa;
use App\Events\StudentCreated;
use App\Events\StudentUpdatedClass;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Siswa extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $table = 'siswa';

    protected $fillable = [
        'person_id', 'lembaga_id', 'kelas_id', 'calon_murid_id', 'pendaftaran_asal_id',
        'sumber_data', 'nis', 'nisn', 'status',
    ];

    protected function casts(): array
    {
        return [
            'sumber_data' => SumberDataSiswa::class,
            'status' => StatusSiswa::class,
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function getUserIdAttribute(): ?int
    {
        return $this->person?->user_id;
    }

    public function getNamaLengkapAttribute(): ?string
    {
        return $this->person?->nama_lengkap;
    }

    public function getJenisKelaminAttribute(): ?string
    {
        return $this->person?->jenis_kelamin;
    }

    public function getTempatLahirAttribute(): ?string
    {
        return $this->person?->tempat_lahir;
    }

    public function getTanggalLahirAttribute(): ?Carbon
    {
        return $this->person?->tanggal_lahir;
    }

    public function getAgamaAttribute(): ?string
    {
        return $this->person?->agama;
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

    public function user(): HasOneThrough
    {
        return $this->hasOneThrough(
            User::class,
            Person::class,
            'id',
            'id',
            'person_id',
            'user_id'
        );
    }

    public function orangTua(): BelongsToMany
    {
        return $this->belongsToMany(OrangTua::class, 'siswa_orang_tua')
            ->withoutGlobalScopes()
            ->withPivot(['hubungan', 'is_kontak_utama'])
            ->withTimestamps()
            ->using(SiswaOrangTua::class);
    }

    public function tagihan(): MorphMany
    {
        return $this->morphMany(Tagihan::class, 'tagihable');
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function siswaKeringanan(): HasMany
    {
        return $this->hasMany(SiswaKeringanan::class);
    }

    protected static function booted(): void
    {
        static::created(fn (Siswa $siswa) => event(new StudentCreated($siswa)));

        static::updated(function (Siswa $siswa) {
            if ($siswa->wasChanged('kelas_id')) {
                event(new StudentUpdatedClass($siswa));
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['kelas_id', 'status', 'lembaga_id'])
            ->logOnlyDirty()
            ->useLogName('siswa');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->whereHas('person', fn ($qp) => $qp->where('nama_lengkap', 'like', "%{$term}%"))
                ->orWhere('siswa.nis', 'like', "%{$term}%")
                ->orWhere('siswa.nisn', 'like', "%{$term}%");
        });
    }

    public function scopeOrderByNama($query, string $direction = 'asc')
    {
        return $query->leftJoin('persons', 'siswa.person_id', '=', 'persons.id')
            ->orderBy('persons.nama_lengkap', $direction)
            ->select('siswa.*');
    }
}
