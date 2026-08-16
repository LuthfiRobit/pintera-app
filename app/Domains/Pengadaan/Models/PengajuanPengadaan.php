<?php

namespace App\Domains\Pengadaan\Models;

use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Enums\TingkatUrgensi;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PengajuanPengadaan extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'pengajuan_pengadaan';

    protected $fillable = [
        'yayasan_id',
        'lembaga_id',
        'nomor_pengajuan',
        'judul_pengajuan',
        'latar_belakang',
        'tingkat_urgensi',
        'total_estimasi',
        'status',
        'nominal_pencairan',
        'tanggal_pencairan',
        'catatan_pencairan',
        'bukti_transfer_pencairan_path',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'tingkat_urgensi' => TingkatUrgensi::class,
            'status' => StatusPengajuan::class,
            'total_estimasi' => 'decimal:2',
            'nominal_pencairan' => 'decimal:2',
            'tanggal_pencairan' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nomor_pengajuan', 'judul_pengajuan', 'total_estimasi', 'status', 'nominal_pencairan'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function yayasan(): BelongsTo
    {
        return $this->belongsTo(Yayasan::class, 'yayasan_id');
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class, 'lembaga_id');
    }

    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PengajuanPengadaanItem::class, 'pengajuan_pengadaan_id');
    }

    public function approvalRequest(): MorphOne
    {
        return $this->morphOne(ApprovalRequest::class, 'approvable');
    }

    public function lpj(): HasOne
    {
        return $this->hasOne(LpjPengadaan::class, 'pengajuan_pengadaan_id');
    }

    public function canBeApprovedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (! in_array($this->status, [StatusPengajuan::Submitted, StatusPengajuan::InReview])) {
            return false;
        }

        $approvalReq = $this->approvalRequest;
        if (! $approvalReq || ! $approvalReq->currentStep) {
            return false;
        }

        return app(\App\Domains\Workflow\Services\ApproverResolverService::class)
            ->canUserApprove($approvalReq->currentStep, $user, $approvalReq);
    }
}
