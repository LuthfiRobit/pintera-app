<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\SystemSetting;
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
    $orangTua = OrangTua::factory()->create([
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

it('shows a Sedang Ditinjau badge and disables the checkbox for a flagged tagihan', function () {
    [$user, , $siswa] = actingAsOrangTuaForTagihan();
    $jenis = JenisTagihan::factory()->create(['nama' => 'SPP Bulanan']);

    Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'belum_bayar', 'net_amount' => 100000, 'paid_amount' => 0,
        'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'contoh alasan internal',
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.tagihan.index'));

    $response->assertOk();
    $response->assertSee('sedang ditinjau', false);
    $response->assertSee(':disabled="item.perlu_ditinjau_ulang"', false);
    // Alasan internal admin tidak boleh bocor ke orang tua -- cuma pesan umum yang ditampilkan.
    $response->assertDontSee('contoh alasan internal');
});

it('shows the breakdown detail of a tagihan owned by the acting orang tua', function () {
    [$user, , $siswa] = actingAsOrangTuaForTagihan();
    $jenis = JenisTagihan::factory()->create(['nama' => 'SPP Bulanan']);
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'sebagian', 'total_tagihan' => 300000, 'discount_amount' => 50000, 'discount_type' => 'fixed',
        'net_amount' => 250000, 'paid_amount' => 100000,
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.tagihan.show', $tagihan));

    $response->assertOk();
    $response->assertSee('300.000', false);
    $response->assertSee('50.000', false);
    $response->assertSee('250.000', false);
    $response->assertSee('Potongan Tetap', false);
});

it('hides the potongan row when discount_amount is zero', function () {
    [$user, , $siswa] = actingAsOrangTuaForTagihan();
    $jenis = JenisTagihan::factory()->create(['nama' => 'SPP Bulanan']);
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'belum_bayar', 'total_tagihan' => 200000, 'discount_amount' => 0, 'discount_type' => null,
        'net_amount' => 200000, 'paid_amount' => 0,
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.tagihan.show', $tagihan));

    $response->assertOk();
    $response->assertDontSee('Potongan Tetap', false);
    $response->assertDontSee('Potongan Persentase', false);
    $response->assertDontSee('Potongan Gabungan', false);
});

it('shows the review banner without leaking alasan_perlu_ditinjau on the detail page', function () {
    [$user, , $siswa] = actingAsOrangTuaForTagihan();
    $jenis = JenisTagihan::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'belum_bayar', 'net_amount' => 100000, 'paid_amount' => 0,
        'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'contoh alasan internal',
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.tagihan.show', $tagihan));

    $response->assertOk();
    $response->assertSee('Nominal sedang ditinjau ulang oleh admin, sementara belum bisa dibayar.', false);
    $response->assertDontSee('contoh alasan internal');
});

it('allows viewing a tagihan for a non-active child of the same orang tua', function () {
    [$user, $orangTua] = actingAsOrangTuaForTagihan();
    $lembaga = Lembaga::factory()->create();
    $anakLain = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTua->siswa()->attach($anakLain->id, ['hubungan' => 'ayah', 'is_kontak_utama' => false]);
    $jenis = JenisTagihan::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $anakLain->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'belum_bayar', 'net_amount' => 100000, 'paid_amount' => 0,
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.tagihan.show', $tagihan));

    $response->assertOk();
});

it('rejects viewing a tagihan belonging to a different orang tua entirely', function () {
    [$user] = actingAsOrangTuaForTagihan();
    $otherSiswa = Siswa::factory()->create();
    $jenis = JenisTagihan::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $otherSiswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'belum_bayar', 'net_amount' => 100000, 'paid_amount' => 0,
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.tagihan.show', $tagihan));

    $response->assertForbidden();
});

it('denies access without keuangan.akses permission', function () {
    $user = User::factory()->create(['lembaga_id' => null]);

    $response = $this->actingAs($user)->get(route('keuangan.tagihan.index'));

    $response->assertForbidden();
});
