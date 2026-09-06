<?php

// tests/Feature/Admin/KasusTerhapusViewTest.php

use App\Domains\Kasus\Enums\StatusKasus;
use App\Domains\Kasus\Models\Kasus;
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
    $role = Role::firstOrCreate(['name' => 'operator_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kasus.view', 'kasus.lihat-log-akses', 'kasus.hapus', 'kasus.pulihkan']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('lists soft-deleted kasus scoped to operator_akademik own lembaga with a restore action', function () {
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

it('finds a soft-deleted kasus by siswa name or kategori_masalah via search', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Siswa Pencarian Terhapus']);
    $kasus = Kasus::factory()->create(['siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id, 'status' => StatusKasus::Selesai, 'kategori_masalah' => 'Kategori Unik Test']);
    $kasus->delete();
    $viewer = actingAsKasusTerhapusViewer($lembaga);

    $bySiswa = $this->actingAs($viewer)->get(route('admin.kasus.terhapus', ['search' => 'Siswa Pencarian Terhapus']));
    $bySiswa->assertOk();
    $bySiswa->assertSee($siswa->nama_lengkap);

    $byKategori = $this->actingAs($viewer)->get(route('admin.kasus.terhapus', ['search' => 'Kategori Unik Test']));
    $byKategori->assertOk();
    $byKategori->assertSee($siswa->nama_lengkap);
});

it('does not leak another lembaga soft-deleted kasus via search', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSendiri = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswaLain = Siswa::factory()->create(['lembaga_id' => $lembagaLain->id, 'nama_lengkap' => 'Siswa Lembaga Lain Terhapus']);
    $kasusLain = Kasus::factory()->create(['siswa_id' => $siswaLain->id, 'lembaga_id' => $lembagaLain->id, 'status' => StatusKasus::Selesai]);
    $kasusLain->delete();
    $viewer = actingAsKasusTerhapusViewer($lembagaSendiri);

    $response = $this->actingAs($viewer)->get(route('admin.kasus.terhapus', ['search' => 'Siswa Lembaga Lain Terhapus']));

    $response->assertOk();
    $response->assertSee('Tidak Ada Kasus Terhapus');
});

it('clamps an out-of-list per_page value back to the default of 20', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $viewer = actingAsKasusTerhapusViewer($lembaga);

    $response = $this->actingAs($viewer)->get(route('admin.kasus.terhapus', ['per_page' => 999999]));

    $response->assertOk();
    $response->assertViewHas('perPage', 20);
});

it('reports totalTerhapus and dihapusBulanIni scoped to the viewing admin lembaga', function () {
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
    $response->assertViewHas('totalTerhapus', 1);
    $response->assertViewHas('dihapusBulanIni', 1);
});

it('does not leak soft-deleted kasus from a DIFFERENT yayasan to a yayasan_super_admin', function () {
    $yayasanA = Yayasan::factory()->create();
    $yayasanB = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $siswaA = Siswa::factory()->create(['lembaga_id' => $lembagaA->id]);
    $siswaB = Siswa::factory()->create(['lembaga_id' => $lembagaB->id]);
    $kasusA = Kasus::factory()->create(['siswa_id' => $siswaA->id, 'lembaga_id' => $lembagaA->id, 'status' => StatusKasus::Selesai]);
    $kasusB = Kasus::factory()->create(['siswa_id' => $siswaB->id, 'lembaga_id' => $lembagaB->id, 'status' => StatusKasus::Selesai]);
    $kasusA->delete();
    $kasusB->delete();

    Permission::firstOrCreate(['name' => 'kasus.lihat-log-akses', 'guard_name' => 'web']);
    $superAdminRole = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $superAdminRole->givePermissionTo('kasus.lihat-log-akses');
    $superAdminYayasanA = User::factory()->create(['yayasan_id' => $yayasanA->id]);
    $superAdminYayasanA->assignRole($superAdminRole);

    $response = $this->actingAs($superAdminYayasanA)->get(route('admin.kasus.terhapus'));

    $response->assertOk();
    // totalTerhapus reflects the raw size of the (correctly or incorrectly) scoped Kasus
    // query directly — unlike assertSee/assertDontSee on $siswaB->nama_lengkap below, it is
    // NOT masked by Person's own YayasanScope (which independently blanks a cross-yayasan
    // siswa's displayed name to "—" regardless of whether this controller's own query leaks),
    // so it is the assertion that actually proves the fix.
    $response->assertViewHas('totalTerhapus', 1);
    $response->assertSee($siswaA->nama_lengkap);
    $response->assertDontSee($siswaB->nama_lengkap);
});

it('narrows yayasan_super_admin kasus terhapus to the switcher-selected lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaX = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaY = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswaX = Siswa::factory()->create(['lembaga_id' => $lembagaX->id]);
    $siswaY = Siswa::factory()->create(['lembaga_id' => $lembagaY->id]);
    $kasusX = Kasus::factory()->create(['siswa_id' => $siswaX->id, 'lembaga_id' => $lembagaX->id, 'status' => StatusKasus::Selesai]);
    $kasusY = Kasus::factory()->create(['siswa_id' => $siswaY->id, 'lembaga_id' => $lembagaY->id, 'status' => StatusKasus::Selesai]);
    $kasusX->delete();
    $kasusY->delete();

    Permission::firstOrCreate(['name' => 'kasus.lihat-log-akses', 'guard_name' => 'web']);
    $superAdminRole = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $superAdminRole->givePermissionTo('kasus.lihat-log-akses');
    $superAdmin = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $superAdmin->assignRole($superAdminRole);

    session(['active_lembaga_id' => $lembagaX->id]);
    $response = $this->actingAs($superAdmin)->get(route('admin.kasus.terhapus'));

    $response->assertOk();
    $response->assertSee($siswaX->nama_lengkap);
    $response->assertDontSee($siswaY->nama_lengkap);
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
