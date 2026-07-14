<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function ditetapkanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditetapkan_oleh_user_id');
    }

    public function skPpdb(): BelongsTo
    {
        return $this->belongsTo(SkPpdb::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'catatan_keputusan', 'ditetapkan_oleh_user_id', 'sk_ppdb_id'])
            ->logOnlyDirty()
            ->useLogName('pendaftaran');
    }
}
