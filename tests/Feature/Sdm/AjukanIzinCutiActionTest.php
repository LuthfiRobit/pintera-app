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

it('rejects a Cuti pengajuan spanning two different calendar years', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    expect(fn () => app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Cuti, '2026-12-30', '2027-01-02', 'Cuti tahun baru.'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('allows a Sakit pengajuan spanning two different calendar years (only Cuti is restricted)', function () {
    seedKuotaCutiWorkflowForTest_ajukan();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Sakit, '2026-12-30', '2027-01-02', 'Sakit lintas tahun.');

    expect($pengajuan->kategori)->toBe(\App\Domains\Sdm\Enums\KategoriPengajuanIzin::Sakit);
});

it('allows a Cuti pengajuan when there is no kuota config at all (no enforcement)', function () {
    seedKuotaCutiWorkflowForTest_ajukan();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Cuti, '2026-09-01', '2026-09-30', 'Cuti panjang, tidak ada config kuota.');

    expect($pengajuan)->not->toBeNull();
});

it('rejects a Cuti pengajuan that exceeds the remaining kuota', function () {
    seedKuotaCutiWorkflowForTest_ajukan();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    \App\Domains\Sdm\Models\KuotaCutiConfig::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jatah_hari_per_tahun' => 5]);

    expect(fn () => app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Cuti, '2026-09-01', '2026-09-10', 'Cuti 10 hari, jatah cuma 5.'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('allows a Cuti pengajuan within the remaining kuota', function () {
    seedKuotaCutiWorkflowForTest_ajukan();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    \App\Domains\Sdm\Models\KuotaCutiConfig::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jatah_hari_per_tahun' => 12]);

    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Cuti, '2026-09-01', '2026-09-05', 'Cuti 5 hari, jatah 12.');

    expect($pengajuan)->not->toBeNull();
});

it('rejects a Cuti pengajuan when admin explicitly configured a 0-day kuota (zero must not be treated as unconfigured)', function () {
    seedKuotaCutiWorkflowForTest_ajukan();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    \App\Domains\Sdm\Models\KuotaCutiConfig::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jatah_hari_per_tahun' => 0]);

    expect(fn () => app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Cuti, '2026-09-01', '2026-09-01', 'Cuti 1 hari, jatah dibekukan ke 0.'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('serializes concurrent Cuti submissions for the same pegawai+tahun via Cache::lock (isolation test, not true concurrency)', function () {
    seedKuotaCutiWorkflowForTest_ajukan();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    \App\Domains\Sdm\Models\KuotaCutiConfig::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jatah_hari_per_tahun' => 12]);

    $lockKey = 'kuota-cuti:'.\App\Models\Guru::class.':'.$guru->id.':2026';
    $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 10);
    expect($lock->get())->toBeTrue();

    expect(fn () => app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Cuti, '2026-09-01', '2026-09-03', 'Cuti saat lock dipegang.'))
        ->toThrow(\Illuminate\Contracts\Cache\LockTimeoutException::class);

    $lock->release();

    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Cuti, '2026-09-01', '2026-09-03', 'Cuti setelah lock dilepas.');
    expect($pengajuan)->not->toBeNull();
});

function seedKuotaCutiWorkflowForTest_ajukan(): void
{
    Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);
    Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\WorkflowDefinitionSeeder']);
    Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
}
