<?php

use App\Domains\Akademik\Actions\Rapor\SimpanCatatanWaliKelasAction;
use App\Domains\Akademik\Actions\Rapor\SubmitPengajuanRaporAction;
use App\Domains\Akademik\Actions\Rapor\VerifyPengajuanRaporAction;
use App\Domains\Akademik\DataTransferObjects\CatatanWaliKelasData;
use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Workflow\Actions\InitializeApprovalRequestAction;
use App\Domains\Workflow\Actions\ProcessApprovalAction;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Domains\Workflow\Models\ApprovalLog;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowDefinitionSeeder;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    (new RoleSeeder)->run();
    $this->seed(WorkflowDefinitionSeeder::class);
});

it('menolak pemanggilan verify dua kali berurutan pada pengajuan rapor yang sama tanpa membuat ApprovalLog ganda', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $roleWaka = Role::firstOrCreate(['name' => 'wakasek_kurikulum', 'guard_name' => 'web']);
    $userWaka = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userWaka->assignRole($roleWaka);
    $userWali = User::factory()->create(['lembaga_id' => $lembaga->id]);

    (new SimpanCatatanWaliKelasAction)->execute(CatatanWaliKelasData::fromArray(['siswa_id' => $siswa->id, 'semester_id' => $semester->id]));

    $submitAction = new SubmitPengajuanRaporAction(app(InitializeApprovalRequestAction::class));
    $pengajuan = $submitAction->execute($kelas, $semester, $userWali);

    $verifyAction = new VerifyPengajuanRaporAction(app(ProcessApprovalAction::class));
    $diverifikasi = $verifyAction->execute($pengajuan, $userWaka, ApprovalAction::Approve, 'Lengkap');

    expect($diverifikasi->status)->toBe(StatusPengajuanRapor::Diverifikasi);
    expect($diverifikasi->diverifikasi_oleh)->toBe($userWaka->id);

    $logCountSebelum = ApprovalLog::where('approval_request_id', $diverifikasi->approvalRequest->id)->count();

    // Panggil verify lagi setelah workflow sudah maju ke step approve (kepsek) --
    // mensimulasikan request kedua yang hampir bersamaan dengan yang pertama. Waka
    // sudah tidak berwenang di step berikutnya, jadi ProcessApprovalAction menolak.
    // lockForUpdate() pada PengajuanRapor memastikan proses ini tetap konsisten
    // meski dua request datang nyaris bersamaan.
    expect(fn () => $verifyAction->execute($diverifikasi->fresh(), $userWaka, ApprovalAction::Approve, null))
        ->toThrow(ValidationException::class);

    $diverifikasi->refresh();
    expect($diverifikasi->status)->toBe(StatusPengajuanRapor::Diverifikasi);
    expect(ApprovalLog::where('approval_request_id', $diverifikasi->approvalRequest->id)->count())
        ->toBe($logCountSebelum);
});
