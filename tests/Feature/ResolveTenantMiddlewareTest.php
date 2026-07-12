<?php

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;

it('lets a yayasan-scoped user switch the active lembaga via query param', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);

    $user = User::factory()->create();
    $user->assignRole('yayasan_super_admin');

    $this->actingAs($user)->get('/dashboard?switch_lembaga='.$lembaga->id);

    expect(session('active_lembaga_id'))->toBe($lembaga->id);
});

it('clears the active lembaga when switch_lembaga=all', function () {
    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);

    $user = User::factory()->create();
    $user->assignRole('yayasan_super_admin');

    session(['active_lembaga_id' => 999]);

    $this->actingAs($user)->get('/dashboard?switch_lembaga=all');

    expect(session('active_lembaga_id'))->toBeNull();
});

it('ignores switch_lembaga for a lembaga-scoped user', function () {
    $yayasan = Yayasan::factory()->create();
    $ownLembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    $user = User::factory()->create(['lembaga_id' => $ownLembaga->id]);
    $user->assignRole('kepala_sekolah');

    $this->actingAs($user)->get('/dashboard?switch_lembaga='.$otherLembaga->id);

    expect(session('active_lembaga_id'))->toBeNull();
});
