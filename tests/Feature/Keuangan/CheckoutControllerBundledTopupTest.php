<?php

use App\Contracts\PaymentGatewayInterface;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Pembayaran;
use App\Models\Siswa;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Mockery;

uses(RefreshDatabase::class);

function actingAsOrangTuaForBundledTopup(): array
{
    config(['services.bri.gateway' => 'mock']);
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Bundling',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200002222',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $jenis = JenisTagihan::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'belum_bayar', 'net_amount' => 100000, 'paid_amount' => 0,
    ]);

    return [$user, $siswa, $tagihan];
}

it('creates a bundled QRIS payment when topup_amount is submitted', function () {
    [$user, , $tagihan] = actingAsOrangTuaForBundledTopup();

    $response = $this->actingAs($user)->post(route('keuangan.checkout.qris'), [
        'tagihan_ids' => [$tagihan->id],
        'topup_amount' => 20000,
    ]);

    $pembayaran = Pembayaran::where('metode', 'qris')->firstOrFail();
    $response->assertRedirect(route('keuangan.checkout.show', $pembayaran));
    expect((float) $pembayaran->amount)->toBe(120000.0);
    expect($pembayaran->topup_status)->toBe('pending');
});

it('shows the checkout tab input for bundling a top-up', function () {
    [$user, , $tagihan] = actingAsOrangTuaForBundledTopup();

    $response = $this->actingAs($user)->get(route('keuangan.checkout.create', ['tagihan_ids' => [$tagihan->id]]));

    $response->assertOk();
    $response->assertSee('name="topup_amount"', false);
});
