<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Services;

use App\Domains\Sdm\Enums\AttendanceStatus;
use App\Domains\Sdm\Models\AttendanceRecord;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecordAggregator
{
    public function __construct(private readonly ShiftAwareAttendanceResolver $resolver) {}

    public function sync(Model $pegawai, CarbonImmutable $tanggal): AttendanceRecord
    {
        $events = $pegawai->attendanceEvents()
            ->whereDate('waktu', $tanggal->toDateString())
            ->orderBy('waktu')
            ->get();

        $waktuMasuk = $events->firstWhere('arah', 'masuk')?->waktu;
        $waktuPulang = $events->where('arah', 'pulang')->last()?->waktu;

        $statusOverride = $events->first(fn ($event) => $event->status !== AttendanceStatus::Hadir);
        $status = $statusOverride?->status ?? AttendanceStatus::Hadir;

        [$isLate, $lateMinutes] = $this->hitungKeterlambatan($pegawai, $tanggal, $waktuMasuk);

        return AttendanceRecord::updateOrCreate(
            [
                'pegawai_type' => $pegawai::class,
                'pegawai_id' => $pegawai->id,
                'tanggal' => $tanggal->toDateString(),
            ],
            [
                'lembaga_id' => $pegawai->lembaga_id,
                'status' => $status,
                'waktu_masuk' => $waktuMasuk,
                'waktu_pulang' => $waktuPulang,
                'is_late' => $isLate,
                'late_minutes' => $lateMinutes,
            ]
        );
    }

    /**
     * @return array{0: bool, 1: int|null}
     */
    private function hitungKeterlambatan(Model $pegawai, CarbonInterface $tanggal, ?CarbonInterface $waktuMasuk): array
    {
        if (! $waktuMasuk) {
            return [false, null];
        }

        $jamKerja = $this->resolver->resolveJamKerjaEfektif($pegawai, $tanggal);

        if (! $jamKerja) {
            return [false, null];
        }

        $batasWaktu = CarbonImmutable::parse($tanggal->toDateString().' '.$jamKerja['jam_masuk'])->addMinutes($jamKerja['toleransi_menit']);

        if ($waktuMasuk->lessThanOrEqualTo($batasWaktu)) {
            return [false, 0];
        }

        return [true, $batasWaktu->diffInMinutes($waktuMasuk)];
    }
}
