<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAsOrangTuaForCheckout(): array
{
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Anak Checkout']);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Checkout',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200005555',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    return [$user, $orangTua, $siswa];
}

it('shows the checkout tabs with the selected tagihan total', function () {
    [$user, , $siswa] = actingAsOrangTuaForCheckout();

    $jenis = JenisTagihan::factory()->create(['nama' => 'SPP Bulanan']);
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'belum_bayar', 'net_amount' => 150000, 'paid_amount' => 0,
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.checkout.create', ['tagihan_ids' => [$tagihan->id]]));

    $response->assertOk();
    $response->assertSee('150.000', false);
    $response->assertSee('VA BRI');
    $response->assertSee('QRIS');
    $response->assertSee('Saldo Wallet');
    $response->assertSee('Transfer Manual');
});

it('ignores tagihan ids that do not belong to the active child', function () {
    [$user, , $siswa] = actingAsOrangTuaForCheckout();
    $otherSiswa = Siswa::factory()->create();

    $jenis = JenisTagihan::factory()->create();
    $foreignTagihan = Tagihan::factory()->create([
        'tagihable_id' => $otherSiswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'belum_bayar', 'net_amount' => 999999, 'paid_amount' => 0,
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.checkout.create', ['tagihan_ids' => [$foreignTagihan->id]]));

    $response->assertOk();
    $response->assertDontSee('999.999', false);
});
