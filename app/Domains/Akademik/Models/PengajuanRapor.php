<?php

namespace App\Domains\Akademik\Models;

use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\User;
use Database\Factories\PengajuanRaporFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class PengajuanRapor extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'pengajuan_rapor';

    protected $fillable = [
        'lembaga_id', 'kelas_id', 'semester_id', 'status',
        'diajukan_oleh', 'diajukan_pada',
        'diverifikasi_oleh', 'diverifikasi_pada',
        'disetujui_oleh', 'disetujui_pada',
        'catatan_revisi', 'tanggal_rapor',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusPengajuanRapor::class,
            'diajukan_pada' => 'datetime',
            'diverifikasi_pada' => 'datetime',
            'disetujui_pada' => 'datetime',
            'tanggal_rapor' => 'date',
        ];
    }

    protected static function newFactory(): PengajuanRaporFactory
    {
        return PengajuanRaporFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (self $pengajuanRapor) {
            if (empty($pengajuanRapor->lembaga_id)) {
                $pengajuanRapor->lembaga_id = Kelas::withoutGlobalScopes()
                    ->findOrFail($pengajuanRapor->kelas_id)
                    ->lembaga_id;
            }
        });
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function diajukanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    public function diverifikasiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function disetujuiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    public function approvalRequest(): MorphOne
    {
        return $this->morphOne(ApprovalRequest::class, 'approvable');
    }
}
