<?php

// tests/Feature/Admin/SiswaOrangTuaLinkingTest.php

use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;

function buatSiswaUntukTautan(): Siswa
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    return Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
}

// `actingAsOrangTuaManager()` (defined in OrangTuaCrudTest.php) grants an
// `admin_akademik` role scoped to 'lembaga', but the manager it creates has
// no `lembaga_id`. Siswa uses the BelongsToTenant trait / TenantScope, which
// filters every query by the acting user's lembaga_id — so with a null
// lembaga_id, route-model-binding on {siswa} never resolves (404) regardless
// of controller logic. Widening the manager to a 'yayasan'-scoped role
// bypasses the per-lembaga filter (TenantScope only constrains yayasan-level
// users when `active_lembaga_id` is set in session), which also matches
// test 7's expectation that this manager can link the same orang tua across
// siswa in different lembaga.
function actingAsSiswaOrangTuaManager(): User
{
    $manager = actingAsOrangTuaManager();
    $yayasanRole = Role::firstOrCreate(
        ['name' => 'yayasan_admin_ortu_link', 'guard_name' => 'web'],
        ['scope_level' => 'yayasan']
    );
    $manager->assignRole($yayasanRole);

    return $manager;
}

it('finds an existing orang tua by nik via the cari endpoint', function () {
    $manager = actingAsSiswaOrangTuaManager();
    $siswa = buatSiswaUntukTautan();
    $orangTua = OrangTua::factory()->create(['nik' => '3201234567895555']);
    $orangTua->user->update(['username' => '3201234567895555']);

    $response = $this->actingAs($manager)->getJson(route('admin.siswa.orang-tua.cari', $siswa).'?nik=3201234567895555');

    $response->assertOk()->assertJson([
        'found' => true,
        'orang_tua' => ['id' => $orangTua->id, 'nama_lengkap' => $orangTua->nama_lengkap],
    ]);
});

it('returns found=false when no orang tua is registered under that nik', function () {
    $manager = actingAsSiswaOrangTuaManager();
    $siswa = buatSiswaUntukTautan();

    $response = $this->actingAs($manager)->getJson(route('admin.siswa.orang-tua.cari', $siswa).'?nik=9999999999999999');

    $response->assertOk()->assertJson(['found' => false]);
});

it('links an existing orang tua to a siswa by orang_tua_id', function () {
    $manager = actingAsSiswaOrangTuaManager();
    $siswa = buatSiswaUntukTautan();
    $orangTua = OrangTua::factory()->create();

    $this->actingAs($manager)->post(route('admin.siswa.orang-tua.store', $siswa), [
        'orang_tua_id' => $orangTua->id,
        'hubungan' => 'ibu',
        'is_kontak_utama' => '1',
    ])->assertRedirect(route('admin.siswa.edit', $siswa));

    expect($siswa->orangTua()->count())->toBe(1);
    $pivot = $siswa->orangTua()->first()->pivot;
    expect($pivot->hubungan)->toBe('ibu');
    expect((bool) $pivot->is_kontak_utama)->toBeTrue();
});

it('creates a new orang tua and links it in one submit when orang_tua_id is absent', function () {
    $manager = actingAsSiswaOrangTuaManager();
    $siswa = buatSiswaUntukTautan();

    $this->actingAs($manager)->post(route('admin.siswa.orang-tua.store', $siswa), [
        'hubungan' => 'ayah',
        'is_kontak_utama' => '1',
        'nama_lengkap' => 'Bapak Baru',
        'nik' => '3201234567896666',
        'no_hp' => '081211112222',
    ])->assertRedirect(route('admin.siswa.edit', $siswa));

    $orangTua = OrangTua::where('nik', '3201234567896666')->firstOrFail();
    expect($orangTua->nama_lengkap)->toBe('Bapak Baru');
    expect($siswa->orangTua()->where('orang_tua_id', $orangTua->id)->exists())->toBeTrue();
});

it('rejects linking with a nik that is already registered instead of silently creating a duplicate', function () {
    $manager = actingAsSiswaOrangTuaManager();
    $siswa = buatSiswaUntukTautan();
    $existing = OrangTua::factory()->create(['nik' => '3201234567897777']);
    $existing->user->update(['username' => '3201234567897777']);

    $this->actingAs($manager)->post(route('admin.siswa.orang-tua.store', $siswa), [
        'hubungan' => 'ayah',
        'nama_lengkap' => 'Nama Lain',
        'nik' => '3201234567897777',
        'no_hp' => '081200002222',
    ])->assertSessionHasErrors('nik');

    expect(OrangTua::where('nik', '3201234567897777')->count())->toBe(1);
});

it('sets is_kontak_utama exclusively when linking a second orang tua as the new main contact', function () {
    $manager = actingAsSiswaOrangTuaManager();
    $siswa = buatSiswaUntukTautan();
    $first = OrangTua::factory()->create();
    $siswa->orangTua()->attach($first->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $second = OrangTua::factory()->create();

    $this->actingAs($manager)->post(route('admin.siswa.orang-tua.store', $siswa), [
        'orang_tua_id' => $second->id,
        'hubungan' => 'ibu',
        'is_kontak_utama' => '1',
    ])->assertRedirect();

    $siswa->refresh();
    expect((bool) $siswa->orangTua()->where('orang_tua_id', $first->id)->first()->pivot->is_kontak_utama)->toBeFalse();
    expect((bool) $siswa->orangTua()->where('orang_tua_id', $second->id)->first()->pivot->is_kontak_utama)->toBeTrue();
});

it('allows the same orang tua to link to a siswa in a different lembaga', function () {
    $manager = actingAsSiswaOrangTuaManager();
    $siswaA = buatSiswaUntukTautan();
    $siswaB = buatSiswaUntukTautan();
    $orangTua = OrangTua::factory()->create();

    $this->actingAs($manager)->post(route('admin.siswa.orang-tua.store', $siswaA), [
        'orang_tua_id' => $orangTua->id, 'hubungan' => 'ayah', 'is_kontak_utama' => '1',
    ])->assertRedirect();
    $this->actingAs($manager)->post(route('admin.siswa.orang-tua.store', $siswaB), [
        'orang_tua_id' => $orangTua->id, 'hubungan' => 'wali', 'is_kontak_utama' => '1',
    ])->assertRedirect();

    expect($orangTua->siswa()->count())->toBe(2);
});
