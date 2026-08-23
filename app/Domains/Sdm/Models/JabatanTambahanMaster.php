<?php

namespace App\Domains\Sdm\Models;

use App\Models\Guru;
use App\Models\GuruJabatanTambahan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class JabatanTambahanMaster extends Model
{
    protected $table = 'jabatan_tambahan_master';

    protected $fillable = ['nama', 'kelompok'];

    public function guru(): BelongsToMany
    {
        return $this->belongsToMany(Guru::class, 'guru_jabatan_tambahan')
            ->withPivot(['mulai_periode', 'akhir_periode', 'no_sk'])
            ->withTimestamps()
            ->using(GuruJabatanTambahan::class);
    }
}
