<?php

use App\Domains\Akademik\Models\PiketHarian;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function siapkanAdminPiketHarianTest(): array
{
    Permission::firstOrCreate(['name' => 'piket.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_piket_harian_test', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('piket.kelola');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guruLembagaLain = Guru::factory()->create(['lembaga_id' => $lembagaLain->id]);

    return compact('lembaga', 'guru', 'admin', 'lembagaLain', 'guruLembagaLain');
}

it('admin bisa buat override manual PiketHarian', function () {
    ['lembaga' => $lembaga, 'guru' => $guru, 'admin' => $admin] = siapkanAdminPiketHarianTest();

    $response = $this->actingAs($admin)->post(route('admin.piket-harian.store'), [
        'guru_id' => $guru->id, 'tanggal' => now()->addDays(3)->toDateString(),
    ]);

    $response->assertRedirect();
    expect(PiketHarian::where('lembaga_id', $lembaga->id)->where('guru_id', $guru->id)->where('sumber', 'override_manual')->exists())->toBeTrue();
});

it('admin lembaga A TIDAK BISA buat override manual utk guru lembaga B', function () {
    ['admin' => $admin, 'guruLembagaLain' => $guruLembagaLain] = siapkanAdminPiketHarianTest();

    $response = $this->actingAs($admin)->post(route('admin.piket-harian.store'), [
        'guru_id' => $guruLembagaLain->id, 'tanggal' => now()->addDays(3)->toDateString(),
    ]);

    $response->assertSessionHasErrors();
    expect(PiketHarian::where('guru_id', $guruLembagaLain->id)->exists())->toBeFalse();
});

it('admin bisa hapus baris PiketHarian', function () {
    ['lembaga' => $lembaga, 'guru' => $guru, 'admin' => $admin] = siapkanAdminPiketHarianTest();
    $piket = PiketHarian::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'tanggal' => now()->addDays(3)->toDateString(), 'sumber' => 'override_manual']);

    $response = $this->actingAs($admin)->delete(route('admin.piket-harian.destroy', $piket));

    $response->assertRedirect();
    expect(PiketHarian::find($piket->id))->toBeNull();
});

it('admin yayasan pada mode agregat bisa menghapus baris override lembaga miliknya', function () {
    Permission::firstOrCreate(['name' => 'piket.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'pengurus_yayasan_piket_harian_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo('piket.kelola');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $adminYayasan = User::factory()->create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null]);
    $adminYayasan->assignRole($role);

    $piket = PiketHarian::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'tanggal' => now()->addDays(3)->toDateString(), 'sumber' => 'override_manual']);

    $response = $this->actingAs($adminYayasan)
        ->withSession(['active_lembaga_id' => null])
        ->delete(route('admin.piket-harian.destroy', $piket));

    $response->assertRedirect();
    expect(PiketHarian::find($piket->id))->toBeNull();
});

