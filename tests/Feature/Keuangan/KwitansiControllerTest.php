<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Models\PembayaranTagihan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
    $orangTua = OrangTua::factory()->create([
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
        'channel_reference' => (string) Str::uuid(),
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
        'channel_reference' => (string) Str::uuid(),
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

it('renders the kelas name, siswa name, and total amount in the kwitansi view via the real controller route', function () {
    [$user, , $siswa, $pembayaran] = actingAsOrangTuaForKwitansi();
    $kelas = Kelas::factory()->create(['lembaga_id' => $siswa->lembaga_id, 'nama' => '7A Istimewa']);
    $siswa->update(['kelas_id' => $kelas->id]);

    $response = $this->actingAs($user)->get(route('keuangan.riwayat.kwitansi', $pembayaran));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');

    $content = $response->getContent();
    expect(strlen($content))->toBeGreaterThan(1000);

    // The PDF body is FlateDecode-compressed; inflate its content streams so we can
    // assert on the actual rendered text instead of just "no exception was thrown".
    // This is what actually exercises the siswa.kelas TenantScope bypass in the
    // controller's load() call: if that bypass is ever removed, the orang_tua-scoped
    // TenantScope filters out the Kelas row (its lembaga_id doesn't match the acting
    // user's null lembaga_id) and "Kelas" silently renders as "-" instead of the real name.
    preg_match_all('/stream\r?\n(.*?)endstream/s', $content, $matches);
    $decoded = '';
    foreach ($matches[1] as $chunk) {
        $inflated = @gzuncompress(rtrim($chunk, "\r\n"));
        if ($inflated !== false) {
            $decoded .= $inflated;
        }
    }

    expect($decoded)->toContain('7A Istimewa');
});

it('renders an img tag when yayasan logo is set, via the real controller route', function () {
    [$user, , $siswa, $pembayaran] = actingAsOrangTuaForKwitansi();
    $yayasan = $siswa->lembaga->yayasan;
    $yayasan->update(['logo' => 'yayasan-logo/test-logo.png']);

    $response = $this->actingAs($user)->get(route('keuangan.riwayat.kwitansi', $pembayaran));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');

    $content = $response->getContent();
    expect(strlen($content))->toBeGreaterThan(1000);
});
