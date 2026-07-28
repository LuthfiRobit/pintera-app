<?php

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\PolaJam;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsKelasManager(Lembaga $lembaga): User
{
    foreach (['kelas.view', 'kelas.create', 'kelas.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kelas.view', 'kelas.create', 'kelas.edit']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access to a user without kelas.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.kelas.index'))->assertForbidden();
});

it('creates a kelas', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKelasManager($lembaga);

    $this->actingAs($manager)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => '6A',
        'tingkat' => '6',
    ])->assertRedirect(route('admin.kelas.index'));

    expect(Kelas::where('nama', '6A')->exists())->toBeTrue();
});

it('offers only guru belonging to the current lembaga as wali kelas options', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKelasManager($lembagaA);

    $guruA = Guru::factory()->create(['lembaga_id' => $lembagaA->id]);
    Guru::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembagaB->id])->id,
        'lembaga_id' => $lembagaB->id,
        'nik' => '3201234567891111',
        'nama' => 'Guru Lembaga B',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    $response = $this->actingAs($manager)->get(route('admin.kelas.create'));

    $response->assertViewHas('guruList', function ($guruList) use ($guruA) {
        return $guruList->count() === 1 && $guruList->first()->id === $guruA->id;
    });
});

it('rejects creating a kelas with a tahun_ajaran belonging to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $manager = actingAsKelasManager($lembagaSaya);

    $this->actingAs($manager)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $tahunAjaranLain->id,
        'nama' => 'Kelas Campur Lembaga',
        'tingkat' => '6',
    ])->assertNotFound();

    expect(Kelas::where('nama', 'Kelas Campur Lembaga')->exists())->toBeFalse();
});

it('rejects creating a kelas with a wali_kelas_guru_id belonging to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $guruLain = Guru::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembagaLain->id])->id,
        'lembaga_id' => $lembagaLain->id,
        'nik' => '3201234567892222',
        'nama' => 'Guru Lain Lembaga',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);
    $manager = actingAsKelasManager($lembagaSaya);

    $this->actingAs($manager)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Kelas Wali Campur',
        'tingkat' => '6',
        'wali_kelas_guru_id' => $guruLain->id,
    ])->assertNotFound();

    expect(Kelas::where('nama', 'Kelas Wali Campur')->exists())->toBeFalse();
});

it('rejects creating a kelas with a pola_jam_id belonging to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $polaLain = PolaJam::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $manager = actingAsKelasManager($lembagaSaya);

    $this->actingAs($manager)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Kelas Pola Campur',
        'tingkat' => '6',
        'pola_jam_id' => $polaLain->id,
    ])->assertNotFound();

    expect(Kelas::where('nama', 'Kelas Pola Campur')->exists())->toBeFalse();
});

it('rejects updating a kelas to a tahun_ajaran belonging to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranSaya = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $manager = actingAsKelasManager($lembagaSaya);
    $kelas = Kelas::create(['lembaga_id' => $lembagaSaya->id, 'tahun_ajaran_id' => $tahunAjaranSaya->id, 'nama' => '6A']);

    $this->actingAs($manager)->put(route('admin.kelas.update', $kelas), [
        'tahun_ajaran_id' => $tahunAjaranLain->id,
        'nama' => '6A',
        'tingkat' => '6',
    ])->assertNotFound();

    expect($kelas->fresh()->tahun_ajaran_id)->toBe($tahunAjaranSaya->id);
});

it('rejects updating a kelas with a wali_kelas_guru_id belonging to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $manager = actingAsKelasManager($lembagaSaya);
    $kelas = Kelas::create(['lembaga_id' => $lembagaSaya->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '6A']);
    $guruLain = Guru::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembagaLain->id])->id,
        'lembaga_id' => $lembagaLain->id,
        'nik' => '3201234567893333',
        'nama' => 'Guru Lain Lembaga Dua',
        'jenis_kelamin' => 'L',
        'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    $this->actingAs($manager)->put(route('admin.kelas.update', $kelas), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => '6A',
        'tingkat' => '6',
        'wali_kelas_guru_id' => $guruLain->id,
    ])->assertNotFound();

    expect($kelas->fresh()->wali_kelas_guru_id)->toBeNull();
});

it('updates a kelas including assigning a wali kelas', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKelasManager($lembaga);
    $kelas = Kelas::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '6A']);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->put(route('admin.kelas.update', $kelas), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => '6A',
        'tingkat' => '6',
        'wali_kelas_guru_id' => $guru->id,
    ])->assertRedirect(route('admin.kelas.index'));

    expect($kelas->fresh()->wali_kelas_guru_id)->toBe($guru->id);
});
