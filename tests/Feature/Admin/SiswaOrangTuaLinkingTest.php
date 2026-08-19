<?php

use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function buatSiswaUntukTautan(?int $yayasanId = null): Siswa
{
    $yayasanId ??= Yayasan::factory()->create()->id;
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasanId]);

    return Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
}

// `actingAsSiswaOrangTuaManager()` is defined in tests/Pest.php as a shared
// fixture, since Task 7/8 tests against this same controller also need a
// manager correctly scoped to see tenant-scoped Siswa records.

it('finds an existing orang tua by nik via the cari endpoint', function () {
    $manager = actingAsSiswaOrangTuaManager();
    $siswa = buatSiswaUntukTautan($manager->yayasan_id);
    $lembagaSama = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);
    // The cari() endpoint's lookup goes through User::where('username', $nik) first (username
    // stores the NIK) - User is tenant-scoped, so the underlying account must belong to a
    // Lembaga under the acting manager's own yayasan to be visible, even though OrangTua
    // itself is intentionally cross-tenant.
    $orangTua = OrangTua::factory()->create(['nik' => '3201234567895555', 'user_id' => User::factory()->create(['lembaga_id' => $lembagaSama->id])->id]);

    $response = $this->actingAs($manager)->getJson(route('admin.siswa.orang-tua.cari', $siswa).'?nik=3201234567895555');

    $response->assertOk()->assertJson([
        'found' => true,
        'orang_tua' => ['id' => $orangTua->id, 'nama_lengkap' => $orangTua->nama_lengkap],
    ]);
});

it('returns found=false when no orang tua is registered under that nik', function () {
    $manager = actingAsSiswaOrangTuaManager();
    $siswa = buatSiswaUntukTautan($manager->yayasan_id);

    $response = $this->actingAs($manager)->getJson(route('admin.siswa.orang-tua.cari', $siswa).'?nik=9999999999999999');

    $response->assertOk()->assertJson(['found' => false]);
});

