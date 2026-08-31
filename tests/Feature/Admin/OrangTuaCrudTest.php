<?php

use App\Domains\Identity\Models\Person;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use App\Services\AkunOrangTuaGenerator;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

function orangTuaFormPayload(array $overrides = []): array
{
    return array_merge([
        'nik' => '3201234567894444',
        'nama_lengkap' => 'Wali Murid Baru',
        'no_hp' => '081234500001',
        'email' => 'wali.baru@example.test',
        'alamat' => 'Jl. Contoh No. 5',
        'pekerjaan' => 'Karyawan Swasta',
    ], $overrides);
}

// Identity data (nama_lengkap, nik, ...) now lives on Person, not on the orang_tua legacy
// columns (no dual-write — see AkunOrangTuaGenerator::buat()), so tests must look OrangTua up
// via its person relation instead of `OrangTua::where('nik', ...)` / `where('nama_lengkap', ...)`.
// Scopes are bypassed deliberately: this is a test-assertion lookup, not an exercise of tenant
// policy, and `actingAsOrangTuaManager()`'s manager has no yayasan_id/lembaga_id, which would
// otherwise fail Person's YayasanScope closed (`whereHas` would never match).
function findOrangTuaByNik(string $nik): ?OrangTua
{
    $person = Person::withoutGlobalScopes()->where('nik_hash', hash('sha256', $nik))->first();

    return $person ? OrangTua::withoutGlobalScopes()->where('person_id', $person->id)->first() : null;
}

it('denies access to a user without orang-tua.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.orang-tua.index'))->assertForbidden();
});

it('creates both a User account and an OrangTua profile, with NIK as username and hashed password', function () {
    $manager = actingAsOrangTuaManager();

    $this->actingAs($manager)->post(route('admin.orang-tua.store'), orangTuaFormPayload())
        ->assertRedirect(route('admin.orang-tua.index'));

    $orangTua = findOrangTuaByNik('3201234567894444');
    expect($orangTua)->not->toBeNull();
    expect($orangTua->nik)->toBe('3201234567894444');

    $user = $orangTua->user;
    expect($user->username)->toBe('3201234567894444');
    expect($user->lembaga_id)->toBeNull();
    expect(Hash::check('3201234567894444', $user->password))->toBeTrue();
    expect($user->hasRole('orang_tua'))->toBeTrue();
});

it('rejects creating an orang tua with a NIK that is not exactly 16 digits', function () {
    $manager = actingAsOrangTuaManager();

    $this->actingAs($manager)->post(route('admin.orang-tua.store'), orangTuaFormPayload(['nik' => '12345']))
        ->assertSessionHasErrors('nik');

    expect(OrangTua::query()->exists())->toBeFalse();
});

it('rejects creating an orang tua with an empty no_hp', function () {
    $manager = actingAsOrangTuaManager();

    $this->actingAs($manager)->post(route('admin.orang-tua.store'), orangTuaFormPayload(['no_hp' => '']))
        ->assertSessionHasErrors('no_hp');
});

it('does not create a duplicate User when the NIK is already registered, and redirects to the existing profile', function () {
    $manager = actingAsOrangTuaManager();

    $this->actingAs($manager)->post(route('admin.orang-tua.store'), orangTuaFormPayload())->assertRedirect();
    $existing = findOrangTuaByNik('3201234567894444');

    $response = $this->actingAs($manager)->post(route('admin.orang-tua.store'), orangTuaFormPayload([
        'nama_lengkap' => 'Nama Berbeda',
    ]));

    $response->assertRedirect(route('admin.orang-tua.edit', $existing));
    expect(Person::withoutGlobalScopes()->where('nik_hash', hash('sha256', '3201234567894444'))->count())->toBe(1);
    expect(User::where('username', '3201234567894444')->count())->toBe(1);
});

it('returns a validation error instead of crashing when the NIK belongs to a User with no OrangTua profile', function () {
    $manager = actingAsOrangTuaManager();

    User::factory()->create(['username' => '3201234567894444']);

    $response = $this->actingAs($manager)->post(route('admin.orang-tua.store'), orangTuaFormPayload());

    $response->assertSessionHasErrors('nik');
    expect(findOrangTuaByNik('3201234567894444'))->toBeNull();
    expect(User::where('username', '3201234567894444')->count())->toBe(1);
});

