<?php
// tests/Feature/Sdm/KalenderKerjaSdmTenantIsolationTest.php

use App\Domains\Sdm\Enums\TipeKalenderKerjaSdm;
use App\Domains\Sdm\Models\KalenderKerjaSdm;
use App\Domains\Sdm\Services\KalenderKerjaSdmResolver;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Carbon\Carbon;

it('resolver still sees the national holiday entry when called while a lembaga-scoped actor is authenticated', function () {
    // Regression guard: KalenderKerjaSdm uses BelongsToTenant, so without an explicit
    // withoutGlobalScope(TenantScope::class) inside the resolver, TenantScope would force
    // `lembaga_id = actingUser->lembaga_id` onto the resolver's whereNull('lembaga_id') query
    // for the national entry, making it impossible to ever match (0 rows), silently hiding
    // every national holiday from a logged-in scope_level:lembaga actor.
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    KalenderKerjaSdm::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null, 'tanggal' => '2026-08-17', 'nama' => 'Hari Kemerdekaan RI', 'tipe' => TipeKalenderKerjaSdm::Libur]);

    $role = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $actor = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $actor->assignRole($role);

    $this->actingAs($actor);

    $result = app(KalenderKerjaSdmResolver::class)->resolve($lembaga, Carbon::parse('2026-08-17'));

    expect($result['libur'])->toBeTrue();
    expect($result['alasan'])->toBe('Hari Kemerdekaan RI');
});
