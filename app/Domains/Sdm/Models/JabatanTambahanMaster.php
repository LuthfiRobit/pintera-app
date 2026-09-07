<?php

namespace App\Domains\Sdm\Models;

use App\Models\Guru;
use App\Models\GuruJabatanTambahan;
use App\Models\Scopes\YayasanScope;
use Database\Factories\JabatanTambahanMasterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class JabatanTambahanMaster extends Model
{
    use HasFactory;

    protected static function newFactory(): JabatanTambahanMasterFactory
    {
        return JabatanTambahanMasterFactory::new();
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new YayasanScope);
    }

    protected $table = 'jabatan_tambahan_master';

    protected $fillable = ['yayasan_id', 'nama', 'kelompok'];

    public function guru(): BelongsToMany
    {
        return $this->belongsToMany(Guru::class, 'guru_jabatan_tambahan')
            ->withPivot(['mulai_periode', 'akhir_periode', 'no_sk'])
            ->withTimestamps()
            ->using(GuruJabatanTambahan::class);
    }
}
