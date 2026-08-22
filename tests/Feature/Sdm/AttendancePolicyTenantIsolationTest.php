<?php
// tests/Feature/Sdm/AttendancePolicyTenantIsolationTest.php

use App\Domains\Sdm\Models\AttendancePolicy;
use App\Domains\Sdm\Services\AttendancePolicyResolver;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;

it('resolver still finds the yayasan-default policy when called while a lembaga-scoped actor is authenticated', function () {
    // Regression guard: AttendancePolicy uses BelongsToTenant, so without an explicit
    // withoutGlobalScope(TenantScope::class), TenantScope would force `lembaga_id =
    // actingUser->lembaga_id` onto resolvePolicy()'s whereNull('lembaga_id') query for the
    // yayasan-default row, making it impossible to ever match for a logged-in scope_level:
    // lembaga actor.
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas']);
    AttendancePolicy::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:00', 'toleransi_menit' => 0]);

    $role = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $actor = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $actor->assignRole($role);
    $this->actingAs($actor);

    $policy = app(AttendancePolicyResolver::class)->resolvePolicy($guru);

    expect($policy)->not->toBeNull();
    expect($policy->jam_masuk)->toBe('07:00:00');
});
