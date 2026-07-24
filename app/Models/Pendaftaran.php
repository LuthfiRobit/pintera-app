<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Pendaftaran extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'pendaftaran';

    protected $attributes = [
        'status' => 'menunggu_verifikasi',
    ];

    protected $fillable = [
        'calon_murid_id',
        'lembaga_id',
        'tahun_ajaran_id',
        'jalur_ppdb_id',
        'gelombang_ppdb_id',
        'kode_pendaftaran',
        'email_pendaftaran',
        'status',
        'submitted_at',
        'catatan_keputusan',
        'ditetapkan_oleh_user_id',
        'ditetapkan_pada',
        'sk_ppdb_id',
        'akun_pendaftar_id',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'ditetapkan_pada' => 'datetime',
        ];
    }

    public function calonMurid(): BelongsTo
    {
        return $this->belongsTo(CalonMurid::class);
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function jalurPpdb(): BelongsTo
    {
        return $this->belongsTo(JalurPpdb::class);
    }

    public function gelombangPpdb(): BelongsTo
    {
        return $this->belongsTo(GelombangPpdb::class);
    }

    public function jawabanFormulir(): HasMany
    {
        return $this->hasMany(JawabanFormulirPendaftaran::class);
    }

    public function dokumen(): HasMany
    {
        return $this->hasMany(DokumenPendaftaran::class);
    }

    public function hasilSeleksi(): HasMany
    {
        return $this->hasMany(HasilSeleksi::class);
    }

    public function tagihan(): HasMany
    {
        return $this->hasMany(Tagihan::class);
    }

    public function siswa(): HasOne
    {
        return $this->hasOne(Siswa::class, 'pendaftaran_asal_id');
    }

    public function scopeSiapDidaftarkanSebagaiSiswa(Builder $query): Builder
    {
        $idAktif = $query->where('status', 'diterima')
            ->whereDoesntHave('siswa')
            ->get()
            ->filter(fn (Pendaftaran $pendaftaran) => $pendaftaran->isAktif)
            ->pluck('id');

        return Pendaftaran::whereIn('id', $idAktif);
    }

    public function ditetapkanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditetapkan_oleh_user_id');
    }

    public function skPpdb(): BelongsTo
    {
        return $this->belongsTo(SkPpdb::class);
    }

    public function akunPendaftar(): BelongsTo
    {
        return $this->belongsTo(AkunPendaftar::class);
    }

    protected function isAktif(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === 'diterima' && (
                $this->tagihan()->where('kategori', 'daftar_ulang')->where('status', 'lunas')->exists()
                || $this->tagihan()->where('kategori', 'daftar_ulang')
                    ->whereHas('cicilan', fn ($q) => $q->where('urutan', 1)->where('status', 'lunas'))
                    ->exists()
            )
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'catatan_keputusan', 'ditetapkan_oleh_user_id', 'sk_ppdb_id'])
            ->logOnlyDirty()
            ->useLogName('pendaftaran');
    }
}
