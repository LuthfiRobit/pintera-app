<?php

use App\Models\JenisTagihan;
use App\Models\Kelas;
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

function actingAsOrangTuaForKwitansi(): array
{
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create(['nama' => 'Yayasan Uji Kwitansi']);
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Anak Kwitansi']);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Kwitansi',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200001111',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $jenis = JenisTagihan::factory()->create(['nama' => 'SPP Bulanan']);
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'lunas', 'net_amount' => 100000, 'paid_amount' => 100000,
    ]);

    $pembayaran = Pembayaran::create([
        'siswa_id' => $siswa->id, 'metode' => 'wallet_saldo', 'status' => 'lunas',
        'channel_reference' => (string) \Illuminate\Support\Str::uuid(),
    ]);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => 100000]);

    return [$user, $orangTua, $siswa, $pembayaran];
}

it('streams a pdf kwitansi for a lunas pembayaran', function () {
    [$user, , , $pembayaran] = actingAsOrangTuaForKwitansi();

    $response = $this->actingAs($user)->get(route('keuangan.riwayat.kwitansi', $pembayaran));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

it('returns 404 for a pembayaran that is not yet lunas', function () {
    [$user, , $siswa] = actingAsOrangTuaForKwitansi();
    $pending = Pembayaran::create([
        'siswa_id' => $siswa->id, 'metode' => 'va_bri', 'status' => 'menunggu_pembayaran',
        'channel_reference' => (string) \Illuminate\Support\Str::uuid(),
    ]);

    $response = $this->actingAs($user)->get(route('keuangan.riwayat.kwitansi', $pending));

    $response->assertNotFound();
});

it('blocks a parent from downloading another parent child\'s kwitansi', function () {
    [, , , $pembayaranA] = actingAsOrangTuaForKwitansi();
    [$userB] = actingAsOrangTuaForKwitansi();

    $response = $this->actingAs($userB)->get(route('keuangan.riwayat.kwitansi', $pembayaranA));

    $response->assertForbidden();
});

it('renders without a logo when yayasan logo is not set', function () {
    [$user, , , $pembayaran] = actingAsOrangTuaForKwitansi();

    $response = $this->actingAs($user)->get(route('keuangan.riwayat.kwitansi', $pembayaran));

    $response->assertOk();
});

it('renders the kelas name, siswa name, and total amount in the kwitansi view', function () {
    [, , $siswa, $pembayaran] = actingAsOrangTuaForKwitansi();
    $kelas = Kelas::factory()->create(['lembaga_id' => $siswa->lembaga_id, 'nama' => '7A Istimewa']);
    $siswa->update(['kelas_id' => $kelas->id]);

    $pembayaran->load([
        'pembayaranTagihan.tagihan.jenisTagihan' => fn ($q) => $q->withoutGlobalScope(\App\Models\Scopes\TenantScope::class),
        'siswa' => fn ($q) => $q->withoutGlobalScope(\App\Models\Scopes\TenantScope::class),
        'siswa.lembaga.yayasan',
        'siswa.kelas' => fn ($q) => $q->withoutGlobalScope(\App\Models\Scopes\TenantScope::class),
    ]);

    $html = view('pdf.kwitansi', [
        'pembayaran' => $pembayaran,
        'siswa' => $pembayaran->siswa,
        'lembaga' => $pembayaran->siswa->lembaga,
        'yayasan' => $pembayaran->siswa->lembaga->yayasan,
    ])->render();

    expect($html)->toContain('7A Istimewa');
    expect($html)->toContain('Anak Kwitansi');
    expect($html)->toContain('100.000');
});

it('renders an img tag when yayasan logo is set', function () {
    [, , $siswa, $pembayaran] = actingAsOrangTuaForKwitansi();
    $yayasan = $siswa->lembaga->yayasan;
    $yayasan->update(['logo' => 'yayasan-logo/test-logo.png']);

    $pembayaran->load([
        'pembayaranTagihan.tagihan.jenisTagihan' => fn ($q) => $q->withoutGlobalScope(\App\Models\Scopes\TenantScope::class),
        'siswa' => fn ($q) => $q->withoutGlobalScope(\App\Models\Scopes\TenantScope::class),
        'siswa.lembaga.yayasan',
        'siswa.kelas' => fn ($q) => $q->withoutGlobalScope(\App\Models\Scopes\TenantScope::class),
    ]);

    $html = view('pdf.kwitansi', [
        'pembayaran' => $pembayaran,
        'siswa' => $pembayaran->siswa,
        'lembaga' => $pembayaran->siswa->lembaga,
        'yayasan' => $pembayaran->siswa->lembaga->yayasan->fresh(),
    ])->render();

    expect($html)->toContain('<img');
    expect($html)->toContain('yayasan-logo/test-logo.png');
});
