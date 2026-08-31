<?php

namespace App\Domains\Identity\Models;

use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\OrangTua;
use App\Models\Scopes\YayasanScope;
use App\Models\Siswa;
use App\Models\User;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): PersonFactory
    {
        return PersonFactory::new();
    }

    protected $table = 'persons';

    protected $fillable = [
        'yayasan_id', 'user_id', 'nik', 'nama_lengkap', 'jenis_kelamin',
        'tempat_lahir', 'tanggal_lahir', 'agama', 'kewarganegaraan', 'no_hp',
        'email', 'alamat_jalan', 'rt', 'rw', 'desa_kelurahan', 'kecamatan',
        'kabupaten_kota', 'provinsi', 'kode_pos', 'merged_into_person_id',
        'deactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'nik' => 'encrypted',
            'tanggal_lahir' => 'date',
            'deactivated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new YayasanScope);

        static::saving(function (Person $person) {
            $person->nik_hash = $person->nik ? hash('sha256', $person->nik) : null;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    public function guru(): HasOne
    {
        return $this->hasOne(Guru::class);
    }

    public function karyawan(): HasOne
    {
        return $this->hasOne(Karyawan::class);
    }

    public function orangTua(): HasOne
    {
        return $this->hasOne(OrangTua::class);
    }

    public function siswa(): HasOne
    {
        return $this->hasOne(Siswa::class);
    }
}
