<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions;

use App\Domains\Sdm\DataTransferObjects\ShiftAssignmentData;
use App\Domains\Sdm\Exceptions\ShiftAssignmentOverlapException;
use App\Domains\Sdm\Models\PenugasanShift;
use Illuminate\Database\Eloquent\Model;

final class AssignShiftAction
{
    public function execute(Model $pegawai, ShiftAssignmentData $data, ?int $excludingId = null): PenugasanShift
    {
        $tumpangTindih = PenugasanShift::where('pegawai_type', $pegawai::class)
            ->where('pegawai_id', $pegawai->id)
            ->when($excludingId, fn ($q) => $q->where('id', '!=', $excludingId))
            ->where('tanggal_mulai', '<=', $data->tanggalSelesai ?? '9999-12-31')
            ->where(function ($q) use ($data) {
                $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $data->tanggalMulai);
            })
            ->exists();

        if ($tumpangTindih) {
            throw new ShiftAssignmentOverlapException();
        }

        $payload = [
            'lembaga_id' => $data->lembagaId,
            'jenis_shift_id' => $data->jenisShiftId,
            'tanggal_mulai' => $data->tanggalMulai,
            'tanggal_selesai' => $data->tanggalSelesai,
            'hari_kerja' => $data->hariKerja,
        ];

        if ($excludingId) {
            $penugasan = PenugasanShift::findOrFail($excludingId);
            $penugasan->update($payload);

            return $penugasan->fresh();
        }

        return $pegawai->penugasanShift()->create($payload);
    }
}
