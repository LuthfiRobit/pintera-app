<?php

namespace App\Domains\Sdm\Services;

use App\Domains\Sdm\Models\PenugasanShift;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class ShiftAwareAttendanceResolver
{
    public function __construct(private readonly AttendancePolicyResolver $policyResolver) {}

    /**
     * @return array{libur: bool, alasan: string}
     */
    public function resolveLibur(Model $pegawai, CarbonInterface $tanggal): array
    {
        $shift = $this->cariPenugasanAktif($pegawai, $tanggal);

        if (! $shift) {
            return $this->policyResolver->resolveLibur($pegawai, $tanggal);
        }

        $nama = $shift->jenisShift->nama;

        if ($shift->hari_kerja === null) {
            return ['libur' => false, 'alasan' => 'Hari kerja sesuai jadwal shift '.$nama];
        }

        $adalahHariKerja = in_array($tanggal->dayOfWeek, $shift->hari_kerja, true);

        return $adalahHariKerja
            ? ['libur' => false, 'alasan' => 'Hari kerja sesuai jadwal shift '.$nama]
            : ['libur' => true, 'alasan' => 'Libur sesuai jadwal shift '.$nama];
    }

    /**
     * @return array{jam_masuk: string, toleransi_menit: int}|null
     */
    public function resolveJamKerjaEfektif(Model $pegawai, CarbonInterface $tanggal): ?array
    {
        $shift = $this->cariPenugasanAktif($pegawai, $tanggal);

        if ($shift) {
            $toleransi = $this->policyResolver->resolvePolicy($pegawai)?->toleransi_menit ?? 0;

            return ['jam_masuk' => $shift->jenisShift->jam_masuk, 'toleransi_menit' => $toleransi];
        }

        $policy = $this->policyResolver->resolvePolicy($pegawai);

        if (! $policy) {
            return null;
        }

        return ['jam_masuk' => $policy->jam_masuk, 'toleransi_menit' => $policy->toleransi_menit];
    }

    private function cariPenugasanAktif(Model $pegawai, CarbonInterface $tanggal): ?PenugasanShift
    {
        $tgl = $tanggal->toDateString();

        return PenugasanShift::where('pegawai_type', $pegawai::class)
            ->where('pegawai_id', $pegawai->id)
            ->where('tanggal_mulai', '<=', $tgl)
            ->where(fn ($q) => $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $tgl))
            ->with('jenisShift')
            ->first();
    }
}
