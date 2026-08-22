<?php
// tests/Feature/Admin/ApprovalIzinCutiControllerTest.php

use App\Domains\Sdm\Actions\AjukanIzinCutiAction;
use App\Domains\Sdm\Enums\KategoriPengajuanIzin;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

it('lets a kepala_sekolah approve a step-1 pending pengajuan via the decision endpoint', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.izin.approve', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('kehadiran-sdm.izin.approve');
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $kepsek = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $kepsek->assignRole($role);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Sakit, '2026-09-01', '2026-09-01', 'Sakit.');

    $this->actingAs($kepsek)->post(route('admin.kehadiran-sdm.izin-cuti.decision', $pengajuan), [
        'action' => 'APPROVE',
    ])->assertRedirect(route('admin.kehadiran-sdm.izin-cuti.index'));

    expect($pengajuan->approvalRequest->fresh()->status)->toBe(ApprovalStatus::InReview);
});

it('rejects an actor whose role cannot approve the current step (wrong turn)', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.izin.approve', 'guard_name' => 'web']);
    $adminSdmRole = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $adminSdmRole->givePermissionTo('kehadiran-sdm.izin.approve');
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $adminSdm = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $adminSdm->assignRole($adminSdmRole);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Sakit, '2026-09-01', '2026-09-01', 'Sakit.');

    // admin_sdm punya permission kehadiran-sdm.izin.approve, tapi step 1 butuh kepala_sekolah —
    // ProcessApprovalAction (Workflow domain) yang menolak, bukan permission gate controller.
    $this->actingAs($adminSdm)->post(route('admin.kehadiran-sdm.izin-cuti.decision', $pengajuan), [
        'action' => 'APPROVE',
    ])->assertRedirect();

    expect($pengajuan->approvalRequest->fresh()->status)->toBe(ApprovalStatus::Pending);
});

it('rejects an admin without kehadiran-sdm.izin.approve permission', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $noPermissionUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Sakit, '2026-09-01', '2026-09-01', 'Sakit.');

    $this->actingAs($noPermissionUser)->get(route('admin.kehadiran-sdm.izin-cuti.index'))->assertForbidden();
});
