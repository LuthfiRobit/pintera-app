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

uses(RefreshDatabase::class);

function actingAsOrangTuaForVaQris(): array
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
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu VaQris',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200006666',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $jenis = JenisTagihan::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'belum_bayar', 'net_amount' => 120000, 'paid_amount' => 0,
    ]);

    return [$user, $orangTua, $siswa, $tagihan];
}

it('creates a VA payment and redirects to the waiting page', function () {
    [$user, , , $tagihan] = actingAsOrangTuaForVaQris();

    $response = $this->actingAs($user)->post(route('keuangan.checkout.va'), [
        'tagihan_ids' => [$tagihan->id],
    ]);

    $pembayaran = Pembayaran::where('metode', 'va_bri')->firstOrFail();
    $response->assertRedirect(route('keuangan.checkout.show', $pembayaran));
    expect($pembayaran->briVirtualAccount)->not->toBeNull();
});

it('creates a QRIS payment and redirects to the waiting page', function () {
    [$user, , , $tagihan] = actingAsOrangTuaForVaQris();

    $response = $this->actingAs($user)->post(route('keuangan.checkout.qris'), [
        'tagihan_ids' => [$tagihan->id],
    ]);

    $pembayaran = Pembayaran::where('metode', 'qris')->firstOrFail();
    $response->assertRedirect(route('keuangan.checkout.show', $pembayaran));
    expect($pembayaran->briQrisPayment)->not->toBeNull();
});

it('does not create a second VA for the same tagihan while one is still waiting', function () {
    [$user, , , $tagihan] = actingAsOrangTuaForVaQris();

    $this->actingAs($user)->post(route('keuangan.checkout.va'), ['tagihan_ids' => [$tagihan->id]]);
    $this->actingAs($user)->post(route('keuangan.checkout.va'), ['tagihan_ids' => [$tagihan->id]]);

    expect(Pembayaran::where('metode', 'va_bri')->count())->toBe(1);
});

it('does not create a second QRIS for the same tagihan while one is still waiting', function () {
    [$user, , , $tagihan] = actingAsOrangTuaForVaQris();

    $this->actingAs($user)->post(route('keuangan.checkout.qris'), ['tagihan_ids' => [$tagihan->id]]);
    $this->actingAs($user)->post(route('keuangan.checkout.qris'), ['tagihan_ids' => [$tagihan->id]]);

    expect(Pembayaran::where('metode', 'qris')->count())->toBe(1);
});

it('creates a new VA when selection expands beyond an existing pending VA set', function () {
    [$user, , $siswa, $tagihanA] = actingAsOrangTuaForVaQris();
    $jenis = JenisTagihan::factory()->create();
    $tagihanB = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'belum_bayar', 'net_amount' => 75000, 'paid_amount' => 0,
    ]);

    $this->actingAs($user)->post(route('keuangan.checkout.va'), ['tagihan_ids' => [$tagihanA->id]]);
    $firstVa = Pembayaran::where('metode', 'va_bri')->firstOrFail();

    $this->actingAs($user)->post(route('keuangan.checkout.va'), [
        'tagihan_ids' => [$tagihanA->id, $tagihanB->id],
    ]);

    expect(Pembayaran::where('metode', 'va_bri')->count())->toBe(2);

    $secondVa = Pembayaran::where('metode', 'va_bri')->where('id', '!=', $firstVa->id)->firstOrFail();
    $coveredTagihanIds = $secondVa->pembayaranTagihan()->pluck('tagihan_id')->sort()->values()->all();
    expect($coveredTagihanIds)->toBe(collect([$tagihanA->id, $tagihanB->id])->sort()->values()->all());
});

it('rejects tagihan_ids that do not belong to the active child', function () {
    [$user] = actingAsOrangTuaForVaQris();
    $otherSiswa = Siswa::factory()->create();
    $jenis = JenisTagihan::factory()->create();
    $foreignTagihan = Tagihan::factory()->create([
        'tagihable_id' => $otherSiswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'belum_bayar', 'net_amount' => 999999, 'paid_amount' => 0,
    ]);

    $this->actingAs($user)->post(route('keuangan.checkout.va'), ['tagihan_ids' => [$foreignTagihan->id]]);

    expect(Pembayaran::where('metode', 'va_bri')->count())->toBe(0);
});

it('shows the waiting page with the VA number', function () {
    [$user, , , $tagihan] = actingAsOrangTuaForVaQris();
    $this->actingAs($user)->post(route('keuangan.checkout.va'), ['tagihan_ids' => [$tagihan->id]]);
    $pembayaran = Pembayaran::where('metode', 'va_bri')->firstOrFail();

    $response = $this->actingAs($user)->get(route('keuangan.checkout.show', $pembayaran));

    $response->assertOk();
    $response->assertSee($pembayaran->briVirtualAccount->va_number);
});

it('blocks viewing a pembayaran belonging to another parent\'s child', function () {
    [$user, , , $tagihan] = actingAsOrangTuaForVaQris();
    $this->actingAs($user)->post(route('keuangan.checkout.va'), ['tagihan_ids' => [$tagihan->id]]);
    $pembayaran = Pembayaran::where('metode', 'va_bri')->firstOrFail();

    [$otherUser] = actingAsOrangTuaForVaQris();

    $response = $this->actingAs($otherUser)->get(route('keuangan.checkout.show', $pembayaran));

    $response->assertForbidden();
});

it('returns the payment status as json for polling', function () {
    [$user, , , $tagihan] = actingAsOrangTuaForVaQris();
    $this->actingAs($user)->post(route('keuangan.checkout.va'), ['tagihan_ids' => [$tagihan->id]]);
    $pembayaran = Pembayaran::where('metode', 'va_bri')->firstOrFail();

    $response = $this->actingAs($user)->getJson(route('keuangan.checkout.status', $pembayaran));

    $response->assertOk();
    $response->assertJson(['status' => 'menunggu_pembayaran']);
});