it('updates the orang tua profile and the linked user name, without touching nik or password', function () {
    $manager = actingAsOrangTuaManager();
    $this->actingAs($manager)->post(route('admin.orang-tua.store'), orangTuaFormPayload())->assertRedirect();
    $orangTua = findOrangTuaByNik('3201234567894444');
    $originalPasswordHash = $orangTua->user->password;

    $this->actingAs($manager)->put(route('admin.orang-tua.update', $orangTua), [
        'nama_lengkap' => 'Nama Diperbarui',
        'no_hp' => '089900001111',
        'email' => 'updated@example.test',
        'alamat' => 'Alamat Baru',
        'pekerjaan' => 'Wiraswasta',
    ])->assertRedirect(route('admin.orang-tua.index'));

    $orangTua->refresh();
    expect($orangTua->nama_lengkap)->toBe('Nama Diperbarui');
    expect($orangTua->no_hp)->toBe('089900001111');
    expect($orangTua->nik)->toBe('3201234567894444');
    expect($orangTua->user->name)->toBe('Nama Diperbarui');
    expect($orangTua->user->password)->toBe($originalPasswordHash);
});

it('denies edit access to a user without orang-tua.edit permission', function () {
    $manager = actingAsOrangTuaManager();
    $this->actingAs($manager)->post(route('admin.orang-tua.store'), orangTuaFormPayload())->assertRedirect();
    $orangTua = findOrangTuaByNik('3201234567894444');

    $this->actingAs(User::factory()->create())->get(route('admin.orang-tua.edit', $orangTua))->assertForbidden();
});

it('toggles an orang tua account status and reflects it on the index page', function () {
    $manager = actingAsOrangTuaManager();
    $this->actingAs($manager)->post(route('admin.orang-tua.store'), orangTuaFormPayload())->assertRedirect();
    $orangTua = findOrangTuaByNik('3201234567894444');
    expect($orangTua->user->is_active)->toBeTrue();

    $this->actingAs($manager)
        ->patch(route('admin.orang-tua.update-status', $orangTua), ['is_active' => '0'])
        ->assertRedirect(route('admin.orang-tua.index'));

    $orangTua->refresh();
    expect($orangTua->user->is_active)->toBeFalse();

    $this->actingAs($manager)->get(route('admin.orang-tua.index'))->assertOk()->assertSee('Non-aktif');

    $this->actingAs($manager)
        ->patch(route('admin.orang-tua.update-status', $orangTua), ['is_active' => '1'])
        ->assertRedirect(route('admin.orang-tua.index'));

    $orangTua->refresh();
    expect($orangTua->user->is_active)->toBeTrue();
});

it('denies status toggle to a user without orang-tua.edit permission', function () {
    $manager = actingAsOrangTuaManager();
    $this->actingAs($manager)->post(route('admin.orang-tua.store'), orangTuaFormPayload())->assertRedirect();
    $orangTua = findOrangTuaByNik('3201234567894444');

    $this->actingAs(User::factory()->create())
        ->patch(route('admin.orang-tua.update-status', $orangTua), ['is_active' => '0'])
        ->assertForbidden();
});

it('shows an operator_akademik an orang tua that has no linked siswa yet', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsOrangTuaManager();
    $manager->update(['lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->post(route('admin.orang-tua.store'), orangTuaFormPayload())->assertRedirect();

    $response = $this->actingAs($manager)->get(route('admin.orang-tua.index'));
    $response->assertOk()->assertSee('Wali Murid Baru');
});

it('shows an operator_akademik an orang tua linked to a siswa in their own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsOrangTuaManager();
    $manager->update(['lembaga_id' => $lembaga->id]);

    $orangTua = app(AkunOrangTuaGenerator::class)->buat('Wali Lembaga Sendiri', '3201234567897777', '081234567800');
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $response = $this->actingAs($manager)->get(route('admin.orang-tua.index'));
    $response->assertOk()->assertSee('Wali Lembaga Sendiri');
});

it('hides from an operator_akademik an orang tua linked only to siswa in a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSendiri = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsOrangTuaManager();
    $manager->update(['lembaga_id' => $lembagaSendiri->id]);

    $orangTua = app(AkunOrangTuaGenerator::class)->buat('Wali Lembaga Lain', '3201234567896666', '081234567801');
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $response = $this->actingAs($manager)->get(route('admin.orang-tua.index'));
    $response->assertOk()->assertDontSee('Wali Lembaga Lain');
});

