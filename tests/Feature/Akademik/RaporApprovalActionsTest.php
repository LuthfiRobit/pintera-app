<?php

use App\Domains\Akademik\Actions\Penilaian\SimpanNilaiSiswaAction;
use App\Domains\Akademik\Actions\Rapor\ApprovePengajuanRaporAction;
use App\Domains\Akademik\Actions\Rapor\SimpanCatatanWaliKelasAction;
use App\Domains\Akademik\Actions\Rapor\SubmitPengajuanRaporAction;
use App\Domains\Akademik\Actions\Rapor\VerifyPengajuanRaporAction;
use App\Domains\Akademik\DataTransferObjects\CatatanWaliKelasData;
use App\Domains\Akademik\DataTransferObjects\NilaiSiswaBatchData;
use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Workflow\Actions\InitializeApprovalRequestAction;
use App\Domains\Workflow\Actions\ProcessApprovalAction;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\WorkflowDefinitionSeeder;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

function siapkanPengajuanDiajukan(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $roleWaka = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web']);
    $roleKepsek = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web']);
    $userWaka = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userWaka->assignRole($roleWaka);
    $userKepsek = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userKepsek->assignRole($roleKepsek);
    $userWali = User::factory()->create(['lembaga_id' => $lembaga->id]);

    (new SimpanCatatanWaliKelasAction())->execute(CatatanWaliKelasData::fromArray(['siswa_id' => $siswa->id, 'semester_id' => $semester->id]));

    $submitAction = new SubmitPengajuanRaporAction(app(InitializeApprovalRequestAction::class));
    $pengajuan = $submitAction->execute($kelas, $semester, $userWali);

    return compact('lembaga', 'semester', 'kelas', 'siswa', 'mapel', 'pengajuan', 'userWaka', 'userKepsek', 'userWali');
}

it('completes the full happy path: submit -> verify -> approve, locking nilai afterward', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['semester' => $semester, 'kelas' => $kelas, 'mapel' => $mapel, 'pengajuan' => $pengajuan, 'userWaka' => $userWaka, 'userKepsek' => $userKepsek] = siapkanPengajuanDiajukan();

    $verifyAction = new VerifyPengajuanRaporAction(app(ProcessApprovalAction::class));
    $diverifikasi = $verifyAction->execute($pengajuan, $userWaka, ApprovalAction::Approve, 'Lengkap');
    expect($diverifikasi->status)->toBe(StatusPengajuanRapor::Diverifikasi);
    expect($diverifikasi->diverifikasi_oleh)->toBe($userWaka->id);

    $approveAction = new ApprovePengajuanRaporAction(app(ProcessApprovalAction::class));
    $disetujui = $approveAction->execute($diverifikasi, $userKepsek, ApprovalAction::Approve, 'Setuju');
    expect($disetujui->status)->toBe(StatusPengajuanRapor::Disetujui);
    expect($disetujui->disetujui_oleh)->toBe($userKepsek->id);

    $asesmen = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);

    expect(fn () => (new SimpanNilaiSiswaAction())->execute($asesmen, NilaiSiswaBatchData::fromArray(['nilai' => []])))
        ->toThrow(ValidationException::class);
});

it('does not lock nilai for a different kelas or semester', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['semester' => $semester, 'kelas' => $kelas, 'userWaka' => $userWaka, 'userKepsek' => $userKepsek, 'pengajuan' => $pengajuan, 'lembaga' => $lembaga] = siapkanPengajuanDiajukan();

    (new VerifyPengajuanRaporAction(app(ProcessApprovalAction::class)))->execute($pengajuan, $userWaka, ApprovalAction::Approve);
    (new ApprovePengajuanRaporAction(app(ProcessApprovalAction::class)))->execute($pengajuan->fresh(), $userKepsek, ApprovalAction::Approve);

    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLain->id]);
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaranLain->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $asesmenLain = Asesmen::factory()->create(['kelas_id' => $kelasLain->id, 'mata_pelajaran_id' => $mapel->id, 'semester_id' => $semesterLain->id]);

    (new SimpanNilaiSiswaAction())->execute($asesmenLain, NilaiSiswaBatchData::fromArray(['nilai' => []]));
    expect(true)->toBeTrue(); // tidak throw = lulus
});

it('rejects at the verify stage and records catatan_revisi, allowing resubmission afterward', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['semester' => $semester, 'kelas' => $kelas, 'pengajuan' => $pengajuan, 'userWaka' => $userWaka, 'userWali' => $userWali] = siapkanPengajuanDiajukan();

    $verifyAction = new VerifyPengajuanRaporAction(app(ProcessApprovalAction::class));
    $ditolak = $verifyAction->execute($pengajuan, $userWaka, ApprovalAction::Reject, 'Nilai belum lengkap');

    expect($ditolak->status)->toBe(StatusPengajuanRapor::Ditolak);
    expect($ditolak->catatan_revisi)->toBe('Nilai belum lengkap');

    $submitAction = new SubmitPengajuanRaporAction(app(InitializeApprovalRequestAction::class));
    $diajukanUlang = $submitAction->execute($kelas, $semester, $userWali);

    expect($diajukanUlang->status)->toBe(StatusPengajuanRapor::Diajukan);
    expect($diajukanUlang->id)->toBe($pengajuan->id);
});
