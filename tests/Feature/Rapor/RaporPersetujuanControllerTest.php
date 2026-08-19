<?php

use App\Domains\Akademik\Actions\Rapor\SimpanCatatanWaliKelasAction;
use App\Domains\Akademik\Actions\Rapor\SubmitPengajuanRaporAction;
use App\Domains\Akademik\Actions\Rapor\VerifyPengajuanRaporAction;
use App\Domains\Akademik\DataTransferObjects\CatatanWaliKelasData;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Domains\Workflow\Actions\InitializeApprovalRequestAction;
use App\Domains\Workflow\Actions\ProcessApprovalAction;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\WorkflowDefinitionSeeder;
use Spatie\Permission\Models\Permission;

function siapkanAktorPersetujuan(): array
{
    Permission::firstOrCreate(['name' => 'rapor.verify', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'rapor.approve', 'guard_name' => 'web']);
    $roleWaka = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web']);
    $roleWaka->givePermissionTo(['rapor.verify']);
    $roleKepsek = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web']);
    $roleKepsek->givePermissionTo(['rapor.approve']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Kelas 5A']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $userWali = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userWaka = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userWaka->assignRole($roleWaka);
    $userKepsek = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userKepsek->assignRole($roleKepsek);

    (new SimpanCatatanWaliKelasAction())->execute(CatatanWaliKelasData::fromArray(['siswa_id' => $siswa->id, 'semester_id' => $semester->id]));
    $pengajuan = (new SubmitPengajuanRaporAction(app(InitializeApprovalRequestAction::class)))->execute($kelas, $semester, $userWali);

    return compact('lembaga', 'kelas', 'semester', 'siswa', 'userWaka', 'userKepsek', 'pengajuan');
}

it('denies access without rapor.verify or rapor.approve permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.rapor.persetujuan.index'))->assertForbidden();
});

it('shows Waka the pengajuan that is Diajukan, not the ones already Diverifikasi', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userWaka' => $userWaka, 'kelas' => $kelas] = siapkanAktorPersetujuan();

    $response = $this->actingAs($userWaka)->get(route('admin.rapor.persetujuan.index'));

    $response->assertOk();
    $response->assertSee('Kelas 5A');
});

it('does not let Waka open the show page for a pengajuan not at their step', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userWaka' => $userWaka, 'userKepsek' => $userKepsek, 'pengajuan' => $pengajuan] = siapkanAktorPersetujuan();

    (new VerifyPengajuanRaporAction(app(ProcessApprovalAction::class)))->execute($pengajuan, $userWaka, ApprovalAction::Approve);

    $this->actingAs($userWaka)
        ->get(route('admin.rapor.persetujuan.show', $pengajuan))
        ->assertNotFound();
});

it('shows Kepsek the show page once the pengajuan is Diverifikasi, with rekap nilai and catatan wali kelas', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userWaka' => $userWaka, 'userKepsek' => $userKepsek, 'pengajuan' => $pengajuan, 'siswa' => $siswa] = siapkanAktorPersetujuan();

    (new VerifyPengajuanRaporAction(app(ProcessApprovalAction::class)))->execute($pengajuan, $userWaka, ApprovalAction::Approve);

    $response = $this->actingAs($userKepsek)->get(route('admin.rapor.persetujuan.show', $pengajuan->fresh()));

    $response->assertOk();
    $response->assertSee($siswa->nama_lengkap);
    $response->assertViewHas('catatanList', fn ($list) => $list->has($siswa->id));
});

it('is tenant-scoped: PengajuanRapor from another lembaga 404s via route model binding', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userWaka' => $userWaka] = siapkanAktorPersetujuan();

    $yayasanLain = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLain->id]);
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $tahunAjaranLain->id]);
    $pengajuanLain = PengajuanRapor::withoutGlobalScopes()->create([
        'lembaga_id' => $lembagaLain->id, 'kelas_id' => $kelasLain->id, 'semester_id' => $semesterLain->id,
        'status' => \App\Domains\Akademik\Enums\StatusPengajuanRapor::Diajukan,
    ]);

    $this->actingAs($userWaka)->get(route('admin.rapor.persetujuan.show', $pengajuanLain))->assertNotFound();
});

it('lets Waka approve, advancing status to Diverifikasi', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userWaka' => $userWaka, 'pengajuan' => $pengajuan] = siapkanAktorPersetujuan();

    $response = $this->actingAs($userWaka)->post(route('admin.rapor.persetujuan.decision', $pengajuan), [
        'action' => 'APPROVE', 'catatan' => 'Lengkap dan sudah sesuai.',
    ]);

    $response->assertRedirect(route('admin.rapor.persetujuan.index'));
    $this->assertDatabaseHas('pengajuan_rapor', [
        'id' => $pengajuan->id,
        'status' => \App\Domains\Akademik\Enums\StatusPengajuanRapor::Diverifikasi->value,
    ]);
});

it('lets Waka reject, setting status to Ditolak with catatan_revisi', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userWaka' => $userWaka, 'pengajuan' => $pengajuan] = siapkanAktorPersetujuan();

    $this->actingAs($userWaka)->post(route('admin.rapor.persetujuan.decision', $pengajuan), [
        'action' => 'REJECT', 'catatan' => 'Nilai belum lengkap.',
    ]);

    $this->assertDatabaseHas('pengajuan_rapor', [
        'id' => $pengajuan->id,
        'status' => \App\Domains\Akademik\Enums\StatusPengajuanRapor::Ditolak->value,
        'catatan_revisi' => 'Nilai belum lengkap.',
    ]);
});

it('lets Kepsek approve a Diverifikasi pengajuan, advancing status to Disetujui', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userWaka' => $userWaka, 'userKepsek' => $userKepsek, 'pengajuan' => $pengajuan] = siapkanAktorPersetujuan();
    (new VerifyPengajuanRaporAction(app(ProcessApprovalAction::class)))->execute($pengajuan, $userWaka, ApprovalAction::Approve);

    $this->actingAs($userKepsek)->post(route('admin.rapor.persetujuan.decision', $pengajuan->fresh()), ['action' => 'APPROVE']);

    $this->assertDatabaseHas('pengajuan_rapor', [
        'id' => $pengajuan->id,
        'status' => \App\Domains\Akademik\Enums\StatusPengajuanRapor::Disetujui->value,
    ]);
});

it('rejects REQUEST_REVISION as an invalid action value with a 422', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userWaka' => $userWaka, 'pengajuan' => $pengajuan] = siapkanAktorPersetujuan();

    $this->actingAs($userWaka)
        ->post(route('admin.rapor.persetujuan.decision', $pengajuan), ['action' => 'REQUEST_REVISION'])
        ->assertSessionHasErrors('action');
});

it('rejects a decision from the wrong step (Kepsek trying to decide before Waka verifies)', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userKepsek' => $userKepsek, 'pengajuan' => $pengajuan] = siapkanAktorPersetujuan();

    $this->actingAs($userKepsek)
        ->post(route('admin.rapor.persetujuan.decision', $pengajuan), ['action' => 'APPROVE'])
        ->assertNotFound();
});
