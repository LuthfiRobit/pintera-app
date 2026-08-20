<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Jadwal;

use App\Domains\Akademik\DataTransferObjects\JadwalPelajaranData;
use App\Models\JadwalPelajaran;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Semester;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DuplicateJadwalAction
{
    public function __construct(
        private readonly CreateJadwalPelajaranAction $createJadwalPelajaranAction
    ) {}

    /**
     * @return array{copied: int, skipped: int}
     * @throws ValidationException
     */
    public function execute(
        Kelas $sourceKelas,
        Semester $sourceSemester,
        Kelas $targetKelas,
        Semester $targetSemester
    ): array {
        if (! $targetKelas->pola_jam_id) {
            throw ValidationException::withMessages([
                'target_kelas_id' => 'Kelas tujuan belum memiliki ikatan Pola Jam.',
            ]);
        }

        $targetSlots = JamPelajaran::where('pola_jam_id', $targetKelas->pola_jam_id)
            ->get()
            ->keyBy(function ($slot) {
                $hariVal = $slot->hari instanceof \UnitEnum ? $slot->hari->value : $slot->hari;
                return $hariVal . '-' . $slot->urutan;
            });

        $sourceJadwals = JadwalPelajaran::with('jamPelajaran')
            ->where('kelas_id', $sourceKelas->id)
            ->where('semester_id', $sourceSemester->id)
            ->get();

        $copiedCount = 0;
        $skippedCount = 0;

        DB::transaction(function () use (
            $sourceJadwals,
            $targetSlots,
            $targetKelas,
            $targetSemester,
            &$copiedCount,
            &$skippedCount
        ) {
            foreach ($sourceJadwals as $source) {
                if (! $source->jamPelajaran) {
                    $skippedCount++;
                    continue;
                }

                $hariVal = $source->jamPelajaran->hari instanceof \UnitEnum
                    ? $source->jamPelajaran->hari->value
                    : $source->jamPelajaran->hari;
                $key = $hariVal . '-' . $source->jamPelajaran->urutan;

                $targetSlot = $targetSlots->get($key);
                if (! $targetSlot) {
                    $skippedCount++;
                    continue;
                }

                $dto = new JadwalPelajaranData(
                    lembagaId: $targetKelas->lembaga_id,
                    kelasId: $targetKelas->id,
                    mataPelajaranId: $source->mata_pelajaran_id,
                    guruId: $source->guru_id,
                    jamPelajaranId: $targetSlot->id,
                    semesterId: $targetSemester->id,
                    ruanganId: $source->ruangan_id ?? $targetKelas->ruangan_id
                );

                try {
                    $this->createJadwalPelajaranAction->execute($dto);
                    $copiedCount++;
                } catch (ValidationException) {
                    // Jika terjadi bentrok ruangan/guru pada slot ini, skip
                    $skippedCount++;
                }
            }
        });

        return [
            'copied' => $copiedCount,
            'skipped' => $skippedCount,
        ];
    }
}
