<?php
// tests/Feature/Keuangan/DashboardAuthorizationTest.php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('never resolves another parent\'s child as the active siswa via switch_siswa', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $siswaMine = Siswa::factory()->create(['lembaga_id' => $lembagaA->id, 'nama_lengkap' => 'Anak Saya']);
    $siswaOther = Siswa::factory()->create(['lembaga_id' => $lembagaB->id, 'nama_lengkap' => 'Anak Orang Lain']);

    $me = User::factory()->create(['lembaga_id' => null]);
    $me->assignRole('orang_tua');
    $ortuMe = OrangTua::create([
        'user_id' => $me->id, 'nama_lengkap' => 'Saya', 'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200007777',
    ]);
    $ortuMe->siswa()->attach($siswaMine->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $otherUser = User::factory()->create(['lembaga_id' => null]);
    $otherUser->assignRole('orang_tua');
    $ortuOther = OrangTua::create([
        'user_id' => $otherUser->id, 'nama_lengkap' => 'Orang Lain', 'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200008888',
    ]);
    $ortuOther->siswa()->attach($siswaOther->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    // Try to hijack the session into showing the other parent's child.
    $response = $this->actingAs($me)->get(route('keuangan.dashboard', ['switch_siswa' => $siswaOther->id]));

    $response->assertOk();
    $response->assertSee('Anak Saya');
    $response->assertDontSee('Anak Orang Lain');
});

it('does not leak lembaga B\'s wallet/tagihan data onto lembaga A parent\'s dashboard', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $siswaA = Siswa::factory()->create(['lembaga_id' => $lembagaA->id, 'nama_lengkap' => 'Anak Lembaga A']);
    $siswaA->wallet->update(['balance' => 999000, 'va_number' => '8808089999999999']);

    $siswaB = Siswa::factory()->create(['lembaga_id' => $lembagaB->id, 'nama_lengkap' => 'Anak Lembaga B']);
    $siswaB->wallet->update(['balance' => 111000, 'va_number' => '8808081111111111']);

    $userA = User::factory()->create(['lembaga_id' => null]);
    $userA->assignRole('orang_tua');
    $orangTuaA = OrangTua::create([
        'user_id' => $userA->id, 'nama_lengkap' => 'Ortu Lembaga A', 'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200009991',
    ]);
    $orangTuaA->siswa()->attach($siswaA->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $userB = User::factory()->create(['lembaga_id' => null]);
    $userB->assignRole('orang_tua');
    $orangTuaB = OrangTua::create([
        'user_id' => $userB->id, 'nama_lengkap' => 'Ortu Lembaga B', 'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200009992',
    ]);
    $orangTuaB->siswa()->attach($siswaB->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    // Two tagihan for siswa A, mirroring AutoAllocationEngine/SkipAlertResolver's
    // zero-or-skip semantics: the higher-priority tagihan exactly consumes siswa A's
    // wallet balance (999.000), leaving the lower-priority one with literally $0
    // allocated — that's what makes it "skipped" and surfaces the skip-alert banner.
    // This exercises SkipAlertResolver's withoutGlobalScope(TenantScope::class) join
    // path against tagihan/jenis_tagihan for siswa A specifically.
    $jenisTinggiA = JenisTagihan::factory()->create(['lembaga_id' => $lembagaA->id, 'priority_score' => 1, 'nama' => 'SPP Lembaga A']);
    Tagihan::factory()->create([
        'tagihable_id' => $siswaA->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenisTinggiA->id,
        'total_tagihan' => 999000, 'net_amount' => 999000, 'paid_amount' => 0,
        'status' => 'belum_bayar', 'jatuh_tempo' => now()->addDays(5),
    ]);
    $jenisRendahA = JenisTagihan::factory()->create(['lembaga_id' => $lembagaA->id, 'priority_score' => 2, 'nama' => 'Buku Lembaga A']);
    Tagihan::factory()->create([
        'tagihable_id' => $siswaA->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenisRendahA->id,
        'total_tagihan' => 501000, 'net_amount' => 501000, 'paid_amount' => 0,
        'status' => 'belum_bayar', 'jatuh_tempo' => now()->addDays(5),
    ]);

    // No active_lembaga_id session set (this admin-side concept is irrelevant for an
    // orang_tua user, who has no lembaga_id of their own) — the dashboard must still
    // resolve the wallet correctly via the relation-membership path, not lembaga_id.
    $response = $this->actingAs($userA)->get(route('keuangan.dashboard'));

    $response->assertOk();
    $response->assertSee('999.000', false);
    $response->assertSee('8808089999999999');
    $response->assertSee('501.000', false); // skip-alert shortfall: 1.500.000 - 999.000 balance

    // The assertions that would actually catch a broken tenant scope: siswa B's
    // wallet must never leak onto siswa A's parent's dashboard.
    $response->assertDontSee('111.000', false);
    $response->assertDontSee('8808081111111111');
});
