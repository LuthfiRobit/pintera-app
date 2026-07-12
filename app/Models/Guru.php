<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guru extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'guru';

    protected $fillable = [
        'user_id', 'lembaga_id', 'nik', 'nuptk', 'nip', 'nama', 'jenis_kelamin',
        'tempat_lahir', 'tanggal_lahir', 'agama', 'kewarganegaraan',
        'alamat_jalan', 'rt', 'rw', 'desa_kelurahan', 'kecamatan', 'kabupaten_kota',
        'provinsi', 'kode_pos', 'no_hp', 'email',
        'jenis_ptk', 'status_kepegawaian', 'golongan_pangkat', 'tmt_tugas', 'tmt_pns', 'status_aktif',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'tmt_tugas' => 'date',
            'tmt_pns' => 'date',
            'nik' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Guru $guru) {
            $guru->nik_hash = hash('sha256', $guru->nik);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function riwayatPendidikan(): HasMany
    {
        return $this->hasMany(RiwayatPendidikanGuru::class);
    }

    public function sertifikasi(): HasMany
    {
        return $this->hasMany(SertifikasiGuru::class);
    }

    public function jabatanTambahan(): BelongsToMany
    {
        return $this->belongsToMany(JabatanTambahanMaster::class, 'guru_jabatan_tambahan')
            ->withPivot(['mulai_periode', 'akhir_periode', 'no_sk'])
            ->withTimestamps()
            ->using(GuruJabatanTambahan::class);
    }
}
