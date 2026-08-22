<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions;

use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Domains\Sdm\Models\PengajuanIzinCuti;
use App\Domains\Sdm\Services\AttendanceRecordAggregator;
use App\Domains\Workflow\Actions\ProcessApprovalAction;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProsesApprovalIzinCutiAction
{
    public function __construct(
        private readonly ProcessApprovalAction $workflowAction,
        private readonly AttendanceRecordAggregator $aggregator,
    ) {}

    public function execute(PengajuanIzinCuti $pengajuan, User $user, ApprovalAction $action, ?string $notes = null): void
    {
        $approvalRequest = $pengajuan->approvalRequest;

        if (! $approvalRequest) {
            throw ValidationException::withMessages([
                'approval' => 'Pengajuan ini tidak memiliki alur persetujuan aktif.',
            ]);
        }

        DB::transaction(function () use ($pengajuan, $approvalRequest, $user, $action, $notes) {
            $this->workflowAction->execute($approvalRequest, $user, $action, $notes);
            $approvalRequest->refresh();

            if ($approvalRequest->status === ApprovalStatus::Approved) {
                $pegawai = $pengajuan->pegawai;
                $status = $pengajuan->kategori->toAttendanceStatus();

                $tanggal = $pengajuan->tanggal_mulai->toImmutable();
                while ($tanggal->lessThanOrEqualTo($pengajuan->tanggal_selesai)) {
                    $pegawai->attendanceEvents()->create([
                        'lembaga_id' => $pengajuan->lembaga_id,
                        'method' => AttendanceMethod::System,
                        'arah' => 'masuk',
                        'status' => $status,
                        'waktu' => $tanggal->setTime(23, 59),
                        'dicatat_oleh_user_id' => null,
                        'catatan' => 'Disetujui via pengajuan izin/cuti #'.$pengajuan->id,
                    ]);

                    $this->aggregator->sync($pegawai, $tanggal);
                    $tanggal = $tanggal->addDay();
                }
            }
        });
    }
}
