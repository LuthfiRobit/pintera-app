<?php

// database/seeders/PengajuanRaporSeeder.php

namespace Database\Seeders;

use App\Domains\Akademik\Actions\Rapor\ApprovePengajuanRaporAction;
use App\Domains\Akademik\Actions\Rapor\SubmitPengajuanRaporAction;
use App\Domains\Akademik\Actions\Rapor\VerifyPengajuanRaporAction;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;

class PengajuanRaporSeeder extends Seeder
{
    /**
     * Mengajukan rapor untuk setiap Kelas di tahun ajaran + semester aktif, lalu memajukan
     * sebagian ke tahap berikutnya lewat Action asli (bukan insert manual) supaya baris
     * ApprovalRequest/ApprovalLog di domain Workflow ikut konsisten -- 3 status berbeda
     * dicampur (Diajukan/Diverifikasi/Disetujui) supaya menu Persetujuan Rapor tidak kosong
     * dan mendemokan ketiga tahap sekaligus. WAJIB dijalankan SETELAH CatatanWaliKelasSeeder
     * dan WorkflowDefinitionSeeder.
     */
    public function run(): void
    {
        $submitAction = app(SubmitPengajuanRaporAction::class);
        $verifyAction = app(VerifyPengajuanRaporAction::class);
        $approveAction = app(ApprovePengajuanRaporAction::class);

        foreach (Lembaga::all() as $lembaga) {
            $tahunAjaranAktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();
            if (! $tahunAjaranAktif) {
                continue;
            }

            $semesterAktif = Semester::where('tahun_ajaran_id', $tahunAjaranAktif->id)->where('status_aktif', true)->first();
            if (! $semesterAktif) {
                continue;
            }

            $waka = User::where('lembaga_id', $lembaga->id)->whereHas('roles', fn ($q) => $q->where('name', 'wakasek_kurikulum'))->first();
            $kepsek = User::where('lembaga_id', $lembaga->id)->whereHas('roles', fn ($q) => $q->where('name', 'kepala_sekolah'))->first();

            $kelasList = Kelas::where('lembaga_id', $lembaga->id)
                ->where('tahun_ajaran_id', $tahunAjaranAktif->id)
                ->whereNotNull('wali_kelas_guru_id')
                ->whereHas('siswa')
                ->get();

            foreach ($kelasList as $idx => $kelas) {
                $waliKelasUser = $kelas->waliKelas?->user;
                if (! $waliKelasUser) {
                    continue;
                }

                try {
                    $pengajuan = $submitAction->execute($kelas, $semesterAktif, $waliKelasUser);
                } catch (ValidationException) {
                    // Kelas ini belum lengkap catatan wali kelasnya (mis. belum ada siswa) -- lewati.
                    continue;
                }

                $tahap = $idx % 3;

                if ($tahap >= 1 && $waka) {
                    $verifyAction->execute($pengajuan, $waka, ApprovalAction::Approve, 'Data lengkap, lanjut ke Kepala Sekolah.');
                }

                if ($tahap === 2 && $kepsek) {
                    $approveAction->execute($pengajuan->fresh(), $kepsek, ApprovalAction::Approve, 'Disetujui, rapor final.');
                }
            }
        }
    }
}
