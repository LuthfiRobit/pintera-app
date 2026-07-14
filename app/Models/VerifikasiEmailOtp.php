<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerifikasiEmailOtp extends Model
{
    protected $table = 'verifikasi_email_otp';

    protected $fillable = [
        'email',
        'kode_otp',
        'expires_at',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }
}
