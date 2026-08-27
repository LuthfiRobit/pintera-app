<?php

use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;
use App\Domains\Akademik\Models\KurikulumAssignment;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function buatUserKelas(): array
{
    Permission::firstOrCreate(['name' => 'kelas.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'kelas.create', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'kelas.edit', 'guard_name' => 'web']);

    $role = Role::firstOrCreate(['name' => 'operator_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kelas.view', 'kelas.create', 'kelas.edit']);

    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $tahunAjaran->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    return [$user, $lembaga, $tahunAjaran];
}

it('stores kelas with an assigned fase_id when submitted from form', function () {
    [$user, $lembaga, $ta] = buatUserKelas();
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);

    $this->actingAs($user)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $ta->id,
        'nama' => 'Kelas 1A',
        'tingkat' => '1',
        'fase_id' => $fase->id,
    ])->assertRedirect(route('admin.kelas.index'));

    $kelas = Kelas::where('nama', 'Kelas 1A')->first();
    expect($kelas->fase_id)->toBe($fase->id);
});

it('stores kelas with null fase_id when fase is not selected (optional/manual empty choice)', function () {
    [$user, $lembaga, $ta] = buatUserKelas();

    $this->actingAs($user)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $ta->id,
        'nama' => 'Kelas Tanpa Fase',
        'tingkat' => '1',
        'fase_id' => '',
    ])->assertRedirect(route('admin.kelas.index'));

    $kelas = Kelas::where('nama', 'Kelas Tanpa Fase')->first();
    expect($kelas->fase_id)->toBeNull();
});

it('rejects an invalid fase_id that does not exist in fase table', function () {
    [$user, $lembaga, $ta] = buatUserKelas();

    $this->actingAs($user)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $ta->id,
        'nama' => 'Kelas Invalid Fase',
        'tingkat' => '1',
        'fase_id' => 99999,
    ])->assertSessionHasErrors('fase_id');
});

it('updates existing kelas to assign or change fase_id', function () {
    [$user, $lembaga, $ta] = buatUserKelas();
    $faseA = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $faseB = Fase::create(['kode' => 'b', 'nama' => 'Fase B', 'urutan' => 2]);
    $kelas = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $ta->id,
        'nama' => 'Kelas 1A',
        'tingkat' => '1',
        'fase_id' => $faseA->id,
    ]);

    $this->actingAs($user)->put(route('admin.kelas.update', $kelas), [
        'tahun_ajaran_id' => $ta->id,
        'nama' => 'Kelas 1A (Update)',
        'tingkat' => '1',
        'fase_id' => $faseB->id,
    ])->assertRedirect(route('admin.kelas.index'));

    expect($kelas->fresh()->fase_id)->toBe($faseB->id);
});

it('does not retroactively change an existing Kelas.fase_id when the default mapping is edited afterwards (immutability contract, end-to-end)', function () {
    [$user, $lembaga, $ta] = buatUserKelas();
    $faseA = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $faseB = Fase::create(['kode' => 'b', 'nama' => 'Fase B', 'urutan' => 2]);
    $mapping = FaseDefaultMapping::create(['lembaga_id' => null, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $faseA->id]);

    // Kelas dibuat memakai suggestion saat mapping masih SD+1 -> A.
    $this->actingAs($user)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $ta->id,
        'nama' => 'Kelas 1A',
        'tingkat' => '1',
        'fase_id' => $faseA->id,
    ]);
    $kelasLama = Kelas::where('nama', 'Kelas 1A')->first();

    // Admin platform mengubah kebijakan mapping SD+1 -> B.
    $mapping->update(['fase_id' => $faseB->id]);

    // Kelas lama TIDAK ikut berubah.
    expect($kelasLama->fresh()->fase_id)->toBe($faseA->id);

    // Kelas BARU yang dibuat setelah perubahan mapping mengikuti suggestion baru.
    $this->actingAs($user)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $ta->id,
        'nama' => 'Kelas 1B',
        'tingkat' => '1',
        'fase_id' => $faseB->id,
    ]);
    $kelasBaru = Kelas::where('nama', 'Kelas 1B')->first();

    expect($kelasBaru->fase_id)->toBe($faseB->id);
});
