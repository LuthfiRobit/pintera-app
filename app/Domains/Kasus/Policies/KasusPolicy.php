<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Policies;

use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusTugasSubmission;
use App\Models\Siswa;
use App\Models\User;

use App\Models\Scopes\TenantScope;

final class KasusPolicy
{
    /**
     * Dipindah persis dari trait AssertsKonselorPemegangKasus + duplikatnya di
     * KasusSesiController, KasusTugasSubmissionController, dan inline di
     * KasusEvaluasiController::store() — 4 salinan identik disatukan di sini.
     */
    public function isKonselor(User $user, Kasus $kasus): bool
    {
        $karyawanId = $user->karyawan()->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)->first()?->id;

        return ($kasus->konselor_guru_id !== null && $kasus->konselor_guru_id === $user->guru?->id)
            || ($kasus->konselor_karyawan_id !== null && $kasus->konselor_karyawan_id === $karyawanId);
    }

    /**
     * Dipindah persis dari KasusController::show() baris 162-173 (versi sebelum migrasi).
     * $siswa WAJIB objek yang sudah di-resolve TANPA TenantScope oleh pemanggil
     * (sama seperti kode asli — Kasus->siswa lazy-load akan gagal untuk orang tua
     * tanpa lembaga_id kalau tidak sudah di-cache lewat setRelation()).
     */
    public function view(User $user, Kasus $kasus, Siswa $siswa): bool
    {
        $isSubmitter = ($kasus->diajukan_oleh_guru_id !== null && $kasus->diajukan_oleh_guru_id === $user->guru?->id)
            || ($kasus->diajukan_oleh_orang_tua_id !== null && $kasus->diajukan_oleh_orang_tua_id === $user->orangTua?->id);
        $isKontakUtama = $user->orangTua !== null
            && $siswa->orangTua()->where('orang_tua_id', $user->orangTua->id)->wherePivot('is_kontak_utama', true)->exists();
        $isTriaseAdmin = $user->can('kasus.triase')
            && ($user->widestScopeLevel() === 'yayasan' || $kasus->lembaga_id === $user->lembaga_id);
        $isSiswaTerkait = $user->siswa !== null && $user->siswa->id === $kasus->siswa_id;

        return $isSubmitter || $isKontakUtama || $isTriaseAdmin || $this->isKonselor($user, $kasus) || $isSiswaTerkait;
    }

    /**
     * Dipindah persis dari KasusTugasSubmissionController::download() baris 105-115.
     * $siswa WAJIB objek yang sudah di-resolve TANPA TenantScope oleh pemanggil.
     */
    public function downloadLampiran(User $user, Kasus $kasus, KasusTugasSubmission $submission, Siswa $siswa): bool
    {
        $isSubmitter = ($submission->siswa_id !== null && $submission->siswa_id === $user->siswa?->id)
            || ($submission->orang_tua_id !== null && $submission->orang_tua_id === $user->orangTua?->id);
        $isKontakUtama = $user->orangTua !== null
            && $siswa->orangTua()->where('orang_tua_id', $user->orangTua->id)->wherePivot('is_kontak_utama', true)->exists();
        $isTriaseAdmin = $user->can('kasus.triase')
            && ($user->widestScopeLevel() === 'yayasan' || $kasus->lembaga_id === $user->lembaga_id);

        return $isSubmitter || $this->isKonselor($user, $kasus) || $isKontakUtama || $isTriaseAdmin;
    }

    /**
     * Alias isKonselor untuk konteks Sesi/Tugas/Submission-review — mengganti trait
     * AssertsKonselorPemegangKasus dan 2 method privat duplikatnya. Nama method beda
     * dari isKonselor supaya panggilan $this->authorize('kelolaSesiTugas', $kasus)
     * di controller jelas menyatakan INTENT (bukan sekadar cek identitas).
     */
    public function kelolaSesiTugas(User $user, Kasus $kasus): bool
    {
        return $this->isKonselor($user, $kasus);
    }

    /**
     * Gate collection-level untuk kasus.index. True kalau user punya capability
     * kasus.view (lewat role apa pun — guru/siswa/orang_tua baseline, atau
     * guru_bk/wakasek_kesiswaan/operator_akademik assignment eksplisit) ATAU
     * minimal satu Kasus punya konselor_karyawan_id = karyawan user ini —
     * fakta domain, bukan role/permission. withoutGlobalScope dipakai sengaja
     * karena karyawan pool level-yayasan (lembaga_id null) valid menangani
     * kasus lintas lembaga dalam yayasannya; validitas hubungan tetap dijaga
     * oleh konselor_karyawan_id itu sendiri, bukan TenantScope. Tidak ada
     * filter status — assignment konselor bersifat historis mengikuti
     * perilaku ListKasusUntukUserAction yang sudah ada.
     */
    public function viewAny(User $user): bool
    {
        if ($user->can('kasus.view')) {
            return true;
        }

        $karyawanId = $user->karyawan()->withoutGlobalScope(TenantScope::class)->first()?->id;

        return $karyawanId !== null && Kasus::withoutGlobalScope(TenantScope::class)
            ->where('konselor_karyawan_id', $karyawanId)
            ->exists();
    }
}
