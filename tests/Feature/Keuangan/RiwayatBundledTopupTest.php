<?php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Pembayaran;
use App\Models\PembayaranTagihan;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('labels a bundled payment row as tagihan plus top-up in the riwayat list', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Riwayat Bundling',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200004444',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $jenis = JenisTagihan::factory()->create(['nama' => 'SPP Riwayat Bundling']);
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'lunas', 'net_amount' => 100000, 'paid_amount' => 100000,
    ]);
    $pembayaran = Pembayaran::create([
        'siswa_id' => $siswa->id, 'metode' => 'va_bri', 'status' => 'lunas',
        'amount' => 150000, 'topup_status' => 'completed',
        'channel_reference' => (string) \Illuminate\Support\Str::uuid(),
    ]);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => 100000]);

    session(['active_siswa_id' => $siswa->id]);

    $response = $this->actingAs($user)->get(route('keuangan.riwayat.index'));

    $response->assertOk();
    $response->assertSee('SPP Riwayat Bundling');
    $response->assertSee('Top Up Wallet');
    $response->assertSee('150.000');
});
