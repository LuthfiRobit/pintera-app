<?php

namespace App\Models;

use App\Domains\Identity\Models\Person;
use App\Models\Concerns\BelongsToTenantViaPerson;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\Notifiable;

class OrangTua extends Model
{
    use BelongsToTenantViaPerson, HasFactory, Notifiable;

    protected $table = 'orang_tua';

    protected $fillable = [
        'person_id', 'user_id', 'nama_lengkap', 'nik', 'no_hp', 'email', 'alamat', 'pekerjaan',
    ];

    protected static function booted(): void
    {
        static::creating(function (OrangTua $orangTua) {
            if (empty($orangTua->person_id)) {
                $user = $orangTua->user_id ? User::withoutGlobalScopes()->find($orangTua->user_id) : null;
                $yayasanId = $user?->yayasan_id ?? auth()->user()?->yayasan_id ?? auth()->user()?->lembaga?->yayasan_id ?? Yayasan::first()?->id ?? Yayasan::factory()->create()->id;
                $person = Person::create([
                    'yayasan_id' => $yayasanId,
                    'user_id' => $orangTua->user_id,
                    'nama_lengkap' => $orangTua->attributes['nama_lengkap'] ?? 'Orang Tua',
                    'nik' => $orangTua->attributes['nik'] ?? null,
                    'no_hp' => $orangTua->attributes['no_hp'] ?? null,
                    'email' => $orangTua->attributes['email'] ?? null,
                    'alamat_jalan' => $orangTua->attributes['alamat'] ?? null,
                ]);
                $orangTua->person_id = $person->id;
            }
        });

        static::saved(function (OrangTua $orangTua) {
            if ($orangTua->user_id && $orangTua->person_id) {
                Person::withoutGlobalScopes()->where('id', $orangTua->person_id)->update(['user_id' => $orangTua->user_id]);
            }
        });
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function getNamaLengkapAttribute(): ?string
    {
        return $this->person?->nama_lengkap ?? $this->attributes['nama_lengkap'] ?? null;
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

    public function getAlamatAttribute(): ?string
    {
        return $this->person?->alamat_jalan ?? $this->attributes['alamat'] ?? null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
                ->orWhere('nik_hash', $termHash))
                ->orWhere('orang_tua.nama_lengkap', 'like', "%{$term}%")
                ->orWhere('orang_tua.nik', $term);
        });
    }

    public function scopeOrderByNama($query, string $direction = 'asc')
    {
        return $query->leftJoin('persons', 'orang_tua.person_id', '=', 'persons.id')
            ->orderByRaw("COALESCE(persons.nama_lengkap, orang_tua.nama_lengkap) {$direction}")
            ->select('orang_tua.*');
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->withoutGlobalScopes()->where($field ?? $this->getRouteKeyName(), $value)->first();
    }
}
