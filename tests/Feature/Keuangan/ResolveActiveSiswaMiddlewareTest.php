<?php

// tests/Feature/Keuangan/ResolveActiveSiswaMiddlewareTest.php

use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware(['web', 'auth', 'permission:keuangan.akses', 'resolve.active.siswa'])
        ->get('/keuangan/__test-probe', function (Request $request) {
            $activeSiswa = $request->attributes->get('activeSiswa');

            return response($activeSiswa?->id !== null ? (string) $activeSiswa->id : 'none');
        });
});

function actingAsOrangTuaWithChildren(array $children = ['utama' => true]): array
{
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');
    $orangTua = OrangTua::factory()->create([
        'user_id' => $user->id, 'nama_lengkap' => 'Ortu Test',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200002222',
    ]);

    $siswaList = [];
    foreach ($children as $key => $isKontakUtama) {
        $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
        $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'is_kontak_utama' => $isKontakUtama]);
        $siswaList[] = $siswa;
    }

    return [$user, $orangTua, $siswaList];
}

it('defaults active siswa to the kontak utama child', function () {
    [$user, , $siswaList] = actingAsOrangTuaWithChildren(['utama' => true, 'lainnya' => false]);
    [$kontakUtama] = $siswaList;

    $response = $this->actingAs($user)->get('/keuangan/__test-probe');

    $response->assertContent((string) $kontakUtama->id);
    expect(session('active_siswa_id'))->toBeNull(); // resolved per-request, not persisted until switched
});

it('lets a valid switch_siswa update the session', function () {
    [$user, , $siswaList] = actingAsOrangTuaWithChildren(['satu' => true, 'dua' => false]);
    [, $keduaAnak] = $siswaList;

    $this->actingAs($user)->get('/keuangan/__test-probe?switch_siswa='.$keduaAnak->id);

    expect(session('active_siswa_id'))->toBe($keduaAnak->id);
});

it('silently ignores a switch_siswa id that does not belong to this orang tua', function () {
    [$user] = actingAsOrangTuaWithChildren(['punya' => true]);
    $strangerSiswa = Siswa::factory()->create();

    $this->actingAs($user)->get('/keuangan/__test-probe?switch_siswa='.$strangerSiswa->id);

    expect(session('active_siswa_id'))->toBeNull();
});

it('aborts 403 for an orang_tua-role user with no linked OrangTua profile', function () {
    Permission::firstOrCreate(['name' => 'keuangan.akses', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('keuangan.akses');
    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('orang_tua');

    $response = $this->actingAs($user)->get('/keuangan/__test-probe');

    $response->assertForbidden();
});
