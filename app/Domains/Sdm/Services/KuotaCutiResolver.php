<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Services;

use App\Domains\Sdm\Enums\KategoriPengajuanIzin;
use App\Domains\Sdm\Models\KuotaCutiConfig;
use App\Domains\Sdm\Models\PengajuanIzinCuti;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Models\Guru;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

class KuotaCutiResolver
{
    /**
     * Resolusi 4-tingkat: spesifik-jenis lembaga -> flat lembaga -> spesifik-jenis nasional -> flat nasional.
     * MVP saat ini hanya pernah membuat baris "flat" (tier 2/4, jenis_ptk & jenis_karyawan_id NULL) —
     * tier 1/3 (spesifik per jenis) sudah disiapkan resolusinya untuk pengembangan masa depan.
     */
    public function resolveConfig(Model $pegawai): ?KuotaCutiConfig
    {
        $kolomKategori = $pegawai instanceof Guru ? 'jenis_ptk' : 'jenis_karyawan_id';
        $nilaiKategori = $pegawai instanceof Guru ? $pegawai->jenis_ptk : $pegawai->jenis_karyawan_id;

        $spesifikLembaga = KuotaCutiConfig::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $pegawai->lembaga_id)
            ->where($kolomKategori, $nilaiKategori)
            ->first();
        if ($spesifikLembaga) {
            return $spesifikLembaga;
        }

        $flatLembaga = KuotaCutiConfig::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $pegawai->lembaga_id)
            ->whereNull('jenis_ptk')
            ->whereNull('jenis_karyawan_id')
            ->first();
        if ($flatLembaga) {
            return $flatLembaga;
        }

        $yayasanId = $pegawai->lembaga->yayasan_id;

        $spesifikNasional = KuotaCutiConfig::withoutGlobalScope(TenantScope::class)
            ->whereNull('lembaga_id')
            ->where('yayasan_id', $yayasanId)
            ->where($kolomKategori, $nilaiKategori)
            ->first();
        if ($spesifikNasional) {
            return $spesifikNasional;
        }

        return KuotaCutiConfig::withoutGlobalScope(TenantScope::class)
            ->whereNull('lembaga_id')
            ->where('yayasan_id', $yayasanId)
            ->whereNull('jenis_ptk')
            ->whereNull('jenis_karyawan_id')
            ->first();
    }

    public function jatahTahunan(Model $pegawai): int
    {
        return $this->resolveConfig($pegawai)?->jatah_hari_per_tahun ?? 0;
    }

    public function sisaKuota(Model $pegawai, int $tahun): int
    {
        $jatah = $this->jatahTahunan($pegawai);

        if ($jatah === 0) {
            return 0;
        }

        $terpakai = PengajuanIzinCuti::withoutGlobalScope(TenantScope::class)
            ->where('pegawai_type', get_class($pegawai))
            ->where('pegawai_id', $pegawai->id)
            ->where('kategori', KategoriPengajuanIzin::Cuti)
            ->whereYear('tanggal_mulai', $tahun)
            ->whereHas('approvalRequest', fn ($q) => $q->whereIn('status', [
                ApprovalStatus::Pending, ApprovalStatus::InReview, ApprovalStatus::Approved,
            ]))
            ->get()
            ->sum(fn (PengajuanIzinCuti $p) => $p->tanggal_mulai->diffInDays($p->tanggal_selesai) + 1);

        return (int) max(0, $jatah - $terpakai);
    }
}
