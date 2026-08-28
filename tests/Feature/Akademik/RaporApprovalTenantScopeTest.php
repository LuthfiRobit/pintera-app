<?php

use App\Domains\Akademik\Actions\Rapor\ApprovePengajuanRaporAction;
use App\Domains\Akademik\Actions\Rapor\SimpanCatatanWaliKelasAction;
use App\Domains\Akademik\Actions\Rapor\SubmitPengajuanRaporAction;
use App\Domains\Akademik\Actions\Rapor\VerifyPengajuanRaporAction;
use App\Domains\Akademik\DataTransferObjects\CatatanWaliKelasData;
use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Domains\Workflow\Actions\InitializeApprovalRequestAction;
use App\Domains\Workflow\Actions\ProcessApprovalAction;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WorkflowDefinitionSeeder;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

it('never resolves a PengajuanRapor belonging to another lembaga by id, so its ApprovalRequest is unreachable cross-tenant', function () {
    $this->seed([RolePermissionSeeder::class, WorkflowDefinitionSeeder::class]);

    $yayasan = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLain->id]);
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $tahunAjaranLain->id]);
    $siswaLain = Siswa::factory()->create(['lembaga_id' => $lembagaLain->id, 'kelas_id' => $kelasLain->id]);
    $userWaliLain = User::factory()->create(['lembaga_id' => $lembagaLain->id]);

    (new SimpanCatatanWaliKelasAction)->execute(CatatanWaliKelasData::fromArray(['siswa_id' => $siswaLain->id, 'semester_id' => $semesterLain->id]));
    $pengajuanLain = (new SubmitPengajuanRaporAction(app(InitializeApprovalRequestAction::class)))->execute($kelasLain, $semesterLain, $userWaliLain);

    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $roleWaka = Role::firstOrCreate(['name' => 'wakasek_kurikulum', 'guard_name' => 'web']);
    $userWakaSaya = User::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $userWakaSaya->assignRole($roleWaka);
    $this->actingAs($userWakaSaya);

    // Route-model-binding style lookup (tenant-scoped via BelongsToTenant): PengajuanRapor milik
    // lembaga lain tidak boleh bisa di-resolve oleh aktor lembaga sendiri.
    expect(PengajuanRapor::find($pengajuanLain->id))->toBeNull();

    // Kalau instance PengajuanRapor lembaga lain tetap diteruskan langsung ke Action (bukan
    // lewat find(), misal lewat command/job internal), Action wajib menolak berdasarkan
    // lembaga_id-nya sendiri - jangan hanya bergantung pada ApproverResolverService, yang
    // fail-open ketika relasi approvable/requester ikut ter-scope null oleh TenantScope.
    expect(fn () => (new VerifyPengajuanRaporAction(app(ProcessApprovalAction::class)))->execute($pengajuanLain, $userWakaSaya, ApprovalAction::Approve))
        ->toThrow(ValidationException::class);
});

it('rejects verify/approve when the acting user role does not match the current workflow step approver, even with a valid ApprovalRequest', function () {
    $this->seed([RolePermissionSeeder::class, WorkflowDefinitionSeeder::class]);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $userWali = User::factory()->create(['lembaga_id' => $lembaga->id]);

    (new SimpanCatatanWaliKelasAction)->execute(CatatanWaliKelasData::fromArray(['siswa_id' => $siswa->id, 'semester_id' => $semester->id]));
    $pengajuan = (new SubmitPengajuanRaporAction(app(InitializeApprovalRequestAction::class)))->execute($kelas, $semester, $userWali);

    // userKepsek belum punya giliran (step 1 = wakasek_kurikulum) - coba approve langsung harus ditolak resolver engine.
    $roleKepsek = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web']);
    $userKepsek = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userKepsek->assignRole($roleKepsek);

    expect(fn () => (new ApprovePengajuanRaporAction(app(ProcessApprovalAction::class)))->execute($pengajuan, $userKepsek, ApprovalAction::Approve))
        ->toThrow(ValidationException::class);
});

it('allows a yayasan-scoped user with the correct active lembaga to verify a pengajuan rapor', function () {
    $this->seed([RolePermissionSeeder::class, WorkflowDefinitionSeeder::class]);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $userWali = User::factory()->create(['lembaga_id' => $lembaga->id]);

    (new SimpanCatatanWaliKelasAction)->execute(CatatanWaliKelasData::fromArray(['siswa_id' => $siswa->id, 'semester_id' => $semester->id]));
    $pengajuan = (new SubmitPengajuanRaporAction(app(InitializeApprovalRequestAction::class)))->execute($kelas, $semester, $userWali);

    $roleWaka = tap(
        Role::firstOrCreate(['name' => 'wakasek_kurikulum', 'guard_name' => 'web'], ['scope_level' => 'yayasan']),
        fn ($r) => $r->update(['scope_level' => 'yayasan']),
    );
    $userWakaYayasan = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $userWakaYayasan->assignRole($roleWaka);
    session(['active_lembaga_id' => $lembaga->id]);

    $diverifikasi = (new VerifyPengajuanRaporAction(app(ProcessApprovalAction::class)))->execute($pengajuan, $userWakaYayasan, ApprovalAction::Approve);

    expect($diverifikasi->status)->toBe(StatusPengajuanRapor::Diverifikasi);
});

it('rejects a yayasan-scoped user without an active lembaga from verifying a pengajuan rapor', function () {
    $this->seed([RolePermissionSeeder::class, WorkflowDefinitionSeeder::class]);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $userWali = User::factory()->create(['lembaga_id' => $lembaga->id]);

    (new SimpanCatatanWaliKelasAction)->execute(CatatanWaliKelasData::fromArray(['siswa_id' => $siswa->id, 'semester_id' => $semester->id]));
    $pengajuan = (new SubmitPengajuanRaporAction(app(InitializeApprovalRequestAction::class)))->execute($kelas, $semester, $userWali);

    $roleWaka = tap(
        Role::firstOrCreate(['name' => 'wakasek_kurikulum', 'guard_name' => 'web'], ['scope_level' => 'yayasan']),
        fn ($r) => $r->update(['scope_level' => 'yayasan']),
    );
    $userWakaYayasan = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $userWakaYayasan->assignRole($roleWaka);
    // Tanpa session('active_lembaga_id') - mode "Semua Lembaga".

    expect(fn () => (new VerifyPengajuanRaporAction(app(ProcessApprovalAction::class)))->execute($pengajuan, $userWakaYayasan, ApprovalAction::Approve))
        ->toThrow(ValidationException::class);
});
