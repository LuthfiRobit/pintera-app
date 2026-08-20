<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Actions\Submission;

use App\Domains\Kasus\DataTransferObjects\SubmitBuktiTugasData;
use App\Domains\Kasus\Models\KasusTugas;
use App\Domains\Kasus\Models\KasusTugasSubmission;

final class SubmitBuktiTugasAction
{
    public function execute(KasusTugas $kasusTugas, SubmitBuktiTugasData $data, bool $isSiswaTerkait, int $siswaId, ?int $orangTuaId): KasusTugasSubmission
    {
        return KasusTugasSubmission::create([
            'tugas_id' => $kasusTugas->id,
            'siswa_id' => $isSiswaTerkait ? $siswaId : null,
            'orang_tua_id' => $isSiswaTerkait ? null : $orangTuaId,
            'teks' => $data->teks,
            'lampiran' => $data->lampiranPath,
        ]);
    }
}
