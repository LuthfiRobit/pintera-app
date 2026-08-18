<?php

namespace App\Domains\Akademik\Actions\Presensi;

use App\Domains\Akademik\Services\SesiPembelajaranGenerator;
use App\Models\Guru;
use App\Models\Kelas;
use Carbon\CarbonInterface;

final class GenerateSesiHarianAction
{
    public function __construct(
        private readonly SesiPembelajaranGenerator $generator,
    ) {
    }

    public function execute(Guru $guru, CarbonInterface $tanggal): void
    {
        $kelasList = Kelas::where(function ($query) use ($guru) {
            $query->whereHas('jadwalPelajaran', fn ($q) => $q->where('guru_id', $guru->id))
                ->orWhere('wali_kelas_guru_id', $guru->id);
        })->get();

        foreach ($kelasList as $kelas) {
            $semesterId = optional($kelas->tahunAjaran->semester()->where('status_aktif', true)->first())->id;
            if ($semesterId) {
                $this->generator->generateUntukTanggal($kelas, $tanggal, $semesterId);
            }
        }
    }
}
