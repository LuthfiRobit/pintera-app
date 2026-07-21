<?php

namespace App\Models;

use Database\Factories\AkunPendaftarFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class AkunPendaftar extends Authenticatable
{
    /** @use HasFactory<AkunPendaftarFactory> */
    use HasFactory, Notifiable;

    protected $table = 'akun_pendaftar';

    protected $fillable = [
        'nama',
        'email',
        'no_hp_wa',
        'password',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function pendaftaran(): HasMany
    {
        return $this->hasMany(Pendaftaran::class, 'akun_pendaftar_id');
    }
}
