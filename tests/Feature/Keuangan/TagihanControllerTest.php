<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\SystemSetting;
use App\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAsOrangTuaForTagihan(): array
{
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Anak Tagihan']);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Tagihan',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200004444',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    return [$user, $orangTua, $siswa, $lembaga];
}

it('lists only belum_bayar and sebagian tagihan for the active child, ordered by jatuh_tempo', function () {
    [$user, , $siswa] = actingAsOrangTuaForTagihan();

    $jenis = JenisTagihan::factory()->create(['nama' => 'SPP Bulanan']);

    $near = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'belum_bayar', 'net_amount' => 100000, 'paid_amount' => 0, 'jatuh_tempo' => now()->addDays(2),
    ]);
    $far = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'sebagian', 'net_amount' => 200000, 'paid_amount' => 50000, 'jatuh_tempo' => now()->addDays(10),
    ]);
    Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'lunas', 'net_amount' => 50000, 'paid_amount' => 50000, 'jatuh_tempo' => now()->addDays(1),
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.tagihan.index'));

    $response->assertOk();
    $response->assertViewHas('tagihans', function ($tagihans) use ($near, $far) {
        return $tagihans->pluck('id')->all() === [$near->id, $far->id];
    });
});

it('shows the auto-debit banner only when the setting is enabled for the lembaga', function () {
    [$user, , $siswa, $lembaga] = actingAsOrangTuaForTagihan();

    SystemSetting::create(['lembaga_id' => $lembaga->id, 'key' => 'auto_debit_enabled', 'value' => true]);

    $response = $this->actingAs($user)->get(route('keuangan.tagihan.index'));

    $response->assertOk();
    $response->assertSee('Sistem Auto-Debit Aktif');
});

it('denies access without keuangan.akses permission', function () {
    $user = User::factory()->create(['lembaga_id' => null]);

    $response = $this->actingAs($user)->get(route('keuangan.tagihan.index'));

    $response->assertForbidden();
});
