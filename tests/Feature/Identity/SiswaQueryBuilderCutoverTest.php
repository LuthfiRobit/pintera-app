<?php

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsSiswaManagerForQueryTest(Lembaga $lembaga): User
{
    Permission::firstOrCreate(['name' => 'siswa.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['siswa.view']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('searches siswa by person nama_lengkap, nis, and nisn', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $manager = actingAsSiswaManagerForQueryTest($lembaga);

    $s1 = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'nama_lengkap' => 'Muhammad Fajar', 'nis' => '20261001']);
    $s2 = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'nama_lengkap' => 'Aisyah Putri', 'nis' => '20261002']);

    $response = $this->actingAs($manager)->get(route('admin.siswa.index', ['search' => 'Fajar']));
    $response->assertOk();
    $response->assertSee('Muhammad Fajar');
    $response->assertDontSee('Aisyah Putri');

    $response2 = $this->actingAs($manager)->get(route('admin.siswa.index', ['search' => '20261002']));
    $response2->assertOk();
    $response2->assertSee('Aisyah Putri');
    $response2->assertDontSee('Muhammad Fajar');
});

it('orders siswa by person nama_lengkap correctly', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $s1 = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Zahra']);
    $s2 = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Bagus']);
    $s3 = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Fatimah']);

    $ordered = Siswa::where('lembaga_id', $lembaga->id)->orderByNama()->get();
    expect($ordered->pluck('nama_lengkap')->toArray())->toBe(['Bagus', 'Fatimah', 'Zahra']);
});
