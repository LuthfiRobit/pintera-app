<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Actions\Submission;

use App\Domains\Kasus\DataTransferObjects\ReviewSubmissionData;
use App\Domains\Kasus\Models\KasusTugas;
use App\Domains\Kasus\Models\KasusTugasSubmission;
use App\Models\Scopes\TenantScope;
use App\Notifications\SubmissionRevisiNotification;

final class ReviewSubmissionAction
{
    public function execute(KasusTugas $kasusTugas, KasusTugasSubmission $kasusTugasSubmission, ReviewSubmissionData $data): KasusTugasSubmission
    {
        $kasusTugasSubmission->update([
            'status_review' => $data->statusReview,
            'catatan_revisi' => $data->catatanRevisi,
        ]);

        if ($data->statusReview === 'revisi_diminta') {
            $kasusTugas->update(['status' => 'revisi']);

            $notifiable = $kasusTugasSubmission->siswa_id !== null
                ? $kasusTugasSubmission->siswa()->withoutGlobalScope(TenantScope::class)->first()
                    ?->user()->withoutGlobalScope(TenantScope::class)->first()
                : $kasusTugasSubmission->orangTua;
            $notifiable?->notify(new SubmissionRevisiNotification($kasusTugasSubmission));
        }

        return $kasusTugasSubmission->fresh();
    }
}
