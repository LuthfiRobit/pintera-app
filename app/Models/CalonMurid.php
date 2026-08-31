<?php

namespace App\Models;

use App\Domains\Identity\Models\Person;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CalonMurid extends Model
{
    use HasFactory;

    protected $table = 'calon_murid';

    protected $fillable = [
        'person_id',
        'yayasan_id',
        'no_kk',
        'nisn',
        'golongan_darah',
    ];

    protected function casts(): array
    {
        return [
            'no_kk' => 'encrypted',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function getNamaLengkapAttribute(): ?string
    {
        return $this->person?->nama_lengkap;
    }

    public function getNikAttribute(): ?string
    {
        return $this->person?->nik;
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

    public function getNoTeleponAttribute(): ?string
    {
        return $this->person?->no_hp;
    }

    public function getEmailKontakAttribute(): ?string
    {
        return $this->person?->email;
    }

    public static function findByNik(string $nik): ?self
    {
        return static::whereHas('person', fn ($q) => $q->where('nik_hash', hash('sha256', $nik)))->first();
    }

    public function yayasan(): BelongsTo
    {
        return $this->belongsTo(Yayasan::class);
    }

    public function alamat(): HasOne
    {
        return $this->hasOne(AlamatCalonMurid::class);
    }

    public function keluarga(): HasMany
    {
        return $this->hasMany(KeluargaCalonMurid::class);
    }

    public function dataPeriodik(): HasOne
    {
        return $this->hasOne(DataPeriodikCalonMurid::class);
    }

    public function dataKhusus(): HasOne
    {
        return $this->hasOne(DataKhususCalonMurid::class);
    }

    public function pendaftaran(): HasMany
    {
        return $this->hasMany(Pendaftaran::class);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        $termHash = hash('sha256', $term);

        return $query->where(function ($q) use ($term, $termHash) {
            $q->whereHas('person', fn ($qp) => $qp->where('nama_lengkap', 'like', "%{$term}%")
                ->orWhere('nik_hash', $termHash))
                ->orWhere('calon_murid.nisn', 'like', "%{$term}%");
        });
    }

    public function scopeOrderByNama($query, string $direction = 'asc')
    {
        return $query->leftJoin('persons', 'calon_murid.person_id', '=', 'persons.id')
            ->orderBy('persons.nama_lengkap', $direction)
            ->select('calon_murid.*');
    }
}
