<?php
// tests/Feature/Sdm/AjukanIzinCutiActionTest.php

use App\Domains\Sdm\Actions\AjukanIzinCutiAction;
use App\Domains\Sdm\Enums\KategoriPengajuanIzin;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Yayasan;
use Illuminate\Support\Facades\Artisan;

it('creates a pengajuan and initializes a pending approval request at step 1', function () {
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);
    Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Sakit, '2026-09-01', '2026-09-02', 'Demam.');

    expect($pengajuan->pegawai_id)->toBe($guru->id);
    $approvalRequest = $pengajuan->approvalRequest;
    expect($approvalRequest)->not->toBeNull();
    expect($approvalRequest->status)->toBe(ApprovalStatus::Pending);
    expect($approvalRequest->currentStep->step_name)->toBe('Verifikasi Kepala Sekolah');
});

it('rejects a pengajuan where tanggal_mulai is after tanggal_selesai', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    expect(fn () => app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Cuti, '2026-09-10', '2026-09-05', 'Cuti.'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});
