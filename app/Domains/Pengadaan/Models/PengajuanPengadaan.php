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

    public function timelineEvents(): \Illuminate\Support\Collection
    {
        $events = collect();

        // 1. Initial Submission Event
        $events->push([
            'type' => 'submission',
            'title' => 'Usulan Pengadaan Diajukan',
            'actor_name' => $this->pengaju?->name ?? 'Admin Lembaga',
            'actor_role' => 'Pengaju Unit Sekolah',
            'timestamp' => $this->created_at,
            'badge_tone' => 'blue',
            'status_label' => 'Diajukan',
            'icon' => 'send',
            'notes' => null,
            'description' => "Usulan pengadaan [{$this->nomor_pengajuan}] didaftarkan dengan total estimasi anggaran Rp " . number_format((float) $this->total_estimasi, 0, ',', '.') . '.',
        ]);

        // 2. Workflow Approval Logs
        if ($this->approvalRequest && $this->approvalRequest->logs) {
            foreach ($this->approvalRequest->logs as $log) {
                $tone = match ($log->action) {
                    \App\Domains\Workflow\Enums\ApprovalAction::Approve => 'green',
                    \App\Domains\Workflow\Enums\ApprovalAction::Reject => 'rose',
                    \App\Domains\Workflow\Enums\ApprovalAction::RequestRevision => 'amber',
                };
                $icon = match ($log->action) {
                    \App\Domains\Workflow\Enums\ApprovalAction::Approve => 'check_circle',
                    \App\Domains\Workflow\Enums\ApprovalAction::Reject => 'cancel',
                    \App\Domains\Workflow\Enums\ApprovalAction::RequestRevision => 'assignment_late',
                };

                $events->push([
                    'type' => 'approval',
                    'title' => 'Persetujuan: ' . ($log->step?->step_name ?? 'Verifikasi Workflow'),
                    'actor_name' => $log->user?->name ?? 'Reviewer',
                    'actor_role' => ucwords(str_replace('_', ' ', $log->step?->approver_value ?? 'Approver')),
                    'timestamp' => $log->created_at,
                    'badge_tone' => $tone,
                    'status_label' => $log->action->label(),
                    'icon' => $icon,
                    'notes' => $log->notes,
                    'description' => $log->notes ? "\"{$log->notes}\"" : 'Pemeriksaan langkah workflow telah diselesaikan.',
                ]);
            }
        }

        // 3. Disbursement Event
        if ($this->nominal_pencairan && $this->tanggal_pencairan) {
            $events->push([
                'type' => 'disbursement',
                'title' => 'Pencairan Kas Pengadaan',
                'actor_name' => 'Bendahara Yayasan',
                'actor_role' => 'Kasir / Pengelola Keuangan',
                'timestamp' => $this->tanggal_pencairan->startOfDay(),
                'badge_tone' => 'indigo',
                'status_label' => 'Dana Cair',
                'icon' => 'payments',
                'notes' => $this->catatan_pencairan,
                'description' => 'Dana kas sebesar Rp ' . number_format((float) $this->nominal_pencairan, 0, ',', '.') . ' telah dicairkan untuk pelaksanaan belanja barang.',
            ]);
        }

        // 4. LPJ Submission Event
        if ($this->lpj) {
            $events->push([
                'type' => 'lpj_submission',
                'title' => 'Penyerahan Laporan Pertanggungjawaban (LPJ)',
                'actor_name' => $this->pengaju?->name ?? 'Admin Sarpras',
                'actor_role' => 'Staf Operasional Sekolah',
                'timestamp' => $this->lpj->created_at,
                'badge_tone' => 'amber',
                'status_label' => 'LPJ Diserahkan',
                'icon' => 'receipt_long',
                'notes' => null,
                'description' => 'Berkas LPJ & nota fisik telah diunggah dengan total belanja riil Rp ' . number_format((float) $this->lpj->total_belanja_riil, 0, ',', '.') . ' (Sisa kas: Rp ' . number_format((float) $this->lpj->sisa_dana, 0, ',', '.') . ').',
            ]);

            // 5. LPJ Audit Verification Event
            if ($this->lpj->verified_at) {
                $isVerified = $this->lpj->status_lpj === \App\Domains\Pengadaan\Enums\StatusLpj::Verified;
                $events->push([
                    'type' => 'lpj_verification',
                    'title' => 'Audit & Verifikasi LPJ Belanja',
                    'actor_name' => $this->lpj->verifiedBy?->name ?? 'Auditor Yayasan',
                    'actor_role' => 'Pemeriksa LPJ Yayasan',
                    'timestamp' => $this->lpj->verified_at,
                    'badge_tone' => $isVerified ? 'green' : 'amber',
                    'status_label' => $this->lpj->status_lpj->label(),
                    'icon' => $isVerified ? 'verified' : 'assignment_late',
                    'notes' => $this->lpj->catatan_verifikasi,
                    'description' => $this->lpj->catatan_verifikasi ? "\"{$this->lpj->catatan_verifikasi}\"" : ($isVerified ? 'LPJ telah diverifikasi dan disetujui oleh Yayasan.' : 'LPJ memerlukan perbaikan nota.'),
                ]);
            }
        }

        // 6. Inventory Conversion Event
        if ($this->status === StatusPengajuan::Completed) {
            $events->push([
                'type' => 'inventory_conversion',
                'title' => 'Konversi ke Master Aset Sarpras',
                'actor_name' => 'Sistem Sarpras Terintegrasi',
                'actor_role' => 'Auto-Register Inventaris',
                'timestamp' => $this->updated_at,
                'badge_tone' => 'emerald',
                'status_label' => 'Aset Terdaftar',
                'icon' => 'inventory_2',
                'notes' => null,
                'description' => 'Seluruh item belanja berhasil diterbitkan kode register inventaris dan dicatat ke Kartu Inventaris Ruangan (KIR).',
            ]);
        }

        return $events->sortBy('timestamp')->values();
    }
}
