<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Models\PembayaranTagihan;
use App\Models\Siswa;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeParentWithLunasPembayaran(string $label): array
{
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => "Anak {$label}"]);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => "Ortu {$label}",
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '0812'.random_int(10000000, 99999999),
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $jenis = JenisTagihan::factory()->create(['nama' => "Jenis Tagihan {$label}"]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'lunas', 'net_amount' => 100000, 'paid_amount' => 100000,
    ]);
    $pembayaran = Pembayaran::create([
        'siswa_id' => $siswa->id, 'metode' => 'wallet_saldo', 'status' => 'lunas',
        'channel_reference' => (string) \Illuminate\Support\Str::uuid(),
    ]);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => 100000]);

    return [$user, $orangTua, $siswa, $pembayaran, $jenis];
}

it('does not show another parent\'s payment history entries', function () {
    [$userA, , , , $jenisA] = makeParentWithLunasPembayaran('A');
    [, , , , $jenisB] = makeParentWithLunasPembayaran('B');

    $response = $this->actingAs($userA)->get(route('keuangan.riwayat.index'));

    $response->assertOk();
    $response->assertSee($jenisA->nama);
    $response->assertDontSee($jenisB->nama);
});

it('blocks downloading another parent child\'s kwitansi', function () {
    [, , , $pembayaranA] = makeParentWithLunasPembayaran('A');
    [$userB] = makeParentWithLunasPembayaran('B');

    $response = $this->actingAs($userB)->get(route('keuangan.riwayat.kwitansi', $pembayaranA));

    $response->assertForbidden();
});

it('blocks downloading another parent child\'s kwitansi even without an active child selected', function () {
    [, , , $pembayaranA] = makeParentWithLunasPembayaran('A');

    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');
    $userC = User::factory()->create(['lembaga_id' => null]);
    $userC->assignRole('orang_tua');

    $response = $this->actingAs($userC)->get(route('keuangan.riwayat.kwitansi', $pembayaranA));

    $response->assertForbidden();
});
