<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisKaryawanMaster extends Model
{
    use HasFactory;

    protected $table = 'jenis_karyawan_master';

    protected $fillable = ['nama'];

    public function karyawan(): HasMany
    {
        return $this->hasMany(Karyawan::class);
    }
}
