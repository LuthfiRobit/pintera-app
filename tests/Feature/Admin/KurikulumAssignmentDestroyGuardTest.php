<?php

use App\Domains\Akademik\Enums\KurikulumFramework;
use App\Domains\Akademik\Models\KurikulumAssignment;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('menolak hapus Kurikulum Assignment yang masih dipakai Kelas', function () {
    $kelas = Kelas::factory()->create(['kurikulum' => KurikulumFramework::Merdeka]);

    $assignment = KurikulumAssignment::create([
        'lembaga_id' => $kelas->lembaga_id,
        'tahun_ajaran_id' => $kelas->tahun_ajaran_id,
        'bentuk_pendidikan' => $kelas->lembaga->bentuk_pendidikan,
        'tingkat' => $kelas->tingkat,
        'kurikulum' => KurikulumFramework::Merdeka,
    ]);

    $user = User::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $user->givePermissionTo('kurikulum-assignment.delete');

    $response = $this->actingAs($user)->delete(route('admin.kurikulum-assignment.destroy', $assignment));

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(KurikulumAssignment::find($assignment->id))->not->toBeNull();
});

it('tetap bisa hapus Kurikulum Assignment yang tidak dipakai Kelas manapun', function () {
    $assignment = KurikulumAssignment::factory()->create();

    $user = User::factory()->create(['lembaga_id' => $assignment->lembaga_id]);
    $user->givePermissionTo('kurikulum-assignment.delete');

    $response = $this->actingAs($user)->delete(route('admin.kurikulum-assignment.destroy', $assignment));

    $response->assertRedirect(route('admin.kurikulum-assignment.index'));
    expect(KurikulumAssignment::find($assignment->id))->toBeNull();
});

it('menolak hapus Kurikulum Assignment lembaga lain oleh aktor yayasan meski active_lembaga_id-nya beda (TenantScope tidak boleh menyamarkan guard)', function () {
    $lembagaA = Lembaga::factory()->create();
    $lembagaB = Lembaga::factory()->create();

    $kelasB = Kelas::factory()->create([
        'lembaga_id' => $lembagaB->id,
        'kurikulum' => KurikulumFramework::Merdeka,
    ]);

    $assignmentB = KurikulumAssignment::create([
        'lembaga_id' => $lembagaB->id,
        'tahun_ajaran_id' => $kelasB->tahun_ajaran_id,
        'bentuk_pendidikan' => $kelasB->lembaga->bentuk_pendidikan,
        'tingkat' => $kelasB->tingkat,
        'kurikulum' => KurikulumFramework::Merdeka,
    ]);

    $userYayasan = User::factory()->create(['lembaga_id' => null]);
    $userYayasan->assignRole('yayasan_super_admin');

    session(['active_lembaga_id' => $lembagaA->id]);

    $response = $this->actingAs($userYayasan)->delete(route('admin.kurikulum-assignment.destroy', $assignmentB));

    $response->assertRedirect(route('admin.kurikulum-assignment.index'));
    $response->assertSessionHas('error');
    expect(KurikulumAssignment::find($assignmentB->id))->not->toBeNull();
});
