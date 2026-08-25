<?php
// tests/Feature/Sdm/IzinCutiWorkflowSeedTest.php

use App\Domains\Workflow\Enums\ApproverType;
use App\Domains\Workflow\Models\WorkflowDefinition;
use App\Models\Role;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

it('seeds the IZIN_CUTI_SDM workflow with 2 role-based steps', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);

    $definition = WorkflowDefinition::where('code', 'IZIN_CUTI_SDM')->first();
    expect($definition)->not->toBeNull();
    expect($definition->is_active)->toBeTrue();

    $steps = $definition->steps;
    expect($steps)->toHaveCount(2);
    expect($steps[0]->approver_type)->toBe(ApproverType::Role);
    expect($steps[0]->approver_value)->toBe('kepala_sekolah');
    expect($steps[0]->is_final_step)->toBeFalse();
    expect($steps[1]->approver_value)->toBe('admin_sdm');
    expect($steps[1]->is_final_step)->toBeTrue();
});

it('seeds the 3 kehadiran-sdm.izin.* permissions and grants them to the right roles', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);

    foreach (['kehadiran-sdm.izin.ajukan', 'kehadiran-sdm.izin.approve', 'kehadiran-sdm.izin.lihat-sendiri'] as $permission) {
        expect(Permission::where('name', $permission)->exists())->toBeTrue();
    }

    expect(Role::where('name', 'guru')->first()->hasPermissionTo('kehadiran-sdm.izin.ajukan'))->toBeTrue();
    expect(Role::where('name', 'pegawai_lembaga')->first()->hasPermissionTo('kehadiran-sdm.izin.ajukan'))->toBeTrue();
    expect(Role::where('name', 'kepala_sekolah')->first()->hasPermissionTo('kehadiran-sdm.izin.approve'))->toBeTrue();
    expect(Role::where('name', 'admin_sdm')->first()->hasPermissionTo('kehadiran-sdm.izin.approve'))->toBeTrue();
});
