<?php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Pembayaran;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAsOrangTuaForWalletCheckout(float $balance = 200000): array
{
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa->wallet->update(['balance' => $balance]);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Wallet',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200007777',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $jenis = JenisTagihan::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'belum_bayar', 'net_amount' => 100000, 'paid_amount' => 0,
    ]);

    return [$user, $orangTua, $siswa, $tagihan];
}

it('pays a tagihan from wallet balance and redirects to the success page', function () {
    [$user, , $siswa, $tagihan] = actingAsOrangTuaForWalletCheckout();

    $response = $this->actingAs($user)->post(route('keuangan.checkout.wallet'), [
        'tagihan_ids' => [$tagihan->id],
    ]);

    $pembayaran = Pembayaran::where('metode', 'wallet_saldo')->firstOrFail();
    $response->assertRedirect(route('keuangan.checkout.sukses', $pembayaran));

    $siswa->wallet->refresh();
    $this->assertEquals(100000, $siswa->wallet->balance);

    $tagihan->refresh();
    $this->assertEquals('lunas', $tagihan->status);
});

it('rejects wallet checkout when balance is insufficient', function () {
    [$user, , $siswa, $tagihan] = actingAsOrangTuaForWalletCheckout(balance: 10000);

    $response = $this->actingAs($user)->post(route('keuangan.checkout.wallet'), [
        'tagihan_ids' => [$tagihan->id],
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('tagihan_ids');
    $this->assertEquals(0, Pembayaran::where('metode', 'wallet_saldo')->count());

    $siswa->wallet->refresh();
    $this->assertEquals(10000, $siswa->wallet->balance);
});

it('shows the success page after a wallet payment', function () {
    [$user, , , $tagihan] = actingAsOrangTuaForWalletCheckout();
    $this->actingAs($user)->post(route('keuangan.checkout.wallet'), ['tagihan_ids' => [$tagihan->id]]);
    $pembayaran = Pembayaran::where('metode', 'wallet_saldo')->firstOrFail();

    $response = $this->actingAs($user)->get(route('keuangan.checkout.sukses', $pembayaran));

    $response->assertOk();
    $response->assertSee('Pembayaran Selesai');
});
