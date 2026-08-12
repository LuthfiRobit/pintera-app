<?php
// tests/Feature/Keuangan/DashboardControllerTest.php

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

function actingAsOrangTuaForDashboard(): array
{
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Anak Dashboard']);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Dashboard',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200003333',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    return [$user, $orangTua, $siswa];
}

it('shows the wallet balance and VA number on the dashboard', function () {
    [$user, , $siswa] = actingAsOrangTuaForDashboard();
    $siswa->wallet->update(['balance' => 250000, 'va_number' => '8808081234567890']);

    $response = $this->actingAs($user)->get(route('keuangan.dashboard'));

    $response->assertOk();
    $response->assertSee('250.000', false);
    $response->assertSee('8808081234567890');
});

it('shows the skip-alert banner for a tagihan that receives zero allocation', function () {
    [$user, , $siswa] = actingAsOrangTuaForDashboard();
    // Wallet balance exactly covers the higher-priority tagihan, leaving nothing
    // for the lower-priority one — mirrors AutoAllocationEngine/SaldoTidakCukupNotificationTest's
    // zero-or-skip scenario: the second tagihan receives literally $0 (amountToPay == 0).
    $siswa->wallet->update(['balance' => 100000]);
    $jenisTinggi = JenisTagihan::factory()->create(['priority_score' => 1, 'nama' => 'SPP']);
    Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenisTinggi->id,
        'total_tagihan' => 100000, 'net_amount' => 100000, 'paid_amount' => 0,
        'status' => 'belum_bayar', 'jatuh_tempo' => now()->addDays(5),
    ]);
    $jenisRendah = JenisTagihan::factory()->create(['priority_score' => 2, 'nama' => 'Buku']);
    Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenisRendah->id,
        'total_tagihan' => 500000, 'net_amount' => 500000, 'paid_amount' => 0,
        'status' => 'belum_bayar', 'jatuh_tempo' => now()->addDays(5),
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.dashboard'));

    $response->assertOk();
    $response->assertSee('500.000', false); // shortfall = full net_amount, zero allocated
});

it('does not show the skip-alert banner for a single tagihan that receives a partial allocation', function () {
    [$user, , $siswa] = actingAsOrangTuaForDashboard();
    // Under zero-or-skip semantics, a tagihan that WOULD receive a partial payment
    // (amountToPay > 0) is not "skipped" — it would be marked 'sebagian' by the real
    // engine, not left out of allocation entirely. This locks in the reverted decision.
    $siswa->wallet->update(['balance' => 50000]);
    $jenis = JenisTagihan::factory()->create(['priority_score' => 1, 'nama' => 'SPP']);
    Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'total_tagihan' => 200000, 'net_amount' => 200000, 'paid_amount' => 0,
        'status' => 'belum_bayar', 'jatuh_tempo' => now()->addDays(5),
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.dashboard'));

    $response->assertOk();
    $response->assertDontSee('Top-up Rp');
});

it('does not show the skip-alert banner when balance fully covers all tagihan', function () {
    [$user, , $siswa] = actingAsOrangTuaForDashboard();
    $siswa->wallet->update(['balance' => 500000]);
    $jenis = JenisTagihan::factory()->create(['priority_score' => 1]);
    Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'total_tagihan' => 200000, 'net_amount' => 200000, 'paid_amount' => 0,
        'status' => 'belum_bayar', 'jatuh_tempo' => now()->addDays(5),
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.dashboard'));

    $response->assertOk();
    $response->assertDontSee('Top-up Rp');
});

it('shows the "tanpa anak" page for an orang tua with zero linked siswa', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');
    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Tanpa Anak',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200004444',
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.dashboard'));

    $response->assertOk();
    $response->assertSee('belum ada anak terdaftar', false);
});

it('blocks a user without keuangan.akses permission', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('keuangan.dashboard'));

    $response->assertForbidden();
});
