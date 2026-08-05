<?php
// tests/Feature/Admin/KasusTerhapusViewTest.php

use App\Enums\StatusKasus;
use App\Models\Kasus;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function actingAsKasusTerhapusViewer(Lembaga $lembaga): User
{
    foreach (['kasus.view', 'kasus.lihat-log-akses', 'kasus.hapus', 'kasus.pulihkan'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kasus.view', 'kasus.lihat-log-akses', 'kasus.hapus', 'kasus.pulihkan']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('lists soft-deleted kasus scoped to admin_akademik own lembaga with a restore action', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSendiri = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswaSendiri = Siswa::factory()->create(['lembaga_id' => $lembagaSendiri->id]);
    $siswaLain = Siswa::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $kasusSendiri = Kasus::factory()->create(['siswa_id' => $siswaSendiri->id, 'lembaga_id' => $lembagaSendiri->id, 'status' => StatusKasus::Selesai]);
    $kasusLain = Kasus::factory()->create(['siswa_id' => $siswaLain->id, 'lembaga_id' => $lembagaLain->id, 'status' => StatusKasus::Selesai]);
    $kasusSendiri->delete();
    $kasusLain->delete();

    $viewer = actingAsKasusTerhapusViewer($lembagaSendiri);

    $response = $this->actingAs($viewer)->get(route('admin.kasus.terhapus'));

    $response->assertOk();
    $response->assertSee($siswaSendiri->nama_lengkap);
    $response->assertDontSee($siswaLain->nama_lengkap);
    $response->assertSee(route('admin.kasus.restore', $kasusSendiri));
});

it('does not list a non-deleted kasus on the Kasus Terhapus page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasusAktif = Kasus::factory()->create(['siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id, 'status' => StatusKasus::Berjalan]);

    $viewer = actingAsKasusTerhapusViewer($lembaga);

    $response = $this->actingAs($viewer)->get(route('admin.kasus.terhapus'));

    $response->assertOk();
    $response->assertDontSee($siswa->nama_lengkap);
});

it('a restore button submission brings the kasus back and off the Kasus Terhapus list', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id, 'status' => StatusKasus::Selesai]);
    $kasus->delete();
    $viewer = actingAsKasusTerhapusViewer($lembaga);

    $this->actingAs($viewer)->post(route('admin.kasus.restore', $kasus))->assertRedirect(route('admin.kasus.terhapus'));

    $response = $this->actingAs($viewer)->get(route('admin.kasus.terhapus'));
    $response->assertDontSee($siswa->nama_lengkap);
});
