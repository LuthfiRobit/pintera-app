<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Models\PembayaranTagihan;
use App\Models\Siswa;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAsOrangTuaForRiwayat(): array
{
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Riwayat',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200009999',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    return [$user, $orangTua, $siswa];
}

function makeLunasPembayaran(Siswa $siswa, string $metode = 'wallet_saldo', ?\Carbon\Carbon $createdAt = null): Pembayaran
{
    $jenis = JenisTagihan::factory()->create(['nama' => 'SPP Bulanan']);
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'lunas', 'net_amount' => 100000, 'paid_amount' => 100000,
    ]);

    $pembayaran = Pembayaran::create([
        'siswa_id' => $siswa->id, 'metode' => $metode, 'status' => 'lunas',
        'channel_reference' => (string) \Illuminate\Support\Str::uuid(),
    ]);
    if ($createdAt !== null) {
        $pembayaran->created_at = $createdAt;
        $pembayaran->save();
    }

    PembayaranTagihan::create([
        'pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => 100000,
    ]);

    return $pembayaran;
}

it('lists the active child payment history ordered newest first', function () {
    [$user, , $siswa] = actingAsOrangTuaForRiwayat();

    $older = makeLunasPembayaran($siswa, createdAt: now()->subDays(5));
    $newer = makeLunasPembayaran($siswa, createdAt: now());

    $response = $this->actingAs($user)->get(route('keuangan.riwayat.index'));

    $response->assertOk();
    $response->assertViewHas('pembayarans', function ($pembayarans) use ($older, $newer) {
        return $pembayarans->pluck('id')->all() === [$newer->id, $older->id];
    });
});

it('filters by metode', function () {
    [$user, , $siswa] = actingAsOrangTuaForRiwayat();

    $wallet = makeLunasPembayaran($siswa, metode: 'wallet_saldo');
    $va = makeLunasPembayaran($siswa, metode: 'va_bri');

    $response = $this->actingAs($user)->get(route('keuangan.riwayat.index', ['metode' => 'wallet_saldo']));

    $response->assertOk();
    $response->assertViewHas('pembayarans', function ($pembayarans) use ($wallet, $va) {
        return $pembayarans->pluck('id')->all() === [$wallet->id] && ! $pembayarans->contains('id', $va->id);
    });
});

it('ignores an invalid date range instead of erroring', function () {
    [$user, , $siswa] = actingAsOrangTuaForRiwayat();
    $pembayaran = makeLunasPembayaran($siswa);

    $response = $this->actingAs($user)->get(route('keuangan.riwayat.index', [
        'dari' => now()->toDateString(),
        'sampai' => now()->subDays(10)->toDateString(),
    ]));

    $response->assertOk();
    $response->assertViewHas('pembayarans', fn ($pembayarans) => $pembayarans->contains('id', $pembayaran->id));
});

it('shows the total amount for each transaction row', function () {
    [$user, , $siswa] = actingAsOrangTuaForRiwayat();

    makeLunasPembayaran($siswa);

    $response = $this->actingAs($user)->get(route('keuangan.riwayat.index'));

    $response->assertOk();
    $response->assertSee('100.000');
});

it('shows the pending amount for a menunggu_pembayaran transaction with no rincian yet', function () {
    [$user, , $siswa] = actingAsOrangTuaForRiwayat();

    Pembayaran::create([
        'siswa_id' => $siswa->id, 'metode' => 'va_bri', 'status' => 'menunggu_pembayaran',
        'channel_reference' => (string) \Illuminate\Support\Str::uuid(), 'amount' => 250000,
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.riwayat.index'));

    $response->assertOk();
    $response->assertSee('250.000');
});

it('filters by a valid full date range including the end-of-day boundary', function () {
    [$user, , $siswa] = actingAsOrangTuaForRiwayat();

    $inRange = makeLunasPembayaran($siswa, createdAt: now()->setTime(23, 59, 0));
    $outOfRange = makeLunasPembayaran($siswa, createdAt: now()->addDays(5));

    $response = $this->actingAs($user)->get(route('keuangan.riwayat.index', [
        'dari' => now()->toDateString(),
        'sampai' => now()->toDateString(),
    ]));

    $response->assertOk();
    $response->assertViewHas('pembayarans', function ($pembayarans) use ($inRange, $outOfRange) {
        return $pembayarans->pluck('id')->all() === [$inRange->id] && ! $pembayarans->contains('id', $outOfRange->id);
    });
});

it('narrows results with a dari-only filter', function () {
    [$user, , $siswa] = actingAsOrangTuaForRiwayat();

    $older = makeLunasPembayaran($siswa, createdAt: now()->subDays(10));
    $newer = makeLunasPembayaran($siswa, createdAt: now());

    $response = $this->actingAs($user)->get(route('keuangan.riwayat.index', [
        'dari' => now()->subDays(1)->toDateString(),
    ]));

    $response->assertOk();
    $response->assertViewHas('pembayarans', function ($pembayarans) use ($older, $newer) {
        return $pembayarans->pluck('id')->all() === [$newer->id] && ! $pembayarans->contains('id', $older->id);
    });
});

it('rejects a malformed dari filter with a validation error instead of a silent query', function () {
    [$user, , $siswa] = actingAsOrangTuaForRiwayat();
    makeLunasPembayaran($siswa);

    $response = $this->actingAs($user)->get(route('keuangan.riwayat.index', ['dari' => 'not-a-date']));

    $response->assertSessionHasErrors('dari');
});

it('shows the kwitansi download link only for lunas rows', function () {
    [$user, , $siswa] = actingAsOrangTuaForRiwayat();

    $lunas = makeLunasPembayaran($siswa);
    $pending = Pembayaran::create([
        'siswa_id' => $siswa->id, 'metode' => 'va_bri', 'status' => 'menunggu_pembayaran',
        'channel_reference' => (string) \Illuminate\Support\Str::uuid(),
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.riwayat.index'));

    $response->assertOk();
    $response->assertSee(route('keuangan.riwayat.kwitansi', $lunas));
    $response->assertDontSee(route('keuangan.riwayat.kwitansi', $pending));
});
