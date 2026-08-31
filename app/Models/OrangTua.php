<?php

namespace App\Models;

use App\Domains\Identity\Models\Person;
use App\Models\Concerns\BelongsToTenantViaPerson;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Notifications\Notifiable;

class OrangTua extends Model
{
    use BelongsToTenantViaPerson, HasFactory, Notifiable;

    protected $table = 'orang_tua';

    protected $fillable = [
        'person_id', 'pekerjaan',
    ];

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

    public function getNikAttribute(): ?string
    {
        return $this->person?->nik;
    }

    public function getNoHpAttribute(): ?string
    {
        return $this->person?->no_hp;
    }

    public function getEmailAttribute(): ?string
    {
        return $this->person?->email;
    }

    public function getAlamatAttribute(): ?string
    {
        return $this->person?->alamat_jalan;
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

    public function siswa(): BelongsToMany
    {
        return $this->belongsToMany(Siswa::class, 'siswa_orang_tua')
            ->withoutGlobalScopes()
            ->withPivot(['hubungan', 'is_kontak_utama'])
            ->withTimestamps()
            ->using(SiswaOrangTua::class);
    }

    public function routeNotificationForMail(): ?string
    {
        return $this->email;
    }

    public function routeNotificationForWhatsapp(): ?string
    {
        return $this->no_hp;
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        $termHash = hash('sha256', $term);

        return $query->where(function ($q) use ($term, $termHash) {
            $q->whereHas('person', fn ($qp) => $qp->where('nama_lengkap', 'like', "%{$term}%")
                ->orWhere('nik_hash', $termHash));
        });
    }

    public function scopeOrderByNama($query, string $direction = 'asc')
    {
        return $query->leftJoin('persons', 'orang_tua.person_id', '=', 'persons.id')
            ->orderBy('persons.nama_lengkap', $direction)
            ->select('orang_tua.*');
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->withoutGlobalScopes()->where($field ?? $this->getRouteKeyName(), $value)->first();
    }
}
