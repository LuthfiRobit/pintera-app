<?php
// tests/Feature/Sdm/ProsesApprovalIzinCutiActionTest.php

use App\Domains\Sdm\Actions\AjukanIzinCutiAction;
use App\Domains\Sdm\Actions\ProsesApprovalIzinCutiAction;
use App\Domains\Sdm\Enums\AttendanceStatus;
use App\Domains\Sdm\Enums\KategoriPengajuanIzin;
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Support\Facades\Artisan;

function seedIzinCutiWorkflowForTest(): void
{
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);
}

it('moves to step 2 and does not create any AttendanceEvent after step-1 approval', function () {
    seedIzinCutiWorkflowForTest();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $kepsekRole = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $kepsek = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $kepsek->assignRole($kepsekRole);
    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Sakit, '2026-09-01', '2026-09-01', 'Demam.');

    app(ProsesApprovalIzinCutiAction::class)->execute($pengajuan, $kepsek, ApprovalAction::Approve);

    $pengajuan->refresh();
    expect($pengajuan->approvalRequest->status)->toBe(ApprovalStatus::InReview);
    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeFalse();
});

it('creates one AttendanceEvent per day in range with the correct status after final-step approval', function () {
    seedIzinCutiWorkflowForTest();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $kepsekRole = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $adminSdmRole = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $kepsek = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $kepsek->assignRole($kepsekRole);
    $adminSdm = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $adminSdm->assignRole($adminSdmRole);
    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Cuti, '2026-09-01', '2026-09-03', 'Acara keluarga.');
    app(ProsesApprovalIzinCutiAction::class)->execute($pengajuan, $kepsek, ApprovalAction::Approve);

    app(ProsesApprovalIzinCutiAction::class)->execute($pengajuan, $adminSdm, ApprovalAction::Approve);

    $pengajuan->refresh();
    expect($pengajuan->approvalRequest->status)->toBe(ApprovalStatus::Approved);
    $records = AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->orderBy('tanggal')->get();
    expect($records)->toHaveCount(3);
    foreach ($records as $record) {
        expect($record->status)->toBe(AttendanceStatus::Cuti);
    }
});

it('creates no AttendanceEvent when rejected', function () {
    seedIzinCutiWorkflowForTest();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $kepsekRole = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $kepsek = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $kepsek->assignRole($kepsekRole);
    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Izin, '2026-09-01', '2026-09-01', 'Keperluan pribadi.');

    app(ProsesApprovalIzinCutiAction::class)->execute($pengajuan, $kepsek, ApprovalAction::Reject, 'Tidak disetujui.');

    $pengajuan->refresh();
    expect($pengajuan->approvalRequest->status)->toBe(ApprovalStatus::Rejected);
    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeFalse();
});
