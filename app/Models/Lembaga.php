<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Lembaga extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'lembaga';

    protected $fillable = [
        'yayasan_id', 'npsn', 'nss', 'nama', 'slug', 'kode_lembaga', 'bentuk_pendidikan', 'status_sekolah',
        'status_kepemilikan', 'naungan', 'sk_pendirian_nomor', 'sk_pendirian_tanggal',
        'sk_izin_operasional_nomor', 'sk_izin_operasional_tanggal', 'akreditasi',
        'sk_akreditasi_nomor', 'tanggal_sk_akreditasi', 'nama_kepala_sekolah', 'nama_bendahara_bosp',
        'alamat_jalan', 'rt', 'rw', 'nama_dusun', 'desa_kelurahan', 'kecamatan',
        'kabupaten_kota', 'provinsi', 'kode_pos', 'lintang', 'bujur',
        'telepon', 'fax', 'email', 'website',
        'nama_bank', 'cabang_kcp_unit', 'rekening_atas_nama', 'nomor_rekening',
        'mbs', 'nama_wajib_pajak', 'npwp',
        'status_aktif', 'hari_libur_mingguan',
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
            'status_aktif' => 'boolean',
            'nomor_rekening' => 'encrypted',
            'npwp' => 'encrypted',
            'hari_libur_mingguan' => 'array',
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

    public function dataPeriodik(): HasMany
    {
        return $this->hasMany(LembagaDataPeriodik::class);
    }

    public function gedung(): HasMany
    {
        return $this->hasMany(\App\Domains\Sarpras\Models\Gedung::class);
    }

    public function ruangan(): HasMany
    {
        return $this->hasMany(\App\Domains\Sarpras\Models\Ruangan::class);
    }

    public function kategoriAset(): HasMany
    {
        return $this->hasMany(\App\Domains\Sarpras\Models\KategoriAset::class);
    }

    public function asetBarang(): HasMany
    {
        return $this->hasMany(\App\Domains\Sarpras\Models\AsetBarang::class);
    }

    public function pengajuanPengadaan(): HasMany
    {
        return $this->hasMany(\App\Domains\Pengadaan\Models\PengajuanPengadaan::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'nama', 'npsn', 'nss', 'bentuk_pendidikan', 'status_sekolah', 'status_kepemilikan',
                'naungan', 'akreditasi', 'status_aktif', 'nama_kepala_sekolah', 'nama_bendahara_bosp',
            ])
            ->logOnlyDirty()
            ->useLogName('lembaga');
    }
}
