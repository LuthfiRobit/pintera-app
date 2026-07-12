<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Lembaga extends Model
{
    use HasFactory;

    protected $table = 'lembaga';

    protected $fillable = [
        'yayasan_id', 'npsn', 'nss', 'nama', 'slug', 'bentuk_pendidikan', 'status_sekolah',
        'status_kepemilikan', 'naungan', 'sk_pendirian_nomor', 'sk_pendirian_tanggal',
        'sk_izin_operasional_nomor', 'sk_izin_operasional_tanggal', 'akreditasi',
        'sk_akreditasi_nomor', 'tanggal_sk_akreditasi', 'nama_kepala_sekolah', 'nama_bendahara_bosp',
        'alamat_jalan', 'rt', 'rw', 'nama_dusun', 'desa_kelurahan', 'kecamatan',
        'kabupaten_kota', 'provinsi', 'kode_pos', 'lintang', 'bujur',
        'telepon', 'fax', 'email', 'website',
        'nama_bank', 'cabang_kcp_unit', 'rekening_atas_nama', 'nomor_rekening',
        'mbs', 'nama_wajib_pajak', 'npwp', 'memungut_iuran', 'nominal_iuran', 'periode_iuran',
        'status_aktif',
    ];

    protected function casts(): array
    {
        return [
            'sk_pendirian_tanggal' => 'date',
            'sk_izin_operasional_tanggal' => 'date',
            'tanggal_sk_akreditasi' => 'date',
            'lintang' => 'decimal:7',
            'bujur' => 'decimal:7',
            'mbs' => 'boolean',
            'memungut_iuran' => 'boolean',
            'nominal_iuran' => 'decimal:2',
            'status_aktif' => 'boolean',
            'nomor_rekening' => 'encrypted',
            'npwp' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Lembaga $lembaga) {
            if (empty($lembaga->slug)) {
                $lembaga->slug = Str::slug($lembaga->nama);
            }
        });
    }

    public function yayasan(): BelongsTo
    {
        return $this->belongsTo(Yayasan::class);
    }

    public function layananKhusus(): HasMany
    {
        return $this->hasMany(LayananKhususLembaga::class);
    }

    public function programInklusi(): HasMany
    {
        return $this->hasMany(ProgramInklusiLembaga::class);
    }

    public function ekstrakurikuler(): HasMany
    {
        return $this->hasMany(EkstrakurikulerLembaga::class);
    }
}
