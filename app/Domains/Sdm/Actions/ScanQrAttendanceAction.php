<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions;

use App\Domains\Sdm\DataTransferObjects\ScanQrAttendanceData;
use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Domains\Sdm\Enums\AttendanceStatus;
use App\Domains\Sdm\Exceptions\AttendanceOnHolidayException;
use App\Domains\Sdm\Exceptions\InvalidQrTokenException;
use App\Domains\Sdm\Exceptions\QrTokenLembagaMismatchException;
use App\Domains\Sdm\Models\AttendanceEvent;
use App\Domains\Sdm\Models\EmployeeQrCode;
use App\Domains\Sdm\Services\AttendancePolicyResolver;
use App\Domains\Sdm\Services\AttendanceRecordAggregator;
use Illuminate\Support\Facades\DB;

final class ScanQrAttendanceAction
{
    public function __construct(
        private readonly AttendanceRecordAggregator $aggregator,
        private readonly AttendancePolicyResolver $policyResolver,
    ) {}

    public function execute(ScanQrAttendanceData $data): AttendanceEvent
    {
        $qrCode = EmployeeQrCode::where('token', $data->token)->where('is_active', true)->first();

        if (! $qrCode) {
            throw new InvalidQrTokenException();
        }

        $pegawai = $qrCode->pegawai;

        if (! $pegawai || (int) $pegawai->lembaga_id !== $data->lembagaId) {
            throw new QrTokenLembagaMismatchException();
        }

        $resolusi = $this->policyResolver->resolveLibur($pegawai, now());

        if ($resolusi['libur']) {
            throw new AttendanceOnHolidayException($resolusi['alasan']);
        }

        return DB::transaction(function () use ($pegawai, $data) {
            $event = $pegawai->attendanceEvents()->create([
                'lembaga_id' => $data->lembagaId,
                'attendance_point_id' => $data->attendancePointId,
                'method' => AttendanceMethod::Qr,
                'arah' => $data->arah,
                'status' => AttendanceStatus::Hadir,
                'waktu' => now(),
                'dicatat_oleh_user_id' => $data->dicatatOlehUserId,
            ]);

            $this->aggregator->sync($pegawai, now()->toImmutable());

            return $event;
        });
    }
}
