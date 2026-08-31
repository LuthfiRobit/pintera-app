<?php

// app/Models/Siswa.php

namespace App\Models;

use App\Domains\Identity\Models\Person;
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
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Siswa extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $table = 'siswa';

    protected $fillable = [
        'person_id', 'lembaga_id', 'kelas_id', 'calon_murid_id', 'pendaftaran_asal_id', 'user_id',
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

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function getNamaLengkapAttribute(): ?string
    {
        return $this->person?->nama_lengkap ?? $this->attributes['nama_lengkap'] ?? null;
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

    protected static function booted(): void
    {
        static::creating(function (Siswa $siswa) {
            if (empty($siswa->person_id)) {
                $yayasanId = $siswa->yayasan_id ?? ($siswa->lembaga_id ? Lembaga::find($siswa->lembaga_id)?->yayasan_id : null) ?? Yayasan::first()?->id ?? Yayasan::factory()->create()->id;
                $person = Person::create([
                    'yayasan_id' => $yayasanId,
                    'user_id' => $siswa->user_id,
                    'nama_lengkap' => $siswa->attributes['nama_lengkap'] ?? 'Siswa',
                    'jenis_kelamin' => $siswa->attributes['jenis_kelamin'] ?? null,
                    'tempat_lahir' => $siswa->attributes['tempat_lahir'] ?? null,
                    'tanggal_lahir' => $siswa->attributes['tanggal_lahir'] ?? null,
                    'agama' => $siswa->attributes['agama'] ?? null,
                ]);
                $siswa->person_id = $person->id;
            }
        });

        static::created(fn (Siswa $siswa) => event(new StudentCreated($siswa)));

        static::saved(function (Siswa $siswa) {
            if ($siswa->user_id && $siswa->person_id) {
                Person::withoutGlobalScopes()->where('id', $siswa->person_id)->update(['user_id' => $siswa->user_id]);
            }
        });

        static::updated(function (Siswa $siswa) {
            if ($siswa->wasChanged('kelas_id')) {
                event(new StudentUpdatedClass($siswa));
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nama_lengkap', 'kelas_id', 'status', 'lembaga_id'])
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
                ->orWhere('siswa.nama_lengkap', 'like', "%{$term}%")
                ->orWhere('siswa.nis', 'like', "%{$term}%")
                ->orWhere('siswa.nisn', 'like', "%{$term}%");
        });
    }

    public function scopeOrderByNama($query, string $direction = 'asc')
    {
        return $query->leftJoin('persons', 'siswa.person_id', '=', 'persons.id')
            ->orderByRaw("COALESCE(persons.nama_lengkap, siswa.nama_lengkap) {$direction}")
            ->select('siswa.*');
    }
}
