<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Jadwal;

use App\Domains\Akademik\DataTransferObjects\JadwalPelajaranData;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Sarpras\Actions\ValidateRoomClashAction;
use App\Models\JadwalPelajaran;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateJadwalPelajaranAction
{
    public function __construct(
        private readonly ValidateRoomClashAction $validateRoomClashAction
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(JadwalPelajaran $jadwal, JadwalPelajaranData $data): JadwalPelajaran
    {
        // 1. Validasi Bentrok Ruangan Sarpras (mengabaikan self-record)
        if ($data->ruanganId !== null) {
            $isRoomClash = $this->validateRoomClashAction->execute(
                ruanganId: $data->ruanganId,
                semesterId: $data->semesterId,
                jamPelajaranId: $data->jamPelajaranId,
                ignoreJadwalId: $jadwal->id
            );

            if ($isRoomClash) {
                throw ValidationException::withMessages([
                    'ruangan_id' => 'Ruangan yang dipilih sudah digunakan oleh kelas lain pada jam pelajaran ini.',
                ]);
            }
        }

        // 2. Validasi Slot Jam Kelas (mengabaikan self-record)
        $isSlotTaken = JadwalPelajaran::query()
            ->where('kelas_id', $data->kelasId)
            ->where('semester_id', $data->semesterId)
            ->where('jam_pelajaran_id', $data->jamPelajaranId)
            ->where('id', '!=', $jadwal->id)
            ->exists();

        if ($isSlotTaken) {
            throw ValidationException::withMessages([
                'jam_pelajaran_id' => 'Kelas ini sudah punya jadwal pada slot ini di semester yang sama.',
            ]);
        }

        $jamPelajaranBaru = JamPelajaran::findOrFail($data->jamPelajaranId);

        // 3. Validasi Bentrok Guru Pengampu (berbasis waktu wall-clock, mengabaikan self-record)
        $isGuruClash = JadwalPelajaran::query()
            ->where('guru_id', $data->guruId)
            ->where('semester_id', $data->semesterId)
            ->where('id', '!=', $jadwal->id)
            ->whereHas('jamPelajaran', function ($q) use ($jamPelajaranBaru) {
                $q->where('hari', $jamPelajaranBaru->hari)
                    ->where('jam_mulai', '<', $jamPelajaranBaru->jam_selesai)
                    ->where('jam_selesai', '>', $jamPelajaranBaru->jam_mulai);
            })
            ->exists();

        if ($isGuruClash) {
            throw ValidationException::withMessages([
                'guru_id' => 'Guru ini sudah mengajar kelas lain pada jam dan semester yang sama.',
            ]);
        }

        return DB::transaction(function () use ($jadwal, $data) {
            $jadwal->update($data->toArray());

            return $jadwal->fresh();
        });
    }
}
