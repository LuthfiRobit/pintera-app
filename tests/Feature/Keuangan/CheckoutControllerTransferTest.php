<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Pembayaran;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAsOrangTuaForTransfer(): array
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
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Transfer',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200008888',
    ]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $jenis = JenisTagihan::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'belum_bayar', 'net_amount' => 80000, 'paid_amount' => 0,
    ]);

    return [$user, $orangTua, $siswa, $tagihan];
}

it('submits a manual transfer proof and redirects to the verification-pending page', function () {
    Storage::fake('public');
    [$user, , , $tagihan] = actingAsOrangTuaForTransfer();

    $response = $this->actingAs($user)->post(route('keuangan.checkout.transfer'), [
        'tagihan_ids' => [$tagihan->id],
        'bank_origin' => 'BCA',
        'transfer_date' => now()->toDateString(),
        'transfer_proof' => UploadedFile::fake()->image('bukti.jpg'),
    ]);

    $pembayaran = Pembayaran::where('metode', 'transfer_manual')->firstOrFail();
    $response->assertRedirect(route('keuangan.checkout.menunggu-verifikasi', $pembayaran));
    $this->assertEquals('menunggu_verifikasi', $pembayaran->status);
    $this->assertNotNull($pembayaran->manualRequest);
    Storage::disk('public')->assertExists($pembayaran->manualRequest->transfer_proof_path);
});

it('requires a transfer proof file', function () {
    [$user, , , $tagihan] = actingAsOrangTuaForTransfer();

    $response = $this->actingAs($user)->post(route('keuangan.checkout.transfer'), [
        'tagihan_ids' => [$tagihan->id],
        'transfer_date' => now()->toDateString(),
    ]);

    $response->assertSessionHasErrors('transfer_proof');
});

it('rejects a transfer proof larger than 2MB', function () {
    Storage::fake('public');
    [$user, , , $tagihan] = actingAsOrangTuaForTransfer();

    $response = $this->actingAs($user)->post(route('keuangan.checkout.transfer'), [
        'tagihan_ids' => [$tagihan->id],
        'transfer_date' => now()->toDateString(),
        'transfer_proof' => UploadedFile::fake()->create('bukti.pdf', 3000, 'application/pdf'),
    ]);

    $response->assertSessionHasErrors('transfer_proof');
});

it('shows the verification-pending page', function () {
    Storage::fake('public');
    [$user, , , $tagihan] = actingAsOrangTuaForTransfer();
    $this->actingAs($user)->post(route('keuangan.checkout.transfer'), [
        'tagihan_ids' => [$tagihan->id],
        'bank_origin' => 'BCA',
        'transfer_date' => now()->toDateString(),
        'transfer_proof' => UploadedFile::fake()->image('bukti.jpg'),
    ]);
    $pembayaran = Pembayaran::where('metode', 'transfer_manual')->firstOrFail();

    $response = $this->actingAs($user)->get(route('keuangan.checkout.menunggu-verifikasi', $pembayaran));

    $response->assertOk();
    $response->assertSee('Menunggu Verifikasi', false);
});
