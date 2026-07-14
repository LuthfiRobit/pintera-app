<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DokumenPendaftaran extends Model
{
    use LogsActivity;

    protected $table = 'dokumen_pendaftaran';

    protected $fillable = [
        'pendaftaran_id',
        'dokumen_syarat_ppdb_id',
        'file_path',
        'nama_file_asli',
        'mime_type',
        'ukuran_bytes',
        'status_verifikasi',
        'catatan_verifikasi',
        'diverifikasi_oleh_user_id',
        'diverifikasi_pada',
    ];

    protected $attributes = [
        'status_verifikasi' => 'belum_diverifikasi',
    ];

    protected function casts(): array
    {
        return [
            'diverifikasi_pada' => 'datetime',
        ];
    }

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(Pendaftaran::class);
    }

    public function dokumenSyaratPpdb(): BelongsTo
    {
        return $this->belongsTo(DokumenSyaratPpdb::class);
    }

    public function diverifikasiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh_user_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status_verifikasi', 'catatan_verifikasi', 'diverifikasi_oleh_user_id'])
            ->logOnlyDirty()
            ->useLogName('dokumen_pendaftaran');
    }
}
