<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Rapor;

use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\CatatanWaliKelas;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Domains\Workflow\Actions\InitializeApprovalRequestAction;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SubmitPengajuanRaporAction
{
    public function __construct(
        private readonly InitializeApprovalRequestAction $initializeApprovalRequestAction,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function execute(Kelas $kelas, Semester $semester, User $user): PengajuanRapor
    {
        $siswaList = $kelas->siswa()->get();
        $siswaIdsWithCatatan = CatatanWaliKelas::where('semester_id', $semester->id)
            ->whereIn('siswa_id', $siswaList->pluck('id'))
            ->pluck('siswa_id');

        $siswaBelumLengkap = $siswaList->whereNotIn('id', $siswaIdsWithCatatan);

        if ($siswaBelumLengkap->isNotEmpty()) {
            $daftarNama = $siswaBelumLengkap->pluck('nama_lengkap')->implode(', ');
            throw ValidationException::withMessages([
                'catatan_wali_kelas' => "Siswa berikut belum memiliki catatan wali kelas: {$daftarNama}.",
            ]);
        }

        return DB::transaction(function () use ($kelas, $semester, $user) {
            $pengajuanRapor = PengajuanRapor::updateOrCreate(
                ['kelas_id' => $kelas->id, 'semester_id' => $semester->id],
                ['status' => StatusPengajuanRapor::Diajukan, 'diajukan_oleh' => $user->id, 'diajukan_pada' => now()]
            );

            $existingApprovalRequest = $pengajuanRapor->approvalRequest;

            if ($existingApprovalRequest) {
                $firstStep = $existingApprovalRequest->workflowDefinition?->firstStep();
                $existingApprovalRequest->current_step_id = $firstStep?->id;
                $existingApprovalRequest->status = ApprovalStatus::Pending;
                $existingApprovalRequest->last_notes = null;
                $existingApprovalRequest->save();
            } else {
                $this->initializeApprovalRequestAction->execute('RAPOR_SEMESTER', $pengajuanRapor, $user);
            }

            return $pengajuanRapor->fresh();
        });
    }
}
