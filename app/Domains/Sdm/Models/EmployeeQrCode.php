<?php

namespace App\Domains\Sdm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmployeeQrCode extends Model
{
    protected $table = 'employee_qr_codes';

    protected $attributes = [
        'is_active' => true,
    ];

    protected $fillable = ['pegawai_type', 'pegawai_id', 'token', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function pegawai(): MorphTo
    {
        return $this->morphTo();
    }
}
