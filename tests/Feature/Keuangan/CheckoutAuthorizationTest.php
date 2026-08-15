<?php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Pembayaran;
use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeParentWithChild(string $label, float $walletBalance = 0): array
{
    config(['services.bri.gateway' => 'mock']);
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => "Anak {$label}"]);
    $siswa->wallet->update(['balance' => $walletBalance]);

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
        'status' => 'belum_bayar', 'net_amount' => 100000, 'paid_amount' => 0,
    ]);

    return [$user, $orangTua, $siswa, $tagihan];
}

it('does not show another parent\'s tagihan in the rekap tagihan list', function () {
    [$userA, , , $tagihanA] = makeParentWithChild('A');
    [, , , $tagihanB] = makeParentWithChild('B');

    $response = $this->actingAs($userA)->get(route('keuangan.tagihan.index'));

    // JenisTagihan carries a TenantScope keyed on the acting user's lembaga_id, which is
    // null for a parent (parents aren't tied to a lembaga directly). Reading the relation
    // here the same way the test's own actingAs($userA) session would apply the scope, so
    // bypass it explicitly to fetch the name for the assertion -- this mirrors how
    // TagihanController@index itself eager-loads jenisTagihan withoutGlobalScope.
    $namaJenisA = JenisTagihan::withoutGlobalScope(TenantScope::class)->find($tagihanA->jenis_tagihan_id)->nama;
    $namaJenisB = JenisTagihan::withoutGlobalScope(TenantScope::class)->find($tagihanB->jenis_tagihan_id)->nama;

    $response->assertOk();
    $response->assertSee($namaJenisA);
    $response->assertDontSee($namaJenisB);
});

it('rejects wallet checkout for a tagihan belonging to another parent\'s child', function () {
    [$userA] = makeParentWithChild('A', walletBalance: 500000);
    [, , , $tagihanB] = makeParentWithChild('B');

    $this->actingAs($userA)->post(route('keuangan.checkout.wallet'), ['tagihan_ids' => [$tagihanB->id]]);

    // PaymentService::createPembayaranRecord() stamps siswa_id from the acting parent's
    // own siswa (siswaA), never from the tagihan being paid -- so a leaked payment here
    // would NOT have siswa_id === tagihanB->tagihable_id. Assert on the total count and
    // on tagihanB's own status remaining untouched instead, so a regression that drops
    // the ownership check in resolveSelectedTagihan() is actually caught.
    $this->assertEquals(0, Pembayaran::count());
    $this->assertEquals('belum_bayar', $tagihanB->fresh()->status);
});

it('rejects manual transfer checkout for a tagihan belonging to another parent\'s child', function () {
    Illuminate\Support\Facades\Storage::fake('public');
    [$userA] = makeParentWithChild('A');
    [, , , $tagihanB] = makeParentWithChild('B');

    $response = $this->actingAs($userA)->post(route('keuangan.checkout.transfer'), [
        'tagihan_ids' => [$tagihanB->id],
        'bank_origin' => 'BCA',
        'transfer_date' => now()->toDateString(),
        'transfer_proof' => Illuminate\Http\UploadedFile::fake()->image('bukti.jpg'),
    ]);

    $response->assertSessionHasErrors('tagihan_ids');
    $this->assertEquals(0, Pembayaran::count());
});

it('blocks a parent from polling the status of another parent\'s pembayaran', function () {
    [$userA, , , $tagihanA] = makeParentWithChild('A');
    $this->actingAs($userA)->post(route('keuangan.checkout.qris'), ['tagihan_ids' => [$tagihanA->id]]);
    $pembayaranA = Pembayaran::where('metode', 'qris')->firstOrFail();

    [$userB] = makeParentWithChild('B');

    $response = $this->actingAs($userB)->getJson(route('keuangan.checkout.status', $pembayaranA));

    $response->assertForbidden();
});

it('blocks a parent from viewing another parent\'s wallet success page', function () {
    [$userA, , , $tagihanA] = makeParentWithChild('A', walletBalance: 500000);
    $this->actingAs($userA)->post(route('keuangan.checkout.wallet'), ['tagihan_ids' => [$tagihanA->id]]);
    $pembayaranA = Pembayaran::where('metode', 'wallet_saldo')->firstOrFail();

    [$userB] = makeParentWithChild('B');

    $response = $this->actingAs($userB)->get(route('keuangan.checkout.sukses', $pembayaranA));

    $response->assertForbidden();
});

it('blocks a parent from viewing another parent\'s qris checkout page', function () {
    [$userA, , , $tagihanA] = makeParentWithChild('A');
    $this->actingAs($userA)->post(route('keuangan.checkout.qris'), ['tagihan_ids' => [$tagihanA->id]]);
    $pembayaranA = Pembayaran::where('metode', 'qris')->firstOrFail();

    [$userB] = makeParentWithChild('B');

    $response = $this->actingAs($userB)->get(route('keuangan.checkout.show', $pembayaranA));

    $response->assertForbidden();
});

it('blocks a parent from viewing another parent\'s menunggu-verifikasi page', function () {
    Illuminate\Support\Facades\Storage::fake('public');
    [$userA, , , $tagihanA] = makeParentWithChild('A');
    $this->actingAs($userA)->post(route('keuangan.checkout.transfer'), [
        'tagihan_ids' => [$tagihanA->id],
        'bank_origin' => 'BCA',
        'transfer_date' => now()->toDateString(),
        'transfer_proof' => Illuminate\Http\UploadedFile::fake()->image('bukti.jpg'),
    ]);
    $pembayaranA = Pembayaran::where('metode', 'transfer_manual')->firstOrFail();

    [$userB] = makeParentWithChild('B');

    $response = $this->actingAs($userB)->get(route('keuangan.checkout.menunggu-verifikasi', $pembayaranA));

    $response->assertForbidden();
});
