<?php

use App\Domains\Keuangan\Models\BriVirtualAccount;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\User;
use App\Domains\Keuangan\Models\Wallet;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function buatAdminDanSiswaUntukVirtualAccount(string $label): array
{
    Permission::firstOrCreate(['name' => 'pembayaran.virtual-account', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_keuangan', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('pembayaran.virtual-account');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('admin_keuangan');

    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => "Anak {$label}", 'status' => 'aktif']);
    $wallet = $siswa->wallet ?? Wallet::create(['siswa_id' => $siswa->id, 'balance' => 50000]);
    $va = BriVirtualAccount::create([
        'wallet_id' => $wallet->id,
        'va_type' => 'WALLET_PERMANENT',
        'va_number' => '8808'.str_pad((string) $siswa->id, 16, '0', STR_PAD_LEFT),
        'status' => 'PERMANENT',
    ]);

    return [$admin, $lembaga, $siswa, $va];
}

it('does not show another lembaga student in the index listing', function () {
    [$adminA, , $siswaA] = buatAdminDanSiswaUntukVirtualAccount('A');
    [, , $siswaB] = buatAdminDanSiswaUntukVirtualAccount('B');

    $response = $this->actingAs($adminA)->get(route('admin.virtual-account.index'));

    $response->assertOk();
    $response->assertSee($siswaA->nama_lengkap);
    $response->assertDontSee($siswaB->nama_lengkap);
});

it('blocks viewing riwayat for another lembaga student', function () {
    [$adminA] = buatAdminDanSiswaUntukVirtualAccount('A');
    [, , $siswaB] = buatAdminDanSiswaUntukVirtualAccount('B');

    $this->actingAs($adminA)->get(route('admin.virtual-account.riwayat', $siswaB))->assertNotFound();
});

it('does not include another lembaga student in calon-generate results', function () {
    [$adminA, $lembagaA] = buatAdminDanSiswaUntukVirtualAccount('A');
    $siswaCalonLembagaLain = Siswa::factory()->create([
        'lembaga_id' => Lembaga::factory()->create(['yayasan_id' => $lembagaA->yayasan_id])->id,
        'nama_lengkap' => 'Calon Lembaga Lain',
        'status' => 'aktif',
    ]);

    $response = $this->actingAs($adminA)->getJson(route('admin.virtual-account.calon'));

    $names = collect($response->json('data'))->pluck('nama_lengkap');
    expect($names)->not->toContain('Calon Lembaga Lain');
});

it('does not generate VA for another lembaga student even if their id is passed in manual mode', function () {
    [$adminA, $lembagaA] = buatAdminDanSiswaUntukVirtualAccount('A');
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $lembagaA->yayasan_id]);
    $siswaB = Siswa::factory()->create(['lembaga_id' => $lembagaB->id, 'status' => 'aktif']);

    $this->actingAs($adminA)->post(route('admin.virtual-account.generate'), [
        'mode' => 'manual',
        'siswa_ids' => [$siswaB->id],
    ]);

    expect(BriVirtualAccount::whereHas('wallet', fn ($q) => $q->where('siswa_id', $siswaB->id))->exists())->toBeFalse();
});

it('only exports the acting admin own lembaga students', function () {
    [$adminA, , $siswaA] = buatAdminDanSiswaUntukVirtualAccount('A');
    [, , $siswaB] = buatAdminDanSiswaUntukVirtualAccount('B');

    $response = $this->actingAs($adminA)->get(route('admin.virtual-account.export'));

    $response->assertOk();
    // Excel content is binary/zipped — assert via the underlying export class directly instead of parsing the response body.
    $export = new \App\Exports\VirtualAccountExport($adminA->lembaga_id);
    $rows = $export->collection();
    expect($rows->pluck(0))->toContain($siswaA->nama_lengkap);
    expect($rows->pluck(0))->not->toContain($siswaB->nama_lengkap);
});