it('lets yayasan_super_admin see an orang tua regardless of which lembaga their siswa belongs to', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $orangTua = app(AkunOrangTuaGenerator::class)->buat('Wali Terlihat Super Admin', '3201234567895555', '081234567802');
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    Permission::firstOrCreate(['name' => 'orang-tua.view', 'guard_name' => 'web']);
    $superAdminRole = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $superAdminRole->givePermissionTo('orang-tua.view');
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole($superAdminRole);

    $response = $this->actingAs($superAdmin)->get(route('admin.orang-tua.index'));
    $response->assertOk()->assertSee('Wali Terlihat Super Admin');
});

it('404s an operator_akademik trying to edit an orang tua linked only to siswa in a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSendiri = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsOrangTuaManager();
    $manager->update(['lembaga_id' => $lembagaSendiri->id]);

    $orangTua = app(AkunOrangTuaGenerator::class)->buat('Wali Tidak Boleh Diedit', '3201234567894321', '081234567803');
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $this->actingAs($manager)->get(route('admin.orang-tua.edit', $orangTua))->assertNotFound();
    $this->actingAs($manager)->put(route('admin.orang-tua.update', $orangTua), [
        'nama_lengkap' => 'Percobaan Ubah', 'no_hp' => '080000000000',
    ])->assertNotFound();
    $this->actingAs($manager)->patch(route('admin.orang-tua.update-status', $orangTua), [
        'is_active' => '0',
    ])->assertNotFound();
});

it('allows an operator_akademik to edit an orang tua that has no linked siswa yet', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsOrangTuaManager();
    $manager->update(['lembaga_id' => $lembaga->id]);

    $orangTua = app(AkunOrangTuaGenerator::class)->buat('Wali Belum Tertaut', '3201234567891234', '081234567804');

    $this->actingAs($manager)->get(route('admin.orang-tua.edit', $orangTua))->assertOk();
});

it('blocks a yayasan_super_admin from viewing or mutating an OrangTua belonging to a different yayasan (cross-tenant IDOR)', function () {
    $yayasanA = Yayasan::factory()->create();
    $yayasanB = Yayasan::factory()->create();

    $orangTuaB = OrangTua::factory()->create([
        'person_id' => Person::factory()->create([
            'yayasan_id' => $yayasanB->id,
            'nama_lengkap' => 'Wali Yayasan B',
            'no_hp' => '081200000099',
        ])->id,
    ]);

    Permission::firstOrCreate(['name' => 'orang-tua.edit', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'orang-tua.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['orang-tua.edit', 'orang-tua.view']);

    $admin = User::factory()->create(['yayasan_id' => $yayasanA->id]);
    $admin->assignRole($role);

    $this->actingAs($admin)->get(route('admin.orang-tua.edit', $orangTuaB))->assertNotFound();

    $this->actingAs($admin)->put(route('admin.orang-tua.update', $orangTuaB), [
        'nama_lengkap' => 'Diambil Alih Yayasan A',
        'no_hp' => '089999999999',
    ])->assertNotFound();

    $this->actingAs($admin)->patch(route('admin.orang-tua.update-status', $orangTuaB), [
        'is_active' => '0',
    ])->assertNotFound();

    // Read the underlying Person unscoped for the assertion: the accessor above proxies to
    // `$this->person`, which is itself governed by Person's own YayasanScope — since we're
    // still acting as the Yayasan A admin, that scope alone would hide Yayasan B's Person and
    // make this assertion vacuously pass even if the row HAD been mutated. Bypassing scopes
    // here is what actually proves the row was left untouched.
    $unscopedPerson = Person::withoutGlobalScopes()->findOrFail($orangTuaB->person_id);
    expect($unscopedPerson->nama_lengkap)->toBe('Wali Yayasan B');
    expect($unscopedPerson->no_hp)->toBe('081200000099');
});

it('logs an orang_tua user into a placeholder dashboard instead of a 500 error', function () {
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $orangTua = app(AkunOrangTuaGenerator::class)->buat('Wali Uji Coba', '3201234567898888', '081234567890');
    $orangTua->user->update(['must_change_password' => false]);

    $response = $this->actingAs($orangTua->user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Selamat datang');
});
