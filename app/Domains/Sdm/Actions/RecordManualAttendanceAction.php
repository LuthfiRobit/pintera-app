<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions;

use App\Domains\Sdm\DataTransferObjects\RecordManualAttendanceData;
use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Domains\Sdm\Models\AttendanceEvent;
use App\Domains\Sdm\Services\AttendanceRecordAggregator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class RecordManualAttendanceAction
{
    public function __construct(private readonly AttendanceRecordAggregator $aggregator) {}

    public function execute(Model $pegawai, RecordManualAttendanceData $data): AttendanceEvent
    {
        return DB::transaction(function () use ($pegawai, $data) {
            $event = $pegawai->attendanceEvents()->create([
                'lembaga_id' => $data->lembagaId,
                'attendance_point_id' => $data->attendancePointId,
                'method' => AttendanceMethod::Admin,
                'arah' => $data->arah,
                'status' => $data->status,
                'waktu' => $data->waktu,
                'dicatat_oleh_user_id' => $data->dicatatOlehUserId,
                'catatan' => $data->catatan,
            ]);

            $this->aggregator->sync($pegawai, $data->waktu->toImmutable());

            return $event;
        });
    }
}
