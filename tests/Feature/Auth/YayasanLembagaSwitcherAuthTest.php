<?php

use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('keeps a yayasan-scoped user authenticated on later requests after switching active lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create([
        'lembaga_id' => null,
        'yayasan_id' => $yayasan->id,
        'email' => 'yayasan@example.test',
        'password' => bcrypt('password'),
    ]);
    $user->assignRole('yayasan_super_admin');

    // Real login (not actingAs(), which only sets an in-memory user on the guard and never
    // touches the session store — too shallow to reproduce a bug about session persistence).
    $this->post('/login', ['email' => 'yayasan@example.test', 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    // Laravel's test kernel reuses the same SessionGuard instance across calls within one test
    // method — unlike real separate HTTP requests, which always resolve a fresh guard from the
    // session store. Forget the resolved guard before each call below so every assertion here
    // genuinely re-hydrates via SessionGuard::user() -> the auth provider, matching what a real
    // second/third HTTP request does.
    app('auth')->forgetGuards();
    $this->get('/dashboard')->assertOk();

    // Simulate clicking the lembaga switcher: hits ResolveTenant middleware, sets session.
    app('auth')->forgetGuards();
    $this->get('/dashboard?switch_lembaga='.$lembaga->id)->assertOk();
    expect(session('active_lembaga_id'))->toBe($lembaga->id);

    // The bug: once active_lembaga_id is set, TenantScope would filter every subsequent User
    // query (including the guard's own re-hydration of the CURRENTLY authenticated user) by
    // lembaga_id = $lembaga->id. A yayasan-scoped user's own lembaga_id is always null, so that
    // filter can never match their own row, making them appear logged out.
    app('auth')->forgetGuards();
    $this->get('/dashboard')->assertOk();
});

it('allows a yayasan-scoped user to log in fresh even while a stale active_lembaga_id sits in session', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create([
        'lembaga_id' => null,
        'yayasan_id' => $yayasan->id,
        'email' => 'yayasan@example.test',
        'password' => bcrypt('password'),
    ]);
    $user->assignRole('yayasan_super_admin');

    $this->post('/login', ['email' => 'yayasan@example.test', 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    app('auth')->forgetGuards();
    $this->get('/dashboard?switch_lembaga='.$lembaga->id)->assertOk();
    expect(session('active_lembaga_id'))->toBe($lembaga->id);

    // Logout does not clear active_lembaga_id from the session today, so it lingers into the
    // next login attempt exactly as it would in a real browser tab.
    app('auth')->forgetGuards();
    $this->post('/logout');

    app('auth')->forgetGuards();
    $response = $this->post('/login', ['email' => 'yayasan@example.test', 'password' => 'password']);

    $response->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($user);
});
