<?php

use App\Models\FormulirField;
use App\Models\JawabanFormulirPendaftaran;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function buatJalurUntukFormulir(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    foreach (['formulir-field.create', 'formulir-field.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['formulir-field.create', 'formulir-field.delete']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Tahfidz']);

    return [$lembaga, $user, $jalur];
}

it('adds a text field to a jalur', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukFormulir();

    $this->actingAs($user)->post(route('admin.formulir-field.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'label' => 'Jumlah Juz Hafalan',
        'field_type' => 'number',
        'is_required' => '1',
    ])->assertRedirect(route('admin.jalur-ppdb.edit', $jalur));

    $field = FormulirField::first();
    expect($field->label)->toBe('Jumlah Juz Hafalan');
    expect($field->is_required)->toBeTrue();
    expect($field->urutan)->toBe(0);
});

it('auto-increments urutan for successive fields on the same jalur', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukFormulir();
    $this->actingAs($user);

    $this->post(route('admin.formulir-field.store'), ['jalur_ppdb_id' => $jalur->id, 'label' => 'Field A', 'field_type' => 'text']);
    $this->post(route('admin.formulir-field.store'), ['jalur_ppdb_id' => $jalur->id, 'label' => 'Field B', 'field_type' => 'text']);

    expect(FormulirField::where('label', 'Field A')->first()->urutan)->toBe(0);
    expect(FormulirField::where('label', 'Field B')->first()->urutan)->toBe(1);
});

it('requires at least two options for a select field', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukFormulir();

    $this->actingAs($user)->post(route('admin.formulir-field.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'label' => 'Pilihan Ekstrakurikuler',
        'field_type' => 'select',
        'options' => 'Pramuka',
    ])->assertSessionHasErrors('options');

    expect(FormulirField::count())->toBe(0);
});

it('saves select options as a json array', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukFormulir();

    $this->actingAs($user)->post(route('admin.formulir-field.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'label' => 'Pilihan Ekstrakurikuler',
        'field_type' => 'select',
        'options' => "Pramuka\nPaskibra\nPMR",
    ]);

    expect(FormulirField::first()->options)->toBe(['Pramuka', 'Paskibra', 'PMR']);
});

it('rejects a formulir field targeting a jalur in another lembaga', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukFormulir();
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    $otherTahun = TahunAjaran::withoutGlobalScopes()->create([
        'lembaga_id' => $otherLembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $otherJalur = JalurPpdb::withoutGlobalScopes()->create([
        'lembaga_id' => $otherLembaga->id, 'tahun_ajaran_id' => $otherTahun->id, 'nama' => 'Reguler',
    ]);

    $this->actingAs($user)->post(route('admin.formulir-field.store'), [
        'jalur_ppdb_id' => $otherJalur->id,
        'label' => 'Field',
        'field_type' => 'text',
    ])->assertSessionHasErrors('jalur_ppdb_id');

    expect(FormulirField::count())->toBe(0);
});

it('deletes a formulir field', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukFormulir();
    $this->actingAs($user);
    $field = FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Field A', 'field_type' => 'text']);

    $this->delete(route('admin.formulir-field.destroy', $field))->assertRedirect(route('admin.jalur-ppdb.edit', $jalur));

    expect(FormulirField::find($field->id))->toBeNull();
});

it('denies access without the manage-ppdb permission', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukFormulir();
    $noRoleUser = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($noRoleUser)->post(route('admin.formulir-field.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'label' => 'Field',
        'field_type' => 'text',
    ])->assertForbidden();
});

it('exposes the jawabanFormulir relation with real registrant answer data', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukFormulir();
    $field = FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Field A', 'field_type' => 'text']);
    [, , , $pendaftaran] = buatPendaftaranUntukAdmin($lembaga);

    JawabanFormulirPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id,
        'formulir_field_id' => $field->id,
        'nilai' => 'jawaban contoh',
    ]);

    expect($field->jawabanFormulir()->count())->toBe(1);
});

it('rejects deleting a formulir field that already has a registrant answer', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukFormulir();
    $field = FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Field A', 'field_type' => 'text']);
    [, , , $pendaftaran] = buatPendaftaranUntukAdmin($lembaga);
    JawabanFormulirPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id, 'formulir_field_id' => $field->id, 'nilai' => 'jawaban',
    ]);

    $this->actingAs($user)->delete(route('admin.formulir-field.destroy', $field))
        ->assertSessionHasErrors('formulir_field');

    expect(FormulirField::find($field->id))->not->toBeNull();
});

it('names the related answer count in the deletion error message', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukFormulir();
    $field = FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Field A', 'field_type' => 'text']);
    [, , , $pendaftaran] = buatPendaftaranUntukAdmin($lembaga);
    JawabanFormulirPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id, 'formulir_field_id' => $field->id, 'nilai' => 'jawaban',
    ]);

    $this->actingAs($user)->delete(route('admin.formulir-field.destroy', $field));

    expect(session('errors')->get('formulir_field')[0])->toContain('1 jawaban');
});

it('responds with JSON on store when requested', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukFormulir();

    $response = $this->actingAs($user)->postJson(route('admin.formulir-field.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'label' => 'Field Baru',
        'field_type' => 'text',
    ]);

    $response->assertCreated();
    expect($response->json('data.label'))->toBe('Field Baru');
});

it('responds with a JSON 422 including field errors for the select-options rule when requested via AJAX', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukFormulir();

    $response = $this->actingAs($user)->postJson(route('admin.formulir-field.store'), [
        'jalur_ppdb_id' => $jalur->id,
        'label' => 'Pilihan',
        'field_type' => 'select',
        'options' => 'Satu',
    ]);

    $response->assertStatus(422);
    expect($response->json('errors.options.0'))->toContain('minimal 2 opsi');
});

it('responds with a JSON 422 and the correct message when a blocked deletion is requested via AJAX', function () {
    [$lembaga, $user, $jalur] = buatJalurUntukFormulir();
    $field = FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Field A', 'field_type' => 'text']);
    [, , , $pendaftaran] = buatPendaftaranUntukAdmin($lembaga);
    JawabanFormulirPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id, 'formulir_field_id' => $field->id, 'nilai' => 'jawaban',
    ]);

    $response = $this->actingAs($user)->deleteJson(route('admin.formulir-field.destroy', $field));

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('1 jawaban');
});
