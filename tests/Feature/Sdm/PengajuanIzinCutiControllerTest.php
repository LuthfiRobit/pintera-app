<?php
// tests/Feature/Sdm/PengajuanIzinCutiControllerTest.php

use App\Domains\Sdm\Models\PengajuanIzinCuti;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

it('lets a guru submit a pengajuan izin/cuti for themselves', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);
    foreach (['kehadiran-sdm.izin.ajukan', 'kehadiran-sdm.izin.lihat-sendiri'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['kehadiran-sdm.izin.ajukan', 'kehadiran-sdm.izin.lihat-sendiri']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $user->id]);

    $this->actingAs($user)->post(route('sdm.izin-cuti.store'), [
        'kategori' => 'sakit', 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-09-01', 'alasan' => 'Demam.',
    ])->assertRedirect(route('sdm.izin-cuti.index'));

    expect(PengajuanIzinCuti::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeTrue();
});

it('lets a guru cancel their own pending pengajuan via the destroy endpoint', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);
    foreach (['kehadiran-sdm.izin.ajukan', 'kehadiran-sdm.izin.lihat-sendiri'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['kehadiran-sdm.izin.ajukan', 'kehadiran-sdm.izin.lihat-sendiri']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $user->id]);
    $pengajuan = app(\App\Domains\Sdm\Actions\AjukanIzinCutiAction::class)->execute($guru, \App\Domains\Sdm\Enums\KategoriPengajuanIzin::Cuti, '2026-09-10', '2026-09-11', 'Acara.');

    $this->actingAs($user)->delete(route('sdm.izin-cuti.destroy', $pengajuan))
        ->assertRedirect(route('sdm.izin-cuti.index'));

    expect($pengajuan->approvalRequest->fresh()->status)->toBe(\App\Domains\Workflow\Enums\ApprovalStatus::Cancelled);
});

it('rejects a user without kehadiran-sdm.izin.ajukan permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->post(route('sdm.izin-cuti.store'), [
        'kategori' => 'sakit', 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-09-01', 'alasan' => 'Demam.',
    ])->assertForbidden();
});
