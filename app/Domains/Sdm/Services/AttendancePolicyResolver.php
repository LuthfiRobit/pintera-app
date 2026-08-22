<?php

namespace App\Domains\Sdm\Services;

use App\Domains\Sdm\Models\AttendancePolicy;
use App\Models\Guru;
use App\Models\Karyawan;
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

        $policyLembaga = AttendancePolicy::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $pegawai->lembaga_id)
            ->where($kolomKategori, $nilaiKategori)
            ->first();

        if ($policyLembaga) {
            return $policyLembaga;
        }

        return AttendancePolicy::withoutGlobalScope(TenantScope::class)
            ->whereNull('lembaga_id')
            ->where('yayasan_id', $pegawai->lembaga->yayasan_id)
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

        return $this->kalenderResolver->resolve($pegawai->lembaga, $tanggal);
    }
}