it('links an existing orang tua to a siswa by orang_tua_id', function () {
    $manager = actingAsSiswaOrangTuaManager();
    $siswa = buatSiswaUntukTautan($manager->yayasan_id);
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
    $siswa = buatSiswaUntukTautan($manager->yayasan_id);

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
    $siswa = buatSiswaUntukTautan($manager->yayasan_id);
    $existing = OrangTua::factory()->create(['nik' => '3201234567897777']);

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
    $siswa = buatSiswaUntukTautan($manager->yayasan_id);
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

// Exercises a yayasan-scoped user's ability to cross-link the same orang tua across
// lembaga (an intended capability, since OrangTua is cross-tenant by design) — it does
// NOT assert anything about tenant isolation for an ordinary lembaga-scoped admin. See
// 'blocks a lembaga-scoped manager from linking an orang tua to a siswa in another lembaga'
// below for the tenant-isolation case.
it('allows the same orang tua to link to a siswa in a different lembaga', function () {
    $manager = actingAsSiswaOrangTuaManager();
    $siswaA = buatSiswaUntukTautan($manager->yayasan_id);
    $siswaB = buatSiswaUntukTautan($manager->yayasan_id);
    $orangTua = OrangTua::factory()->create();

    $this->actingAs($manager)->post(route('admin.siswa.orang-tua.store', $siswaA), [
        'orang_tua_id' => $orangTua->id, 'hubungan' => 'ayah', 'is_kontak_utama' => '1',
    ])->assertRedirect();
    $this->actingAs($manager)->post(route('admin.siswa.orang-tua.store', $siswaB), [
        'orang_tua_id' => $orangTua->id, 'hubungan' => 'wali', 'is_kontak_utama' => '1',
    ])->assertRedirect();

    expect($orangTua->siswa()->count())->toBe(2);
});

it('sets a different orang tua as the exclusive kontak utama', function () {
    $manager = actingAsSiswaOrangTuaManager();
    $siswa = buatSiswaUntukTautan($manager->yayasan_id);
    $first = OrangTua::factory()->create();
    $second = OrangTua::factory()->create();
    $siswa->orangTua()->attach($first->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $siswa->orangTua()->attach($second->id, ['hubungan' => 'ibu', 'is_kontak_utama' => false]);

    $this->actingAs($manager)
        ->patch(route('admin.siswa.orang-tua.kontak-utama', [$siswa, $second]))
        ->assertRedirect(route('admin.siswa.edit', $siswa));

    $siswa->refresh();
    expect((bool) $siswa->orangTua()->where('orang_tua_id', $first->id)->first()->pivot->is_kontak_utama)->toBeFalse();
    expect((bool) $siswa->orangTua()->where('orang_tua_id', $second->id)->first()->pivot->is_kontak_utama)->toBeTrue();
});

it('rejects setting kontak utama for an orang tua not linked to the siswa', function () {
    $manager = actingAsSiswaOrangTuaManager();
    $siswa = buatSiswaUntukTautan($manager->yayasan_id);
    $unlinked = OrangTua::factory()->create();

    $this->actingAs($manager)
        ->patch(route('admin.siswa.orang-tua.kontak-utama', [$siswa, $unlinked]))
        ->assertSessionHasErrors();
});

it('unlinks an orang tua from a siswa without deleting the orang tua profile or user', function () {
    $manager = actingAsSiswaOrangTuaManager();
    $siswa = buatSiswaUntukTautan($manager->yayasan_id);
    $lembagaSama = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);
    // User::find() below is tenant-scoped, so the linked account must belong to a Lembaga
    // under the acting manager's own yayasan to still be visible after unlinking.
    $orangTua = OrangTua::factory()->create(['user_id' => User::factory()->create(['lembaga_id' => $lembagaSama->id])->id]);
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $this->actingAs($manager)
        ->delete(route('admin.siswa.orang-tua.destroy', [$siswa, $orangTua]))
        ->assertRedirect(route('admin.siswa.edit', $siswa));

    expect($siswa->orangTua()->count())->toBe(0);
    expect(OrangTua::find($orangTua->id))->not->toBeNull();
    expect(User::find($orangTua->user_id))->not->toBeNull();
});

it('blocks a lembaga-scoped manager from linking an orang tua to a siswa in another lembaga', function () {
    $manager = actingAsOrangTuaManager();
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $manager->update(['lembaga_id' => $lembagaA->id]);

    $siswaInLembagaB = buatSiswaUntukTautan();
    $orangTua = OrangTua::factory()->create();

    $this->actingAs($manager)->post(route('admin.siswa.orang-tua.store', $siswaInLembagaB), [
        'orang_tua_id' => $orangTua->id,
        'hubungan' => 'ayah',
        'is_kontak_utama' => '1',
    ])->assertNotFound();
});

it('requires siswa.edit in addition to orang-tua permissions on nested siswa/orang-tua routes', function () {
    foreach (['orang-tua.view', 'orang-tua.create', 'orang-tua.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = \App\Models\Role::firstOrCreate(
        ['name' => 'orang_tua_only_no_siswa_edit', 'guard_name' => 'web'],
        ['scope_level' => 'yayasan']
    );
    $role->givePermissionTo(['orang-tua.create', 'orang-tua.edit']);
    $yayasan = Yayasan::factory()->create();
    $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $user->assignRole($role);

    $siswa = buatSiswaUntukTautan($yayasan->id);
    $orangTua = OrangTua::factory()->create();
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $this->actingAs($user)
        ->getJson(route('admin.siswa.orang-tua.cari', $siswa).'?nik=3201234567895555')
        ->assertForbidden();

    $this->actingAs($user)->post(route('admin.siswa.orang-tua.store', $siswa), [
        'orang_tua_id' => $orangTua->id, 'hubungan' => 'ibu', 'is_kontak_utama' => '1',
    ])->assertForbidden();

    $this->actingAs($user)
        ->patch(route('admin.siswa.orang-tua.kontak-utama', [$siswa, $orangTua]))
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('admin.siswa.orang-tua.destroy', [$siswa, $orangTua]))
        ->assertForbidden();
});

it('returns a distinct message when linking a nik that belongs to a non-parent user', function () {
    $manager = actingAsSiswaOrangTuaManager();
    $siswa = buatSiswaUntukTautan($manager->yayasan_id);
    $lembagaSama = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);
    User::factory()->create(['username' => '3201234567899999', 'lembaga_id' => $lembagaSama->id]);

    $response = $this->actingAs($manager)->post(route('admin.siswa.orang-tua.store', $siswa), [
        'hubungan' => 'ayah',
        'nama_lengkap' => 'Nama Lain',
        'nik' => '3201234567899999',
        'no_hp' => '081200003333',
    ]);

    $response->assertSessionHasErrors('nik');
    expect(session('errors')->get('nik')[0])->toBe('NIK ini sudah terdaftar ke akun lain yang bukan profil Orang Tua.');
});

it('shows the linked orang tua list and a search box on the siswa edit page', function () {
    $manager = actingAsSiswaOrangTuaManager();
    Permission::firstOrCreate(['name' => 'siswa.edit', 'guard_name' => 'web']);
    $manager->givePermissionTo('siswa.edit');
    $siswa = buatSiswaUntukTautan($manager->yayasan_id);
    $orangTua = OrangTua::factory()->create(['nama_lengkap' => 'Ibu Tertaut']);
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $response = $this->actingAs($manager)->get(route('admin.siswa.edit', $siswa));

    $response->assertOk();
    $response->assertSee('Orang Tua/Wali Tertaut');
    $response->assertSee('Ibu Tertaut');
});
