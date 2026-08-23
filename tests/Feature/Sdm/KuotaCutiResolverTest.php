<?php
// tests/Feature/Sdm/KuotaCutiResolverTest.php

use App\Domains\Sdm\Actions\AjukanIzinCutiAction;
use App\Domains\Sdm\Enums\KategoriPengajuanIzin;
use App\Domains\Sdm\Models\KuotaCutiConfig;
use App\Domains\Sdm\Services\KuotaCutiResolver;
use App\Domains\Workflow\Actions\ProcessApprovalAction;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Support\Facades\Artisan;

function seedKuotaCutiWorkflowForTest(): void
{
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);
    Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);
}

it('returns 0 jatah when there is no config at all', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    expect(app(KuotaCutiResolver::class)->jatahTahunan($guru))->toBe(0);
});

it('resolves the lembaga-level flat config over nasional when both exist', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    KuotaCutiConfig::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'jatah_hari_per_tahun' => 10]);
    KuotaCutiConfig::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jatah_hari_per_tahun' => 12]);

    expect(app(KuotaCutiResolver::class)->jatahTahunan($guru))->toBe(12);
});

it('falls back to nasional flat config when there is no lembaga-level config', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    KuotaCutiConfig::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'jatah_hari_per_tahun' => 10]);

    expect(app(KuotaCutiResolver::class)->jatahTahunan($guru))->toBe(10);
});

it('only counts Cuti pengajuan with Pending/InReview/Approved status in the given year', function () {
    seedKuotaCutiWorkflowForTest();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    KuotaCutiConfig::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jatah_hari_per_tahun' => 12]);
    $kepsekRole = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $kepsek = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $kepsek->assignRole($kepsekRole);

    // Pending (3 hari) — dihitung.
    app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Cuti, '2026-09-01', '2026-09-03', 'Cuti A.');

    // Rejected (5 hari) — TIDAK dihitung.
    $ditolak = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Cuti, '2026-10-01', '2026-10-05', 'Cuti B.');
    app(ProcessApprovalAction::class)->execute($ditolak->approvalRequest, $kepsek, ApprovalAction::Reject, 'Ditolak.');

    // Beda kategori (Sakit, 100 hari) — TIDAK dihitung meski hari-nya besar.
    app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Sakit, '2026-11-01', '2026-11-05', 'Sakit.');

    $sisa = app(KuotaCutiResolver::class)->sisaKuota($guru, 2026);

    expect($sisa)->toBe(9); // 12 - 3 (hanya pengajuan Pending yang dihitung)
});
