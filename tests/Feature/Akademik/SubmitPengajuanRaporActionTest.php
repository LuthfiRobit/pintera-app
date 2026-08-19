<?php

use App\Domains\Akademik\Actions\Rapor\SimpanCatatanWaliKelasAction;
use App\Domains\Akademik\Actions\Rapor\SubmitPengajuanRaporAction;
use App\Domains\Akademik\DataTransferObjects\CatatanWaliKelasData;
use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\WorkflowDefinitionSeeder;

function siapkanKelasDenganSiswa(int $jumlahSiswa = 2): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswaList = Siswa::factory()->count($jumlahSiswa)->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    return compact('lembaga', 'semester', 'kelas', 'siswaList', 'user');
}

it('rejects submission when not every siswa in the kelas has a CatatanWaliKelas', function () {
    ['semester' => $semester, 'kelas' => $kelas, 'siswaList' => $siswaList, 'user' => $user] = siapkanKelasDenganSiswa(2);

    (new SimpanCatatanWaliKelasAction())->execute(CatatanWaliKelasData::fromArray([
        'siswa_id' => $siswaList[0]->id,
        'semester_id' => $semester->id,
    ]));
    // siswaList[1] sengaja tidak dikasih catatan

    expect(fn () => (new SubmitPengajuanRaporAction(app(\App\Domains\Workflow\Actions\InitializeApprovalRequestAction::class)))->execute($kelas, $semester, $user))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('creates a PengajuanRapor and initializes an ApprovalRequest when every siswa has a CatatanWaliKelas', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['semester' => $semester, 'kelas' => $kelas, 'siswaList' => $siswaList, 'user' => $user] = siapkanKelasDenganSiswa(2);

    foreach ($siswaList as $siswa) {
        (new SimpanCatatanWaliKelasAction())->execute(CatatanWaliKelasData::fromArray([
            'siswa_id' => $siswa->id,
            'semester_id' => $semester->id,
        ]));
    }

    $pengajuan = (new SubmitPengajuanRaporAction(app(\App\Domains\Workflow\Actions\InitializeApprovalRequestAction::class)))->execute($kelas, $semester, $user);

    expect($pengajuan->status)->toBe(StatusPengajuanRapor::Diajukan);
    expect($pengajuan->diajukan_oleh)->toBe($user->id);
    expect($pengajuan->approvalRequest)->not->toBeNull();
    expect($pengajuan->approvalRequest->status)->toBe(ApprovalStatus::Pending);
});

it('resets the same ApprovalRequest to its first step on resubmission after rejection, instead of creating a new one', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['semester' => $semester, 'kelas' => $kelas, 'siswaList' => $siswaList, 'user' => $user] = siapkanKelasDenganSiswa(1);
    (new SimpanCatatanWaliKelasAction())->execute(CatatanWaliKelasData::fromArray(['siswa_id' => $siswaList[0]->id, 'semester_id' => $semester->id]));

    $action = new SubmitPengajuanRaporAction(app(\App\Domains\Workflow\Actions\InitializeApprovalRequestAction::class));
    $pengajuan = $action->execute($kelas, $semester, $user);
    $approvalRequestIdPertama = $pengajuan->approvalRequest->id;

    // Simulasikan penolakan langsung di level engine (tanpa lewat VerifyPengajuanRaporAction, karena itu Task 5)
    $pengajuan->approvalRequest->update(['status' => ApprovalStatus::Rejected]);
    $pengajuan->update(['status' => StatusPengajuanRapor::Ditolak]);

    $pengajuanResubmit = $action->execute($kelas, $semester, $user);

    expect($pengajuanResubmit->id)->toBe($pengajuan->id);
    expect($pengajuanResubmit->approvalRequest->id)->toBe($approvalRequestIdPertama);
    expect($pengajuanResubmit->approvalRequest->status)->toBe(ApprovalStatus::Pending);
    expect($pengajuanResubmit->status)->toBe(StatusPengajuanRapor::Diajukan);
});
