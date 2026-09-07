<?php

namespace App\Domains\Sdm\Services;

use App\Domains\Sdm\Enums\TipeKalenderKerjaSdm;
use App\Domains\Sdm\Models\AttendancePolicy;
use App\Domains\Sdm\Models\KalenderKerjaSdm;
use App\Models\Guru;
use App\Models\Scopes\TenantScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class AttendancePolicyResolver
{
    public function __construct(private readonly KalenderKerjaSdmResolver $kalenderResolver) {}

    public function resolvePolicy(Model $pegawai): ?AttendancePolicy
    {
        $kolomKategori = $pegawai instanceof Guru ? 'jenis_ptk' : 'jenis_karyawan_id';
        $nilaiKategori = $pegawai instanceof Guru ? $pegawai->jenis_ptk : $pegawai->jenis_karyawan_id;
        $yayasanId = $pegawai->lembaga_id !== null ? $pegawai->lembaga->yayasan_id : $pegawai->yayasan_id;

        if ($pegawai->lembaga_id !== null) {
            $policyLembaga = AttendancePolicy::withoutGlobalScope(TenantScope::class)
                ->where('lembaga_id', $pegawai->lembaga_id)
                ->where($kolomKategori, $nilaiKategori)
                ->first();

            if ($policyLembaga) {
                return $policyLembaga;
            }
        }

        return AttendancePolicy::withoutGlobalScope(TenantScope::class)
            ->whereNull('lembaga_id')
            ->where('yayasan_id', $yayasanId)
            ->where($kolomKategori, $nilaiKategori)
            ->first();
    }

    /**
     * @return array{libur: bool, alasan: string}
     */
    public function resolveLibur(Model $pegawai, CarbonInterface $tanggal): array
    {
        $policy = $this->resolvePolicy($pegawai);

        if ($policy && $policy->hari_kerja !== null) {
            $adalahHariKerja = in_array($tanggal->dayOfWeek, $policy->hari_kerja, true);

            return $adalahHariKerja
                ? ['libur' => false, 'alasan' => 'Hari kerja sesuai kebijakan peran']
                : ['libur' => true, 'alasan' => 'Hari libur sesuai kebijakan peran'];
        }

        if ($pegawai->lembaga_id === null) {
            return $this->resolveLiburPool($pegawai->yayasan_id, $tanggal);
        }

        return $this->kalenderResolver->resolve($pegawai->lembaga, $tanggal);
    }

    private function resolveLiburPool(int $yayasanId, CarbonInterface $tanggal): array
    {
        $entriNasional = KalenderKerjaSdm::withoutGlobalScope(TenantScope::class)
            ->whereNull('lembaga_id')
            ->where('yayasan_id', $yayasanId)
            ->where(function ($q) use ($tanggal) {
                $tgl = $tanggal->toDateString();
                $q->whereDate('tanggal', '<=', $tgl)
                    ->where(fn ($q2) => $q2->whereDate('tanggal_selesai', '>=', $tgl)
                        ->orWhere(fn ($q3) => $q3->whereNull('tanggal_selesai')->whereDate('tanggal', '>=', $tgl))
                    );
            })
            ->first();

        if ($entriNasional) {
            return [
                'libur' => $entriNasional->tipe === TipeKalenderKerjaSdm::Libur,
                'alasan' => $entriNasional->nama,
            ];
        }

        return ['libur' => false, 'alasan' => 'Hari kerja efektif (karyawan pool, default hari kerja)'];
    }
}
