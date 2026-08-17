<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Models;

use App\Domains\Akademik\Enums\StatusRpp;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Rpp extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'rpp';

    protected $fillable = [
        'yayasan_id',
        'lembaga_id',
        'guru_id',
        'tahun_ajaran_id',
        'semester_id',
        'kelas_id',
        'mata_pelajaran_id',
        'judul_topik',
        'alokasi_waktu',
        'pertemuan_ke',
        'file_path',
        'file_name',
        'file_size_bytes',
        'mime_type',
        'status',
        'catatan_revisi',
        'verified_by_user_id',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusRpp::class,
            'file_size_bytes' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    public function yayasan(): BelongsTo
    {
        return $this->belongsTo(Yayasan::class);
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function isDraft(): bool
    {
        return $this->status === StatusRpp::Draft;
    }

    public function isDiajukan(): bool
    {
        return $this->status === StatusRpp::Diajukan;
    }

    public function isDisetujui(): bool
    {
        return $this->status === StatusRpp::Disetujui;
    }

    public function isPerluRevisi(): bool
    {
        return $this->status === StatusRpp::PerluRevisi;
    }

    public function canBeEditedByGuru(): bool
    {
        return in_array($this->status, [StatusRpp::Draft, StatusRpp::PerluRevisi], true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['judul_topik', 'status', 'catatan_revisi', 'verified_by_user_id', 'verified_at'])
            ->logOnlyDirty()
            ->useLogName('akademik_rpp');
    }
}
