<?php
// tests/Feature/Sdm/ApprovalIzinCutiControllerTest.php

use App\Domains\Sdm\Actions\AjukanIzinCutiAction;
use App\Domains\Sdm\Enums\KategoriPengajuanIzin;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Support\Facades\Artisan;

function seedIzinCutiWorkflowForControllerTest(): void
{
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);
}

it('shows the decision form to the approver whose turn it currently is', function () {
    seedIzinCutiWorkflowForControllerTest();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $kepsekRole = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $kepsek = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $kepsek->assignRole($kepsekRole);
    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Sakit, '2026-09-01', '2026-09-01', 'Demam.');

    $response = $this->actingAs($kepsek)->get(route('admin.kehadiran-sdm.izin-cuti.show', $pengajuan));

    $response->assertOk()->assertSee('Form Keputusan Approver')->assertDontSee('Menunggu Keputusan Tahap Ini');
});

it('hides the decision form and shows a waiting notice for an approver whose turn has not come yet', function () {
    seedIzinCutiWorkflowForControllerTest();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $adminSdmRole = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $adminSdm = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $adminSdm->assignRole($adminSdmRole);
    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Sakit, '2026-09-01', '2026-09-01', 'Demam.');

    $response = $this->actingAs($adminSdm)->get(route('admin.kehadiran-sdm.izin-cuti.show', $pengajuan));

    $response->assertOk()->assertDontSee('Form Keputusan Approver')->assertSee('Menunggu Keputusan Tahap Ini');
});
