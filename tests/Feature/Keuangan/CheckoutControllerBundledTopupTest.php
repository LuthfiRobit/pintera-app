<?php

use App\Contracts\PaymentGatewayInterface;
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

it('creates a bundled VA payment when topup_amount is submitted alongside tagihan_ids', function () {
    [$user, , $tagihan] = actingAsOrangTuaForBundledTopup();

    $response = $this->actingAs($user)->post(route('keuangan.checkout.va'), [
        'tagihan_ids' => [$tagihan->id],
        'topup_amount' => 50000,
    ]);
    
    if ($response->exception) {
        throw $response->exception;
    }

    $pembayaran = Pembayaran::where('metode', 'va_bri')->firstOrFail();
    $response->assertRedirect(route('keuangan.checkout.show', $pembayaran));
    expect((float) $pembayaran->amount)->toBe(150000.0);
    expect($pembayaran->topup_status)->toBe('pending');
});

it('creates a plain VA payment (no topup_status) when topup_amount is omitted', function () {
    [$user, , $tagihan] = actingAsOrangTuaForBundledTopup();

    $this->actingAs($user)->post(route('keuangan.checkout.va'), ['tagihan_ids' => [$tagihan->id]]);

    $pembayaran = Pembayaran::where('metode', 'va_bri')->firstOrFail();
    expect($pembayaran->topup_status)->toBe('none');
});

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

it('creates a second, distinct VA when re-submitting the same tagihan with a topup amount', function () {
    [$user, , $tagihan] = actingAsOrangTuaForBundledTopup();

    $first = $this->actingAs($user)->post(route('keuangan.checkout.va'), [
        'tagihan_ids' => [$tagihan->id],
    ]);
    $first->assertRedirect();

    $firstPembayaran = Pembayaran::where('metode', 'va_bri')->firstOrFail();
    expect($firstPembayaran->topup_status)->toBe('none');

    $second = $this->actingAs($user)->post(route('keuangan.checkout.va'), [
        'tagihan_ids' => [$tagihan->id],
        'topup_amount' => 50000,
    ]);

    if ($second->exception) {
        throw $second->exception;
    }

    expect(Pembayaran::where('metode', 'va_bri')->count())->toBe(2);

    $secondPembayaran = Pembayaran::where('metode', 'va_bri')
        ->where('id', '!=', $firstPembayaran->id)
        ->firstOrFail();

    $second->assertRedirect(route('keuangan.checkout.show', $secondPembayaran));
    expect($secondPembayaran->id)->not->toBe($firstPembayaran->id);
    expect($secondPembayaran->topup_status)->toBe('pending');
    expect((float) $secondPembayaran->amount)->toBe(150000.0);
});

it('does not redirect a plain resubmit into an existing bundled payment for the same tagihan', function () {
    [$user, , $tagihan] = actingAsOrangTuaForBundledTopup();

    $bundled = $this->actingAs($user)->post(route('keuangan.checkout.va'), [
        'tagihan_ids' => [$tagihan->id],
        'topup_amount' => 50000,
    ]);

    if ($bundled->exception) {
        throw $bundled->exception;
    }

    $bundledPembayaran = Pembayaran::where('metode', 'va_bri')->firstOrFail();
    expect($bundledPembayaran->topup_status)->toBe('pending');
    expect((float) $bundledPembayaran->amount)->toBe(150000.0);

    $plain = $this->actingAs($user)->post(route('keuangan.checkout.va'), [
        'tagihan_ids' => [$tagihan->id],
    ]);

    if ($plain->exception) {
        throw $plain->exception;
    }

    expect(Pembayaran::where('metode', 'va_bri')->count())->toBe(2);

    $plainPembayaran = Pembayaran::where('metode', 'va_bri')
        ->where('id', '!=', $bundledPembayaran->id)
        ->firstOrFail();

    $plain->assertRedirect(route('keuangan.checkout.show', $plainPembayaran));
    expect($plainPembayaran->topup_status)->toBe('none');
});

it('shows the checkout tab input for bundling a top-up', function () {
    [$user, , $tagihan] = actingAsOrangTuaForBundledTopup();

    $response = $this->actingAs($user)->get(route('keuangan.checkout.create', ['tagihan_ids' => [$tagihan->id]]));

    $response->assertOk();
    $response->assertSee('name="topup_amount"', false);
});
